<?php
/**
 * Plugin Name: XXDQ Products
 * Description: Product CPT, single/archive templates.
 * Version: 2.0.0
 * Author: XXDQ
 * Text Domain: xxdq-products
 */

if (!defined('ABSPATH')) exit;

define('XXDQ_PRODUCTS_VERSION', '2.0.0');
define('XXDQ_PRODUCTS_DIR', plugin_dir_path(__FILE__));

/* ── Register CPT ───────────────────────────────────────────── */
add_action('init', function () {
    register_post_type('product', [
        'labels' => [
            'name'               => '产品',
            'singular_name'      => '产品',
            'add_new_item'       => '添加产品',
            'edit_item'          => '编辑产品',
            'all_items'          => '所有产品',
            'menu_name'          => '产品中心',
        ],
        'public'              => true,
        'has_archive'         => true,
        'show_in_rest'        => true,
        'menu_position'       => 5,
        'menu_icon'           => 'dashicons-products',
        'supports'            => ['title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions'],
        'rewrite'             => ['slug' => 'product', 'with_front' => false],
        'taxonomies'          => ['product_category'],
    ]);

    /* product_category — top-level product categories (sidebar nav).
     * Hierarchical so we can have parent / child if needed. */
    register_taxonomy('product_category', 'product', [
        'labels' => [
            'name'              => '产品分类',
            'singular_name'     => '产品分类',
            'search_items'      => '搜索分类',
            'all_items'         => '所有分类',
            'parent_item'       => '父级分类',
            'parent_item_colon' => '父级分类:',
            'edit_item'         => '编辑分类',
            'update_item'       => '更新分类',
            'add_new_item'      => '添加新分类',
            'new_item_name'     => '新分类名称',
            'menu_name'         => '产品分类',
        ],
        'hierarchical'      => true,
        'public'            => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'show_in_nav_menus' => true,
        'rewrite'           => ['slug' => 'product-category', 'with_front' => false, 'hierarchical' => true],
    ]);
    /* product_series was removed in 2026-09: merged into hierarchical product_category. */
});

/* ── Front-end templates ────────────────────────────────────── */
/* Single product */
add_filter('single_template', function ($t) {
    if (is_singular('product')) {
        $custom = plugin_dir_path(__FILE__) . 'templates/single-product.php';
        if (file_exists($custom)) return $custom;
        // fallback: load theme's single-product.php or single.php
        $theme = locate_template(['single-product.php']);
        return $theme ?: $t;
    }
    return $t;
});

/* Product archive */
add_filter('archive_template', function ($t) {
    if (is_post_type_archive('product')) {
        $custom = plugin_dir_path(__FILE__) . 'templates/archive-product.php';
        if (file_exists($custom)) return $custom;
        return locate_template(['archive-product.php']) ?: $t;
    }
    return $t;
});

/* Product taxonomy archive (e.g. /product-category/copper/).
 * archive_template only fires for post_type archives, so without this
 * taxonomy pages fall back to the default index.php / search.php and the
 * sidebar + product grid never render. */
add_filter('taxonomy_template', function ($t) {
    if (is_tax('product_category')) {
        $custom = plugin_dir_path(__FILE__) . 'templates/archive-product.php';
        if (file_exists($custom)) return $custom;
        return locate_template(['taxonomy-product_category.php']) ?: $t;
    }
    return $t;
});

/* ── Helper: get product specs from ACF ─────────────────────── */
function xxdq_product_specs($post_id = null) {
    $post_id = $post_id ?: get_the_ID();
    $fields = ['model','wire_range','hole_size','material','finish','color_code',
               'voltage','temperature','certification','package','short_desc'];
    $out = [];
    foreach ($fields as $f) {
        $v = function_exists('get_field') ? get_field($f, $post_id) : get_post_meta($post_id, $f, true);
        if (!empty($v)) $out[$f] = $v;
    }
    return $out;
}

/* Media helpers (read ACF field -- may be ID or URL) */
function xxdq_media_url($field, $post_id = null) {
    $post_id = $post_id ?: get_the_ID();
    $val = function_exists('get_field') ? get_field($field, $post_id) : get_post_meta($post_id, $field, true);
    if (!$val) return '';
    if (is_numeric($val)) {
        $src = wp_get_attachment_image_url((int)$val, 'full');
        if ($src) return $src;
    }
    if (is_array($val) && !empty($val['url'])) return $val['url'];
    if (is_string($val)) return $val;
    return '';
}

/**
 * Build a deduplicated gallery of full-size image URLs from the ACF image
 * fields registered for a product (real_image, tech_image, image_2..4).
 *
 * Returns an array of URLs in priority order. Empty fields are skipped.
 */
function xxdq_product_gallery($post_id = null, $size = 'large') {
    $post_id = $post_id ?: get_the_ID();
    $fields  = ['real_image', 'tech_image', 'image_2', 'image_3', 'image_4'];
    $seen    = [];
    $out     = [];
    foreach ($fields as $f) {
        $val = function_exists('get_field') ? get_field($f, $post_id) : null;
        if (!$val) continue;
        $aid = is_numeric($val) ? (int) $val : 0;
        $src = $aid ? wp_get_attachment_image_url($aid, $size) : '';
        if (!$src && is_array($val) && !empty($val['url'])) $src = $val['url'];
        if (!$src) continue;
        if (isset($seen[$src])) continue; // dedupe identical URLs
        $seen[$src] = true;
        $out[] = $src;
    }
    return $out;
}

function xxdq_media_id($field, $post_id = null) {
    $post_id = $post_id ?: get_the_ID();
    $val = function_exists('get_field') ? get_field($field, $post_id) : get_post_meta($post_id, $field, true);
    if (is_numeric($val)) return (int)$val;
    if (is_array($val) && !empty($val['ID'])) return (int)$val['ID'];
    return 0;
}

/* ── GeneratePress compatibility — drop the theme sidebar on product pages.
 * Our plugin provides its own sidebar; GP's widget area would otherwise
 * render a duplicate and visually overlap the nav dropdown. */
add_filter('generate_sidebar_layout', function ($layout) {
    if (is_singular('product') || is_post_type_archive('product') || is_tax('product_category')) {
        return 'no-sidebar';
    }
    return $layout;
}, 20);

add_filter('body_class', function ($classes) {
    if (is_singular('product') || is_post_type_archive('product') || is_tax('product_category')) {
        $classes[] = 'no-sidebar';
        /* `full-width-content` lets GP give the content area 100% width
         * so the dark hero can span the full viewport instead of being
         * capped at the theme's 1200px content width. */
        $classes[] = 'full-width-content';
        $classes[] = 'xxdq-product-page';
    }
    return $classes;
});

/* ── Force the theme's content container to be full-width on product pages
 * (GP default is 700px, we want the full 1200px to fit our sidebar + grid). */
add_filter('generate_content_width', function ($width) {
    if (is_singular('product') || is_post_type_archive('product') || is_tax('product_category')) {
        return 1400;
    }
    return $width;
});

/* Flush rewrite on activation */
register_activation_hook(__FILE__, function () {
    xxdq_products_register_rewrite();
    flush_rewrite_rules();
});
register_deactivation_hook(__FILE__, function () { flush_rewrite_rules(); });

function xxdq_products_register_rewrite() { /* placeholder for compat */ }
