<?php
/**
 * Frontend rendering helpers — real image first, SVG fallback.
 *
 * Provides:
 *  - xxdq_pim_product_image($post_id, $size, $attr)  echo <img> with fallback
 *  - xxdq_pim_render_pdf_list($post_id)               echo download list
 *  - xxdq_pim_render_tech_image($post_id)            echo tech image
 *
 * Designed to be called from xxdq-products template or future block plugins.
 */

if (!defined('ABSPATH')) exit;

class XXDQ_PIM_Render {

    private static $instance;

    public static function instance() {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        // No-op; helpers below are static-callable too.
    }

    /**
     * Return the best image URL for a product: real featured image > SVG fallback.
     */
    public static function image_url($post_id, $size = 'medium') {
        // 1) 实物图 — real product photo (used on product center card)
        $real = get_field('real_image', $post_id);
        if ($real) {
            $url = is_numeric($real) ? wp_get_attachment_url((int) $real) : (is_array($real) && !empty($real['url']) ? $real['url'] : '');
            if ($url) return $url;
        }
        // 2) Featured image (WP thumbnail) — back-compat
        if (has_post_thumbnail($post_id)) {
            $url = get_the_post_thumbnail_url($post_id, $size);
            if ($url) return $url;
        }
        // 3) 描述图 — spec/manual sheet (used on detail page)
        $tech = get_field('tech_image', $post_id);
        if ($tech) {
            $url = is_numeric($tech) ? wp_get_attachment_url((int) $tech) : (is_array($tech) && !empty($tech['url']) ? $tech['url'] : '');
            if ($url) return $url;
        }
        // 4) SVG placeholder
        $model = get_field('model', $post_id) ?: get_the_title($post_id);
        $cats  = wp_get_post_terms($post_id, 'product_category', ['fields' => 'slugs']);
        $cat   = !empty($cats) ? $cats[0] : 'default';
        return home_url('/?xxdq_img=' . urlencode($model) . '&cat=' . $cat);
    }

    /**
     * Echo an <img> with proper class + alt.
     */
    public static function image_tag($post_id, $size = 'medium', $class = 'xxdq-prod-img', $alt = '') {
        $url = self::image_url($post_id, $size);
        $alt = $alt ?: (get_field('model', $post_id) ?: get_the_title($post_id));
        echo '<img class="' . esc_attr($class) . '" src="' . esc_url($url) . '" alt="' . esc_attr($alt) . '" loading="lazy">';
    }

    /**
     * Echo the tech image (or empty if none).
     */
    public static function tech_image_tag($post_id, $size = 'large', $class = 'xxdq-prod-tech', $alt = '') {
        $img = get_field('tech_image', $post_id);
        if (!$img || empty($img['ID'])) return;
        echo wp_get_attachment_image($img['ID'], $size, false, ['class' => $class, 'alt' => $alt ?: '技术图', 'loading' => 'lazy']);
    }

    /**
     * Echo the PDF download list.
     */
    public static function pdf_list($post_id, $class = 'xxdq-pdf-list') {
        $att = get_field('attachment', $post_id);
        if (!$att || empty($att['ID'])) return;

        $url  = wp_get_attachment_url($att['ID']);
        if (!$url) return;
        $name = $att['title'] ?: basename($url);
        $size = !empty($att['filesize']) ? size_format($att['filesize']) : '';

        echo '<div class="' . esc_attr($class) . '">';
        echo '<a class="' . esc_attr($class) . '__item" href="' . esc_url($url) . '" target="_blank" rel="noopener">';
        echo '<span class="' . esc_attr($class) . '__icon">📄</span>';
        echo '<span class="' . esc_attr($class) . '__name">' . esc_html($name) . '</span>';
        if ($size) echo '<span class="' . esc_attr($class) . '__size">' . esc_html($size) . '</span>';
        echo '<span class="' . esc_attr($class) . '__action">下载</span>';
        echo '</a>';
        echo '</div>';
    }

    /**
     * Aggregate all PDFs grouped by product_category.
     * Returns array [ 'category_name' => [ ['title'=>..., 'url'=>..., 'model'=>...], ... ], ... ]
     */
    public static function aggregate_pdfs_by_category() {
        $out = [];
        $products = get_posts([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);
        foreach ($products as $p) {
            $att = get_field('attachment', $p->ID);
            if (!$att || empty($att['ID'])) continue;

            $cats = wp_get_post_terms($p->ID, 'product_category', ['fields' => 'names']);
            $cat_name = !empty($cats) ? $cats[0] : '其他';
            if (!isset($out[$cat_name])) $out[$cat_name] = [];
            $out[$cat_name][] = [
                'model' => get_field('model', $p->ID) ?: $p->post_title,
                'title' => $att['title'] ?: basename($att['url'] ?? ''),
                'url'   => wp_get_attachment_url($att['ID']),
                'size'  => !empty($att['filesize']) ? size_format($att['filesize']) : '',
            ];
        }
        return $out;
    }
}
