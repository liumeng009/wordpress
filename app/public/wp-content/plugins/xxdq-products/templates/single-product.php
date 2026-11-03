<?php
/**
 * Single product detail template.
 *
 * Layout:
 *   - Breadcrumbs: 首页 › 产品中心 › [分类] › [产品]
 *   - Two-column body:
 *       LEFT  : main product image (real_image > tech_image > featured)
 *       RIGHT : title, model, short description, specs preview, CTA buttons
 *   - Below  : full spec table, tech image, PDF download, prev/next within category
 */
if (!defined('ABSPATH')) exit;
get_header();

$post_id    = get_the_ID();
$specs      = class_exists('XXDQ_I18n_Content')
    ? XXDQ_I18n_Content::specs($post_id)
    : xxdq_product_specs($post_id);
$gallery    = xxdq_product_gallery($post_id, 'large');   // ordered, deduped
$main_img   = $gallery[0] ?? '';                          // first image wins
if (!$main_img && has_post_thumbnail($post_id)) {
    $main_img = get_the_post_thumbnail_url($post_id, 'large');
}
$tech_img   = $gallery[1] ?? '';                          // legacy "技术图 / 尺寸图" section
$pdf_id     = xxdq_media_id('attachment', $post_id);
$pdf_url    = $pdf_id ? wp_get_attachment_url($pdf_id) : '';
$pdf_title  = $pdf_id ? get_the_title($pdf_id) : '';

/* Breadcrumb: get the first product_category term. */
$cats = wp_get_post_terms($post_id, 'product_category', ['fields' => 'all']);
$cat  = (!empty($cats) && !is_wp_error($cats)) ? $cats[0] : null;

/* Prev / Next within the same category (falls back to global). */
$nav_args = ['post_type' => 'product', 'post_status' => 'publish'];
if ($cat) {
    $nav_args['tax_query'] = [[
        'taxonomy' => 'product_category',
        'field'    => 'term_id',
        'terms'    => $cat->term_id,
    ]];
}
$prev_post = get_previous_post(true, '', 'product_category');
$next_post = get_next_post(true, '', 'product_category');

/* Spec labels (mirror the helper). */
$labels = [
    'model'         => xxdq_t('product.model_label'),
    'wire_range'    => xxdq_t('product.wire_range'),
    'hole_size'     => xxdq_t('product.hole_size'),
    'material'      => xxdq_t('product.material'),
    'finish'        => xxdq_t('product.finish'),
    'color_code'    => xxdq_t('product.color_code'),
    'voltage'       => xxdq_t('product.voltage'),
    'temperature'   => xxdq_t('product.temperature'),
    'certification' => xxdq_t('product.certification'),
    'package'       => xxdq_t('product.package'),
    'short_desc'    => xxdq_t('product.short_desc'),
];
?>
<div class="xxdq-product__bc">
  <nav class="xxp-breadcrumb">
    <a href="<?php echo esc_url(xxdq_url('/')); ?>"><?php echo esc_html(xxdq_t('crumb.home')); ?></a>
    <span>›</span>
    <a href="<?php echo esc_url(xxdq_url('/product/')); ?>"><?php echo esc_html(xxdq_t('nav.products')); ?></a>
    <?php if ($cat): ?>
      <span>›</span>
      <a href="<?php echo esc_url(xxdq_term_link($cat)); ?>"><?php echo esc_html(xxdq_term_name($cat)); ?></a>
    <?php endif; ?>
    <span>›</span>
    <span class="xxp-breadcrumb__current"><?php the_title(); ?></span>
  </nav>
</div>

<article class="xxdq-product">
  <div class="xxdq-product__hero">
    <div class="xxdq-product__gallery">
      <?php if ($main_img): ?>
        <a class="xxdq-product__mainimg" href="<?php echo esc_url($main_img); ?>" target="_blank" rel="noopener">
          <img src="<?php echo esc_url($main_img); ?>" alt="<?php the_title_attribute(); ?>">
        </a>
      <?php else: ?>
        <div class="xxdq-product__mainimg xxdq-product__mainimg--empty"><?php echo esc_html(xxdq_t('product.no_image')); ?></div>
      <?php endif; ?>

      <?php if (count($gallery) > 1): ?>
        <div class="xxdq-product__thumbs">
          <?php foreach ($gallery as $i => $src): ?>
            <button type="button" class="xxdq-product__thumb <?php echo $i === 0 ? 'is-active' : ''; ?>" data-src="<?php echo esc_url($src); ?>">
              <img src="<?php echo esc_url($src); ?>" alt="<?php echo esc_attr(sprintf(xxdq_t('product.image_alt'), $i + 1)); ?>">
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="xxdq-product__summary">
      <h1 class="xxdq-product__title"><?php the_title(); ?></h1>

      <?php if (!empty($specs['model'])): ?>
        <p class="xxdq-product__model"><?php echo esc_html(xxdq_t('product.model_label')); ?>: <strong><?php echo esc_html($specs['model']); ?></strong></p>
      <?php endif; ?>

      <?php if (!empty($specs['short_desc'])): ?>
        <p class="xxdq-product__intro"><?php echo esc_html($specs['short_desc']); ?></p>
      <?php endif; ?>

      <ul class="xxdq-product__highlights">
        <?php if (!empty($specs['material'])): ?>
          <li><span><?php echo esc_html(xxdq_t('product.material')); ?></span><?php echo esc_html($specs['material']); ?></li>
        <?php endif; ?>
        <?php if (!empty($specs['wire_range'])): ?>
          <li><span><?php echo esc_html(xxdq_t('product.wire_range')); ?></span><?php echo esc_html($specs['wire_range']); ?></li>
        <?php endif; ?>
        <?php if (!empty($specs['hole_size'])): ?>
          <li><span><?php echo esc_html(xxdq_t('product.hole_size')); ?></span><?php echo esc_html($specs['hole_size']); ?></li>
        <?php endif; ?>
        <?php if (!empty($specs['certification'])): ?>
          <li><span><?php echo esc_html(xxdq_t('product.certification')); ?></span><?php echo esc_html($specs['certification']); ?></li>
        <?php endif; ?>
      </ul>

      <div class="xxdq-product__actions">
        <?php
          /* 立即询价 → 询价单页,带型号查询参数。
           *
           * 关键:URL 参数名必须避开 WP 保留 query var。直接叫 `product` 会
           * 被 WP 解释成 `?post_type=product&product=<slug>`,然后因为没
           * 找到对应 product post → 整个请求 404(任何页面挂上 `?product=…`
           * 都会 404,不只是 inquiry 页)。
           *
           * 询价表单上对应的输入框是 name="product"(form submit 时用),
           * 但 URL 入参我们改用 "model" 来避开 WP 解析。
           */
          $inquiry_url = method_exists('XXDQ_Blocks_Render', 'inquiry_url')
              ? call_user_func(['XXDQ_Blocks_Render', 'inquiry_url'])
              : (function_exists('get_permalink') ? xxdq_permalink(17) : home_url('/inquiry/'));
          $product_inquiry_url = $inquiry_url
              . (strpos($inquiry_url, '?') === false ? '?' : '&amp;')
              . 'model=' . rawurlencode(get_the_title());
        ?>
        <a class="xxdq-btn xxdq-btn--primary" href="<?php echo esc_url($product_inquiry_url); ?>"><?php echo esc_html(xxdq_t('product.inquiry_now')); ?></a>
        <?php if ($pdf_url): ?>
          <a class="xxdq-btn xxdq-btn--outline" href="<?php echo esc_url($pdf_url); ?>" target="_blank" rel="noopener"><?php echo esc_html(xxdq_t('product.download')); ?></a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if (!empty($specs)): ?>
    <section class="xxdq-product__section">
      <h2 class="xxdq-product__section-title"><?php echo esc_html(xxdq_t('product.specs_title')); ?></h2>
      <table class="xxdq-product__specs">
        <tbody>
          <?php foreach ($specs as $k => $v):
              $label = $labels[$k] ?? $k;
              if ($k === 'short_desc') continue; // already shown above ?>
            <tr>
              <th><?php echo esc_html($label); ?></th>
              <td><?php echo esc_html(is_array($v) ? implode(', ', $v) : $v); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  <?php endif; ?>

  <?php if ($tech_img): ?>
    <section class="xxdq-product__section">
      <h2 class="xxdq-product__section-title"><?php echo esc_html(xxdq_t('product.tech_title')); ?></h2>
      <figure class="xxdq-product__tech">
        <img src="<?php echo esc_url($tech_img); ?>" alt="<?php echo esc_attr(get_the_title()); ?>">
      </figure>
    </section>
  <?php endif; ?>

  <?php if (get_the_content()): ?>
    <section class="xxdq-product__section">
      <h2 class="xxdq-product__section-title"><?php echo esc_html(xxdq_t('product.detail_title')); ?></h2>
      <div class="xxdq-product__content"><?php the_content(); ?></div>
    </section>
  <?php endif; ?>

  <nav class="xxdq-product__nav">
    <div class="xxdq-product__nav-prev">
      <?php if ($prev_post): ?>
        <a href="<?php echo esc_url(xxdq_permalink($prev_post)); ?>">
          <span class="xxdq-product__nav-label"><?php echo esc_html(xxdq_t('product.prev')); ?></span>
          <span class="xxdq-product__nav-title"><?php echo esc_html(get_the_title($prev_post)); ?></span>
        </a>
      <?php else: ?>
        <span class="xxdq-product__nav-label"><?php echo esc_html(xxdq_t('product.first')); ?></span>
      <?php endif; ?>
    </div>
    <div class="xxdq-product__nav-next">
      <?php if ($next_post): ?>
        <a href="<?php echo esc_url(xxdq_permalink($next_post)); ?>">
          <span class="xxdq-product__nav-label"><?php echo esc_html(xxdq_t('product.next')); ?></span>
          <span class="xxdq-product__nav-title"><?php echo esc_html(get_the_title($next_post)); ?></span>
        </a>
      <?php else: ?>
        <span class="xxdq-product__nav-label"><?php echo esc_html(xxdq_t('product.last')); ?></span>
      <?php endif; ?>
    </div>
  </nav>

  <p class="xxdq-product__back">
    <a href="<?php echo $cat ? esc_url(xxdq_term_link($cat)) : esc_url(xxdq_url('/product/')); ?>">
      <?php echo esc_html(sprintf(xxdq_t('product.back'), $cat ? xxdq_term_name($cat) : xxdq_t('product.back_to'))); ?>
    </a>
  </p>

  <?php
    /* ── SEO 模块: 相关产品 (同系列/同分类) ── */
    if (function_exists('xxdq_render_related_products')) {
      echo xxdq_render_related_products($post_id, 6);
    }
    /* ── SEO 模块: 相关资讯 (产品→新闻, 跨类型) ── */
    if (function_exists('xxdq_render_related_news_for_product')) {
      echo xxdq_render_related_news_for_product($post_id, 2);
    }
    /* ── SEO 模块: FAQ (产品级 + 站点级, 加 FAQPage schema) ── */
    if (function_exists('xxdq_render_faq')) {
      echo xxdq_render_faq($post_id);
    }
  ?>
</article>

<script>
/* Tiny gallery thumb-switcher — vanilla JS so we don't pull in jQuery. */
document.addEventListener('DOMContentLoaded', function () {
  var main = document.querySelector('.xxdq-product__mainimg img');
  if (!main) return;
  document.querySelectorAll('.xxdq-product__thumb').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var src = this.getAttribute('data-src');
      if (!src) return;
      main.src = src;
      document.querySelectorAll('.xxdq-product__thumb').forEach(function (b) {
        b.classList.remove('is-active');
      });
      this.classList.add('is-active');
    });
  });
});
</script>

<?php get_footer();
