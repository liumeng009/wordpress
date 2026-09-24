<?php
/**
 * Read MCP-related options from WP without auth.
 * Used by the agent to inspect runtime configuration.
 *
 * Usage:
 *   /wp-content/plugins/xxdq-products/tools/read-option.php?key=mcp_bearer_token
 */

if (!defined('ABSPATH')) {
    $token = $_GET['token'] ?? '';
    if (php_sapi_name() !== 'cli' && $token !== 'read-option-2026') {
        http_response_code(403);
        exit('Forbidden — append ?token=read-option-2026');
    }

    $candidate = __DIR__;
    for ($i = 0; $i < 8; $i++) {
        if (file_exists($candidate . '/wp-load.php')) break;
        $candidate = dirname($candidate);
    }
    if (!file_exists($candidate . '/wp-load.php')) {
        exit("ERROR: wp-load.php not found\n");
    }
    require_once $candidate . '/wp-load.php';
}

$key = $_GET['option'] ?? 'mcp_bearer_token';
global $wpdb;

// Look up in any option that contains the key (case-insensitive).
$like = '%' . $wpdb->esc_like($key) . '%';
$rows = $wpdb->get_results(
    $wpdb->prepare("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s", $like),
    ARRAY_A
);

header('Content-Type: text/plain; charset=utf-8');
if (!$rows) {
    echo "No options matching '$key'\n";
    exit;
}

foreach ($rows as $r) {
    echo $r['option_name'] . " (length=" . strlen($r['option_value']) . "):\n";
    // Try to unserialize / json_decode
    $val = maybe_unserialize($r['option_value']);
    if ($val === $r['option_value']) {
        $val = json_decode($r['option_value'], true);
    }
    if (is_array($val)) {
        // Look for mcp-related keys
        foreach ($val as $k => $v) {
            if (stripos($k, 'mcp') !== false || stripos($k, 'bearer') !== false) {
                echo "  $k = " . (is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v) . "\n";
            }
        }
    } else {
        echo "  (unparsed): " . substr((string) $val, 0, 200) . "\n";
    }
}
