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
$specs      = xxdq_product_specs($post_id);
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
    'model'         => '型号',
    'wire_range'    => '适用线径',
    'hole_size'     => '螺丝孔径',
    'material'      => '材质',
    'finish'        => '表面处理',
    'color_code'    => '绝缘颜色',
    'voltage'       => '额定电压',
    'temperature'   => '工作温度',
    'certification' => '认证',
    'package'       => '包装方式',
    'short_desc'    => '简介',
];
?>
<nav class="xxdq-breadcrumb">
  <a href="<?php echo esc_url(home_url('/')); ?>">首页</a>
  <span>›</span>
  <a href="<?php echo esc_url(get_post_type_archive_link('product')); ?>">产品中心</a>
  <?php if ($cat): ?>
    <span>›</span>
    <a href="<?php echo esc_url(get_term_link($cat)); ?>"><?php echo esc_html($cat->name); ?></a>
  <?php endif; ?>
  <span>›</span>
  <span class="xxdq-breadcrumb__current"><?php the_title(); ?></span>
</nav>

<article class="xxdq-product">
  <div class="xxdq-product__hero">
    <div class="xxdq-product__gallery">
      <?php if ($main_img): ?>
        <a class="xxdq-product__mainimg" href="<?php echo esc_url($main_img); ?>" target="_blank" rel="noopener">
          <img src="<?php echo esc_url($main_img); ?>" alt="<?php the_title_attribute(); ?>">
        </a>
      <?php else: ?>
        <div class="xxdq-product__mainimg xxdq-product__mainimg--empty">暂无图片</div>
      <?php endif; ?>

      <?php if (count($gallery) > 1): ?>
        <div class="xxdq-product__thumbs">
          <?php foreach ($gallery as $i => $src): ?>
            <button type="button" class="xxdq-product__thumb <?php echo $i === 0 ? 'is-active' : ''; ?>" data-src="<?php echo esc_url($src); ?>">
              <img src="<?php echo esc_url($src); ?>" alt="图 <?php echo $i + 1; ?>">
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="xxdq-product__summary">
      <h1 class="xxdq-product__title"><?php the_title(); ?></h1>

      <?php if (!empty($specs['model'])): ?>
        <p class="xxdq-product__model">型号: <strong><?php echo esc_html($specs['model']); ?></strong></p>
      <?php endif; ?>

      <?php if (!empty($specs['short_desc'])): ?>
        <p class="xxdq-product__intro"><?php echo esc_html($specs['short_desc']); ?></p>
      <?php endif; ?>

      <ul class="xxdq-product__highlights">
        <?php if (!empty($specs['material'])): ?>
          <li><span>材质</span><?php echo esc_html($specs['material']); ?></li>
        <?php endif; ?>
        <?php if (!empty($specs['wire_range'])): ?>
          <li><span>适用线径</span><?php echo esc_html($specs['wire_range']); ?></li>
        <?php endif; ?>
        <?php if (!empty($specs['hole_size'])): ?>
          <li><span>螺丝孔径</span><?php echo esc_html($specs['hole_size']); ?></li>
        <?php endif; ?>
        <?php if (!empty($specs['certification'])): ?>
          <li><span>认证</span><?php echo esc_html($specs['certification']); ?></li>
        <?php endif; ?>
      </ul>

      <div class="xxdq-product__actions">
        <a class="xxdq-btn xxdq-btn--primary" href="<?php echo esc_url(home_url('/询价单/?product=' . urlencode(get_the_title()))); ?>">立即询价</a>
        <?php if ($pdf_url): ?>
          <a class="xxdq-btn xxdq-btn--outline" href="<?php echo esc_url($pdf_url); ?>" target="_blank" rel="noopener">📄 下载技术手册</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if (!empty($specs)): ?>
    <section class="xxdq-product__section">
      <h2 class="xxdq-product__section-title">规格参数</h2>
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
      <h2 class="xxdq-product__section-title">技术图 / 尺寸图</h2>
      <figure class="xxdq-product__tech">
        <img src="<?php echo esc_url($tech_img); ?>" alt="<?php the_title_attribute(); ?> 技术图">
      </figure>
    </section>
  <?php endif; ?>

  <?php if (get_the_content()): ?>
    <section class="xxdq-product__section">
      <h2 class="xxdq-product__section-title">产品详情</h2>
      <div class="xxdq-product__content"><?php the_content(); ?></div>
    </section>
  <?php endif; ?>

  <nav class="xxdq-product__nav">
    <div class="xxdq-product__nav-prev">
      <?php if ($prev_post): ?>
        <a href="<?php echo esc_url(get_permalink($prev_post)); ?>">
          <span class="xxdq-product__nav-label">← 上一个产品</span>
          <span class="xxdq-product__nav-title"><?php echo esc_html(get_the_title($prev_post)); ?></span>
        </a>
      <?php else: ?>
        <span class="xxdq-product__nav-label">已经是第一个了</span>
      <?php endif; ?>
    </div>
    <div class="xxdq-product__nav-next">
      <?php if ($next_post): ?>
        <a href="<?php echo esc_url(get_permalink($next_post)); ?>">
          <span class="xxdq-product__nav-label">下一个产品 →</span>
          <span class="xxdq-product__nav-title"><?php echo esc_html(get_the_title($next_post)); ?></span>
        </a>
      <?php else: ?>
        <span class="xxdq-product__nav-label">已经是最后一个了</span>
      <?php endif; ?>
    </div>
  </nav>

  <p class="xxdq-product__back">
    <a href="<?php echo $cat ? esc_url(get_term_link($cat)) : esc_url(get_post_type_archive_link('product')); ?>">
      ← 返回<?php echo $cat ? esc_html($cat->name) : '产品中心'; ?>
    </a>
  </p>
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
