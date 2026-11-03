<?php
/**
 * XXDQ Blocks — per-product_category term meta.
 *
 * Adds a "封面图" (cover image) field to each product category in
 * WP admin so editors can pick a representative photo per top-level
 * category via the standard Media Library. The image is stored as an
 * attachment ID in term meta; render_category_grid() looks this up
 * first and only falls back to the hardcoded map when a category has
 * no custom image set.
 *
 * Failure mode: if a term has no meta, the existing 11-entry hardcoded
 * map is used unchanged, so the home page grid keeps the same look
 * after this change as before — only the *opt-in* per-category images
 * override.
 */

if (!defined('ABSPATH')) exit;

class XXDQ_Blocks_Term_Meta {

    const META_KEY = 'cover_image_id';
    const TAX      = 'product_category';

    public function __construct() {
        add_action(self::TAX . '_add_form_fields',  [$this, 'add_field']);
        add_action(self::TAX . '_edit_form_fields', [$this, 'edit_field']);
        add_action('created_' . self::TAX,          [$this, 'save']);
        add_action('edited_' . self::TAX,           [$this, 'save']);
        add_action('admin_enqueue_scripts',         [$this, 'enqueue']);
    }

    /** Load the media library + our picker UI on the category edit screens only. */
    public function enqueue($hook) {
        $screen = get_current_screen();
        if (!$screen) return;
        $is_term_screen = in_array($hook, ['edit-tags.php', 'term.php'], true)
            && isset($_GET['taxonomy']) && $_GET['taxonomy'] === self::TAX;
        if (!$is_term_screen) return;

        wp_enqueue_media();
        wp_enqueue_script(
            'xxdq-term-cover-image',
            XXDQ_BLOCKS_URL . 'assets/term-cover-image.js',
            ['jquery'],
            XXDQ_BLOCKS_VERSION,
            true
        );
        wp_enqueue_style(
            'xxdq-term-cover-image',
            XXDQ_BLOCKS_URL . 'assets/term-cover-image.css',
            [],
            XXDQ_BLOCKS_VERSION
        );
    }

    /** "Add new category" form. */
    public function add_field() {
        ?>
        <div class="form-field xxdq-cover-image-wrap">
            <label for="xxdq_cover_image">封面图</label>
            <div class="xxdq-cover-image-picker">
                <input type="hidden" id="xxdq_cover_image" name="xxdq_cover_image" value="" />
                <div class="xxdq-cover-image-preview"></div>
                <button type="button" class="button xxdq-cover-image-select">从媒体库选择</button>
                <button type="button" class="button xxdq-cover-image-remove" style="display:none;">移除</button>
                <p class="description">首页分类卡片缩略图（推荐 600×600 或更大）。留空则使用系统默认图。</p>
            </div>
        </div>
        <?php
    }

    /** "Edit category" form. */
    public function edit_field($term) {
        $val     = (int) get_term_meta($term->term_id, self::META_KEY, true);
        $preview = '';
        if ($val && wp_get_attachment_url($val)) {
            $img = wp_get_attachment_image($val, 'medium', false, [
                'style' => 'max-width:300px;height:auto;display:block;',
            ]);
            $preview = $img ? $img : '';
        }
        ?>
        <tr class="form-field xxdq-cover-image-wrap">
            <th scope="row"><label for="xxdq_cover_image">封面图</label></th>
            <td>
                <div class="xxdq-cover-image-picker">
                    <input type="hidden" id="xxdq_cover_image" name="xxdq_cover_image" value="<?php echo esc_attr($val); ?>" />
                    <div class="xxdq-cover-image-preview"><?php echo $preview; ?></div>
                    <button type="button" class="button xxdq-cover-image-select"><?php echo $val ? '更换' : '从媒体库选择'; ?></button>
                    <button type="button" class="button xxdq-cover-image-remove" <?php echo $val ? '' : 'style="display:none;"'; ?>>移除（恢复默认）</button>
                    <p class="description">首页分类卡片缩略图（推荐 600×600 或更大）。留空则使用系统默认图。</p>
                </div>
            </td>
        </tr>
        <?php
    }

    /** Persist the chosen attachment ID. Empty value clears the override. */
    public function save($term_id) {
        if (!current_user_can('manage_categories')) return;
        if (!isset($_POST['xxdq_cover_image'])) return;

        $val = (int) $_POST['xxdq_cover_image'];
        if ($val > 0 && wp_get_attachment_url($val)) {
            update_term_meta($term_id, self::META_KEY, $val);
        } else {
            delete_term_meta($term_id, self::META_KEY);
        }
    }

    /**
     * Resolve the cover image URL for a given term.
     * Public so the render layer can call this without duplicating logic.
     * Order:  term meta → hardcoded map → prefix map → pre-* default → fallback.
     */
    public static function resolve_cover_image($term_or_slug, $img_map, $prefix_map, $fallback_img) {
        if (is_object($term_or_slug)) {
            $slug = $term_or_slug->slug;
            $tid  = (int) $term_or_slug->term_id;
        } else {
            $slug = (string) $term_or_slug;
            $tid  = 0;
        }

        /* The English site keeps parallel terms whose WP slug carries a
         * "-2" suffix ("copper-2") because the Chinese term already
         * occupies "copper". The PUBLIC English slug is stored in the
         * `_xxdq_en_slug` meta — the cover-image map is keyed by the
         * public slug, so we have to look it up. Without this every
         * English category card fell back to the placeholder image. */
        if ($tid) {
            $en_slug = get_term_meta($tid, '_xxdq_en_slug', true);
            if ($en_slug) {
                $slug = $en_slug;
            }
        }

        // 1) Term meta (the per-category override)
        if ($tid) {
            $custom = (int) get_term_meta($tid, self::META_KEY, true);
            if ($custom) {
                $url = wp_get_attachment_image_url($custom, 'medium_large');
                if ($url) return $url;
                $url = wp_get_attachment_url($custom);
                if ($url) return $url;
            }
        }

        // 2) Hardcoded map
        if (isset($img_map[$slug])) return $img_map[$slug];

        // 3) Prefix map
        foreach ($prefix_map as $needle => $target) {
            if ($needle !== $target) continue;
            if (strpos($slug, $needle) === 0 && isset($img_map[$target])) {
                return $img_map[$target];
            }
        }

        // 4) Pre-* family
        if (strpos($slug, 'pre-') === 0 && isset($img_map['pre-insulated-terminals'])) {
            return $img_map['pre-insulated-terminals'];
        }

        return $fallback_img;
    }
}

new XXDQ_Blocks_Term_Meta();
