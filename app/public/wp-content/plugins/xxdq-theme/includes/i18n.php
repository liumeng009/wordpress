<?php
/**
 * XXDQ i18n — language context layer.
 *
 * Responsibilities
 * ----------------
 *   1. Resolve the current language of the current request (zh_CN | en_US).
 *   2. Provide a translation lookup helper `xxdq_t()`.
 *   3. Provide URL helpers that build language-correct links.
 *   4. Expose the language on <html lang>, body class and Open Graph locale.
 *
 * Design constraints
 * ------------------
 *   - The Chinese site must render BYTE-IDENTICAL to before this layer
 *     existed. Everything defaults to zh_CN, and zh_CN strings live in
 *     lang/zh_CN.php. Nothing outside this file should branch on the
 *     language with an `if` — use `xxdq_t('key')` instead.
 *   - The English site lives under the `/en/` prefix on the SAME install
 *     (no Polylang/WPML). Content is stored as parallel post objects
 *     (post_name + '_en', linked via post_parent) and parallel terms
 *     (term meta `name_en` / `desc_en`).
 *
 * @package XXDQ
 */

if (!defined('ABSPATH')) exit;

define('XXDQ_LANG_DEFAULT', 'zh_CN');
define('XXDQ_LANG_EN_PREFIX', '/en');
define('XXDQ_I18N_DIR', plugin_dir_path(__FILE__) . 'lang');

/* ── 1. Language resolution ───────────────────────────────────── */

class XXDQ_I18n {

    /** @var string|null Cached resolved language for this request. */
    private static $lang = null;

    /** @var bool Whether the current request is for the English site. */
    private static $is_en = false;

    /**
     * Detect the language from the request path.
     *
     * Detection order (first match wins):
     *   1. An explicit `?lang=en` query arg — used by the language
     *      switcher and by the admin-side preview links.
     *   2. The `/en/...` path prefix.
     *   3. Default: zh_CN.
     *
     * We deliberately do NOT use the cookie or the browser Accept-Language
     * header: the English content is a SEPARATE set of posts, so silently
     * switching a visitor's language without changing the URL would serve
     * the wrong content for the URL they are on. The URL is the source of
     * truth; the cookie is only used to remember the preference for the
     * switcher link.
     *
     * @return string 'zh_CN' or 'en_US'
     */
    public static function lang() {
        if (self::$lang !== null) {
            return self::$lang;
        }

        $lang = XXDQ_LANG_DEFAULT;

        // 1. Explicit override (language switcher / admin preview).
        if (isset($_GET['lang']) && $_GET['lang'] === 'en') {
            $lang = 'en_US';
        } elseif (isset($_GET['lang']) && $_GET['lang'] === 'zh') {
            $lang = 'zh_CN';
        } else {
            // 2. Path prefix. We read the raw REQUEST_URI rather than
            //    get_page_by_path() because this runs on `init`, before
            //    the rewrite rules have been flushed and before we know
            //    what the URL resolves to.
            $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
            $path = parse_url($uri, PHP_URL_PATH);
            if (is_string($path)) {
                $path = '/' . ltrim($path, '/');
                if ($path === '/en' || strpos($path, '/en/') === 0) {
                    $lang = 'en_US';
                }
            }
        }

        self::$lang     = $lang;
        self::$is_en    = ($lang === 'en_US');
        return self::$lang;
    }

    /**
     * Are we rendering the English site?
     */
    public static function is_en() {
        self::lang();
        return self::$is_en;
    }

    /** Convenience inverse of is_en(). */
    public static function is_zh() {
        return !self::is_en();
    }

    /**
     * The current language's locale string, for Open Graph / schema.
     */
    public static function locale() {
        return self::is_en() ? 'en_US' : 'zh_CN';
    }

    /**
     * The current language's HTML lang attribute value.
     */
    public static function html_lang() {
        return self::is_en() ? 'en' : 'zh-CN';
    }

    /**
     * BCP-47 tag used inside JSON-LD (schema.org `inLanguage`).
     */
    public static function schema_lang() {
        return self::is_en() ? 'en-US' : 'zh-CN';
    }

    /* ── URL helpers ───────────────────────────────────────────── */

    /**
     * Build a site URL in the current language.
     *
     * @param string $path Path relative to the site root, e.g. '/product/'.
     * @return string
     */
    public static function url($path = '/') {
        $path = '/' . ltrim((string) $path, '/');
        if (self::is_en()) {
            return home_url('/en' . ($path === '/' ? '/' : $path));
        }
        return home_url($path);
    }

    /**
     * Build a URL for a specific language (used by the language switcher).
     *
     * @param string $lang Target language.
     * @param string $path Path relative to the site root.
     * @return string
     */
    public static function url_for($lang, $path = '/') {
        $path = '/' . ltrim((string) $path, '/');
        if ($lang === 'en_US') {
            return home_url('/en' . ($path === '/' ? '/' : $path));
        }
        return home_url($path);
    }

    /**
     * Swap the language prefix of the current URL.
     *
     * Used by the language switcher so that "English" on
     * /product/copper-lug/ points at /en/product/copper-lug/ and
     * "中文" on /en/product/copper-lug/ points at /product/copper-lug/.
     *
     * A naive prefix swap is NOT always correct. The English parallel
     * of a Chinese article keeps its OWN slug — the titles were
     * rewritten during translation, so "冷压端子压接标准" lives at
     * /news/cold-terminal-crimp-standard/ but its English counterpart
     * lives at /en/news/crimp-terminal-crimping-standards/. Swapping
     * the prefix alone sends the visitor to a 404.
     *
     * The two languages are linked through the `_xxdq_zh_id` /
     * `_xxdq_en_id` post meta, so the counterpart post is looked up
     * first and its real permalink used. Only when no counterpart
     * exists does the code fall back to the plain prefix swap.
     *
     * @param string $target_lang Target language.
     * @return string
     */
    public static function switch_url($target_lang) {
        $uri  = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';

        $en = self::is_en();

        /* Same language — nothing to switch to. */
        if (($target_lang === 'en_US') === $en) {
            return home_url($path);
        }

        /* A single article/page has a counterpart with its own slug —
         * use that post's real permalink. */
        $counterpart = self::counterpart_permalink($target_lang);
        if ($counterpart !== '') {
            return $counterpart;
        }

        if ($target_lang === 'en_US') {
            return self::url_for('en_US', $path);
        }

        // Remove the /en prefix.
        $stripped = preg_replace('#^/en(/|$)#', '/', $path);
        return home_url($stripped === '' ? '/' : $stripped);
    }

    /**
     * The permalink of the current post in the target language.
     *
     * @param string $target_lang Target language ('en_US' or 'zh_CN').
     * @return string Empty when there is no counterpart post.
     */
    private static function counterpart_permalink($target_lang) {
        if (!function_exists('is_singular') || !is_singular()) {
            return '';
        }

        $post_id = get_the_ID();
        if (!$post_id) {
            return '';
        }

        /* On the English site the counterpart is the Chinese original. */
        if ($target_lang === 'zh_CN') {
            $zh_id = (int) get_post_meta($post_id, '_xxdq_zh_id', true);
            if (!$zh_id) {
                return '';
            }
            $target = get_post($zh_id);
            if (!$target || $target->post_status !== 'publish') {
                return '';
            }
            return self::permalink_for($target, 'zh_CN');
        }

        /* On the Chinese site the counterpart is the English parallel. */
        $en_id = (int) get_post_meta($post_id, '_xxdq_en_id', true);
        if (!$en_id) {
            return '';
        }
        $target = get_post($en_id);
        if (!$target || $target->post_status !== 'publish') {
            return '';
        }
        return self::permalink_for($target, 'en_US');
    }

    /**
     * The public permalink of a post in a given language.
     *
     * The `_xxdq_en_path` meta holds the exact public path of the
     * English parallel ("news/do-you-need-crimp-terminals/"). It is
     * the authoritative value — the English slug is NOT derived from
     * the post_name (the post_name carries an "-en" / "_en" suffix
     * that never appears in the URL), and the Chinese post's own slug
     * differs from the English one for every translated article.
     *
     * On the Chinese side `get_permalink()` is already correct: the
     * Chinese slug of a product, an article or a page IS the public
     * slug (percent-encoded for the Chinese ones).
     *
     * @param WP_Post $post  The post.
     * @param string  $lang  Language ('en_US' or 'zh_CN').
     * @return string
     */
    private static function permalink_for($post, $lang) {
        if ($lang === 'zh_CN') {
            $link = get_permalink($post);
            return $link ? $link : home_url('/');
        }

        $path = (string) get_post_meta($post->ID, '_xxdq_en_path', true);
        if ($path !== '') {
            return home_url('/en/' . ltrim($path, '/'));
        }

        /* An EMPTY `_xxdq_en_path` means the English parallel IS the
         * home page — its path is "/en/" and there is nothing after
         * the prefix. */
        if ($post->post_type === 'page') {
            return home_url('/en/');
        }

        /* No stored path — fall back to the post name minus the
         * language suffix. */
        $slug = preg_replace('/[-_]en$/', '', $post->post_name);
        $type = $post->post_type === 'product' ? 'product' : $post->post_type;
        return home_url('/en/' . $type . '/' . $slug . '/');
    }

    /* ── Translation lookup ────────────────────────────────────── */

    /**
     * Translate a key into the current language.
     *
     * @param string $key      Translation key.
     * @param array  $args     Optional sprintf-style placeholders.
     * @param string $fallback Fallback when the key is missing.
     * @return string
     */
    public static function t($key, $args = [], $fallback = '') {
        $catalog = self::catalog();
        $text = isset($catalog[$key]) ? $catalog[$key] : $fallback;

        // A missing key in the English catalog is a bug — surface it in
        // the debug log rather than silently printing the key.
        if ($text === '' && $fallback === '' && !isset($catalog[$key])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[xxdq-i18n] missing translation key: ' . $key);
            }
            return $key;
        }

        if (!empty($args) && is_array($args)) {
            $text = vsprintf($text, $args);
        }
        return $text;
    }

    /**
     * Load and cache the translation catalog for the current language.
     *
     * @return array
     */
    public static function catalog() {
        static $loaded = [];
        if (isset($loaded[self::lang()])) {
            return $loaded[self::lang()];
        }

        $lang = self::lang();
        $file = XXDQ_I18N_DIR . '/' . $lang . '.php';
        $data = [];
        if (file_exists($file)) {
            $data = require $file;
            if (!is_array($data)) {
                $data = [];
            }
        }

        // The English catalog falls back to the Chinese one so that any
        // key we have not translated yet renders in Chinese instead of
        // printing the raw key name. Keys that need translating are
        // listed in the translation-notes doc.
        if ($lang === 'en_US' && file_exists(XXDQ_I18N_DIR . '/zh_CN.php')) {
            $zh = require XXDQ_I18N_DIR . '/zh_CN.php';
            $data = array_merge($zh, $data);
        }

        $loaded[$lang] = $data;
        return $data;
    }
}

/* ── Convenience wrappers ────────────────────────────────────── */

/** Translate a key. See XXDQ_I18n::t(). */
function xxdq_t($key, $args = [], $fallback = '') {
    return XXDQ_I18n::t($key, $args, $fallback);
}

/** Are we rendering the English site? */
function xxdq_is_en() {
    return XXDQ_I18n::is_en();
}

/** The current language code ('zh_CN' or 'en_US'). */
function xxdq_lang() {
    return XXDQ_I18n::lang();
}

/** Build a URL in the current language. */
function xxdq_url($path = '/') {
    return XXDQ_I18n::url($path);
}

/**
 * Is the current request the front page of the *current language*?
 *
 * WordPress' `is_front_page()` compares the queried object against
 * `page_on_front`, which always points at the Chinese home page. The
 * English home lives at its own page ID (`xxdq_en_home_id`), so on
 * `/en/` `is_front_page()` is FALSE and `is_home()` is FALSE too
 * (the site uses a static front page).
 *
 * That broke two things on the English home:
 *   - the Organization / LocalBusiness JSON-LD was never emitted;
 *   - a BreadcrumbList was emitted on the home page, which the Chinese
 *     home does not have.
 *
 * This helper returns TRUE for the Chinese home AND the English home.
 */
function xxdq_is_front_page() {
    if (is_front_page() || is_home()) {
        return true;
    }
    if (!function_exists('xxdq_is_en') || !xxdq_is_en()) {
        return false;
    }
    $en_home = (int) get_option('xxdq_en_home_id');
    return $en_home > 0 && (int) get_queried_object_id() === $en_home;
}

/** The home URL of the current language. */
function xxdq_home_url() {
    if (function_exists('xxdq_is_en') && xxdq_is_en()) {
        return home_url('/en/');
    }
    return home_url('/');
}

/**
 * The inquiry (Get a Quote) page URL of the current language.
 *
 * The Chinese inquiry page is post 17; its English counterpart is
 * linked through the `_xxdq_en_id` / `_xxdq_zh_id` post meta. Without
 * this helper every CTA on the English site pointed back at the
 * Chinese /inquiry/ page.
 *
 * @return string
 */
function xxdq_inquiry_url() {
    $zh_id = 17;
    if (function_exists('xxdq_is_en') && xxdq_is_en()) {
        $en_id = class_exists('XXDQ_I18n_Content')
            ? XXDQ_I18n_Content::get_en_post_id($zh_id)
            : 0;
        if ($en_id) {
            $link = get_permalink($en_id);
            if ($link) {
                return (string) $link;
            }
        }
        return home_url('/en/inquiry/');
    }
    $link = get_permalink($zh_id);
    return $link ? (string) $link : home_url('/inquiry/');
}

/**
 * Permalink of a post in the current language.
 *
 * On the Chinese site this is the post's own permalink. On the English
 * site it resolves to the English counterpart's public URL (e.g.
 * /en/product/dt-25/), falling back to the Chinese permalink when no
 * English counterpart exists yet.
 *
 * @param int|WP_Post $post Post or post ID.
 * @return string
 */
function xxdq_permalink($post = null) {
    $post = get_post($post);
    if (!$post) {
        return home_url('/');
    }

    if (!XXDQ_I18n::is_en()) {
        return (string) get_permalink($post);
    }

    // The post itself might already be the English one.
    if (get_post_meta($post->ID, '_xxdq_lang', true) === 'en_US') {
        return (string) get_permalink($post);
    }

    $en_id = class_exists('XXDQ_I18n_Content')
        ? XXDQ_I18n_Content::get_en_post_id($post->ID)
        : 0;

    if ($en_id) {
        return (string) get_permalink($en_id);
    }

    // No English counterpart yet — point at the English equivalent of
    // the same path so the visitor stays inside the English site.
    $link = (string) get_permalink($post);
    return xxdq_prefix_en_path($link);
}

/**
 * Term link in the current language.
 *
 * @param object $term Term object.
 * @return string
 */
function xxdq_term_link($term) {
    if (!is_object($term)) {
        return home_url('/');
    }
    if (!XXDQ_I18n::is_en()) {
        return (string) get_term_link($term);
    }

    $en_id = class_exists('XXDQ_I18n_Content')
        ? XXDQ_I18n_Content::get_en_term_id($term->term_id)
        : 0;

    if ($en_id) {
        return (string) get_term_link($en_id);
    }

    /* The term is ALREADY an English term (it carries the
     * `xxdq_zh_term_id` meta pointing back at its Chinese
     * counterpart). `get_term_link()` already returns the public
     * English URL for it — /en/product-category/<slug>/ — because of
     * the `term_link` filter in the router.
     *
     * Prefixing it again here would produce /en/en/product-category/…
     * so we return it untouched. */
    if (xxdq_is_en_term($term->term_id)) {
        return (string) get_term_link($term);
    }

    /* A Chinese term with no English counterpart yet — keep the
     * visitor inside the English site. */
    $link = (string) get_term_link($term);
    return xxdq_prefix_en_path($link);
}

/**
 * Prefix an absolute site URL with the `/en` segment.
 *
 * Used as the fallback when a Chinese object has no English counterpart
 * yet: the visitor stays inside the English site instead of being
 * dropped back onto the Chinese one.
 *
 * The path is inserted AFTER the trailing slash so that a URL like
 * "/inquiry/" becomes "/en/inquiry/" — not "/eninquiry/".
 *
 * @param string $url Absolute URL.
 * @return string
 */
function xxdq_prefix_en_path($url) {
    $url  = (string) $url;
    /* home_url('/') carries a trailing slash, so strip it before
     * comparing — otherwise substr() would hand back "inquiry/"
     * and the result would be "/eninquiry/" instead of "/en/inquiry/". */
    $home = rtrim(home_url('/'), '/');

    if (strpos($url, $home) !== 0) {
        return $url;
    }

    $path = substr($url, strlen($home));

    /* The URL may already carry the /en segment — for example when
     * `get_term_link()` already returned the public English URL via
     * the `term_link` filter. Prefixing again would yield
     * /en/en/…, so we bail out in that case. */
    if (strpos($path, '/en/') === 0 || rtrim($path, '/') === '/en') {
        return $url;
    }

    return home_url('/en' . $path);
}

/**
 * Is this term an ENGLISH term?
 *
 * The English site keeps a parallel set of product_category terms.
 * Each English term carries a `xxdq_zh_term_id` meta pointing back at
 * its Chinese counterpart, and each Chinese term carries an
 * `xxdq_en_term_id` meta pointing forward at the English one.
 *
 * So: an English term has `xxdq_zh_term_id`; a Chinese term does NOT.
 *
 * @param int $term_id Term ID.
 * @return bool
 */
function xxdq_is_en_term($term_id) {
    return (int) get_term_meta((int) $term_id, 'xxdq_zh_term_id', true) > 0;
}

/**
 * Filter a list of product_category terms down to the current language.
 *
 * The English site keeps a parallel set of terms (each linked to its
 * Chinese counterpart via the `xxdq_en_term_id` / `xxdq_zh_term_id`
 * term meta). Any query that lists product categories must be scoped
 * to one language, otherwise the Chinese navigation and sidebar would
 * also list the English categories.
 *
 * @param array $terms Terms to filter.
 * @return array
 */
function xxdq_filter_terms_by_lang($terms) {
    if (empty($terms) || !is_array($terms)) {
        return [];
    }
    return array_values(array_filter($terms, function ($t) {
        if (!is_object($t) || !isset($t->term_id)) {
            return false;
        }
        $is_en_term = xxdq_is_en_term($t->term_id);
        return XXDQ_I18n::is_en() ? $is_en_term : !$is_en_term;
    }));
}

/**
 * The term IDs of the product categories belonging to the CURRENT
 * language.
 *
 * The English site keeps a parallel set of product_category terms.
 * Any query that lists or groups by categories has to be scoped to one
 * language, otherwise the Chinese archive would list English
 * categories and vice versa.
 *
 * @return int[]
 */
function xxdq_current_lang_term_ids() {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $all = get_terms([
        'taxonomy'   => 'product_category',
        'hide_empty' => false,
        'fields'     => 'ids',
    ]);
    if (is_wp_error($all)) {
        $cache = [];
        return $cache;
    }

    $is_en = XXDQ_I18n::is_en();
    $ids   = [];
    foreach ($all as $tid) {
        $is_en_term = xxdq_is_en_term($tid);
        if ($is_en ? $is_en_term : !$is_en_term) {
            $ids[] = (int) $tid;
        }
    }

    $cache = $ids;
    return $cache;
}

/**
 * The display name of a term in the current language.
 *
 * @param object $term Term object.
 * @return string
 */
function xxdq_term_name($term) {
    if (!is_object($term)) {
        return '';
    }
    if (class_exists('XXDQ_I18n_Content')) {
        return XXDQ_I18n_Content::term_text($term, 'name');
    }
    return (string) $term->name;
}

/**
 * The description of a term in the current language.
 *
 * Mirrors term_description(): runs the text through wpautop so HTML
 * markup inside a category description renders as paragraphs exactly
 * like it did before the i18n layer existed.
 *
 * @param object $term Term object.
 * @return string
 */
function xxdq_term_description($term) {
    if (!is_object($term)) {
        return '';
    }

    $taxonomy = isset($term->taxonomy) ? $term->taxonomy : 'product_category';

    if (XXDQ_I18n::is_en() && class_exists('XXDQ_I18n_Content')) {
        $en_id = XXDQ_I18n_Content::get_en_term_id($term->term_id);
        if ($en_id) {
            $en_term = get_term($en_id);
            if ($en_term && !is_wp_error($en_term)) {
                return term_description($en_id, $en_term->taxonomy);
            }
        }
    }

    return term_description($term->term_id, $taxonomy);
}

/**
 * The RAW description of a term in the current language.
 *
 * Unlike xxdq_term_description() this does NOT run the text through
 * wpautop. Use it where the caller escapes the value itself (e.g. with
 * esc_html) and a nested <p> wrapper would be wrong.
 *
 * @param object $term Term object.
 * @return string
 */
function xxdq_term_description_raw($term) {
    if (!is_object($term)) {
        return '';
    }
    if (XXDQ_I18n::is_en() && class_exists('XXDQ_I18n_Content')) {
        $en_id = XXDQ_I18n_Content::get_en_term_id($term->term_id);
        if ($en_id) {
            $en_term = get_term($en_id);
            if ($en_term && !is_wp_error($en_term)) {
                return (string) $en_term->description;
            }
        }
    }
    return (string) $term->description;
}

/* ── 2. Document / head integration ───────────────────────────── */

/**
 * Set the <html lang> attribute for the current language.
 *
 * The theme's language_attributes() is filtered here so the English
 * pages announce `lang="en"` instead of `lang="zh-CN"`.
 */
add_filter('language_attributes', function ($output) {
    if (!XXDQ_I18n::is_en()) {
        return $output;
    }
    // Replace lang="zh-CN" (or whatever WP resolved) with lang="en".
    return preg_replace('/lang\s*=\s*(["\'])[^"\']*\1/', 'lang="en"', $output, 1);
});

/**
 * Add a language marker to the body class so CSS can target the
 * English site without relying on any content inspection.
 */
add_filter('body_class', function ($classes) {
    $classes[] = XXDQ_I18n::is_en() ? 'xxdq-lang-en' : 'xxdq-lang-zh';
    if (class_exists('XXDQ_I18n')) {
        $classes[] = 'xxdq-lang-' . strtolower(XXDQ_I18n::lang());
    }
    return $classes;
});

/**
 * Point Yoast's Open Graph locale at the right language.
 *
 * The theme plugin forces `zh_CN` on every request. That filter lives
 * in xxdq-theme.php and is changed to be language-aware in that file.
 */
add_filter('wpseo_locale', function ($locale) {
    return XXDQ_I18n::locale();
}, 30);

add_filter('wpseo_og_locale', function ($locale) {
    return XXDQ_I18n::locale();
}, 30);

/* ── 3. Site name / tagline ───────────────────────────────────── */

/**
 * Render the site name and tagline in the current language.
 *
 * On the Chinese site the values come straight from the WordPress
 * options, so nothing changes. On the English site we return the
 * English brand name / tagline, which keeps the Open Graph site name,
 * the JSON-LD organisation name and the GeneratePress header markup
 * free of Chinese.
 *
 * We hook `pre_option_*` rather than the `bloginfo` filter because
 * the latter only fires when get_bloginfo() is called with
 * $filter = 'display' — the default 'raw' calls would still return
 * the Chinese name.
 */
add_filter('pre_option_blogname', function ($name) {
    if (!XXDQ_I18n::is_en()) {
        return $name;
    }
    return XXDQ_I18n::t('brand.name');
});

add_filter('pre_option_blogdescription', function ($desc) {
    if (!XXDQ_I18n::is_en()) {
        return $desc;
    }
    return XXDQ_I18n::t('brand.tagline');
});

/**
 * The Open Graph site name on the English site.
 */
add_filter('wpseo_opengraph_site_name', function ($name) {
    if (!XXDQ_I18n::is_en()) {
        return $name;
    }
    return XXDQ_I18n::t('brand.name');
}, 10);

/**
 * The schema.org organisation name on the English site.
 */
add_filter('wpseo_schema_company_name', function ($name) {
    if (!XXDQ_I18n::is_en()) {
        return $name;
    }
    return XXDQ_I18n::t('brand.name');
}, 10);

/**
 * The English logo alt text.
 *
 * GeneratePress renders the custom logo with `get_bloginfo('name')`
 * as the alt attribute, which would be the Chinese site name.
 */
add_filter('generate_logo_title', function ($title) {
    if (!XXDQ_I18n::is_en()) {
        return $title;
    }
    return XXDQ_I18n::t('brand.alt');
}, 10);

/**
 * Point GeneratePress' branding at the English home page.
 *
 * GeneratePress renders its own (CSS-hidden) masthead with the logo
 * and the site title linking to `home_url('/')`, i.e. the Chinese home
 * page. On the English site those links must point at /en/ so the
 * English pages never link back into the Chinese site.
 */
add_filter('generate_logo_output', function ($output) {
    if (!XXDQ_I18n::is_en()) {
        return $output;
    }
    return str_replace(
        'href="' . esc_url(home_url('/')) . '"',
        'href="' . esc_url(home_url('/en/')) . '"',
        $output
    );
}, 10);

add_filter('generate_site_branding_output', function ($output) {
    if (!XXDQ_I18n::is_en()) {
        return $output;
    }
    return str_replace(
        'href="' . esc_url(home_url('/')) . '"',
        'href="' . esc_url(home_url('/en/')) . '"',
        $output
    );
}, 10);

/**
 * Localise the WordPress navigation menu on the English site.
 *
 * The primary menu points at the Chinese pages. On the English site
 * every item is swapped for its English counterpart so the title AND
 * the URL point at /en/... — otherwise the English page would link
 * back into the Chinese site.
 *
 * @param array $items Menu items.
 * @return array
 */
add_filter('wp_nav_menu_objects', function ($items) {
    if (!XXDQ_I18n::is_en() || empty($items)) {
        return $items;
    }

    foreach ($items as $item) {
        /* Only page items have an English counterpart. */
        if ($item->type !== 'post_type' || (int) $item->object_id <= 0) {
            continue;
        }

        $zh_id = (int) $item->object_id;
        if (get_post_meta($zh_id, '_xxdq_lang', true) === 'en_US') {
            /* Already an English item. */
            continue;
        }

        $en_id = class_exists('XXDQ_I18n_Content')
            ? XXDQ_I18n_Content::get_en_post_id($zh_id)
            : 0;
        if ($en_id) {
            $en_post = get_post($en_id);
            if ($en_post) {
                $item->title     = $en_post->post_title;
                $item->url       = (string) get_permalink($en_id);
                $item->object_id = $en_id;
                continue;
            }
        }

        /* The Chinese "产品中心" / "新闻中心" pages are ARCHIVE pages
         * on the English site — they have no parallel post, but they do
         * have an English archive URL (/en/product/, /en/news/). */
        $archive = xxdq_en_archive_for_page($zh_id);
        if ($archive) {
            $item->title = $archive['title'];
            $item->url   = $archive['url'];
        }
    }

    return $items;
}, 10);

/**
 * Localise the taxonomy labels on the English site.
 *
 * WordPress builds the `<link rel="alternate" type="application/rss+xml"
 * title="… 产品分类 Feed">` tag from the taxonomy's `singular_name`
 * label, which is registered in Chinese. On the English site that leaks
 * Chinese into the `<head>`.
 *
 * The label objects are created once at registration time, so they are
 * mutated in place on `init` (after the CPT / taxonomy plugins have
 * registered their Chinese labels).
 */
add_action('init', function () {
    if (!XXDQ_I18n::is_en()) {
        return;
    }

    global $wp_post_types, $wp_taxonomies;

    $post_type_labels = [
        'product' => [
            'name'          => 'Products',
            'singular_name' => 'Product',
            'search_items'  => 'Search Products',
            'all_items'     => 'All Products',
            'edit_item'     => 'Edit Product',
            'update_item'   => 'Update Product',
            'add_new_item'  => 'Add New Product',
            'new_item_name' => 'New Product Name',
            'menu_name'     => 'Products',
        ],
        'news' => [
            'name'          => 'News',
            'singular_name' => 'News',
            'search_items'  => 'Search News',
            'all_items'     => 'All News',
            'edit_item'     => 'Edit News',
            'update_item'   => 'Update News',
            'add_new_item'  => 'Add New News',
            'new_item_name' => 'New News Title',
            'menu_name'     => 'News',
        ],
    ];

    foreach ($post_type_labels as $name => $map) {
        if (empty($wp_post_types[$name]) || !is_object($wp_post_types[$name]->labels)) {
            continue;
        }
        foreach ($map as $key => $value) {
            if (isset($wp_post_types[$name]->labels->$key)) {
                $wp_post_types[$name]->labels->$key = $value;
            }
        }
    }

    if (!empty($wp_taxonomies['product_category']) && is_object($wp_taxonomies['product_category']->labels)) {
        $map = [
            'name'          => 'Product Categories',
            'singular_name' => 'Product Category',
            'search_items'  => 'Search Categories',
            'all_items'     => 'All Categories',
            'parent_item'   => 'Parent Category',
            'edit_item'     => 'Edit Category',
            'update_item'   => 'Update Category',
            'add_new_item'  => 'Add New Category',
            'new_item_name' => 'New Category Name',
            'menu_name'     => 'Product Categories',
        ];
        foreach ($map as $key => $value) {
            if (isset($wp_taxonomies['product_category']->labels->$key)) {
                $wp_taxonomies['product_category']->labels->$key = $value;
            }
        }
    }
}, 30);

/**
 * Map a Chinese archive page to its English archive equivalent.
 *
 * @param int $zh_id Chinese page ID.
 * @return array|null ['title' => string, 'url' => string] or null.
 */
function xxdq_en_archive_for_page($zh_id) {
    $map = [
        8  => ['title' => 'Products',            'url' => '/en/product/'],
        14 => ['title' => 'News',                'url' => '/en/news/'],
    ];

    if (!isset($map[(int) $zh_id])) {
        return null;
    }

    $entry = $map[(int) $zh_id];

    return [
        'title' => $entry['title'],
        'url'   => home_url($entry['url']),
    ];
}

/**
 * Convert the Chinese company address into an English one.
 *
 * The address comes from the ACF options page and is stored in Chinese
 * ("浙江省温州市乐清市柳市镇蟾民西路3号"). The English site needs a
 * romanised version so the footer — and the LocalBusiness JSON-LD
 * that mirrors it — stay readable for the target audience.
 *
 * @param string $zh The Chinese address.
 * @return string
 */
function xxdq_en_address($zh) {
    $zh = trim((string) $zh);
    if ($zh === '' || !preg_match('/[\x{4e00}-\x{9fff}]/u', $zh)) {
        return $zh;
    }

    /* The house number is the trailing "<digits>号". It has to be
     * extracted BEFORE the street name is replaced, otherwise the "3"
     * would be glued onto the end of the English street name
     * ("…Zhejiang3"). */
    $number = '';
    if (preg_match('/(\d+)\s*号/u', $zh, $m)) {
        $number = $m[1];
        $zh     = str_replace($m[0], '', $zh);
    }

    /* Ordered longest-first so the full street name wins over the
     * shorter district fragments. */
    $map = [
        '浙江省温州市乐清市柳市镇蟾民西路' => 'Chanmin West Road, Liuqi Town, Yueqing, Wenzhou, Zhejiang',
        '浙江省温州市乐清市柳市镇'       => 'Liuqi Town, Yueqing, Wenzhou, Zhejiang',
        '浙江省乐清市柳市镇'             => 'Liuqi Town, Yueqing, Zhejiang',
        '温州市乐清市柳市镇'             => 'Liuqi Town, Yueqing, Wenzhou',
        '浙江省温州市乐清市'             => 'Yueqing, Wenzhou, Zhejiang',
        '浙江省乐清市'                   => 'Yueqing, Zhejiang',
        '柳市镇'                         => 'Liuqi Town',
        '乐清市'                         => 'Yueqing',
        '温州市'                         => 'Wenzhou',
        '浙江省'                         => 'Zhejiang',
    ];

    uksort($map, function ($a, $b) {
        return mb_strlen($b) - mb_strlen($a);
    });

    $out = $zh;
    foreach ($map as $zh_p => $en_p) {
        $out = str_replace($zh_p, $en_p, $out);
    }

    /* Any remaining Chinese (street names we have not mapped) is
     * dropped rather than shipped to the English site. */
    $out = preg_replace('/[\x{4e00}-\x{9fff}]+/u', '', $out);
    $out = preg_replace('/\s{2,}/u', ' ', (string) $out);
    $out = trim($out, " ,-—");

    /* Append the house number back on, separated from the street name
     * by a comma so it reads as "…, Zhejiang, No. 3" rather than
     * "…Zhejiang3". */
    if ($number !== '') {
        $out = $out . ', No. ' . $number;
    }

    return $out !== '' ? $out : 'Yueqing, Wenzhou, Zhejiang, China';
}

/**
 * The company contact details in the current language.
 *
 * A single source of truth for the address / phone / email / working
 * hours, so the footer, the contact-card block and the LocalBusiness
 * JSON-LD can never drift apart. On the English site the Chinese
 * address is romanised and the Chinese working hours are translated.
 *
 * @return array{address:string,phone:string,email:string,hours:string}
 */
function xxdq_company_contact() {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $opt = function ($key, $default) {
        if (!function_exists('get_field')) {
            return $default;
        }
        $v = get_field($key, 'option');
        return ($v === null || $v === '') ? $default : $v;
    };

    $address = $opt('company_address', $opt('address', '浙江省乐清市柳市镇'));
    $phone   = $opt('company_phone', $opt('phone', '+86-18658733315'));
    $email   = $opt('company_email', $opt('email', '197995061@qq.com'));
    $hours   = $opt('work_hours', '周一至周六 8:30-17:30');

    if (XXDQ_I18n::is_en()) {
        $address = xxdq_en_address($address);
        /* The Chinese working hours ("周一至周六 8:30-17:30") need a
         * translated equivalent — the day names are Chinese. */
        $hours = xxdq_en_hours($hours);
    }

    $cache = [
        'address' => (string) $address,
        'phone'   => (string) $phone,
        'email'   => (string) $email,
        'hours'   => (string) $hours,
    ];

    return $cache;
}

/**
 * Translate the Chinese working-hours string into English.
 *
 * Handles the "周一至周六 8:30-17:30" form used on this site, and
 * falls back to the language-file default for anything it cannot map.
 *
 * @param string $zh Chinese working hours.
 * @return string English working hours.
 */
function xxdq_en_hours($zh) {
    $zh = trim((string) $zh);
    if ($zh === '' || !preg_match('/[\x{4e00}-\x{9fff}]/u', $zh)) {
        return $zh !== '' ? $zh : xxdq_t('footer.hours_value');
    }

    $days = [
        '周一至周五' => 'Mon – Fri',
        '周一至周六' => 'Mon – Sat',
        '周一至周日' => 'Mon – Sun',
        '周六至周日' => 'Sat – Sun',
        '星期一至星期五' => 'Mon – Fri',
        '星期一至星期六' => 'Mon – Sat',
        '每天'      => 'Mon – Sun',
        '周日'      => 'Sun',
        '周六'      => 'Sat',
        '周五'      => 'Fri',
        '周四'      => 'Thu',
        '周三'      => 'Wed',
        '周二'      => 'Tue',
        '周一'      => 'Mon',
    ];

    $out = $zh;
    uksort($days, function ($a, $b) {
        return mb_strlen($b) - mb_strlen($a);
    });
    foreach ($days as $zh_d => $en_d) {
        $out = str_replace($zh_d, $en_d, $out);
    }

    /* Drop any Chinese that is left (an unmapped phrase). */
    $out = preg_replace('/[\x{4e00}-\x{9fff}]+/u', '', $out);
    $out = preg_replace('/\s{2,}/u', ' ', (string) $out);
    $out = trim($out, " ,-—");

    return $out !== '' ? $out : xxdq_t('footer.hours_value');
}

/* ── 6. HREFLANG ALTERNATES ────────────────────────────────────
 * Without `<link rel="alternate" hreflang="…">` tags Google treats the
 * Chinese site and the English site as two unrelated documents, so it
 * picks one and ignores the other. The tags tell it they are the same
 * page in two languages, which is what makes the English pages actually
 * get indexed for English queries.
 *
 * Each page points at its counterpart in the other language, plus
 * `x-default` for the Chinese (origin) site.
 */
add_action('wp_head', function () {
    $zh_url = null;
    $en_url = null;

    if (is_singular()) {
        $zh_id = get_queried_object_id();
        /* Already on the English side? Then the Chinese twin is the
         * post that points back at us. */
        $back = class_exists('XXDQ_I18n_Content')
            ? XXDQ_I18n_Content::get_zh_post_id($zh_id)
            : 0;
        if (get_post_meta($zh_id, '_xxdq_lang', true) === 'en_US') {
            if ($back) {
                $zh_url = (string) get_permalink($back);
            }
            $en_url = (string) get_permalink($zh_id);
        } else {
            $en_id = class_exists('XXDQ_I18n_Content')
                ? XXDQ_I18n_Content::get_en_post_id($zh_id)
                : 0;
            if ($en_id) {
                $en_url = (string) get_permalink($en_id);
            }
            $zh_url = (string) get_permalink($zh_id);
        }
    } elseif (is_tax('product_category') || is_tax('product_series')) {
        $term = get_queried_object();
        if (is_object($term)) {
            if (XXDQ_I18n::is_en()) {
                $en_url = (string) get_term_link($term);
                $zh_id  = class_exists('XXDQ_I18n_Content')
                    ? XXDQ_I18n_Content::get_zh_term_id($term->term_id)
                    : 0;
                if ($zh_id) {
                    $zh_term = get_term($zh_id);
                    if ($zh_term && !is_wp_error($zh_term)) {
                        $zh_url = (string) get_term_link($zh_term);
                    }
                }
            } else {
                $zh_url = (string) get_term_link($term);
                $en_id  = class_exists('XXDQ_I18n_Content')
                    ? XXDQ_I18n_Content::get_en_term_id($term->term_id)
                    : 0;
                if ($en_id) {
                    /* Use the PUBLIC English URL (/en/product-category/copper/),
                     * not the raw term permalink (/product-category/copper-2/).
                     * The English terms carry their own internal slug with a
                     * "-2" suffix, and the router's term_link filter only
                     * rewrites them while the English site is being rendered
                     * — from the Chinese site the raw permalink leaks. */
                    $en_slug = get_term_meta($en_id, '_xxdq_en_slug', true);
                    if ($en_slug) {
                        $en_url = home_url('/en/product-category/' . $en_slug . '/');
                    } else {
                        $en_term = get_term($en_id);
                        if ($en_term && !is_wp_error($en_term)) {
                            $en_url = function_exists('xxdq_term_link')
                                ? xxdq_term_link($en_term)
                                : (string) get_term_link($en_term);
                        }
                    }
                }
            }
        }
    } elseif (is_post_type_archive('product') || is_post_type_archive('news')) {
        /* The archive itself, not the home page.
         *
         * The URLs are built by hand because get_post_type_archive_link()
         * returns the ENGLISH URL while the English site is rendering
         * (the router filters it), which would make the zh-Hans tag point
         * back at /en/. */
        if (is_post_type_archive('news')) {
            $zh_url = home_url('/news/');
            $en_url = home_url('/en/news/');
        } else {
            $zh_url = home_url('/product/');
            $en_url = home_url('/en/product/');
        }
    } elseif (function_exists('xxdq_is_front_page') && xxdq_is_front_page()) {
        $zh_url = home_url('/');
        $en_url = home_url('/en/');
    }

    if (!$zh_url && !$en_url) {
        return;
    }

    echo "\n<!-- XXDQ hreflang alternates -->\n";

    if ($zh_url) {
        printf(
            '<link rel="alternate" hreflang="zh-Hans" href="%s" />' . "\n",
            esc_url($zh_url)
        );
    }
    if ($en_url) {
        printf(
            '<link rel="alternate" hreflang="en" href="%s" />' . "\n",
            esc_url($en_url)
        );
    }
    if ($zh_url) {
        printf(
            '<link rel="alternate" hreflang="x-default" href="%s" />' . "\n",
            esc_url($zh_url)
        );
    }
}, 2);

