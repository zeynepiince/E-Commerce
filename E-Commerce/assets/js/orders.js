(function () {
  'use strict';

  const filterSelect = document.getElementById('orders-filter-status');
  const sortSelect = document.getElementById('orders-sort');
  const ordersList = document.getElementById('ordersList');

  // View details toggle
  document.querySelectorAll('.orders-btn-details').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const orderId = btn.dataset.order;
      const detailsEl = document.getElementById('order-details-' + orderId);
      if (!detailsEl) return;

      const isOpen = detailsEl.classList.toggle('orders-card-details--open');
      btn.textContent = isOpen ? 'Hide details' : 'View details';
    });
  });

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
