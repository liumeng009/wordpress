<?php
define('ABSPATH', dirname(__DIR__, 3) . '/');
require_once ABSPATH . 'wp-load.php';

header('Content-Type: text/plain; charset=utf-8');

echo "permalink_structure = '" . get_option('permalink_structure') . "'\n";
echo "rewrite_rules count = " . count((array) get_option('rewrite_rules')) . "\n\n";

global $wpdb;
$rows = $wpdb->get_results(
    "SELECT ID, post_status, post_name, post_title, post_type FROM $wpdb->posts WHERE post_type='page' AND post_status IN ('publish','future','draft') ORDER BY ID",
    ARRAY_A
);
foreach ($rows as $r) {
    $permalink = get_permalink($r['ID']);
    $path = $permalink ? str_replace(get_home_url(), '', $permalink) : '(none)';
    echo sprintf("ID=%-3d status=%-8s slug=%-30s type=%-6s permalink=%s\n",
        $r['ID'], $r['post_status'], $r['post_name'], $r['post_type'], $path
    );
}

echo "\n--- rewrite rules for cable-lug or online-tools ---\n";
$rules = (array) get_option('rewrite_rules');
foreach ($rules as $k => $v) {
    if (stripos($k, 'cable') !== false || stripos($k, 'online') !== false) {
        echo "  $k => $v\n";
    }
}
