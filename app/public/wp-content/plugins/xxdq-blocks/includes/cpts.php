<?php
/**
 * Custom Post Types registration (Phase 9).
 *
 * Currently registers:
 *   - news: 公司新闻动态 (shown on the homepage news block + archive page).
 */

if (!defined('ABSPATH')) exit;

/**
 * Register the `news` CPT.
 *
 * - Public, archiveable, REST-visible so it works with the editor and REST API.
 * - Slug: `news` so URLs become `/news/{post-slug}/` and the archive lives at `/news/`.
 * - flush_rewrite_rules() is intentionally NOT called here (heavy on every page load).
 *   The tools/reset-homepage.php script + the user visiting Settings→Permalinks will
 *   flush rules after activation.
 */
add_action('init', function () {
    register_post_type('news', [
        'labels' => [
            'name'               => '新闻',
            'singular_name'      => '新闻',
            'menu_name'          => '新闻',
            'add_new'            => '发布新闻',
            'add_new_item'       => '发布新新闻',
            'edit_item'          => '编辑新闻',
            'new_item'           => '新新闻',
            'view_item'          => '查看新闻',
            'search_items'       => '搜索新闻',
            'not_found'          => '暂无新闻',
            'not_found_in_trash' => '回收站中暂无新闻',
            'all_items'          => '所有新闻',
        ],
        'public'        => true,
        'has_archive'   => true,
        'show_in_rest'  => true,
        'menu_icon'     => 'dashicons-megaphone',
        'menu_position' => 5,
        'supports'      => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'custom-fields', 'author'],
        'rewrite'       => [
            'slug'       => 'news',
            'with_front' => false,
            'pages'      => true,
            'feeds'      => true,
        ],
        'capability_type' => 'post',
        'hierarchical'     => false,
        'taxonomies'       => [],
    ]);
}, 11);

/**
 * Helper: render an inline list of recent news posts.
 * Used by `XXDQ_Blocks_Render::render_news_section()`.
 */
if (!function_exists('xxdq_get_recent_news')) {
    function xxdq_get_recent_news($limit = 3) {
        $q = new WP_Query([
            'post_type'      => 'news',
            'post_status'    => 'publish',
            'posts_per_page' => max(1, (int) $limit),
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ]);
        return $q;
    }
}

/* ── News front-end templates ─────────────────────────────── *
 * Mirrors the wiring in xxdq-products/xxdq-products.php: load our
 * plugin's archive-news.php for post-type-archive requests, and tell
 * GeneratePress to skip its right widget area on news pages so the
 * default Search / Recent Posts / Recent Comments widgets don't appear.
 */

/* 1) Archive loader — fires only for post type archives. */
add_filter('archive_template', function ($t) {
    if (is_post_type_archive('news')) {
        $custom = plugin_dir_path(__FILE__) . '../templates/archive-news.php';
        if (file_exists($custom)) return $custom;
        return locate_template(['archive-news.php']) ?: $t;
    }
    return $t;
});

/* 1b) Single template loader — fires only for singular post-type pages. */
add_filter('single_template', function ($t) {
    if (is_singular('news')) {
        $custom = plugin_dir_path(__FILE__) . '../templates/single-news.php';
        if (file_exists($custom)) return $custom;
        return locate_template(['single-news.php']) ?: $t;
    }
    return $t;
});

/* 2) Kill GeneratePress's right sidebar (the "3 widget blocks" the
 *    user complained about) on news pages. */
add_filter('generate_sidebar_layout', function ($layout) {
    if (is_singular('news') || is_post_type_archive('news')) {
        return 'no-sidebar';
    }
    return $layout;
}, 20);

/* 3) Body classes — `.no-sidebar` for GP, `.full-width-content` so GP
 *    gives the content area 100% width (and the dark hero can span the
 *    whole viewport instead of being squeezed into 1200px), and
 *    `.xxdq-news-page` for our own CSS hooks. */
add_filter('body_class', function ($classes) {
    if (is_singular('news') || is_post_type_archive('news')) {
        $classes[] = 'no-sidebar';
        $classes[] = 'full-width-content';
        $classes[] = 'xxdq-news-page';
    }
    return $classes;
});

/* 4) Force full content width on news pages (GP default is 700px,
 *    which would crush our banner / list layout). */
add_filter('generate_content_width', function ($width) {
    if (is_singular('news') || is_post_type_archive('news')) {
        return 1400;
    }
    return $width;
});
