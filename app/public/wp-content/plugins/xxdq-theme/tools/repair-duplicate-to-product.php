<?php
/**
 * ONE-TIME REPAIR: remove the duplicated "圆形裸端头（TO型）" product.
 *
 * WHY
 * ---
 * The catalogue import wrote TWO products with byte-identical content:
 *
 *     1069  to-round-bare-terminal     圆形裸端头（TO型）
 *     1073  to-round-bare-terminal-2   圆形裸端头（TO型）
 *
 * Same title, same body, same specs, same image, same category. The
 * only difference is the slug. Two consequences:
 *
 *   - duplicate content: the English side produced two products with
 *     the same title ("Round Bare Terminal (TO Series)"),
 *   - two sitemap entries that a search engine reads as two URLs for
 *     the same thing.
 *
 * The fix keeps 1069 (the older, lower ID, which keeps the nicer
 * slug) and removes 1073. The old URL is redirected with a 301 so no
 * inbound link breaks, using the site's existing
 * `xxdq_slug_redirects` table.
 *
 * Run: php repair-duplicate-to-product.php [--dry-run]
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

$keep_id   = 1069;   // to-round-bare-terminal
$remove_id = 1073;   // to-round-bare-terminal-2
$en_remove = 4690;   // to-round-bare-terminal-2-en

$keep   = get_post($keep_id);
$remove = get_post($remove_id);

if (!$keep || !$remove) {
    echo 'ABORT: the expected products are not both present.' . PHP_EOL;
    exit(1);
}

echo 'KEEP   ' . $keep_id . ' ' . $keep->post_name . ' — ' . $keep->post_title . PHP_EOL;
echo 'REMOVE ' . $remove_id . ' ' . $remove->post_name . ' — ' . $remove->post_title . PHP_EOL;

$zh_from = '/product/' . $remove->post_name;
$zh_to   = '/product/' . $keep->post_name;

/* The English duplicate is removed, so its URL has to point at the
 * English counterpart of the KEPT Chinese product. */
$en_keep = (int) get_post_meta($keep_id, '_xxdq_en_id', true);
if (!$en_keep) {
    $en_keep = (int) $wpdb->get_var(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_xxdq_zh_id' AND meta_value = " . (int) $keep_id
    );
}

$en_keep_post = $en_keep ? get_post($en_keep) : null;

/* The English permalink strips the "-en" suffix, so the public path
 * of the kept English product is "/en/product/<slug-without-en>". */
$en_keep_slug = $en_keep_post ? preg_replace('/-en$/', '', $en_keep_post->post_name) : $keep->post_name;

$en_from = '/en/product/' . preg_replace('/-en$/', '', $remove->post_name);
$en_to   = '/en/product/' . $en_keep_slug;

echo PHP_EOL . 'Redirects to add:' . PHP_EOL;
echo '  ' . $zh_from . '  →  ' . $zh_to . PHP_EOL;
echo '  ' . $en_from . '  →  ' . $en_to . PHP_EOL;

if ($dry_run) {
    echo PHP_EOL . 'DRY RUN — nothing written.' . PHP_EOL;
    exit(0);
}

/* ── 1. The redirect entries ───────────────────────────────── */
$redirects = get_option('xxdq_slug_redirects', array());
if (!is_array($redirects)) {
    $redirects = array();
}

$redirects[$zh_from] = $zh_to;
$redirects[$en_from] = $en_to;

update_option('xxdq_slug_redirects', $redirects);

echo PHP_EOL . 'Redirect entries written.' . PHP_EOL;

/* ── 2. Repoint the term relationships ─────────────────────── */
$terms = $wpdb->get_col("SELECT term_taxonomy_id FROM {$wpdb->term_relationships} WHERE object_id = " . (int) $remove_id);
foreach ($terms as $ttid) {
    /* Only add the relationship if the kept product does not already
     * have it — otherwise the term would list the product twice. */
    $already = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->term_relationships}
         WHERE object_id = " . (int) $keep_id . " AND term_taxonomy_id = " . (int) $ttid
    );
    if (!$already) {
        $wpdb->insert(
            $wpdb->term_relationships,
            ['object_id' => (int) $keep_id, 'term_taxonomy_id' => (int) $ttid],
            ['%d', '%d']
        );
        echo '  term ' . $ttid . ' linked to the kept product.' . PHP_EOL;
    }
}
$wpdb->delete($wpdb->term_relationships, ['object_id' => (int) $remove_id], ['%d']);

/* ── 3. Repoint the English product back to the kept one ──── */
$wpdb->update(
    $wpdb->postmeta,
    ['meta_value' => (string) $keep_id],
    ['post_id' => (int) $en_remove, 'meta_key' => '_xxdq_zh_id'],
    ['%s'],
    ['%d']
);

/* ── 4. Remove the English duplicate ───────────────────────── */
if (get_post($en_remove)) {
    /* Keep the English product's own URL alive by pointing it at the
     * kept English product instead of deleting it outright — that way
     * its image and any inbound links survive. */
    $wpdb->update(
        $wpdb->posts,
        [
            'post_name'   => $keep->post_name . '-en',
            'post_status' => 'publish',
        ],
        ['ID' => (int) $en_remove],
        ['%s', '%s'],
        ['%d']
    );

    /* But that slug now collides with the kept English product. The
     * kept English product (4689) is the one that stays; the duplicate
     * is removed. */
    echo PHP_EOL . 'Removing the English duplicate ' . $en_remove . PHP_EOL;
    $wpdb->delete($wpdb->posts, ['ID' => (int) $en_remove], ['%d']);
    $wpdb->delete($wpdb->postmeta, ['post_id' => (int) $en_remove], ['%d']);
    $wpdb->delete($wpdb->term_relationships, ['object_id' => (int) $en_remove], ['%d']);
    $wpdb->delete($wpdb->yoast_indexable, ['object_id' => (int) $en_remove], ['%d']);
}

/* ── 5. Remove the Chinese duplicate ───────────────────────── */
echo 'Removing the Chinese duplicate ' . $remove_id . PHP_EOL;
$wpdb->delete($wpdb->posts, ['ID' => (int) $remove_id], ['%d']);
$wpdb->delete($wpdb->postmeta, ['post_id' => (int) $remove_id], ['%d']);
$wpdb->delete($wpdb->yoast_indexable, ['object_id' => (int) $remove_id], ['%d']);

/* ── 6. Verify ─────────────────────────────────────────────── */
echo PHP_EOL . '=== verification ===' . PHP_EOL;

$dupes = $wpdb->get_results(
    "SELECT post_title, COUNT(*) c, GROUP_CONCAT(ID) ids FROM {$wpdb->posts}
     WHERE post_type = 'product' AND post_status = 'publish'
     GROUP BY post_title HAVING c > 1",
    ARRAY_A
);
echo 'Duplicate Chinese titles: ' . count($dupes) . PHP_EOL;
foreach ($dupes as $d) {
    echo '  ' . $d['c'] . 'x [' . $d['post_title'] . '] ids=' . $d['ids'] . PHP_EOL;
}

$en_dupes = $wpdb->get_results(
    "SELECT p.post_title, COUNT(*) c, GROUP_CONCAT(p.ID) ids FROM {$wpdb->posts} p
     JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_xxdq_lang' AND m.meta_value = 'en_US'
     WHERE p.post_type = 'product' AND p.post_status = 'publish'
     GROUP BY p.post_title HAVING c > 1",
    ARRAY_A
);
echo 'Duplicate English titles: ' . count($en_dupes) . PHP_EOL;
foreach ($en_dupes as $d) {
    echo '  ' . $d['c'] . 'x [' . $d['post_title'] . '] ids=' . $d['ids'] . PHP_EOL;
}

$zh_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish' AND post_name NOT LIKE '%-en'");
$en_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish' AND post_name LIKE '%-en'");
echo 'Products now: ZH=' . $zh_count . '  EN=' . $en_count . PHP_EOL;

echo PHP_EOL . 'Done.' . PHP_EOL;
