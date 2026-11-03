<?php
/**
 * One-off: finalize page ID=13
 *  - post_name: cable-lug-calculator  (rename to match spec §八 URL 建议)
 *  - post_status: publish  (was stuck at 'future' with already-elapsed post_date)
 *  - flush rewrite rules so /cable-lug-calculator/ resolves
 *
 * DRY RUN by default. Add ?confirm=yes to actually write.
 */

define('ABSPATH', dirname(__DIR__, 3) . '/');
require_once ABSPATH . 'wp-load.php';

if ( $_GET['key'] !== 'xxdq' ) {
    wp_die( 'Unauthorized. Use ?key=xxdq[&confirm=yes]', 403 );
}

header( 'Content-Type: text/plain; charset=utf-8' );

$page_id  = 13;
$new_slug = 'cable-lug-calculator';

$page = get_post( $page_id );
if ( ! $page ) {
    wp_die( "Page $page_id not found.", 404 );
}

echo "BEFORE:\n";
echo "  ID          = {$page->ID}\n";
echo "  title       = {$page->post_title}\n";
echo "  slug        = {$page->post_name}\n";
echo "  status      = {$page->post_status}\n";
echo "  post_date   = {$page->post_date}\n";
echo "  permalink   = " . get_permalink( $page_id ) . "\n\n";

if ( ! ( $_GET['confirm'] ?? '' ) ) {
    echo "DRY RUN. Add ?confirm=yes to write.\n";
    exit;
}

// 1. Update slug + status
$upd = wp_update_post( array(
    'ID'          => $page_id,
    'post_name'   => $new_slug,
    'post_status' => 'publish',
) );
if ( is_wp_error( $upd ) ) {
    wp_die( 'Update failed: ' . $upd->get_error_message(), 500 );
}

// 2. Flush rewrite rules (otherwise /cable-lug-calculator/ won't resolve)
flush_rewrite_rules( false );

// 3. Verify
$page    = get_post( $page_id );
$rewrite = get_option( 'rewrite_rules' );
$has_rule = false;
foreach ( (array) $rewrite as $k => $v ) {
    if ( stripos( $k, 'cable-lug-calculator' ) !== false ) { $has_rule = $k; break; }
}

echo "AFTER:\n";
echo "  ID          = {$page->ID}\n";
echo "  title       = {$page->post_title}\n";
echo "  slug        = {$page->post_name}\n";
echo "  status      = {$page->post_status}\n";
echo "  permalink   = " . get_permalink( $page_id ) . "\n";
echo "  rewrite rule for cable-lug-calculator = " . ( $has_rule ?: 'NOT FOUND' ) . "\n";
echo "\nRewrite rules flushed.\n";
