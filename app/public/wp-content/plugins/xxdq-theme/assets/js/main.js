/* XX鐢垫皵 鈥?Main JS */
(function($) {
  'use strict';

  $(function() {

    /* Mobile burger menu */
    $('#xxp-nav-burger').on('click', function() {
      $('body').toggleClass('xxp-menu-open');
      $(this).text($('body').hasClass('xxp-menu-open') ? '鉁? : '鈽?);
    });

    /* Close menu on link click (mobile) */
    $('.xxp-nav__menu a').on('click', function() {
      if ($(window).width() <= 900) {
        // Don't close if it's the dropdown toggle itself
        if ($(this).hasClass('xxp-nav__toggle')) return;
        $('body').removeClass('xxp-menu-open');
        $('#xxp-nav-burger').text('鈽?);
      }
    });

    /* Dropdown: hover for desktop, click for mobile/touch */
    var $hasSub = $('.xxp-nav__has-sub');
    var $toggle = $hasSub.children('a.xxp-nav__toggle');

    // Touch / click toggle (mobile only 鈥?desktop keeps the link clickable)
    $toggle.on('click', function(e) {
      if ($(window).width() > 900) return; // desktop: let link navigate
      e.preventDefault();
      e.stopPropagation();
      // Close other open sub-menus first
      $hasSub.not($(this).parent()).removeClass('xxp-nav__has-sub--open');
      $(this).parent().toggleClass('xxp-nav__has-sub--open');
    });

    // Close on outside click
    $(document).on('click', function(e) {
      if (!$(e.target).closest('.xxp-nav__has-sub').length) {
        $hasSub.removeClass('xxp-nav__has-sub--open');
      }
    });

    /* Wire selector button hover effect */
    $('.xxdq-wire-btn').on('mouseenter', function() {
      $(this).siblings().not(this).css('opacity', '0.7');
    }).on('mouseleave', function() {
      $('.xxdq-wire-btn').css('opacity', '1');
    });

  });

  /* Smooth scroll for anchor links */
  $('a[href^="#"]').on('click', function(e) {
    var target = $(this.getAttribute('href'));
    if (target.length) {
      e.preventDefault();
      $('html, body').stop().animate({ scrollTop: target.offset().top - 80 }, 400);
    }
  });

  /* Category card entrance animation */
  var catObserver = new IntersectionObserver(function(entries) {
    entries.forEach(function(entry) {
      if (entry.isIntersecting) {
        $(entry.target).css('opacity', '1').css('transform', 'translateY(0)');
        catObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });

  $('.xxp-cat-card').each(function(i) {
    $(this).css({
      opacity: '0',
      transform: 'translateY(20px)',
      transition: 'opacity 0.4s ease ' + (i * 0.08) + 's, transform 0.4s ease ' + (i * 0.08) + 's'
    });
    catObserver.observe(this);
  });

  /* Hot card entrance animation */
  $('.xxp-hot-card').each(function(i) {
    $(this).css({
      opacity: '0',
      transform: 'translateY(16px)',
      transition: 'opacity 0.35s ease ' + (i * 0.06) + 's, transform 0.35s ease ' + (i * 0.06) + 's'
    });
    catObserver.observe(this);
  });

})(jQuery);

/* ── Phase 9: Hero Slider ────────────────────────────
   Vanilla-JS slider: reads data-autoplay, shows prev/next arrows
   and dot navigation, autoplay-pauses on mouse-hover. */
(function () {
  'use strict';

  function initSlider(slider) {
    var slides = slider.querySelectorAll('.xxdq-hero-slider__slide');
    var dots  = slider.querySelectorAll('.xxdq-hero-slider__dot');
    var prev  = slider.querySelector('.xxdq-hero-slider__arrow--prev');
    var next  = slider.querySelector('.xxdq-hero-slider__arrow--next');
    if (slides.length === 0) return;

    var current  = 0;
    var total    = slides.length;
    var autoplay = parseInt(slider.dataset.autoplay, 10) || 5000;
    var timer    = null;

    function go(n) {
      slides[current].classList.remove('xxdq-hero-slider__slide--active');
      if (dots[current]) dots[current].classList.remove('xxdq-hero-slider__dot--active');
      current = ((n % total) + total) % total;
      slides[current].classList.add('xxdq-hero-slider__slide--active');
      if (dots[current]) dots[current].classList.add('xxdq-hero-slider__dot--active');
    }

    function start() {
      if (autoplay > 0) timer = setInterval(function () { go(current + 1); }, autoplay);
    }

    function stop() {
      if (timer) { clearInterval(timer); timer = null; }
    }

    if (prev) prev.addEventListener('click', function () { stop(); go(current - 1); start(); });
    if (next) next.addEventListener('click', function () { stop(); go(current + 1); start(); });
    dots.forEach(function (dot, i) {
      dot.addEventListener('click', function () { stop(); go(i); start(); });
    });
    slider.addEventListener('mouseenter', stop);
    slider.addEventListener('mouseleave', start);

    // Initialise first slide
    slides[0].classList.add('xxdq-hero-slider__slide--active');
    if (dots[0]) dots[0].classList.add('xxdq-hero-slider__dot--active');
    start();
  }

  function boot() {
    document.querySelectorAll('.xxdq-hero-slider').forEach(initSlider);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
