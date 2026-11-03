<?php
/**
 * Plugin Name: 乐清市耕文电气有限公司 Theme
 * Description: Custom site theme plugin for 乐清市耕文电气有限公司 (gengwen-electric) — generates all custom homepage sections and styles.
 * Version: 2.2.0
 */

if (!defined('ABSPATH')) exit;

define('XXDQ_THEME_DIR', plugin_dir_path(__FILE__));
define('XXDQ_THEME_URL', plugin_dir_url(__FILE__));
define('XXDQ_THEME_VERSION', '2.6.1');

/* ── 0. I18N LAYER (English site under /en/) ────────────────
 * The language context layer must load before anything else so that
 * `xxdq_t()` and `xxdq_is_en()` are available to every other module.
 *
 * Load order inside the layer:
 *   i18n.php           — language detection + translation lookup
 *   lang/content.php  — parallel content objects (EN posts/terms/meta)
 *   lang/translator.php — Chinese → English content translation
 *   lang/router.php   — /en/... URL routing
 */
require_once XXDQ_THEME_DIR . 'includes/i18n.php';
require_once XXDQ_THEME_DIR . 'includes/lang/content.php';
require_once XXDQ_THEME_DIR . 'includes/lang/translator.php';
require_once XXDQ_THEME_DIR . 'includes/lang/cat-desc.php';
require_once XXDQ_THEME_DIR . 'includes/lang/router.php';
require_once XXDQ_THEME_DIR . 'includes/lang/redirects.php';
require_once XXDQ_THEME_DIR . 'includes/lang/seo.php';
require_once XXDQ_THEME_DIR . 'includes/lang/product-body.php';
require_once XXDQ_THEME_DIR . 'includes/lang/catalogue-stats.php';
require_once XXDQ_THEME_DIR . 'includes/lang/auto-sync.php';
require_once XXDQ_THEME_DIR . 'includes/lang/sitemap.php';
require_once XXDQ_THEME_DIR . 'includes/lang/sitemap-product.php';

/* ── 0.1 PIM ACF fields (实物图 / 描述图 / PDF) ─────────────
 * The PIM plugin owns these field-group definitions; we load them
 * here because the theme is guaranteed to load after ACF and before
 * admin requests, so the "实物图" upload metabox is present on every
 * product edit screen.
 */
add_action('plugins_loaded', function () {
    $pim_acf = WP_PLUGIN_DIR . '/xxdq-pim/includes/class-pim-acf-fields.php';
    if (file_exists($pim_acf)) {
        require_once $pim_acf;
        XXDQ_PIM_ACF_Fields::instance();
    }
}, 21);

/* ── 1. FRONT-END ASSETS ───────────────────────────────────── */
add_action('wp_enqueue_scripts', function () {
    $base = XXDQ_THEME_URL;

    // Google Fonts
    wp_enqueue_style(
        'xxdq-fonts',
        'https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@300;400;500;600;700&family=Roboto:wght@400;500;700&display=swap',
        [], null
    );

    // Main stylesheet
    wp_enqueue_style('xxdq-main', $base . '/assets/css/main.css', [], XXDQ_THEME_VERSION);
    wp_enqueue_style('xxdq-blocks-css', $base . '/assets/css/blocks.css', ['xxdq-main'], XXDQ_THEME_VERSION);

    // Main JS
    wp_enqueue_script('xxdq-main', $base . '/assets/js/main.js', [], XXDQ_THEME_VERSION, true);
});

/* ── 1.5 ADMIN: Category description bulk editor ─────────── */
require_once XXDQ_THEME_DIR . 'includes/cat-desc-editor.php';

/* ── 2. GLOBAL TOP NAV ────────────────────────────────────── */
/**
 * Render the top navigation bar with dropdown on "产品中心".
 * Hooked to generate_before_header (GeneratePress theme).
 * Product categories are pulled dynamically from the `product_category`
 * taxonomy via get_terms(), so the dropdown auto-updates when categories
 * are added/removed in WordPress admin.
 */
add_action('generate_before_header', function () {
    // Pull top-level product categories (one per row, with sub-categories nested).
    $product_cats = get_terms([
        'taxonomy'   => 'product_category',
        'hide_empty' => false,   // show even empty top-level so newly-created ones appear
        'parent'     => 0,       // only top-level
        'orderby'    => 'menu_order',
        'order'      => 'ASC',
        /* The English site keeps its own parallel terms (linked via
         * the `xxdq_zh_term_id` meta). Without this filter the Chinese
         * navigation would list the English categories as well. */
        'meta_query' => [
            'relation' => 'OR',
            [
                'key'     => 'xxdq_zh_term_id',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key'     => 'xxdq_zh_term_id',
                'compare' => 'EXISTS',
            ],
        ],
    ]);
    if (is_wp_error($product_cats)) {
        $product_cats = [];
    }

    /* Keep only the terms that belong to the CURRENT language. An
     * ENGLISH term is the one that carries the `xxdq_zh_term_id` meta
     * (pointing back at its Chinese counterpart). */
    $product_cats = array_values(array_filter($product_cats, function ($t) {
        $is_en_term = xxdq_is_en_term($t->term_id);
        return xxdq_is_en() ? $is_en_term : !$is_en_term;
    }));

    // For each top-level category, fetch its sub-categories (二级分类).
    // Using term cache (1 query per top-level) is fine for 13 terms.
    $product_cats_with_subs = [];
    foreach ($product_cats as $top) {
        $subs = get_terms([
            'taxonomy'   => 'product_category',
            'hide_empty' => false,
            'parent'     => (int) $top->term_id,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]);
        if (is_wp_error($subs)) $subs = [];
        /* Keep only the sub-categories of the current language. */
        $subs = array_values(array_filter($subs, function ($t) {
            $is_en_term = xxdq_is_en_term($t->term_id);
            return xxdq_is_en() ? $is_en_term : !$is_en_term;
        }));
        $product_cats_with_subs[] = ['top' => $top, 'subs' => $subs];
    }

    // Active-state detection for static pages only (关于我们 / 技术资料 / 联系我们).
    // get_queried_object() can return a WP_Post, WP_Term, WP_Post_Type, or other —
    // only treat `post_name` as valid when we actually have a post.
    $active_slug = '';
    $qobj = get_queried_object();
    if (is_object($qobj) && isset($qobj->post_name)) {
        $active_slug = (string) $qobj->post_name;
    }

    // Active-state for product section:
    //  - product archive          -> highlight "产品中心" toggle
    //  - product_category term     -> highlight that term in the dropdown
    $on_product_archive = is_post_type_archive('product') || is_tax('product_category');
    $current_term_id    = 0;
    if (is_tax('product_category')) {
        $current_term_id = $qobj && isset($qobj->term_id) ? (int) $qobj->term_id : 0;
    }
?>
<header class="xxp-nav" id="xxp-nav">
  <div class="xxdq-container xxp-nav__inner">
    <a href="<?php echo esc_url(function_exists('xxdq_home_url') ? xxdq_home_url() : home_url('/')); ?>" class="xxp-nav__logo" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?>">
      <?php if (function_exists('has_custom_logo') && has_custom_logo()):
        // Render the logo directly so we control width/height via inline style.
        // Bypasses the_custom_logo() (which wraps <img> in its own <a> and emits
        // an invalid nested-anchor and an aggressive srcset/sizes combo that's
        // hard to override from CSS). Inline style beats any !important in our
        // stylesheet and stops srcset from laying the logo out at 470px wide.
        $logo_id   = get_theme_mod('custom_logo');
        $logo_meta = $logo_id ? wp_get_attachment_image_src($logo_id, 'full') : null;
        if ($logo_id && $logo_meta):
          $logo_url    = $logo_meta[0];
          $logo_w_src  = (int) $logo_meta[1];
          $logo_h_src  = (int) $logo_meta[2];
          // Aim for 24px tall in the nav — auto-scale width by aspect ratio,
          // also cap width at 140px so a very wide logo can't blow out the bar.
          $render_h = 24;
          $render_w = $logo_w_src && $logo_h_src ? round($render_h * ($logo_w_src / $logo_h_src)) : $render_h;
          if ($render_w > 140) {
            $render_w = 140;
            $render_h = round(140 * ($logo_h_src / max(1, $logo_w_src)));
          }
          // SEO: always use the full brand name + category keyword in the
          // alt, regardless of what the WP media library has stored. The
          // generic initials ("GWWLY") that Customizer hands back are useless
          // for image search ranking and Chinese SERPs.
          $logo_alt = xxdq_t('brand.alt');
          printf(
            '<img src="%s" alt="%s" class="custom-logo xxp-nav__logo-img" width="%d" height="%d" style="height:%dpx;width:%dpx;max-height:%dpx;max-width:%dpx;object-fit:contain;display:block;" decoding="async" />',
            esc_url($logo_url),
            esc_attr($logo_alt),
            (int) $render_w,
            (int) $render_h,
            (int) $render_h,
            (int) $render_w,
            (int) $render_h,
            (int) $render_w
          );
          // Wordmark text + tagline sit next to the image so the brand name
          // is in the most weighted spot on the page (top nav). Image alt +
          // visible text give duplicate-but-consistent signals, which Google
          // handles fine and actually rewards for branded queries.
          ?>
          <span class="xxp-nav__logo-text"><?php echo esc_html(xxdq_t('brand.name')); ?><small><?php echo esc_html(xxdq_t('brand.tagline')); ?></small></span>
          <?php
        endif;
      else: ?>
        <span class="xxp-nav__logo-mark"><?php echo esc_html(xxdq_t('brand.mark')); ?></span>
        <span class="xxp-nav__logo-text"><?php echo esc_html(xxdq_t('brand.name')); ?><small><?php echo esc_html(xxdq_t('brand.tagline')); ?></small></span>
      <?php endif; ?>
    </a>
    <nav class="xxp-nav__menu">
      <a href="<?php echo esc_url(xxdq_url('/')); ?>" class="<?php echo is_front_page() ? 'active' : ''; ?>"><?php echo esc_html(xxdq_t('nav.home')); ?></a>
      <div class="xxp-nav__has-sub">
        <a href="<?php echo esc_url(xxdq_url('/product/')); ?>" class="xxp-nav__toggle <?php echo $on_product_archive ? 'active' : ''; ?>">
          <?php echo esc_html(xxdq_t('nav.products')); ?> <span class="xxp-nav__caret">▼</span>
        </a>
        <div class="xxp-nav__dropdown xxp-nav__dropdown--mega">
          <?php foreach ($product_cats_with_subs as $i => $row): ?>
            <?php
                $top   = $row['top'];
                $subs  = $row['subs'];
                $tlink = xxdq_term_link($top);
                if (is_wp_error($tlink) || !$tlink) continue;
                $is_current_top    = ((int) $top->term_id === $current_term_id);
                $has_subs          = !empty($subs);
            ?>
            <div class="xxp-nav__dropdown-col <?php echo $i < 4 ? 'xxp-nav__dropdown-col--row1' : 'xxp-nav__dropdown-col--row2'; ?>">
              <a href="<?php echo esc_url($tlink); ?>" class="xxp-nav__dropdown-cat <?php echo $is_current_top ? 'active' : ''; ?>">
                <?php echo esc_html(xxdq_term_name($top)); ?>
                <span class="xxp-nav__dropdown-count">(<?php echo (int) $top->count; ?>)</span>
              </a>
              <?php if ($has_subs): ?>
                <ul class="xxp-nav__dropdown-subs">
                  <?php foreach ($subs as $sub): ?>
                    <?php
                        $slink = xxdq_term_link($sub);
                        if (is_wp_error($slink) || !$slink) continue;
                        $is_current_sub = ((int) $sub->term_id === $current_term_id);
                    ?>
                    <li>
                      <a href="<?php echo esc_url($slink); ?>" class="<?php echo $is_current_sub ? 'active' : ''; ?>">
                        <?php echo esc_html(xxdq_term_name($sub)); ?>
                      </a>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
          <div class="xxp-nav__dropdown-foot">
            <a href="<?php echo esc_url(xxdq_url('/product/')); ?>" class="xxp-nav__dropdown-all"><?php echo esc_html(xxdq_t('nav.browse_all')); ?></a>
          </div>
        </div>
      </div>
      <a href="<?php echo esc_url(xxdq_url('/cable-lug-calculator/')); ?>" class="<?php echo ($active_slug === 'cable-lug-calculator') ? 'active' : ''; ?>"><?php echo esc_html(xxdq_t('nav.calculator')); ?></a>
      <a href="<?php echo esc_url(xxdq_url('/news/')); ?>" class="<?php echo ($active_slug === 'news') ? 'active' : ''; ?>"><?php echo esc_html(xxdq_t('nav.news')); ?></a>
      <a href="<?php echo esc_url(xxdq_permalink(15)); ?>" class="<?php echo ($active_slug === 'about-us') ? 'active' : ''; ?>"><?php echo esc_html(xxdq_t('nav.about')); ?></a>
      <a href="<?php echo esc_url(xxdq_permalink(16)); ?>" class="<?php echo ($active_slug === 'contact-us') ? 'active' : ''; ?>"><?php echo esc_html(xxdq_t('nav.contact')); ?></a>
    </nav>
    <div class="xxp-nav__lang">
      <?php
        /* Language switcher — the ONLY way for a visitor to reach the
         * other language. Without it the English site is unreachable
         * except by typing /en/ by hand.
         *
         * `XXDQ_I18n::switch_url()` swaps the /en/ prefix of the current
         * URL, so the visitor lands on the SAME page in the other
         * language (/product/copper-lug/ ⇄ /en/product/copper-lug/).
         *
         * The label is always shown in the TARGET language ("English" on
         * the Chinese site, "中文" on the English site) — that is the
         * universal convention and avoids a "Chinese label on the
         * Chinese site" no-op link. */
        $xxdq_target_lang = xxdq_is_en() ? 'zh_CN' : 'en_US';
        $xxdq_target_url  = class_exists('XXDQ_I18n') ? XXDQ_I18n::switch_url($xxdq_target_lang) : home_url('/');
        $xxdq_target_text = xxdq_is_en() ? '中文' : 'English';
      ?>
      <a class="xxp-nav__lang-link" href="<?php echo esc_url($xxdq_target_url); ?>"
         hreflang="<?php echo esc_attr($xxdq_target_lang === 'en_US' ? 'en' : 'zh-Hans'); ?>"
         lang="<?php echo esc_attr($xxdq_target_lang === 'en_US' ? 'en' : 'zh-Hans'); ?>"
         title="<?php echo esc_attr($xxdq_target_text); ?>">
        <span class="xxp-nav__lang-icon" aria-hidden="true">🌐</span>
        <span class="xxp-nav__lang-text"><?php echo esc_html($xxdq_target_text); ?></span>
      </a>
    </div>
    <div class="xxp-nav__cta">
      <a href="<?php echo esc_url(xxdq_permalink(17)); ?>" class="xxdq-btn xxdq-btn--primary xxdq-btn--sm"><?php echo esc_html(xxdq_t('nav.inquiry')); ?></a>
    </div>
    <button class="xxp-nav__burger" id="xxp-nav-burger" aria-label="<?php echo esc_attr(xxdq_t('nav.menu')); ?>">☰</button>
  </div>
</header>
<?php
}, 1);

/* ── 3. CUSTOM LOGO ───────────────────────────────────────── *
 * `add_theme_support()` MUST run inside `after_setup_theme`. Calling it
 * at plugin load time is silently ignored by WordPress, which is why the
 * Customizer / Site Identity section never showed this plugin's logo
 * field even though the line looked correct. Hooked properly here.
 */
add_action('after_setup_theme', function () {
    add_theme_support('custom-logo', [
        'height'      => 60,
        'width'       => 200,
        'flex-height' => true,
        'flex-width'  => true,
    ]);
});

/* ── 4. CUSTOM FOOTER (Phase 9) ───────────────────────────── *
 * Render a 3-column footer driven by the ACF options page.
 * Hooked to `generate_before_footer` so it appears ABOVE the
 * default GeneratePress footer (we want to replace the GP
 * footer, not stack on top of it).
 */
add_action('generate_before_footer', function () {
    // Defaults — used when ACF is not active OR no options set.
    $brand_intro = xxdq_is_en()
        ? 'Gengwen Electric — a direct manufacturer with 10+ years of industry experience. Products certified to CE / RoHS / ISO9001, export-grade quality, shipped from stock.'
        : '乐清市耕文电气有限公司 — 源头工厂,10年+ 行业经验,产品通过 CE/RoHS/ISO9001 认证,出口品质,现货直发。';
    $main_products = xxdq_is_en()
        ? 'Copper Lug · Crimp Terminal · Pre-insulated Terminal · Connector · Cable Accessory · Power Fitting'
        : '铜鼻子 · 冷压端子 · 预绝缘端子 · 接插件 · 线缆附件 · 电力金具';
    $product_archive = xxdq_url('/product/');
    $quick_links = [
        ['label' => xxdq_t('nav.home'),      'url' => xxdq_url('/')],
        ['label' => xxdq_t('nav.products'),  'url' => $product_archive],
        ['label' => xxdq_t('nav.calculator'),'url' => xxdq_url('/cable-lug-calculator/')],
        ['label' => xxdq_t('nav.about'),     'url' => xxdq_permalink(15)],
        ['label' => xxdq_t('nav.news'),      'url' => xxdq_url('/news/')],
        ['label' => xxdq_t('nav.contact'),   'url' => xxdq_permalink(16)],
    ];
    $main_products  = xxdq_is_en()
        ? 'Copper Lug · Crimp Terminal · Pre-insulated Terminal · Connector · Cable Accessory · Power Fitting'
        : '铜鼻子 · 冷压端子 · 预绝缘端子 · 接插件 · 线缆附件 · 电力金具';
    $copyright      = sprintf(xxdq_t('footer.copyright'), date('Y'));
    $icp            = '';

    // On the English site the ACF options are Chinese-only, so the
    // English copy comes from the language catalog instead.
    $is_en = xxdq_is_en();

    // Pull from ACF Options Page if available.
    if (function_exists('get_field')) {
        $g = function ($key, $default) {
            $v = get_field($key, 'option');
            return ($v === null || $v === '') ? $default : $v;
        };
        $opt_brand   = $g('footer_brand_intro', '');
        $opt_quick   = $g('footer_quick_links', null);
        $opt_prods   = $g('footer_main_products', '');
        $opt_copy    = $g('copyright_text', '');
        $opt_icp     = $g('icp_number', '');

        if ($is_en) {
            // English site: keep the catalog links, ignore the Chinese
            // ACF link list (its URLs point at the Chinese site).
            $opt_quick   = null;
            $opt_brand   = '';
            $opt_prods   = '';
            $opt_copy    = '';
        }

        if ($opt_brand)              $brand_intro    = $opt_brand;
        if (is_array($opt_quick) && !empty($opt_quick)) $quick_links = $opt_quick;
        if ($opt_prods)              $main_products  = $opt_prods;
        if ($opt_copy)               $copyright      = $opt_copy;
        if ($opt_icp)                $icp            = $opt_icp;
    }

    // ── Contact info (NAP) — pulled from the same ACF options that
    // already feed the Organization / LocalBusiness JSON-LD, so the
    // on-page footer text and the schema always agree (critical for
    // Google's local-SEO NAP consistency check).
    $g_opt = function ($key, $default) {
        if (!function_exists('get_field')) return $default;
        $v = get_field($key, 'option');
        return ($v === null || $v === '') ? $default : $v;
    };
    $company_name = $g_opt('company_name', '乐清市耕文电气有限公司');
    // On the English site the legal Chinese company name is replaced by
    // the brand name used in the English catalogue.
    if ($is_en) {
        $company_name = xxdq_t('brand.name');
    }
    // Prefer the JSON-LD-grade fields (with country code + full street
    // address); fall back to the visible-only fields when those are empty.
    $contact  = xxdq_company_contact();
    $footer_phone    = $contact['phone'];
    $footer_email    = $contact['email'];
    $footer_address  = $contact['address'];
    $footer_wechat   = $g_opt('wechat', '');
    $footer_hours    = $contact['hours'];

    // On the English site the Chinese street address and the Chinese ICP
    // filing number must not appear — they are meaningless to the target
    // audience and would leak Chinese into an otherwise English page.
    if ($is_en) {
        $icp = '';
    }

    // tel: hrefs only accept digits and '+'; strip everything else.
    $tel_digits      = preg_replace('/[^0-9+]/', '', $footer_phone);
    $tel_href        = 'tel:' . $tel_digits;
    $mailto_href     = 'mailto:' . $footer_email;
    $map_href        = 'https://map.baidu.com/search/' . rawurlencode($footer_address);

    // Build quick-links <ul>
    $quick_html = '';
    if (is_array($quick_links)) {
        foreach ($quick_links as $link) {
            $label = isset($link['label']) ? (string) $link['label'] : '';
            $url   = isset($link['url'])   ? (string) $link['url']   : '#';
            if ($label === '') continue;
            $quick_html .= sprintf('<li><a href="%s">%s</a></li>', esc_url($url), esc_html($label));
        }
    }
    if ($quick_html === '') {
        $quick_html = '<li><a href="' . esc_url(xxdq_url('/')) . '">' . esc_html(xxdq_t('footer.no_links')) . '</a></li>';
    }
?>
<footer class="xxdq-footer" itemscope itemtype="https://schema.org/LocalBusiness">
  <link itemprop="url" href="<?php echo esc_url(xxdq_url('/')); ?>" />
  <meta itemprop="name" content="<?php echo esc_attr($company_name); ?>" />

  <div class="xxdq-footer__inner">
    <div class="xxdq-footer__col xxdq-footer__col--brand">
      <strong class="xxdq-footer__name"><?php echo esc_html($company_name); ?></strong>
      <p class="xxdq-footer__slogan"><?php echo esc_html(xxdq_t('footer.slogan')); ?></p>
      <p class="xxdq-footer__intro"><?php echo esc_html($brand_intro); ?></p>
    </div>

    <nav class="xxdq-footer__col" aria-label="<?php echo esc_attr(xxdq_t('footer.quick_links')); ?>">
      <h5><?php echo esc_html(xxdq_t('footer.quick_links')); ?></h5>
      <ul><?php echo $quick_html; ?></ul>
    </nav>

    <div class="xxdq-footer__col">
      <h5><?php echo esc_html(xxdq_t('footer.main_products')); ?></h5>
      <p class="xxdq-footer__products"><?php echo esc_html($main_products); ?></p>
    </div>

    <div class="xxdq-footer__col xxdq-footer__col--contact">
      <h5><?php echo esc_html(xxdq_t('footer.contact')); ?></h5>
      <address class="xxdq-footer__contact">
        <p class="xxdq-footer__contact-item">
          <span class="xxdq-footer__contact-icon" aria-hidden="true">📍</span>
          <span class="xxdq-footer__contact-label"><?php echo esc_html(xxdq_t('footer.address')); ?></span>
          <a itemprop="address" href="<?php echo esc_url($map_href); ?>"
             target="_blank" rel="nofollow noopener"><?php echo esc_html($footer_address); ?></a>
        </p>
        <p class="xxdq-footer__contact-item">
          <span class="xxdq-footer__contact-icon" aria-hidden="true">📞</span>
          <span class="xxdq-footer__contact-label"><?php echo esc_html(xxdq_t('footer.phone')); ?></span>
          <a itemprop="telephone" href="<?php echo esc_url($tel_href); ?>"><?php echo esc_html($footer_phone); ?></a>
        </p>
        <p class="xxdq-footer__contact-item">
          <span class="xxdq-footer__contact-icon" aria-hidden="true">✉</span>
          <span class="xxdq-footer__contact-label"><?php echo esc_html(xxdq_t('footer.email')); ?></span>
          <a itemprop="email" href="<?php echo esc_url($mailto_href); ?>"><?php echo esc_html($footer_email); ?></a>
        </p>
        <?php if ($footer_wechat !== ''): ?>
        <p class="xxdq-footer__contact-item">
          <span class="xxdq-footer__contact-icon" aria-hidden="true">💬</span>
          <span class="xxdq-footer__contact-label"><?php echo esc_html(xxdq_t('footer.wechat')); ?></span>
          <span class="xxdq-footer__contact-value"><?php echo esc_html($footer_wechat); ?></span>
        </p>
        <?php endif; ?>
        <p class="xxdq-footer__contact-item">
          <span class="xxdq-footer__contact-icon" aria-hidden="true">🕐</span>
          <span class="xxdq-footer__contact-label"><?php echo esc_html(xxdq_t('footer.hours')); ?></span>
          <span class="xxdq-footer__contact-value"><?php echo esc_html($footer_hours); ?></span>
        </p>
      </address>
    </div>
  </div>

  <div class="xxdq-footer__bottom">
    <span><?php echo esc_html($copyright); ?></span>
    <?php if ($icp): ?>
      <span class="xxdq-footer__icp"> · <a href="https://beian.miit.gov.cn/" target="_blank" rel="nofollow noopener"><?php echo esc_html($icp); ?></a></span>
    <?php endif; ?>
    <?php
      /* Footer language switcher — a second, always-visible entry point
       * to the other language (search engines and returning visitors
       * often land on the footer before the header). */
      $xxdq_f_lang = $is_en ? 'zh_CN' : 'en_US';
      $xxdq_f_url  = class_exists('XXDQ_I18n') ? XXDQ_I18n::switch_url($xxdq_f_lang) : home_url('/');
      $xxdq_f_text = $is_en ? '中文' : 'English';
    ?>
    <span class="xxdq-footer__lang"> · <a href="<?php echo esc_url($xxdq_f_url); ?>"
          hreflang="<?php echo esc_attr($xxdq_f_lang === 'en_US' ? 'en' : 'zh-Hans'); ?>"
          rel="alternate"><?php echo esc_html($xxdq_f_text); ?></a></span>
  </div>
</footer>
<?php
}, 5);

/* Hide GeneratePress's own footer — we render our own above.
 *
 * NOTE: `generate_footer` is an **action** in GeneratePress (see
 * themes/generatepress/footer.php → `do_action('generate_footer')`),
 * NOT a filter. The previous `add_filter('generate_footer',
 * '__return_empty_string')` therefore did nothing at all — filters on
 * an action are ignored — and GP's `site-info` block kept rendering
 * below our custom footer. That produced the stacked double footer
 * with the unwanted "Built with GeneratePress" credit line.
 *
 * Correct fix: unhook the two callbacks GP registers on that action.
 *   - generate_construct_footer_widgets (priority 5) → GP footer widgets
 *   - generate_construct_footer        (priority 10) → GP site-info,
 *     which is where "Built with GeneratePress" comes from
 *
 * `remove_action()` must run after GP has registered them, so we hook
 * into `wp` (which fires after `after_setup_theme`, where GP registers
 * its hooks) instead of doing it at plugin load time.
 */
add_action('wp', function () {
    remove_action('generate_footer', 'generate_construct_footer_widgets', 5);
    remove_action('generate_footer', 'generate_construct_footer', 10);
}, 20);

/* Belt-and-braces: if GP's `site-info` still slips through (e.g. a
 * theme update changes the hook name), strip the "Built with" credit
 * text so it can never reappear on a client-facing page.
 */
add_filter('generate_copyright', function ($copyright) {
    if (!is_string($copyright)) {
        return $copyright;
    }
    // Remove the " • Built with <a ...>GeneratePress</a>" segment.
    $clean = preg_replace(
        '/\s*&bull;\s*Built with\s*<a\b[^>]*>.*?<\/a>/is',
        '',
        $copyright
    );
    return $clean !== null ? $clean : $copyright;
});

/* ── 4.5 301 REDIRECTS (slugs we have retired) ──────────────────
 * `online-tools` was renamed to `cable-lug-calculator` in Oct 2026 for
 * SEO precision. Anyone (search engines, bookmarks, partner sites)
 * who still hits the old URL gets a 301 → new URL so:
 *   - the old page's link equity flows through instead of 404'ing
 *   - Google de-indexes the old slug quickly
 *   - inbound links from external sites keep working
 *
 * Add future slug renames to the same map.
 */
add_action('template_redirect', function () {
    $redirects = [
        '/online-tools/' => '/cable-lug-calculator/',
        // '/old-slug/' => '/new-slug/',
    ];
    $req = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
    if ( isset($redirects[$req]) ) {
        wp_safe_redirect( home_url($redirects[$req]), 301 );
        exit;
    }
});

/* ── 5. CUSTOM PAGE TEMPLATE ───────────────────────────────────
 * WP pages (about / contact / inquiry / etc.) would otherwise render
 * GeneratePress's plain page.php, which only emits the_content with no
 * hero or breadcrumb. Force xxdq-blocks' templates/page.php instead so
 * every page gets the same dark-gradient hero + xxp-breadcrumb + content
 * layout as the news and product pages.
 *
 * Opt-out: a page can bypass this by giving itself any non-empty
 * `page_template` (set via Page Attributes → Template in WP admin); we
 * only intercept pages that are using the default template.
 */
add_filter('template_include', function ($template) {
    if (is_page() && !is_front_page()) {
        $page_id = get_queried_object_id();
        /* The English home page is the front page of the English site,
         * but WordPress's `page_on_front` option still points at the
         * CHINESE home page — so is_front_page() is false here. It must
         * nevertheless be excluded: the Chinese home page renders
         * through GeneratePress' own page template (hero block only,
         * no page-hero / breadcrumb chrome), and the English one has to
         * look identical. */
        $en_home = class_exists('XXDQ_I18n_Router')
            ? XXDQ_I18n_Router::english_home_id()
            : 0;
        if ($page_id && $en_home && (int) $page_id === (int) $en_home) {
            return $template;
        }
        if ($page_id) {
            $chosen = get_page_template_slug($page_id);
            if (empty($chosen)) {
                $custom = WP_PLUGIN_DIR . '/xxdq-blocks/templates/page.php';
                if (file_exists($custom)) {
                    return $custom;
                }
            }
        }
    }
    return $template;
});

/* ── 6. SEO TITLE CLEANUP ───────────────────────────────────── *
 * Yoast's default title template for taxonomy archives embeds the
 * literal English word "Archives" (e.g. "rnb冷压端子 Archives - 乐清...").
 * That token is awkward on a Chinese B2B site. Strip it from every
 * taxonomy-term and CPT-archive title at priority 20, so it runs
 * AFTER Yoast's own wpseo_title pass and overrides any English
 * fallback Yoast emits.
 *
 * - is_tax()        : any taxonomy term page (product_category, news-cat, ...)
 * - is_post_type_archive() : CPT archive root (/product/, /news/)
 *
 * Custom SEO titles set per-term in Yoast are preserved as long as
 * they don't contain the literal "Archives" token.
 */
add_filter('wpseo_title', function ($title) {
    if (!is_tax() && !is_post_type_archive()) {
        return $title;
    }
    // Remove " Archives" / "Archives " (case-insensitive, with surrounding ws)
    $title = preg_replace('/\s*Archives\s*/i', '', $title);
    // Collapse any doubled separators left behind ("term - - sitename" → "term - sitename")
    $title = preg_replace('/(\s*-\s*){2,}/', ' - ', $title);
    $title = preg_replace('/(\s*\|\s*){2,}/', ' | ', $title);
    return trim($title);
}, 20);

/* ── 7. SEO TITLE REWRITE (B2B-friendly) ───────────────────── *
 * After section 6 strips "Archives", what's left is Yoast's default
 * "<term> - 乐清市耕文电气有限公司 - 电气连接件源头工厂". That brand is
 * 12 characters long and the tagline duplicates the same idea twice.
 * Rewrite to "<term> - 源头厂家 | 乐清耕文电气" — short brand, single
 * SEO keyword ("源头厂家"), pipe separator (renders cleaner in SERPs
 * and gives Google an extra weighted token before the brand).
 *
 * ONLY rewrites Yoast defaults (detected by the long brand name or
 * the standard tagline). Any custom SEO title you've set per-term in
 * Yoast admin is preserved untouched.
 *
 * Customise the per-context suffix in the $seo map below.
 */
add_filter('wpseo_title', function ($title) {
    if (!is_tax() && !is_post_type_archive()) {
        return $title;
    }

    /* Detect Yoast default vs hand-tuned title.
     *
     * The original check looked for the literal Chinese brand string
     * ("乐清市耕文电气有限公司" / "电气连接件源头工厂"). That works for
     * the Chinese site but silently fails on the English site, where the
     * title contains English text instead — every English taxonomy page
     * would then keep Yoast's raw "<term> - Site Title" instead of our
     * shortened B2B format.
     *
     * We now detect the default by comparing against the CURRENT
     * language's site name and tagline, which are language-neutral in
     * structure. A title is considered "Yoast default" when it ends
     * with the site name, or contains the tagline, or is still the
     * untouched Yoast "<term> - Archives" shape.
     */
    $site_name   = get_bloginfo('name');
    $is_default  = (stripos($title, (string) $site_name) !== false)
                || (stripos($title, (string) get_bloginfo('description')) !== false)
                || (strpos($title, 'Archives') !== false)
                || (strpos($title, 'Archives') !== false);

    // Language-specific default markers (the Chinese site still gets the
    // exact same behaviour as before).
    if (!xxdq_is_en()) {
        $is_default = $is_default
                    || (strpos($title, '乐清市耕文电气有限公司') !== false)
                    || (strpos($title, '电气连接件源头工厂') !== false);
    }

    if (!$is_default) {
        return $title; // leave custom titles alone
    }

    // Pull the term name (everything before the first separator).
    $parts   = preg_split('/\s*[-–—|]\s*/', $title, 2);
    $term    = trim($parts[0]);
    if ($term === '') {
        return $title;
    }

    if (xxdq_is_en()) {
        /* ── English site ─────────────────────────────────────── */
        $brand = 'Gengwen Electric';

        if (is_tax('product_category')) {
            return $term . ' - Manufacturer | ' . $brand;
        }
        if (is_tax()) {
            return $term . ' | ' . $brand;
        }
        if (is_post_type_archive('product')) {
            return 'Electrical Connectors & Terminals - Manufacturer | ' . $brand;
        }
        if (is_post_type_archive('news')) {
            return 'News & Industry Insights | ' . $brand;
        }
        if (is_post_type_archive()) {
            $pto   = get_post_type();
            $label = ($pto && isset(get_post_type_object($pto)->labels->name))
                ? get_post_type_object($pto)->labels->name
                : 'Articles';
            return $label . ' | ' . $brand;
        }

        return $term . ' | ' . $brand;
    }

    /* ── Chinese site (unchanged behaviour) ───────────────────── */
    $brand = '乐清耕文电气';

    if (is_tax('product_category')) {
        return $term . ' - 源头厂家 | ' . $brand;
    }
    if (is_tax()) {
        // Other taxonomies (news-cat, post_tag, post category, ...) — generic.
        return $term . ' | ' . $brand;
    }
    if (is_post_type_archive('product')) {
        return '产品中心 - 电气连接件源头厂家 | ' . $brand;
    }
    if (is_post_type_archive('news')) {
        return '新闻中心 - 电气行业资讯 | ' . $brand;
    }
    if (is_post_type_archive()) {
        $pto   = get_post_type();
        $label = ($pto && isset(get_post_type_object($pto)->labels->name))
            ? get_post_type_object($pto)->labels->name
            : '文章';
        return $label . ' | ' . $brand;
    }

    return $term . ' | ' . $brand;
}, 21);

/* ── 8. SEO HEAD POLICY OVERRIDES (Yoast filters) ───────────── *
 * Yoast is the single SEO emit engine; these filters are policy hooks
 * to fix things Yoast gets wrong on this site:
 *   • og:locale — WPLANG is not defined in wp-config.php, causing Yoast
 *     to inherit en_US. Force zh_CN so wechat / baidu / linkedin parse
 *     the locale correctly.
 *   • og:image on front page — Yoast falls back to the 470px cropped
 *     logo which renders as a tiny image in social previews. Force the
 *     1200×630 share card.
 *   • metadesc on front page — Yoast's wpseo_titles option has no
 *     homepage_description set, so Yoast omits the tag entirely on the
 *     front page. Inject the B2B long description here.
 *
 * Probed 2026-10: `wpseo_canonical` and `wpseo_opengraph_type` are
 * NOT needed — Yoast already returns `home_url('/')` and `website` on
 * the static homepage, so those filters were removed.
 *
 * Only fires on the front page so we don't disturb post / product /
 * taxonomy SEO that Yoast already gets right.
 */
add_filter('wpseo_opengraph_image', function ($image) {
    if (is_front_page()) {
        return home_url('/wp-content/uploads/2026/09/xxdq-share-card-1200x630-1.jpg');
    }
    return $image;
});

add_filter('wpseo_locale', function ($locale) {
    // Language-aware: the i18n layer already registers a filter at
    // priority 30 for this. This one runs at the default priority (10)
    // and simply defers to the language context.
    return XXDQ_I18n::locale();
}, 10);

add_filter('wpseo_og_locale', function ($locale) {
    return XXDQ_I18n::locale();
}, 10);

add_filter('wpseo_metadesc', function ($description) {
    if (is_front_page()) {
        /* The catalogue size comes from the database — the copy used to
         * claim "600+ 规格" / "600+ models" while the catalogue held
         * far fewer, which a buyer can disprove by counting. */
        $cat = function_exists('xxdq_catalogue_kw') ? xxdq_catalogue_kw() : '300';
        if (xxdq_is_en()) {
            return 'Gengwen Electric - a Yueqing-based manufacturer of copper lugs, crimp terminals, pre-insulated terminals, wire-end connectors, insulated end caps and electrical connectors. ' . $cat . '+ models in stock, CE / RoHS / ISO9001 certified, 24-hour dispatch, 1-hour quotation. OEM / ODM customisation welcome, small MOQ accepted.';
        }
        return '乐清市耕文电气有限公司 - 浙江源头工厂专业生产铜鼻子、冷压端子、预绝缘端子、接线端子、绝缘端头、连接器等电气连接件,' . $cat . '+ 规格现货,CE/RoHS/ISO9001 认证,24 小时出货,1 小时报价。支持 OEM/ODM 定制,小批量也欢迎。';
    }
    return $description;
});

/* ── 9. META DESCRIPTION SOURCE OF TRUTH ───────────────────── *
 * Yoast SEO is the single source of truth for <meta name="description">,
 * <link rel="canonical">, Open Graph, Twitter Cards, and <title>. The
 * companion xxdq-seo mu-plugin (10-meta.php) used to emit a competing
 * description tag from the site tagline, which Bing Webmaster Tools
 * flagged as a hard error and refused to index. That duplication was
 * removed; Yoast's Meta_Description_Presenter (with the wpseo_metadesc
 * filter above) is the only path that outputs the description now.
 *
 * Other xxdq-seo modules (20-schema, 30-faq, 40-related, 50-redirects)
 * still run and complement Yoast with JSON-LD / FAQ schema / related
 * templates / 301 redirects — none of those conflict with Yoast's
 * head output, so they stay.
 */
