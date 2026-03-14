<?php
require_once 'functions.php';

if (empty($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT user_id, full_name, email, password_hash, created_at FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: auth.php');
    exit;
}

// Fetch recent orders (last 5)
$orders = [];
$orderItemsByOrder = [];
try {
    $stmt = $pdo->prepare("
      SELECT order_id AS order_id, total_amount, status, created_at
      FROM orders
      WHERE user_id = ?
      ORDER BY created_at DESC
      LIMIT 5
    ");
    $stmt->execute([$userId]);
    $orders = $stmt->fetchAll();

    if ($orders) {
        $orderIds = array_column($orders, 'id');
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $sqlItems = "
          SELECT oi.order_id, oi.product_id, oi.quantity, oi.unit_price, p.name, p.image_url
          FROM order_items oi
          LEFT JOIN products p ON p.product_id = oi.product_id
          WHERE oi.order_id IN ($placeholders)
        ";
        try {
            $stmtItems = $pdo->prepare($sqlItems);
            $stmtItems->execute($orderIds);
            foreach ($stmtItems->fetchAll() as $row) {
                $oid = $row['order_id'];
                if (!isset($orderItemsByOrder[$oid])) $orderItemsByOrder[$oid] = [];
                $orderItemsByOrder[$oid][] = $row;
            }
        } catch (PDOException $e) {
            // Fallback if products join fails
            $stmtItems = $pdo->prepare("SELECT order_id, product_id, quantity, unit_price FROM order_items WHERE order_id IN ($placeholders)");
            $stmtItems->execute($orderIds);
            foreach ($stmtItems->fetchAll() as $row) {
                $oid = $row['order_id'];
                if (!isset($orderItemsByOrder[$oid])) $orderItemsByOrder[$oid] = [];
                $orderItemsByOrder[$oid][] = array_merge($row, ['name' => 'Product', 'image_url' => null]);
            }
        }
    }
} catch (PDOException $e) {
    $orders = [];
}

$profileMessage = '';
$passwordMessage = '';
$settingsMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($name !== '' && $email !== '') {
            $update = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
            $update->execute([$name, $email, $userId]);
            $profileMessage = 'Profile updated successfully.';
            $user['name'] = $name;
            $user['email'] = $email;
            $_SESSION['user_name'] = $name;
        } else {
            $profileMessage = 'Name and email are required.';
        }
    } elseif ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($new === '' || $confirm === '') {
            $passwordMessage = 'New password and confirmation are required.';
        } elseif ($new !== $confirm) {
            $passwordMessage = 'New passwords do not match.';
        } elseif (strlen($new) < 8) {
            $passwordMessage = 'Password must be at least 8 characters.';
        } else {
            $validCurrent = !empty($user['password_hash']) && password_verify($current, $user['password_hash']);

            if (!$validCurrent) {
                $passwordMessage = 'Current password is incorrect.';
            } else {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $update = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $update->execute([$hash, $userId]);
                $passwordMessage = 'Password updated successfully.';
                $user['password_hash'] = $hash;
            }
        }
    } elseif ($action === 'settings') {
        $settingsMessage = 'Preferences saved.';
    }
}

$membershipDate = isset($user['created_at']) ? date('F j, Y', strtotime($user['created_at'])) : 'Member';

$page_title = "Your Profile – STORY";
?>
<?php include 'includes/header.php'; ?>

<link rel="stylesheet" href="assets/css/profile.css">

<div class="profile-page">
  <aside class="profile-sidebar">
    <nav class="profile-nav">
      <a href="#profile-info" class="profile-nav-item active">
        <span class="profile-nav-icon">👤</span>
        Profile Info
      </a>
      <a href="#orders" class="profile-nav-item">
        <span class="profile-nav-icon">📦</span>
        Orders
      </a>
      <a href="#wishlist" class="profile-nav-item">
        <span class="profile-nav-icon">♥</span>
        Wishlist
      </a>
      <a href="#settings" class="profile-nav-item">
        <span class="profile-nav-icon">⚙</span>
        Settings
      </a>
    </nav>
    <div class="profile-sidebar-footer">
      <a href="logout.php" class="profile-logout">Log out</a>
    </div>
  </aside>

  <main class="profile-main">
    <div class="profile-hero-card">
      <div class="profile-avatar">
        <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
      </div>
      <div class="profile-hero-info">
        <h1 class="profile-hero-name"><?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="profile-hero-email"><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></p>
        <p class="profile-hero-date">Member since <?= htmlspecialchars($membershipDate, ENT_QUOTES, 'UTF-8') ?></p>
      </div>
    </div>

    <section id="profile-info" class="profile-section">
      <h2 class="profile-section-title">Profile Info</h2>
      <div class="profile-card">
        <?php if ($profileMessage): ?>
          <div class="profile-message profile-message--success"><?= htmlspecialchars($profileMessage, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="post" class="profile-form">
          <input type="hidden" name="action" value="profile">
          <div class="profile-field">
            <label for="profile-name">Full Name</label>
            <input type="text" id="profile-name" name="full_name" value="<?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?>" required>
          </div>
          <div class="profile-field">
            <label for="profile-email">Email</label>
            <input type="email" id="profile-email" name="email" value="<?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>" required>
          </div>
          <button type="submit" class="profile-btn">Save changes</button>
        </form>
      </div>

      <div class="profile-card">
        <h3 class="profile-card-title">Change password</h3>
        <?php if ($passwordMessage): ?>
          <div class="profile-message profile-message--success"><?= htmlspecialchars($passwordMessage, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="post" class="profile-form">
          <input type="hidden" name="action" value="password">
          <div class="profile-field">
            <label for="current_password">Current password</label>
            <input type="password" id="current_password" name="current_password">
          </div>
          <div class="profile-field">
            <label for="new_password">New password</label>
            <input type="password" id="new_password" name="new_password" required minlength="8">
          </div>
          <div class="profile-field">
            <label for="confirm_password">Confirm new password</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
          </div>
          <button type="submit" class="profile-btn">Update password</button>
        </form>
      </div>
    </section>

    <section id="orders" class="profile-section">
      <h2 class="profile-section-title">Recent Orders</h2>
      <div class="profile-card">
        <?php if ($orders): ?>
          <div class="profile-orders-list">
            <?php foreach ($orders as $order): ?>
              <?php
                $status = $order['status'] ?? 'pending';
                $statusClass = 'profile-order-status--' . $status;
                $oid = (int) $order['id'];
                $items = $orderItemsByOrder[$oid] ?? [];
              ?>
              <article class="profile-order-card <?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>">
                <div class="profile-order-header">
                  <div>
                    <h4>Order #<?= htmlspecialchars($order['id'], ENT_QUOTES, 'UTF-8') ?></h4>
                    <p class="profile-order-meta"><?= htmlspecialchars($order['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?> · $<?= htmlspecialchars($order['total_amount'] ?? '0', ENT_QUOTES, 'UTF-8') ?></p>
                  </div>
                  <span class="profile-order-status"><?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <?php if (!empty($items)): ?>
                  <div class="profile-order-items">
                    <?php foreach (array_slice($items, 0, 3) as $item): ?>
                      <div class="profile-order-item">
                        <img src="<?= htmlspecialchars($item['image_url'] ?: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff', ENT_QUOTES, 'UTF-8') ?>" alt="">
                        <span><?= htmlspecialchars($item['name'] ?? 'Product', ENT_QUOTES, 'UTF-8') ?></span>
                      </div>
                    <?php endforeach; ?>
                    <?php if (count($items) > 3): ?>
                      <span class="profile-order-more">+<?= count($items) - 3 ?> more</span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
                <a href="orders.php" class="profile-order-link">View details</a>
              </article>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="profile-empty">No orders yet. <a href="products.php">Start shopping</a></p>
        <?php endif; ?>
      </div>
    </section>

    <section id="wishlist" class="profile-section">
      <h2 class="profile-section-title">Wishlist / Favorites</h2>
      <div class="profile-card">
        <div id="profileWishlistContainer" class="profile-wishlist-grid"></div>
        <p id="profileWishlistEmpty" class="profile-empty" style="display:none;">No favorites yet. <a href="products.php">Browse products</a></p>
      </div>
    </section>

    <section id="settings" class="profile-section">
      <h2 class="profile-section-title">Settings</h2>
      <div class="profile-card">
        <?php if ($settingsMessage): ?>
          <div class="profile-message profile-message--success"><?= htmlspecialchars($settingsMessage, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="post" class="profile-settings-form">
          <input type="hidden" name="action" value="settings">
          <div class="profile-setting-row">
            <div>
              <label class="profile-setting-label">Email notifications</label>
              <p class="profile-setting-desc">Receive order updates and promotions</p>
            </div>
            <label class="profile-toggle">
              <input type="checkbox" name="email_notifications" value="1" checked>
              <span class="profile-toggle-slider"></span>
            </label>
          </div>
          <div class="profile-setting-row">
            <div>
              <label class="profile-setting-label">Newsletter</label>
              <p class="profile-setting-desc">Weekly deals and new arrivals</p>
            </div>
            <label class="profile-toggle">
              <input type="checkbox" name="newsletter" value="1">
              <span class="profile-toggle-slider"></span>
            </label>
          </div>
          <button type="submit" class="profile-btn">Save preferences</button>
        </form>
        <div class="profile-logout-section">
          <a href="logout.php" class="profile-btn profile-btn--outline">Log out</a>
        </div>
      </div>
    </section>
  </main>
</div>

<script src="assets/js/profile.js"></script>
<?php include 'includes/footer.php'; ?>
