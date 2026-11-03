<?php
/**
 * Remove all generated English content objects (idempotent reset).
 *
 * Only touches objects created by the i18n generator — identified by
 * the `_xxdq_lang` = 'en_US' post meta and the `xxdq_zh_term_id`
 * term meta. Chinese content is never affected.
 *
 * @package XXDQ
 */

$wp_root = dirname(dirname(dirname(dirname(__DIR__))));
require_once $wp_root . '/wp-load.php';

global $wpdb;

/* ── 1. English posts ─────────────────────────────────────────── */

$en_posts = $wpdb->get_col(
    "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('product','page','news','post') AND ID IN (
        SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_xxdq_lang' AND meta_value='en_US'
     )"
);

$deleted = 0;
foreach ($en_posts as $pid) {
    $pid = (int) $pid;
    // Guard: only delete posts that are actually flagged as English.
    if (get_post_meta($pid, '_xxdq_lang', true) !== 'en_US') {
        continue;
    }
    wp_delete_post($pid, true);
    $deleted++;
}
echo "Deleted {$deleted} English posts\n";

/* ── 2. English terms ─────────────────────────────────────────── */

$en_terms = $wpdb->get_col(
    "SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key='xxdq_zh_term_id'"
);

$deleted_terms = 0;
foreach ($en_terms as $tid) {
    $tid = (int) $tid;
    $term = get_term($tid);
    if (!$term || is_wp_error($term)) {
        continue;
    }
    // Only remove terms that are linked back to a Chinese term.
    $zh = get_term_meta($tid, 'xxdq_zh_term_id', true);
    if (!$zh) {
        continue;
    }
    wp_delete_term($tid, 'product_category');
    // Clean up the dangling link on the Chinese side.
    delete_term_meta((int) $zh, 'xxdq_en_term_id');
    $deleted_terms++;
}
echo "Deleted {$deleted_terms} English terms\n";

/* ── 3. Options ───────────────────────────────────────────────── */

delete_option('xxdq_en_home_id');
delete_option('xxdq_en_term_map');

/* ── 4. Review file ───────────────────────────────────────────── */

$review = WP_CONTENT_DIR . '/en-content-review.txt';
if (file_exists($review)) {
    unlink($review);
}

echo "Reset complete.\n";
