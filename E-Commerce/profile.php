<?php
require_once 'functions.php';
require_once __DIR__ . '/user/UserPreferencesService.php';

$userId = require_login();
user_prefs_ensure_schema($pdo);
$stmt = $pdo->prepare("SELECT user_id, full_name, email, password_hash, created_at, email_notifications FROM users WHERE user_id = ?");
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
      SELECT order_id AS id, total_amount, status, created_at
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
    csrf_require(false);
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($name !== '' && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $dup = $pdo->prepare('SELECT user_id FROM users WHERE email = ? AND user_id <> ? LIMIT 1');
            $dup->execute([$email, $userId]);
            if ($dup->fetch()) {
                $profileMessage = t('auth.error_email_exists', 'An account with this email already exists.');
            } else {
                $update = $pdo->prepare('UPDATE users SET full_name = ?, email = ? WHERE user_id = ?');
                $update->execute([$name, $email, $userId]);
                $profileMessage = t('profile.msg.updated', 'Profile updated successfully.');
                $user['full_name'] = $name;
                $user['email'] = $email;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
            }
        } else {
            $profileMessage = t('profile.msg.name_email_required', 'Name and email are required.');
        }
    } elseif ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($new === '' || $confirm === '') {
            $passwordMessage = t('profile.msg.password_required', 'New password and confirmation are required.');
        } elseif ($new !== $confirm) {
            $passwordMessage = t('profile.msg.password_mismatch', 'New passwords do not match.');
        } elseif (strlen($new) < 8) {
            $passwordMessage = t('profile.msg.password_min', 'Password must be at least 8 characters.');
        } else {
            $validCurrent = !empty($user['password_hash']) && password_verify($current, $user['password_hash']);

            if (!$validCurrent) {
                $passwordMessage = t('profile.msg.current_password_wrong', 'Current password is incorrect.');
            } else {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $update = $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
                $update->execute([$hash, $userId]);
                $passwordMessage = t('profile.msg.password_updated', 'Password updated successfully.');
                $user['password_hash'] = $hash;
            }
        }
    } elseif ($action === 'settings') {
        $emailNotifications = !empty($_POST['email_notifications']);
        user_prefs_save_email_notifications($pdo, $userId, $emailNotifications);
        $user['email_notifications'] = $emailNotifications ? 1 : 0;
        $settingsMessage = t('profile.msg.preferences_saved', 'Preferences saved.');
    }
}

$emailNotificationsOn = (bool) ((int) ($user['email_notifications'] ?? 1));
$membershipDate = isset($user['created_at']) ? date('F j, Y', strtotime($user['created_at'])) : t('profile.member', 'Member');

$page_title = t("meta.profile_title", "ZERA - Profile");
?>
<?php include 'includes/header.php'; ?>

<link rel="stylesheet" href="<?= htmlspecialchars(asset_url('assets/css/profile.css'), ENT_QUOTES, 'UTF-8') ?>">

<div class="profile-page">
  <aside class="profile-sidebar">
    <nav class="profile-nav">
      <a href="#profile-info" class="profile-nav-item active">
        <span class="profile-nav-icon">👤</span>
        <?= htmlspecialchars(t('profile.nav.info', 'Profile Info'), ENT_QUOTES, 'UTF-8') ?>
      </a>
      <a href="#orders" class="profile-nav-item">
        <span class="profile-nav-icon">📦</span>
        <?= htmlspecialchars(t('profile.nav.orders', 'Orders'), ENT_QUOTES, 'UTF-8') ?>
      </a>
      <a href="#wishlist" class="profile-nav-item">
        <span class="profile-nav-icon">♥</span>
        <?= htmlspecialchars(t('profile.nav.wishlist', 'Wishlist'), ENT_QUOTES, 'UTF-8') ?>
      </a>
      <a href="#settings" class="profile-nav-item">
        <span class="profile-nav-icon">⚙</span>
        <?= htmlspecialchars(t('profile.nav.settings', 'Settings'), ENT_QUOTES, 'UTF-8') ?>
      </a>
    </nav>
    <div class="profile-sidebar-footer">
      <a href="logout.php" class="profile-logout"><?= htmlspecialchars(t('profile.logout', 'Log out'), ENT_QUOTES, 'UTF-8') ?></a>
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
        <p class="profile-hero-date"><?= htmlspecialchars(t('profile.member_since', 'Member since'), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($membershipDate, ENT_QUOTES, 'UTF-8') ?></p>
      </div>
    </div>

    <section id="profile-info" class="profile-section">
      <h2 class="profile-section-title"><?= htmlspecialchars(t('profile.section.info', 'Profile Info'), ENT_QUOTES, 'UTF-8') ?></h2>
      <div class="profile-card">
        <?php if ($profileMessage): ?>
          <div class="profile-message profile-message--success"><?= htmlspecialchars($profileMessage, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="post" class="profile-form">
          <?= csrf_field_html() ?>
          <input type="hidden" name="action" value="profile">
          <div class="profile-field">
            <label for="profile-name"><?= htmlspecialchars(t('profile.full_name', 'Full Name'), ENT_QUOTES, 'UTF-8') ?></label>
            <input type="text" id="profile-name" name="full_name" value="<?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?>" required>
          </div>
          <div class="profile-field">
            <label for="profile-email"><?= htmlspecialchars(t('profile.email', 'Email'), ENT_QUOTES, 'UTF-8') ?></label>
            <input type="email" id="profile-email" name="email" value="<?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>" required>
          </div>
          <button type="submit" class="profile-btn"><?= htmlspecialchars(t('profile.save_changes', 'Save changes'), ENT_QUOTES, 'UTF-8') ?></button>
        </form>
      </div>

      <div class="profile-card">
        <h3 class="profile-card-title"><?= htmlspecialchars(t('profile.change_password', 'Change password'), ENT_QUOTES, 'UTF-8') ?></h3>
        <?php if ($passwordMessage): ?>
          <div class="profile-message profile-message--success"><?= htmlspecialchars($passwordMessage, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="post" class="profile-form">
          <?= csrf_field_html() ?>
          <input type="hidden" name="action" value="password">
          <div class="profile-field">
            <label for="current_password"><?= htmlspecialchars(t('profile.current_password', 'Current password'), ENT_QUOTES, 'UTF-8') ?></label>
            <input type="password" id="current_password" name="current_password">
          </div>
          <div class="profile-field">
            <label for="new_password"><?= htmlspecialchars(t('profile.new_password', 'New password'), ENT_QUOTES, 'UTF-8') ?></label>
            <input type="password" id="new_password" name="new_password" required minlength="8">
          </div>
          <div class="profile-field">
            <label for="confirm_password"><?= htmlspecialchars(t('profile.confirm_new_password', 'Confirm new password'), ENT_QUOTES, 'UTF-8') ?></label>
            <input type="password" id="confirm_password" name="confirm_password" required>
          </div>
          <button type="submit" class="profile-btn"><?= htmlspecialchars(t('profile.update_password', 'Update password'), ENT_QUOTES, 'UTF-8') ?></button>
        </form>
      </div>
    </section>

    <section id="orders" class="profile-section">
      <h2 class="profile-section-title"><?= htmlspecialchars(t('profile.section.recent_orders', 'Recent Orders'), ENT_QUOTES, 'UTF-8') ?></h2>
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
                    <h4><?= htmlspecialchars(t('profile.order', 'Order'), ENT_QUOTES, 'UTF-8') ?> #<?= htmlspecialchars($order['id'], ENT_QUOTES, 'UTF-8') ?></h4>
                    <p class="profile-order-meta"><?= htmlspecialchars($order['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?> · $<?= htmlspecialchars($order['total_amount'] ?? '0', ENT_QUOTES, 'UTF-8') ?></p>
                  </div>
                  <span class="profile-order-status"><?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <?php if (!empty($items)): ?>
                  <div class="profile-order-items">
                    <?php foreach (array_slice($items, 0, 3) as $item): ?>
                      <div class="profile-order-item">
                        <img src="<?= htmlspecialchars($item['image_url'] ?: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff', ENT_QUOTES, 'UTF-8') ?>" alt="">
                        <span><?= htmlspecialchars($item['name'] ?? t('product.card.fallback_name', 'Product'), ENT_QUOTES, 'UTF-8') ?></span>
                      </div>
                    <?php endforeach; ?>
                    <?php if (count($items) > 3): ?>
                      <span class="profile-order-more">+<?= count($items) - 3 ?> <?= htmlspecialchars(t('profile.more', 'more'), ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
                <a href="<?= htmlspecialchars(localized_path('orders.php'), ENT_QUOTES, 'UTF-8') ?>" class="profile-order-link"><?= htmlspecialchars(t('profile.view_details', 'View details'), ENT_QUOTES, 'UTF-8') ?></a>
              </article>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="profile-empty"><?= htmlspecialchars(t('profile.no_orders', 'No orders yet.'), ENT_QUOTES, 'UTF-8') ?> <a href="<?= htmlspecialchars(localized_path('products.php'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t('profile.start_shopping', 'Start shopping'), ENT_QUOTES, 'UTF-8') ?></a></p>
        <?php endif; ?>
      </div>
    </section>

    <section id="wishlist" class="profile-section">
      <h2 class="profile-section-title"><?= htmlspecialchars(t('profile.section.wishlist', 'Wishlist / Favorites'), ENT_QUOTES, 'UTF-8') ?></h2>
      <div class="profile-card">
        <div id="profileWishlistContainer" class="profile-wishlist-grid"></div>
        <p id="profileWishlistEmpty" class="profile-empty" style="display:none;"><?= htmlspecialchars(t('profile.no_favorites', 'No favorites yet.'), ENT_QUOTES, 'UTF-8') ?> <a href="<?= htmlspecialchars(localized_path('products.php'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t('profile.browse_products', 'Browse products'), ENT_QUOTES, 'UTF-8') ?></a></p>
      </div>
    </section>

    <section id="settings" class="profile-section">
      <h2 class="profile-section-title"><?= htmlspecialchars(t('profile.section.settings', 'Settings'), ENT_QUOTES, 'UTF-8') ?></h2>
      <div class="profile-card">
        <?php if ($settingsMessage): ?>
          <div class="profile-message profile-message--success"><?= htmlspecialchars($settingsMessage, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="post" class="profile-settings-form">
          <?= csrf_field_html() ?>
          <input type="hidden" name="action" value="settings">
          <div class="profile-setting-row">
            <div>
              <label class="profile-setting-label"><?= htmlspecialchars(t('profile.email_notifications', 'Email notifications'), ENT_QUOTES, 'UTF-8') ?></label>
              <p class="profile-setting-desc"><?= htmlspecialchars(t('profile.email_notifications_desc', 'Receive order updates and promotions'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <label class="profile-toggle">
              <input type="checkbox" name="email_notifications" value="1" <?= $emailNotificationsOn ? 'checked' : '' ?>>
              <span class="profile-toggle-slider"></span>
            </label>
          </div>
          <button type="submit" class="profile-btn"><?= htmlspecialchars(t('profile.save_preferences', 'Save preferences'), ENT_QUOTES, 'UTF-8') ?></button>
        </form>
        <div class="profile-logout-section">
          <a href="logout.php" class="profile-btn profile-btn--outline"><?= htmlspecialchars(t('profile.logout', 'Log out'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
      </div>
    </section>
  </main>
</div>

<script src="<?= htmlspecialchars(asset_url('assets/js/profile.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php include 'includes/footer.php'; ?>
