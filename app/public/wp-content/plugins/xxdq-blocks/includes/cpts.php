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
