<?php
/**
 * XXDQ i18n — language-split XML sitemaps.
 *
 * WHY THIS EXISTS
 * ---------------
 * Yoast builds one sitemap per post type, and the Chinese and the
 * English products live in the SAME post type — so /product-sitemap.xml
 * used to list 595 URLs: 297 Chinese and 297 English, side by side.
 *
 * That is not wrong for a crawler, but it is wrong for a search engine
 * that wants to know which URLs belong to which language:
 *
 *   - the hreflang alternates live in the HTML, but the sitemap gives
 *     no way to group the two languages apart,
 *   - the English sitemap mixes hundreds of Chinese URLs, so a crawler
 *     spends its budget on pages that are not the English site.
 *
 * The two languages therefore get their own sitemap:
 *
 *   /product-sitemap.xml            → the Chinese products
 *   /en/product-sitemap.xml         → the English products
 *
 * The same split is applied to the news, the pages and the product
 * categories, so each language has a coherent set of sitemaps.
 *
 * @package XXDQ
 */

if (!defined('ABSPATH')) exit;

class XXDQ_I18n_Sitemap {

    /**
     * Register the hooks.
     */
    public static function init() {
        /* Filter the URLs inside a sitemap. */
        add_filter('wpseo_sitemap_entry', [__CLASS__, 'filter_entry'], 10, 3);

        /* Filter the list of sitemaps in the index. */
        add_filter('wpseo_sitemap_index_links', [__CLASS__, 'filter_index_links'], 10, 1);

        /* Correct the front page / archive links, which bypass the
         * entry filter. */
        add_filter('wpseo_sitemap_post_type_first_links', [__CLASS__, 'filter_first_links'], 10, 2);
    }

    /**
     * The language of a URL path.
     *
     * @param string $path The URL path.
     * @return string 'en' or 'zh'.
     */
    private static function path_lang($path) {
        return (preg_match('#/en(/|$)#', (string) $path)) ? 'en' : 'zh';
    }

    /**
     * The language of a post.
     *
     * @param int $post_id Post ID.
     * @return string 'en' or 'zh'.
     */
    private static function post_lang($post_id) {
        $lang = get_post_meta($post_id, '_xxdq_lang', true);
        if ($lang === 'en_US') {
            return 'en';
        }
        return 'zh';
    }

    /**
     * Is this the English home page post?
     *
     * The English home page is a `page` whose `_xxdq_en_path` is empty
     * — it renders at /en/ itself, which is the SAME URL as the site's
     * front page.
     *
     * @param int $post_id Post ID.
     * @return bool
     */
    private static function is_english_home_post($post_id) {
        static $home_ids = null;

        if ($home_ids === null) {
            $home_ids = array();

            $option_id = (int) get_option('xxdq_en_home_id');
            if ($option_id > 0) {
                $home_ids[] = $option_id;
            }

            /* Fall back to scanning for the English page with an empty
             * path. */
            $found = get_posts([
                'post_type'   => 'page',
                'post_status' => 'publish',
                'numberposts' => 20,
                'meta_key'    => '_xxdq_lang',
                'meta_value'  => 'en_US',
                'fields'      => 'ids',
            ]);

            foreach ($found as $id) {
                $path = get_post_meta($id, '_xxdq_en_path', true);
                if ($path === '' || $path === false || $path === null) {
                    $home_ids[] = (int) $id;
                }
            }

            $home_ids = array_unique(array_filter($home_ids));
        }

        return in_array((int) $post_id, $home_ids, true);
    }

    /**
     * Remove the URLs of the other language from a sitemap.
     *
     * @param array  $url      The sitemap entry.
     * @param string $type     The object type ('post', 'term', 'user').
     * @param mixed  $object   The object.
     * @return array
     */
    public static function filter_entry($url, $type = '', $object = null) {
        if (!is_array($url) || empty($url['loc'])) {
            return $url;
        }

        /* The current sitemap request decides which language's URLs
         * survive. The sitemap URL carries the language: the English
         * sitemaps live under /en/. */
        $want = self::requested_lang();

        /* Posts: decided by the post's own language meta. */
        if ($type === 'post' && is_object($object) && isset($object->ID)) {
            if (self::post_lang($object->ID) !== $want) {
                return array();
            }

            /* The English home page lives at /en/ — the SAME URL as
             * the site's front page. Yoast lists the front page as a
             * "first link" AND the English home post as a regular
             * entry, so /en/ appeared twice in the English page
             * sitemap. A duplicate URL in a sitemap is a duplicate-
             * content signal, so the second one is dropped here. */
            if (self::is_english_home_post($object->ID)) {
                return array();
            }

            return $url;
        }

        /* Terms: the English categories are the ones linked back to a
         * Chinese term. `xxdq_zh_term_id` is stored on the ENGLISH
         * term and points at its Chinese parent. */
        if ($type === 'term' && is_object($object) && isset($object->term_id)) {
            $is_en_term = get_term_meta((int) $object->term_id, 'xxdq_zh_term_id', true);

            if ($want === 'en' && empty($is_en_term)) {
                return array();
            }
            if ($want === 'zh' && !empty($is_en_term)) {
                return array();
            }

            /* The term's own URL already carries the language, because
             * the router rewrites `term_link` for English terms. The
             * path check below catches any term whose link was not
             * rewritten. */
            if (self::path_lang($url['loc']) !== $want) {
                return array();
            }

            return $url;
        }

        /* Everything else (the front page, the author archives) is
         * filtered by the URL path. */
        if (self::path_lang($url['loc']) !== $want) {
            return array();
        }

        return $url;
    }

    /**
     * Correct the front page and the archive links of the first links.
     *
     * Yoast's "first links" for the `page` post type (the front page)
     * and for the other post types (the archive URL) are added BEFORE
     * the entries are filtered, so they never pass through
     * `wpseo_sitemap_entry`. They are corrected here instead.
     *
     * @param array  $links     The first links.
     * @param string $post_type The post type.
     * @return array
     */
    public static function filter_first_links($links, $post_type = '') {
        if (!is_array($links) || empty($links)) {
            return $links;
        }

        $is_en = self::requested_lang() === 'en';

        foreach ($links as $i => $link) {
            if (empty($link['loc'])) {
                continue;
            }

            $loc = $link['loc'];

            if ($is_en) {
                /* The English front page and the English archives. */
                if (self::path_lang($loc) === 'zh') {
                    $links[$i]['loc'] = self::to_en_path($loc);
                }
            } else {
                /* The Chinese sitemap must not carry the English
                 * archives. */
                if (self::path_lang($loc) === 'en') {
                    unset($links[$i]);
                }
            }
        }

        $links = array_values($links);

        /* The English home page appears twice: once as the front page
         * and once as the English home post. Only one copy belongs in
         * the sitemap. */
        $seen = array();
        $out  = array();
        foreach ($links as $link) {
            $loc = isset($link['loc']) ? $link['loc'] : '';
            if ($loc !== '' && isset($seen[$loc])) {
                continue;
            }
            if ($loc !== '') {
                $seen[$loc] = true;
            }
            $out[] = $link;
        }

        return $out;
    }

    /**
     * Which language the current sitemap request is for.
     *
     * @return string 'en' or 'zh'.
     */
    private static function requested_lang() {
        static $lang = null;

        if ($lang !== null) {
            return $lang;
        }

        $lang = 'zh';

        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';

        /* "/en/product-sitemap.xml" → the English sitemap. */
        if (preg_match('#/en/[^/]*sitemap\.xml#', $request_uri)) {
            $lang = 'en';
        }

        return $lang;
    }

    /**
     * Split the sitemap index into the Chinese and the English set.
     *
     * Yoast's index lists one sitemap per post type. Each of those is
     * duplicated with an "/en/" version, so the index ends up with a
     * Chinese sitemap and an English sitemap per content type.
     *
     * @param array $links The index links.
     * @return array
     */
    public static function filter_index_links($links) {
        if (!is_array($links) || empty($links)) {
            return $links;
        }

        /* The English sitemap index (/en/sitemap.xml) lists the English
     * sitemaps, which all live under /en/. The Chinese index lists
     * the Chinese ones. */
        $is_en_index = self::requested_lang() === 'en';

        $out = array();

        foreach ($links as $link) {
            if (empty($link['loc'])) {
                continue;
            }

            $loc = $link['loc'];

            if ($is_en_index) {
                /* Rewrite every sitemap URL to its English twin and
                 * drop the ones that have no English counterpart. */
                $en_loc = self::to_en_path($loc);
                if ($en_loc === $loc) {
                    /* Already an English URL — keep it. */
                    $out[] = $link;
                    continue;
                }
                $en_link = $link;
                $en_link['loc'] = $en_loc;
                $out[] = $en_link;
                continue;
            }

            /* The Chinese index: skip anything under /en/ — the
             * English sitemaps are listed in the English index. */
            if (self::path_lang($loc) === 'en') {
                continue;
            }

            $out[] = $link;
        }

        /* The ROOT index (/sitemap.xml) is the entry point that Google
         * reads first. It must point at BOTH language sets, otherwise
         * the English sitemaps are never discovered. */
        if (self::is_root_index()) {
            foreach ($out as $link) {
                $en_link = $link;
                $en_link['loc'] = self::to_en_path($link['loc']);
                $out[] = $en_link;
            }
        }

        return $out;
    }

    /**
     * Is the current request for the ROOT sitemap index?
     *
     * The root index lives at /sitemap_index.xml (and /sitemap.xml
     * redirects to it). The English one lives at /en/sitemap.xml.
     *
     * @return bool
     */
    private static function is_root_index() {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';

        return (bool) preg_match('#/sitemap(_index)?\.xml$#', $request_uri)
            && !preg_match('#/en/sitemap#', $request_uri);
    }

    /**
     * Turn a site-relative sitemap URL into its English twin.
     *
     * "/product-sitemap.xml" → "/en/product-sitemap.xml"
     *
     * @param string $loc The sitemap URL.
     * @return string
     */
    private static function to_en_path($loc) {
        /* Work on the path only — the host stays the same. */
        $parts = parse_url($loc);
        $path  = isset($parts['path']) ? $parts['path'] : '/';

        if (strpos($path, '/en/') === 0) {
            return $loc;
        }

        $en_path = '/en' . $path;

        if (isset($parts['host'])) {
            $scheme = isset($parts['scheme']) ? $parts['scheme'] : 'https';
            $port   = isset($parts['port']) ? ':' . $parts['port'] : '';
            return $scheme . '://' . $parts['host'] . $port . $en_path;
        }

        return $en_path;
    }
}

XXDQ_I18n_Sitemap::init();
