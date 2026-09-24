<?php
/**
 * Plugin Name: 乐清市耕文电气有限公司 Theme
 * Description: Custom site theme plugin for 乐清市耕文电气有限公司 (gengwen-electric) — generates all custom homepage sections and styles.
 * Version: 2.0.6
 */

if (!defined('ABSPATH')) exit;

define('XXDQ_THEME_DIR', plugin_dir_path(__FILE__));
define('XXDQ_THEME_URL', plugin_dir_url(__FILE__));
define('XXDQ_THEME_VERSION', '2.0.6');

/* ── 0. PIM ACF fields (实物图 / 描述图 / PDF) ─────────────
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

/* ── 2. GLOBAL TOP NAV ────────────────────────────────────── */
/**
 * Render the top navigation bar with dropdown on "产品中心".
 * Hooked to generate_before_header (GeneratePress theme).
 * Product categories are pulled dynamically from the `product_category`
 * taxonomy via get_terms(), so the dropdown auto-updates when categories
 * are added/removed in WordPress admin.
 */
add_action('generate_before_header', function () {
    // Pull product categories dynamically from the `product_category` taxonomy
    // so the dropdown auto-updates when new categories are added/removed.
    $product_cats = get_terms([
        'taxonomy'   => 'product_category',
        'hide_empty' => false,   // show even empty categories so newly-created ones appear
        'orderby'    => 'menu_order',
        'order'      => 'ASC',
    ]);
    if (is_wp_error($product_cats)) {
        $product_cats = [];
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
    <a href="<?php echo esc_url(home_url('/')); ?>" class="xxp-nav__logo">
      <span class="xxp-nav__logo-mark">耕文</span>
      <span class="xxp-nav__logo-text">乐清市耕文电气有限公司<small>电气连接件源头工厂</small></span>
    </a>
    <nav class="xxp-nav__menu">
      <a href="<?php echo esc_url(home_url('/')); ?>" class="<?php echo is_front_page() ? 'active' : ''; ?>">首页</a>
      <div class="xxp-nav__has-sub">
        <a href="<?php echo esc_url(get_post_type_archive_link('product')); ?>" class="xxp-nav__toggle <?php echo $on_product_archive ? 'active' : ''; ?>">
          产品中心 <span class="xxp-nav__caret">▼</span>
        </a>
        <div class="xxp-nav__dropdown">
          <?php foreach ($product_cats as $term): ?>
            <?php
                $link = get_term_link($term);
                if (is_wp_error($link)) { continue; }
                $is_current = ((int) $term->term_id === $current_term_id);
            ?>
            <a href="<?php echo esc_url($link); ?>" class="<?php echo $is_current ? 'active' : ''; ?>">
              <?php echo esc_html($term->name); ?>
              <span class="xxp-nav__dropdown-count">(<?php echo (int) $term->count; ?>)</span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <a href="<?php echo esc_url(home_url('/news/')); ?>" class="<?php echo ($active_slug === 'news') ? 'active' : ''; ?>">新闻中心</a>
      <a href="<?php echo esc_url(home_url('/关于我们/')); ?>" class="<?php echo ($active_slug === '关于我们') ? 'active' : ''; ?>">关于我们</a>
      <a href="<?php echo esc_url(home_url('/联系我们/')); ?>" class="<?php echo ($active_slug === '联系我们') ? 'active' : ''; ?>">联系我们</a>
    </nav>
    <div class="xxp-nav__cta">
      <a href="<?php echo esc_url(home_url('/询价单/')); ?>" class="xxdq-btn xxdq-btn--primary xxdq-btn--sm">立即询价</a>
    </div>
    <button class="xxp-nav__burger" id="xxp-nav-burger" aria-label="菜单">☰</button>
  </div>
</header>
<?php
}, 1);

/* ── 3. CUSTOM LOGO ───────────────────────────────────────── */
add_theme_support('custom-logo', [
    'height'      => 60,
    'width'       => 200,
    'flex-height' => true,
    'flex-width'  => true,
]);

/* ── 4. CUSTOM FOOTER (Phase 9) ───────────────────────────── *
 * Render a 3-column footer driven by the ACF options page.
 * Hooked to `generate_before_footer` so it appears ABOVE the
 * default GeneratePress footer (we want to replace the GP
 * footer, not stack on top of it).
 */
add_action('generate_before_footer', function () {
    // Defaults — used when ACF is not active OR no options set.
    $brand_intro = '乐清市耕文电气有限公司 — 源头工厂,10年+ 行业经验,产品通过 CE/RoHS/ISO9001 认证,出口品质,现货直发。';
    $product_archive = get_post_type_archive_link('product') ?: home_url('/product/');
    $quick_links = [
        ['label' => '首页',         'url' => home_url('/')],
        ['label' => '产品中心',     'url' => $product_archive],
        ['label' => '关于我们',     'url' => home_url('/关于我们/')],
        ['label' => '新闻中心',     'url' => home_url('/news/')],
        ['label' => '联系我们',     'url' => home_url('/联系我们/')],
    ];
    $main_products  = '铜鼻子 · 冷压端子 · 预绝缘端子 · 接插件 · 线缆附件 · 电力金具';
    $copyright      = '© ' . date('Y') . ' 乐清市耕文电气有限公司 版权所有';
    $icp            = '';

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

        if ($opt_brand)              $brand_intro    = $opt_brand;
        if (is_array($opt_quick) && !empty($opt_quick)) $quick_links = $opt_quick;
        if ($opt_prods)              $main_products  = $opt_prods;
        if ($opt_copy)               $copyright      = $opt_copy;
        if ($opt_icp)                $icp            = $opt_icp;
    }

    $company_name = function_exists('get_field')
        ? (get_field('company_name', 'option') ?: '乐清市耕文电气有限公司')
        : '乐清市耕文电气有限公司';

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
        $quick_html = '<li><a href="/">暂无链接</a></li>';
    }
?>
<footer class="xxdq-footer">
  <div class="xxdq-footer__inner">
    <div class="xxdq-footer__brand">
      <h4><?php echo esc_html($company_name); ?></h4>
      <p><?php echo esc_html($brand_intro); ?></p>
    </div>
    <div class="xxdq-footer__col">
      <h5>快捷链接</h5>
      <ul><?php echo $quick_html; ?></ul>
    </div>
    <div class="xxdq-footer__col">
      <h5>主营产品</h5>
      <p class="xxdq-footer__products"><?php echo esc_html($main_products); ?></p>
    </div>
  </div>
  <div class="xxdq-footer__bottom">
    <span><?php echo esc_html($copyright); ?></span>
    <?php if ($icp): ?>
      <span class="xxdq-footer__icp"> · <a href="https://beian.miit.gov.cn/" target="_blank" rel="nofollow noopener"><?php echo esc_html($icp); ?></a></span>
    <?php endif; ?>
  </div>
</footer>
<?php
}, 5);

/* Hide GeneratePress's own footer — we render our own above.
 * Returning an empty string from `generate_footer` removes the GP-supplied
 * footer markup so we don't end up with two stacked footers.
 */
add_filter('generate_footer', '__return_empty_string');

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
