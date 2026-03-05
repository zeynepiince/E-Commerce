<?php
require_once 'functions.php';

// Örnek: sabit user_id = 1 için son siparişler
$user_id = 1;

$stmt = $pdo->prepare("
  SELECT order_id AS id, total_amount, status, created_at
  FROM orders
  WHERE user_id = ?
  ORDER BY created_at DESC
  LIMIT 20
");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll();

// Order items + product info for collapsible sections
$orderItemsByOrder = [];
if ($orders) {
    $orderIds = array_column($orders, 'id');
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $sqlItems = "
      SELECT oi.order_id,
             oi.product_id,
             oi.quantity,
             oi.unit_price,
             p.name,
             p.image_url
      FROM order_items oi
      JOIN products p ON p.product_id = oi.product_id
      WHERE oi.order_id IN ($placeholders)
    ";
    $stmtItems = $pdo->prepare($sqlItems);
    $stmtItems->execute($orderIds);
    $rows = $stmtItems->fetchAll();
    foreach ($rows as $row) {
        $oid = $row['order_id'];
        if (!isset($orderItemsByOrder[$oid])) {
            $orderItemsByOrder[$oid] = [];
        }
        $orderItemsByOrder[$oid][] = $row;
    }
}

$page_title = "Your Orders – STORY";
?>

<?php include 'includes/header.php'; ?>

<section class="products">
  <h3>Your Orders</h3>
  <p class="subtitle">
    Recent orders for your account.
  </p>

  <?php if ($orders): ?>
    <div class="orders-card-list">
      <?php foreach ($orders as $order): ?>
        <?php
          $status = $order['status'];
          $statusClass = 'order-status-pending';
          if ($status === 'shipped') {
              $statusClass = 'order-status-shipped';
          } elseif ($status === 'delivered') {
              $statusClass = 'order-status-delivered';
          } elseif ($status === 'cancelled') {
              $statusClass = 'order-status-cancelled';
          }
          $oid = (int) $order['id'];
          $items = $orderItemsByOrder[$oid] ?? [];
        ?>
        <article class="order-card <?= $statusClass ?>">
          <header class="order-card-header">
            <div>
              <h4>#<?= htmlspecialchars($order['id'], ENT_QUOTES, 'UTF-8') ?></h4>
              <div class="order-meta">
                <span><?= htmlspecialchars($order['created_at'], ENT_QUOTES, 'UTF-8') ?></span>
                <span>Total: $<?= htmlspecialchars($order['total_amount'], ENT_QUOTES, 'UTF-8') ?></span>
              </div>
            </div>
            <div class="order-status-pill">
              <?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') ?>
            </div>
          </header>
          <div class="order-card-body">
            <div id="order-details-<?= $oid ?>" class="order-panel order-details-panel">
              <?php if ($items): ?>
                <div class="order-items">
                  <?php foreach ($items as $item): ?>
                    <div class="order-item">
                      <div class="order-item-image">
                        <img src="<?= htmlspecialchars($item['image_url'] ?: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff', ENT_QUOTES, 'UTF-8') ?>"
                             alt="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>">
                      </div>
                      <div class="order-item-info">
                        <strong><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <div class="order-item-meta">
                          <span>Qty: <?= htmlspecialchars($item['quantity'], ENT_QUOTES, 'UTF-8') ?></span>
                          <span>$<?= htmlspecialchars($item['unit_price'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="order-item-extra">
                          STORY Partner · Free shipping
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <p style="font-size:13px;color:#6b7280;">Order details are not available.</p>
              <?php endif; ?>
            </div>
            <div id="order-tracking-<?= $oid ?>" class="order-panel order-tracking-panel">
              <p>
                Your order is currently
                <strong><?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') ?></strong>.
              </p>
              <p>
                Estimated delivery:
                <strong>2–4 business days</strong>.
              </p>
              <p style="font-size:12px;color:#9ca3af;">
                Tracking information will appear here once the carrier updates the shipment.
              </p>
            </div>
          </div>
          <footer class="order-card-footer">
            <button type="button" class="order-btn" onclick="toggleOrderDetails(<?= $oid ?>)">
              View details
            </button>
            <button type="button" class="order-btn secondary" onclick="toggleOrderTracking(<?= $oid ?>)">
              Track order
            </button>
          </footer>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p>No orders yet.</p>
  <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>

