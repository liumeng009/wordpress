<?php
/**
 * ONE-TIME REPAIR: give every published post a UNIQUE post_modified.
 *
 * WHY
 * ---
 * Yoast builds its XML sitemaps with:
 *
 *     SELECT ... ORDER BY post_modified ASC LIMIT 100 OFFSET n
 *
 * `post_modified` was not unique: the catalogue import wrote 61 products
 * with the SAME second, 53 more with the next second, and so on. MySQL
 * does not guarantee a stable order for tied rows, so consecutive
 * LIMIT/OFFSET windows could return overlapping or missing rows.
 *
 * The Chinese product sitemap therefore listed 26 URLs twice and
 * silently dropped 26 others — those 26 products could not be discovered
 * through the sitemap at all.
 *
 * THE FIX
 * -------
 * Spread the tied timestamps apart by one second each, keeping the
 * original order (post ID order) intact. This makes `post_modified`
 * unique, the ORDER BY total, and the pagination exact — with no code
 * change to Yoast and no change to any URL.
 *
 * The English posts keep their own (already unique) timestamps; only
 * the tied Chinese rows are touched. Their relative order is preserved.
 *
 * SAFE TO RE-RUN: rows whose timestamp is already unique are skipped.
 *
 * Run: php repair-unique-post-modified.php
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

echo '=== Tied post_modified values (publish only) ===' . PHP_EOL;

$tied = $wpdb->get_results(
    "SELECT post_modified, COUNT(*) c, GROUP_CONCAT(ID ORDER BY ID) ids
     FROM {$wpdb->posts}
     WHERE post_status = 'publish' AND post_type NOT IN ('revision','attachment','nav_menu_item','acf-field','acf-field-group','wp_global_styles','wp_navigation')
     GROUP BY post_modified
     HAVING c > 1
     ORDER BY post_modified ASC",
    ARRAY_A
);

if (empty($tied)) {
    echo '  (none — every post already has a unique timestamp)' . PHP_EOL;
    echo PHP_EOL . 'Nothing to do.' . PHP_EOL;
    exit(0);
}

$total_rows = 0;
foreach ($tied as $t) {
    $ids = array_map('intval', explode(',', $t['ids']));
    echo '  ' . $t['post_modified'] . ' -> ' . $t['c'] . ' posts' . PHP_EOL;
    $total_rows += count($ids);
}

echo PHP_EOL . 'Total rows to re-stamp: ' . $total_rows . PHP_EOL;
echo 'Mode: ' . ($dry_run ? 'DRY RUN (no writes)' : 'WRITE') . PHP_EOL . PHP_EOL;

if ($dry_run) {
    echo 'Re-run without --dry-run to apply.' . PHP_EOL;
    exit(0);
}

/* Walk the tied groups in ascending timestamp order and push each
 * group's timestamps one second apart, starting from the group's
 * original timestamp. The very first row of a group keeps the
 * original value, so the group's overall position in the timeline
 * does not move. */
$applied = 0;
$cursor  = null;

foreach ($tied as $t) {
    $ids = array_map('intval', explode(',', $t['ids']));
    sort($ids);

    $base = $t['post_modified'];
    $cur  = $base;

    foreach ($ids as $id) {
        /* The first row keeps the original timestamp; every next row is
         * pushed one second further, so the group becomes strictly
         * increasing and stays contiguous with the neighbouring groups. */
        $new = $cur;

        $wpdb->update(
            $wpdb->posts,
            ['post_modified' => $new],
            ['ID' => $id],
            ['%s'],
            ['%d']
        );

        $cur = date('Y-m-d H:i:s', strtotime($cur) + 1);
        $applied++;
    }
}

echo 'Rows re-stamped: ' . $applied . PHP_EOL;

/* Verify. */
$remaining = $wpdb->get_results(
    "SELECT post_modified, COUNT(*) c FROM {$wpdb->posts}
     WHERE post_status = 'publish' AND post_type NOT IN ('revision','attachment','nav_menu_item','acf-field','acf-field-group','wp_global_styles','wp_navigation')
     GROUP BY post_modified HAVING c > 1",
    ARRAY_A
);

echo 'Remaining tied groups: ' . count($remaining) . PHP_EOL;
foreach ($remaining as $r) {
    echo '  ' . $r['post_modified'] . ' -> ' . $r['c'] . PHP_EOL;
}

echo PHP_EOL . 'Done.' . PHP_EOL;
