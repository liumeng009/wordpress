<?php
/**
 * XXDQ i18n — backfill the English `model` meta for English products.
 *
 * The `model` field of a product holds the Chinese type description
 * ("片型裸端头", "堵油型铜接线端子（DT型）", …). The English product
 * title already carries the English type, so we derive the English
 * model from the type dictionary.
 *
 * Idempotent: re-running it is a no-op.
 *
 * @package XXDQ
 */

if (!defined('ABSPATH') && !defined('WP_CLI')) {
    /* tools/ → xxdq-theme/ → plugins/ → wp-content/ → public/ */
    $wp_load = dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';
    if (!file_exists($wp_load)) {
        fwrite(STDERR, "wp-load.php not found (tried $wp_load)\n");
        exit(1);
    }
    require_once $wp_load;
}

global $wpdb;

$map = xxdq_product_type_map();

/* Normalise the dictionary once so the lookup is a hash hit. */
$norm_map = [];
foreach ($map as $zh => $en) {
    $norm_map[xxdq_normalize_type_text($zh)] = $en;
}

$ids = $wpdb->get_col(
    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_xxdq_lang' AND meta_value='en_US'"
);

$updated = 0;
$skipped = 0;
$manual  = [];

foreach ($ids as $id) {
    $model = get_post_meta($id, 'model', true);
    if ($model === '' || $model === false) {
        continue;
    }
    if (!preg_match('/[\x{4e00}-\x{9fff}]/u', $model)) {
        continue; /* Already language-neutral. */
    }

    $norm = xxdq_normalize_type_text($model);

    if (isset($norm_map[$norm])) {
        update_post_meta($id, 'model_en', $norm_map[$norm]);
        $updated++;
        continue;
    }

    /* Fall back to the English product title: "<MODEL> <English type>".
     * If the title has no model prefix, the title IS the type. */
    $title = get_the_title($id);
    if ($title) {
        update_post_meta($id, 'model_en', $title);
        $updated++;
        continue;
    }

    $manual[] = "$id [$model]";
    $skipped++;
}

echo "model_en updated: $updated, unresolved: $skipped\n";
foreach ($manual as $m) {
    echo "  MANUAL: $m\n";
}
