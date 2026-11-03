<?php
/**
 * News archive template — /news/.
 *
 * Layout:
 *   - Full-width dark gradient banner at the top
 *   - Breadcrumb below the banner (consistent with other inner pages)
 *   - Single-column news list (card style: date badge + title + excerpt + arrow)
 *   - Numbered pagination at the bottom
 *   - NO right sidebar (GeneratePress widget area is fully suppressed)
 */
if (!defined('ABSPATH')) exit;
get_header();

$is_en = function_exists('xxdq_is_en') && xxdq_is_en();

if ($is_en) {
    $news_title = 'News';
    $news_lede  = 'The latest from Gengwen Electric — product releases, technical articles and factory updates.';
    $crumb_home = 'Home';
    $empty_text = 'No news yet. Please check back soon.';
    $prev_text  = '← Previous';
    $next_text  = 'Next →';
} else {
    $news_title = '新闻中心';
    $news_lede  = '了解 乐清市耕文电气有限公司 最新动态 · 产品发布 · 技术资讯';
    $crumb_home = '首页';
    $empty_text = '暂无新闻,敬请期待。';
    $prev_text  = '← 上一页';
    $next_text  = '下一页 →';
}
?>

<header class="xxdq-news-archive__hero">
  <div class="xxdq-news-archive__hero-inner">
    <h1><?php echo esc_html($news_title); ?></h1>
    <p class="xxdq-news-archive__lede"><?php echo esc_html($news_lede); ?></p>
  </div>
</header>

<div class="xxdq-news-archive__container">
  <nav class="xxp-breadcrumb">
    <a href="<?php echo esc_url(xxdq_url('/')); ?>"><?php echo esc_html($crumb_home); ?></a>
    <span>›</span>
    <span class="xxp-breadcrumb__current"><?php echo esc_html($news_title); ?></span>
  </nav>

  <section class="xxdq-news-archive__layout">
    <?php if (have_posts()): ?>
      <ul class="xxdq-news-list">
        <?php while (have_posts()): the_post();
          $pid     = get_the_ID();
          $day     = get_the_date('d');
          $ym      = get_the_date('Y-m');
          $excerpt = has_excerpt($pid)
              ? get_the_excerpt()
              : wp_trim_words(wp_strip_all_tags(get_the_content()), 50, '…');
        ?>
          <li class="xxdq-news-list__item">
            <a class="xxdq-news-list__inner" href="<?php the_permalink(); ?>">
              <div class="xxdq-news-list__date">
                <span class="xxdq-news-list__day"><?php echo esc_html($day); ?></span>
                <span class="xxdq-news-list__year"><?php echo esc_html($ym); ?></span>
              </div>
              <div class="xxdq-news-list__info">
                <h2 class="xxdq-news-list__title"><?php the_title(); ?></h2>
                <p class="xxdq-news-list__excerpt"><?php echo esc_html($excerpt); ?></p>
              </div>
              <span class="xxdq-news-list__arrow" aria-hidden="true">›</span>
            </a>
          </li>
        <?php endwhile; ?>
      </ul>

      <div class="xxdq-pagination">
        <?php
          the_posts_pagination([
            'prev_text' => $prev_text,
            'next_text' => $next_text,
            'mid_size'  => 1,
          ]);
        ?>
      </div>
    <?php else: ?>
      <div class="xxdq-news-empty">
        <p><?php echo esc_html($empty_text); ?></p>
      </div>
    <?php endif; ?>
  </section>
</div>

<?php get_footer();
