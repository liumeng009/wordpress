<?php
/**
 * One-off: force ID=13 status future -> publish.
 *
 * wp_update_post() silently rejects the transition `future -> publish`
 * because the post_date is still nominally scheduled. We bypass that
 * gate with a direct $wpdb->update and also rewrite post_date to
 * `now()` so the row passes WP's "published" sanity check, then
 * flush_rewrite_rules().
 *
 * DRY RUN by default. Add ?confirm=yes to actually write.
 */

define('ABSPATH', dirname(__DIR__, 3) . '/');
require_once ABSPATH . 'wp-load.php';

if ( $_GET['key'] !== 'xxdq' ) {
    wp_die( 'Unauthorized. Use ?key=xxdq[&confirm=yes]', 403 );
}

header( 'Content-Type: text/plain; charset=utf-8' );

global $wpdb;

$page_id  = 13;
$new_slug = 'cable-lug-calculator';

// --- BEFORE snapshot ---
$row = $wpdb->get_row( $wpdb->prepare(
    "SELECT ID, post_title, post_name, post_status, post_date, post_date_gmt FROM {$wpdb->posts} WHERE ID = %d",
    $page_id
) );
if ( ! $row ) {
    wp_die( "Page $page_id not found.", 404 );
}

echo "BEFORE:\n";
echo "  ID          = {$row->ID}\n";
echo "  title       = {$row->post_title}\n";
echo "  slug        = {$row->post_name}\n";
echo "  status      = {$row->post_status}\n";
echo "  post_date   = {$row->post_date}\n";
echo "  permalink   = " . get_permalink( $page_id ) . "\n\n";

if ( ! ( $_GET['confirm'] ?? '' ) ) {
    echo "DRY RUN. Add ?confirm=yes to write.\n";
    exit;
}

// --- WRITE ---
$now_mysql  = current_time( 'mysql' );      // site-local
$now_mysql_g = current_time( 'mysql', true ); // GMT

$updated = $wpdb->update(
    $wpdb->posts,
    array(
        'post_status'   => 'publish',
        'post_name'     => $new_slug,
        'post_date'     => $now_mysql,
        'post_date_gmt' => $now_mysql_g,
        'post_modified' => $now_mysql,
        'post_modified_gmt' => $now_mysql_g,
    ),
    array( 'ID' => $page_id ),
    array( '%s', '%s', '%s', '%s', '%s', '%s' ),
    array( '%d' )
);

if ( $updated === false ) {
    wp_die( 'DB update failed: ' . $wpdb->last_error, 500 );
}

// Make sure WP's post cache agrees with DB.
clean_post_cache( $page_id );

// Flush rewrite so /cable-lug-calculator/ resolves.
flush_rewrite_rules( false );

// --- AFTER snapshot ---
$row2 = $wpdb->get_row( $wpdb->prepare(
    "SELECT ID, post_title, post_name, post_status, post_date FROM {$wpdb->posts} WHERE ID = %d",
    $page_id
) );

$has_rule = null;
foreach ( (array) get_option( 'rewrite_rules' ) as $k => $v ) {
    if ( stripos( $k, 'cable-lug-calculator' ) !== false ) { $has_rule = $k; break; }
}

echo "AFTER (rows affected = $updated):\n";
echo "  ID          = {$row2->ID}\n";
echo "  title       = {$row2->post_title}\n";
echo "  slug        = {$row2->post_name}\n";
echo "  status      = {$row2->post_status}\n";
echo "  post_date   = {$row2->post_date}\n";
echo "  permalink   = " . get_permalink( $page_id ) . "\n";
echo "  rewrite rule for cable-lug-calculator = " . ( $has_rule ?: 'NOT FOUND' ) . "\n";
echo "\nDone.\n";