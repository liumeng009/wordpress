/* XXDQ - Main JS
 *
 * Vanilla JS, no jQuery.
 *
 * GeneratePress 3.5 dropped jQuery entirely and this site never
 * enqueued it, so the previous jQuery-based version of this file
 * threw a ReferenceError on load and NOTHING in it ran — the mobile
 * burger menu silently did nothing.
 */
(function () {
  'use strict';

  /* ── Mobile burger menu ────────────────────────────────────────
   * The icons are Unicode escapes rather than literal characters:
   * a literal "☰" can be mangled by an editor that saves in another
   * encoding, and a mangled byte inside a string literal breaks the
   * whole file. An escape sequence cannot be corrupted that way. */
  var ICON_MENU = '\u2630';   /* ☰ */
  var ICON_CLOSE = '\u2715';  /* ✕ */

  var MOBILE_MAX = 900;

  function isMobile() {
    return window.innerWidth <= MOBILE_MAX;
  }

  function setMenuOpen(open) {
    document.body.classList.toggle('xxp-menu-open', open);
    var burger = document.getElementById('xxp-nav-burger');
    if (burger) {
      burger.textContent = open ? ICON_CLOSE : ICON_MENU;
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
  }

  function initBurger() {
    var burger = document.getElementById('xxp-nav-burger');
    if (!burger) return;

    burger.setAttribute('aria-expanded', 'false');

    burger.addEventListener('click', function (e) {
      e.preventDefault();
      setMenuOpen(!document.body.classList.contains('xxp-menu-open'));
    });

    /* Close the panel when a menu link is followed. The dropdown
     * toggle itself is excluded — it opens a sub-menu instead. */
    var links = document.querySelectorAll('.xxp-nav__menu a');
    for (var i = 0; i < links.length; i++) {
      links[i].addEventListener('click', function (e) {
        if (!isMobile()) return;
        if (this.classList.contains('xxp-nav__toggle')) return;
        setMenuOpen(false);
      });
    }
  }

  /* ── Dropdown: hover on desktop, tap on mobile ──────────────── */
  function initDropdowns() {
    var parents = document.querySelectorAll('.xxp-nav__has-sub');

    for (var i = 0; i < parents.length; i++) {
      (function (parent) {
        var toggle = parent.querySelector(':scope > a.xxp-nav__toggle');
        if (!toggle) return;

        toggle.addEventListener('click', function (e) {
          /* Desktop keeps the link clickable. */
          if (!isMobile()) return;
          e.preventDefault();
          e.stopPropagation();

          /* Close the other open sub-menus first. */
          for (var j = 0; j < parents.length; j++) {
            if (parents[j] !== parent) {
              parents[j].classList.remove('xxp-nav__has-sub--open');
            }
          }
          parent.classList.toggle('xxp-nav__has-sub--open');
        });
      })(parents[i]);
    }

    /* Clicking outside closes any open sub-menu. */
    document.addEventListener('click', function (e) {
      var target = e.target;
      if (target && target.closest && target.closest('.xxp-nav__has-sub')) return;
      for (var j = 0; j < parents.length; j++) {
        parents[j].classList.remove('xxp-nav__has-sub--open');
      }
    });
  }

  /* ── Wire selector button hover effect ─────────────────────── */
  function initWireButtons() {
    var buttons = document.querySelectorAll('.xxdq-wire-btn');
    for (var i = 0; i < buttons.length; i++) {
      (function (btn) {
        btn.addEventListener('mouseenter', function () {
          var sibs = btn.parentNode ? btn.parentNode.children : [];
          for (var j = 0; j < sibs.length; j++) {
            if (sibs[j] !== btn) sibs[j].style.opacity = '0.7';
          }
        });
        btn.addEventListener('mouseleave', function () {
          var sibs = btn.parentNode ? btn.parentNode.children : [];
          for (var j = 0; j < sibs.length; j++) {
            sibs[j].style.opacity = '1';
          }
        });
      })(buttons[i]);
    }
  }

  /* ── Smooth scroll for anchor links ────────────────────────── */
  function initSmoothScroll() {
    var anchors = document.querySelectorAll('a[href^="#"]');
    for (var i = 0; i < anchors.length; i++) {
      anchors[i].addEventListener('click', function (e) {
        var href = this.getAttribute('href');
        if (!href || href === '#') return;
        var target = document.querySelector(href);
        if (!target) return;
        e.preventDefault();
        var top = target.getBoundingClientRect().top + window.pageYOffset - 80;
        window.scrollTo({ top: top, behavior: 'smooth' });
      });
    }
  }

  /* ── Entrance animations ───────────────────────────────────── */
  function initEntranceAnimations() {
    if (!('IntersectionObserver' in window)) return;

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.style.opacity = '1';
        entry.target.style.transform = 'translateY(0)';
        observer.unobserve(entry.target);
      });
    }, { threshold: 0.1 });

    var cats = document.querySelectorAll('.xxp-cat-card');
    for (var i = 0; i < cats.length; i++) {
      cats[i].style.opacity = '0';
      cats[i].style.transform = 'translateY(20px)';
      cats[i].style.transition = 'opacity 0.4s ease ' + (i * 0.08) + 's, transform 0.4s ease ' + (i * 0.08) + 's';
      observer.observe(cats[i]);
    }

    var hots = document.querySelectorAll('.xxp-hot-card');
    for (var k = 0; k < hots.length; k++) {
      hots[k].style.opacity = '0';
      hots[k].style.transform = 'translateY(16px)';
      hots[k].style.transition = 'opacity 0.35s ease ' + (k * 0.06) + 's, transform 0.35s ease ' + (k * 0.06) + 's';
      observer.observe(hots[k]);
    }
  }

  /* ── Hero slider ───────────────────────────────────────────── */
  function initSlider(slider) {
    var slides = slider.querySelectorAll('.xxdq-hero-slider__slide');
    var dots = slider.querySelectorAll('.xxdq-hero-slider__dot');
    var prev = slider.querySelector('.xxdq-hero-slider__arrow--prev');
    var next = slider.querySelector('.xxdq-hero-slider__arrow--next');
    if (slides.length === 0) return;

    var current = 0;
    var total = slides.length;
    var autoplay = parseInt(slider.dataset.autoplay, 10) || 5000;
    var timer = null;

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
    for (var i = 0; i < dots.length; i++) {
      (function (dot, idx) {
        dot.addEventListener('click', function () { stop(); go(idx); start(); });
      })(dots[i], i);
    }
    slider.addEventListener('mouseenter', stop);
    slider.addEventListener('mouseleave', start);

    slides[0].classList.add('xxdq-hero-slider__slide--active');
    if (dots[0]) dots[0].classList.add('xxdq-hero-slider__dot--active');
    start();
  }

  function initSliders() {
    var sliders = document.querySelectorAll('.xxdq-hero-slider');
    for (var i = 0; i < sliders.length; i++) initSlider(sliders[i]);
  }

  function boot() {
    initBurger();
    initDropdowns();
    initWireButtons();
    initSmoothScroll();
    initEntranceAnimations();
    initSliders();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
