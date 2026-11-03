<?php
/**
 * XXDQ i18n — English routing.
 *
 * Maps `/en/...` URLs onto the parallel English content objects.
 *
 * HOW IT WORKS
 * ------------
 * 1. A single catch-all rewrite rule captures everything after `/en/`
 *    into the `xxdq_en_path` query var.
 * 2. On `request`, we resolve that path to a concrete English object
 *    (page / product / term) and rewrite the query vars so that
 *    WordPress's normal query machinery runs against it.
 * 3. `pre_get_posts` then filters archive queries so the English site
 *    only ever sees English products, and the Chinese site only ever
 *    sees Chinese ones.
 *
 * ENGLISH URLS
 * ------------
 * The English product's `post_name` is `{slug}-en` so it does not
 * collide with the Chinese product's `post_name` (WordPress enforces
 * uniqueness per post type). The PUBLIC English URL is stored
 * separately in the `_xxdq_en_path` meta and restored by the
 * `post_link` filter, so visitors see:
 *
 *   /en/product/dt-25/          (public)
 *   /product/dt-25-en/          (internal post_name, never shown)
 *
 * @package XXDQ
 */

if (!defined('ABSPATH')) exit;

class XXDQ_I18n_Router {

    /** @var bool Set once the English path has been resolved. */
    private static $resolved = false;

    /**
     * Register the rewrite rule and query var.
     */
    public static function init() {
        add_action('init', function () {
            add_rewrite_tag('%xxdq_en_path%', '(.+?)');
            add_rewrite_rule(
                '^en(?:/(.*))?/?$',
                'index.php?xxdq_en_path=$matches[1]',
                'top'
            );
        });

        // Resolve the captured path into real query vars.
        add_filter('request', [__CLASS__, 'resolve_request']);
    }

    /**
     * Resolve `xxdq_en_path` into the correct query vars.
     *
     * @param array $query_vars Parsed query vars.
     * @return array
     */
    public static function resolve_request($query_vars) {
        if (self::$resolved) {
            return $query_vars;
        }
        /* The rewrite rule captures the path AFTER /en/. For the bare
         * "/en/" URL that capture is an EMPTY STRING, and
         * `empty()` would treat it as "no English path" and bail out —
         * which left the English home page rendering "Nothing Found".
         * So we test for the KEY, not for a non-empty value. */
        if (!array_key_exists('xxdq_en_path', $query_vars)) {
            return $query_vars;
        }
        if ($query_vars['xxdq_en_path'] === null) {
            return $query_vars;
        }
        self::$resolved = true;

        $path = trim((string) $query_vars['xxdq_en_path'], '/');

        // `/en/` — the English home page.
        if ($path === '') {
            $en_home = self::english_home_id();
            if ($en_home) {
                $query_vars = self::force_singular($query_vars, $en_home);
                /* The English home page IS the front page of the English
                 * site. Without these two vars WordPress renders it as
                 * "Nothing Found" because no post is marked as the
                 * front page. */
                $query_vars['is_home']       = 1;
                $query_vars['is_front_page'] = 1;
                return $query_vars;
            }
            $query_vars['xxdq_en_home'] = 1;
            return $query_vars;
        }

        $segments = explode('/', $path);
        $root     = $segments[0];

        /* ── /en/{type}-sitemap.xml ────────────────────────────
         * The English site has its own sitemaps, so a crawler can
         * fetch only the English URLs. Yoast reads the sitemap type
         * from the `sitemap` query var, so the file name is all that
         * is needed here — the language filtering happens in
         * XXDQ_I18n_Sitemap. */
        if (count($segments) === 1 && preg_match('/^([a-z0-9_-]+)-sitemap\.xml$/', $root, $m)) {
            $query_vars['sitemap']     = $m[1];
            $query_vars['sitemap_subtype'] = '';
            $query_vars['sitemap_n']   = '';
            unset($query_vars['xxdq_en_path']);
            return $query_vars;
        }

        /* ── /en/sitemap.xml → the English sitemap index ─────── */
        if (count($segments) === 1 && ($root === 'sitemap.xml' || $root === 'sitemap_index.xml')) {
            /* Yoast uses the type "1" for the root sitemap index. */
            $query_vars['sitemap']    = '1';
            $query_vars['sitemap_n']  = '';
            unset($query_vars['xxdq_en_path']);
            return $query_vars;
        }

        /* ── /en/product/feed/ ────────────────────────────────── */
        if ($root === 'product' && count($segments) === 2 && self::is_feed_format($segments[1])) {
            $query_vars['post_type'] = 'product';
            $query_vars['feed']      = $segments[1] === 'feed' ? 'rss2' : $segments[1];
            unset($query_vars['xxdq_en_path']);
            return $query_vars;
        }

        /* ── /en/news/feed/ ───────────────────────────────────── */
        if ($root === 'news' && count($segments) === 2 && self::is_feed_format($segments[1])) {
            $query_vars['post_type'] = 'news';
            $query_vars['feed']      = $segments[1] === 'feed' ? 'rss2' : $segments[1];
            unset($query_vars['xxdq_en_path']);
            return $query_vars;
        }

        /* ── /en/feed/ ────────────────────────────────────────── */
        if ($root === 'feed') {
            $query_vars['post_type'] = 'post';
            $query_vars['feed']      = '';
            unset($query_vars['xxdq_en_path']);
            return $query_vars;
        }

        /* ── /en/product/ ─────────────────────────────────────── */
        if ($root === 'product') {
            if (count($segments) === 1) {
                // Archive — handled by pre_get_posts.
                $query_vars['post_type']       = 'product';
                $query_vars['xxdq_en_archive'] = 'product';
                unset($query_vars['xxdq_en_path']);
                return $query_vars;
            }

            // Single product.
            $en_id = self::find_en_post_by_slug('product', $segments[1]);
            if ($en_id) {
                return self::force_singular($query_vars, $en_id);
            }
            $query_vars['error'] = '404';
            return $query_vars;
        }

        /* ── /en/news/ ───────────────────────────────────────── */
        if ($root === 'news') {
            if (count($segments) === 1) {
                $query_vars['post_type']       = 'news';
                $query_vars['xxdq_en_archive'] = 'news';
                unset($query_vars['xxdq_en_path']);
                return $query_vars;
            }
            $en_id = self::find_en_post_by_slug('news', $segments[1]);
            if ($en_id) {
                return self::force_singular($query_vars, $en_id);
            }
            $query_vars['error'] = '404';
            return $query_vars;
        }

        /* ── /en/product-category/{slug}/ ────────────────────── */
        if ($root === 'product-category') {
            if (count($segments) >= 2) {
                /* The path may be hierarchical: copper/10mm-copper-nose/.
                 * The LAST segment identifies the term; the leading ones
                 * are its ancestors and are only used to disambiguate
                 * (two terms can share a leaf slug under different
                 * parents). */
                $leaf     = $segments[count($segments) - 1];
                $en_term_id = self::find_en_term_by_slug($leaf);

                if ($en_term_id) {
                    /* Verify the ancestor chain when one was given, so
                     * /en/product-category/copper/10mm-copper-nose/ can
                     * never resolve to a term living under a different
                     * parent that happens to share the leaf slug. */
                    $ancestors = array_slice($segments, 1, count($segments) - 2);
                    if ($ancestors && !self::en_term_has_ancestors($en_term_id, $ancestors)) {
                        $en_term_id = 0;
                    }
                }

                if ($en_term_id) {
                    $term = get_term($en_term_id);
                    if ($term && !is_wp_error($term)) {
                        $query_vars['product_category'] = $term->slug;
                        unset($query_vars['xxdq_en_path']);
                        return $query_vars;
                    }
                }
            }
            $query_vars['error'] = '404';
            return $query_vars;
        }

        /* ── /en/{page-slug}/ ────────────────────────────────── */
        $en_id = self::find_en_post_by_slug('page', $root);
        if ($en_id) {
            return self::force_singular($query_vars, $en_id);
        }

        $query_vars['error'] = '404';
        return $query_vars;
    }

    /**
     * Is this path segment a feed format?
     *
     * WordPress supports several feed formats: /feed/, /atom/, /rss/,
     * /rss2/ and /rdf/. The bare "/feed/" defaults to RSS2.
     *
     * @param string $segment Path segment.
     * @return bool
     */
    private static function is_feed_format($segment) {
        return in_array(strtolower((string) $segment), ['feed', 'atom', 'rss', 'rss2', 'rdf'], true);
    }

    /**
     * Force the query to resolve to one specific post.
     *
     * @param array $query_vars Query vars.
     * @param int   $post_id    Target post ID.
     * @return array
     */
    private static function force_singular($query_vars, $post_id) {
        $post_type = get_post_type($post_id);

        if ('page' === $post_type) {
            /* A page MUST be addressed through `page_id`, not `p`.
             * WordPress sets `is_page = true` only for `page_id` /
             * `pagename` queries; a `p` query sets `is_single = true`
             * instead (see WP_Query::parse_query()). That difference
             * matters a lot here:
             *   - the theme's template_include filter only swaps in the
             *     xxdq page template when is_page() is true, so English
             *     pages were rendered by GeneratePress' bare page.php —
             *     no dark hero, no breadcrumb;
             *   - GeneratePress then treats them as "single" and picks
             *     the single-post layout instead of the page layout.
             */
            $query_vars['post_type'] = 'page';
            $query_vars['page_id']   = (int) $post_id;
            $query_vars['p']         = 0;
            $query_vars['name']      = '';
            $query_vars['pagename']  = '';
            unset($query_vars['xxdq_en_path']);
            return $query_vars;
        }

        $query_vars['post_type'] = $post_type;
        $query_vars['p']         = (int) $post_id;
        $query_vars['name']      = '';
        $query_vars['pagename']  = '';
        $query_vars['page_id']   = 0;
        unset($query_vars['xxdq_en_path']);
        return $query_vars;
    }

    /**
     * Find an English post by its PUBLIC English slug.
     *
     * @param string $post_type Post type.
     * @param string $slug      Public English slug (without the -en suffix).
     * @return int Post ID, or 0.
     */
    public static function find_en_post_by_slug($post_type, $slug) {
        $slug = sanitize_title($slug);
        if ($slug === '') {
            return 0;
        }
        $posts = get_posts([
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => '_xxdq_en_slug',
                    'value' => $slug,
                ],
            ],
            'no_found_rows'  => true,
        ]);
        return $posts ? (int) $posts[0] : 0;
    }

    /**
     * Find an English post by its PUBLIC English slug, scoped to a
     * specific Chinese parent post.
     *
     * Several Chinese products share the same slug (WordPress appends
     * -2, -3, … to keep post_name unique, but the PUBLIC url is built
     * from the shared base slug). When that happens the URL alone is
     * ambiguous, so the router falls back to resolving it against the
     * specific Chinese product the visitor came from.
     *
     * @param string $post_type Post type.
     * @param string $slug      Public English slug.
     * @param int    $zh_id     Optional Chinese parent post ID.
     * @return int Post ID, or 0.
     */
    public static function find_en_post_by_slug_scoped($post_type, $slug, $zh_id = 0) {
        $slug = sanitize_title($slug);
        if ($slug === '') {
            return 0;
        }

        $args = [
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => '_xxdq_en_slug',
                    'value' => $slug,
                ],
            ],
            'no_found_rows'  => true,
        ];

        if ($zh_id) {
            $args['post_parent'] = (int) $zh_id;
        }

        $posts = get_posts($args);
        return $posts ? (int) $posts[0] : 0;
    }

    /**
     * Find an English term by its PUBLIC English slug.
     *
     * @param string $slug Public English slug.
     * @return int Term ID, or 0.
     */
    public static function find_en_term_by_slug($slug) {
        $slug = sanitize_title($slug);
        if ($slug === '') {
            return 0;
        }
        $terms = get_terms([
            'taxonomy'   => 'product_category',
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key'   => '_xxdq_en_slug',
                    'value' => $slug,
                ],
            ],
            'no_found_rows' => true,
        ]);
        if (is_wp_error($terms) || empty($terms)) {
            return 0;
        }
        return (int) $terms[0]->term_id;
    }

    /**
     * Does an English term sit under the given chain of ancestor slugs?
     *
     * The ancestors are given as English slugs (the same ones that
     * appear in the public URL), e.g. ['copper'] for
     * /en/product-category/copper/10mm-copper-nose/.
     *
     * @param int   $term_id   English term ID.
     * @param array $ancestors English ancestor slugs, outermost first.
     * @return bool
     */
    private static function en_term_has_ancestors($term_id, $ancestors) {
        $term = get_term($term_id, 'product_category');
        if (!$term || is_wp_error($term)) {
            return false;
        }

        $parents = array_reverse((array) get_ancestors($term_id, 'product_category', 'taxonomy'));
        $count   = count($ancestors);

        if (count($parents) < $count) {
            return false;
        }

        /* Only the DIRECT ancestors that were given are compared — a
         * deeper parent chain is allowed. */
        for ($i = 0; $i < $count; $i++) {
            $parent = get_term($parents[$i], 'product_category');
            if (!$parent || is_wp_error($parent)) {
                return false;
            }
            $parent_slug = get_term_meta($parent->term_id, '_xxdq_en_slug', true);
            if (!$parent_slug) {
                $parent_slug = $parent->slug;
            }
            if ($parent_slug !== $ancestors[$i]) {
                return false;
            }
        }

        return true;
    }

    /**
     * The English home page ID.
     *
     * @return int
     */
    public static function english_home_id() {
        $id = get_option('xxdq_en_home_id');
        return $id ? (int) $id : 0;
    }

    /* ── Permalink handling ───────────────────────────────────── */

    /**
     * Restore the public English URL for English posts.
     *
     * Without this, WordPress would build /product/dt-25-en/ from the
     * internal post_name. We want /en/product/dt-25/.
     */
    public static function filter_post_link($permalink, $post, $leavename = false) {
        /* `post_link` passes a WP_Post object, but some callers
         * (and the `page_link` filter) pass a post ID instead. Accept
         * both so the filter works everywhere. */
        if (is_numeric($post)) {
            $post = get_post($post);
        }
        if (!($post instanceof WP_Post)) {
            return $permalink;
        }
        if (get_post_meta($post->ID, '_xxdq_lang', true) !== 'en_US') {
            return $permalink;
        }

        /* The English home page has an EMPTY public path — it lives at
         * /en/ itself. `$path === ''` is therefore a valid value, so we
         * must not treat it as "no path". A missing meta key (null)
         * is the only real "no path" case. */
        $path = get_post_meta($post->ID, '_xxdq_en_path', true);
        if ($path === null || $path === false) {
            return $permalink;
        }

        $path = ltrim((string) $path, '/');
        if ($path === '') {
            return home_url('/en/');
        }

        return home_url('/en/' . $path);
    }

    /**
     * Restore the public English URL for English PAGES.
     *
     * Pages do not go through the `post_link` filter — WordPress uses
     * `page_link` for them — and the site runs a "首页" (Home) parent
     * page, so without this the canonical URL came out as
     * "/首页/home-en/" instead of "/en/".
     */
    public static function filter_page_link($permalink, $page, $leavename = false) {
        /* The `page_link` filter passes the post ID, not a WP_Post
         * object — see get_page_link() in wp-includes/link-template.php.
         * Accept both shapes. */
        if (is_numeric($page)) {
            $page = get_post($page);
        }
        if (!($page instanceof WP_Post)) {
            return $permalink;
        }
        if (get_post_meta($page->ID, '_xxdq_lang', true) !== 'en_US') {
            return $permalink;
        }

        $path = get_post_meta($page->ID, '_xxdq_en_path', true);
        if ($path === null || $path === false) {
            return $permalink;
        }

        $path = ltrim((string) $path, '/');
        if ($path === '') {
            return home_url('/en/');
        }

        return home_url('/en/' . $path);
    }

    /**
     * Restore the public English URL for product category archives.
     *
     * Two directions:
     *   - On the ENGLISH site: an English term must serve its public URL
     *     (/en/product-category/copper/), not its raw WordPress slug
     *     (/product-category/copper-2/).
     *   - On the CHINESE site: an ENGLISH term must NOT leak its raw
     *     permalink. Otherwise Yoast puts /product-category/copper-2/
     *     into the Chinese sitemap — a duplicate, indexable URL that
     *     competes with /product-category/copper/ for the same keywords
     *     and whose content is in English on a Chinese-language site.
     *     English terms have no Chinese audience, so their link is
     *     rewritten to the /en/ public URL instead.
     */
    public static function filter_term_link($termlink, $term, $taxonomy) {
        if ($taxonomy !== 'product_category') {
            return $termlink;
        }

        if (XXDQ_I18n::is_en()) {
            $slug = get_term_meta($term->term_id, '_xxdq_en_slug', true);
            if (!$slug) {
                return $termlink;
            }
            return home_url('/en/product-category/' . self::en_term_path($term) . '/');
        }

        /* Chinese site: rewrite ENGLISH terms to their /en/ URL. */
        if (function_exists('xxdq_is_en_term') && xxdq_is_en_term($term->term_id)) {
            return home_url('/en/product-category/' . self::en_term_path($term) . '/');
        }

        return $termlink;
    }

    /**
     * The full public path of an English term, including its ancestors.
     *
     * Mirrors the Chinese URL structure, so
     * /product-category/copper/10mm-copper-nose/ becomes
     * /en/product-category/copper/10mm-copper-nose/ (not the flat
     * /en/product-category/10mm-copper-nose/).
     *
     * @param WP_Term $term Term object.
     * @return string Path without leading/trailing slash.
     */
    private static function en_term_path($term) {
        $slug = get_term_meta($term->term_id, '_xxdq_en_slug', true);
        if (!$slug) {
            $slug = $term->slug;
        }

        $parents = array_reverse((array) get_ancestors($term->term_id, 'product_category', 'taxonomy'));
        $parts   = [];

        foreach ($parents as $parent_id) {
            $parent = get_term($parent_id, 'product_category');
            if (!$parent || is_wp_error($parent)) {
                continue;
            }
            $parent_slug = get_term_meta($parent->term_id, '_xxdq_en_slug', true);
            $parts[] = $parent_slug ? $parent_slug : $parent->slug;
        }

        $parts[] = $slug;

        return implode('/', $parts);
    }

    /**
     * Restore the public English URL for the product / news archives
     * AND for single custom-post-type posts.
     *
     * WordPress uses the SAME `post_type_link` filter for both:
     *   - archives  → get_post_type_archive_link()
     *   - singles   → get_post_permalink() for any non-builtin CPT
     *
     * The single-post call passes a WP_Post as the second argument and
     * a third `$sample` argument; the archive call passes the post
     * type as a string. We tell them apart by type.
     */
    public static function filter_post_type_link($post_type_link, $post_type, $leavename = false) {
        /* ── Single CPT post ───────────────────────────────────── */
        if ($post_type instanceof WP_Post) {
            $post = $post_type;
            if (get_post_meta($post->ID, '_xxdq_lang', true) !== 'en_US') {
                return $post_type_link;
            }
            $path = get_post_meta($post->ID, '_xxdq_en_path', true);
            if ($path === null || $path === false) {
                return $post_type_link;
            }
            $path = ltrim((string) $path, '/');
            if ($path === '') {
                return home_url('/en/');
            }
            return home_url('/en/' . $path);
        }

        /* ── Archive ──────────────────────────────────────────── */
        if (!XXDQ_I18n::is_en()) {
            return $post_type_link;
        }
        if ($post_type === 'product') {
            return home_url('/en/product/');
        }
        if ($post_type === 'news') {
            return home_url('/en/news/');
        }
        return $post_type_link;
    }

    /**
     * Restore the public English URL for a custom post type ARCHIVE.
     *
     * `get_post_type_archive_link()` uses the `post_type_archive_link`
     * filter (not `post_type_link`), so the archive canonical needed
     * its own handler.
     */
    public static function filter_post_type_archive_link($link, $post_type) {
        if (!XXDQ_I18n::is_en()) {
            return $link;
        }
        if ($post_type === 'product') {
            return home_url('/en/product/');
        }
        if ($post_type === 'news') {
            return home_url('/en/news/');
        }
        return $link;
    }

    /* ── Query scoping ────────────────────────────────────────── */

    /**
     * Scope archive queries to the current language.
     *
     * The English archive must only list English products; the Chinese
     * archive must not list the English ones. Both are handled here.
     */
    public static function scope_queries($query) {
        if (is_admin() || !($query instanceof WP_Query)) {
            return;
        }

        $is_main = $query->is_main_query();
        if (!$is_main) {
            return;
        }

        if (XXDQ_I18n::is_en()) {
            // English site: only English objects.
            $query->set('meta_query', self::merge_meta(
                (array) $query->get('meta_query'),
                [[
                    'key'   => '_xxdq_lang',
                    'value' => 'en_US',
                ]]
            ));

            /* Taxonomy archives must resolve to the ENGLISH term, not
             * the Chinese one. The rewrite already points the query at
             * the English slug, but the term lookup below also needs to
             * be scoped. */
            if ($query->is_tax() || $query->is_category() || $query->is_tag()) {
                self::scope_term_query($query);
            }
        } else {
            // Chinese site: exclude English objects.
            $query->set('meta_query', self::merge_meta(
                (array) $query->get('meta_query'),
                [[
                    'key'     => '_xxdq_lang',
                    'compare' => 'NOT EXISTS',
                ]]
            ));
        }
    }

    /**
     * Rewrite a taxonomy query so it targets the term of the current
     * language.
     *
     * `/en/product-category/copper/` must resolve to the ENGLISH
     * "Copper Lug" term, not the Chinese "铜鼻子" term that happens
     * to share the slug.
     */
    private static function scope_term_query($query) {
        $taxonomy = $query->get('taxonomy');
        if (!$taxonomy) {
            $obj = $query->get_queried_object();
            if ($obj && isset($obj->taxonomy)) {
                $taxonomy = $obj->taxonomy;
            }
        }
        if ($taxonomy !== 'product_category') {
            return;
        }

        $term = $query->get_queried_object();
        if (!$term || !is_object($term) || !isset($term->term_id)) {
            return;
        }

        $en_id = (int) get_term_meta($term->term_id, 'xxdq_en_term_id', true);
        if ($en_id > 0) {
            $en_term = get_term($en_id, 'product_category');
            if ($en_term && !is_wp_error($en_term)) {
                $query->set('product_category', $en_term->slug);
                $query->queried_object = $en_term;
            }
        }
    }

    /**
     * Merge an extra meta_query condition into an existing one.
     *
     * @param array $existing Existing meta_query.
     * @param array $extra    Extra condition.
     * @return array
     */
    private static function merge_meta($existing, $extra) {
        if (empty($existing)) {
            return [$extra];
        }
        // WP_Query expects a flat list of conditions, or a nested
        // 'relation' array. Normalise both into the flat form.
        if (isset($existing['relation'])) {
            $rel = $existing['relation'];
            unset($existing['relation']);
            $existing[] = $extra;
            return ['relation' => $rel] + $existing;
        }
        $existing[] = $extra;
        return $existing;
    }
}

XXDQ_I18n_Router::init();

add_filter('post_link', [XXDQ_I18n_Router::class, 'filter_post_link'], 10, 3);
add_filter('page_link', [XXDQ_I18n_Router::class, 'filter_page_link'], 10, 3);
add_filter('term_link', [XXDQ_I18n_Router::class, 'filter_term_link'], 10, 3);
add_filter('post_type_link', [XXDQ_I18n_Router::class, 'filter_post_type_link'], 10, 3);
add_filter('post_type_archive_link', [XXDQ_I18n_Router::class, 'filter_post_type_archive_link'], 10, 2);
add_action('pre_get_posts', [XXDQ_I18n_Router::class, 'scope_queries'], 20);
