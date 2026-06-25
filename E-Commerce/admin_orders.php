<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/orders/OrderStatusService.php';

require_admin();
ensure_order_fulfillment_columns($pdo);

$stmt = $pdo->query("
  SELECT o.order_id, o.user_id, o.total_amount, o.status, o.payment_status,
         o.tracking_number, o.carrier, o.shipped_at, o.delivered_at, o.created_at,
         u.full_name, u.email
  FROM orders o
  LEFT JOIN users u ON u.user_id = o.user_id
  ORDER BY o.created_at DESC
  LIMIT 50
");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = t('admin.orders_title', 'Order Management | ZERA');
$is_admin_orders = true;
$page_footer_scripts = '<script src="' . htmlspecialchars(asset_url('assets/js/admin_orders.js'), ENT_QUOTES, 'UTF-8') . '?v='
    . urlencode((string) @filemtime(__DIR__ . '/assets/js/admin_orders.js'))
    . '"></script>';
include 'includes/header.php';
?>

<link rel="stylesheet" href="<?= htmlspecialchars(asset_url('assets/css/admin_orders.css'), ENT_QUOTES, 'UTF-8') ?>">

<main class="admin-orders-page">
  <div class="admin-orders-container">
    <header class="admin-orders-header">
      <div>
        <h1 class="admin-orders-title"><?= htmlspecialchars(t('admin.orders_heading', 'Order management'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="admin-orders-subtitle"><?= htmlspecialchars(t('admin.orders_subtitle', 'Update fulfillment status for paid orders.'), ENT_QUOTES, 'UTF-8') ?></p>
      </div>
      <a href="<?= htmlspecialchars(localized_path('orders.php'), ENT_QUOTES, 'UTF-8') ?>" class="admin-orders-back">
        <?= htmlspecialchars(t('admin.back_orders', '← My orders'), ENT_QUOTES, 'UTF-8') ?>
      </a>
    </header>

    <?php if (!$orders): ?>
      <p class="admin-orders-empty"><?= htmlspecialchars(t('admin.no_orders', 'No orders yet.'), ENT_QUOTES, 'UTF-8') ?></p>
    <?php else: ?>
      <div class="admin-orders-table-wrap">
        <table class="admin-orders-table">
          <thead>
            <tr>
              <th><?= htmlspecialchars(t('admin.col_order', 'Order'), ENT_QUOTES, 'UTF-8') ?></th>
              <th><?= htmlspecialchars(t('admin.col_customer', 'Customer'), ENT_QUOTES, 'UTF-8') ?></th>
              <th><?= htmlspecialchars(t('admin.col_total', 'Total'), ENT_QUOTES, 'UTF-8') ?></th>
              <th><?= htmlspecialchars(t('admin.col_payment', 'Payment'), ENT_QUOTES, 'UTF-8') ?></th>
              <th><?= htmlspecialchars(t('admin.col_status', 'Status'), ENT_QUOTES, 'UTF-8') ?></th>
              <th><?= htmlspecialchars(t('admin.col_tracking', 'Tracking'), ENT_QUOTES, 'UTF-8') ?></th>
              <th><?= htmlspecialchars(t('admin.col_actions', 'Actions'), ENT_QUOTES, 'UTF-8') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orders as $order): ?>
              <?php
                $oid = (int) ($order['order_id'] ?? 0);
                $status = strtolower((string) ($order['status'] ?? 'pending'));
                $paymentStatus = normalize_order_payment_status((string) ($order['payment_status'] ?? 'paid'));
                $statusKey = resolve_order_display_status_key($status, (string) ($order['payment_status'] ?? 'paid'));
                $nextActions = order_status_transitions()[$status] ?? [];
              ?>
              <tr data-order-id="<?= $oid ?>" data-status="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>">
                <td>
                  <strong>#<?= $oid ?></strong>
                  <div class="admin-orders-meta"><?= htmlspecialchars((string) ($order['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                </td>
                <td>
                  <div><?= htmlspecialchars((string) ($order['full_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="admin-orders-meta"><?= htmlspecialchars((string) ($order['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                </td>
                <td>$<?= htmlspecialchars(number_format((float) ($order['total_amount'] ?? 0), 2), ENT_QUOTES, 'UTF-8') ?></td>
                <td><span class="admin-badge admin-badge--<?= htmlspecialchars($paymentStatus, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($paymentStatus, ENT_QUOTES, 'UTF-8') ?></span></td>
                <td><span class="admin-badge admin-badge--<?= htmlspecialchars($statusKey, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t('orders.status.' . $statusKey, $statusKey), ENT_QUOTES, 'UTF-8') ?></span></td>
                <td>
                  <?php if (!empty($order['tracking_number'])): ?>
                    <div><?= htmlspecialchars((string) $order['tracking_number'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="admin-orders-meta"><?= htmlspecialchars((string) ($order['carrier'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                  <?php else: ?>
                    <span class="admin-orders-meta">—</span>
                  <?php endif; ?>
                </td>
                <td class="admin-orders-actions">
                  <?php if (in_array('shipped', $nextActions, true) && $paymentStatus === 'paid'): ?>
                    <div class="admin-ship-form">
                      <select class="admin-input admin-carrier-input">
                        <option value="ZERA Kargo">ZERA Kargo</option>
                        <option value="Yurtiçi Kargo">Yurtiçi Kargo</option>
                        <option value="Aras Kargo">Aras Kargo</option>
                        <option value="MNG Kargo">MNG Kargo</option>
                        <option value="PTT Kargo">PTT Kargo</option>
                      </select>
                      <input type="text" class="admin-input admin-tracking-input" placeholder="<?= htmlspecialchars(t('admin.tracking_placeholder', 'Tracking no. (optional)'), ENT_QUOTES, 'UTF-8') ?>" maxlength="64" value="<?= htmlspecialchars('ZERA' . str_pad((string) $oid, 6, '0', STR_PAD_LEFT), ENT_QUOTES, 'UTF-8') ?>">
                      <button type="button" class="admin-btn admin-btn--ship" data-action="shipped">
                        <?= htmlspecialchars(t('admin.mark_shipped', 'Mark shipped'), ENT_QUOTES, 'UTF-8') ?>
                      </button>
                    </div>
                  <?php endif; ?>
                  <?php if (in_array('delivered', $nextActions, true)): ?>
                    <button type="button" class="admin-btn admin-btn--deliver" data-action="delivered">
                      <?= htmlspecialchars(t('admin.mark_delivered', 'Mark delivered'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                  <?php endif; ?>
                  <?php if (in_array('cancelled', $nextActions, true)): ?>
                    <button type="button" class="admin-btn admin-btn--cancel" data-action="cancelled">
                      <?= htmlspecialchars(t('admin.cancel_order', 'Cancel'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                  <?php endif; ?>
                  <?php if ($nextActions === []): ?>
                    <span class="admin-orders-meta"><?= htmlspecialchars(t('admin.no_actions', 'No actions'), ENT_QUOTES, 'UTF-8') ?></span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</main>

<script>
window.ADMIN_ORDERS_I18N = <?= json_encode([
    'confirmShip' => t('admin.confirm_ship', 'Mark this order as shipped?'),
    'confirmDeliver' => t('admin.confirm_deliver', 'Mark this order as delivered?'),
    'confirmCancel' => t('admin.confirm_cancel', 'Cancel this order and restore stock?'),
    'trackingRequired' => t('admin.tracking_required', 'Enter a tracking number.'),
    'updateFailed' => t('admin.update_failed', 'Could not update order.'),
    'updated' => t('admin.updated', 'Order updated.'),
], JSON_UNESCAPED_UNICODE) ?>;
</script>

<?php include 'includes/footer.php'; ?>
