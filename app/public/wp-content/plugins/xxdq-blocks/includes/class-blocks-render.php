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
        if (isset(self::$current_attrs[$key])) return self::$current_attrs[$key];
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, $key, true) ?: $default;
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
        $eyebrow = self::val('hero_eyebrow', $post_id, '源头工厂 · 全产业链 · 现货直发');
        $subtitle = self::val('hero_subtitle', $post_id, '铜鼻子 · 冷压端子 · 预绝缘端子 · 接插件 · 500+ 规格 · CE/RoHS/ISO9001 认证 · 24小时出货');
        $cta_text = self::val('hero_cta_text', $post_id, '立即询价');
        $cta_url  = self::val('hero_cta_url', $post_id, '/询价单/');
        $sec_cta_text = self::val('hero_secondary_cta_text', $post_id, '浏览产品');
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
            esc_html(get_bloginfo('name')),
            esc_html($subtitle),
            $actions
        );
    }

    /* ── STATS BAR ─────────────────────────────────────────── */
    public static function render_stats_bar($block, $content = '', $is_preview = false, $post_id = 0) {
        $items = [
            ['value' => '500+', 'label' => '产品规格'],
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
        $cats = get_terms([
            'taxonomy'   => 'product_category',
            'hide_empty' => false,
            'orderby'    => 'menu_order',
            'order'      => 'ASC',
        ]);
        if (is_wp_error($cats) || empty($cats)) {
            // Fallback for first install before categories exist.
            $cats = [
                (object)['name' => '铜鼻子',     'slug' => 'copper',         'description' => 'T系列铜管端子, DTG系列'],
                (object)['name' => '冷压端子',   'slug' => 'cold',           'description' => 'SC系列冷压端头, OT开口鼻'],
                (object)['name' => '预绝缘端子', 'slug' => 'preinsulated',   'description' => 'VE管型, E系列预绝缘'],
                (object)['name' => '接插件',     'slug' => 'connector',      'description' => 'UK/EUK 轨道式接线端子'],
            ];
        }

        $html = sprintf('<div class="xxdq-category-grid xxdq-category-grid--%s">', esc_attr($style));
        foreach ($cats as $cat) {
            $link = is_object($cat) && isset($cat->term_id)
                ? get_term_link($cat)
                : ('/' . rawurlencode($cat->slug) . '/');
            if (is_wp_error($link)) { continue; }
            $name = is_object($cat) ? $cat->name : $cat['name'];
            $desc = is_object($cat) ? $cat->description : ($cat['description'] ?? '');
            $html .= sprintf('<a class="xxdq-category-card" href="%s">
  <h3 class="xxdq-category-card__name">%s</h3>
  <p class="xxdq-category-card__desc">%s</p>
  <span class="xxdq-category-card__arrow">→</span>
</a>', esc_url($link), esc_html($name), esc_html($desc));
        }
        $html .= '</div>';
        return $html;
    }

    /* ── HOT PRODUCTS ────────────────────────────────────────── */
    public static function render_hot_products($block, $content = '', $is_preview = false, $post_id = 0) {
        $q = new WP_Query(['post_type' => 'product', 'posts_per_page' => 8, 'post_status' => 'publish', 'orderby' => 'rand']);
        if (!$q->have_posts()) return '<p class="xxdq-empty">暂无产品。</p>';
        $html = '<div class="xxdq-hot-products"><h2 class="xxdq-section-title">热门产品</h2><div class="xxdq-product-grid">';
        while ($q->have_posts()) { $q->the_post();
            $img = has_post_thumbnail() ? get_the_post_thumbnail(get_the_ID(), 'medium') : '<span class="xxdq-product-card__noimg">无图</span>';
            $html .= sprintf('<a class="xxdq-product-card" href="%s">%s<h3>%s</h3></a>',
                esc_url(get_permalink()), $img, esc_html(get_the_title()));
        }
        wp_reset_postdata();
        return $html . '</div></div>';
    }

    /* ── PROCESS STEPS ──────────────────────────────────────── */
    public static function render_process_steps($block, $content = '', $is_preview = false, $post_id = 0) {
        $steps = ['需求确认','选型指导','报价打样','批量生产','质检出货'];
        $html = '<div class="xxdq-process-steps"><h2 class="xxdq-section-title">合作流程</h2><ol class="xxdq-process-steps__list">';
        foreach ($steps as $i => $s)
            $html .= sprintf('<li class="xxdq-process-steps__item"><span class="xxdq-process-steps__num">%d</span><span class="xxdq-process-steps__label">%s</span></li>', $i+1, esc_html($s));
        return $html . '</ol></div>';
    }

    /* ── WHY US ─────────────────────────────────────────────── */
    public static function render_why_us($block, $content = '', $is_preview = false, $post_id = 0) {
        // Phase 9: prefer ACF Options Page data; fall back to defaults.
        $default_items = [
            ['icon' => '🏭', 'title' => '源头工厂', 'desc' => '自有工厂,成本可控,品质稳定'],
            ['icon' => '📦', 'title' => '海量库存', 'desc' => '500+ 规格现货,24小时快速出货'],
            ['icon' => '✅', 'title' => '权威认证', 'desc' => 'CE/RoHS/ISO9001 认证,出口品质'],
            ['icon' => '🔧', 'title' => '定制灵活', 'desc' => '支持非标定制,小批量也欢迎'],
        ];
        $items = $default_items;
        if (function_exists('get_field')) {
            $acf_items = get_field('why_us_items', 'option');
            if (is_array($acf_items) && !empty($acf_items)) {
                $items = [];
                foreach ($acf_items as $row) {
                    $items[] = [
                        'icon' => isset($row['icon'])        ? (string) $row['icon']        : '',
                        'title' => isset($row['title'])      ? (string) $row['title']      : '',
                        'desc'  => isset($row['description'])? (string) $row['description']: '',
                    ];
                }
            }
        }
        $html = '<div class="xxdq-why-us"><h2 class="xxdq-section-title">为什么选择我们</h2><div class="xxdq-why-us__grid">';
        foreach ($items as $it)
            $html .= sprintf('<div class="xxdq-why-us__item"><div class="xxdq-why-us__icon">%s</div><h3>%s</h3><p>%s</p></div>',
                esc_html($it['icon']), esc_html($it['title']), esc_html($it['desc']));
        return $html . '</div></div>';
    }

    /* ── CTA ────────────────────────────────────────────────── */
    public static function render_cta($block, $content = '', $is_preview = false, $post_id = 0) {
        return '<div class="xxdq-cta">
  <h2>需要报价或技术支持?</h2>
  <p>联系我们,1小时内响应您的需求</p>
  <a href="/询价单/" class="xxdq-cta__btn">立即询价 →</a>
</div>';
    }

    /* ── CATEGORY ARCHIVE ──────────────────────────────────── */
    public static function render_category_archive($block, $content = '', $is_preview = false, $post_id = 0) {
        $cat_id = (int)self::val('ca_category', $post_id, 0);
        $page = $cat_id ? get_post($cat_id) : null;
        $title = $page ? $page->post_title : '产品列表';
        $q = new WP_Query(['post_type' => 'product', 'posts_per_page' => -1, 'post_status' => 'publish']);
        if (!$q->have_posts()) return '<p class="xxdq-empty">此分类暂无产品。</p>';
        $html = sprintf('<div class="xxdq-category-archive"><h2 class="xxdq-section-title">%s</h2><div class="xxdq-product-grid">', esc_html($title));
        while ($q->have_posts()) { $q->the_post();
            $img = has_post_thumbnail() ? get_the_post_thumbnail(get_the_ID(), 'medium') : '<span class="xxdq-product-card__noimg">无图</span>';
            $html .= sprintf('<a class="xxdq-product-card" href="%s">%s<h3>%s</h3></a>',
                esc_url(get_permalink()), $img, esc_html(get_the_title()));
        }
        wp_reset_postdata();
        return $html . '</div></div>';
    }

    /* ── SELECTOR TOOL ─────────────────────────────────────── */
    public static function render_selector_tool($block, $content = '', $is_preview = false, $post_id = 0) {
        return '<div class="xxdq-selector-tool">
  <h2>产品选型工具</h2>
  <p>根据您的线径、孔径等参数,快速匹配适合的端子型号。</p>
  <a href="/技术资料/" class="xxdq-cta__btn">查看技术资料 →</a>
</div>';
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
        return '<div class="xxdq-about">
  <h2>关于我们</h2>
  <p>我们是一家专业从事电气连接件生产与销售的源头工厂,产品涵盖铜鼻子、冷压端子、预绝缘端子、接插件等,广泛应用于电力、通信、工业控制等领域。10年以上行业经验,服务客户遍及国内外。</p>
</div>';
    }

    /* ── CONTACT CARDS ─────────────────────────────────────── */
    public static function render_contact_cards($block, $content = '', $is_preview = false, $post_id = 0) {
        $cards = [
            ['icon' => '📍', 'title' => '地址',     'body' => '浙江省乐清市柳市镇'],
            ['icon' => '📞', 'title' => '电话',    'body' => '0577-XXXXXXXX'],
            ['icon' => '✉️', 'title' => '邮箱',    'body' => 'info@xxdqexample.com'],
            ['icon' => '🕐', 'title' => '工作时间','body' => '周一至周六 8:30-17:30'],
        ];
        $html = '<div class="xxdq-contact-cards"><h2>联系我们</h2><div class="xxdq-contact-cards__grid">';
        foreach ($cards as $c)
            $html .= sprintf('<div class="xxdq-contact-card"><h3>%s %s</h3><p>%s</p></div>',
                esc_html($c['icon']), esc_html($c['title']), esc_html($c['body']));
        return $html . '</div></div>';
    }

    /* ── INQUIRY FORM ─────────────────────────────────────── */
    public static function render_inquiry_form($block, $content = '', $is_preview = false, $post_id = 0) {
        $nonce = wp_nonce_field('xxdq_inquiry_submit', 'xxdq_inquiry_nonce', true, false);
        return sprintf('<div class="xxdq-inquiry-form">
  <h2>询价单</h2>
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
      <label>产品型号 <input type="text" name="product" placeholder="如 DT-16"></label>
    </div>
    <label>需求描述
      <textarea name="message" rows="4" placeholder="请描述您需要的产品型号、数量、交期等"></textarea>
    </label>
    <button type="submit" class="xxdq-form-submit">提交询价</button>
  </form>
</div>',
            esc_url(admin_url('admin-post.php')),
            $nonce
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
            $site_name    = get_bloginfo('name') ?: 'XX电气';
            $slides = [
                [
                    'image'    => '',
                    'title'    => $site_name,
                    'subtitle' => $hero_eyebrow,
                    'btn_text' => '立即询价',
                    'btn_url'  => '/询价单/',
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
