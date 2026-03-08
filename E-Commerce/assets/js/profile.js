(function () {
  'use strict';

  // Sidebar nav: highlight active section on scroll
  const sections = document.querySelectorAll('.profile-section');
  const navItems = document.querySelectorAll('.profile-nav-item');

  function updateActiveNav() {
    const scrollY = window.scrollY + 120;
    let current = '';

    sections.forEach(function (section) {
      const top = section.offsetTop;
      const height = section.offsetHeight;
      if (scrollY >= top && scrollY < top + height) {
        current = section.getAttribute('id') || '';
      }
    });

    navItems.forEach(function (item) {
      const href = item.getAttribute('href') || '';
      const targetId = href.replace('#', '');
      item.classList.toggle('active', targetId === current);
    });
  }

  window.addEventListener('scroll', function () {
    requestAnimationFrame(updateActiveNav);
  });
  updateActiveNav();

  // Smooth scroll for nav links
  navItems.forEach(function (item) {
    item.addEventListener('click', function (e) {
      const href = item.getAttribute('href');
      if (href && href.startsWith('#')) {
        e.preventDefault();
        const target = document.querySelector(href);
        if (target) {
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      }
    });
  });

  // Render wishlist in profile (uses same localStorage as main.js)
  function renderProfileWishlist() {
    const container = document.getElementById('profileWishlistContainer');
    const emptyEl = document.getElementById('profileWishlistEmpty');
    if (!container) return;

    let favorites = [];
    try {
      const saved = localStorage.getItem('story_favorites');
      if (saved) {
        favorites = JSON.parse(saved);
      }
    } catch (e) {}

    container.innerHTML = '';

    if (!favorites.length) {
      if (emptyEl) emptyEl.style.display = 'block';
      return;
    }

    if (emptyEl) emptyEl.style.display = 'none';

    favorites.forEach(function (f) {
      const safeImage = f.imageUrl || 'https://images.unsplash.com/photo-1542291026-7eec264c27ff';
      const priceDisplay = typeof f.price === 'number' ? '$' + f.price : '';
      const card = document.createElement('div');
      card.className = 'profile-wishlist-card';
      card.innerHTML = `
        <img src="${safeImage}" alt="${(f.name || 'Product').replace(/"/g, '&quot;')}">
        <div class="profile-wishlist-card-content">
          <h4>${(f.name || 'Product').replace(/</g, '&lt;')}</h4>
          ${priceDisplay ? '<p class="price">' + priceDisplay + '</p>' : ''}
          <div class="profile-wishlist-actions">
            <button type="button" class="profile-wishlist-add" onclick="addFavoriteToCart(${f.id})">Add to Cart</button>
            <button type="button" class="profile-wishlist-remove" onclick="wishlistRemove(${f.id}); renderProfileWishlist();">Remove</button>
          </div>
        </div>
      `;
      container.appendChild(card);
    });
  }

  window.renderProfileWishlist = renderProfileWishlist;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      renderProfileWishlist();
    });
  } else {
    renderProfileWishlist();
  }
})();
