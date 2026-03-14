(function () {
  'use strict';

  // Lazy load images (native loading="lazy" is used in HTML; this adds IntersectionObserver fallback for older browsers)
  if ('IntersectionObserver' in window) {
    const lazyImages = document.querySelectorAll('.product-card-image img[loading="lazy"]');
    const imageObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          const img = entry.target;
          if (img.dataset.src) {
            img.src = img.dataset.src;
            img.removeAttribute('data-src');
          }
          imageObserver.unobserve(img);
        }
      });
    }, { rootMargin: '50px' });

    lazyImages.forEach(function (img) {
      imageObserver.observe(img);
    });
  }

  // Apply wishlist state to product cards on load
  document.addEventListener('DOMContentLoaded', function () {
    if (typeof applyWishlistState === 'function') {
      applyWishlistState();
    }
  });

})();


