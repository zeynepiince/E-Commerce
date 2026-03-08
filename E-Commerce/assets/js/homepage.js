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
      } else {
        if (section.classList.contains('home-section')) {
          section.classList.add('home-section--animate');
        } else if (section.classList.contains('home-newsletter')) {
          section.classList.add('home-newsletter--animate');
        }
        observer.observe(section);
      }
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    initHomeHero();
    initHomeFlashCountdown();
    initScrollAnimations();
  });
})();
