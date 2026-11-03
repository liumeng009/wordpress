<?php
/**
 * ONE-TIME REPAIR: rebuild the English product titles that lost their
 * size/model token.
 *
 * WHY
 * ---
 * The English title is derived from the Chinese one by
 * `xxdq_translate_product_title()`. That function had a bug: it
 * returned the bare type as soon as the type phrase appeared at the
 * START of the title, so any trailing size token was dropped:
 *
 *     冷压端子o型 01 / 02 / 03      → "O-Shaped Crimp Terminal"  (×3)
 *     螺旋式压线帽 1号 / 2号 / 3号   → "Spiral Wire Connector"   (×3)
 *     绝缘软套管 1.25mm² / 2mm²     → "Insulating Sleeve"        (×4)
 *
 * Twenty groups of products therefore shared ONE English title, which
 * is duplicate content and made the sitemap look like it listed the
 * same URL several times.
 *
 * The translator is fixed; this script re-derives the titles.
 *
 * It only rewrites a title when the NEW title differs from the old one
 * AND the product is one of the affected ones (i.e. the old title is
 * still a bare type with no distinguishing token).
 *
 * Run: php repair-en-product-titles.php [--dry-run]
 */

$_SERVER['HTTP_HOST']   = 'localhost:10004';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/index.php';

define('WP_USE_THEMES', false);
$wp_root = dirname(dirname(dirname(dirname(__DIR__))));
require $wp_root . '/wp-load.php';

global $wpdb;

$dry_run = in_array('--dry-run', $argv ?? [], true);

/* The English products, paired with their Chinese source. */
$rows = $wpdb->get_results(
    "SELECT en.ID en_id, en.post_title en_title, en.post_name en_slug,
            zh.ID zh_id, zh.post_title zh_title
     FROM {$wpdb->posts} en
     JOIN {$wpdb->postmeta} em ON em.post_id = en.ID AND em.meta_key = '_xxdq_lang' AND em.meta_value = 'en_US'
     JOIN {$wpdb->postmeta} pm ON pm.post_id = en.ID AND pm.meta_key = '_xxdq_zh_id'
     JOIN {$wpdb->posts} zh ON zh.ID = pm.meta_value
     WHERE en.post_type = 'product' AND en.post_status = 'publish'",
    ARRAY_A
);

echo 'English products: ' . count($rows) . PHP_EOL;

$changed = 0;
$skipped = 0;

foreach ($rows as $r) {
    $new = xxdq_translate_product_title($r['zh_title']);

    if ($new === null) {
        $skipped++;
        continue;
    }

    if (trim((string) $new) === trim((string) $r['en_title'])) {
        continue;
    }

    /* SAFETY: only rewrite when the old title carries NO distinguishing
     * token at all. If the editor has already given this product a
     * proper title, leave it alone. */
    $old = trim((string) $r['en_title']);
    $has_token = (bool) preg_match('/\b\d+\b|\b\d+\.\d+\b/', $old);

    if ($has_token) {
        continue;
    }

    $changed++;

    echo '  ' . $r['en_id'] . ' [' . $r['en_slug'] . ']' . PHP_EOL;
    echo '      old: ' . $old . PHP_EOL;
    echo '      new: ' . $new . PHP_EOL;

    if (!$dry_run) {
        /* A direct UPDATE, not wp_update_post(): wp_update_post() would
         * fire the auto-sync hook again and re-translate the title
         * from the (unchanged) Chinese source. */
        $wpdb->update(
            $wpdb->posts,
            ['post_title' => $new],
            ['ID' => (int) $r['en_id']],
            ['%s'],
            ['%d']
        );
    }
}

echo PHP_EOL . 'Titles changed: ' . $changed . '  (skipped, untranslatable: ' . $skipped . ')' . PHP_EOL;

if ($dry_run) {
    echo 'DRY RUN — nothing written. Re-run without --dry-run to apply.' . PHP_EOL;
    exit(0);
}

/* Verify: no two English products should share a title now. */
$dupes = $wpdb->get_results(
    "SELECT post_title, COUNT(*) c, GROUP_CONCAT(ID) ids
     FROM {$wpdb->posts} p
     JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_xxdq_lang' AND m.meta_value = 'en_US'
     WHERE p.post_type = 'product' AND p.post_status = 'publish'
     GROUP BY post_title HAVING c > 1",
    ARRAY_A
);

echo PHP_EOL . 'Remaining duplicate English titles: ' . count($dupes) . PHP_EOL;
foreach ($dupes as $d) {
    echo '  ' . $d['c'] . 'x [' . $d['post_title'] . '] ids=' . $d['ids'] . PHP_EOL;
}

echo PHP_EOL . 'Done.' . PHP_EOL;
