<?php
/**
 * Product archive template.
 *
 * Layout mirrors the reference site's /products/index.html page:
 *   - Left sidebar with all product categories (pulled from product_category taxonomy).
 *   - Main area groups products by product_series (sub-category). When a product has
 *     no series, it falls into an "其他" group.
 *   - Each product is rendered as a text card: name + model — no thumbnail (matches
 *     the reference site's clean, text-first listing).
 *
 * Contexts supported:
 *   - /product/                    (post_type_archive) → shows ALL products.
 *   - /product-category/foo/       (taxonomy archive)  → shows products in that term.
 */
if (!defined('ABSPATH')) exit;
get_header();

/* ── 1. Sidebar data — all product categories ───────────────── */
$sidebar_cats = get_terms([
    'taxonomy'   => 'product_category',
    'hide_empty' => false,
    'orderby'    => 'term_id',
    'order'      => 'ASC',
]);

/* ── 2. Main content ─────────────────────────────────────────── */
$is_tax     = is_tax('product_category');
$current_id = $is_tax ? (int) get_queried_object_id() : 0;
$page_title = $is_tax ? single_cat_title('', false) : post_type_archive_title('', false);
$page_desc  = $is_tax
    ? term_description($current_id, 'product_category')
    : '选择产品分类,或浏览全部';

/* Build the main query. */
$main_q = new WP_Query([
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
]);
if ($is_tax) {
    $main_q = new WP_Query([
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'tax_query'      => [[
            'taxonomy' => 'product_category',
            'field'    => 'term_id',
            'terms'    => $current_id,
        ]],
    ]);
}

/* Group products by product_series. */
$groups = [];   // [ series_slug => [ 'name' => '...', 'items' => [ ... ] ] ]
$unnamed = [];
if ($main_q->have_posts()) {
    while ($main_q->have_posts()) {
        $main_q->the_post();
        $pid = get_the_ID();
        $title  = get_the_title();
        $model  = function_exists('get_field') ? get_field('model', $pid) : '';
        $desc   = function_exists('get_field') ? get_field('short_desc', $pid) : '';
        $series = wp_get_post_terms($pid, 'product_series', ['fields' => 'all']);
        // Product thumbnail — fall back through real_image → tech_image
        // → image_2/3/4 → featured_media → SVG placeholder. The same
        // helper xxdq_product_gallery() is used by single-product.php
        // so the two pages always show the same primary image.
        $gallery  = function_exists('xxdq_product_gallery') ? xxdq_product_gallery($pid, 'medium') : [];
        $thumb    = $gallery[0] ?? '';
        if (empty($thumb)) {
            $fid = get_post_thumbnail_id($pid);
            if ($fid) {
                $thumb = wp_get_attachment_image_url($fid, 'medium', false);
            }
        }

        $item = [
            'id'       => $pid,
            'title'    => $title,
            'model'    => $model,
            'desc'     => $desc,
            'url'      => get_permalink(),
            'thumb'    => $thumb,
        ];

        if (!empty($series) && !is_wp_error($series)) {
            $s = $series[0];
            $key = $s->slug;
            if (!isset($groups[$key])) {
                $groups[$key] = ['name' => $s->name, 'items' => []];
            }
            $groups[$key]['items'][] = $item;
        } else {
            $unnamed[] = $item;
        }
    }
    wp_reset_postdata();
}
?>

<header class="xxdq-product-archive__hero">
  <div class="xxdq-product-archive__hero-inner">
    <h1><?php echo esc_html($page_title); ?></h1>
    <?php if ($page_desc): ?>
      <p class="xxdq-product-archive__lede"><?php echo wp_kses_post($page_desc); ?></p>
    <?php endif; ?>
  </div>
</header>

<?php
/* ── Breadcrumb (below hero, same 1200px cap as the layout below) ───── */
$bc_archive_url = get_post_type_archive_link('product');
$bc_parts = [
    ['url' => home_url('/'),   'label' => '首页'],
    ['url' => $bc_archive_url, 'label' => '产品中心'],
];
if ($is_tax) {
    $term = get_queried_object();
    if ($term && $term->taxonomy === 'product_category') {
        // Walk up the parent chain (category is hierarchical).
        $chain = [];
        $cur   = $term;
        while ($cur && !is_wp_error($cur)) {
            $chain[] = $cur;
            $cur     = $cur->parent ? get_term($cur->parent, 'product_category') : null;
        }
        $chain = array_reverse($chain);
        foreach ($chain as $ancestor) {
            $bc_parts[] = [
                'url'   => get_term_link($ancestor),
                'label' => $ancestor->name,
            ];
        }
    } elseif ($term && $term->taxonomy === 'product_series') {
        $bc_parts[] = ['url' => '', 'label' => $term->name];
    }
}
?>
<div class="xxdq-product-archive__bc">
  <nav class="xxp-breadcrumb">
    <?php foreach ($bc_parts as $i => $p): ?>
      <?php if (!empty($p['url'])): ?>
        <a href="<?php echo esc_url($p['url']); ?>"><?php echo esc_html($p['label']); ?></a>
      <?php else: ?>
        <span class="xxp-breadcrumb__current"><?php echo esc_html($p['label']); ?></span>
      <?php endif; ?>
      <?php if ($i < count($bc_parts) - 1): ?>
        <span>›</span>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>
</div>

<div class="xxdq-product-archive__layout">
  <aside class="xxdq-product-archive__sidebar">
    <h3 class="xxdq-product-archive__sidebar-title">产品中心</h3>
    <ul>
      <li class="<?php echo !$is_tax ? 'is-active' : ''; ?>">
        <a href="<?php echo esc_url(get_post_type_archive_link('product')); ?>">全部产品</a>
      </li>
      <?php if (!is_wp_error($sidebar_cats) && !empty($sidebar_cats)): ?>
        <?php foreach ($sidebar_cats as $cat): ?>
          <li class="<?php echo ($is_tax && $current_id === (int) $cat->term_id) ? 'is-active' : ''; ?>">
            <a href="<?php echo esc_url(get_term_link($cat)); ?>">
              <?php echo esc_html($cat->name); ?>
              <span class="xxdq-product-archive__count">(<?php echo (int) $cat->count; ?>)</span>
            </a>
          </li>
        <?php endforeach; ?>
      <?php endif; ?>
    </ul>
  </aside>

  <section class="xxdq-product-archive__main">
    <?php if (empty($groups) && empty($unnamed)): ?>
      <p class="xxdq-empty">暂无产品。</p>
    <?php else: ?>

      <?php
        /*
         * Group-heading policy:
         *   - Multiple groups present → render each group title so the visitor
         *     can tell SV / RV / DBV etc. apart.
         *   - Single group present AND its name is the generic "其他型号"
         *     bucket (i.e. products with no series assigned) → skip the heading.
         *     The page H1 already names the context ("全部产品" / "铜鼻子"),
         *     so a second "其他型号" header is redundant noise.
         *   - Single named group present → still render its name, because it
         *     carries information (e.g. "VE 管型") the H1 doesn't.
         */
        $total_groups = count($groups) + (empty($unnamed) ? 0 : 1);
        $unnamed_is_only = (count($groups) === 0 && !empty($unnamed));
    ?>

      <?php if (!empty($groups)): ?>
        <?php foreach ($groups as $g): ?>
          <div class="xxdq-product-group">
            <?php if ($total_groups > 1): ?>
              <h2 class="xxdq-product-group__title"><?php echo esc_html($g['name']); ?></h2>
            <?php endif; ?>
            <ul class="xxdq-product-list">
              <?php foreach ($g['items'] as $it): ?>
                <li class="xxdq-product-list__item">
                  <a href="<?php echo esc_url($it['url']); ?>" class="xxdq-product-list__inner">
                    <div class="xxdq-product-list__thumb">
                      <?php if (!empty($it['thumb'])): ?>
                        <img src="<?php echo esc_url($it['thumb']); ?>" alt="<?php echo esc_attr($it['title']); ?>" loading="lazy" />
                      <?php else: ?>
                        <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                          <rect width="200" height="200" fill="#F4F6FA"/>
                          <rect x="60" y="55" width="80" height="55" rx="6" fill="#CBD5E0"/>
                          <circle cx="100" cy="135" r="22" fill="#CBD5E0"/>
                          <path d="M82 152 L96 138 L112 156 L128 140 L138 152 Z" fill="#CBD5E0"/>
                        </svg>
                      <?php endif; ?>
                    </div>
                    <div class="xxdq-product-list__info">
                      <span class="xxdq-product-list__name"><?php echo esc_html($it['title']); ?></span>
                      <?php if (!empty($it['model'])): ?>
                        <span class="xxdq-product-list__model"><?php echo esc_html($it['model']); ?></span>
                      <?php endif; ?>
                      <?php if (!empty($it['desc'])): ?>
                        <span class="xxdq-product-list__desc"><?php echo esc_html($it['desc']); ?></span>
                      <?php endif; ?>
                    </div>
                    <span class="xxdq-product-list__arrow" aria-hidden="true">›</span>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if (!empty($unnamed)): ?>
        <div class="xxdq-product-group">
          <?php if ($total_groups > 1): ?>
            <h2 class="xxdq-product-group__title">其他型号</h2>
          <?php endif; ?>
            <ul class="xxdq-product-list">
              <?php foreach ($unnamed as $it): ?>
                <li class="xxdq-product-list__item">
                  <a href="<?php echo esc_url($it['url']); ?>" class="xxdq-product-list__inner">
                    <div class="xxdq-product-list__thumb">
                      <?php if (!empty($it['thumb'])): ?>
                        <img src="<?php echo esc_url($it['thumb']); ?>" alt="<?php echo esc_attr($it['title']); ?>" loading="lazy" />
                      <?php else: ?>
                        <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                          <rect width="200" height="200" fill="#F4F6FA"/>
                          <rect x="60" y="55" width="80" height="55" rx="6" fill="#CBD5E0"/>
                          <circle cx="100" cy="135" r="22" fill="#CBD5E0"/>
                          <path d="M82 152 L96 138 L112 156 L128 140 L138 152 Z" fill="#CBD5E0"/>
                        </svg>
                      <?php endif; ?>
                    </div>
                    <div class="xxdq-product-list__info">
                      <span class="xxdq-product-list__name"><?php echo esc_html($it['title']); ?></span>
                      <?php if (!empty($it['model'])): ?>
                        <span class="xxdq-product-list__model"><?php echo esc_html($it['model']); ?></span>
                      <?php endif; ?>
                      <?php if (!empty($it['desc'])): ?>
                        <span class="xxdq-product-list__desc"><?php echo esc_html($it['desc']); ?></span>
                      <?php endif; ?>
                    </div>
                    <span class="xxdq-product-list__arrow" aria-hidden="true">›</span>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
        </div>
      <?php endif; ?>

    <?php endif; ?>
  </section>
</div>

<?php get_footer();
