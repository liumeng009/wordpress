<?php
/**
 * XXDQ i18n — a deterministic sitemap provider for the `product` type.
 *
 * WHY THIS EXISTS
 * ---------------
 * Yoast's own post-type sitemap provider pages its results with:
 *
 *     SELECT ID FROM wp_posts
 *     WHERE post_type = 'product' AND post_status = 'publish'
 *     ORDER BY post_modified ASC
 *     LIMIT 100 OFFSET n
 *
 * `post_modified` is NOT unique. When the catalogue was imported, 61
 * products were written with the SAME second, 53 more with the next
 * second, and so on — 338 published posts shared a timestamp with at
 * least one other post.
 *
 * MySQL does not guarantee a stable order for tied rows, so a LIMIT/
 * OFFSET window over a non-unique sort key can return overlapping or
 * missing rows between two requests. Measured on this site:
 *
 *     batch offset=100 -> 1 repeated row
 *     batch offset=200 -> 1 repeated row
 *     distinct IDs seen: 592 of 594
 *
 * The Chinese product sitemap therefore listed 26 URLs TWICE and
 * silently DROPPED 26 others. Those 26 products could not be
 * discovered through the sitemap at all, and the duplicated URLs
 * looked like a duplicate-content bug to a search engine.
 *
 * The fix
 * -------
 * This provider takes over the `product` sitemap entirely:
 *
 *   - it sorts by a TOTAL order (post_modified, then ID), so the
 *     ordering is deterministic and the LIMIT/OFFSET windows partition
 *     the table exactly,
 *   - it counts the rows of THIS language only, so the pagination
 *     matches what is actually rendered,
 *   - it de-duplicates the URL list as a final safety net,
 *   - it emits the English and the Chinese product sets into their
 *     own sitemaps, with no cross-language leakage.
 *
 * @package XXDQ
 */

if (!defined('ABSPATH')) exit;

/**
 * Sitemap provider for the `product` post type.
 */
class XXDQ_I18n_Product_Sitemap_Provider implements WPSEO_Sitemap_Provider {

    /**
     * The post type this provider handles.
     */
    const POST_TYPE = 'product';

    /**
     * The INTERNAL sitemap type this provider answers to.
     *
     * Yoast's own post-type provider is registered FIRST, and
     * WPSEO_Sitemaps::build_sitemap() stops at the first provider whose
     * `handles_type()` returns true. So the request for
     * /product-sitemap.xml is rewritten to this internal type before
     * the providers are consulted, and Yoast's provider no longer
     * matches it.
     */
    const SITEMAP_TYPE = 'xxdq_product';

    /**
     * Register the hooks.
     */
    public static function init() {
        /* The provider list is assembled on `after_setup_theme` — see
         * WPSEO_Sitemaps::init_sitemaps_providers(). */
        add_filter('wpseo_sitemaps_providers', [__CLASS__, 'register_provider'], 10, 1);

        /* Route the `product` sitemap request to this provider. */
        add_filter('wpseo_build_sitemap_post_type', [__CLASS__, 'route_sitemap_type'], 10, 1);

        /* Keep Yoast's own post-type provider out of the product
         * sitemap AND out of the sitemap index — this provider
         * supplies both. */
        add_filter('wpseo_sitemap_exclude_post_type', [__CLASS__, 'exclude_from_yoast_provider'], 10, 2);
    }

    /**
     * Route the `product` sitemap request to this provider's type.
     *
     * @param string $type The requested sitemap type.
     * @return string
     */
    public static function route_sitemap_type($type) {
        if ($type === self::POST_TYPE) {
            return self::SITEMAP_TYPE;
        }
        return $type;
    }

    /**
     * Exclude the `product` post type from Yoast's own provider.
     *
     * @param bool   $exclude   Whether to exclude.
     * @param string $post_type The post type.
     * @return bool
     */
    public static function exclude_from_yoast_provider($exclude, $post_type = '') {
        if ($post_type === self::POST_TYPE) {
            return true;
        }
        return $exclude;
    }

    /**
     * Register this provider with Yoast.
     *
     * @param array $providers The existing providers.
     * @return array
     */
    public static function register_provider($providers) {
        if (!is_array($providers)) {
            $providers = [];
        }
        $providers[] = new self();
        return $providers;
    }

    /**
     * Check if provider supports given item type.
     *
     * @param string $type Type string to check for.
     * @return bool
     */
    public function handles_type($type) {
        return $type === self::SITEMAP_TYPE;
    }

    /**
     * The IDs of the published products of ONE language.
     *
     * The language is decided by the sitemap URL: the English
     * sitemaps live under /en/, the Chinese ones at the root.
     *
     * @return int[] Ordered product IDs.
     */
    private function product_ids() {
        global $wpdb;

        $is_en = self::is_english_request();

        /* The English products carry `_xxdq_lang = en_US`; the Chinese
         * ones carry no such meta at all. A LEFT JOIN keeps the query
         * to a single round trip. */
        $sql = "SELECT p.ID
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} m
                       ON m.post_id = p.ID
                      AND m.meta_key = '_xxdq_lang'
                      AND m.meta_value = 'en_US'
                WHERE p.post_type = %s
                  AND p.post_status = 'publish'
                  AND p.post_date != '0000-00-00 00:00:00'
                  AND p.post_password = ''
                  AND ((%d = 1 AND m.post_id IS NOT NULL)
                       OR (%d = 0 AND m.post_id IS NULL))
                ORDER BY p.post_modified ASC, p.ID ASC";

        $ids = $wpdb->get_col($wpdb->prepare($sql, self::POST_TYPE, $is_en ? 1 : 0, $is_en ? 1 : 0));

        return array_map('intval', (array) $ids);
    }

    /**
     * Is the current sitemap request for the English site?
     *
     * @return bool
     */
    private static function is_english_request() {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        return (bool) preg_match('#/en/[^/]*sitemap\.xml#', $request_uri);
    }

    /**
     * Get set of sitemaps index link data.
     *
     * @param int $max_entries Entries per sitemap.
     * @return array
     */
    public function get_index_links($max_entries) {
        $total = count($this->product_ids());

        if ($total === 0) {
            return [];
        }

        $max_pages = ($total > $max_entries) ? (int) ceil($total / $max_entries) : 1;

        $index = [];

        for ($page_counter = 0; $page_counter < $max_pages; $page_counter++) {
            $current_page = ($page_counter === 0) ? '' : ($page_counter + 1);

            $index[] = [
                'loc'     => WPSEO_Sitemaps_Router::get_base_url(self::POST_TYPE . '-sitemap' . $current_page . '.xml'),
                'lastmod' => WPSEO_Sitemaps::get_last_modified_gmt(self::POST_TYPE),
            ];
        }

        return $index;
    }

    /**
     * Get set of sitemap link data.
     *
     * @param string $type         Sitemap type.
     * @param int    $max_entries  Entries per sitemap.
     * @param int    $current_page Current page of the sitemap.
     * @return array
     * @throws OutOfBoundsException When an invalid page is requested.
     */
    public function get_sitemap_links($type, $max_entries, $current_page) {
        $ids = $this->product_ids();

        $total = count($ids);

        if ($total === 0) {
            throw new OutOfBoundsException('Invalid sitemap page requested');
        }

        $offset  = ($current_page > 1) ? (($current_page - 1) * $max_entries) : 0;
        $page_ids = array_slice($ids, $offset, $max_entries);

        if (empty($page_ids) && $current_page > 1) {
            throw new OutOfBoundsException('Invalid sitemap page requested');
        }

        $links = [];

        /* The post type archive goes first on page 1. */
        if ($current_page === 1) {
            $links = array_merge($links, $this->get_first_links());
        }

        foreach ($page_ids as $post_id) {
            $post = get_post($post_id);
            if (!$post) {
                continue;
            }

            $url = $this->get_url($post);
            if (!$url) {
                continue;
            }

            $links[] = $url;
        }

        /* Safety net: the SAME URL must never appear twice in one
         * sitemap. */
        return $this->unique_links($links);
    }

    /**
     * The archive links that head the first sitemap page.
     *
     * @return array
     */
    private function get_first_links() {
        $links = [];

        $archive_url = apply_filters(
            'wpseo_sitemap_post_type_archive_link',
            get_post_type_archive_link(self::POST_TYPE),
            self::POST_TYPE
        );

        if ($archive_url) {
            $links[] = [
                'loc' => $archive_url,
                'mod' => WPSEO_Sitemaps::get_last_modified_gmt(self::POST_TYPE),
                'chf' => 'daily',
                'pri' => 1,
            ];
        }

        return apply_filters('wpseo_sitemap_post_type_first_links', $links, self::POST_TYPE);
    }

    /**
     * Build the URL parts for one product.
     *
     * @param WP_Post $post The product.
     * @return array|false
     */
    private function get_url($post) {
        $url = [];

        $url['loc'] = apply_filters('wpseo_xml_sitemap_post_url', get_permalink($post), $post);

        /* External URLs are not allowed in a sitemap. */
        $link_type = YoastSEO()->helpers->url->get_link_type(
            wp_parse_url($url['loc']),
            wp_parse_url(home_url())
        );

        if ($link_type === \Yoast\WP\SEO\Models\SEO_Links::TYPE_EXTERNAL) {
            return false;
        }

        $modified = max($post->post_modified_gmt, $post->post_date_gmt);

        if ($modified !== '0000-00-00 00:00:00') {
            $url['mod'] = $modified;
        }

        $url['chf'] = 'daily';
        $url['pri'] = 1;

        return $url;
    }

    /**
     * Remove duplicate URLs from a link list.
     *
     * @param array $links The link list.
     * @return array
     */
    private function unique_links($links) {
        $seen = [];
        $out  = [];

        foreach ($links as $link) {
            if (empty($link['loc'])) {
                continue;
            }
            if (isset($seen[$link['loc']])) {
                continue;
            }
            $seen[$link['loc']] = true;
            $out[] = $link;
        }

        return $out;
    }
}

XXDQ_I18n_Product_Sitemap_Provider::init();
