<?php
/**
 * Flush the rewrite rules so the /en/ routing takes effect.
 *
 * @package XXDQ
 */

$wp_root = dirname(dirname(dirname(dirname(__DIR__))));
require_once $wp_root . '/wp-load.php';

// Ensure the rewrite rules are registered before flushing.
do_action('init');

global $wp_rewrite;
$wp_rewrite->init();
$wp_rewrite->flush_rules(true);

echo "Rewrite rules flushed.\n";

// Show the /en/ rule specifically.
foreach ((array) $wp_rewrite->get_rules() as $pattern => $target) {
    if (strpos($pattern, 'en') !== false) {
        echo "  {$pattern}  =>  {$target}\n";
    }
}
