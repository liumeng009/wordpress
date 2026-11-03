<?php
$wp_root = dirname(dirname(dirname(dirname(__DIR__))));
require_once $wp_root . '/wp-load.php';
global $wp_rewrite;
$rules = $wp_rewrite->get_rules();
foreach ($rules as $p => $t) { if (strpos($p, 'en') !== false) { echo "RULE: $p => $t\n"; } }
