<?php
/**
 * Product archive template.
 *
 * Layout mirrors the reference site's /products/index.html page:
 *   - Left sidebar with all product categories (pulled from product_category taxonomy).
 *   - Main area groups products by the parent product_category.
 *   - Each product is rendered as a text card: name + model — no thumbnail (matches
 *     the reference site's clean, text-first listing).
 *
 * Contexts supported:
 *   - /product/                    (post_type_archive) → shows ALL products grouped by top category.
 *   - /product-category/foo/       (top-level tax)     → shows a grid of sub-category cards.
 *   - /product-category/foo/bar/   (sub-level tax)     → shows flat list of products in that sub.
 */
if (!defined('ABSPATH')) exit;
get_header();

/* ── 1. Sidebar data — all product categories ───────────────── */
/* Sidebar shows a 2-level tree (top-level categories, with their sub-categories nested). */
$sidebar_top_cats = get_terms([
    'taxonomy'   => 'product_category',
    'hide_empty' => false,
    'parent'     => 0,
    'orderby'    => 'term_id',
    'order'      => 'ASC',
]);
/* Keep only the categories of the current language — the English site
 * keeps a parallel set of terms. */
$sidebar_top_cats = function_exists('xxdq_filter_terms_by_lang')
    ? xxdq_filter_terms_by_lang($sidebar_top_cats)
    : $sidebar_top_cats;
$sidebar_sub_by_top = [];
if (!is_wp_error($sidebar_top_cats) && !empty($sidebar_top_cats)) {
    foreach ($sidebar_top_cats as $top) {
        $children = get_terms([
            'taxonomy'   => 'product_category',
            'hide_empty' => false,
            'parent'     => (int) $top->term_id,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]);
        if (function_exists('xxdq_filter_terms_by_lang')) {
            $children = xxdq_filter_terms_by_lang($children);
        }
        $sidebar_sub_by_top[$top->term_id] = (!is_wp_error($children) && $children) ? $children : [];
    }
}

/* ── 2. Main content context detection ──────────────────────── */
$is_tax     = is_tax('product_category');
$is_archive = is_post_type_archive('product');

$current_term = null;
$is_top_level = false;     // 顶级分类（parent=0）
$is_sub_level = false;     // 二级分类（parent>0）
$children     = [];         // 当前顶级下的所有二级分类

if ($is_tax) {
    $current_term = get_queried_object();
    if ((int) $current_term->parent === 0) {
        $is_top_level = true;
        $children = get_terms([
            'taxonomy'   => 'product_category',
            'hide_empty' => false,
            'parent'     => (int) $current_term->term_id,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]);
        if (is_wp_error($children)) $children = [];
    } else {
        $is_sub_level = true;
    }
}

$current_id = $is_tax ? (int) get_queried_object_id() : 0;
if ($is_tax) {
    $page_title = single_cat_title('', false);
} else {
    // Shop archive (/product/): use shop page's post title if configured.
    $shop_id = (int) get_option('woocommerce_shop_page_id');
    if ($shop_id) {
        $shop_post = get_post($shop_id);
        if ($shop_post && !is_wp_error($shop_post) && $shop_post->post_title) {
            // On the English site the shop page is the shared Chinese
            // page, so its title would leak "产品中心" into the English
            // hero. Use the English archive title instead.
            if (function_exists('xxdq_is_en') && xxdq_is_en()) {
                $page_title = post_type_archive_title('', false);
            } else {
                $page_title = $shop_post->post_title;
            }
        } else {
            $page_title = post_type_archive_title('', false);
        }
    } else {
        $page_title = post_type_archive_title('', false);
    }
}
$page_desc  = $is_tax
    ? xxdq_term_description($current_term)
    : xxdq_t('archive.select_hint');

/* ── 3. Build products query ─────────────────────────────────── */
$main_q  = null;        // single-level (archive / sub-level): flat list
$groups   = [];         // archive: products grouped by top product_category
$unnamed  = [];         // archive: products with no product_category assigned

if ($is_top_level) {
    // Top-level: render the sub-cat grid, no product query needed.
    $main_q = null;
} else {
    $tax_q = ($is_sub_level) ? [[
        'taxonomy' => 'product_category',
        'field'    => 'term_id',
        'terms'    => $current_id,
    ]] : [];

    $main_q = new WP_Query([
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'tax_query'      => $tax_q,
        /* The English site keeps a parallel set of products. This is a
         * secondary query (not the main query), so the router's
         * pre_get_posts scoping does NOT apply here — we have to scope
         * it ourselves or the Chinese archive would list the English
         * products as well. */
        'meta_query'     => xxdq_is_en()
            ? [['key' => '_xxdq_lang', 'value' => 'en_US']]
            : [['key' => '_xxdq_lang', 'compare' => 'NOT EXISTS']],
    ]);

    /* Archive page: group products by their TOP-level product_category. */
    if ($main_q && $main_q->have_posts()) {
        // Pre-fetch top-level cat id for each sub-cat (one query).
        /* The English site keeps a parallel set of categories, so the
         * lookup tables below must only contain the current language's
         * terms — otherwise English products would be grouped under
         * Chinese category names. */
        $lang_term_ids = xxdq_current_lang_term_ids();

        $all_top = get_terms([
            'taxonomy'   => 'product_category',
            'hide_empty' => false,
            'parent'     => 0,
            'fields'     => 'id=>parent',
            'include'    => xxdq_current_lang_term_ids(),
        ]);
        // Build sub-cat → top-cat lookup so we can group products by their top ancestor.
        $sub_to_top = [];
        if (!is_wp_error($all_top) && $all_top) {
            $subs = get_terms([
                'taxonomy'   => 'product_category',
                'hide_empty' => false,
                'parent__not_in' => [0],
                'fields'     => 'id=>parent',
                'include'    => $lang_term_ids,
            ]);
            if (!is_wp_error($subs) && $subs) {
                foreach ($subs as $sub_id => $parent_id) {
                    $sub_to_top[(int)$sub_id] = (int)$parent_id;
                }
            }
        }
        $top_names = [];
        if (!is_wp_error($all_top) && $all_top) {
            $top_terms = get_terms([
                'taxonomy'   => 'product_category',
                'hide_empty' => false,
                'parent'     => 0,
                'include'    => $lang_term_ids,
            ]);
            if (!is_wp_error($top_terms)) {
                foreach ($top_terms as $t) $top_names[(int)$t->term_id] = xxdq_term_name($t);
            }
        }

        while ($main_q->have_posts()) {
            $main_q->the_post();
            $pid = get_the_ID();
            $title  = get_the_title();
            /* On the English site the `model` meta holds the Chinese type
             * description, so we read the `model_en` variant instead. */
            if (xxdq_is_en()) {
                $model = get_post_meta($pid, 'model_en', true);
                if ($model === '' || $model === false) {
                    $model = function_exists('get_field') ? get_field('model', $pid) : '';
                }
            } else {
                $model = function_exists('get_field') ? get_field('model', $pid) : '';
            }
            $desc   = function_exists('get_field') ? get_field('short_desc', $pid) : '';
            $gallery  = function_exists('xxdq_product_gallery') ? xxdq_product_gallery($pid, 'medium') : [];
            $thumb    = $gallery[0] ?? '';
            if (empty($thumb)) {
                $fid = get_post_thumbnail_id($pid);
                if ($fid) {
                    $thumb = wp_get_attachment_image_url($fid, 'medium', false);
                }
            }

            $item = [
                'id'    => $pid,
                'title' => $title,
                'model' => $model,
                'desc'  => $desc,
                'url'   => xxdq_permalink($pid),
                'thumb' => $thumb,
            ];

            if ($is_sub_level) {
                // Sub-level page: flat list, no grouping.
                $groups['_flat'] = ['name' => '', 'items' => &$unnamed];
                $unnamed[] = $item;
                unset($groups);
                $groups = [];
            } else {
                // Archive page: group by top-level product_category.
                $cats = wp_get_post_terms($pid, 'product_category', ['fields' => 'all']);
                $group_key = '_orphan';
                $group_name = xxdq_t('archive.other_models');
                if (!empty($cats) && !is_wp_error($cats)) {
                    // Pick the lowest-level cat the product belongs to.
                    $cat = $cats[0];
                    $top_id = ((int)$cat->parent === 0) ? (int)$cat->term_id : ($sub_to_top[(int)$cat->term_id] ?? 0);
                    if ($top_id && isset($top_names[$top_id])) {
                        $group_key  = 'top_' . $top_id;
                        $group_name = $top_names[$top_id];
                    }
                }
                if (!isset($groups[$group_key])) {
                    $groups[$group_key] = ['name' => $group_name, 'items' => []];
                }
                $groups[$group_key]['items'][] = $item;
            }
        }
        wp_reset_postdata();
        // Stable order by group name.
        uasort($groups, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
    }
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
/* ── Breadcrumb (below hero, supports 2-level hierarchy) ───── */
$bc_archive_url = xxdq_url('/product/');
$bc_parts = [
    ['url' => xxdq_url('/'),   'label' => xxdq_t('crumb.home')],
    ['url' => $bc_archive_url, 'label' => xxdq_t('nav.products')],
];
if ($is_tax && $current_term && $current_term->taxonomy === 'product_category') {
    // Walk up the parent chain (category is hierarchical).
    $chain = [];
    $cur   = $current_term;
    while ($cur && !is_wp_error($cur)) {
        $chain[] = $cur;
        $cur     = $cur->parent ? get_term($cur->parent, 'product_category') : null;
    }
    $chain = array_reverse($chain);
    foreach ($chain as $ancestor) {
        $bc_parts[] = [
            'url'   => xxdq_term_link($ancestor),
            'label' => xxdq_term_name($ancestor),
        ];
    }
} elseif (false) { /* legacy product_series breadcrumb removed */ }
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
    <h3 class="xxdq-product-archive__sidebar-title"><?php echo esc_html(xxdq_t('archive.sidebar_title')); ?></h3>
    <ul>
      <li class="<?php echo (!$is_tax) ? 'is-active' : ''; ?>">
        <a href="<?php echo esc_url(xxdq_url('/product/')); ?>#xxdq-product-main"><?php echo esc_html(xxdq_t('archive.all_products')); ?></a>
      </li>
      <?php if (!empty($sidebar_top_cats) && !is_wp_error($sidebar_top_cats)): ?>
        <?php foreach ($sidebar_top_cats as $top): ?>
          <?php
            $is_active_top    = ($is_tax && (int) $current_term->parent === 0 && (int) $current_term->term_id === (int) $top->term_id);
            $is_active_parent = ($is_tax && $is_sub_level && $current_term && (int) $current_term->parent === (int) $top->term_id);
            $show_sub         = $is_active_top || $is_active_parent;
          ?>
          <li class="<?php echo ($is_active_top || $is_active_parent) ? 'is-active' : ''; ?>">
            <a href="<?php echo esc_url(xxdq_term_link($top)); ?>#xxdq-product-main">
              <?php echo esc_html(xxdq_term_name($top)); ?>
              <span class="xxdq-product-archive__count">(<?php echo (int) $top->count; ?>)</span>
            </a>
            <?php if ($show_sub && !empty($sidebar_sub_by_top[$top->term_id])):
                /* 顶级被选中（或其子被选中）时，展开显示其下的二级分类 */
            ?>
              <ul class="xxdq-product-archive__sidebar-sub">
                <?php foreach ($sidebar_sub_by_top[$top->term_id] as $sub): ?>
                  <?php $is_active_sub = ($is_tax && $is_sub_level && (int) $current_term->term_id === (int) $sub->term_id); ?>
                  <li class="<?php echo $is_active_sub ? 'is-active' : ''; ?>">
                    <a href="<?php echo esc_url(xxdq_term_link($sub)); ?>#xxdq-product-main">
                      <?php echo esc_html(xxdq_term_name($sub)); ?>
                      <span class="xxdq-product-archive__count">(<?php echo (int) $sub->count; ?>)</span>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      <?php endif; ?>
    </ul>
  </aside>

  <section id="xxdq-product-main" class="xxdq-product-archive__main">
    <?php if ($is_top_level): ?>
      <?php
        /* ── 一级分类页：展示二级分类卡片网格 ───────────── */
        $featured = !empty($children) ? $children[0] : null;   // 简单用第一个作为视觉焦点
      ?>
      <?php if (empty($children)): ?>
        <p class="xxdq-empty"><?php echo esc_html(xxdq_t('archive.no_subcats')); ?></p>
      <?php else: ?>
        <div class="xxdq-subcat-grid">
          <?php foreach ($children as $i => $sub): ?>
            <a class="xxdq-subcat-card<?php echo $i === 0 ? ' is-featured' : ''; ?>" href="<?php echo esc_url(xxdq_term_link($sub)); ?>">
              <div class="xxdq-subcat-card__no"><?php echo esc_html(sprintf('%02d', $i + 1)); ?></div>
              <div class="xxdq-subcat-card__body">
                <h3 class="xxdq-subcat-card__title"><?php echo esc_html(xxdq_term_name($sub)); ?></h3>
                <?php if (!empty($sub->description) && $sub->description !== $sub->name): ?>
                  <p class="xxdq-subcat-card__sub"><?php echo esc_html(xxdq_term_description_raw($sub)); ?></p>
                <?php endif; ?>                <p class="xxdq-subcat-card__meta">
                  <span class="xxdq-subcat-card__count"><?php echo esc_html(sprintf(xxdq_t('archive.model_count'), (int) $sub->count)); ?></span>
                  <span class="xxdq-subcat-card__cta"><?php echo esc_html(xxdq_t('archive.view_product')); ?></span>
                </p>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    <?php elseif (empty($groups) && empty($unnamed)): ?>
      <p class="xxdq-empty"><?php echo esc_html(xxdq_t('archive.empty')); ?></p>
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
              <h2 class="xxdq-product-group__title"><?php echo esc_html(xxdq_t('archive.other_models')); ?></h2>
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

<?php /* 产品分类页：SPA 风格侧边栏切换（不刷新页面、不滚动）*/ ?>
<?php
/* The comments inside the <script> block are shipped to the browser, so
 * they must be localised — but the Chinese output has to stay
 * BYTE-IDENTICAL to the pre-i18n baseline. The whole script is emitted
 * from one heredoc so that no newline is swallowed by `?>`.
 *
 * The English variant additionally fixes the SPA scope check, which
 * hard-coded the Chinese site paths and therefore never matched on
 * /en/... . */
$spa_note_1   = '老浏览器降级到原生跳转';
$spa_note_2   = '判断链接是否在 SPA 作用域内（站内产品分类/归档/系列页）*/';
$spa_note_3   = '不滚动 — 保持用户当前的浏览位置（SPA 的核心好处）*/';
$spa_note_4   = '降级：原生跳转';
$spa_note_5   = '拦截站内分类链接的点击 */';
$spa_note_6   = '浏览器后退/前进 */';
$spa_scope_1  = "p === '/product/'";
$spa_scope_2  = "p.indexOf('/product-category/') === 0";

if (function_exists('xxdq_is_en') && xxdq_is_en()) {
    $spa_note_1  = 'old browsers fall back to a native navigation';
    $spa_note_2  = 'check whether the link is inside the SPA scope (in-site category / archive / series pages) */';
    $spa_note_3  = 'do not scroll — keep the visitor at the current position (the main benefit of the SPA) */';
    $spa_note_4  = 'fallback: native navigation';
    $spa_note_5  = 'intercept clicks on in-site category links */';
    $spa_note_6  = 'browser back / forward */';
    $spa_scope_1 = "p === '/en/product/'";
    $spa_scope_2 = "p.indexOf('/en/product-category/') === 0";
}

echo <<<JS
<script>
(function () {
  if (!window.history || !window.fetch) return; // {$spa_note_1}

  var hero  = document.querySelector('.xxdq-product-archive__hero');
  var bc    = document.querySelector('.xxdq-product-archive__bc');
  var main  = document.getElementById('xxdq-product-main');
  var side  = document.querySelector('.xxdq-product-archive__sidebar');
  var layout = document.querySelector('.xxdq-product-archive__layout');
  if (!main || !side) return;

  var busy = false;

  function setLoading(on) {
    document.body.classList.toggle('xxdq-spa-loading', !!on);
  }

  /* {$spa_note_2}
  function inScope(a) {
    if (!a || !a.href) return false;
    if (a.target === '_blank') return false;
    if (a.origin !== location.origin) return false;
    var p = a.pathname;
    return {$spa_scope_1} ||
           {$spa_scope_2};
  }

  function swap(newDoc) {
    var t = newDoc.querySelector('title');                   if (t) document.title = t.textContent;
    var h = newDoc.querySelector('.xxdq-product-archive__hero'); if (h && hero) hero.innerHTML = h.innerHTML;
    var b = newDoc.querySelector('.xxdq-product-archive__bc');   if (b && bc)   bc.innerHTML   = b.innerHTML;
    var m = newDoc.querySelector('#xxdq-product-main');           if (m && main) main.innerHTML = m.innerHTML;
    var s = newDoc.querySelector('.xxdq-product-archive__sidebar'); if (s && side) side.innerHTML = s.innerHTML;
  }

  async function navigate(url, pushState) {
    if (busy) return;
    busy = true;
    setLoading(true);
    try {
      var resp = await fetch(url, {
        credentials: 'same-origin',
        headers: { 'X-XXDQ-SPA': '1' },
      });
      if (!resp.ok) throw new Error('HTTP ' + resp.status);
      var html = await resp.text();
      var doc  = new DOMParser().parseFromString(html, 'text/html');
      swap(doc);
      if (pushState) history.pushState({ url: url }, '', url);
      /* {$spa_note_3}
    } catch (err) {
      console.error('SPA nav failed:', err);
      window.location.href = url;          // {$spa_note_4}
    } finally {
      busy = false;
      setLoading(false);
    }
  }

  /* {$spa_note_5}
  document.addEventListener('click', function (e) {
    if (e.defaultPrevented) return;
    if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || e.button !== 0) return;
    var a = e.target.closest('a');
    if (!inScope(a)) return;
    e.preventDefault();
    navigate(a.href, true);
  });

  /* {$spa_note_6}
  window.addEventListener('popstate', function (e) {
    var url = (e.state && e.state.url) || location.href;
    navigate(url, false);
  });
})();
</script>

JS;
?>

<?php get_footer();
