<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/mail/PasswordResetService.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . localized_path('index.php'));
    exit;
}

$message = '';
$messageType = '';
$emailValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require(false);
    $emailValue = trim((string) ($_POST['email'] ?? ''));

    if (password_reset_rate_limited()) {
        $message = t('auth.forgot_rate_limit', 'Too many reset requests. Please try again in an hour.');
        $messageType = 'error';
    } elseif ($emailValue === '' || !filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
        $message = t('auth.error_invalid_email', 'Please enter a valid email address.');
        $messageType = 'error';
    } else {
        $result = request_password_reset($pdo, $emailValue, get_current_lang());
        if (!$result['mail_configured']) {
            $message = t('auth.forgot_mail_disabled', 'Password reset email is not configured on this server.');
            $messageType = 'error';
        } elseif (!$result['sent']) {
            $message = t('auth.forgot_send_failed', 'We could not send the reset email. Please try again later.');
            $messageType = 'error';
        } else {
            $message = t(
                'auth.forgot_sent',
                'If an account exists for this email, we sent password reset instructions.'
            );
            $messageType = 'success';
            $emailValue = '';
        }
    }
}

$page_title = t('auth.forgot_page_title', 'Forgot Password | ZERA');
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
            <h2 class="auth-card-title"><?= htmlspecialchars(t('auth.forgot_title', 'Forgot your password?'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="auth-card-subtitle"><?= htmlspecialchars(t('auth.forgot_subtitle', 'Enter your email and we will send you a reset link.'), ENT_QUOTES, 'UTF-8') ?></p>
          </div>

          <?php if ($message): ?>
            <div class="auth-message auth-message--<?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
          <?php endif; ?>

          <form class="auth-form active" method="post" action="<?= htmlspecialchars(localized_path('forgot_password.php'), ENT_QUOTES, 'UTF-8') ?>">
            <?= csrf_field_html() ?>
            <div class="input-group">
              <span class="input-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                  <polyline points="22,6 12,13 2,6"/>
                </svg>
              </span>
              <input
                type="email"
                id="forgot-email"
                name="email"
                placeholder="<?= htmlspecialchars(t('auth.email', 'Email'), ENT_QUOTES, 'UTF-8') ?>"
                required
                autocomplete="email"
                value="<?= htmlspecialchars($emailValue, ENT_QUOTES, 'UTF-8') ?>"
              >
            </div>
            <button type="submit" class="auth-btn"><?= htmlspecialchars(t('auth.forgot_submit', 'Send reset link'), ENT_QUOTES, 'UTF-8') ?></button>
          </form>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
