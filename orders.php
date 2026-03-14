<?php
require_once 'functions.php';

if (empty($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("
  SELECT order_id AS id, total_amount, status, created_at
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

$page_title = "Your Orders – STORY";
?>
<?php include 'includes/header.php'; ?>

<link rel="stylesheet" href="assets/css/orders.css">

<div class="orders-page">
  <aside class="orders-sidebar">
    <nav class="orders-quick-nav">
      <a href="profile.php" class="orders-quick-link">
        <span class="orders-quick-icon">👤</span>
        Profile
      </a>
      <a href="wishlist.php" class="orders-quick-link">
        <span class="orders-quick-icon">♥</span>
        Wishlist
      </a>
      <a href="checkout.php" class="orders-quick-link">
        <span class="orders-quick-icon">🛒</span>
        Cart
      </a>
    </nav>
  </aside>

  <main class="orders-main">
    <header class="orders-header">
      <h1 class="orders-title">Your Orders</h1>
      <p class="orders-subtitle">Track and manage your recent orders</p>
    </header>

    <div class="orders-status-legend">
      <span class="orders-legend-item orders-legend--pending">Pending</span>
      <span class="orders-legend-item orders-legend--shipped">Shipped</span>
      <span class="orders-legend-item orders-legend--delivered">Delivered</span>
      <span class="orders-legend-item orders-legend--cancelled">Cancelled</span>
    </div>

    <div class="orders-toolbar">
      <div class="orders-filter">
        <label for="orders-filter-status">Filter:</label>
        <select id="orders-filter-status" class="orders-select">
          <option value="">All statuses</option>
          <option value="pending">Pending</option>
          <option value="shipped">Shipped</option>
          <option value="delivered">Delivered</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>
      <div class="orders-sort">
        <label for="orders-sort">Sort:</label>
        <select id="orders-sort" class="orders-select">
          <option value="date-desc">Newest first</option>
          <option value="date-asc">Oldest first</option>
          <option value="total-desc">Highest total</option>
          <option value="total-asc">Lowest total</option>
        </select>
      </div>
    </div>

    <?php if ($orders): ?>
      <div class="orders-list" id="ordersList">
        <?php foreach ($orders as $order): ?>
          <?php
            $status = $order['status'] ?? 'pending';
            $statusClass = 'orders-card--' . $status;
            $oid = (int) $order['id'];
            $items = $orderItemsByOrder[$oid] ?? [];
            $itemCount = count($items);
          ?>
          <article class="orders-card <?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>" data-order-id="<?= $oid ?>" data-status="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>" data-total="<?= htmlspecialchars($order['total_amount'], ENT_QUOTES, 'UTF-8') ?>" data-date="<?= htmlspecialchars($order['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <div class="orders-card-header">
              <div class="orders-card-summary">
                <h3 class="orders-card-id">Order #<?= htmlspecialchars($order['id'], ENT_QUOTES, 'UTF-8') ?></h3>
                <div class="orders-card-meta">
                  <span class="orders-card-date"><?= htmlspecialchars($order['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                  <span class="orders-card-total">$<?= htmlspecialchars($order['total_amount'] ?? '0', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
              </div>
              <span class="orders-card-status orders-status--<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') ?></span>
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

            <div class="orders-card-details" id="order-details-<?= $oid ?>">
              <div class="orders-details-inner">
                <?php if ($items): ?>
                  <h4 class="orders-details-title">Order items</h4>
                  <div class="orders-items-list">
                    <?php foreach ($items as $item): ?>
                      <div class="orders-item">
                        <img src="<?= htmlspecialchars($item['image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="" class="orders-item-img">
                        <div class="orders-item-info">
                          <strong><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                          <span class="orders-item-meta">Qty: <?= htmlspecialchars($item['quantity'], ENT_QUOTES, 'UTF-8') ?> × $<?= htmlspecialchars($item['unit_price'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <span class="orders-item-subtotal">$<?= number_format((float)$item['quantity'] * (float)$item['unit_price'], 2) ?></span>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <div class="orders-details-totals">
                    <div class="orders-total-row">
                      <span>Subtotal</span>
                      <span>$<?= htmlspecialchars($order['total_amount'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="orders-total-row orders-total-row--shipping">
                      <span>Shipping</span>
                      <span>Free</span>
                    </div>
                    <div class="orders-total-row orders-total-row--final">
                      <span>Total</span>
                      <span>$<?= htmlspecialchars($order['total_amount'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                  </div>
                <?php else: ?>
                  <p class="orders-no-items">Order details are not available.</p>
                <?php endif; ?>
              </div>
            </div>

            <div class="orders-card-actions">
              <button type="button" class="orders-btn orders-btn--primary orders-btn-details" data-order="<?= $oid ?>">
                View details
              </button>
              <?php if ($status !== 'cancelled' && $status !== 'delivered'): ?>
                <button type="button" class="orders-btn orders-btn--secondary" onclick="alert('Tracking info will appear here once shipped.')">
                  Track order
                </button>
              <?php endif; ?>
              <?php if ($status === 'pending'): ?>
                <button type="button" class="orders-btn orders-btn--outline" onclick="alert('Cancel order feature coming soon.')">
                  Cancel order
                </button>
              <?php endif; ?>
              <button type="button" class="orders-btn orders-btn--outline" onclick="window.location.href='products.php'">
                Reorder
              </button>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="orders-empty">
        <p class="orders-empty-text">No orders yet.</p>
        <a href="products.php" class="orders-empty-btn">Start shopping</a>
      </div>
    <?php endif; ?>
  </main>
</div>

<script src="assets/js/orders.js"></script>
<?php include 'includes/footer.php'; ?>
