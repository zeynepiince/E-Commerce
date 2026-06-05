(function () {
  'use strict';

  const filterSelect = document.getElementById('orders-filter-status');
  const sortSelect = document.getElementById('orders-sort');
  const ordersList = document.getElementById('ordersList');
  const i18n = window.ORDERS_I18N || {};
  const t = {
    viewDetails: i18n.viewDetails || 'View details',
    hideDetails: i18n.hideDetails || 'Hide details',
    trackOrder: i18n.trackOrder || 'Track order',
    hideTracking: i18n.hideTracking || 'Hide tracking',
    cancelling: i18n.cancelling || 'Cancelling...',
    cancelOrder: i18n.cancelOrder || 'Cancel order',
    cancelConfirm: i18n.cancelConfirm || 'Do you want to cancel this order?',
    cancelFailed: i18n.cancelFailed || 'Order could not be cancelled.',
    cancelled: i18n.cancelled || 'Cancelled',
    reorderEmpty: i18n.reorderEmpty || 'No items to reorder.',
    reorderAdded: i18n.reorderAdded || 'Items added to cart.'
  };

  function appUrl(path) {
    if (typeof window.appUrl === 'function') {
      return window.appUrl(path);
    }
    const url = new URL(path, window.location.href);
    const lang = typeof window.APP_LANG === 'string' ? window.APP_LANG : '';
    if (lang && !url.searchParams.get('lang')) {
      url.searchParams.set('lang', lang);
    }
    return url.toString();
  }

  function getOrderCards() {
    if (!ordersList) return [];
    return Array.from(ordersList.querySelectorAll('.orders-card'));
  }

  function closePanel(panel) {
    if (!panel) return;
    panel.hidden = true;
    panel.classList.remove('orders-card-details--open');
  }

  function openPanel(panel) {
    if (!panel) return;
    panel.hidden = false;
    panel.classList.add('orders-card-details--open');
    panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function togglePanel(orderId, type, btn) {
    const detailsEl = document.getElementById('order-details-' + orderId);
    const trackEl = document.getElementById('order-tracking-' + orderId);
    const target = type === 'details' ? detailsEl : trackEl;
    const other = type === 'details' ? trackEl : detailsEl;
    if (!target) return;

    const willOpen = target.hidden || !target.classList.contains('orders-card-details--open');
    closePanel(other);

    if (willOpen) {
      openPanel(target);
      if (btn) {
        btn.textContent = type === 'details' ? t.hideDetails : t.hideTracking;
        btn.setAttribute('aria-expanded', 'true');
      }
      const otherBtn = btn && btn.closest('.orders-card')
        ? btn.closest('.orders-card').querySelector(type === 'details' ? '.orders-btn-track' : '.orders-btn-details')
        : null;
      if (otherBtn) {
        otherBtn.textContent = type === 'details' ? t.trackOrder : t.viewDetails;
        otherBtn.setAttribute('aria-expanded', 'false');
      }
      return;
    }

    closePanel(target);
    if (btn) {
      btn.textContent = type === 'details' ? t.viewDetails : t.trackOrder;
      btn.setAttribute('aria-expanded', 'false');
    }
  }

  function cancelOrder(orderId, btn) {
    if (!orderId || !btn) return;
    if (!window.confirm(t.cancelConfirm)) return;

    btn.disabled = true;
    const prevText = btn.textContent;
    btn.textContent = t.cancelling;

    const body = new URLSearchParams();
    body.set('order_id', String(orderId));
    if (typeof window.CSRF_TOKEN === 'string' && window.CSRF_TOKEN) {
      body.set('csrf_token', window.CSRF_TOKEN);
    }

    fetch(appUrl('cancel_order.php'), {
      method: 'POST',
      headers: (typeof window.csrfHeaders === 'function'
        ? window.csrfHeaders({
            'Content-Type': 'application/x-www-form-urlencoded',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          })
        : {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          }),
      credentials: 'same-origin',
      body: body.toString()
    })
      .then(function (res) {
        return res.json().catch(function () {
          throw new Error('invalid_json');
        }).then(function (data) {
          if (!res.ok) throw new Error((data && data.error) || 'request_failed');
          return data;
        });
      })
      .then(function (data) {
        if (!data || !data.success) {
          throw new Error((data && data.error) || t.cancelFailed);
        }

        const card = btn.closest('.orders-card');
        if (card) {
          card.dataset.status = 'cancelled';
          card.dataset.displayStatus = 'cancelled';
          card.classList.remove('orders-card--pending', 'orders-card--shipped', 'orders-card--delivered', 'orders-card--processing');
          card.classList.add('orders-card--cancelled');

          const statusEl = card.querySelector('.orders-card-status');
          if (statusEl) {
            statusEl.className = 'orders-card-status orders-status--cancelled';
            statusEl.textContent = t.cancelled;
          }

          closePanel(document.getElementById('order-details-' + orderId));
          closePanel(document.getElementById('order-tracking-' + orderId));

          const trackBtn = card.querySelector('.orders-btn-track');
          if (trackBtn) trackBtn.remove();
        }
        btn.remove();
      })
      .catch(function (err) {
        alert(err && err.message && err.message !== 'invalid_json' ? err.message : t.cancelFailed);
        btn.disabled = false;
        btn.textContent = prevText || t.cancelOrder;
      });
  }

  function reorder(orderId) {
    const card = document.querySelector('.orders-card[data-order-id="' + orderId + '"]');
    if (!card) return;

    let items = [];
    try {
      items = JSON.parse(card.dataset.orderItems || '[]');
    } catch (e) {
      items = [];
    }
    if (!Array.isArray(items) || items.length === 0) {
      alert(t.reorderEmpty);
      return;
    }

    if (typeof window.addToCart !== 'function') {
      window.location.href = appUrl('products.php');
      return;
    }

    items.forEach(function (item) {
      const id = item.product_id || item.id;
      const qty = Math.max(1, parseInt(item.quantity, 10) || 1);
      const name = item.name || 'Product';
      const price = parseFloat(item.unit_price || item.price || 0);
      const imageUrl = item.image_url || item.imageUrl || '';
      for (let i = 0; i < qty; i += 1) {
        window.addToCart(id, name, price, imageUrl);
      }
    });

    if (typeof window.goToCheckout === 'function') {
      window.goToCheckout();
      return;
    }
    window.location.href = appUrl('checkout.php');
  }

  function applyFilterAndSort() {
    const cards = getOrderCards();
    if (!cards.length) return;

    const filter = filterSelect ? filterSelect.value : '';
    const sort = sortSelect ? sortSelect.value : 'date-desc';

    cards.forEach(function (card) {
      const displayStatus = card.dataset.displayStatus || card.dataset.status || '';
      const matchesFilter = !filter || displayStatus === filter;
      card.style.display = matchesFilter ? '' : 'none';
    });

    const visibleCards = cards.filter(function (c) { return c.style.display !== 'none'; });

    visibleCards.sort(function (a, b) {
      if (sort === 'date-desc') {
        return (b.dataset.date || '').localeCompare(a.dataset.date || '');
      }
      if (sort === 'date-asc') {
        return (a.dataset.date || '').localeCompare(b.dataset.date || '');
      }
      if (sort === 'total-desc') {
        return parseFloat(b.dataset.total || 0) - parseFloat(a.dataset.total || 0);
      }
      if (sort === 'total-asc') {
        return parseFloat(a.dataset.total || 0) - parseFloat(b.dataset.total || 0);
      }
      return 0;
    });

    visibleCards.forEach(function (card) {
      ordersList.appendChild(card);
    });
  }

  document.addEventListener('click', function (event) {
    const copyBtn = event.target.closest('.orders-btn-copy-tracking');
    if (copyBtn) {
      const targetId = copyBtn.getAttribute('data-copy-target');
      const el = targetId ? document.getElementById(targetId) : null;
      const text = el ? el.textContent.trim() : '';
      if (text && navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).catch(function () {});
      }
      return;
    }
  });

  if (ordersList) {
    ordersList.addEventListener('click', function (event) {
      const btn = event.target.closest('[data-action]');
      if (!btn || !ordersList.contains(btn)) return;

      const orderId = btn.getAttribute('data-order');
      const action = btn.getAttribute('data-action');
      if (!orderId || !action) return;

      if (action === 'details' || action === 'track') {
        event.preventDefault();
        togglePanel(orderId, action, btn);
        return;
      }
      if (action === 'cancel') {
        event.preventDefault();
        cancelOrder(orderId, btn);
        return;
      }
      if (action === 'reorder') {
        event.preventDefault();
        reorder(orderId);
      }
    });
  }

  if (filterSelect) {
    filterSelect.addEventListener('change', applyFilterAndSort);
  }

  if (sortSelect) {
    sortSelect.addEventListener('change', applyFilterAndSort);
  }
})();
