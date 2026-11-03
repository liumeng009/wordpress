<?php
/**
 * Single news template — /news/{post-slug}/.
 *
 * Layout:
 *   - Same dark gradient banner as archive-news (consistent branding).
 *   - Breadcrumb: 首页 › 新闻中心 › {title}.
 *   - Centered reading column (~800px) on a white card.
 *   - Article meta (date · author) below title.
 *   - Featured image (if set) above body.
 *   - Post body content with proper typography.
 *   - Multi-page break support.
 *   - Previous / next news nav + "back to list" button.
 *   - NO right sidebar (handled by generate_sidebar_layout filter in cpts.php).
 */
if (!defined('ABSPATH')) exit;
get_header();

$is_en = function_exists('xxdq_is_en') && xxdq_is_en();

if ($is_en) {
    $banner_title = 'News';
    $banner_lede  = 'The latest from Gengwen Electric — product releases, technical articles and factory updates.';
    $crumb_home   = 'Home';
    $crumb_archive = 'News';
    $prev_label   = '← Previous';
    $next_label   = 'Next →';
    $back_label   = 'Back to News List';
    $date_format  = 'F j, Y';
    $page_prefix  = 'Page:';
} else {
    $banner_title = '新闻中心';
    $banner_lede  = '了解 乐清市耕文电气有限公司 最新动态 · 产品发布 · 技术资讯';
    $crumb_home   = '首页';
    $crumb_archive = '新闻中心';
    $prev_label   = '← 上一篇';
    $next_label   = '下一篇 →';
    $back_label   = '返回新闻列表';
    $date_format  = 'Y 年 m 月 d 日';
    $page_prefix  = '分页:';
}
?>

<header class="xxdq-news-archive__hero xxdq-news-single__hero">
  <div class="xxdq-news-archive__hero-inner">
    <h2 class="xxdq-news-single__banner-title"><?php echo esc_html($banner_title); ?></h2>
    <p class="xxdq-news-archive__lede"><?php echo esc_html($banner_lede); ?></p>
  </div>
</header>

<div class="xxdq-news-single__container">
  <nav class="xxp-breadcrumb">
    <a href="<?php echo esc_url(xxdq_url('/')); ?>"><?php echo esc_html($crumb_home); ?></a>
    <span>›</span>
    <a href="<?php echo esc_url(get_post_type_archive_link('news')); ?>"><?php echo esc_html($crumb_archive); ?></a>
    <span>›</span>
    <span class="xxp-breadcrumb__current"><?php echo esc_html(wp_trim_words(get_the_title(), 14, '…')); ?></span>
  </nav>

  <article class="xxdq-news-single">
    <header class="xxdq-news-single__header">
      <h1 class="xxdq-news-single__title"><?php the_title(); ?></h1>
      <div class="xxdq-news-single__meta">
        <time datetime="<?php echo esc_attr(get_the_date('c')); ?>">
          <?php echo esc_html(get_the_date($date_format)); ?>
        </time>
        <span class="xxdq-news-single__sep">·</span>
        <span class="xxdq-news-single__author"><?php echo esc_html(get_the_author()); ?></span>
      </div>
    </header>

    <?php if (has_post_thumbnail()): ?>
      <figure class="xxdq-news-single__hero-img">
        <?php the_post_thumbnail('large', ['loading' => 'lazy']); ?>
        <?php if (get_the_post_thumbnail_caption()): ?>
          <figcaption class="xxdq-news-single__caption"><?php echo esc_html(get_the_post_thumbnail_caption()); ?></figcaption>
        <?php endif; ?>
      </figure>
    <?php endif; ?>

    <div class="xxdq-news-single__content">
      <?php
        the_content();
        wp_link_pages([
            'before'      => '<div class="xxdq-news-single__pages"><strong>' . esc_html($page_prefix) . '</strong>',
            'after'       => '</div>',
            'link_before' => '<span class="xxdq-news-single__page-num">',
            'link_after'  => '</span>',
        ]);
      ?>
    </div>

    <footer class="xxdq-news-single__footer">
      <?php
        $prev_post = get_previous_post(true, '', 'news');
        $next_post = get_next_post(true, '', 'news');
      ?>
      <nav class="xxdq-news-single__nav">
        <?php if ($prev_post): ?>
          <a class="xxdq-news-single__nav-link xxdq-news-single__nav-link--prev" href="<?php echo esc_url(get_permalink($prev_post)); ?>">
            <span class="xxdq-news-single__nav-label"><?php echo esc_html($prev_label); ?></span>
            <span class="xxdq-news-single__nav-title"><?php echo esc_html(wp_trim_words($prev_post->post_title, 18, '…')); ?></span>
          </a>
        <?php else: ?>
          <span class="xxdq-news-single__nav-link xxdq-news-single__nav-link--empty" aria-hidden="true"></span>
        <?php endif; ?>

        <a class="xxdq-news-single__back" href="<?php echo esc_url(get_post_type_archive_link('news')); ?>">
          <?php echo esc_html($back_label); ?>
        </a>

        <?php if ($next_post): ?>
          <a class="xxdq-news-single__nav-link xxdq-news-single__nav-link--next" href="<?php echo get_permalink($next_post); ?>">
            <span class="xxdq-news-single__nav-label"><?php echo esc_html($next_label); ?></span>
            <span class="xxdq-news-single__nav-title"><?php echo esc_html(wp_trim_words($next_post->post_title, 18, '…')); ?></span>
          </a>
        <?php else: ?>
          <span class="xxdq-news-single__nav-link xxdq-news-single__nav-link--empty" aria-hidden="true"></span>
        <?php endif; ?>
      </nav>

      <?php
        /* ── SEO 模块: 相关新闻 (同作者优先, 最新降级) ── */
        if (function_exists('xxdq_render_related_news')) {
          echo xxdq_render_related_news(get_the_ID(), 3);
        }
      ?>
    </footer>
  </article>
</div>

<?php get_footer();
