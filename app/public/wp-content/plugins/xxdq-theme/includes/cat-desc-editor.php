<?php
/**
 * XXDQ Category Description Bulk Editor
 *
 * Adds an admin sub-menu under "产品" that lets the editor mass-edit
 * the `description` field of every product_category term in one form.
 *
 * Why a custom page instead of WP's built-in quick edit:
 *  - We have 11 top-level + 79 sub = 90 terms; the built-in "Quick Edit"
 *    requires 90 page-reloads, each only editing ONE term at a time.
 *  - The built-in Description textarea is hidden inside the editor
 *    screen for product_category (customized by GeneratePress / Yoast)
 *    and is easy to miss.
 *
 * This page renders ALL terms on a single screen, each with its own
 * textarea (small for subs, large for top-levels), plus per-term
 * checkboxes so accidental edits can be skipped.
 *
 * Saves via standard $wpdb->update on wp_term_taxonomy, so it goes
 * through the same code path as a normal term save (and Yoast's
 * indexable cache is busted by deleting rows for those term_ids).
 *
 * Auth: manage_options (admin only).
 */

if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=product',
        '分类描述批量编辑',
        '分类描述',
        'manage_options',
        'xxdq-cat-desc',
        'xxdq_render_cat_desc_page'
    );
});

function xxdq_render_cat_desc_page() {
    if (!current_user_can('manage_options')) {
        wp_die('权限不足');
    }

    global $wpdb;

    // ── SAVE handler ─────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST'
        && check_admin_referer('xxdq_cat_desc_save')
        && !empty($_POST['desc'])) {
        $descs = (array) $_POST['desc'];
        $saved = 0;
        foreach ($descs as $term_id => $val) {
            $term_id = (int) $term_id;
            if ($term_id <= 0) continue;
            $clean = wp_kses_post((string) $val);
            $clean = trim($clean);
            $r = $wpdb->update(
                $wpdb->term_taxonomy,
                ['description' => $clean],
                ['term_id' => $term_id, 'taxonomy' => 'product_category'],
                ['%s'],
                ['%d', '%s']
            );
            if ($r !== false) $saved++;
        }
        // Bust Yoast indexable cache so the new descriptions render on next request.
        if ($saved > 0) {
            $ids = implode(',', array_map('intval', array_keys($descs)));
            $wpdb->query("DELETE FROM {$wpdb->prefix}yoast_indexable
                          WHERE object_type='term'
                            AND object_sub_type='product_category'
                            AND object_id IN ($ids)");
            echo '<div class="notice notice-success is-dismissible"><p>已保存 ' . (int) $saved . ' 条分类描述。</p></div>';
        }
    }

    // ── READ all terms ──────────────────────────────────────
    $top = get_terms([
        'taxonomy'   => 'product_category',
        'parent'     => 0,
        'hide_empty' => false,
        'orderby'    => 'menu_order',
        'order'      => 'ASC',
    ]);
    if (is_wp_error($top)) $top = [];

    $subs_by_parent = [];
    foreach ($top as $t) {
        $subs = get_terms([
            'taxonomy'   => 'product_category',
            'parent'     => (int) $t->term_id,
            'hide_empty' => false,
            'orderby'    => 'menu_order',
            'order'      => 'ASC',
        ]);
        if (is_wp_error($subs)) $subs = [];
        $subs_by_parent[$t->term_id] = $subs;
    }

    echo '<div class="wrap"><h1>分类描述批量编辑</h1>';
    echo '<p>在下方直接编辑每个分类的「描述」字段。描述会同步出现在 <strong>首页产品分类卡片</strong> 和 <strong>分类页顶部说明</strong>。<br><em>字数建议：一级分类 60–110 字，子分类 25–50 字。</em></p>';

    echo '<form method="post">';
    wp_nonce_field('xxdq_cat_desc_save');
    echo '<style>
        .xxdq-cat-desc-row { background:#fff; border:1px solid #ccd0d4; border-radius:6px; padding:14px 18px; margin-bottom:14px; }
        .xxdq-cat-desc-row--top { background:#f0f6fc; border-left:4px solid #1565C0; }
        .xxdq-cat-desc-row h3 { margin:0 0 8px; font-size:15px; }
        .xxdq-cat-desc-row .xxdq-meta { color:#666; font-size:12px; margin-left:8px; }
        .xxdq-cat-desc-row textarea { width:100%; font-family:inherit; padding:8px 10px; box-sizing:border-box; }
        .xxdq-cat-desc-row textarea.top { min-height:72px; }
        .xxdq-cat-desc-row textarea.sub { min-height:48px; }
        .xxdq-cat-desc-row .xxdq-counter { font-size:11px; color:#888; margin-top:4px; }
        .xxdq-cat-desc-row .xxdq-counter.warn { color:#d00; }
        .xxdq-cat-desc-sub { margin-left:24px; margin-top:10px; }
        .xxdq-cat-desc-top-row { display:flex; align-items:center; justify-content:space-between; }
    </style>';

    foreach ($top as $t) {
        $desc_top = (string) $t->description;
        ?>
        <div class="xxdq-cat-desc-row xxdq-cat-desc-row--top">
            <div class="xxdq-cat-desc-top-row">
                <h3>一级分类 · <?php echo esc_html($t->name); ?> <span class="xxdq-meta">term_id=<?php echo (int) $t->term_id; ?> · slug=<?php echo esc_html($t->slug); ?> · count=<?php echo (int) $t->count; ?></span></h3>
            </div>
            <textarea name="desc[<?php echo (int) $t->term_id; ?>]" class="top" oninput="this.nextElementSibling.textContent='字数 '+this.value.length+' (建议 60–110)'"
                ><?php echo esc_textarea($desc_top); ?></textarea>
            <div class="xxdq-counter">字数 <?php echo mb_strlen($desc_top, 'utf-8'); ?>（建议 60–110）</div>

            <?php
            $subs = $subs_by_parent[$t->term_id] ?? [];
            if (!empty($subs)):
                foreach ($subs as $s):
                    $desc_sub = (string) $s->description;
            ?>
                    <div class="xxdq-cat-desc-sub">
                        <div class="xxdq-cat-desc-row">
                            <h3>↳ <?php echo esc_html($s->name); ?> <span class="xxdq-meta">term_id=<?php echo (int) $s->term_id; ?> · count=<?php echo (int) $s->count; ?></span></h3>
                            <textarea name="desc[<?php echo (int) $s->term_id; ?>]" class="sub" oninput="this.nextElementSibling.textContent='字数 '+this.value.length+' (建议 25–50)'"
                                ><?php echo esc_textarea($desc_sub); ?></textarea>
                            <div class="xxdq-counter">字数 <?php echo mb_strlen($desc_sub, 'utf-8'); ?>（建议 25–50）</div>
                        </div>
                    </div>
                <?php
                endforeach;
            endif;
            ?>
        </div>
        <?php
    }

    echo '<p><input type="submit" class="button button-primary button-large" value="保存所有分类描述"></p>';
    echo '</form></div>';
}
