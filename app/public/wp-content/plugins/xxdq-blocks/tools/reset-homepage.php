<?php
/**
 * reset-homepage.php — Phase 9 homepage rebuild.
 *
 * Usage:
 *   wp eval-file wp-content/plugins/xxdq-blocks/tools/reset-homepage.php
 *
 * What it does:
 *   1. Finds the front page (page_on_front or fallback ID=7).
 *   2. Replaces post_content with the new Phase 9 block sequence.
 *   3. Prints a success / error report.
 *
 * No parameters — edit the HOME_ID constant below if your homepage
 * has a different post ID.
 */

if (!defined('ABSPATH')) {
    // Running outside WP_CLI — load WP directly.
    $path = dirname(__DIR__, 3) . '/../../../wp-load.php';
    if (file_exists($path)) {
        require_once $path;
    } else {
        fwrite(STDERR, "ERROR: Cannot find wp-load.php. Run via `wp eval-file`.\n");
        exit(1);
    }
}

define('XXDQ_HOME_ID', 7);

/**
 * The new homepage block sequence (Gutenberg comment syntax).
 * Order matches zsbef.com 8-section structure:
 *   1. hero-slider   — Hero with ACF-driven background images
 *   2. category-grid — 4 product categories
 *   3. why-us        — Why choose us (now reads from ACF options)
 *   4. app-fields    — Application fields grid
 *   5. news-section  — Latest news from news CPT
 *   6. cta           — Call to action banner
 */
$block_sequence = <<<'BLOCKS'
<!-- wp:xxdq/hero-slider /-->

<!-- wp:xxdq/category-grid /-->

<!-- wp:xxdq/why-us /-->

<!-- wp:xxdq/application-fields /-->

<!-- wp:xxdq/news-section /-->

<!-- wp:xxdq/cta /-->
BLOCKS;

/* ── Locate the homepage post ───────────────────────────── */
$home_id = apply_filters('xxdq_homepage_id', XXDQ_HOME_ID);
$post    = get_post($home_id);

if (!$post) {
    // Fallback: scan for page with page_on_front set.
    $front = get_option('page_on_front', 0);
    if ($front) {
        $post   = get_post($front);
        $home_id = $front;
    }
}

if (!$post || $post->post_type !== 'page') {
    fwrite(STDERR, "ERROR: Cannot find homepage (ID $home_id). Please check your Settings → Reading.\n");
    exit(1);
}

/* ── Backup current content (log to stderr / WP debug) ─── */
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log("[xxdq-blocks] Homepage backup: ID $home_id, old content length: " . strlen($post->post_content));
}

/* ── Update the homepage content ────────────────────────── */
$result = wp_update_post([
    'ID'           => $home_id,
    'post_content' => trim($block_sequence),
    'post_modified'    => current_time('mysql'),
    'post_modified_gmt'=> current_time('mysql', true),
], true);

if (is_wp_error($result)) {
    /** @var WP_Error $result */
    fwrite(STDERR, "ERROR updating page $home_id: " . $result->get_error_message() . "\n");
    exit(1);
}

/* ── Report ─────────────────────────────────────────────── */
$url = get_permalink($home_id);
echo "✓ Phase 9 homepage reset complete.\n";
echo "  Page ID : $home_id\n";
echo "  Blocks  : hero-slider | category-grid | why-us | application-fields | news-section | cta\n";
echo "  URL     : $url\n";
echo "\nNext steps:\n";
echo "  1. Visit Settings → Permalinks → Save  (flush rewrite rules for News CPT)\n";
echo "  2. Visit the homepage: $url\n";
echo "  3. Go to 网站设置 (Website Settings) in the admin to configure banners, app fields, company info.\n";
