<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth/AuthService.php';
require_once __DIR__ . '/auth/OAuthService.php';

// Ensure users table exists with the expected schema
try {
    ensure_users_table($pdo);
} catch (PDOException $e) {
    error_log('auth.php ensure_users_table: ' . $e->getMessage());
}

$returnUrl = auth_safe_return_url($_GET['return'] ?? $_POST['return'] ?? null);

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . $returnUrl);
    exit;
}

$authMessage = '';
$authMessageType = '';
$activeTab = 'signin';

if (($_GET['reset'] ?? '') === 'success') {
    $authMessage = t('auth.reset_success', 'Your password was updated. You can now sign in.');
    $authMessageType = 'success';
}

$oauthError = trim((string) ($_GET['oauth_error'] ?? ''));
if ($oauthError !== '') {
    $authMessage = $oauthError;
    $authMessageType = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require(false);
    $action = $_POST['action'] ?? '';

    if ($action === 'join') {
        $result = auth_process_join(
            $pdo,
            (string) ($_POST['name'] ?? ''),
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['confirm'] ?? ''),
            get_current_lang()
        );
        if (!empty($result['message'])) {
            $authMessage = $result['message'];
            $authMessageType = $result['message_type'] ?? ($result['success'] ? 'success' : 'error');
        }
        if (!empty($result['active_tab'])) {
            $activeTab = $result['active_tab'];
        }
    } elseif ($action === 'signin') {
        $result = auth_process_signin(
            $pdo,
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['password'] ?? '')
        );
        if ($result['success']) {
            header('Location: ' . $returnUrl);
            exit;
        }
        if (!empty($result['message'])) {
            $authMessage = $result['message'];
            $authMessageType = $result['message_type'] ?? 'error';
        }
        if (!empty($result['active_tab'])) {
            $activeTab = $result['active_tab'];
        }
    }
}

$oauth_return = $returnUrl;
$oauth_links = oauth_login_links($returnUrl);

$page_title = t('auth.page_title', 'Sign In | ZERA - Smart Online Store');
$currentLang = function_exists('get_current_lang') ? get_current_lang() : 'en';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang, ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('auth.css'), ENT_QUOTES, 'UTF-8') ?>?v=1">
</head>
<body>
  <div class="auth-split">
    <div class="auth-hero">
      <div class="auth-hero-overlay"></div>
      <div class="auth-hero-content">
        <h1 class="auth-hero-title">ZERA</h1>
        <p class="auth-hero-tagline"><?= htmlspecialchars(t('auth.tagline', 'Smart Online Store'), ENT_QUOTES, 'UTF-8') ?></p>
        <p class="auth-hero-desc"><?= htmlspecialchars(t('auth.hero_desc', 'Discover curated fashion, tech, and lifestyle essentials.'), ENT_QUOTES, 'UTF-8') ?></p>
      </div>
      <div class="auth-hero-float">✨</div>
    </div>

    <div class="auth-form-section">
      <div class="auth-form-wrapper">
        <div class="auth-card">
            <a href="index.php" class="auth-back-link">
              <?= htmlspecialchars(t('auth.back_home', '← Back to Home'), ENT_QUOTES, 'UTF-8') ?>
            </a>
          <div class="auth-header">
            <h2 class="auth-card-title"><?= htmlspecialchars(t('auth.welcome_back', 'Welcome back'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="auth-card-subtitle"><?= htmlspecialchars(t('auth.card_subtitle', 'Sign in or create an account'), ENT_QUOTES, 'UTF-8') ?></p>
          </div>

          <?php if ($authMessage): ?>
            <div class="auth-message auth-message--<?= htmlspecialchars($authMessageType, ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars($authMessage, ENT_QUOTES, 'UTF-8') ?>
            </div>
          <?php endif; ?>

          <div class="auth-tabs">
            <button type="button" class="auth-tab <?= $activeTab === 'signin' ? 'active' : '' ?>" data-tab="signin"><?= htmlspecialchars(t('auth.signin', 'Sign In'), ENT_QUOTES, 'UTF-8') ?></button>
            <button type="button" class="auth-tab <?= $activeTab === 'join' ? 'active' : '' ?>" data-tab="join"><?= htmlspecialchars(t('auth.join', 'Join'), ENT_QUOTES, 'UTF-8') ?></button>
          </div>

          <div class="auth-forms">
            <form id="signin-form" class="auth-form <?= $activeTab === 'signin' ? 'active' : '' ?>" method="post" action="auth.php">
              <?= csrf_field_html() ?>
              <input type="hidden" name="action" value="signin">
              <input type="hidden" name="return" value="<?= htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') ?>">
              <div class="input-group">
                <span class="input-icon">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                    <polyline points="22,6 12,13 2,6"/>
                  </svg>
                </span>
                <input type="email" id="signin-email" name="email" placeholder="<?= htmlspecialchars(t('auth.email', 'Email'), ENT_QUOTES, 'UTF-8') ?>" required autocomplete="email" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
              </div>
              <div class="input-group input-group--password">
                <span class="input-icon">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                  </svg>
                </span>
                <input type="password" id="signin-password" name="password" placeholder="<?= htmlspecialchars(t('auth.password', 'Password'), ENT_QUOTES, 'UTF-8') ?>" required autocomplete="current-password">
                <button type="button" class="input-toggle-password" aria-label="Toggle password visibility">
                  <svg class="icon-eye" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                  </svg>
                  <svg class="icon-eye-off" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                    <line x1="1" y1="1" x2="23" y2="23"/>
                  </svg>
                </button>
              </div>
              <button type="button" class="forgot-link auth-link" data-switch="forgot"><?= htmlspecialchars(t('auth.forgot_password', 'Forgot password?'), ENT_QUOTES, 'UTF-8') ?></button>
              <button type="submit" class="auth-btn"><?= htmlspecialchars(t('auth.signin', 'Sign In'), ENT_QUOTES, 'UTF-8') ?></button>
              <p class="auth-switch">
                <?= htmlspecialchars(t('auth.no_account', "Don't have an account?"), ENT_QUOTES, 'UTF-8') ?> <button type="button" class="auth-link" data-switch="join"><?= htmlspecialchars(t('auth.join', 'Join'), ENT_QUOTES, 'UTF-8') ?></button>
              </p>
            </form>

            <form id="join-form" class="auth-form <?= $activeTab === 'join' ? 'active' : '' ?> join-active" method="post" action="auth.php">
              <?= csrf_field_html() ?>
              <input type="hidden" name="action" value="join">
              <input type="hidden" name="return" value="<?= htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') ?>">
              <div class="input-group">
                <span class="input-icon">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                  </svg>
                </span>
                <input type="text" id="join-name" name="name" placeholder="<?= htmlspecialchars(t('auth.full_name', 'Full Name'), ENT_QUOTES, 'UTF-8') ?>" required autocomplete="name" value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
              </div>
              <div class="input-group">
                <span class="input-icon">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                    <polyline points="22,6 12,13 2,6"/>
                  </svg>
                </span>
                <input type="email" id="join-email" name="email" placeholder="<?= htmlspecialchars(t('auth.email', 'Email'), ENT_QUOTES, 'UTF-8') ?>" required autocomplete="email" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
              </div>
              <div class="input-group input-group--password">
                <span class="input-icon">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                  </svg>
                </span>
                <input type="password" id="join-password" name="password" placeholder="<?= htmlspecialchars(t('auth.password', 'Password'), ENT_QUOTES, 'UTF-8') ?>" required autocomplete="new-password">
                <button type="button" class="input-toggle-password" aria-label="Toggle password visibility">
                  <svg class="icon-eye" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                  </svg>
                  <svg class="icon-eye-off" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                    <line x1="1" y1="1" x2="23" y2="23"/>
                  </svg>
                </button>
              </div>
              <div class="input-group input-group--password">
                <span class="input-icon">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                  </svg>
                </span>
                <input type="password" id="join-confirm" name="confirm" placeholder="<?= htmlspecialchars(t('auth.confirm_password', 'Confirm Password'), ENT_QUOTES, 'UTF-8') ?>" required autocomplete="new-password">
                <button type="button" class="input-toggle-password" aria-label="Toggle password visibility">
                  <svg class="icon-eye" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                  </svg>
                  <svg class="icon-eye-off" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                    <line x1="1" y1="1" x2="23" y2="23"/>
                  </svg>
                </button>
              </div>
              <button type="submit" class="auth-btn"><?= htmlspecialchars(t('auth.create_account', 'Create Account'), ENT_QUOTES, 'UTF-8') ?></button>
              <p class="auth-switch">
                <?= htmlspecialchars(t('auth.have_account', 'Already have an account?'), ENT_QUOTES, 'UTF-8') ?> <button type="button" class="auth-link" data-switch="signin"><?= htmlspecialchars(t('auth.signin', 'Sign In'), ENT_QUOTES, 'UTF-8') ?></button>
              </p>
            </form>

            <form id="forgot-form" class="auth-form auth-form--forgot" method="post" action="<?= htmlspecialchars(localized_path('forgot_password.php'), ENT_QUOTES, 'UTF-8') ?>">
              <?= csrf_field_html() ?>
              <p class="auth-forgot-intro"><?= htmlspecialchars(t('auth.forgot_subtitle', 'Enter your email and we will send you a reset link.'), ENT_QUOTES, 'UTF-8') ?></p>
              <div class="input-group">
                <span class="input-icon">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                    <polyline points="22,6 12,13 2,6"/>
                  </svg>
                </span>
                <input type="email" id="forgot-email" name="email" placeholder="<?= htmlspecialchars(t('auth.email', 'Email'), ENT_QUOTES, 'UTF-8') ?>" required autocomplete="email">
              </div>
              <button type="submit" class="auth-btn"><?= htmlspecialchars(t('auth.forgot_submit', 'Send reset link'), ENT_QUOTES, 'UTF-8') ?></button>
              <p class="auth-switch">
                <button type="button" class="auth-link" data-switch="signin"><?= htmlspecialchars(t('auth.back_signin', '← Back to Sign In'), ENT_QUOTES, 'UTF-8') ?></button>
              </p>
            </form>
          </div>

          <div id="auth-page-social">
          <?php include __DIR__ . '/includes/auth-social.php'; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;</script>
  <script src="<?= htmlspecialchars(asset_url('auth.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
