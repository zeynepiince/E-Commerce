(function () {
  'use strict';

  const i18n = window.ADMIN_ORDERS_I18N || {};

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

  function confirmFor(action) {
    if (action === 'shipped') return i18n.confirmShip || 'Mark this order as shipped?';
    if (action === 'delivered') return i18n.confirmDeliver || 'Mark this order as delivered?';
    if (action === 'cancelled') return i18n.confirmCancel || 'Cancel this order?';
    return 'Continue?';
  }

  document.addEventListener('click', function (event) {
    const btn = event.target.closest('.admin-btn[data-action]');
    if (!btn) return;

    const row = btn.closest('tr[data-order-id]');
    if (!row) return;

    const orderId = row.getAttribute('data-order-id');
    const action = btn.getAttribute('data-action');
    if (!orderId || !action) return;

    const trackingInput = row.querySelector('.admin-tracking-input');
    const carrierInput = row.querySelector('.admin-carrier-input');
    const trackingNumber = trackingInput ? trackingInput.value.trim() : '';
    const carrier = carrierInput ? carrierInput.value.trim() : '';

    if (!window.confirm(confirmFor(action))) {
      return;
    }

    btn.disabled = true;
    const body = new URLSearchParams();
    body.set('order_id', orderId);
    body.set('status', action);
    if (trackingNumber !== '') {
      body.set('tracking_number', trackingNumber);
    }
    if (carrier !== '') {
      body.set('carrier', carrier);
    }
    if (typeof window.CSRF_TOKEN === 'string' && window.CSRF_TOKEN) {
      body.set('csrf_token', window.CSRF_TOKEN);
    }

    fetch(appUrl('admin_update_order.php'), {
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
        return res.json().then(function (data) {
          if (!res.ok) throw new Error((data && data.error) || 'request_failed');
          return data;
        });
      })
      .then(function (data) {
        if (!data || !data.success) {
          throw new Error((data && data.error) || (i18n.updateFailed || 'Update failed'));
        }
        window.location.reload();
      })
      .catch(function (err) {
        alert(err && err.message ? err.message : (i18n.updateFailed || 'Update failed'));
        btn.disabled = false;
      });
  });
})();
