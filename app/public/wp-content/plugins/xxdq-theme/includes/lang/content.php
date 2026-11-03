<?php
/**
 * XXDQ i18n — English content storage.
 *
 * The English site reuses the SAME database as the Chinese site. English
 * content lives in parallel objects:
 *
 *   Products / pages / news → parallel POST objects
 *     - linked to their Chinese original via `post_parent`
 *     - post meta `_xxdq_lang` = 'en_US'
 *     - slug suffix `_en` (e.g. `dt-25` → `dt-25_en`)
 *
 *   Product categories → parallel TERMS
 *     - a new term in the SAME taxonomy, linked via term meta
 *       `xxdq_en_term_id` on the Chinese term and `xxdq_zh_term_id` on
 *       the English one
 *     - hierarchy is mirrored (English parent → English parent)
 *
 *   Product spec values → parallel POST META on the English product
 *     - `material_en`, `finish_en`, ...
 *
 * WHY PARALLEL POSTS RATHER THAN POST META
 * ---------------------------------------
 * Yoast keeps one indexable row per post. If English content were post
 * meta on the Chinese post, the Chinese and English pages would share a
 * single Yoast indexable and the two languages would overwrite each
 * other's title / description / canonical. Parallel posts give each
 * language its own Yoast row, its own canonical URL and its own
 * sitemap entry.
 *
 * @package XXDQ
 */

if (!defined('ABSPATH')) exit;

class XXDQ_I18n_Content {

    /* ── Linking helpers ──────────────────────────────────────── */

    /**
     * Find the English counterpart of a Chinese post.
     *
     * The link between the two languages lives in the `_xxdq_zh_id`
     * post meta, NOT in `post_parent` — see create_en_post() for why
     * `post_parent` is deliberately left at 0 (it would make WordPress
     * treat the English page as a child of the Chinese one).
     *
     * @param int $zh_id Chinese post ID.
     * @return int English post ID, or 0.
     */
    public static function get_en_post_id($zh_id) {
        $zh_id = (int) $zh_id;
        if (!$zh_id) {
            return 0;
        }

        /* Primary lookup: the explicit meta link. */
        $en_id = (int) get_post_meta($zh_id, '_xxdq_en_id', true);
        if ($en_id > 0 && get_post($en_id)) {
            return $en_id;
        }

        /* Fallback: scan the English posts for one pointing back at us.
         * The indexable is small (a few hundred rows) and the English
         * posts are cached by the query, so this stays cheap. */
        $children = get_posts([
            'post_type'      => 'any',
            'post_status'    => ['publish', 'draft', 'pending'],
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => '_xxdq_lang',
                    'value' => 'en_US',
                ],
                [
                    'key'   => '_xxdq_zh_id',
                    'value' => $zh_id,
                ],
            ],
            'no_found_rows'  => true,
        ]);
        return $children ? (int) $children[0] : 0;
    }

    /**
     * Find the Chinese counterpart of an English post.
     *
     * @param int $en_id English post ID.
     * @return int Chinese post ID, or 0.
     */
    public static function get_zh_post_id($en_id) {
        $en_id = (int) $en_id;
        if (!$en_id) {
            return 0;
        }
        if (get_post_meta($en_id, '_xxdq_lang', true) !== 'en_US') {
            return 0;
        }

        $zh_id = (int) get_post_meta($en_id, '_xxdq_zh_id', true);
        if ($zh_id > 0 && get_post($zh_id)) {
            return $zh_id;
        }

        /* Fallback: the old post_parent based link. */
        $post = get_post($en_id);
        if ($post && (int) $post->post_parent > 0) {
            return (int) $post->post_parent;
        }
        return 0;
    }

    /**
     * Find the English counterpart of a Chinese term.
     *
     * @param int $zh_term_id Chinese term ID.
     * @return int English term ID, or 0.
     */
    public static function get_en_term_id($zh_term_id) {
        $zh_term_id = (int) $zh_term_id;
        if (!$zh_term_id) {
            return 0;
        }
        $found = get_term_meta($zh_term_id, 'xxdq_en_term_id', true);
        return $found ? (int) $found : 0;
    }

    /**
     * Find the Chinese counterpart of an English term.
     *
     * @param int $en_term_id English term ID.
     * @return int Chinese term ID, or 0.
     */
    public static function get_zh_term_id($en_term_id) {
        $en_term_id = (int) $en_term_id;
        if (!$en_term_id) {
            return 0;
        }
        $found = get_term_meta($en_term_id, 'xxdq_zh_term_id', true);
        return $found ? (int) $found : 0;
    }

    /**
     * The display name of a term in the current language.
     *
     * @param object $term  Term object.
     * @param string $field 'name' or 'description'.
     * @return string
     */
    public static function term_text($term, $field = 'name') {
        if (!is_object($term)) {
            return '';
        }
        if (!XXDQ_I18n::is_en()) {
            return (string) $term->$field;
        }

        $en_id = self::get_en_term_id($term->term_id);
        if ($en_id) {
            $en_term = get_term($en_id);
            if ($en_term && !is_wp_error($en_term)) {
                return (string) $en_term->$field;
            }
        }

        // No English counterpart yet — fall back to the Chinese name so
        // the page still renders something sensible.
        return (string) $term->$field;
    }

    /* ── Product helpers ──────────────────────────────────────── */

    /**
     * Read a product spec value in the current language.
     *
     * @param string $field   Field key, e.g. 'material'.
     * @param int    $post_id Product ID.
     * @return string
     */
    public static function spec($field, $post_id = null) {
        $post_id = $post_id ?: get_the_ID();

        if (XXDQ_I18n::is_en()) {
            $en = get_post_meta($post_id, $field . '_en', true);
            if ($en !== '' && $en !== false) {
                return (string) $en;
            }
            /* `model` is special: it holds the Chinese TYPE DESCRIPTION
             * rather than a model number, so the English product needs
             * its own `model_en` value. */
            if ($field === 'model') {
                $en_model = get_post_meta($post_id, 'model_en', true);
                if ($en_model !== '' && $en_model !== false) {
                    return (string) $en_model;
                }
            }
        }

        $val = function_exists('get_field')
            ? get_field($field, $post_id)
            : get_post_meta($post_id, $field, true);

        if (is_array($val)) {
            return implode(', ', $val);
        }
        return (string) $val;
    }

    /**
     * All product specs for a product, in the current language.
     *
     * @param int $post_id Product ID.
     * @return array
     */
    public static function specs($post_id = null) {
        $post_id = $post_id ?: get_the_ID();
        $fields = ['model', 'wire_range', 'hole_size', 'material', 'finish', 'color_code',
                   'voltage', 'temperature', 'certification', 'package', 'short_desc'];
        $out = [];
        foreach ($fields as $f) {
            $v = self::spec($f, $post_id);
            if ($v !== '') {
                $out[$f] = $v;
            }
        }
        return $out;
    }

    /* ── Creation ─────────────────────────────────────────────── */

    /**
     * Create the English counterpart of a Chinese post.
     *
     * @param int    $zh_id  Chinese post ID.
     * @param array  $fields Overrides: post_title, post_content, post_name,
     *                       post_excerpt.
     * @return int New English post ID, or 0 on failure.
     */
    public static function create_en_post($zh_id, $fields = []) {
        $zh_id = (int) $zh_id;
        $zh    = get_post($zh_id);
        if (!$zh) {
            return 0;
        }

        // Already exists?
        $existing = self::get_en_post_id($zh_id);
        if ($existing) {
            return $existing;
        }

        $slug = isset($fields['post_name'])
            ? $fields['post_name']
            : $zh->post_name . '_en';

        /* NOTE: we deliberately do NOT set `post_parent` to the Chinese
         * post ID.
         *
         * The parent/child relationship is already recorded in the
         * `_xxdq_zh_id` post meta, and `post_parent` has real side
         * effects in WordPress: a page that has children gets the
         * `page-parent` body class, and `get_pages(['parent' => …])`
         * starts returning the English page as a child of the Chinese
         * one. That leaked into the Chinese site's HTML (the
         * `page-parent` class appeared on /about-us/ once the English
         * pages existed).
         *
         * The English pages live in their own hierarchy, mirroring the
         * Chinese one, but the link between the two languages is the
         * meta, not the WP tree. */
        $data = [
            'post_type'      => $zh->post_type,
            'post_status'    => 'draft',
            'post_parent'    => 0,
            'post_title'     => isset($fields['post_title']) ? $fields['post_title'] : $zh->post_title,
            'post_name'      => $slug,
            'post_content'   => isset($fields['post_content']) ? $fields['post_content'] : $zh->post_content,
            'post_excerpt'   => isset($fields['post_excerpt']) ? $fields['post_excerpt'] : $zh->post_excerpt,
            'post_date'      => $zh->post_date,
            'menu_order'     => $zh->menu_order,
        ];

        $en_id = wp_insert_post($data, true);
        if (is_wp_error($en_id) || !$en_id) {
            return 0;
        }

        update_post_meta($en_id, '_xxdq_lang', 'en_US');
        update_post_meta($en_id, '_xxdq_zh_id', $zh_id);
        /* The forward link makes the lookup O(1) instead of a query. */
        update_post_meta($zh_id, '_xxdq_en_id', $en_id);

        return (int) $en_id;
    }

    /**
     * Create the English counterpart of a Chinese term.
     *
     * @param int    $zh_term_id Chinese term ID.
     * @param array  $fields     Overrides: name, slug, description, parent.
     * @return int New English term ID, or 0 on failure.
     */
    public static function create_en_term($zh_term_id, $fields = []) {
        $zh_term_id = (int) $zh_term_id;
        $zh_term    = get_term($zh_term_id, 'product_category');
        if (!$zh_term || is_wp_error($zh_term)) {
            return 0;
        }

        $existing = self::get_en_term_id($zh_term_id);
        if ($existing) {
            return $existing;
        }

        $slug = isset($fields['slug'])
            ? $fields['slug']
            : $zh_term->slug . '-en';

        $parent = 0;
        if ($zh_term->parent) {
            $en_parent = self::get_en_term_id($zh_term->parent);
            $parent    = $en_parent ? $en_parent : 0;
        }

        $args = [
            'taxonomy'    => 'product_category',
            'name'        => isset($fields['name']) ? $fields['name'] : $zh_term->name,
            'slug'        => $slug,
            'description' => isset($fields['description']) ? $fields['description'] : $zh_term->description,
            'parent'      => $parent,
        ];

        $result = wp_insert_term($args['name'], $args['taxonomy'], [
            'slug'        => $args['slug'],
            'description' => $args['description'],
            'parent'      => $args['parent'],
        ]);

        if (is_wp_error($result)) {
            return 0;
        }

        $en_id = (int) $result['term_id'];

        update_term_meta($zh_term_id, 'xxdq_en_term_id', $en_id);
        update_term_meta($en_id, 'xxdq_zh_term_id', $zh_term_id);

        return $en_id;
    }
}
