/* ============================================================
   Launchpad — main.js
   Vanilla JS only. Each init guard-clauses missing elements.
   ============================================================ */
(function () {
  'use strict';

  var reduceMotion = window.matchMedia &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- header: solid on scroll ---------- */
  function initHeader() {
    var header = document.querySelector('[data-header]');
    if (!header) return;
    var onScroll = function () {
      header.classList.toggle('is-scrolled', window.scrollY > 40);
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  /* ---------- mobile navigation ---------- */
  function initMobileNav() {
    var toggle = document.querySelector('[data-nav-toggle]');
    var nav = document.getElementById('primary-menu');
    if (!toggle || !nav) return;

    var closeBtn = document.querySelector('[data-nav-close]');
    var scrim = document.createElement('div');
    scrim.className = 'nav-scrim';
    document.body.appendChild(scrim);

    var open = function () {
      document.body.classList.add('nav-open');
      toggle.setAttribute('aria-expanded', 'true');
    };
    var close = function () {
      document.body.classList.remove('nav-open');
      toggle.setAttribute('aria-expanded', 'false');
    };
    var toggleNav = function () {
      if (document.body.classList.contains('nav-open')) { close(); }
      else { open(); }
    };

    toggle.addEventListener('click', toggleNav);
    scrim.addEventListener('click', close);
    if (closeBtn) closeBtn.addEventListener('click', close);

    nav.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', close);
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') close();
    });
  }

  /* ---------- pricing: monthly / annual toggle ---------- */
  function initPricingToggle() {
    var sw = document.querySelector('[data-bill-switch]');
    var wrap = document.querySelector('[data-bill]');
    if (!sw || !wrap) return;

    var prices = document.querySelectorAll('[data-price]');
    var optMonthly = wrap.querySelector('[data-bill-monthly]');
    var optAnnual = wrap.querySelector('[data-bill-annual]');

    var render = function () {
      var annual = sw.getAttribute('aria-checked') === 'true';
      prices.forEach(function (el) {
        var val = annual ? el.getAttribute('data-annual') : el.getAttribute('data-monthly');
        if (val !== null) el.textContent = val;
      });
      if (optMonthly) optMonthly.classList.toggle('is-on', !annual);
      if (optAnnual) optAnnual.classList.toggle('is-on', annual);
    };

    var flip = function () {
      var annual = sw.getAttribute('aria-checked') === 'true';
      sw.setAttribute('aria-checked', annual ? 'false' : 'true');
      render();
    };

    sw.addEventListener('click', flip);
    if (optMonthly) optMonthly.addEventListener('click', function () {
      sw.setAttribute('aria-checked', 'false'); render();
    });
    if (optAnnual) optAnnual.addEventListener('click', function () {
      sw.setAttribute('aria-checked', 'true'); render();
    });

    render();
  }

  /* ---------- FAQ accordion (single open) ---------- */
  function initFaq() {
    var root = document.querySelector('[data-accordion]');
    if (!root) return;
    var items = root.querySelectorAll('.faq-item');

    var closeItem = function (item) {
      item.classList.remove('is-open');
      var btn = item.querySelector('.faq-q');
      var panel = item.querySelector('.faq-a');
      if (btn) btn.setAttribute('aria-expanded', 'false');
      if (panel) panel.style.maxHeight = null;
    };
    var openItem = function (item) {
      item.classList.add('is-open');
      var btn = item.querySelector('.faq-q');
      var panel = item.querySelector('.faq-a');
      if (btn) btn.setAttribute('aria-expanded', 'true');
      if (panel) panel.style.maxHeight = panel.scrollHeight + 'px';
    };

    items.forEach(function (item) {
      var btn = item.querySelector('.faq-q');
      if (!btn) return;
      btn.addEventListener('click', function () {
        var isOpen = item.classList.contains('is-open');
        items.forEach(closeItem);
        if (!isOpen) openItem(item);
      });
    });

    // keep an open panel sized correctly on resize
    window.addEventListener('resize', function () {
      var open = root.querySelector('.faq-item.is-open .faq-a');
      if (open) open.style.maxHeight = open.scrollHeight + 'px';
    });
  }

  /* ---------- scroll reveal ---------- */
  function initReveal() {
    var els = document.querySelectorAll('[data-reveal]');
    if (!els.length) return;

    if (reduceMotion || !('IntersectionObserver' in window)) {
      els.forEach(function (el) { el.classList.add('is-visible'); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });

    els.forEach(function (el) { io.observe(el); });
  }

  /* ---------- animated stat counters ---------- */
  function initCounters() {
    var nums = document.querySelectorAll('[data-count]');
    if (!nums.length) return;

    var format = function (el, value) {
      var prefix = el.getAttribute('data-prefix') || '';
      var suffix = el.getAttribute('data-suffix') || '';
      el.textContent = prefix + Math.round(value).toLocaleString('en-US') + suffix;
    };

    if (reduceMotion || !('IntersectionObserver' in window)) {
      nums.forEach(function (el) { format(el, parseFloat(el.getAttribute('data-count')) || 0); });
      return;
    }

    var run = function (el) {
      var target = parseFloat(el.getAttribute('data-count')) || 0;
      var dur = 1500, start = null;
      var step = function (ts) {
        if (start === null) start = ts;
        var p = Math.min((ts - start) / dur, 1);
        var eased = 1 - Math.pow(1 - p, 3);
        format(el, target * eased);
        if (p < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    };

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          run(entry.target);
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.5 });

    nums.forEach(function (el) { io.observe(el); });
  }

  /* ---------- CTA email form validation ---------- */
  function initForm() {
    var form = document.querySelector('[data-form]');
    if (!form) return;
    var field = form.querySelector('.field');
    var input = form.querySelector('input[type="email"]');
    var note = form.querySelector('[data-form-note]');
    if (!input) return;

    var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    var defaultNote = note ? note.textContent : '';

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var val = input.value.trim();
      if (!emailRe.test(val)) {
        if (field) field.classList.add('invalid');
        if (note) { note.textContent = 'Please enter a valid work email address.'; note.className = 'form-note err'; }
        input.focus();
        return;
      }
      if (field) field.classList.remove('invalid');
      if (note) { note.textContent = 'Thanks — check ' + val + ' for your launch link.'; note.className = 'form-note ok'; }
      form.reset();
      window.setTimeout(function () {
        if (note) { note.textContent = defaultNote; note.className = 'form-note'; }
      }, 6000);
    });

    input.addEventListener('input', function () {
      if (field) field.classList.remove('invalid');
    });
  }

  /* ---------- boot ---------- */
  function boot() {
    initHeader();
    initMobileNav();
    initPricingToggle();
    initFaq();
    initReveal();
    initCounters();
    initForm();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
