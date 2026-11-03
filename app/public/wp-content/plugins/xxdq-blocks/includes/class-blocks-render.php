<?php
/**
 * Block render callbacks as static methods.
 * Called by XXDQ_Blocks_Registry::reg().
 *
 * API: XXDQ_Blocks_Render::render_<block_name>($block, $content, $is_preview, $post_id)
 */

if (!defined('ABSPATH')) exit;

class XXDQ_Blocks_Render {

    private static $instance;
    public static $current_attrs = [];

    public static function instance() {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    /** Helper: get field value from attrs or post meta */
    public static function val($key, $post_id = 0, $default = '') {
        if (isset(self::$current_attrs[$key])) {
            return self::normalize_cta_url($key, self::$current_attrs[$key]);
        }
        $post_id = $post_id ?: get_the_ID();
        $value   = get_post_meta($post_id, $key, true);
        if ($value) {
            return self::normalize_cta_url($key, $value);
        }
        return $default;
    }

    /**
     * Three-tier SEO value resolver used by the Selector Tool:
     *   1. Block attribute (Inspector-driven, per-block, lives in post_content)
     *   2. ACF field on the page (post meta, attached via
     *      group_xxdq_selector_tool)
     *   3. Code default (hardcoded fallback so a fresh install still
     *      renders sensible Chinese copy)
     *
     * An empty string from either tier is treated as "not set" and
     * falls through to the next tier. This lets an admin explicitly
     * clear an inherited value (e.g. by emptying the Inspector field
     * and then emptying the ACF field) and still get a readable page
     * via the code default.
     */
    private static function resolve_seo_value($attr_key, $acf_key, $page_id, $default) {
        // Tier 1: block attribute. Only trust non-empty strings — an
        // empty Inspector field should NOT shadow a valid ACF value.
        if (isset(self::$current_attrs[$attr_key])) {
            $v = (string) self::$current_attrs[$attr_key];
            if ($v !== '') {
                return $v;
            }
        }
        // Tier 2: ACF. get_field returns null when the field has no
        // value on the post, which we also treat as "not set".
        if (function_exists('get_field')) {
            $v = get_field($acf_key, $page_id);
            if ($v !== null && $v !== false && $v !== '') {
                return (string) $v;
            }
        }
        // Tier 3: code default.
        return $default;
    }

    /**
     * Defensive rewrite: if a stored CTA URL points to one of the
     * stale inquiry-page targets that older versions of this plugin
     * hardcoded (`/询价单/`, `/inquiry/`, `/index.php/…`), replace it
     * with `inquiry_url()` so every button on the page agrees on the
     * same target.
     *
     * Without this, a block attribute like `hero_cta_url="/询价单/"`
     * stored inside `post_content` would shadow the PHP default and
     * produce an inconsistent (and possibly 404) URL on the live site,
     * even after the code change below.
     *
     * Only rewrites CTA-shaped keys (cta_url / button_url / cta-link).
     * Secondary CTAs that point elsewhere are left alone.
     */
    private static function normalize_cta_url($key, $value) {
        if (!is_string($value) || $value === '') return $value;
        $is_cta_field = (stripos($key, 'cta_url') !== false)
                     || (stripos($key, 'button_url') !== false)
                     || (stripos($key, 'cta-link') !== false)
                     || (stripos($key, 'cta_link') !== false);
        if (!$is_cta_field) return $value;

        $stale_prefixes = ['/询价单/', '/inquiry/', '/index.php/询价单/', '/index.php/inquiry/'];
        $is_stale = false;
        foreach ($stale_prefixes as $needle) {
            if ($value === $needle
                || strpos($value, $needle) !== false
                || strpos($value, urlencode('询价单')) !== false) {
                $is_stale = true;
                break;
            }
        }
        // Also rewrite fully-qualified (http(s)://…) variants that point
        // to the same target — the user's nav button on the live site
        // is one of these (e.g. https://dzldz.cn/inquiry/).
        if (!$is_stale) {
            foreach ($stale_prefixes as $needle) {
                if (strpos($value, $needle) !== false) { $is_stale = true; break; }
            }
        }
        return $is_stale ? self::inquiry_url() : $value;
    }

    /**
     * Canonical URL for the "询价单" (Inquiry / RFQ) page.
     *
     * Single source of truth for every CTA on the site. Returns
     * `get_permalink(17)` (page 17 is the published inquiry page in
     * wp_posts) when that page exists, otherwise falls back to
     * `home_url('/询价单/')` so the URL still works after a fresh import
     * where page numbering may shift.
     *
     * Centralising this here means the homepage nav, hero, hero-slider,
     * CTA block, and any future CTA block all point at the SAME URL —
     * no more 404 from `…/inquiry/` and no more non-URL-encoded raw
     * Chinese slug slipping into href attributes.
     */
    public static function inquiry_url() {
        /* Language-aware: on the English site this must resolve to
         * /en/inquiry/, not the Chinese /inquiry/ page. */
        if (function_exists('xxdq_inquiry_url')) {
            return xxdq_inquiry_url();
        }
        $url = get_permalink(17);
        if (!$url || (is_string($url) && $url === '')) {
            $url = home_url('/询价单/');
        }
        return $url;
    }

    /**
     * Build the product card thumbnail HTML.
     *
     * Tries the ACF gallery fields (real_image → tech_image → image_2..4)
     * via xxdq_product_gallery() first, then falls back to the WP featured
     * image. Returns a placeholder only when both are absent. This mirrors
     * the priority chain used by archive-product.php / single-product.php
     * so the home hot-products grid shows the same primary image as the
     * product detail page.
     */
    private static function product_thumb($pid) {
        $url = '';
        if (function_exists('xxdq_product_gallery')) {
            $g   = xxdq_product_gallery($pid, 'medium');
            $url = $g[0] ?? '';
        }
        if (empty($url)) {
            $fid = get_post_thumbnail_id($pid);
            if ($fid) {
                $url = wp_get_attachment_image_url($fid, 'medium', false);
            }
        }
        if (empty($url)) return '<span class="xxdq-product-card__noimg">' . ((function_exists('xxdq_is_en') && xxdq_is_en()) ? 'No image' : '无图') . '</span>';
        return sprintf(
            '<img src="%s" alt="%s" loading="lazy" />',
            esc_url($url),
            esc_attr(get_the_title($pid))
        );
    }

    /**
     * Replace legacy product URLs (e.g. `/产品中心/`, `/铜鼻子/`, `/冷压端子/`)
     * that were stored before we migrated to the real `product` post-type
     * archive and `product_category` taxonomy. The values were hardcoded
     * Chinese page slugs and now point to empty/draft pages.
     */
    public static function heal_legacy_product_url($url, $fallback) {
        if (!$url) return $fallback;
        // Match both literal and percent-encoded forms of the old slugs.
        $legacy = [
            '产品中心', '铜鼻子', '冷压端子', '预绝缘端子', '接插件',
            '%E4%BA%A7%E5%93%81%E4%B8%AD%E5%BF%83',          // 产品中心
            '%E9%93%9C%E9%BC%BB%E5%AD%90',                  // 铜鼻子
            '%E5%86%B7%E5%8E%8B%E7%AB%AF%E5%AD%90',          // 冷压端子
            '%E9%A2%84%E7%BB%9D%E7%BC%98%E7%AB%AF%E5%AD%90',  // 预绝缘端子
            '%E6%8E%A5%E6%8F%92%E4%BB%B6',                  // 接插件
        ];
        foreach ($legacy as $needle) {
            if (strpos($url, $needle) !== false) {
                return $fallback;
            }
        }
        return $url;
    }

    /* ── HERO ─────────────────────────────────────────────── */
    public static function render_hero($block, $content = '', $is_preview = false, $post_id = 0) {
        $is_en = function_exists('xxdq_is_en') && xxdq_is_en();
        /* The catalogue size is computed from the database rather than
         * hardcoded, so the marketing copy can never drift away from the
         * real catalogue. */
        $cat_count = function_exists('xxdq_catalogue_kw') ? xxdq_catalogue_kw() : '300';
        $defaults = $is_en ? [
            'eyebrow' => 'Direct Manufacturer · Full Supply Chain · Ready to Ship',
            'subtitle'=> 'Copper Lug · Crimp Terminal · Pre-insulated Terminal · Connector — ' . $cat_count . '+ Models · CE/RoHS Certified · Ships in 24 Hours',
            'cta'     => 'Get a Quote',
            'sec_cta' => 'Browse Products',
        ] : [
            'eyebrow' => '源头工厂 · 全产业链 · 现货直发',
            'subtitle'=> '铜鼻子 · 冷压端子 · 预绝缘端子 · 接插件 · ' . $cat_count . '+ 规格 · CE/RoHS/ISO9001 认证 · 24小时出货',
            'cta'     => '立即询价',
            'sec_cta' => '浏览产品',
        ];
        $eyebrow = self::val('hero_eyebrow', $post_id, $defaults['eyebrow']);
        $subtitle = self::val('hero_subtitle', $post_id, $defaults['subtitle']);
        $cta_text = self::val('hero_cta_text', $post_id, $defaults['cta']);
        // Default the hero CTA to the canonical inquiry page URL — NOT
        // a hardcoded `/询价单/`. The old default caused the homepage
        // CTA to drift out of sync with the nav's "立即询价" button and
        // left a non-URL-encoded Chinese slug in the href.
        $cta_url  = self::val('hero_cta_url', $post_id, self::inquiry_url());
        $sec_cta_text = self::val('hero_secondary_cta_text', $post_id, $defaults['sec_cta']);
        $sec_cta_url  = self::val('hero_secondary_cta_url', $post_id, get_post_type_archive_link('product') ?: '/product/');
        $style = self::val('hero_style', $post_id, 'industrial-dark');

        // Heal stale values stored from older versions of the theme that
        // hardcoded `/产品中心/` (a now-static page) for these buttons.
        // The actual product archive lives at /product/. Replace on render
        // so we don't need a DB migration.
        $product_archive = get_post_type_archive_link('product') ?: home_url('/product/');
        $cta_url     = self::heal_legacy_product_url($cta_url, $product_archive);
        $sec_cta_url = self::heal_legacy_product_url($sec_cta_url, $product_archive);

        $actions = '';
        if ($cta_text)
            $actions .= sprintf('<a href="%s" class="xxdq-hero__btn xxdq-hero__btn--primary">%s</a>',
                esc_url($cta_url), esc_html($cta_text));
        if ($sec_cta_text)
            $actions .= sprintf('<a href="%s" class="xxdq-hero__btn xxdq-hero__btn--secondary">%s</a>',
                esc_url($sec_cta_url), esc_html($sec_cta_text));

        /* The H1 carries the brand + the product-category descriptor, the
         * same way the Chinese hero does:
         *   ZH  乐清市耕文电气有限公司 – 电气连接件源头工厂
         *   EN  Gengwen Electric – Electrical Connector Manufacturer
         * `get_bloginfo('name')` alone would render a bare "Gengwen
         * Electric", which is too thin for an H1 and loses the
         * "electrical connector manufacturer" keyword. */
        $hero_title = get_bloginfo('name');
        if ($is_en) {
            $tagline = function_exists('xxdq_t') ? xxdq_t('brand.tagline') : '';
            if ($tagline !== '' && stripos($hero_title, $tagline) === false) {
                $hero_title .= ' – ' . $tagline;
            }
        }

        return sprintf('<section class="xxdq-hero xxdq-hero--%s">
  <div class="xxdq-hero__inner">
    %s
    <h1 class="xxdq-hero__title">%s</h1>
    <p class="xxdq-hero__subtitle">%s</p>
    <div class="xxdq-hero__actions">%s</div>
  </div>
</section>',
            esc_html($style),
            $eyebrow ? '<p class="xxdq-hero__eyebrow">'.esc_html($eyebrow).'</p>' : '',
            esc_html($hero_title),
            esc_html($subtitle),
            $actions
        );
    }

    /* ── STATS BAR ─────────────────────────────────────────── */
    public static function render_stats_bar($block, $content = '', $is_preview = false, $post_id = 0) {
        $is_en = function_exists('xxdq_is_en') && xxdq_is_en();
        /* Computed from the database — the old hardcoded "500+" did not
         * match the real catalogue. */
        $cat_count = function_exists('xxdq_catalogue_kw') ? xxdq_catalogue_kw() : '300';
        $items = $is_en ? [
            ['value' => $cat_count . '+', 'label' => 'Product Models'],
            ['value' => 'CE/RoHS', 'label' => 'Certified'],
            ['value' => '24h', 'label' => 'Fast Dispatch'],
            ['value' => '10+ yrs', 'label' => 'Industry Experience'],
        ] : [
            ['value' => $cat_count . '+', 'label' => '产品规格'],
            ['value' => 'CE/RoHS', 'label' => '认证保障'],
            ['value' => '24h', 'label' => '快速出货'],
            ['value' => '10年+', 'label' => '行业经验'],
        ];
        $html = '<div class="xxdq-stats-bar"><div class="xxdq-stats-bar__inner">';
        foreach ($items as $item) {
            $html .= sprintf('<div class="xxdq-stats-bar__item"><strong>%s</strong><span>%s</span></div>',
                esc_html($item['value']), esc_html($item['label']));
        }
        $html .= '</div></div>';
        return $html;
    }

    /* ── CATEGORY GRID ──────────────────────────────────────── */
    public static function render_category_grid($block, $content = '', $is_preview = false, $post_id = 0) {
        $style = self::val('cg_style', $post_id, 'grid');

        // Pull categories dynamically from the product_category taxonomy so the
        // homepage grid stays in sync with the sidebar on /product/. The desc
        // is stored as the category description in WP admin.
        //
        // parent=0 ⇒ only top-level (产品中心入口) cards. Sub-categories live
        // under their parent on the archive page, so the homepage stays a
        // clean grid of product families instead of a 90-card wall.
        $cats = get_terms([
            'taxonomy'   => 'product_category',
            'parent'     => 0,
            'hide_empty' => false,
            'orderby'    => 'menu_order',
            'order'      => 'ASC',
        ]);
        /* Keep only the categories of the current language — the English
         * site keeps a parallel set of terms. */
        if (function_exists('xxdq_filter_terms_by_lang')) {
            $cats = xxdq_filter_terms_by_lang($cats);
        }
        if (is_wp_error($cats) || empty($cats)) {
            // Fallback for first install before categories exist.
            $cats = [
                (object)['name' => '铜鼻子',     'slug' => 'copper',         'description' => 'T系列铜管端子, DTG系列'],
                (object)['name' => '冷压端子',   'slug' => 'cold',           'description' => 'SC系列冷压端头, OT开口鼻'],
                (object)['name' => '预绝缘端子', 'slug' => 'preinsulated',   'description' => 'VE管型, E系列预绝缘'],
                (object)['name' => '接插件',     'slug' => 'connector',      'description' => 'UK/EUK 轨道式接线端子'],
            ];
        }

        // Cover-image lookup table. Static map keeps the home page stable
        // without relying on per-category term-meta, and the local entries
        // cover categories that don't yet have a real product photo.
        //
        // Photos are pre-downloaded to /wp-content/uploads/2026/09/ so the
        // home page never depends on a third-party CDN. The connector
        // category in particular uses a network-switch photo (red
        // Ethernet cables plugged into a switch) — semantically a much
        // better fit for "接插件" than the previous meeting-room shot.
        //
        // Keys MUST match the real term slugs. The original keys
        // ('copper', 'cold', 'preinsulated', 'connector') were written
        // before the product was imported with English-hyphenated slugs
        // ('cold-pressed-terminal', 'pre-insulated-terminals',
        // 'connectors', …). The mismatch meant every category except
        // 'copper' silently fell back to the placeholder, which is why
        // the homepage grid looked stale no matter what was uploaded.
        //
        // Each entry picks a representative terminal screenshot from
        // uploads/2026/09/ so every card visually matches its family:
        //   - bare (DBN)            片形裸端头  → bare-terminals
        //   - SV 叉形预绝缘端头     → pre-insulated-connectors
        //   - MDD 公预绝缘端头      → pre-insulated-nylon-connectors
        //   - RV 圆形预绝缘端头     → pre-insulated-terminals
        //   - VE 管型 / 双线         → pre-insulated-nylon
        //   - DT / SC 铜管端子       → copper-tube-terminals
        //   - 中间接头              → intermediate-connectors
        //   - 绝缘软管套            → insulation-sleeves
        $img_map = [
            'copper'                       => '/wp-content/uploads/2026/09/ScreenShot_2026-09-24_201200_204-300x225.png',
            'cold-pressed-terminal'        => '/wp-content/uploads/2026/09/ScreenShot_2026-09-24_194852_420-300x258.png',
            'bare-terminals'               => '/wp-content/uploads/2026/09/ScreenShot_2026-09-24_195435_132-300x240.png',
            'copper-tube-terminals'         => '/wp-content/uploads/2026/09/ScreenShot_2026-09-24_195235_027-300x291.png',
            'pre-insulated-terminals'      => '/wp-content/uploads/2026/09/ScreenShot_2026-09-24_201945_638-297x300.png',
            'pre-insulated-nylon'          => '/wp-content/uploads/2026/09/tube-type-pre-insulated-terminal.jpg',
            'pre-insulated-connectors'     => '/wp-content/uploads/2026/09/ScreenShot_2026-09-24_202406_077-281x300.png',
            'pre-insulated-nylon-connectors'=> '/wp-content/uploads/2026/09/ScreenShot_2026-09-24_200811_413-300x293.png',
            'intermediate-connectors'      => '/wp-content/uploads/2026/09/ScreenShot_2026-09-24_201442_757-300x285.png',
            'connectors'                   => '/wp-content/uploads/2026/09/cat-connector.jpg',
            'insulation-sleeves'           => '/wp-content/uploads/2026/09/ScreenShot_2026-09-24_202627_333-300x264.png',
        ];
        $fallback_img = '/wp-content/uploads/2026/09/ScreenShot_2026-09-24_202135_542-283x300.png';

        // Build a prefix-keyed fallback so a slug rename in a future
        // import (e.g. 'copper-tube-terminals' → 'tube-terminals') still
        // resolves to a sensible image instead of the bare fallback.
        // Order matters — longest match wins.
        $prefix_map = [
            'copper-tube-terminals'         => 'copper-tube-terminals',
            'copper'                        => 'copper',
            'cold-pressed-terminal'         => 'cold-pressed-terminal',
            'bare-terminals'                => 'bare-terminals',
            'pre-insulated-nylon-connectors'=> 'pre-insulated-nylon-connectors',
            'pre-insulated-nylon'           => 'pre-insulated-nylon',
            'pre-insulated-connectors'      => 'pre-insulated-connectors',
            'pre-insulated-terminals'       => 'pre-insulated-terminals',
            'intermediate-connectors'       => 'intermediate-connectors',
            'connectors'                    => 'connectors',
            'insulation-sleeves'            => 'insulation-sleeves',
        ];

        // Helper: resolve an image URL for any slug. Used by the closure
        // below so the prefix fallback is also available there.
        //
        // Order of preference (added by Phase 9.5):
        //   1. term meta `cover_image_id` (set per-category in WP admin)
        //   2. hardcoded $img_map
        //   3. prefix-based $prefix_map
        //   4. generic "pre-*" family default
        //   5. fallback placeholder
        $resolve_img = function ($cat) use ($img_map, $fallback_img, $prefix_map) {
            return XXDQ_Blocks_Term_Meta::resolve_cover_image($cat, $img_map, $prefix_map, $fallback_img);
        };


        // 首页按业务分组展示 11 张一级分类卡片,而非平铺一整块大网格,
        // 让访客一眼看出"端子 / 接头 / 绝缘防护"三大产品族。
        //
        // The group is defined by the CHINESE term IDs; on the English
        // site each one is translated to its English counterpart via
        // the `xxdq_en_term_id` term meta. Hard-coding the English IDs
        // would break the moment a category is re-imported, because the
        // English term IDs are allocated sequentially by the importer.
        $is_en = function_exists('xxdq_is_en') && xxdq_is_en();
        $group_map = $is_en ? [
            'Cable Terminals'    => [4, 5, 43, 72, 29, 36],
            'Splice & Connector' => [53, 60, 66, 78],
            'Insulation'         => [82],
        ] : [
            '端子系列 · Cable Terminals'    => [4, 5, 43, 72, 29, 36],
            '接头系列 · Splice & Connector' => [53, 60, 66, 78],
            '绝缘防护 · Insulation'         => [82],
        ];

        // 把 $cats 按 term_id 索引,方便按指定顺序取。
        $cats_by_id = [];
        foreach ($cats as $cat) {
            $cats_by_id[(int) $cat->term_id] = $cat;
        }

        /* On the English site the grouped IDs are the Chinese ones, so
         * they have to be swapped for the English terms. */
        if ($is_en) {
            foreach ($group_map as $group_title => $zh_ids) {
                $en_ids = [];
                foreach ($zh_ids as $zh_id) {
                    $en_id = class_exists('XXDQ_I18n_Content')
                        ? XXDQ_I18n_Content::get_en_term_id($zh_id)
                        : 0;
                    if ($en_id && isset($cats_by_id[$en_id])) {
                        $en_ids[] = $en_id;
                    }
                }
                $group_map[$group_title] = $en_ids;
            }
        }

        $render_card = function ($cat) use ($resolve_img) {
            $link = get_term_link($cat);
            if (is_wp_error($link)) return '';
            $full_desc = $cat->description;
            $stripped  = trim(wp_strip_all_tags($full_desc));
            $desc      = mb_strlen($stripped, 'utf-8') > 60
                ? mb_substr($stripped, 0, 60, 'utf-8') . '…'
                : $stripped;
            $img = $resolve_img($cat);
            return sprintf(
                '<a class="xxdq-category-card" href="%s">
  <div class="xxdq-category-card__media"><img src="%s" alt="%s" loading="lazy" /></div>
  <div class="xxdq-category-card__body">
    <h3 class="xxdq-category-card__name">%s</h3>
    <p class="xxdq-category-card__desc">%s</p>
    <span class="xxdq-category-card__arrow">→</span>
  </div>
</a>',
                esc_url($link),
                esc_url($img),
                esc_attr($cat->name),
                esc_html($cat->name),
                esc_html($desc)
            );
        };

        $html = '<div class="xxdq-category-grid-wrap">';
        foreach ($group_map as $group_title => $group_ids) {
            $html .= '<section class="xxdq-category-group">';
            $html .= '<h3 class="xxdq-category-group__title">' . esc_html($group_title) . '</h3>';
            $html .= '<div class="xxdq-category-grid xxdq-category-grid--grouped">';
            $rendered_in_group = 0;
            foreach ($group_ids as $tid) {
                if (!isset($cats_by_id[$tid])) continue;
                $card = $render_card($cats_by_id[$tid]);
                if ($card === '') continue;
                $html .= $card;
                $rendered_in_group++;
            }
            // 不够 4 张时补占位,保持每行 4 张的视觉节奏(最后一行不满时左对齐)
            $spacer_count = $rendered_in_group > 0 ? (4 - ($rendered_in_group % 4)) % 4 : 0;
            for ($i = 0; $i < $spacer_count; $i++) {
                $html .= '<div class="xxdq-category-card xxdq-category-card--spacer" aria-hidden="true"></div>';
            }
            $html .= '</div></section>';
        }
        $html .= '</div>';
        return $html;
    }

    /* ── HOT PRODUCTS ────────────────────────────────────────── */
    public static function render_hot_products($block, $content = '', $is_preview = false, $post_id = 0) {
        $is_en = function_exists('xxdq_is_en') && xxdq_is_en();
        $q = new WP_Query([
            'post_type' => 'product', 'posts_per_page' => 8, 'post_status' => 'publish', 'orderby' => 'rand',
            'meta_query' => $is_en
                ? [['key' => '_xxdq_lang', 'value' => 'en_US']]
                : [['key' => '_xxdq_lang', 'compare' => 'NOT EXISTS']],
        ]);
        if (!$q->have_posts()) return '<p class="xxdq-empty">' . ($is_en ? 'No products found.' : '暂无产品。') . '</p>';
        $html = '<div class="xxdq-hot-products"><h2 class="xxdq-section-title">' . ($is_en ? 'Popular Products' : '热门产品') . '</h2><div class="xxdq-product-grid">';
        while ($q->have_posts()) { $q->the_post();
            $img = self::product_thumb(get_the_ID());
            $html .= sprintf('<a class="xxdq-product-card" href="%s">%s<h3>%s</h3></a>',
                esc_url(get_permalink()), $img, esc_html(get_the_title()));
        }
        wp_reset_postdata();
        return $html . '</div></div>';
    }

    /* ── PROCESS STEPS ──────────────────────────────────────── */
    public static function render_process_steps($block, $content = '', $is_preview = false, $post_id = 0) {
        $is_en = function_exists('xxdq_is_en') && xxdq_is_en();
        $steps = $is_en
            ? ['Requirement Review', 'Model Selection', 'Quotation & Sample', 'Bulk Production', 'Inspection & Shipment']
            : ['需求确认','选型指导','报价打样','批量生产','质检出货'];
        $html = '<div class="xxdq-process-steps"><h2 class="xxdq-section-title">' . ($is_en ? 'How We Work' : '合作流程') . '</h2><ol class="xxdq-process-steps__list">';
        foreach ($steps as $i => $s)
            $html .= sprintf('<li class="xxdq-process-steps__item"><span class="xxdq-process-steps__num">%d</span><span class="xxdq-process-steps__label">%s</span></li>', $i+1, esc_html($s));
        return $html . '</ol></div>';
    }

    /* ── WHY US ─────────────────────────────────────────────── */
    public static function render_why_us($block, $content = '', $is_preview = false, $post_id = 0) {
        // Phase 9: prefer ACF Options Page data; fall back to defaults.
        $is_en = function_exists('xxdq_is_en') && xxdq_is_en();
        /* The stock claim is computed from the database — see
         * xxdq_catalogue_stats(). */
        $cat_count = function_exists('xxdq_catalogue_kw') ? xxdq_catalogue_kw() : '300';
        $default_items = $is_en ? [
            ['icon' => '🏭', 'title' => 'Direct Manufacturer', 'desc' => 'In-house factory, controlled cost, stable quality'],
            ['icon' => '📦', 'title' => 'Large Stock', 'desc' => $cat_count . '+ models in stock, dispatched within 24 hours'],
            ['icon' => '✅', 'title' => 'Certified Quality', 'desc' => 'CE / RoHS / ISO9001 certified, export-grade'],
            ['icon' => '🔧', 'title' => 'Flexible Customisation', 'desc' => 'Custom parts supported, small batches welcome'],
        ] : [
            ['icon' => '🏭', 'title' => '源头工厂', 'desc' => '自有工厂,成本可控,品质稳定'],
            ['icon' => '📦', 'title' => '海量库存', 'desc' => $cat_count . '+ 规格现货,24小时快速出货'],
            ['icon' => '✅', 'title' => '权威认证', 'desc' => 'CE/RoHS/ISO9001 认证,出口品质'],
            ['icon' => '🔧', 'title' => '定制灵活', 'desc' => '支持非标定制,小批量也欢迎'],
        ];
        $items = $default_items;
        if (function_exists('get_field')) {
            $acf_items = get_field('why_us_items', 'option');
            if (is_array($acf_items) && !empty($acf_items)) {
                $items = [];
                foreach ($acf_items as $row) {
                    $title = isset($row['title'])      ? (string) $row['title']      : '';
                    $desc  = isset($row['description'])? (string) $row['description']: '';
                    /* The ACF options page is shared with the Chinese site, so
                     * its rows may still contain Chinese copy. On English
                     * pages fall back to the English defaults instead of
                     * leaking Chinese text. */
                    if ($is_en && preg_match('/[\x{4e00}-\x{9fff}]/u', $title . $desc)) {
                        $items = $default_items;
                        break;
                    }
                    $items[] = [
                        'icon' => isset($row['icon'])        ? (string) $row['icon']        : '',
                        'title' => $title,
                        'desc'  => $desc,
                    ];
                }
            }
        }
        $html = '<div class="xxdq-why-us"><h2 class="xxdq-section-title">' . ($is_en ? 'Why Choose Us' : '为什么选择我们') . '</h2><div class="xxdq-why-us__grid">';
        foreach ($items as $it)
            $html .= sprintf('<div class="xxdq-why-us__item"><div class="xxdq-why-us__icon">%s</div><h3>%s</h3><p>%s</p></div>',
                esc_html($it['icon']), esc_html($it['title']), esc_html($it['desc']));
        return $html . '</div></div>';
    }

    /* ── CTA ────────────────────────────────────────────────── */
    public static function render_cta($block, $content = '', $is_preview = false, $post_id = 0) {
        $is_en = function_exists('xxdq_is_en') && xxdq_is_en();
        if ($is_en) {
            return sprintf('<div class="xxdq-cta">
  <h2>Need a quotation or technical support?</h2>
  <p>Contact us — we reply within 1 hour</p>
  <a href="%s" class="xxdq-cta__btn">Get a Quote →</a>
</div>', esc_url(self::inquiry_url()));
        }
        return sprintf('<div class="xxdq-cta">
  <h2>需要报价或技术支持?</h2>
  <p>联系我们,1小时内响应您的需求</p>
  <a href="%s" class="xxdq-cta__btn">立即询价 →</a>
</div>', esc_url(self::inquiry_url()));
    }

    /* ── CATEGORY ARCHIVE ──────────────────────────────────── */
    public static function render_category_archive($block, $content = '', $is_preview = false, $post_id = 0) {
        $cat_id = (int)self::val('ca_category', $post_id, 0);
        $page = $cat_id ? get_post($cat_id) : null;
        $is_en = function_exists('xxdq_is_en') && xxdq_is_en();
        $title = $page ? $page->post_title : ($is_en ? 'Product List' : '产品列表');
        $q = new WP_Query(['post_type' => 'product', 'posts_per_page' => -1, 'post_status' => 'publish']);
        if (!$q->have_posts()) return '<p class="xxdq-empty">' . ($is_en ? 'No products in this category.' : '此分类暂无产品。') . '</p>';
        $html = sprintf('<div class="xxdq-category-archive"><h2 class="xxdq-section-title">%s</h2><div class="xxdq-product-grid">', esc_html($title));
        while ($q->have_posts()) { $q->the_post();
            $img = self::product_thumb(get_the_ID());
            $html .= sprintf('<a class="xxdq-product-card" href="%s">%s<h3>%s</h3></a>',
                esc_url(get_permalink()), $img, esc_html(get_the_title()));
        }
        wp_reset_postdata();
        return $html . '</div></div>';
    }

    /* ── SELECTOR TOOL ─────────────────────────────────────── */
    public static function render_selector_tool($block, $content = '', $is_preview = false, $post_id = 0) {
        // Enqueue self-contained assets (only when this block is on the page).
        wp_enqueue_style(
            'xxdq-selector-tool',
            XXDQ_BLOCKS_URL . 'assets/selector-tool.css',
            [],
            XXDQ_BLOCKS_VERSION
        );
        wp_enqueue_script(
            'xxdq-selector-tool',
            XXDQ_BLOCKS_URL . 'assets/selector-tool.js',
            [],
            XXDQ_BLOCKS_VERSION,
            true
        );

        // SEO content resolution priority:
        //   1. Block attributes (set via the JS Inspector panel — the
        //      per-block source of truth for admins editing in the page
        //      editor, lives in post_content as Gutenberg block markup).
        //   2. ACF fields on the page (group_xxdq_selector_tool, attached
        //      to post_type=page) — backward-compatible with pages
        //      authored before the Inspector existed.
        //   3. Code defaults — the original inline Chinese copy that
        //      ships with the block, used as a last-resort safety net so
        //      the page still renders sensible content if both ACF and
        //      attributes are empty.
        //
        // FAQ is intentionally NOT pulled from block attributes — the
        //       repeater UI would require a full custom Inspector that
        //       is overkill for a 5-item list. Admins keep using the
        //       ACF field group for FAQ editing.
        $page_id = $post_id ?: get_the_ID();

        /* The English site must not read the Chinese ACF copy, so the
         * language is resolved first and the English strings are used
         * unconditionally. */
        $is_en = function_exists('xxdq_is_en') && xxdq_is_en();
        $t     = self::selector_strings($is_en);

        if ($is_en) {
            $seo_intro   = $t['seo_intro'];
            $seo_formula = $t['seo_formula_body'];
        } else {
            $seo_intro  = self::resolve_seo_value(
                'selectorIntro', 'selector_intro', $page_id,
                $t['seo_intro']
            );
            $seo_formula = self::resolve_seo_value(
                'selectorFormula', 'selector_formula', $page_id,
                $t['seo_formula_body']
            );
        }
        $inquiry_url = self::resolve_seo_value(
            'selectorInquiryUrl', 'selector_inquiry_url', $page_id,
            self::inquiry_url()
        );

        // Build a model → product-URL map so the JS can decide whether to
        // render the "查看该型号产品" button without making a REST call.
        // We keep it inline as a data-* attribute because:
        //  - the page is server-rendered (no cache layer to flush),
        //  - the map is small (≤ ~50 models active per page load),
        //  - it avoids a roundtrip from the user clicking "calculate".
        $product_map = [];
        $pq = new \WP_Query([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
            'fields'         => 'ids',
        ]);
        /* The English site keeps a parallel set of products.
         *
         * We filter in PHP rather than with a `meta_query` on purpose:
         * adding a meta_query changes the query plan, which changes the
         * order the rows come back in, and that order ends up in the
         * generated JSON map. Filtering in PHP keeps the exact same
         * query (and therefore the exact same output) as before the
         * English site existed. */
        $is_en = function_exists('xxdq_is_en') && xxdq_is_en();
        if ($pq->have_posts()) {
            foreach ($pq->posts as $pid) {
                $lang = get_post_meta($pid, '_xxdq_lang', true);
                if ($is_en) {
                    if ($lang !== 'en_US') {
                        continue;
                    }
                } elseif ($lang === 'en_US') {
                    continue;
                }

                /* The Chinese site keys the map by the ACF `model` field,
                 * which holds the model code (DT-25, RV1.25-4, …).
                 *
                 * The English site must NOT reuse the Chinese value: for
                 * the type-level products `model` holds a Chinese type
                 * description (片型裸端头, 堵油型铜接线端子（DT型）, …)
                 * instead of a code, so it would leak Chinese into the
                 * inline JSON. XXDQ_I18n_Content::spec() resolves the
                 * English counterpart (`model_en` → `model`) for us.
                 *
                 * The Chinese branch is left byte-for-byte identical so
                 * the baseline pages do not change. */
                if ($is_en) {
                    $model = class_exists('XXDQ_I18n_Content')
                        ? XXDQ_I18n_Content::spec('model', $pid)
                        : '';
                    if ($model === '') {
                        $model = get_the_title($pid);
                    }
                    /* Belt-and-braces: an English map key must never
                     * contain CJK, otherwise the inline JSON would leak
                     * Chinese onto /en/ pages. */
                    if (preg_match('/[\x{4e00}-\x{9fff}]/u', $model)) {
                        $model = get_the_title($pid);
                    }
                } else {
                    $model = function_exists('get_field')
                        ? (string) get_field('model', $pid)
                        : '';
                    if ($model === '') {
                        $model = get_the_title($pid);
                    }
                }
                $model = trim($model);
                if ($model !== '') {
                    $product_map[$model] = get_permalink($pid);
                }
            }
            wp_reset_postdata();
        }
        $product_map_json = wp_json_encode(
            (object) $product_map,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        // FAQ stays ACF-only (see comment above).
        $seo_faqs = [];
        if (function_exists('get_field')) {
            $acf_faqs = get_field('selector_faqs', $page_id);
            if (is_array($acf_faqs) && !empty($acf_faqs)) {
                $seo_faqs = $acf_faqs;
            }
        }

        /* ── UI string table ────────────────────────────────────────────
         * The Chinese strings are the historical defaults; the English
         * ones are the equivalents for the /en/ site. */
        if (empty($seo_faqs)) {
            $seo_faqs = $is_en ? self::selector_faqs_en() : [
                ['q' => '为什么计算结果比标称值小？', 'a' => '因为实际工况下温度升高、穿管散热差、铝芯电阻率高于铜芯等原因,需要对基准载流量进行修正。'],
                ['q' => '截面 1.5 mm² 选 DT-16 是不是太浪费？', 'a' => '工具会自动向上取整到最近的规格型号,这是出于安全裕度考虑。如果对成本敏感,可联系我司技术确认更小规格。'],
                ['q' => '已知功率 kW,为什么用公式换算成电流再选？', 'a' => '端子库的规格是按基准载流量(安培)编排的,选型本质是按电流。系统电压、相数、功率因数决定了功率对应的电流值,公式 I = P/(U·cosφ)(单相)或 I = P/(√3·U·cosφ)(三相)就是电气工程的标准换算。'],
                ['q' => '为什么我填了功率,但工具还是让我按截面反算？', 'a' => '功率字段是「选填」,留空时工具按导线截面查端子表,并根据修正后的载流量反算该截面对应的最大可持续功率,便于您核对设计裕度。'],
                ['q' => '功率因数默认 0.85,可以改吗？', 'a' => '可以。电机类负载一般取 0.8–0.85,电阻性负载（电加热、白炽灯）取 1.0,变频器后端可取 0.95 以上。表单中可自定义输入 0–1 之间。'],
                ['q' => '铜线和铝线为什么不能直接压接？', 'a' => '铜铝直接接触会形成电偶腐蚀,接触电阻迅速增大、发热。铜铝过渡场合必须使用 DTL 系列（铜铝过渡端子）。'],
                ['q' => 'DTL 端子的铝端可以反复拆装吗？', 'a' => '不建议。DTL 端子在压接后形成气密连接,拆装会破坏氧化层,需要重新打掉氧化层或更换新品。'],
                ['q' => '计算结果可以直接下单吗？', 'a' => '可以。点击「立即询价」按钮,系统会自动把推荐型号带到询价单,您只需补充数量和交期。'],
            ];
        }

        // Build Schema.org JSON-LD AFTER FAQs are resolved so the FAQPage
        // entity has the final list. Two @graph entries:
        //   - SoftwareApplication (the calculator tool itself)
        //   - FAQPage (the 8 Q&A items)
        $schema_ld = self::build_selector_schema($page_id, $seo_faqs, $inquiry_url);

        ob_start();
        ?>
<section class="xxdq-selector" data-inquiry-url="<?php echo esc_attr($inquiry_url); ?>"<?php echo $is_en ? ' data-lang="en"' : ''; ?>>
  <script type="application/json" class="xxdq-selector__product-map"><?php echo $product_map_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — JSON is server-generated and trusted; user data flows through wp_json_encode which handles JSON-level escaping. ?></script>
  <header class="xxdq-selector__hero">
    <h1><?php echo esc_html($t['hero_title']); ?></h1>
    <p><?php echo esc_html($t['hero_sub']); ?></p>
  </header>

  <div class="xxdq-selector__card">
    <h2><?php echo esc_html($t['form_title']); ?></h2>
    <p class="xxdq-selector__hint"><?php echo esc_html($t['form_hint']); ?></p>
    <form class="xxdq-selector__form" id="xxdq-selector-form" novalidate>

      <div class="xxdq-selector__field">
        <label class="xxdq-selector__label" for="xxdq-area"><?php echo esc_html($t['area']); ?><span class="xxdq-selector__req">*</span></label>
        <select class="xxdq-selector__select" id="xxdq-area" name="area" required>
          <option value=""><?php echo esc_html($t['area_ph']); ?></option>
        </select>
      </div>

      <div class="xxdq-selector__field">
        <label class="xxdq-selector__label" for="xxdq-material"><?php echo esc_html($t['material']); ?><span class="xxdq-selector__req">*</span></label>
        <select class="xxdq-selector__select" id="xxdq-material" name="material" required>
          <option value="copper"><?php echo esc_html($t['mat_copper']); ?></option>
          <option value="aluminum"><?php echo esc_html($t['mat_aluminum']); ?></option>
        </select>
      </div>

      <div class="xxdq-selector__field">
        <label class="xxdq-selector__label" for="xxdq-temp"><?php echo esc_html($t['temp']); ?><span class="xxdq-selector__req">*</span></label>
        <input class="xxdq-selector__input" type="number" id="xxdq-temp" name="temp" min="-30" max="80" step="1" value="25" required>
      </div>

      <div class="xxdq-selector__field">
        <label class="xxdq-selector__label" for="xxdq-install"><?php echo esc_html($t['install']); ?><span class="xxdq-selector__req">*</span></label>
        <select class="xxdq-selector__select" id="xxdq-install" name="install" required>
          <option value="air"><?php echo esc_html($t['inst_air']); ?></option>
          <option value="pipe"><?php echo esc_html($t['inst_pipe']); ?></option>
          <option value="buried"><?php echo esc_html($t['inst_buried']); ?></option>
        </select>
      </div>

      <div class="xxdq-selector__field">
        <label class="xxdq-selector__label" for="xxdq-type"><?php echo esc_html($t['type']); ?><span class="xxdq-selector__req">*</span></label>
        <select class="xxdq-selector__select" id="xxdq-type" name="type" required>
          <option value="DT"><?php echo esc_html($t['type_dt']); ?></option>
          <option value="SC"><?php echo esc_html($t['type_sc']); ?></option>
          <option value="RV"><?php echo esc_html($t['type_rv']); ?></option>
          <option value="DTL"><?php echo esc_html($t['type_dtl']); ?></option>
        </select>
      </div>

      <div class="xxdq-selector__field xxdq-selector__field--power">
        <label class="xxdq-selector__label" for="xxdq-voltage"><?php echo esc_html($t['voltage']); ?><span class="xxdq-selector__req">*</span></label>
        <select class="xxdq-selector__select" id="xxdq-voltage" name="voltage" required>
          <option value="220">220 V</option>
          <option value="380" selected>380 V</option>
          <option value="660">660 V</option>
        </select>
      </div>

      <div class="xxdq-selector__field xxdq-selector__field--power">
        <label class="xxdq-selector__label" for="xxdq-phase"><?php echo esc_html($t['phase']); ?><span class="xxdq-selector__req">*</span></label>
        <select class="xxdq-selector__select" id="xxdq-phase" name="phase" required>
          <option value="three" selected><?php echo esc_html($t['phase_three']); ?></option>
          <option value="single"><?php echo esc_html($t['phase_single']); ?></option>
        </select>
      </div>

      <div class="xxdq-selector__field xxdq-selector__field--power">
        <label class="xxdq-selector__label" for="xxdq-power"><?php echo esc_html($t['power']); ?></label>
        <input class="xxdq-selector__input" type="number" id="xxdq-power" name="power" min="0" step="0.1" placeholder="<?php echo esc_attr($t['optional']); ?>">
      </div>

      <div class="xxdq-selector__field xxdq-selector__field--power">
        <label class="xxdq-selector__label" for="xxdq-powerfactor"><?php echo esc_html($t['powerfactor']); ?></label>
        <input class="xxdq-selector__input" type="number" id="xxdq-powerfactor" name="powerFactor" min="0" max="1" step="0.01" value="0.85">
      </div>

      <div class="xxdq-selector__actions">
        <button type="submit" class="xxdq-selector__btn xxdq-selector__btn--primary"><?php echo esc_html($t['submit']); ?></button>
        <button type="button" class="xxdq-selector__btn xxdq-selector__btn--ghost xxdq-selector__reset"><?php echo esc_html($t['reset']); ?></button>
      </div>
    </form>
  </div>

  <div class="xxdq-selector__error" role="alert">
    <strong><?php echo esc_html($t['err_title']); ?></strong>
    <span class="xxdq-selector__error-msg"></span>
  </div>

  <div class="xxdq-selector__result" aria-live="polite">

    <div class="xxdq-selector__result-head">
      <h2 class="xxdq-selector__result-title"><?php echo esc_html($t['res_title']); ?></h2>
      <span class="xxdq-selector__model-badge"></span>
    </div>

    <div class="xxdq-selector__tables">

      <!-- <?php echo $is_en ? 'Terminal selection result' : '端子选型结果'; ?> -->
      <div class="xxdq-selector__terminal-section">
        <h3><?php echo esc_html($t['sec_terminal']); ?></h3>
        <table class="xxdq-selector__terminal-table">
          <tbody>
            <tr>
              <th><?php echo esc_html($t['row_model']); ?></th>
              <td class="xxdq-selector__stat--model"><span class="xxdq-selector__model-badge">—</span></td>
            </tr>
            <tr>
              <th><?php echo esc_html($t['row_hole']); ?></th>
              <td><span class="xxdq-selector__stat--hole xxdq-selector__stat-value">—</span></td>
            </tr>
            <tr>
              <th><?php echo esc_html($t['row_mold']); ?></th>
              <td><span class="xxdq-selector__stat--mold xxdq-selector__stat-value">—</span></td>
            </tr>
            <tr>
              <th><?php echo esc_html($t['row_base']); ?></th>
              <td><span class="xxdq-selector__stat--base xxdq-selector__stat-value">—</span></td>
            </tr>
            <tr>
              <th><?php echo esc_html($t['row_actual']); ?></th>
              <td><span class="xxdq-selector__stat--actual xxdq-selector__stat-value">—</span></td>
            </tr>
            <tr>
              <th><?php echo esc_html($t['row_conclusion']); ?></th>
              <td><span class="xxdq-selector__terminal-conclusion">—</span></td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- <?php echo $is_en ? 'Power information' : '功率信息'; ?> -->
      <div class="xxdq-selector__power-section">
        <h3><?php echo esc_html($t['sec_power']); ?></h3>
        <table class="xxdq-selector__power-table">
          <tbody>
            <tr>
              <th><?php echo esc_html($t['row_voltage']); ?></th>
              <td><span class="xxdq-selector__pv--voltage">—</span></td>
            </tr>
            <tr>
              <th><?php echo esc_html($t['row_phase']); ?></th>
              <td><span class="xxdq-selector__pv--phase">—</span></td>
            </tr>
            <tr>
              <th><?php echo esc_html($t['row_pf']); ?></th>
              <td><span class="xxdq-selector__pv--powerfactor">—</span></td>
            </tr>
            <tr>
              <th><?php echo esc_html($t['row_power']); ?></th>
              <td><span class="xxdq-selector__pv--power">—</span></td>
            </tr>
            <tr>
              <th><?php echo esc_html($t['row_target']); ?></th>
              <td><span class="xxdq-selector__pv--target">—</span></td>
            </tr>
            <tr>
              <th><?php echo esc_html($t['row_actual']); ?></th>
              <td><span class="xxdq-selector__pv--actual">—</span></td>
            </tr>
          </tbody>
        </table>
      </div>

    </div><!-- /.xxdq-selector__tables -->

    <!-- Warning strip (JS toggles is-visible) -->
    <div class="xxdq-selector__warning" role="alert">
      <strong><?php echo esc_html($t['warn_title']); ?></strong>
      <span class="xxdq-selector__warning-text"></span>
    </div>

    <!-- Correction factors -->
    <div class="xxdq-selector__factors">
      <strong><?php echo esc_html($t['factors']); ?></strong>
      <ul>
        <li><?php echo esc_html($t['f_temp']); ?> <strong class="xxdq-selector__factor--temp">—</strong></li>
        <li><?php echo esc_html($t['f_install']); ?> <strong class="xxdq-selector__factor--install">—</strong></li>
        <li><?php echo esc_html($t['f_material']); ?> <strong class="xxdq-selector__factor--material">—</strong></li>
      </ul>
    </div>

    <!-- Note strip -->
    <div class="xxdq-selector__note" style="display:none">
      <span class="xxdq-selector__note-text"></span>
    </div>

    <!-- CTA buttons -->
    <div class="xxdq-selector__cta">
      <a class="xxdq-selector__btn xxdq-selector__btn--accent xxdq-selector__inquiry-btn" href="<?php echo esc_url($inquiry_url); ?>"><?php echo esc_html($t['cta_inquiry']); ?></a>
      <a class="xxdq-selector__btn xxdq-selector__btn--ghost xxdq-selector__product-btn" href="#" style="display:none" rel="nofollow"><?php echo esc_html($t['cta_product']); ?></a>
      <a class="xxdq-selector__btn xxdq-selector__btn--ghost xxdq-selector__recalc-btn" href="#xxdq-selector-form"><?php echo esc_html($t['cta_recalc']); ?></a>
    </div>

  </div>

  <section class="xxdq-selector__seo">
    <h2><?php echo esc_html($t['seo_title']); ?></h2>
    <div><?php echo wp_kses_post($seo_intro); ?></div>

    <h3><?php echo esc_html($t['seo_formula']); ?></h3>
    <pre class="xxdq-selector__formula"><?php echo esc_html($seo_formula); ?></pre>

    <h3><?php echo esc_html($t['seo_faq']); ?></h3>
    <dl class="xxdq-selector__faq">
      <?php foreach ($seo_faqs as $row) :
        $q = isset($row['q']) ? (string) $row['q'] : '';
        $a = isset($row['a']) ? (string) $row['a'] : '';
        if ($q === '' && $a === '') continue; ?>
        <dt><?php echo esc_html($q); ?></dt>
        <dd><?php echo esc_html($a); ?></dd>
      <?php endforeach; ?>
    </dl>
  </section>

  <?php echo $schema_ld; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — JSON-LD is generated server-side and escaped inside the helper. ?>
</section>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Build Schema.org JSON-LD for the selector-tool page.
     * Emits two @graph entries:
     *   - SoftwareApplication (the calculator tool itself)
     *   - FAQPage (the 8 Q&A items)
     * The result is a single <script type="application/ld+json"> tag.
     * All dynamic fields are escaped; the wrapper is plain HTML.
     */
    /**
     * The UI string table for the selector tool.
     *
     * The Chinese entry holds the historical strings verbatim so the
     * Chinese page output does not change by a single byte. The English
     * entry carries the /en/ equivalents.
     *
     * @param bool $is_en Whether to return the English strings.
     * @return array<string,string>
     */
    private static function selector_strings($is_en) {
        if (!$is_en) {
            return [
                'hero_title'    => '铜鼻子/冷压端子选型计算器',
                'hero_sub'      => '输入导线截面、材质、环境温度，自动推荐合适的铜鼻子 / 冷压端子 / 预绝缘端子 / 铜铝过渡端子型号。',
                'form_title'    => '选型参数',
                'form_hint'     => '所有字段均需填写；不确定的项可按常规场景选择。',
                'area'          => '导线截面',
                'area_ph'       => '请选择截面',
                'material'      => '导线材质',
                'mat_copper'    => '铜芯',
                'mat_aluminum'  => '铝芯',
                'temp'          => '环境温度 (℃)',
                'install'       => '安装方式',
                'inst_air'      => '空气中敷设',
                'inst_pipe'     => '穿管敷设',
                'inst_buried'   => '直埋敷设',
                'type'          => '端子类型',
                'type_dt'       => 'DT 系列（铜鼻子）',
                'type_sc'       => 'SC 系列（冷压端子）',
                'type_rv'       => 'RV 系列（预绝缘端子）',
                'type_dtl'      => 'DTL 系列（铜铝过渡）',
                'voltage'       => '系统电压 (V)',
                'phase'         => '相数',
                'phase_three'   => '三相',
                'phase_single'  => '单相',
                'power'         => '负载功率 (kW)',
                'optional'      => '选填',
                'powerfactor'   => '功率因数 (cosφ)',
                'submit'        => '立即计算',
                'reset'         => '重置',
                'err_title'     => '无法匹配型号',
                'res_title'     => '推荐型号',
                'sec_terminal'  => '端子选型结果',
                'row_model'     => '推荐型号',
                'row_hole'      => '螺栓孔径',
                'row_mold'      => '压接模具',
                'row_base'      => '基准载流量',
                'row_actual'    => '修正后载流量',
                'row_conclusion'=> '选型结论',
                'sec_power'     => '功率信息',
                'row_voltage'   => '系统电压',
                'row_phase'     => '相数',
                'row_pf'        => '功率因数',
                'row_power'     => '负载功率',
                'row_target'    => '目标电流',
                'warn_title'    => '⚠️ 载流量不足',
                'factors'       => '修正系数明细：',
                'f_temp'        => '温度',
                'f_install'     => '安装方式',
                'f_material'    => '材质',
                'cta_inquiry'   => '立即询价',
                'cta_product'   => '查看该型号产品',
                'cta_recalc'    => '重新选型',
                'seo_title'     => '使用说明',
                'seo_formula'   => '计算公式',
                'seo_faq'       => '常见问题 (FAQ)',
                'seo_intro'     => '<p>本工具根据您提供的导线截面、负载功率（或反算）与工况参数,'
                    . '依据电气工程常用修正系数（GB/T 4703 / IEC 61238）推荐匹配的铜鼻子、冷压端子、'
                    . '预绝缘端子或铜铝过渡端子型号。计算结果仅供参考,正式采购前请与我司技术人员确认。</p>',
                'seo_formula_body' => "载流量修正:\n"
                    . "  实际载流量 = 基准载流量 × 温度修正系数 × 安装方式修正系数 × 材质修正系数\n\n"
                    . "功率 → 目标电流（用于按功率选型）:\n"
                    . "  单相:  I = P × 1000 / (U × cosφ)\n"
                    . "  三相:  I = P × 1000 / (√3 × U × cosφ)\n\n"
                    . "已知导线截面时，反算最大可持续功率:\n"
                    . "  单相:  P = U × I × cosφ / 1000\n"
                    . "  三相:  P = √3 × U × I × cosφ / 1000",
            ];
        }

        return [
            'hero_title'    => 'Cable Lug & Crimp Terminal Selection Calculator',
            'hero_sub'      => 'Enter the conductor cross-section, material and ambient temperature to get a recommended copper lug, crimp terminal, pre-insulated terminal or copper-to-aluminium transition terminal.',
            'form_title'    => 'Selection Parameters',
            'form_hint'     => 'All fields are required. If you are unsure about one of them, pick the most common scenario.',
            'area'          => 'Conductor Cross-section',
            'area_ph'       => 'Select a cross-section',
            'material'      => 'Conductor Material',
            'mat_copper'    => 'Copper',
            'mat_aluminum'  => 'Aluminium',
            'temp'          => 'Ambient Temperature (°C)',
            'install'       => 'Installation Method',
            'inst_air'      => 'In-air installation',
            'inst_pipe'     => 'In-conduit installation',
            'inst_buried'   => 'Direct burial',
            'type'          => 'Terminal Type',
            'type_dt'       => 'DT Series (Copper Lug)',
            'type_sc'       => 'SC Series (Crimp Terminal)',
            'type_rv'       => 'RV Series (Pre-insulated Terminal)',
            'type_dtl'      => 'DTL Series (Copper-to-Aluminium)',
            'voltage'       => 'System Voltage (V)',
            'phase'         => 'Phase',
            'phase_three'   => 'Three phase',
            'phase_single'  => 'Single phase',
            'power'         => 'Load Power (kW)',
            'optional'      => 'Optional',
            'powerfactor'   => 'Power Factor (cosφ)',
            'submit'        => 'Calculate',
            'reset'         => 'Reset',
            'err_title'     => 'No matching model',
            'res_title'     => 'Recommended Model',
            'sec_terminal'  => 'Terminal Selection Result',
            'row_model'     => 'Recommended Model',
            'row_hole'      => 'Stud Size',
            'row_mold'      => 'Crimp Die',
            'row_base'      => 'Base Ampacity',
            'row_actual'    => 'Corrected Ampacity',
            'row_conclusion'=> 'Conclusion',
            'sec_power'     => 'Power Information',
            'row_voltage'   => 'System Voltage',
            'row_phase'     => 'Phase',
            'row_pf'        => 'Power Factor',
            'row_power'     => 'Load Power',
            'row_target'    => 'Target Current',
            'warn_title'    => '⚠️ Insufficient ampacity',
            'factors'       => 'Correction factors:',
            'f_temp'        => 'Temperature',
            'f_install'     => 'Installation',
            'f_material'    => 'Material',
            'cta_inquiry'   => 'Get a Quote',
            'cta_product'   => 'View this model',
            'cta_recalc'    => 'Select Again',
            'seo_title'     => 'How to Use',
            'seo_formula'   => 'Formulas',
            'seo_faq'       => 'Frequently Asked Questions (FAQ)',
            'seo_intro'     => '<p>Based on the conductor cross-section, load power (or the reverse calculation) '
                . 'and the operating conditions you provide, this tool recommends a matching copper lug, crimp terminal, '
                . 'pre-insulated terminal or copper-to-aluminium transition terminal using the standard correction '
                . 'factors of electrical engineering (GB/T 4703 / IEC 61238). The result is for reference only — '
                . 'please confirm with our engineers before placing an order.</p>',
            'seo_formula_body' => "Ampacity correction:\n"
                . "  Corrected ampacity = Base ampacity × Temperature factor × Installation factor × Material factor\n\n"
                . "Power → target current (for selecting by power):\n"
                . "  Single phase:  I = P × 1000 / (U × cosφ)\n"
                . "  Three phase:   I = P × 1000 / (√3 × U × cosφ)\n\n"
                . "Back-calculating the maximum sustained power from a known cross-section:\n"
                . "  Single phase:  P = U × I × cosφ / 1000\n"
                . "  Three phase:   P = √3 × U × I × cosφ / 1000",
        ];
    }

    /**
     * The English FAQ for the selector tool.
     *
     * @return array<int,array{q:string,a:string}>
     */
    private static function selector_faqs_en() {
        return [
            ['q' => 'Why is the calculated result lower than the nominal rating?',
             'a' => 'Under real operating conditions the ambient temperature rises, heat dissipation inside a conduit is poor, and aluminium has a higher resistivity than copper — so the base ampacity has to be corrected accordingly.'],
            ['q' => 'Is DT-16 overkill for a 1.5 mm² conductor?',
             'a' => 'The tool always rounds up to the next standard size for safety margin. If you are cost-sensitive, contact our engineers and we can confirm a smaller size.'],
            ['q' => 'Why is the power in kW converted to current before selecting a terminal?',
             'a' => 'The terminal tables are indexed by base ampacity (amperes), so selection is fundamentally a current decision. System voltage, phase count and power factor determine the current that corresponds to a given power: I = P/(U·cosφ) for single phase and I = P/(√3·U·cosφ) for three phase — the standard conversions of electrical engineering.'],
            ['q' => 'Why does the tool still back-calculate from the cross-section after I entered the power?',
             'a' => 'The power field is optional. When left blank, the tool looks up the terminal table by conductor cross-section and back-calculates the maximum sustained power for that size from the corrected ampacity, so you can check your design margin.'],
            ['q' => 'Can I change the default power factor of 0.85?',
             'a' => 'Yes. Motor loads typically use 0.8–0.85, resistive loads (heating, incandescent lighting) use 1.0, and loads behind a variable-frequency drive can use 0.95 or above. You can enter any value between 0 and 1 in the form.'],
            ['q' => 'Why can copper and aluminium not be crimped directly together?',
             'a' => 'Direct copper-to-aluminium contact causes galvanic corrosion; the contact resistance rises rapidly and the joint heats up. A DTL series (copper-to-aluminium transition terminal) must be used for these applications.'],
            ['q' => 'Can the aluminium side of a DTL terminal be assembled and disassembled repeatedly?',
             'a' => 'Not recommended. A DTL terminal forms a gas-tight connection after crimping, and disassembly destroys the oxide layer — the surface has to be re-cleaned or the terminal replaced.'],
            ['q' => 'Can I order directly from the calculation result?',
             'a' => 'Yes. Click the "Get a Quote" button and the recommended model is carried over into the inquiry form automatically; you only need to add the quantity and the required delivery date.'],
        ];
    }

    /**
     * Build Schema.org JSON-LD for the selector-tool page.
     * Emits two @graph entries:
     *   - SoftwareApplication (the calculator tool itself)
     *   - FAQPage (the 8 Q&A items)
     * The result is a single <script type="application/ld+json"> tag.
     * All dynamic fields are escaped; the wrapper is plain HTML.
     */
    private static function build_selector_schema($page_id, array $faqs, $inquiry_url) {
        $is_en     = function_exists('xxdq_is_en') && xxdq_is_en();
        $page_url  = $page_id ? get_permalink($page_id) : home_url('/online-tools/');
        $site_name = $is_en ? 'Gengwen Electric' : get_bloginfo('name');
        $desc      = $is_en
            ? 'Free online cable lug and crimp terminal selection calculator. Enter the conductor cross-section, material and ambient temperature to get a recommended copper lug or crimp terminal model, the matching crimp die and the reference ampacity. 10 years of direct-manufacturing experience, CE / RoHS certified, ships within 24 hours.'
            : '免费在线铜鼻子选型计算器,输入导线截面、材质、环境温度,自动推荐合适的铜鼻子/冷压端子型号、压接模具及参考载流量。10年源头工厂,CE/RoHS认证,24小时出货。';

        // FAQPage: only emit entities that have both Q and A.
        $faq_entities = [];
        foreach ($faqs as $row) {
            $q = isset($row['q']) ? trim((string) $row['q']) : '';
            $a = isset($row['a']) ? trim((string) $row['a']) : '';
            if ($q === '' || $a === '') continue;
            $faq_entities[] = [
                '@type'      => 'Question',
                'name'       => $q,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $a,
                ],
            ];
        }

        $graph = [
            [
                '@type'            => 'SoftwareApplication',
                'name'             => $is_en ? 'Cable Lug & Crimp Terminal Selection Calculator' : '端子选型计算器',
                'headline'         => $is_en ? 'Online Copper Lug & Crimp Terminal Spec Lookup' : '铜鼻子 / 冷压端子规格在线查询',
                'url'              => $page_url,
                'applicationCategory' => 'UtilitiesApplication',
                'operatingSystem'  => 'Any (Web Browser)',
                'inLanguage'       => $is_en ? 'en-US' : 'zh-CN',
                'description'      => $desc,
                'offers'           => [
                    '@type'         => 'Offer',
                    'price'         => '0',
                    'priceCurrency' => 'CNY',
                ],
                'publisher'        => [
                    '@type' => 'Organization',
                    'name'  => $site_name,
                    'url'   => home_url('/'),
                ],
            ],
            [
                '@type'         => 'FAQPage',
                'url'           => $page_url,
                'mainEntity'    => $faq_entities,
            ],
        ];

        $payload = [
            '@context' => 'https://schema.org',
            '@graph'   => $graph,
        ];

        $json = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        return '<script type="application/ld+json">' . $json . '</script>' . "\n";
    }

    /* ── PDF AGGREGATOR ────────────────────────────────────── */
    public static function render_pdf_aggregator($block, $content = '', $is_preview = false, $post_id = 0) {
        return '<div class="xxdq-pdf-aggregator"><h2>技术资料下载</h2><p>技术资料陆续整理中,请稍后查看。</p></div>';
    }

    /* ── TECH DOCS ─────────────────────────────────────────── */
    public static function render_tech_docs($block, $content = '', $is_preview = false, $post_id = 0) {
        return '<div class="xxdq-tech-docs"><p>技术文档列表</p></div>';
    }

    /* ── ABOUT ─────────────────────────────────────────────── */
    public static function render_about($block, $content = '', $is_preview = false, $post_id = 0) {
        /* The English site renders an English version of this block. The
         * Chinese text below stays byte-identical for the Chinese site. */
        if (function_exists('xxdq_is_en') && xxdq_is_en()) {
            return '<div class="xxdq-about">
  <h2>About Us</h2>
  <p>We are a source factory specialised in the production and sales of electrical connectors. Our range covers copper lugs, crimp terminals, pre-insulated terminals and wire-end connectors, widely used in power distribution, telecommunications and industrial control. More than 10 years of industry experience, serving customers at home and abroad.</p>
</div>';
        }
        return '<div class="xxdq-about">
  <h2>关于我们</h2>
  <p>我们是一家专业从事电气连接件生产与销售的源头工厂,产品涵盖铜鼻子、冷压端子、预绝缘端子、接插件等,广泛应用于电力、通信、工业控制等领域。10年以上行业经验,服务客户遍及国内外。</p>
</div>';
    }

    /* ── CONTACT CARDS ─────────────────────────────────────── */
    public static function render_contact_cards($block, $content = '', $is_preview = false, $post_id = 0) {
        $is_en = function_exists('xxdq_is_en') && xxdq_is_en();

        /* The cards must show the SAME contact details as the footer and
         * the LocalBusiness JSON-LD — a page that lists "0577-XXXX"
         * while the footer lists the real number is a NAP inconsistency
         * and hurts local SEO. The shared helper is the single source of
         * truth. */
        $contact = function_exists('xxdq_company_contact')
            ? xxdq_company_contact()
            : [
                'address' => '浙江省乐清市柳市镇',
                'phone'   => '+86-18658733315',
                'email'   => '197995061@qq.com',
                'hours'   => '周一至周六 8:30-17:30',
            ];

        if ($is_en) {
            $cards = [
                ['icon' => '📍', 'title' => 'Address',        'body' => $contact['address']],
                ['icon' => '📞', 'title' => 'Phone',         'body' => $contact['phone']],
                ['icon' => '✉️', 'title' => 'Email',         'body' => $contact['email']],
                ['icon' => '🕐', 'title' => 'Business Hours','body' => $contact['hours']],
            ];
        } else {
            $cards = [
                ['icon' => '📍', 'title' => '地址',     'body' => $contact['address']],
                ['icon' => '📞', 'title' => '电话',    'body' => $contact['phone']],
                ['icon' => '✉️', 'title' => '邮箱',    'body' => $contact['email']],
                ['icon' => '🕐', 'title' => '工作时间','body' => $contact['hours']],
            ];
        }

        $heading = $is_en ? 'Contact Us' : '联系我们';
        $html = '<div class="xxdq-contact-cards"><h2>' . esc_html($heading) . '</h2><div class="xxdq-contact-cards__grid">';
        foreach ($cards as $c)
            $html .= sprintf('<div class="xxdq-contact-card"><h3>%s %s</h3><p>%s</p></div>',
                esc_html($c['icon']), esc_html($c['title']), esc_html($c['body']));
        return $html . '</div></div>';
    }

    /* ── INQUIRY FORM ─────────────────────────────────────── */
    public static function render_inquiry_form($block, $content = '', $is_preview = false, $post_id = 0) {
        $nonce = wp_nonce_field('xxdq_inquiry_submit', 'xxdq_inquiry_nonce', true, false);
        $is_en = function_exists('xxdq_is_en') && xxdq_is_en();

        // Show a "thanks" panel when the form was just submitted, so the
        // visitor gets visual confirmation instead of seeing the same
        // form again (which made it look like the submission failed).
        $thanks_html = '';
        if (function_exists('get_query_var')) {
            $submitted = get_query_var('submitted');
            if (!$submitted && isset($_GET['submitted'])) {
                $submitted = sanitize_text_field(wp_unslash($_GET['submitted']));
            }
            if ($submitted === '1') {
                $thanks_html = '<div class="xxdq-inquiry-form__success" role="status">'
                    . '<strong>' . esc_html(xxdq_t('inquiry.success')) . '</strong> '
                    . esc_html(xxdq_t('inquiry.success_msg'))
                    . '</div>';
            }
        }

        // Pre-fill "产品型号" from URL when arriving from a product page.
        // Product-page "立即询价" button uses ?model=<title>; read both
        // ?model= (current) and ?product= (legacy URL-encoded) as fallbacks.
        $prefill = '';
        if (isset($_GET['model'])) {
            $prefill = sanitize_text_field(wp_unslash($_GET['model']));
        } elseif (isset($_GET['product'])) {
            $prefill = sanitize_text_field(wp_unslash($_GET['product']));
        }
        $prefill_attr = $prefill !== ''
            ? sprintf(' value="%s"', esc_attr($prefill))
            : '';

        /* The English site submits an English inquiry. The labels below
         * come from the language catalog, so the form renders in English.
         * The backend handler stores the submission and flags it as an
         * English inquiry so the sales team reads it correctly. */
        if ($is_en) {
            return sprintf('<div class="xxdq-inquiry-form">
  <h2>%s</h2>
  %s
  <form class="xxdq-inquiry-form__form" method="POST" action="%s">
    %s
    <input type="hidden" name="action" value="xxdq_inquiry_submit">
    <input type="hidden" name="xxdq_inquiry_lang" value="en_US">
    <div class="xxdq-form-row">
      <label>%s <input type="text" name="company" required></label>
      <label>%s <input type="text" name="name" required></label>
    </div>
    <div class="xxdq-form-row">
      <label>%s <input type="tel" name="phone" required></label>
      <label>%s <input type="email" name="email"></label>
    </div>
    <div class="xxdq-form-row">
      <label>%s <input type="text" name="product" placeholder="%s"%s></label>
    </div>
    <label>%s
      <textarea name="message" rows="4" placeholder="%s" required></textarea>
    </label>
    <button type="submit" class="xxdq-form-submit">%s</button>
  </form>
</div>',
                esc_html(xxdq_t('inquiry.title')),
                $thanks_html,
                esc_url(admin_url('admin-post.php')),
                $nonce,
                esc_html(xxdq_t('inquiry.company')),
                esc_html(xxdq_t('inquiry.name')),
                esc_html(xxdq_t('inquiry.phone')),
                esc_html(xxdq_t('inquiry.email')),
                esc_html(xxdq_t('inquiry.model')),
                esc_attr(xxdq_t('inquiry.model_ph')),
                $prefill_attr,
                esc_html(xxdq_t('inquiry.message')),
                esc_attr(xxdq_t('inquiry.message_ph')),
                esc_html(xxdq_t('inquiry.submit'))
            );
        }

        return sprintf('<div class="xxdq-inquiry-form">
  <h2>询价单</h2>
  %s
  <form class="xxdq-inquiry-form__form" method="POST" action="%s">
    %s
    <input type="hidden" name="action" value="xxdq_inquiry_submit">
    <div class="xxdq-form-row">
      <label>公司名称 <input type="text" name="company" required></label>
      <label>联系人 <input type="text" name="name" required></label>
    </div>
    <div class="xxdq-form-row">
      <label>电话 <input type="tel" name="phone" required></label>
      <label>邮箱 <input type="email" name="email"></label>
    </div>
    <div class="xxdq-form-row">
      <label>产品型号 <input type="text" name="product" placeholder="如 DT-16"%s></label>
    </div>
    <label>需求描述
      <textarea name="message" rows="4" placeholder="请描述您需要的产品型号、数量、交期等" required></textarea>
    </label>
    <button type="submit" class="xxdq-form-submit">提交询价</button>
  </form>
</div>',
            $thanks_html,
            esc_url(admin_url('admin-post.php')),
            $nonce,
            $prefill_attr
        );
    }

    /* ── SERIES ACCORDION ──────────────────────────────────── */
    public static function render_series_accordion($block, $content = '', $is_preview = false, $post_id = 0) {
        return '<div class="xxdq-series-accordion"><p>系列详情(占位)</p></div>';
    }

    /* ── PRODUCT DETAIL ────────────────────────────────────── */
    public static function render_product_detail($block, $content = '', $is_preview = false, $post_id = 0) {
        if (!$post_id || get_post_type($post_id) !== 'product') {
            return '<p class="xxdq-empty">请在产品详情页使用此区块。</p>';
        }
        $specs = ['model','wire_range','hole_size','material','finish','color_code','voltage','temperature','certification','package','short_desc'];
        $labels = [
            'model' => '型号', 'wire_range' => '适用线径', 'hole_size' => '螺丝孔径',
            'material' => '材质', 'finish' => '表面处理', 'color_code' => '绝缘颜色',
            'voltage' => '额定电压', 'temperature' => '工作温度',
            'certification' => '认证', 'package' => '包装方式', 'short_desc' => '简介',
        ];
        $rows = '';
        foreach ($specs as $k) {
            $v = get_post_meta($post_id, $k, true);
            if (!empty($v))
                $rows .= sprintf('<tr><th>%s</th><td>%s</td></tr>', $labels[$k] ?? $k, esc_html(is_array($v) ? implode(',', $v) : $v));
        }
        $tech_img_id = get_post_meta($post_id, 'tech_image', true);
        $tech_img = $tech_img_id ? wp_get_attachment_image_url((int)$tech_img_id, 'large') : '';
        $real_img_id = get_post_meta($post_id, 'real_image', true);
        $real_img = $real_img_id ? wp_get_attachment_image_url((int)$real_img_id, 'large') : '';
        $pdf_id = get_post_meta($post_id, 'attachment', true);
        $pdf_url = $pdf_id ? wp_get_attachment_url((int)$pdf_id) : '';

        $specs_html = $rows ? "<table class='xxdq-product__specs'><caption>规格参数</caption><tbody>$rows</tbody></table>" : '';
        $tech_html = $tech_img ? "<figure class='xxdq-product__tech'><img src='".esc_url($tech_img)."' alt='技术图'/><figcaption>技术图</figcaption></figure>" : '';
        $real_html = $real_img ? "<figure class='xxdq-product__real'><img src='".esc_url($real_img)."' alt='实物图'/><figcaption>实物图</figcaption></figure>" : '';
        $pdf_html = $pdf_url ? "<a class='xxdq-btn xxdq-btn--pdf' href='".esc_url($pdf_url)."' target='_blank'>📄 下载技术手册 (PDF)</a>" : '';

        return sprintf('<div class="xxdq-product">
  <h1>%s</h1>%s%s%s%s
  <p class="xxdq-product__back"><a href="%s">← 返回产品列表</a></p>
</div>',
            esc_html(get_the_title($post_id)),
            $tech_html, $real_html, $specs_html, $pdf_html,
            esc_url(get_post_type_archive_link('product'))
        );
    }

    /* ── CATALOG HUB ───────────────────────────────────────── */
    public static function render_catalog_hub($block, $content = '', $is_preview = false, $post_id = 0) {
        return '<div class="xxdq-catalog-hub"><p>产品目录中心(占位)</p></div>';
    }

    /* ════════════════════════════════════════════════════════════
       Phase 9: Home-page rebuild blocks
       ════════════════════════════════════════════════════════════ */

    /**
     * Hero Slider — reads the `hero_banner` repeater from the ACF options page.
     * Each slide: background image + title + subtitle + button.
     * Falls back to a single placeholder slide if no banners are configured.
     */
    public static function render_hero_slider($block, $content = '', $is_preview = false, $post_id = 0) {
        $title   = function_exists('get_field') ? get_field('hero_eyebrow', 'option') : '';
        $slides  = [];

        if (function_exists('get_field')) {
            $rows = get_field('hero_banner', 'option');
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $img = isset($row['image']) ? (is_array($row['image']) ? ($row['image']['url'] ?? '') : (string) $row['image']) : '';
                    if (!$img) continue;
                    $slides[] = [
                        'image'    => $img,
                        'title'    => isset($row['title'])       ? (string) $row['title']       : '',
                        'subtitle' => isset($row['subtitle'])    ? (string) $row['subtitle']    : '',
                        'btn_text' => isset($row['button_text']) ? (string) $row['button_text'] : '',
                        'btn_url'  => isset($row['button_url'])  ? (string) $row['button_url']  : '',
                    ];
                }
            }
        }

        if (empty($slides)) {
            // Sensible fallback so the block always renders something on first activation.
            $hero_eyebrow = $title !== null && $title !== '' ? $title : '源头工厂 · 全产业链 · 现货直发';
            $site_name    = get_bloginfo('name') ?: '乐清市耕文电气有限公司';
            $slides = [
                [
                    'image'    => '',
                    'title'    => $site_name,
                    'subtitle' => $hero_eyebrow,
                    'btn_text' => '立即询价',
                    'btn_url'  => self::inquiry_url(),
                ],
            ];
        }

        $autoplay = 5000;
        $html  = '<div class="xxdq-hero-slider" data-autoplay="' . (int) $autoplay . '">';
        foreach ($slides as $i => $s) {
            $bg = $s['image'] ? ' style="background-image:url(' . esc_url($s['image']) . ')"' : ' style="background:linear-gradient(135deg,#0D1B2A,#1B263B);"';
            $btn = $s['btn_text']
                ? sprintf('<a href="%s" class="xxdq-hero-slider__btn">%s</a>', esc_url($s['btn_url']), esc_html($s['btn_text']))
                : '';
            $html .= sprintf(
                '<div class="xxdq-hero-slider__slide"%s>
                  <div class="xxdq-hero-slider__content">
                    <h2 class="xxdq-hero-slider__title">%s</h2>
                    <p class="xxdq-hero-slider__subtitle">%s</p>
                    %s
                  </div>
                </div>',
                $bg,
                esc_html($s['title']),
                esc_html($s['subtitle']),
                $btn
            );
        }
        // arrows
        $html .= '<button type="button" class="xxdq-hero-slider__arrow xxdq-hero-slider__arrow--prev" aria-label="上一张">‹</button>';
        $html .= '<button type="button" class="xxdq-hero-slider__arrow xxdq-hero-slider__arrow--next" aria-label="下一张">›</button>';
        // dots
        if (count($slides) > 1) {
            $html .= '<div class="xxdq-hero-slider__dots">';
            foreach ($slides as $i => $s) {
                $html .= sprintf('<button type="button" class="xxdq-hero-slider__dot" aria-label="第%d张"></button>', $i + 1);
            }
            $html .= '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Application Fields — 4-up (or 8-up) industry grid driven by the ACF options page.
     */
    public static function render_application_fields($block, $content = '', $is_preview = false, $post_id = 0) {
        $default_items = [
            ['icon' => '🚗', 'title' => '汽车行业',     'description' => '汽车线束,动力系统,电池连接,新能源汽车'],
            ['icon' => '🏠', 'title' => '家用电器',     'description' => '空调,冰箱,洗衣机,厨电控制板'],
            ['icon' => '⚙️', 'title' => '工业设备',     'description' => 'PLC 控制柜,变频器,工业自动化'],
            ['icon' => '🏗️', 'title' => '建筑电气',     'description' => '配电箱,照明系统,楼宇智能化'],
        ];

        $items = $default_items;
        if (function_exists('get_field')) {
            $acf_items = get_field('application_fields', 'option');
            if (is_array($acf_items) && !empty($acf_items)) {
                $items = [];
                foreach ($acf_items as $row) {
                    $items[] = [
                        'icon'        => isset($row['icon'])        ? (string) $row['icon']        : '🏢',
                        'title'       => isset($row['title'])       ? (string) $row['title']       : '',
                        'description' => isset($row['description']) ? (string) $row['description'] : '',
                    ];
                }
            }
        }

        $html  = '<section class="xxdq-app-fields">';
        $html .= '<div class="xxdq-app-fields__inner">';
        $html .= '<h2 class="xxdq-section-title">应用领域</h2>';
        $html .= '<div class="xxdq-app-fields__grid">';
        foreach ($items as $it) {
            $html .= sprintf(
                '<div class="xxdq-app-fields__card">
                  <div class="xxdq-app-fields__icon" aria-hidden="true">%s</div>
                  <h3 class="xxdq-app-fields__title">%s</h3>
                  <p class="xxdq-app-fields__desc">%s</p>
                </div>',
                esc_html($it['icon']),
                esc_html($it['title']),
                esc_html($it['description'])
            );
        }
        $html .= '</div></div></section>';
        return $html;
    }

    /**
     * News Section — pulls from the `news` CPT, count + more link from options.
     */
    public static function render_news_section($block, $content = '', $is_preview = false, $post_id = 0) {
        $count = 3;
        $more  = '/news/';
        if (function_exists('get_field')) {
            $opt_count = get_field('news_count', 'option');
            $opt_more  = get_field('news_more_link', 'option');
            if ($opt_count) $count = (int) $opt_count;
            if ($opt_more)  $more  = (string) $opt_more;
        }
        $count = max(1, $count);

        if (function_exists('xxdq_get_recent_news')) {
            $q = xxdq_get_recent_news($count);
        } else {
            $q = new WP_Query([
                'post_type'      => 'news',
                'post_status'    => 'publish',
                'posts_per_page' => $count,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'no_found_rows'  => true,
            ]);
        }

        $html  = '<section class="xxdq-news-section">';
        $html .= '<div class="xxdq-news-section__inner">';
        $html .= '<h2 class="xxdq-section-title">新闻动态</h2>';
        $html .= '<div class="xxdq-news-section__grid">';

        if (!$q->have_posts()) {
            $html .= '<p class="xxdq-news-section__empty">暂无新闻,敬请期待。</p>';
        } else {
            while ($q->have_posts()) {
                $q->the_post();
                $pid     = get_the_ID();
                $title   = get_the_title();
                $excerpt = has_excerpt($pid) ? get_the_excerpt() : wp_trim_words(wp_strip_all_tags(get_the_content()), 28, '…');
                $date    = get_the_date('Y-m-d');
                $url     = get_permalink();
                $html .= sprintf(
                    '<article class="xxdq-news-section__card">
                      <div class="xxdq-news-section__date"><time datetime="%s">%s</time></div>
                      <h3 class="xxdq-news-section__title"><a href="%s">%s</a></h3>
                      <p class="xxdq-news-section__excerpt">%s</p>
                      <a class="xxdq-news-section__more" href="%s">阅读全文 →</a>
                    </article>',
                    esc_attr($date),
                    esc_html($date),
                    esc_url($url),
                    esc_html($title),
                    esc_html($excerpt),
                    esc_url($url)
                );
            }
            wp_reset_postdata();
        }

        $html .= '</div>';
        if ($more) {
            $html .= sprintf(
                '<div class="xxdq-news-section__footer"><a class="xxdq-news-section__viewmore" href="%s">查看更多 →</a></div>',
                esc_url($more)
            );
        }
        $html .= '</div></section>';
        return $html;
    }
}

// Auto-bootstrap
XXDQ_Blocks_Render::instance();
