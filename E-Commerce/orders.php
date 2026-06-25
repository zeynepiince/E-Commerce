<?php
require_once 'functions.php';
require_once __DIR__ . '/orders/OrderStatusService.php';
require_once __DIR__ . '/orders/TrackingService.php';

$user_id = require_login();
ensure_order_fulfillment_columns($pdo);

$stmt = $pdo->prepare("
  SELECT order_id AS id, total_amount, status, payment_status, tracking_number, carrier,
         shipped_at, delivered_at, created_at
  FROM orders
  WHERE user_id = ?
  ORDER BY created_at DESC
  LIMIT 10
");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll();

$orderItemsByOrder = [];
if ($orders) {
    $orderIds = array_column($orders, 'id');
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    try {
        $sqlItems = "
          SELECT oi.order_id, oi.product_id, oi.quantity, oi.unit_price, p.name, p.image_url
          FROM order_items oi
          LEFT JOIN products p ON p.product_id = oi.product_id
          WHERE oi.order_id IN ($placeholders)
        ";
        $stmtItems = $pdo->prepare($sqlItems);
        $stmtItems->execute($orderIds);
        foreach ($stmtItems->fetchAll() as $row) {
            $oid = $row['order_id'];
            if (!isset($orderItemsByOrder[$oid])) $orderItemsByOrder[$oid] = [];
            $orderItemsByOrder[$oid][] = array_merge($row, [
                'name' => $row['name'] ?? 'Product',
                'image_url' => $row['image_url'] ?? 'https://images.unsplash.com/photo-1542291026-7eec264c27ff'
            ]);
        }
    } catch (PDOException $e) {
        $stmtItems = $pdo->prepare("SELECT order_id, product_id, quantity, unit_price FROM order_items WHERE order_id IN ($placeholders)");
        $stmtItems->execute($orderIds);
        foreach ($stmtItems->fetchAll() as $row) {
            $oid = $row['order_id'];
            if (!isset($orderItemsByOrder[$oid])) $orderItemsByOrder[$oid] = [];
            $orderItemsByOrder[$oid][] = array_merge($row, ['name' => 'Product', 'image_url' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff']);
        }
    }
}

$page_title = t("meta.orders_title", "ZERA - Orders");
$is_orders = true;
$page_footer_scripts = '<script>
window.ORDERS_I18N = ' . json_encode([
    'viewDetails' => t('orders.view_details', 'View details'),
    'hideDetails' => t('orders.hide_details', 'Hide details'),
    'trackOrder' => t('orders.track_order', 'Track order'),
    'hideTracking' => t('orders.hide_tracking', 'Hide tracking'),
    'cancelling' => t('orders.cancelling', 'Cancelling...'),
    'cancelOrder' => t('orders.cancel_order', 'Cancel order'),
    'cancelConfirm' => t('orders.cancel_confirm', 'Do you want to cancel this order?'),
    'cancelFailed' => t('orders.cancel_failed', 'Order could not be cancelled.'),
    'cancelled' => t('orders.status.cancelled', 'Cancelled'),
    'reorderEmpty' => t('orders.reorder_empty', 'No items to reorder.'),
    'reorderAdded' => t('orders.reorder_added', 'Items added to cart.'),
], JSON_UNESCAPED_UNICODE) . ';
</script>
<script src="' . htmlspecialchars(asset_url('assets/js/orders.js'), ENT_QUOTES, 'UTF-8') . '?v='
    . urlencode((string) @filemtime(__DIR__ . '/assets/js/orders.js'))
    . '"></script>';
?>
<?php include 'includes/header.php'; ?>

<link rel="stylesheet" href="<?= htmlspecialchars(asset_url('assets/css/orders.css'), ENT_QUOTES, 'UTF-8') ?>">

<div class="orders-page">
  <aside class="orders-sidebar">
    <nav class="orders-quick-nav">
      <a href="<?= htmlspecialchars(localized_path('profile.php'), ENT_QUOTES, 'UTF-8') ?>" class="orders-quick-link">
        <span class="orders-quick-icon">👤</span>
        <?= htmlspecialchars(t("orders.profile", "Profile"), ENT_QUOTES, 'UTF-8') ?>
      </a>
      <a href="<?= htmlspecialchars(localized_path('wishlist.php'), ENT_QUOTES, 'UTF-8') ?>" class="orders-quick-link">
        <span class="orders-quick-icon">♥</span>
        <?= htmlspecialchars(t("orders.wishlist", "Wishlist"), ENT_QUOTES, 'UTF-8') ?>
      </a>
      <a href="<?= htmlspecialchars(localized_path('checkout.php'), ENT_QUOTES, 'UTF-8') ?>" class="orders-quick-link">
        <span class="orders-quick-icon">🛒</span>
        <?= htmlspecialchars(t("orders.cart", "Cart"), ENT_QUOTES, 'UTF-8') ?>
      </a>
    </nav>
  </aside>

  <main class="orders-main">
    <header class="orders-header">
      <h1 class="orders-title"><?= htmlspecialchars(t("orders.title", "Your Orders"), ENT_QUOTES, 'UTF-8') ?></h1>
      <p class="orders-subtitle"><?= htmlspecialchars(t("orders.subtitle", "Track and manage your recent orders"), ENT_QUOTES, 'UTF-8') ?></p>
      <?php
        $paymentFlash = (string) ($_GET['payment'] ?? '');
        if ($paymentFlash === 'success') {
            echo '<p class="orders-flash orders-flash--success">' . htmlspecialchars(t('orders.payment.success', 'Your payment was successful.'), ENT_QUOTES, 'UTF-8') . '</p>';
        } elseif ($paymentFlash === 'failed') {
            echo '<p class="orders-flash orders-flash--failed">' . htmlspecialchars(t('orders.payment.failed', 'Payment could not be completed.'), ENT_QUOTES, 'UTF-8') . '</p>';
        } elseif ($paymentFlash === 'error') {
            echo '<p class="orders-flash orders-flash--failed">' . htmlspecialchars(t('orders.payment.error', 'Payment verification error.'), ENT_QUOTES, 'UTF-8') . '</p>';
        }
      ?>
    </header>

    <div class="orders-status-legend">
      <span class="orders-legend-item orders-legend--pending"><?= htmlspecialchars(t("orders.status.pending", "Pending"), ENT_QUOTES, 'UTF-8') ?></span>
      <span class="orders-legend-item orders-legend--shipped"><?= htmlspecialchars(t("orders.status.shipped", "Shipped"), ENT_QUOTES, 'UTF-8') ?></span>
      <span class="orders-legend-item orders-legend--delivered"><?= htmlspecialchars(t("orders.status.delivered", "Delivered"), ENT_QUOTES, 'UTF-8') ?></span>
      <span class="orders-legend-item orders-legend--cancelled"><?= htmlspecialchars(t("orders.status.cancelled", "Cancelled"), ENT_QUOTES, 'UTF-8') ?></span>
    </div>

    <div class="orders-toolbar">
      <div class="orders-filter">
        <label for="orders-filter-status"><?= htmlspecialchars(t("orders.filter", "Filter"), ENT_QUOTES, 'UTF-8') ?>:</label>
        <select id="orders-filter-status" class="orders-select">
          <option value=""><?= htmlspecialchars(t("orders.all_statuses", "All statuses"), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="processing"><?= htmlspecialchars(t("orders.status.processing", "Processing"), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="awaiting_payment"><?= htmlspecialchars(t("orders.status.awaiting_payment", "Awaiting payment"), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="pending"><?= htmlspecialchars(t("orders.status.pending", "Pending"), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="shipped"><?= htmlspecialchars(t("orders.status.shipped", "Shipped"), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="delivered"><?= htmlspecialchars(t("orders.status.delivered", "Delivered"), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="cancelled"><?= htmlspecialchars(t("orders.status.cancelled", "Cancelled"), ENT_QUOTES, 'UTF-8') ?></option>
        </select>
      </div>
      <div class="orders-sort">
        <label for="orders-sort"><?= htmlspecialchars(t("orders.sort", "Sort"), ENT_QUOTES, 'UTF-8') ?>:</label>
        <select id="orders-sort" class="orders-select">
          <option value="date-desc"><?= htmlspecialchars(t("orders.sort.newest", "Newest first"), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="date-asc"><?= htmlspecialchars(t("orders.sort.oldest", "Oldest first"), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="total-desc"><?= htmlspecialchars(t("orders.sort.highest_total", "Highest total"), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="total-asc"><?= htmlspecialchars(t("orders.sort.lowest_total", "Lowest total"), ENT_QUOTES, 'UTF-8') ?></option>
        </select>
      </div>
    </div>

    <?php if ($orders): ?>
      <div class="orders-list" id="ordersList">
        <?php foreach ($orders as $order): ?>
          <?php
            $status = $order['status'] ?? 'pending';
            $paymentStatus = normalize_order_payment_status((string) ($order['payment_status'] ?? 'paid'));
            $statusClass = 'orders-card--' . $status;
            $statusKey = resolve_order_display_status_key((string) $status, (string) ($order['payment_status'] ?? 'paid'));
            $oid = (int) $order['id'];
            $items = $orderItemsByOrder[$oid] ?? [];
            $itemCount = count($items);
            $reorderItems = array_map(static function (array $item): array {
                return [
                    'product_id' => (int) ($item['product_id'] ?? 0),
                    'name' => (string) ($item['name'] ?? 'Product'),
                    'quantity' => (int) ($item['quantity'] ?? 1),
                    'unit_price' => (float) ($item['unit_price'] ?? 0),
                    'image_url' => (string) ($item['image_url'] ?? ''),
                ];
            }, $items);
            $canCancel = $status === 'pending' && in_array($paymentStatus, ['awaiting_payment', 'failed', 'paid'], true);
          ?>
          <article class="orders-card <?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>" data-order-id="<?= $oid ?>" data-status="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>" data-display-status="<?= htmlspecialchars($statusKey, ENT_QUOTES, 'UTF-8') ?>" data-payment-status="<?= htmlspecialchars($paymentStatus, ENT_QUOTES, 'UTF-8') ?>" data-total="<?= htmlspecialchars($order['total_amount'], ENT_QUOTES, 'UTF-8') ?>" data-date="<?= htmlspecialchars($order['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-order-items="<?= htmlspecialchars(json_encode($reorderItems, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
            <div class="orders-card-header">
              <div class="orders-card-summary">
                <h3 class="orders-card-id"><?= htmlspecialchars(t("orders.order", "Order"), ENT_QUOTES, 'UTF-8') ?> #<?= htmlspecialchars($order['id'], ENT_QUOTES, 'UTF-8') ?></h3>
                <div class="orders-card-meta">
                  <span class="orders-card-date"><?= htmlspecialchars($order['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                  <span class="orders-card-total">$<?= htmlspecialchars($order['total_amount'] ?? '0', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
              </div>
              <span class="orders-card-status orders-status--<?= htmlspecialchars($statusKey, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t('orders.status.' . $statusKey, ucfirst($statusKey)), ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <?php if (!empty($items)): ?>
              <div class="orders-card-thumbnails">
                <?php foreach (array_slice($items, 0, 4) as $item): ?>
                  <img src="<?= htmlspecialchars($item['image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="" class="orders-thumb">
                <?php endforeach; ?>
                <?php if ($itemCount > 4): ?>
                  <span class="orders-thumb-more">+<?= $itemCount - 4 ?></span>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <div class="orders-card-actions">
              <button type="button" class="orders-btn orders-btn--primary orders-btn-details" data-order="<?= $oid ?>" data-action="details">
                <?= htmlspecialchars(t("orders.view_details", "View details"), ENT_QUOTES, 'UTF-8') ?>
              </button>
              <?php if ($status !== 'cancelled' && $status !== 'delivered'): ?>
                <button type="button" class="orders-btn orders-btn--secondary orders-btn-track" data-order="<?= $oid ?>" data-action="track">
                  <?= htmlspecialchars(t("orders.track_order", "Track order"), ENT_QUOTES, 'UTF-8') ?>
                </button>
              <?php endif; ?>
              <?php if ($canCancel): ?>
                <button type="button" class="orders-btn orders-btn--outline orders-btn-cancel" data-order="<?= $oid ?>" data-action="cancel">
                  <?= htmlspecialchars(t("orders.cancel_order", "Cancel order"), ENT_QUOTES, 'UTF-8') ?>
                </button>
              <?php endif; ?>
              <?php if (!empty($reorderItems)): ?>
                <button type="button" class="orders-btn orders-btn--outline orders-btn-reorder" data-order="<?= $oid ?>" data-action="reorder">
                  <?= htmlspecialchars(t("orders.reorder", "Reorder"), ENT_QUOTES, 'UTF-8') ?>
                </button>
              <?php endif; ?>
            </div>

            <div class="orders-card-details" id="order-details-<?= $oid ?>" hidden>
              <div class="orders-details-inner">
                <?php if ($items): ?>
                  <h4 class="orders-details-title"><?= htmlspecialchars(t("orders.items", "Order items"), ENT_QUOTES, 'UTF-8') ?></h4>
                  <div class="orders-items-list">
                    <?php foreach ($items as $item): ?>
                      <div class="orders-item">
                        <img src="<?= htmlspecialchars($item['image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="" class="orders-item-img">
                        <div class="orders-item-info">
                          <strong><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                          <span class="orders-item-meta"><?= htmlspecialchars(t("orders.qty", "Qty"), ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars($item['quantity'], ENT_QUOTES, 'UTF-8') ?> × $<?= htmlspecialchars($item['unit_price'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <span class="orders-item-subtotal">$<?= number_format((float)$item['quantity'] * (float)$item['unit_price'], 2) ?></span>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <div class="orders-details-totals">
                    <div class="orders-total-row">
                      <span><?= htmlspecialchars(t("orders.subtotal", "Subtotal"), ENT_QUOTES, 'UTF-8') ?></span>
                      <span>$<?= htmlspecialchars($order['total_amount'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="orders-total-row orders-total-row--shipping">
                      <span><?= htmlspecialchars(t("orders.shipping", "Shipping"), ENT_QUOTES, 'UTF-8') ?></span>
                      <span><?= htmlspecialchars(t("orders.free", "Free"), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="orders-total-row orders-total-row--final">
                      <span><?= htmlspecialchars(t("orders.total", "Total"), ENT_QUOTES, 'UTF-8') ?></span>
                      <span>$<?= htmlspecialchars($order['total_amount'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                  </div>
                <?php else: ?>
                  <p class="orders-no-items"><?= htmlspecialchars(t("orders.no_details", "Order details are not available."), ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
              </div>
            </div>

            <?php
              $trackingTimeline = build_order_tracking_timeline($order, get_current_lang());
              $trackingNumber = trim((string) ($order['tracking_number'] ?? ''));
              $trackingUrl = tracking_external_url((string) ($order['carrier'] ?? ''), $trackingNumber);
              $etaDate = estimate_delivery_date($order);
            ?>
            <div class="orders-card-details" id="order-tracking-<?= $oid ?>" hidden>
              <div class="orders-details-inner">
                <h4 class="orders-details-title"><?= htmlspecialchars(t("orders.tracking_title", "Tracking"), ENT_QUOTES, 'UTF-8') ?></h4>

                <div class="orders-tracking-timeline">
                  <?php foreach ($trackingTimeline as $step): ?>
                    <?php
                      $stepClass = 'orders-tracking-step';
                      if (!empty($step['done'])) {
                          $stepClass .= ' orders-tracking-step--done';
                      }
                      if (!empty($step['active'])) {
                          $stepClass .= ' orders-tracking-step--active';
                      }
                    ?>
                    <div class="<?= htmlspecialchars($stepClass, ENT_QUOTES, 'UTF-8') ?>">
                      <span class="orders-tracking-label"><?= htmlspecialchars(t($step['label_key'], $step['key']), ENT_QUOTES, 'UTF-8') ?></span>
                      <?php if (!empty($step['date'])): ?>
                        <span class="orders-tracking-date"><?= htmlspecialchars((string) $step['date'], ENT_QUOTES, 'UTF-8') ?></span>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>

                <?php if ($etaDate && $status !== 'delivered' && $status !== 'cancelled'): ?>
                  <p class="orders-tracking-eta">
                    <?= htmlspecialchars(t('orders.tracking_eta', 'Estimated delivery'), ENT_QUOTES, 'UTF-8') ?>:
                    <strong><?= htmlspecialchars($etaDate, ENT_QUOTES, 'UTF-8') ?></strong>
                  </p>
                <?php endif; ?>

                <?php if ($trackingNumber !== ''): ?>
                  <div class="orders-tracking-box">
                    <p class="orders-tracking-number">
                      <?= htmlspecialchars(t('orders.tracking_number', 'Tracking number'), ENT_QUOTES, 'UTF-8') ?>:
                      <strong id="tracking-num-<?= $oid ?>"><?= htmlspecialchars($trackingNumber, ENT_QUOTES, 'UTF-8') ?></strong>
                    </p>
                    <?php if (!empty($order['carrier'])): ?>
                      <p class="orders-tracking-carrier">
                        <?= htmlspecialchars(t('orders.carrier', 'Carrier'), ENT_QUOTES, 'UTF-8') ?>:
                        <?= htmlspecialchars((string) $order['carrier'], ENT_QUOTES, 'UTF-8') ?>
                      </p>
                    <?php endif; ?>
                    <div class="orders-tracking-actions">
                      <button type="button" class="orders-btn orders-btn--outline orders-btn-copy-tracking" data-copy-target="tracking-num-<?= $oid ?>">
                        <?= htmlspecialchars(t('orders.copy_tracking', 'Copy number'), ENT_QUOTES, 'UTF-8') ?>
                      </button>
                      <?php if ($trackingUrl): ?>
                        <a href="<?= htmlspecialchars($trackingUrl, ENT_QUOTES, 'UTF-8') ?>" class="orders-btn orders-btn--secondary" target="_blank" rel="noopener noreferrer">
                          <?= htmlspecialchars(t('orders.track_external', 'Track on carrier site'), ENT_QUOTES, 'UTF-8') ?>
                        </a>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php elseif ($paymentStatus === 'paid' && $status === 'pending'): ?>
                  <p class="orders-tracking-hint"><?= htmlspecialchars(t('orders.track_preparing', 'Your order is being prepared for shipment.'), ENT_QUOTES, 'UTF-8') ?></p>
                <?php elseif ($paymentStatus === 'awaiting_payment'): ?>
                  <p class="orders-tracking-hint"><?= htmlspecialchars(t('orders.track_awaiting_payment', 'Tracking will be available after payment is completed.'), ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="orders-empty">
        <p class="orders-empty-text"><?= htmlspecialchars(t("orders.empty", "No orders yet."), ENT_QUOTES, 'UTF-8') ?></p>
        <a href="<?= htmlspecialchars(localized_path('products.php'), ENT_QUOTES, 'UTF-8') ?>" class="orders-empty-btn"><?= htmlspecialchars(t("orders.start_shopping", "Start shopping"), ENT_QUOTES, 'UTF-8') ?></a>
      </div>
    <?php endif; ?>
  </main>
</div>

<?php include 'includes/footer.php'; ?>
