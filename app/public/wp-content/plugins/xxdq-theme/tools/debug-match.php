<?php
/** Probe: does the wpseo_metadesc filter run on the English inquiry page? */
$wp_root = dirname(dirname(dirname(dirname(__DIR__))));
require_once $wp_root . '/wp-load.php';

// Find the English inquiry page.
$en_id = 5288;
$post = get_post($en_id);
echo "post: {$post->post_title} type={$post->post_type} status={$post->post_status}\n";
echo "en_path: " . get_post_meta($en_id, '_xxdq_en_path', true) . "\n";
echo "excerpt: [" . $post->post_excerpt . "]\n";

// Simulate the request that WordPress makes for /en/inquiry/.
$uri = '/en/inquiry/';
$_SERVER['REQUEST_URI'] = $uri;
$parts = explode('/', trim($uri, '/'));
echo "xxdq_en_path from URI would be: " . ($parts[1] ?? '') . "\n";

// Check that the SEO class is loaded and its filter registered.
echo "class exists: " . var_export(class_exists('XXDQ_I18n_Seo'), true) . "\n";
global $wp_filter;
if (isset($wp_filter['wpseo_metadesc'])) {
    foreach ($wp_filter['wpseo_metadesc']->callbacks as $prio => $cbs) {
        foreach ($cbs as $cb) {
            $fn = $cb['function'];
            if (is_array($fn)) {
                echo "  prio $prio: " . (is_object($fn[0]) ? get_class($fn[0]) : $fn[0]) . '::' . $fn[1] . "\n";
            } else {
                echo "  prio $prio: closure\n";
            }
        }
    }
} else {
    echo "  wpseo_metadesc NOT REGISTERED\n";
}
