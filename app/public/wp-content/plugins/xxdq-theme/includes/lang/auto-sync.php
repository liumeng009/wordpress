<?php
/**
 * XXDQ i18n — automatic English draft creation.
 *
 * THE WORKFLOW THIS ENABLES
 * -------------------------
 * The catalogue is entered ONCE, in Chinese. Every time a Chinese
 * product or page is saved, this module creates or refreshes the
 * parallel English post, so the English site never falls behind the
 * Chinese one.
 *
 * The English post is created as a DRAFT on purpose:
 *
 *   - a machine translation of a new product is not publish-ready, and
 *     publishing an unreviewed page on the English site would hurt its
 *     credibility with Western buyers,
 *   - the catalogue manager reviews the draft, corrects the English, and
 *     publishes it in one click.
 *
 * Once published, the English post is no longer overwritten — the
 * editor's corrections must survive. Only a missing field is filled in.
 *
 * @package XXDQ
 */

if (!defined('ABSPATH')) exit;

class XXDQ_I18n_Auto_Sync {

    /**
     * The post types that get an English counterpart.
     *
     * @return array
     */
    public static function post_types() {
        return array('product', 'page', 'news');
    }

    /**
     * Register the hooks.
     */
    public static function init() {
        add_action('save_post', [__CLASS__, 'on_save_post'], 20, 3);
        add_action('deleted_post', [__CLASS__, 'on_deleted_post'], 10, 2);
    }

    /**
     * Create or refresh the English counterpart of a saved post.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an update.
     */
    public static function on_save_post($post_id, $post = null, $update = false) {
        /* Never run for revisions, autosaves or the English posts
         * themselves — that would loop forever. */
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        if (!in_array(get_post_type($post_id), self::post_types(), true)) {
            return;
        }
        if (get_post_meta($post_id, '_xxdq_lang', true) === 'en_US') {
            return;
        }

        /* Only act on the real publish transition, so that a bulk edit
         * of unrelated fields does not rewrite 300 English posts. */
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            return;
        }

        self::sync($post_id);
    }

    /**
     * Delete the English counterpart when the Chinese post is deleted.
     *
     * @param int           $post_id Post ID.
     * @param WP_Post|null  $post    Post object.
     */
    public static function on_deleted_post($post_id, $post = null) {
        if (!in_array(get_post_type($post_id), self::post_types(), true)) {
            return;
        }
        if (get_post_meta($post_id, '_xxdq_lang', true) === 'en_US') {
            return;
        }

        $en_id = class_exists('XXDQ_I18n_Content')
            ? XXDQ_I18n_Content::get_en_post_id($post_id)
            : 0;

        if ($en_id) {
            wp_delete_post($en_id, true);
        }
    }

    /**
     * Create or refresh the English counterpart of a Chinese post.
     *
     * @param int $zh_id Chinese post ID.
     * @return int English post ID, or 0 on failure.
     */
    public static function sync($zh_id) {
        $zh = get_post($zh_id);
        if (!$zh) {
            return 0;
        }

        $en_id = class_exists('XXDQ_I18n_Content')
            ? XXDQ_I18n_Content::get_en_post_id($zh_id)
            : 0;

        $fields = self::build_fields($zh);

        if (!$en_id) {
            /* ── Create ─────────────────────────────────────── */
            $en_id = XXDQ_I18n_Content::create_en_post($zh_id, $fields);
            if (!$en_id) {
                return 0;
            }
            self::write_specs($en_id, $zh_id);

            /* Now that the English spec values exist, rebuild the body
             * from them — the first pass had to translate the Chinese
             * meta on the fly. */
            if ($zh->post_type === 'product' && class_exists('XXDQ_I18n_Product_Body')) {
                $body = XXDQ_I18n_Product_Body::build($en_id, get_the_title($en_id), $zh_id);
                if ($body !== '') {
                    wp_update_post(array('ID' => $en_id, 'post_content' => $body));
                }
            }
            return $en_id;
        }

        /* ── Refresh ──────────────────────────────────────────
         * A published English post is never overwritten — the
         * editor's corrections must survive. A draft IS refreshed,
         * because nobody has corrected it yet. */
        $en = get_post($en_id);
        if ($en && $en->post_status === 'publish') {
            /* Only fill in what is missing. */
            $patch = array();
            if (trim((string) $en->post_content) === '' && $fields['post_content'] !== '') {
                $patch['post_content'] = $fields['post_content'];
            }
            if (trim((string) $en->post_title) === '' || self::is_chinese($en->post_title)) {
                $patch['post_title'] = $fields['post_title'];
            }
            if (!empty($patch)) {
                $patch['ID'] = $en_id;
                wp_update_post($patch);
            }
            self::write_specs($en_id, $zh_id);
            return $en_id;
        }

        /* Draft: refresh it completely. */
        wp_update_post(array(
            'ID'           => $en_id,
            'post_title'   => $fields['post_title'],
            'post_content' => $fields['post_content'],
            'post_excerpt' => $fields['post_excerpt'],
        ));
        self::write_specs($en_id, $zh_id);

        return $en_id;
    }

    /**
     * The English field values for a Chinese post.
     *
     * @param WP_Post $zh Chinese post.
     * @return array
     */
    private static function build_fields($zh) {
        $title = '';
        $body  = '';
        $excerpt = '';

        if ($zh->post_type === 'product') {
            $title = xxdq_translate_product_title($zh->post_title);
            if ($title === null) {
                $title = self::fallback_title($zh);
            }

            /* The body comes from the generator, which fills the
             * English copy from the English spec values. The generator
             * reads the ENGLISH title off the English post — which does
             * not exist yet at this point — so the title is passed in
             * explicitly. */
            if (class_exists('XXDQ_I18n_Product_Body')) {
                $body = XXDQ_I18n_Product_Body::build($zh->ID, $title, $zh->ID);
            }

            $zh_desc = (string) get_post_meta($zh->ID, 'short_desc', true);
            $desc = xxdq_translate_short_desc($zh_desc);
            if ($desc !== null) {
                $excerpt = $desc;
            }
        } else {
            /* Pages and news. */
            $title = self::translate_page_title($zh->post_title);
            $body  = self::translate_page_body($zh->post_content);
            $excerpt = trim((string) $zh->post_excerpt);
        }

        return array(
            'post_title'   => $title,
            'post_content' => $body,
            'post_excerpt' => $excerpt,
        );
    }

    /**
     * A readable English title when the dictionary has no entry.
     *
     * Keeps the model code and drops the Chinese type description, so
     * the page still has a meaningful title instead of Chinese text.
     *
     * @param WP_Post $zh Chinese post.
     * @return string
     */
    private static function fallback_title($zh) {
        $title = trim((string) $zh->post_title);
        if ($title === '' || !self::is_chinese($title)) {
            return $title;
        }

        /* "<MODEL> <中文类型>" → "<MODEL>". */
        if (preg_match('/^([A-Za-z0-9][A-Za-z0-9\-–\.\/]*)/u', $title, $m)) {
            return $m[1];
        }
        return $title;
    }

    /**
     * Translate a page / news title.
     *
     * @param string $zh_title Chinese title.
     * @return string
     */
    private static function translate_page_title($zh_title) {
        $zh_title = trim((string) $zh_title);
        if ($zh_title === '' || !self::is_chinese($zh_title)) {
            return $zh_title;
        }

        /* A small map of the site's own pages. */
        $map = array(
            '关于我们'   => 'About Us',
            '产品中心'   => 'Products',
            '资讯中心'   => 'News',
            '询价单'     => 'Inquiry',
            '询价单页'   => 'Inquiry',
            '联系我们'   => 'Contact Us',
            '首页'       => 'Home',
            '新闻资讯'   => 'News',
            '常见问题'   => 'FAQ',
        );
        if (isset($map[$zh_title])) {
            return $map[$zh_title];
        }

        /* Try the generic translator. */
        $t = xxdq_translate_product_title($zh_title);
        if ($t !== null && !self::is_chinese($t)) {
            return $t;
        }

        return $zh_title;
    }

    /**
     * Translate a page / news body.
     *
     * The general translator handles the paragraphs the PIM generated;
     * anything it cannot map is kept as-is so a human can finish it.
     *
     * @param string $zh_body Chinese body.
     * @return string
     */
    private static function translate_page_body($zh_body) {
        $zh_body = trim((string) $zh_body);
        if ($zh_body === '') {
            return '';
        }

        $out = array();
        foreach (preg_split('/\n{2,}/u', $zh_body) as $para) {
            $para = trim($para);
            if ($para === '') {
                continue;
            }
            $t = xxdq_translate_text($para);
            $out[] = $t === null ? $para : $t;
        }

        return implode("\n\n", $out);
    }

    /**
     * Write the translated spec values onto the English product.
     *
     * ONLY EMPTY fields are written. A published English product may
     * have been corrected by the editor, and those corrections must
     * survive a later re-sync of the Chinese original.
     *
     * @param int $en_id English product ID.
     * @param int $zh_id Chinese product ID.
     */
    private static function write_specs($en_id, $zh_id) {
        $fields = array('material', 'finish', 'color_code', 'voltage', 'temperature', 'certification', 'package', 'wire_range', 'hole_size');

        foreach ($fields as $field) {
            $key = $field . '_en';

            /* Never overwrite a value that is already there. */
            if (trim((string) get_post_meta($en_id, $key, true)) !== '') {
                continue;
            }

            $zh_value = trim((string) get_post_meta($zh_id, $field, true));
            if ($zh_value === '') {
                continue;
            }

            /* The language-neutral fields are identical on both sides. */
            $neutral = array('wire_range', 'hole_size', 'voltage');
            if (in_array($field, $neutral, true)) {
                update_post_meta($en_id, $key, $zh_value);
                continue;
            }

            $en_value = xxdq_translate_spec_value($field, $zh_value);
            if ($en_value !== null) {
                update_post_meta($en_id, $key, $en_value);
            }
        }

        /* `model_en` holds the English TYPE of the product. */
        if (trim((string) get_post_meta($en_id, 'model_en', true)) === '') {
            $zh_model = trim((string) get_post_meta($zh_id, 'model', true));
            if ($zh_model !== '') {
                $map = xxdq_product_type_map();
                $norm_map = array();
                foreach ($map as $zh => $en) {
                    $norm_map[xxdq_normalize_type_text($zh)] = $en;
                }
                $norm = xxdq_normalize_type_text($zh_model);
                if (isset($norm_map[$norm])) {
                    update_post_meta($en_id, 'model_en', $norm_map[$norm]);
                }
            }
        }

        /* `short_desc_en` is the English one-liner under the title. */
        if (trim((string) get_post_meta($en_id, 'short_desc_en', true)) === '') {
            $zh_sd = trim((string) get_post_meta($zh_id, 'short_desc', true));
            if ($zh_sd !== '') {
                $t = xxdq_translate_short_desc($zh_sd);
                if ($t !== null) {
                    update_post_meta($en_id, 'short_desc_en', $t);
                }
            }
        }
    }

    /**
     * Does the string contain Chinese characters?
     *
     * @param string $text Text to test.
     * @return bool
     */
    private static function is_chinese($text) {
        return is_string($text) && (bool) preg_match('/[\x{4e00}-\x{9fff}]/u', $text);
    }
}

XXDQ_I18n_Auto_Sync::init();
