<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/mail/PasswordResetService.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . localized_path('index.php'));
    exit;
}

$rawToken = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$message = '';
$messageType = '';
$tokenValid = false;
$tokenError = '';

if ($rawToken !== '') {
    $check = validate_password_reset_token($pdo, $rawToken);
    $tokenValid = $check['valid'];
    if (!$tokenValid) {
        $tokenError = match ($check['reason']) {
            'expired' => t('auth.reset_expired', 'This reset link has expired. Request a new one.'),
            'used' => t('auth.reset_used', 'This reset link has already been used.'),
            default => t('auth.reset_invalid', 'This reset link is invalid.'),
        };
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    csrf_require(false);
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');

    if (strlen($password) < 8) {
        $message = t('auth.error_password_min', 'Password must be at least 8 characters.');
        $messageType = 'error';
    } elseif ($password !== $confirm) {
        $message = t('auth.error_passwords_mismatch', 'Passwords do not match.');
        $messageType = 'error';
    } else {
        try {
            if (complete_password_reset($pdo, $rawToken, $password)) {
                header('Location: ' . localized_path('auth.php', ['reset' => 'success']));
                exit;
            }
            $message = t('auth.reset_failed', 'Password could not be reset. Please request a new link.');
            $messageType = 'error';
            $tokenValid = false;
            $tokenError = $message;
        } catch (Throwable $e) {
            $message = t('auth.reset_failed', 'Password could not be reset. Please request a new link.');
            $messageType = 'error';
        }
    }
}

$page_title = t('auth.reset_page_title', 'Reset Password | ZERA');
$currentLang = get_current_lang();
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang, ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="auth.css?v=1">
</head>
<body>
  <div class="auth-split">
    <div class="auth-hero">
      <div class="auth-hero-overlay"></div>
      <div class="auth-hero-content">
        <h1 class="auth-hero-title">ZERA</h1>
        <p class="auth-hero-tagline"><?= htmlspecialchars(t('auth.tagline', 'Smart Online Store'), ENT_QUOTES, 'UTF-8') ?></p>
      </div>
    </div>

    <div class="auth-form-section">
      <div class="auth-form-wrapper">
        <div class="auth-card">
          <a href="<?= htmlspecialchars(localized_path('auth.php'), ENT_QUOTES, 'UTF-8') ?>" class="auth-back-link">
            <?= htmlspecialchars(t('auth.back_signin', '← Back to Sign In'), ENT_QUOTES, 'UTF-8') ?>
          </a>

          <div class="auth-header">
            <h2 class="auth-card-title"><?= htmlspecialchars(t('auth.reset_title', 'Set a new password'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="auth-card-subtitle"><?= htmlspecialchars(t('auth.reset_subtitle', 'Choose a strong password with at least 8 characters.'), ENT_QUOTES, 'UTF-8') ?></p>
          </div>

          <?php if ($message): ?>
            <div class="auth-message auth-message--<?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
          <?php endif; ?>

          <?php if (!$tokenValid): ?>
            <div class="auth-message auth-message--error">
              <?= htmlspecialchars($tokenError !== '' ? $tokenError : t('auth.reset_invalid', 'This reset link is invalid.'), ENT_QUOTES, 'UTF-8') ?>
            </div>
            <p class="auth-switch" style="margin-top:16px;">
              <a class="auth-link" href="<?= htmlspecialchars(localized_path('forgot_password.php'), ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars(t('auth.forgot_submit', 'Send reset link'), ENT_QUOTES, 'UTF-8') ?>
              </a>
            </p>
          <?php else: ?>
            <form class="auth-form active" method="post" action="<?= htmlspecialchars(localized_path('reset_password.php'), ENT_QUOTES, 'UTF-8') ?>">
              <?= csrf_field_html() ?>
              <input type="hidden" name="token" value="<?= htmlspecialchars($rawToken, ENT_QUOTES, 'UTF-8') ?>">
              <div class="input-group input-group--password">
                <span class="input-icon">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                  </svg>
                </span>
                <input type="password" id="reset-password" name="password" placeholder="<?= htmlspecialchars(t('auth.password', 'Password'), ENT_QUOTES, 'UTF-8') ?>" required autocomplete="new-password" minlength="8">
              </div>
              <div class="input-group input-group--password">
                <span class="input-icon">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                  </svg>
                </span>
                <input type="password" id="reset-confirm" name="confirm" placeholder="<?= htmlspecialchars(t('auth.confirm_password', 'Confirm Password'), ENT_QUOTES, 'UTF-8') ?>" required autocomplete="new-password" minlength="8">
              </div>
              <button type="submit" class="auth-btn"><?= htmlspecialchars(t('auth.reset_submit', 'Update password'), ENT_QUOTES, 'UTF-8') ?></button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <script src="auth.js"></script>
</body>
</html>
