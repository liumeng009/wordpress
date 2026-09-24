<?php
/**
 * flush-rewrites.php — One-shot rewrite rule flush.
 *
 * Usage:
 *   Web : https://your-site/wp-content/plugins/xxdq-products/tools/flush-rewrites.php?key=xxdq-flush-2026
 *   CLI : php flush-rewrites.php
 *
 * Why this exists:
 *   WP only flushes rewrites when plugins are activated or when the user
 *   visits Settings → Permalinks. Adding new taxonomies to an existing
 *   CPT requires a manual flush, which we trigger here.
 */

if (!defined('ABSPATH')) {
    if (php_sapi_name() !== 'cli') {
        $provided = isset($_GET['key']) ? (string) $_GET['key'] : '';
        if (!hash_equals('xxdq-flush-2026', $provided)) {
            http_response_code(403);
            exit('Forbidden — append ?key=xxdq-flush-2026');
        }
        header('Content-Type: text/plain; charset=utf-8');
    }

    $candidate = __DIR__;
    for ($i = 0; $i < 8; $i++) {
        if (file_exists($candidate . '/wp-load.php')) break;
        $candidate = dirname($candidate);
    }
    if (!file_exists($candidate . '/wp-load.php')) {
        $msg = "ERROR: wp-load.php not found.\n";
        if (php_sapi_name() === 'cli') fwrite(STDERR, $msg); else echo $msg;
        exit(1);
    }

    // Load WordPress. We capture fatal errors so we can still report them.
    register_shutdown_function(function () {
        $e = error_get_last();
        @file_put_contents(
            __DIR__ . '/_flush-error.log',
            date('c') . " | " . print_r($e, true) . "\n"
        );
        if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            echo "\n\nFATAL: {$e['message']} in {$e['file']}:{$e['line']}\n";
        }
    });

    require_once $candidate . '/wp-load.php';
}

/* flush_rewrite_rules(true) — true = write to .htaccess / web.config */
flush_rewrite_rules(true);

echo "✓ flush_rewrite_rules() executed.\n";
echo "Test URLs:\n";
echo "  Archive : /product/\n";
echo "  Category: /product-category/<slug>/\n";
echo "  Series  : /product-series/<slug>/\n";
echo "  Single  : /product/<slug>/\n";
echo "\nIf the URLs still 404, re-visit /wp-admin/options-permalink.php (Save).\n";
