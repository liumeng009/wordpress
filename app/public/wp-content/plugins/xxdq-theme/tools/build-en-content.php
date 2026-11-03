<?php
/**
 * XXDQ i18n — English content generator (CLI / WP-CLI).
 *
 * Creates the English counterparts of the Chinese content:
 *   - product categories  → English terms
 *   - products            → English posts (+ spec meta)
 *   - pages               → English posts
 *
 * Usage:
 *   wp eval-file wp-content/plugins/xxdq-theme/tools/build-en-content.php
 *
 * Or via LocalWP's PHP CLI:
 *   php wp-content/plugins/xxdq-theme/tools/build-en-content.php
 *
 * The script is idempotent — running it again skips objects that
 * already have an English counterpart.
 *
 * @package XXDQ
 */

if (!defined('ABSPATH') && !defined('WP_CLI')) {
    // Allow direct execution under LocalWP's PHP binary.
    // Script lives at <wp-root>/wp-content/plugins/xxdq-theme/tools/,
    // so the WP root is 4 levels up from this file's directory.
    $wp_root = dirname(dirname(dirname(dirname(__DIR__))));
    $wp_load = $wp_root . '/wp-load.php';
    if (!file_exists($wp_load)) {
        fwrite(STDERR, "Cannot locate wp-load.php at {$wp_load}\n");
        exit(1);
    }
    require_once $wp_load;
}

if (!defined('ABSPATH')) {
    exit(1);
}

/**
 * Report helper.
 *
 * @param string $msg Message.
 * @param string $lvl Level: info | warn | ok.
 */
function xxdq_gen_log($msg, $lvl = 'info') {
    $prefix = '[en-gen] ';
    if (defined('WP_CLI')) {
        WP_CLI::log($prefix . $msg);
        return;
    }
    echo $prefix . $msg . "\n";
}

/**
 * Translate a Chinese category description into English.
 *
 * Category descriptions are structured sentences like:
 *   "OT / SC / UT / RV / VE 全系列冷压端头,镀锡紫铜,规格 0.5–50 mm²。"
 *   "VE / SV / RV / DBV 全系列预绝缘端子,尼龙 / PVC 双层绝缘,叉形 / 圆形 ..."
 *
 * We translate the recurring Chinese phrases and keep the model codes
 * and numeric specs untouched. Anything we cannot map confidently
 * returns NULL and is recorded for review.
 *
 * @param string $zh Chinese description.
 * @return string|null English description, or NULL when unmapped.
 */
function xxdq_translate_cat_description($zh) {
    $zh = trim((string) $zh);
    if ($zh === '') {
        return null;
    }

    // Strip embedded HTML (category descriptions sometimes carry an image).
    $plain = trim(preg_replace('/<[^>]*>/u', '', $zh));
    if ($plain === '') {
        return null;
    }

    /* Phrase dictionary. Order matters — longer phrases first. */
    $phrases = [
        '全系列'            => 'Full range',
        '双层绝缘'          => 'double-layer insulation',
        '尼龙绝缘'          => 'nylon insulation',
        '尼龙 / PVC'        => 'nylon / PVC',
        '尼龙'              => 'nylon',
        'PVC'               => 'PVC',
        '镀锡紫铜'          => 'tinned copper',
        '紫铜镀锡'          => 'tinned copper',
        '冷压端头'          => 'crimp terminals',
        '冷压端子'          => 'crimp terminals',
        '预绝缘端子'        => 'pre-insulated terminals',
        '预绝缘端头'        => 'pre-insulated terminals',
        '裸端头'            => 'bare terminals',
        '中间接头'          => 'intermediate connectors',
        '预绝缘接头'        => 'pre-insulated connectors',
        '接线端子'          => 'connector terminals',
        '铜鼻子'            => 'copper lugs',
        '铜管端子'          => 'copper tube terminals',
        '铜端子'            => 'copper terminals',
        '连接器'            => 'connectors',
        '规格齐全'          => 'full range available',
        '规格'              => 'specification',
        '适用线径'          => 'wire range',
        '公制'              => 'metric',
        '现货'              => 'in stock',
        '广泛用于'          => 'widely used in',
        '主要适用于'        => 'mainly used for',
        '小型电气'          => 'small electrical',
        '首选'              => 'first choice',
        '电气'              => 'electrical',
        '系列'              => 'series',
        '型'                => 'type',
        '及'                => 'and',
        '与'                => 'and',
        '、'                => ', ',
        '，'                => ', ',
        '。'                => '.',
    ];

    // If the description is mostly model codes (no Chinese beyond a
    // threshold), it is not worth auto-translating.
    if (!preg_match('/[\x{4e00}-\x{9fff}]/u', $plain)) {
        return null;
    }

    $out = $plain;
    foreach ($phrases as $zh_p => $en_p) {
        $out = str_replace($zh_p, $en_p, $out);
    }

    // Tidy up: collapse spacing around punctuation, fix doubled commas.
    $out = preg_replace('/\s*,\s*,+/', ', ', $out);
    $out = preg_replace('/\s*\.\s*\./', '.', $out);
    $out = preg_replace('/\s+/u', ' ', $out);
    $out = trim($out);

    // If nothing actually changed, we could not translate it.
    if ($out === $plain) {
        return null;
    }

    return ucfirst($out);
}

/* ── 1. Categories ────────────────────────────────────────────── */

$cat_report = ['created' => 0, 'skipped' => 0, 'review' => 0];
$review_items = [];

$zh_terms = get_terms([
    'taxonomy'   => 'product_category',
    'hide_empty' => false,
    'orderby'    => 'term_id',
    'order'      => 'ASC',
]);

if (is_wp_error($zh_terms)) {
    xxdq_gen_log('Cannot load product_category terms: ' . $zh_terms->get_error_message(), 'warn');
    $zh_terms = [];
}

$terms_dict = require WP_PLUGIN_DIR . '/xxdq-theme/includes/lang/terms.php';

/**
 * Build the English name for a Chinese term.
 *
 * @param string $zh_name Chinese term name.
 * @return string|null
 */
function xxdq_en_term_name($zh_name, $terms_dict) {
    if (isset($terms_dict['cat.' . $zh_name])) {
        return $terms_dict['cat.' . $zh_name];
    }
    // Try the plural form used for the term TYPE (e.g. 冷压端子 → Crimp Terminals).
    if (isset($terms_dict['cat_type.' . $zh_name])) {
        return $terms_dict['cat_type.' . $zh_name];
    }
    return null;
}

// Two passes: parents first (so children can attach), then children.
$sorted = [];
foreach ($zh_terms as $t) {
    $sorted[(int) $t->parent][] = $t;
}

$process_level = function ($terms) use (&$process_level, $terms_dict, &$cat_report, &$review_items) {
    foreach ($terms as $t) {
        $zh_name = $t->name;

        $en_name = xxdq_en_term_name($zh_name, $terms_dict);
        if ($en_name === null) {
            $cat_report['review']++;
            $review_items[] = "CATEGORY NAME UNMAPPED: {$zh_name} (term_id {$t->term_id})";
            // Fall back to the Chinese name so the term still exists.
            $en_name = $zh_name;
        }

        $en_desc = xxdq_translate_cat_description($t->description);
        if ($en_desc === null && !empty($t->description)) {
            $review_items[] = "CATEGORY DESC UNMAPPED: {$zh_name} -> " . substr(wp_strip_all_tags($t->description), 0, 60);
        }

        // The public English slug mirrors the Chinese slug, which is
        // already English on this site (e.g. copper → copper).
        $en_slug = $t->slug;

        $existing = XXDQ_I18n_Content::get_en_term_id($t->term_id);
        if ($existing) {
            $cat_report['skipped']++;
            continue;
        }

        $en_id = XXDQ_I18n_Content::create_en_term($t->term_id, [
            'name'        => $en_name,
            'slug'        => $en_slug,
            'description' => $en_desc ?: '',
        ]);

        if ($en_id) {
            update_term_meta($en_id, '_xxdq_en_slug', $en_slug);
            $cat_report['created']++;
        } else {
            $cat_report['review']++;
            $review_items[] = "CATEGORY CREATE FAILED: {$zh_name}";
        }
    }
};

if (isset($sorted[0])) {
    $process_level($sorted[0]);
}
// Children (2 levels deep in this catalogue).
foreach ($sorted as $parent_id => $terms) {
    if ($parent_id === 0) {
        continue;
    }
    $process_level($terms);
}

xxdq_gen_log(sprintf(
    'Categories: %d created, %d skipped, %d need review',
    $cat_report['created'], $cat_report['skipped'], $cat_report['review']
), 'ok');

/* ── 2. Products ──────────────────────────────────────────────── */

$prod_report = ['created' => 0, 'skipped' => 0, 'review' => 0];

$zh_products = get_posts([
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'ID',
    'order'          => 'ASC',
]);

$spec_fields = ['material', 'finish', 'color_code', 'voltage', 'temperature', 'certification', 'package'];

foreach ($zh_products as $zp) {
    if (XXDQ_I18n_Content::get_en_post_id($zp->ID)) {
        $prod_report['skipped']++;
        continue;
    }

    $en_title = xxdq_translate_product_title($zp->post_title);
    if ($en_title === null) {
        $prod_report['review']++;
        $review_items[] = "PRODUCT TITLE UNMAPPED: {$zp->post_title} (id {$zp->ID})";
        continue;
    }

    // The public English slug mirrors the Chinese post_name (already English).
    /* Several Chinese products share the same base slug (WordPress keeps
     * post_name unique by appending -2, -3, … but the PUBLIC url uses
     * the shared base). To keep every English URL unique we append the
     * Chinese post ID to the English slug when the base is taken.
     *
     * The lookup must consider drafts too, because the English posts are
     * created as drafts and only published at the end of the run. */
    $en_slug_base = $zp->post_name;
    $en_slug      = $en_slug_base;
    $taken        = get_posts([
        'post_type'      => 'product',
        'post_status'    => ['publish', 'draft', 'pending', 'private'],
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => [[
            'key'   => '_xxdq_en_slug',
            'value' => $en_slug,
        ]],
        'no_found_rows'  => true,
    ]);
    if ($taken) {
        $en_slug = $en_slug_base . '-' . $zp->ID;
    }

    $en_id = XXDQ_I18n_Content::create_en_post($zp->ID, [
        'post_title'   => $en_title,
        'post_name'    => $en_slug . '-en',
        'post_content' => '',
        'post_excerpt' => '',
    ]);

    if (!$en_id) {
        $prod_report['review']++;
        $review_items[] = "PRODUCT CREATE FAILED: {$zp->post_title} (id {$zp->ID})";
        continue;
    }

    update_post_meta($en_id, '_xxdq_en_slug', $en_slug);
    update_post_meta($en_id, '_xxdq_en_path', 'product/' . $en_slug . '/');
    /* Copy the media references (images, PDF) unchanged — they are
     * language-independent. */
    foreach (['real_image', 'tech_image', 'attachment', 'image_2', 'image_3', 'image_4'] as $mf) {
        $v = get_post_meta($zp->ID, $mf, true);
        if ($v !== '' && $v !== false) {
            update_post_meta($en_id, $mf, $v);
        }
    }

    /* Numeric / language-neutral specs are copied verbatim. */
    foreach (['model', 'wire_range', 'hole_size'] as $mf) {
        $v = get_post_meta($zp->ID, $mf, true);
        if ($v !== '' && $v !== false) {
            update_post_meta($en_id, $mf, $v);
        }
    }

    /* Translated spec values. */
    foreach ($spec_fields as $mf) {
        $v = get_post_meta($zp->ID, $mf, true);
        if ($v === '' || $v === false) {
            continue;
        }
        $en_v = xxdq_translate_spec_value($mf, $v);
        if ($en_v === null) {
            $review_items[] = "SPEC UNMAPPED [{$mf}]: {$v} (product id {$zp->ID})";
            $en_v = $v; // keep the Chinese value, flagged for review
        }
        update_post_meta($en_id, $mf . '_en', $en_v);
    }

    /* The short description. */
    $sd = get_post_meta($zp->ID, 'short_desc', true);
    if ($sd !== '' && $sd !== false) {
        $en_sd = xxdq_translate_short_desc($sd);
        if ($en_sd === null) {
            $review_items[] = "SHORT_DESC UNMAPPED: {$sd} (product id {$zp->ID})";
            $en_sd = '';
        }
        update_post_meta($en_id, 'short_desc_en', $en_sd);
    }

    /* Link the English product to its English categories. */
    $zh_cats = wp_get_post_terms($zp->ID, 'product_category', ['fields' => 'ids']);
    $en_cats = [];
    if (!is_wp_error($zh_cats)) {
        foreach ($zh_cats as $zh_cid) {
            $en_cid = XXDQ_I18n_Content::get_en_term_id($zh_cid);
            if ($en_cid) {
                $en_cats[] = $en_cid;
            }
        }
    }
    if ($en_cats) {
        wp_set_object_terms($en_id, $en_cats, 'product_category');
    }

    $prod_report['created']++;
}

xxdq_gen_log(sprintf(
    'Products: %d created, %d skipped, %d need review',
    $prod_report['created'], $prod_report['skipped'], $prod_report['review']
), 'ok');

/* ── 3. Pages ─────────────────────────────────────────────────── */

$page_report = ['created' => 0, 'skipped' => 0, 'review' => 0];

// Only the pages we agreed on: home, about, contact, inquiry.
$page_ids = [7, 15, 16, 17];

foreach ($page_ids as $pid) {
    $zp = get_post($pid);
    if (!$zp) {
        continue;
    }
    if (XXDQ_I18n_Content::get_en_post_id($pid)) {
        $page_report['skipped']++;
        continue;
    }
    $page_report['review']++;
    $review_items[] = "PAGE needs manual English copy: {$zp->post_title} (id {$pid})";
}

xxdq_gen_log(sprintf(
    'Pages: %d created, %d skipped, %d need manual copy',
    $page_report['created'], $page_report['skipped'], $page_report['review']
), 'ok');

/* ── 3. Publish the English products ─────────────────────────── */

/* The English products and terms are created as drafts so that a
 * half-finished run never exposes half-translated pages. Once every
 * object is in place we publish them all. */
$publish_ids = get_posts([
    'post_type'      => ['product', 'page', 'news', 'post'],
    'post_status'    => ['draft', 'pending', 'private'],
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'meta_query'     => [[
        'key'   => '_xxdq_lang',
        'value' => 'en_US',
    ]],
    'no_found_rows'  => true,
]);

$published = 0;
foreach ($publish_ids as $pid) {
    wp_update_post(['ID' => $pid, 'post_status' => 'publish']);
    $published++;
}
xxdq_gen_log("Published {$published} English posts", 'ok');

/* Publish the English terms too. */
$en_term_ids = [];
$term_rows = get_terms([
    'taxonomy'   => 'product_category',
    'hide_empty' => false,
    'meta_query' => [[
        'key'   => 'xxdq_zh_term_id',
        'compare' => 'EXISTS',
    ]],
]);
if (!is_wp_error($term_rows)) {
    foreach ($term_rows as $t) {
        $en_term_ids[] = $t->term_id;
    }
}
xxdq_gen_log('English terms: ' . count($en_term_ids) . ' present', 'ok');

/* ── 4. Review list ───────────────────────────────────────────── */

if ($review_items) {
    $out = "=== ITEMS NEEDING HUMAN REVIEW ===\n";
    $out .= implode("\n", $review_items) . "\n";
    file_put_contents(WP_CONTENT_DIR . '/en-content-review.txt', $out);
    xxdq_gen_log(count($review_items) . ' items need review -> wp-content/en-content-review.txt', 'warn');
}

xxdq_gen_log('Done.', 'ok');
