<?php
/**
 * XXDQ i18n — re-translate the English category descriptions.
 *
 * The first generation pass used a naive per-phrase dictionary that
 * left Chinese fragments in the output. This script re-runs the
 * translation with the improved dictionary and writes the result to
 * the English terms.
 *
 * Idempotent: re-running it is a no-op (the output is deterministic).
 *
 * @package XXDQ
 */

if (!defined('ABSPATH') && !defined('WP_CLI')) {
    /* tools/ → xxdq-theme/ → plugins/ → wp-content/ → public/ */
    $wp_load = dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';
    if (!file_exists($wp_load)) {
        fwrite(STDERR, "wp-load.php not found\n");
        exit(1);
    }
    require_once $wp_load;
}

$terms = get_terms([
    'taxonomy'   => 'product_category',
    'hide_empty' => false,
]);

if (is_wp_error($terms)) {
    fwrite(STDERR, 'Cannot load terms: ' . $terms->get_error_message() . "\n");
    exit(1);
}

$updated = 0;
$failed  = 0;
$issues  = [];

foreach ($terms as $en_term) {
    $zh_id = (int) get_term_meta($en_term->term_id, 'xxdq_zh_term_id', true);
    if ($zh_id <= 0) {
        continue; /* A Chinese term — leave it alone. */
    }

    $zh_term = get_term($zh_id, 'product_category');
    if (!$zh_term || is_wp_error($zh_term)) {
        continue;
    }

    $en_desc = xxdq_translate_cat_description($zh_term->description);

    if ($en_desc === '') {
        $failed++;
        $issues[] = "UNTRANSLATED: {$en_term->name} (zh {$zh_id}) -> "
            . substr(wp_strip_all_tags($zh_term->description), 0, 80);
        continue;
    }

    if ($en_desc !== $en_term->description) {
        wp_update_term($en_term->term_id, 'product_category', ['description' => $en_desc]);
        $updated++;
    }
}

echo "category descriptions updated: $updated, untranslated: $failed\n";
foreach ($issues as $i) {
    echo "  $i\n";
}
