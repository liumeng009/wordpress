<?php
/**
 * XXDQ — slug collision repair.
 *
 * THE PROBLEM
 * -----------
 * The PIM import inserted several products under the SAME WordPress
 * `post_name`. WordPress normally appends -2, -3, … to keep post_name
 * unique, but the importer wrote `post_name` directly, so e.g. six
 * products all have post_name = "dt-25".
 *
 * Consequences:
 *   - only the FIRST product of a group is reachable; the others are
 *     unreachable dead weight,
 *   - the sitemap lists the same URL several times,
 *   - the English side had to work around it by appending the post ID
 *     to the public slug ("/en/product/dt-25-1499/"), which is ugly and
 *     hurts click-through.
 *
 * TWO KINDS OF COLLISION
 * ----------------------
 *   1. TRUE DUPLICATES — identical title, identical specs, identical
 *      body, only the imported image attachment differs. They are
 *      redundant and get merged into the canonical one.
 *   2. DIFFERENT PRODUCTS — e.g. "DT25 铜管端子" (M8 stud) and
 *      "DT-25 紫铜堵油标准型（大头）" (8.5mm hole) are two distinct
 *      catalogue items whose slugs collide. Both must stay, but each
 *      needs its own URL.
 *
 * WHAT THIS SCRIPT DOES
 * ---------------------
 *   - for true duplicates:  merge them into the canonical product,
 *     delete the redundant post (and its English twin), and record a
 *     redirect from the old URL,
 *   - for different products:  give the LATER one a clean, meaningful
 *     slug (no numeric suffix) and record a redirect,
 *   - keep the English side in sync (public slug, path, post_name).
 *
 * The redirect table is written to the `xxdq_slug_redirects` option,
 * which `XXDQ_I18n_Redirects` reads on `template_redirect`.
 *
 * Usage:
 *   wp eval-file wp-content/plugins/xxdq-theme/tools/repair-slug-collisions.php
 *   php wp-content/plugins/xxdq-theme/tools/repair-slug-collisions.php
 *
 * @package XXDQ
 */

if (!defined('ABSPATH') && !defined('WP_CLI')) {
    $wp_root = dirname(dirname(dirname(dirname(__DIR__))));
    require_once $wp_root . '/wp-load.php';
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
function xxdq_scr_log($msg, $lvl = 'info') {
    $prefix = '[slug-repair] ';
    if (defined('WP_CLI')) {
        WP_CLI::log($prefix . $msg);
        return;
    }
    echo $prefix . $msg . "\n";
}

/**
 * The meaningful slug we want for a product, based on its title.
 *
 * The PIM catalogue uses two naming conventions that collapse onto the
 * same slug:
 *
 *   "DT-25 紫铜堵油标准型（大头）"  → dt-25        (oil-block terminal)
 *   "DT25 铜管端子"                → dt-25        (copper tube terminal)
 *
 * We disambiguate with the English type, which is what the English
 * title already says:
 *
 *   dt-25-oil-block   (堵油 = oil-block)
 *   dt-25-tube        (铜管端子 = copper tube terminal)
 *
 * @param string $zh_title Chinese product title.
 * @param string $en_title English product title (may be empty).
 * @return string Slug suffix, e.g. "oil-block" / "tube".
 */
function xxdq_scr_type_suffix($zh_title, $en_title) {
    /* Prefer the English title — it already carries the canonical type
     * wording used across the English catalogue. */
    $haystack = strtolower($en_title . ' ' . $zh_title);

    /* Order matters: check the more specific phrase first. */
    $rules = [
        'oil-block'   => ['oil-block', '堵油'],
        'transition'  => ['transition', '过渡', '摩擦焊'],
        'inspection'  => ['inspection', '窥口'],
        'tube'        => ['copper tube terminal', '铜管端子'],
        'bare'        => ['bare terminal', '裸端头', '裸端子'],
        'pre-insulated' => ['pre-insulated', '预绝缘'],
        'fork'        => ['fork', '叉形'],
        'pin'         => ['pin', '插针', '针形'],
        'flared'      => ['flared', '凸缘'],
        'round'       => ['round', '圆形'],
        'y-shaped'    => ['y-shaped', 'y型'],
        'o-shaped'    => ['o-shaped', 'o型'],
        'u-shaped'    => ['u-shaped', 'u型'],
        'crimp'       => ['crimp', '冷压'],
    ];

    foreach ($rules as $suffix => $needles) {
        foreach ($needles as $needle) {
            if (strpos($haystack, $needle) !== false) {
                return $suffix;
            }
        }
    }

    return '';
}

/**
 * Build the desired public slug for a product.
 *
 * @param int    $post_id  Chinese product ID.
 * @param string $fallback Used when the base slug is empty.
 * @return string
 */
function xxdq_scr_desired_slug($post_id, $fallback = '') {
    $base = sanitize_title(get_post_field('post_name', $post_id));
    if ($base === '') {
        $base = sanitize_title(get_post_field('post_title', $post_id));
    }
    if ($base === '') {
        $base = $fallback;
    }
    return $base;
}

/**
 * The fingerprint used to detect true duplicates.
 *
 * Title + body length + every spec value. The image attachment is
 * deliberately EXCLUDED: the importer uploaded the same picture under
 * different attachment IDs, which is exactly what makes these look
 * different while they are the same product.
 *
 * @param int $post_id Product ID.
 * @return string
 */
function xxdq_scr_fingerprint($post_id) {
    $post = get_post($post_id);
    if (!$post) {
        return '';
    }
    $meta = array();
    foreach (['model', 'wire_range', 'hole_size', 'material', 'voltage', 'package', 'short_desc'] as $f) {
        $meta[$f] = (string) get_post_meta($post_id, $f, true);
    }
    return md5($post->post_title . '|' . strlen($post->post_content) . '|' . wp_json_encode($meta));
}

/**
 * Collect the duplicate post_name groups.
 *
 * @return array Map of post_name => list of post IDs.
 */
function xxdq_scr_collision_groups() {
    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT post_name FROM {$wpdb->posts}
          WHERE post_type = 'product' AND post_status = 'publish'
          GROUP BY post_name HAVING COUNT(*) > 1"
    );
    $groups = array();
    foreach ($rows as $r) {
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
              WHERE post_type = 'product' AND post_status = 'publish' AND post_name = %s
              ORDER BY ID",
            $r->post_name
        ));
        $groups[$r->post_name] = array_map('intval', $ids);
    }
    return $groups;
}

/**
 * Split a collision group into clusters of identical products.
 *
 * The fingerprint deliberately EXCLUDES the image: the catalogue
 * reuses the same picture across many models (one "铜鼻子" image is
 * attached to 20+ products), so the image says nothing about whether
 * two rows are the same product.
 *
 * @param array $ids Product IDs in the group.
 * @return array List of clusters, each a list of product IDs.
 */
function xxdq_scr_cluster($ids) {
    $clusters = array();
    foreach ($ids as $id) {
        $matched = false;
        foreach ($clusters as $k => $cluster) {
            if (xxdq_scr_is_same_product($cluster[0], $id)) {
                $clusters[$k][] = $id;
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            $clusters[] = array($id);
        }
    }
    return $clusters;
}

/**
 * Which member of a cluster should own the public URL?
 *
 * The oldest product wins: it is the one that has been live longest
 * and therefore carries the accumulated link equity. The others are
 * merged into it.
 *
 * @param array $cluster Cluster of product IDs.
 * @return int Canonical product ID.
 */
function xxdq_scr_canonical_of($cluster) {
    $ids = $cluster;
    usort($ids, function ($a, $b) {
        $da = (int) get_post_field('post_date', $a);
        $db = (int) get_post_field('post_date', $b);
        if ($da !== $db) {
            return $da < $db ? -1 : 1;
        }
        return $a < $b ? -1 : 1;
    });
    return (int) $ids[0];
}

/**
 * Pick the best image for the canonical product.
 *
 * The importer assigned images by row order, so the canonical product
 * may have ended up with a generic picture while one of its duplicates
 * has the model-specific one. We therefore pick the image whose file
 * name best matches the product's model.
 *
 * @param array $cluster Cluster of product IDs.
 * @param int   $canonical Canonical product ID.
 * @return int Attachment ID, or 0.
 */
function xxdq_scr_pick_image($cluster, $canonical) {
    $model = strtolower((string) get_post_meta($canonical, 'model', true));
    /* Normalise: DT-25 → dt25, DT(E)-25 → dte25. */
    $model_key = preg_replace('/[^a-z0-9]/', '', $model);
    if ($model_key === '') {
        $model_key = strtolower((string) get_post_field('post_name', $canonical));
    }

    $best = 0;
    $best_score = -1;

    foreach ($cluster as $pid) {
        $att = (int) get_post_meta($pid, 'real_image', true);
        if (!$att || !get_post($att)) {
            continue;
        }
        $file = strtolower((string) get_post_meta($att, '_wp_attached_file', true));
        $file_key = preg_replace('/[^a-z0-9]/', '', $file);

        /* Score: an exact model prefix wins, then any mention of the
         * model, then the canonical product's own image. */
        $score = 0;
        if ($model_key !== '' && strpos($file_key, $model_key) === 0) {
            $score = 100;
        } elseif ($model_key !== '' && strpos($file_key, $model_key) !== false) {
            $score = 50;
        }
        if ($pid === $canonical) {
            $score += 5;
        }
        /* Prefer a smaller / older attachment id on a tie, so the
         * result is stable. */
        if ($score > $best_score) {
            $best_score = $score;
            $best = $att;
        }
    }

    return $best;
}

/**
 * Are these two products the same catalogue item?
 *
 * @param int $a Product A.
 * @param int $b Product B.
 * @return bool
 */
function xxdq_scr_is_same_product($a, $b) {
    $fa = xxdq_scr_fingerprint($a);
    $fb = xxdq_scr_fingerprint($b);
    return $fa !== '' && $fa === $fb;
}

/**
 * Keep the shortest, most descriptive slug.
 *
 * Two Chinese products can slugify to the same base ("DT-25 紫铜堵油…"
 * → "dt-25", "DT25 铜管端子" → "dt-25"). We give the SECOND one a
 * type suffix so both stay reachable.
 *
 * @param string $base       Base slug.
 * @param string $suffix     Type suffix (may be empty).
 * @param array  $taken      Slugs already in use.
 * @param int    $post_id    Product ID (used as a last-resort fallback).
 * @return string
 */
function xxdq_scr_unique_slug($base, $suffix, $taken, $post_id) {
    $candidates = array();
    if ($suffix !== '') {
        $candidates[] = $base . '-' . $suffix;
    }
    $candidates[] = $base;

    foreach ($candidates as $c) {
        if (!isset($taken[$c])) {
            return $c;
        }
    }
    /* Everything is taken — fall back to the post ID. */
    return $base . '-' . $post_id;
}

/* ── Main ─────────────────────────────────────────────────────── */

$dry_run = in_array('--dry-run', $argv ?? array(), true);
if ($dry_run) {
    xxdq_scr_log('DRY RUN — nothing will be written.', 'warn');
}

$groups = xxdq_scr_collision_groups();
if (empty($groups)) {
    xxdq_scr_log('No slug collisions found. Nothing to do.', 'ok');
    exit(0);
}

xxdq_scr_log(sprintf('Found %d colliding post_name group(s).', count($groups)), 'warn');

/* Load the existing redirect table. */
$redirects = get_option('xxdq_slug_redirects', array());
if (!is_array($redirects)) {
    $redirects = array();
}

/* All slugs currently in use, so the new ones stay unique. */
global $wpdb;
$used = array();
foreach ($wpdb->get_col("SELECT post_name FROM {$wpdb->posts} WHERE post_type = 'product'") as $sn) {
    $used[$sn] = true;
}

$stats = array('merged' => 0, 'renamed' => 0, 'redirects' => 0);

foreach ($groups as $post_name => $ids) {
    xxdq_scr_log(sprintf('--- %s (%d products) ---', $post_name, count($ids)));

    /* Step 1: split the group into clusters of identical products. */
    $clusters = xxdq_scr_cluster($ids);

    /* Step 2: the FIRST cluster keeps the existing URL. It was the one
     * that was already reachable, so it holds the accumulated link
     * equity. */
    $first       = $clusters[0];
    $canonical   = xxdq_scr_canonical_of($first);
    $duplicates  = array_values(array_diff($first, array($canonical)));

    xxdq_scr_log(sprintf(
        '  canonical: #%d "%s" (%d duplicate(s) merged)',
        $canonical,
        get_the_title($canonical),
        count($duplicates)
    ));

    /* Step 3: merge the true duplicates into the canonical product. */
    foreach ($duplicates as $dup) {
        xxdq_scr_log(sprintf(
            '    duplicate: #%d "%s"',
            $dup,
            get_the_title($dup)
        ));

        /* Move the taxonomy terms across so the merged product keeps
         * its category placement. */
        $terms = wp_get_post_terms($dup, 'product_category', array('fields' => 'ids'));
        if (!is_wp_error($terms) && !empty($terms)) {
            wp_set_object_terms($canonical, $terms, 'product_category', true);
        }

        /* The English twin of the duplicate is redundant too. */
        $dup_en = (int) get_post_meta($dup, '_xxdq_en_id', true);
        if ($dup_en && get_post($dup_en)) {
            /* The duplicate's English URL may already be live, so it
             * needs a redirect to the canonical English product. */
            $dup_en_path = (string) get_post_meta($dup_en, '_xxdq_en_path', true);
            $can_en      = (int) get_post_meta($canonical, '_xxdq_en_id', true);
            if ($dup_en_path !== '' && $can_en) {
                $can_en_path = (string) get_post_meta($can_en, '_xxdq_en_path', true);
                if ($can_en_path !== '') {
                    $old_en = home_url('/en/' . ltrim($dup_en_path, '/'));
                    $new_en = home_url('/en/' . ltrim($can_en_path, '/'));
                    if ($old_en !== $new_en) {
                        $redirects[$old_en] = $new_en;
                        $stats['redirects']++;
                    }
                }
            }
            if (!$dry_run) {
                wp_delete_post($dup_en, true);
            }
        }

        if (!$dry_run) {
            /* Remove the stale link on the duplicate so nothing points
             * at the deleted post. */
            delete_post_meta($dup, '_xxdq_en_id');
            wp_delete_post($dup, true);
            $stats['merged']++;
        }
    }

    /* Step 4: pick the best image for the canonical product. The
     * importer assigned images by row order, so the surviving product
     * may have ended up with a generic picture. */
    $best_img = xxdq_scr_pick_image($first, $canonical);
    if ($best_img && !$dry_run) {
        $current_img = (int) get_post_meta($canonical, 'real_image', true);
        if ($current_img !== $best_img) {
            update_post_meta($canonical, 'real_image', $best_img);
            set_post_thumbnail($canonical, $best_img);
            xxdq_scr_log(sprintf(
                '    image: attachment %d → %d',
                $current_img,
                $best_img
            ));
        }
    }

    /* Step 5: the REMAINING clusters are DIFFERENT products that share
     * the slug. Each of them needs its own URL. */
    foreach (array_slice($clusters, 1) as $cluster) {
        /* Within the cluster only the canonical survives; the rest are
         * duplicates of it. */
        $pid        = xxdq_scr_canonical_of($cluster);
        $dup_ids    = array_values(array_diff($cluster, array($pid)));

        $en_id = (int) get_post_meta($pid, '_xxdq_en_id', true);
        if ($en_id && !get_post($en_id)) {
            /* The link is stale (the English twin was already removed). */
            $en_id = 0;
            if (!$dry_run) {
                delete_post_meta($pid, '_xxdq_en_id');
            }
        }
        $en_title = $en_id ? get_the_title($en_id) : '';

        $base     = xxdq_scr_desired_slug($pid, $post_name);
        $suffix   = xxdq_scr_type_suffix(get_the_title($pid), $en_title);
        $new_slug = xxdq_scr_unique_slug($base, $suffix, $used, $pid);

        xxdq_scr_log(sprintf(
            '  distinct product: #%d "%s" — slug %s → %s%s',
            $pid,
            get_the_title($pid),
            $post_name,
            $new_slug,
            $dup_ids ? sprintf(' (%d duplicate(s) merged)', count($dup_ids)) : ''
        ));

        /* Merge this cluster's own duplicates into it first. */
        foreach ($dup_ids as $dup) {
            $terms = wp_get_post_terms($dup, 'product_category', array('fields' => 'ids'));
            if (!is_wp_error($terms) && !empty($terms)) {
                wp_set_object_terms($pid, $terms, 'product_category', true);
            }
            $dup_en = (int) get_post_meta($dup, '_xxdq_en_id', true);
            if ($dup_en && get_post($dup_en) && !$dry_run) {
                wp_delete_post($dup_en, true);
            }
            if (!$dry_run) {
                delete_post_meta($dup, '_xxdq_en_id');
                wp_delete_post($dup, true);
                $stats['merged']++;
            }
        }

        if ($new_slug === $post_name) {
            continue;
        }

        $old_zh = home_url('/product/' . $post_name . '/');
        $used[$new_slug] = true;

        if (!$dry_run) {
            /* 1. Rename the Chinese product. */
            wp_update_post(array(
                'ID'        => $pid,
                'post_name' => $new_slug,
            ));

            /* 2. Keep the English side in sync. The English public slug
             *    drops the numeric suffix the builder had to append to
             *    break the collision, and the internal post_name
             *    follows. */
            if ($en_id) {
                $en_post = get_post($en_id);
                if ($en_post) {
                    $old_en_path = (string) get_post_meta($en_id, '_xxdq_en_path', true);

                    update_post_meta($en_id, '_xxdq_en_slug', $new_slug);
                    update_post_meta($en_id, '_xxdq_en_path', 'product/' . $new_slug . '/');
                    wp_update_post(array(
                        'ID'        => $en_id,
                        'post_name' => $new_slug . '-en',
                    ));

                    /* Redirect the English URL that was actually live
                     * (the suffixed one) to the new clean URL. */
                    if ($old_en_path !== '') {
                        $old_en = home_url('/en/' . ltrim($old_en_path, '/'));
                        $new_en = home_url('/en/product/' . $new_slug . '/');
                        if ($old_en !== $new_en) {
                            $redirects[$old_en] = $new_en;
                            $stats['redirects']++;
                        }
                    }
                }
            }

            /* 3. Redirect the old Chinese URL. */
            $new_zh = get_permalink($pid);
            if ($old_zh !== $new_zh) {
                $redirects[$old_zh] = $new_zh;
                $stats['redirects']++;
            }
            $stats['renamed']++;
        }
    }
}

/* ── Persist ──────────────────────────────────────────────────── */

if (!$dry_run) {
    update_option('xxdq_slug_redirects', $redirects);
    /* The rewrite rules and the post_name changes both need a flush. */
    flush_rewrite_rules(false);
}

xxdq_scr_log(sprintf(
    'Done%s. merged=%d renamed=%d redirects=%d',
    $dry_run ? ' (dry run)' : '',
    $stats['merged'],
    $stats['renamed'],
    $stats['redirects']
), 'ok');

if (!$dry_run && $stats['redirects'] > 0) {
    xxdq_scr_log('Redirect table written to the `xxdq_slug_redirects` option.', 'ok');
}
