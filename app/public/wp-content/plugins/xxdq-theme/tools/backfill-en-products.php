<?php
/**
 * XXDQ i18n — backfill the English product data.
 *
 * Fills the gaps the initial English build left behind:
 *
 *   1. `model_en`      — the English TYPE of the product ("Tinned Copper
 *                        Oil-Block Terminal (Large Head)"), derived from
 *                        the Chinese `model` field via the type
 *                        dictionary. Only 24 of 297 had it.
 *   2. `<field>_en`    — the translated spec values, for the products
 *                        whose build run predates a dictionary entry.
 *   3. `short_desc_en` — the English short description, for the 25
 *                        products whose Chinese description could not
 *                        be mapped and was therefore left empty.
 *   4. `post_content`  — the English product body, generated from the
 *                        template in XXDQ_I18n_Product_Body.
 *
 * Usage:
 *   wp eval-file wp-content/plugins/xxdq-theme/tools/backfill-en-products.php
 *   php wp-content/plugins/xxdq-theme/tools/backfill-en-products.php
 *
 * The script is idempotent — re-running it only rewrites what is still
 * missing.
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

if (!defined('ABSPATH')) {
    exit(1);
}

/**
 * Log helper.
 *
 * @param string $msg Message.
 * @param string $lvl info | warn | ok.
 */
function xxdq_be_log($msg, $lvl = 'info') {
    $prefix = '[en-backfill] ';
    if (defined('WP_CLI')) {
        WP_CLI::log($prefix . $msg);
        return;
    }
    echo $prefix . $msg . "\n";
}

/* ── 1. model_en ─────────────────────────────────────────────── */

$map = xxdq_product_type_map();

/* Normalise the dictionary once so the lookup is a hash hit. */
$norm_map = [];
foreach ($map as $zh => $en) {
    $norm_map[xxdq_normalize_type_text($zh)] = $en;
}

/* The English product title is "<MODEL> <English type>". When the
 * dictionary has no entry for the Chinese type we can still recover the
 * English type by taking the title minus its leading model token. */
function xxdq_be_type_from_title($title) {
    $title = trim((string) $title);
    if ($title === '') {
        return '';
    }
    /* Strip a leading model token: letters, digits, dash, parentheses. */
    if (preg_match('/^([A-Za-z]{1,6}[-–]?[A-Za-z0-9.]*)\s+(.*)$/u', $title, $m)) {
        return trim($m[2]);
    }
    return $title;
}

$en_products = get_posts([
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'meta_query'     => [[
        'key'   => '_xxdq_lang',
        'value' => 'en_US',
    ]],
]);

$stats = [
    'model_en'       => 0,
    'spec_en'        => 0,
    'short_desc_en'  => 0,
    'body'           => 0,
    'unresolved'     => [],
];

foreach ($en_products as $en_id) {
    /* ── model_en ─────────────────────────────────────────── */
    $model_en = trim((string) get_post_meta($en_id, 'model_en', true));
    if ($model_en === '') {
        $zh_model = trim((string) get_post_meta($en_id, 'model', true));

        $resolved = '';
        if ($zh_model !== '') {
            $norm = xxdq_normalize_type_text($zh_model);
            if (isset($norm_map[$norm])) {
                $resolved = $norm_map[$norm];
            }
        }

        /* Fall back to the English title minus the model token. */
        if ($resolved === '') {
            $resolved = xxdq_be_type_from_title(get_the_title($en_id));
        }

        if ($resolved !== '') {
            update_post_meta($en_id, 'model_en', $resolved);
            $stats['model_en']++;
        } elseif ($zh_model !== '') {
            $stats['unresolved'][] = "model_en [{$en_id}] {$zh_model}";
        }
    }

    /* ── translated spec values ──────────────────────────── */
    foreach (array('material', 'finish', 'color_code', 'voltage', 'temperature', 'certification', 'package') as $field) {
        $en_val = trim((string) get_post_meta($en_id, $field . '_en', true));
        if ($en_val !== '') {
            continue;
        }
        $zh_val = trim((string) get_post_meta($en_id, $field, true));
        if ($zh_val === '') {
            continue;
        }
        $translated = xxdq_translate_spec_value($field, $zh_val);
        if ($translated === null) {
            $stats['unresolved'][] = "{$field}_en [{$en_id}] {$zh_val}";
            continue;
        }
        update_post_meta($en_id, $field . '_en', $translated);
        $stats['spec_en']++;
    }

    /* ── short_desc_en ────────────────────────────────────── */
    $sd = trim((string) get_post_meta($en_id, 'short_desc_en', true));
    if ($sd === '') {
        $zh_sd = trim((string) get_post_meta($en_id, 'short_desc', true));
        if ($zh_sd !== '') {
            $translated = xxdq_translate_short_desc($zh_sd);
            if ($translated !== null) {
                update_post_meta($en_id, 'short_desc_en', $translated);
                $stats['short_desc_en']++;
            } else {
                $stats['unresolved'][] = "short_desc_en [{$en_id}] " . mb_substr($zh_sd, 0, 60);
            }
        }
    }

    /* ── body ─────────────────────────────────────────────── */
    if (class_exists('XXDQ_I18n_Product_Body') && XXDQ_I18n_Product_Body::apply($en_id)) {
        $stats['body']++;
    }
}

xxdq_be_log(sprintf(
    'model_en=%d  spec_en=%d  short_desc_en=%d  body=%d  (total %d English products)',
    $stats['model_en'],
    $stats['spec_en'],
    $stats['short_desc_en'],
    $stats['body'],
    count($en_products)
), 'ok');

if (!empty($stats['unresolved'])) {
    xxdq_be_log(sprintf('%d item(s) still need a human look:', count($stats['unresolved'])), 'warn');
    foreach (array_slice($stats['unresolved'], 0, 40) as $u) {
        xxdq_be_log('  ' . $u);
    }
}
