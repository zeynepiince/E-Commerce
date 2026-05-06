(function () {
  'use strict';

  const filterSelect = document.getElementById('orders-filter-status');
  const sortSelect = document.getElementById('orders-sort');
  const ordersList = document.getElementById('ordersList');
  const lang = (window.APP_LANG || 'en').toLowerCase();
  const tr = lang === 'tr';
  const t = {
    viewDetails: tr ? 'Detayları gör' : 'View details',
    hideDetails: tr ? 'Detayları gizle' : 'Hide details',
    trackOrder: tr ? 'Siparişi takip et' : 'Track order',
    hideTracking: tr ? 'Takibi gizle' : 'Hide tracking',
    cancelling: tr ? 'İptal ediliyor...' : 'Cancelling...',
    cancelOrder: tr ? 'Siparişi iptal et' : 'Cancel order',
    cancelConfirm: tr ? 'Bu siparişi iptal etmek istiyor musunuz?' : 'Do you want to cancel this order?',
    cancelFailed: tr ? 'Sipariş iptal edilemedi.' : 'Order could not be cancelled.',
    cancelled: tr ? 'İptal' : 'Cancelled'
  };

  window.orderToggleDetails = function (orderId, btn) {
    const detailsEl = document.getElementById('order-details-' + orderId);
    if (!detailsEl) return;
    const isOpen = detailsEl.classList.toggle('orders-card-details--open');
    if (btn) btn.textContent = isOpen ? t.hideDetails : t.viewDetails;
  };

  window.orderToggleTracking = function (orderId, btn) {
    const trackEl = document.getElementById('order-tracking-' + orderId);
    if (!trackEl) return;
    const isOpen = trackEl.classList.toggle('orders-card-details--open');
    if (btn) btn.textContent = isOpen ? t.hideTracking : t.trackOrder;
  };

  window.orderCancel = function (orderId, btn) {
    if (!orderId || !btn) return;
    if (!window.confirm(t.cancelConfirm)) return;
    btn.disabled = true;
    const prevText = btn.textContent;
    btn.textContent = t.cancelling;
    const body = new URLSearchParams();
    body.set('order_id', orderId);

    fetch('cancel_order.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString()
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (!data || !data.success) {
          alert((data && data.error) ? data.error : t.cancelFailed);
          btn.disabled = false;
          btn.textContent = prevText || t.cancelOrder;
          return;
        }
        const card = btn.closest('.orders-card');
        if (card) {
          card.dataset.status = 'cancelled';
          card.classList.remove('orders-card--pending', 'orders-card--shipped', 'orders-card--delivered');
          card.classList.add('orders-card--cancelled');
          const statusEl = card.querySelector('.orders-card-status');
          if (statusEl) {
            statusEl.classList.remove('orders-status--pending', 'orders-status--shipped', 'orders-status--delivered');
            statusEl.classList.add('orders-status--cancelled');
            statusEl.textContent = t.cancelled;
          }
          const trackBtn = card.querySelector('.orders-btn-track');
          if (trackBtn) trackBtn.remove();
        }
        btn.remove();
      })
      .catch(function () {
        alert(t.cancelFailed);
        btn.disabled = false;
        btn.textContent = prevText || t.cancelOrder;
      });
  };

  function getOrderCards() {
    if (!ordersList) return [];
    return Array.from(ordersList.querySelectorAll('.orders-card'));
  }

  function applyFilterAndSort() {
    const cards = getOrderCards();
    if (!cards.length) return;

    const filter = filterSelect ? filterSelect.value : '';
    const sort = sortSelect ? sortSelect.value : 'date-desc';

    // Filter
    cards.forEach(function (card) {
      const status = card.dataset.status || '';
      const matchesFilter = !filter || status === filter;
      card.style.display = matchesFilter ? '' : 'none';
    });

    // Sort visible cards
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

    // Reorder in DOM
    visibleCards.forEach(function (card) {
      ordersList.appendChild(card);
    });
  }

  if (filterSelect) {
    filterSelect.addEventListener('change', applyFilterAndSort);
  }

  if (sortSelect) {
    sortSelect.addEventListener('change', applyFilterAndSort);
  }
})();
