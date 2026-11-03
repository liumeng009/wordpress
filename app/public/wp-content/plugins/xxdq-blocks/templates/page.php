<?php
/**
 * Generic page template (applied to all WP pages via template_include filter
 * in xxdq-theme).
 *
 * Renders:
 *   1. Dark gradient hero with the page title and excerpt as lede
 *   2. Breadcrumb (matches xxp-breadcrumb used by news + product pages)
 *   3. The page content (already styled by xxdq-blocks — xxdq-about,
 *      xxdq-why-us, xxdq-contact-cards, xxdq-cta, xxdq-inquiry-form, …)
 *
 * Bypasses GeneratePress's default page layout (which only renders
 * the_content with no chrome) so every page on the site looks consistent.
 */
if (!defined('ABSPATH')) exit;

/* Tag the body with .xxdq-page so the CSS can override GeneratePress's
 * flex sidebar layout (force full-width, hide sidebars). */
add_filter('body_class', function ($classes) {
    $classes[] = 'xxdq-page';
    return $classes;
});

get_header();

/* Per-page hero image map. Each WP page slug maps to a different
 * /wp-content/uploads/2026/09/hero-*.jpg that was pre-downloaded
 * locally. The hero block falls back to its CSS gradient if the file
 * 404s. This keeps the same dark hero chrome across the site while
 * letting each page wear a thematically-appropriate photo. */
$hero_image_map = [
    '关于我们'  => '/wp-content/uploads/2026/09/hero-about.jpg',
    'about'    => '/wp-content/uploads/2026/09/hero-about.jpg',
    'about-us' => '/wp-content/uploads/2026/09/hero-about.jpg',
    '联系我们'  => '/wp-content/uploads/2026/09/hero-contact.jpg',
    'contact'  => '/wp-content/uploads/2026/09/hero-contact.jpg',
    'contact-us' => '/wp-content/uploads/2026/09/hero-contact.jpg',
    '询价单'    => '/wp-content/uploads/2026/09/hero-inquiry.jpg',
    'inquiry'  => '/wp-content/uploads/2026/09/hero-inquiry.jpg',
    '技术资料'  => '/wp-content/uploads/2026/09/hero-news.jpg',
    'news'     => '/wp-content/uploads/2026/09/hero-news.jpg',
];

while (have_posts()) {
    the_post();

    $page_id    = get_the_ID();
    $page_title = get_the_title();
    /* Prefer explicit excerpt; fall back to ACF "subtitle" if defined;
     * otherwise pick a per-slug default so the hero doesn't look orphaned
     * (small title with a vast empty gradient under it) on pages where the
     * admin didn't fill in an excerpt.
     *
     * IMPORTANT: read the RAW post_excerpt, not get_the_excerpt().
     * get_the_excerpt() auto-generates an excerpt from post_content when
     * none is set — on the contact page that pulled the whole contact
     * card grid (address / phone / email / CTA) into the hero lede,
     * duplicating the content right below it. */
    $post_obj   = get_post($page_id);
    $page_lede  = $post_obj ? trim((string) $post_obj->post_excerpt) : '';
    if ($page_lede === '') {
        $acf_sub = function_exists('get_field') ? get_field('hero_subtitle', $page_id) : '';
        if (is_string($acf_sub)) $page_lede = trim($acf_sub);
    }
    if ($page_lede === '') {
        // WP stores non-ASCII slugs URL-encoded in post_name; normalize both
        // forms so the lookup below doesn't depend on which one it sees.
        $raw_slug  = $post_obj ? $post_obj->post_name : '';
        $slug      = urldecode($raw_slug);
        $is_en     = function_exists('xxdq_is_en') && xxdq_is_en();
        $defaults  = $is_en ? [
            'inquiry'    => 'Send us your requirement — we reply within 1 hour with model selection and a quotation.',
            'contact'    => 'Several ways to reach us — we look forward to working with you.',
            'contact-us' => 'Several ways to reach us — we look forward to working with you.',
            'about'      => 'Direct manufacturer · 10+ years of industry experience · CE/RoHS certified.',
            'about-us'   => 'Direct manufacturer · 10+ years of industry experience · CE/RoHS certified.',
            'news'       => 'Product releases · technical insights · industry trends.',
        ] : [
            '询价单'      => '留下您的产品需求,我们 1 小时内响应,提供选型指导与报价',
            'inquiry'    => '留下您的产品需求,我们 1 小时内响应,提供选型指导与报价',
            '联系我们'    => '多种联系方式,期待与您合作',
            'contact-us'=> '多种联系方式,期待与您合作',
            '关于我们'    => '源头工厂 · 10 年+ 行业经验 · CE/RoHS 认证',
            'about-us'   => '源头工厂 · 10 年+ 行业经验 · CE/RoHS 认证',
            '技术资料'    => '产品发布 · 技术资讯 · 行业动态',
            'news'       => '产品发布 · 技术资讯 · 行业动态',
            'online-tools' => '按导线截面、电流、接线方式快速匹配端子/连接器规格 · 计算结果仅供参考,正式采购请与我司确认',
        ];
        $page_lede = $defaults[$slug] ?? ($defaults[$raw_slug] ?? '');
    }

    /* Resolve the hero image. We try in this order:
     *   1. ACF field `hero_image` (URL or attachment ID) on the page
     *   2. Per-slug lookup in $hero_image_map (Chinese or Latin slug)
     *   3. Empty → CSS gradient fallback */
    $hero_img = '';
    if (function_exists('get_field')) {
        $acf_img = get_field('hero_image', $page_id);
        if (is_array($acf_img) && !empty($acf_img['url'])) {
            $hero_img = $acf_img['url'];
        } elseif (is_string($acf_img) && $acf_img !== '') {
            $hero_img = $acf_img;
        } elseif (is_numeric($acf_img)) {
            $hero_img = wp_get_attachment_url((int) $acf_img) ?: '';
        }
    }
    if ($hero_img === '') {
        $post_obj = get_post($page_id);
        $raw_slug = $post_obj ? $post_obj->post_name : '';
        $slug     = urldecode($raw_slug);
        $hero_img = $hero_image_map[$slug] ?? ($hero_image_map[$raw_slug] ?? '');

        /* English pages carry an "-en" suffix on their internal slug
         * (inquiry-en, about-us-en, contact-us-en). Strip it so the
         * same hero photo is used on both language versions. */
        if ($hero_img === '' && substr($slug, -3) === '-en') {
            $base = substr($slug, 0, -3);
            $hero_img = $hero_image_map[$base] ?? '';
        }
    }

    /* Directional overlay (dark on left where the title sits, fading to
     * near-transparent on the right so the photo is fully visible).
     * Keeps white text readable while letting the photo shine through. */
    $hero_style = $hero_img !== ''
        ? sprintf(' style="background-image:linear-gradient(90deg, rgba(13,27,42,.78) 0%%, rgba(27,38,59,.50) 45%%, rgba(17,36,58,.15) 100%%), url(%s);"', esc_url($hero_img))
        : '';
?>
<header class="xxdq-page-hero"<?php echo $hero_style; ?>>
  <div class="xxdq-page-hero__inner">
    <h1><?php echo esc_html($page_title); ?></h1>
    <?php if ($page_lede !== ''): ?>
      <p class="xxdq-page-hero__lede"><?php echo esc_html($page_lede); ?></p>
    <?php endif; ?>
  </div>
</header>

<div class="xxp-page-bc">
  <nav class="xxp-breadcrumb">
    <a href="<?php echo esc_url(function_exists('xxdq_url') ? xxdq_url('/') : home_url('/')); ?>"><?php echo esc_html(xxdq_t('crumb.home')); ?></a>
    <span>›</span>
    <span class="xxp-breadcrumb__current"><?php echo esc_html($page_title); ?></span>
  </nav>
</div>

<div class="xxdq-page-content">
  <?php the_content(); ?>
</div>

<?php
} // end while

get_footer();
