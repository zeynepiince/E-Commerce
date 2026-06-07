(function () {
  'use strict';

  // Hero slider
  function initHomeHero() {
    const slides = document.querySelectorAll('.home-hero-slide');
    const dots = document.querySelectorAll('.home-hero-dot');
    if (!slides.length || !dots.length) return;

    let current = 0;

    function showSlide(index) {
      current = (index + slides.length) % slides.length;
      slides.forEach((slide, i) => {
        slide.classList.toggle('home-hero-slide--active', i === current);
      });
      dots.forEach((dot, i) => {
        dot.classList.toggle('home-hero-dot--active', i === current);
      });
    }

    dots.forEach((dot, index) => {
      dot.addEventListener('click', () => showSlide(index));
    });

    setInterval(() => showSlide(current + 1), 6000);
  }

  // Flash countdown
  function initHomeFlashCountdown() {
    const timerEl = document.querySelector('.home-flash-timer');
    if (!timerEl) return;

    const hours = parseInt(timerEl.dataset.countdownHours || '6', 10);
    const endTime = Date.now() + hours * 60 * 60 * 1000;
    const displayEl = timerEl.querySelector('.home-flash-time');
    if (!displayEl) return;

    function update() {
      const diff = endTime - Date.now();
      if (diff <= 0) {
        displayEl.textContent = '00:00:00';
        return;
      }
      const totalSeconds = Math.floor(diff / 1000);
      const h = String(Math.floor(totalSeconds / 3600)).padStart(2, '0');
      const m = String(Math.floor((totalSeconds % 3600) / 60)).padStart(2, '0');
      const s = String(totalSeconds % 60).padStart(2, '0');
      displayEl.textContent = `${h}:${m}:${s}`;
      setTimeout(update, 1000);
    }
    update();
  }

  // Scroll-triggered animations (use classes, not inline styles, so visibility works)
  function initScrollAnimations() {
    const sections = document.querySelectorAll('.home-section, .home-newsletter');
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('home-visible');
            entry.target.classList.remove('home-section--animate', 'home-newsletter--animate');
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.05, rootMargin: '0px 0px -30px 0px' }
    );

    sections.forEach((section) => {
      const rect = section.getBoundingClientRect();
      const inView = rect.top < window.innerHeight && rect.bottom > 0;
      if (inView) {
        section.classList.add('home-visible');
      } else if (section.classList.contains('home-section')) {
        section.classList.add('home-section--animate');
        observer.observe(section);
      } else if (section.classList.contains('home-newsletter')) {
        section.classList.add('home-newsletter--animate');
        observer.observe(section);
      }
    });
  }

  /** UI-only newsletter signup (localStorage; no server / DB). */
  function initNewsletterForm() {
    const form = document.getElementById('homeNewsletterForm');
    const emailInput = document.getElementById('homeNewsletterEmail');
    const messageEl = document.getElementById('homeNewsletterMessage');
    const submitBtn = document.getElementById('homeNewsletterBtn');
    if (!form || !emailInput || !messageEl) return;

    const storageKey = 'zera_newsletter_email';

    function showMessage(text, type) {
      const message = String(text || '').trim();
      if (!message) {
        messageEl.hidden = true;
        messageEl.textContent = '';
        messageEl.className = 'newsletter-message';
        return;
      }
      messageEl.hidden = false;
      messageEl.textContent = message;
      messageEl.className = `newsletter-message newsletter-message--${type === 'success' ? 'success' : 'error'}`;
    }

    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const email = String(emailInput.value || '').trim();
      if (!email) return;

      const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
      if (!valid) {
        showMessage(form.dataset.msgInvalid || 'Please enter a valid email address.', 'error');
        return;
      }

      const normalized = email.toLowerCase();
      let stored = '';
      try {
        stored = String(localStorage.getItem(storageKey) || '').toLowerCase();
      } catch (err) {
        stored = '';
      }

      if (stored === normalized) {
        showMessage(form.dataset.msgAlready || 'You are already on our newsletter list.', 'success');
        return;
      }

      if (submitBtn) submitBtn.disabled = true;
      try {
        localStorage.setItem(storageKey, normalized);
      } catch (err) {
        /* ignore quota errors — still show success for demo UI */
      }
      showMessage(form.dataset.msgSuccess || 'Thanks! You have been added to our newsletter.', 'success');
      form.reset();
      if (submitBtn) submitBtn.disabled = false;
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    initHomeHero();
    initHomeFlashCountdown();
    initScrollAnimations();
    initNewsletterForm();
  });
})();
