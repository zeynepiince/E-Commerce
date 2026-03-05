<?php
require_once 'functions.php';

// Şimdilik tek bir kullanıcı varsayımı (ilk kayıt)
try {
    $stmt = $pdo->query("SELECT name, email, password_hash FROM users LIMIT 1");
    $user = $stmt->fetch() ?: [
        'name'          => 'Demo User',
        'email'         => 'demo@example.com',
        'password_hash' => null,
    ];
} catch (PDOException $e) {
    // users tablosu ya da kolonları yoksa graceful fallback
    $user = [
        'name'          => 'Demo User',
        'email'         => 'demo@example.com',
        'password_hash' => null,
    ];
}

$profileMessage = '';
$passwordMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'profile') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($name !== '' && $email !== '') {
            $update = $pdo->prepare("UPDATE users SET name = ?, email = ? LIMIT 1");
            $update->execute([$name, $email]);
            $profileMessage = 'Profile updated successfully.';
            $user['name']   = $name;
            $user['email']  = $email;
        } else {
            $profileMessage = 'Name and email are required.';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($new === '' || $confirm === '') {
            $passwordMessage = 'New password and confirmation are required.';
        } elseif ($new !== $confirm) {
            $passwordMessage = 'New passwords do not match.';
        } else {
            // Mevcut şifre kontrolü (password_hash kolonu varsa)
            $validCurrent = true;
            if (!empty($user['password_hash'])) {
                $validCurrent = password_verify($current, $user['password_hash']);
            }

            if (!$validCurrent) {
                $passwordMessage = 'Current password is incorrect.';
            } else {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $update = $pdo->prepare("UPDATE users SET password_hash = ? LIMIT 1");
                $update->execute([$hash]);
                $passwordMessage = 'Password updated successfully.';
                $user['password_hash'] = $hash;
            }
        }
    }
}

$page_title = "Your Profile – STORY";
?>

<?php include 'includes/header.php'; ?>

<section class="products">
  <h3>Your Profile</h3>
  <p class="subtitle">
    Manage your personal information and change your password.
  </p>

  <div class="product-detail">
    <div class="product-detail-info">
      <h4>Profile information</h4>
      <?php if ($profileMessage): ?>
        <p style="font-size:13px;color:#065f46;margin-bottom:10px;"><?= htmlspecialchars($profileMessage, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endif; ?>
      <form method="post">
        <input type="hidden" name="action" value="profile">
        <div class="filter-group">
          <label for="name">Name</label>
          <input
            type="text"
            id="name"
            name="name"
            value="<?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>"
            required
          >
        </div>
        <div class="filter-group">
          <label for="email">Email</label>
          <input
            type="email"
            id="email"
            name="email"
            value="<?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>"
            required
          >
        </div>
        <button type="submit" class="btn-full-width" style="max-width:220px;margin-top:16px;">
          Save profile
        </button>
      </form>
    </div>

    <div class="product-detail-info">
      <h4>Change password</h4>
      <?php if ($passwordMessage): ?>
        <p style="font-size:13px;color:#065f46;margin-bottom:10px;"><?= htmlspecialchars($passwordMessage, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endif; ?>
      <form method="post">
        <input type="hidden" name="action" value="password">
        <div class="filter-group">
          <label for="current_password">Current password</label>
          <input type="password" id="current_password" name="current_password">
        </div>
        <div class="filter-group">
          <label for="new_password">New password</label>
          <input type="password" id="new_password" name="new_password" required>
        </div>
        <div class="filter-group">
          <label for="confirm_password">Confirm new password</label>
          <input type="password" id="confirm_password" name="confirm_password" required>
        </div>
        <button type="submit" class="btn-full-width" style="max-width:220px;margin-top:16px;">
          Update password
        </button>
      </form>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>

