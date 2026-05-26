<?php
require_once __DIR__ . '/functions.php';

// Ensure users table exists
try {
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY (email)
      )
    ");
} catch (PDOException $e) {
    // Table may already exist with different structure
}

/**
 * Open-redirect saldırılarına karşı yalnız uygulamamıza dönen
 * göreceli yolları kabul ediyoruz.
 */
function safe_return_url(?string $raw): string
{
    $fallback = 'index.php';
    if (!is_string($raw) || $raw === '') {
        return $fallback;
    }
    if (strpos($raw, '//') === 0 || preg_match('#^[a-z][a-z0-9+.\-]*:#i', $raw)) {
        return $fallback;
    }
    if ($raw[0] !== '/' && !preg_match('#^[a-zA-Z0-9_\-./?=&%]+$#', $raw)) {
        return $fallback;
    }
    return $raw;
}

$returnUrl = safe_return_url($_GET['return'] ?? $_POST['return'] ?? null);

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . $returnUrl);
    exit;
}

$authMessage = '';
$authMessageType = '';
$activeTab = 'signin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'join') {
        // Join (Create Account)
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm'] ?? '';

        if ($name === '' || $email === '' || $password === '' || $confirm === '') {
            $authMessage = t('auth.error_all_fields', 'All fields are required.');
            $authMessageType = 'error';
            $activeTab = 'join';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $authMessage = t('auth.error_invalid_email', 'Please enter a valid email address.');
            $authMessageType = 'error';
            $activeTab = 'join';
        } elseif (strlen($password) < 8) {
            $authMessage = t('auth.error_password_min', 'Password must be at least 8 characters.');
            $authMessageType = 'error';
            $activeTab = 'join';
        } elseif ($password !== $confirm) {
            $authMessage = t('auth.error_passwords_mismatch', 'Passwords do not match.');
            $authMessageType = 'error';
            $activeTab = 'join';
        } else {
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $authMessage = t('auth.error_email_exists', 'An account with this email already exists.');
                $authMessageType = 'error';
                $activeTab = 'join';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)");
                $stmt->execute([$name, $email, $hash]);
                $authMessage = t('auth.success_account_created', 'Account created successfully! You can now sign in.');
                $authMessageType = 'success';
                $activeTab = 'signin';
            }
        }
    } elseif ($action === 'signin') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $authMessage = t('auth.error_required', 'Email and password are required.');
            $authMessageType = 'error';
            $activeTab = 'signin';
        } else {
            $stmt = $pdo->prepare("SELECT user_id, full_name, email, password_hash FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_email'] = $user['email'];
                header('Location: ' . $returnUrl);
                exit;
            } else {
                $authMessage = t('auth.error_invalid_credentials', 'Invalid email or password.');
                $authMessageType = 'error';
                $activeTab = 'signin';
            }
        }
    }
}

$page_title = t('auth.page_title', 'Sign In | ZERA - Smart Online Store');
$currentLang = function_exists('get_current_lang') ? get_current_lang() : 'en';
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
              <a href="#" class="forgot-link"><?= htmlspecialchars(t('auth.forgot_password', 'Forgot password?'), ENT_QUOTES, 'UTF-8') ?></a>
              <button type="submit" class="auth-btn"><?= htmlspecialchars(t('auth.signin', 'Sign In'), ENT_QUOTES, 'UTF-8') ?></button>
              <p class="auth-switch">
                <?= htmlspecialchars(t('auth.no_account', "Don't have an account?"), ENT_QUOTES, 'UTF-8') ?> <button type="button" class="auth-link" data-switch="join"><?= htmlspecialchars(t('auth.join', 'Join'), ENT_QUOTES, 'UTF-8') ?></button>
              </p>
            </form>

            <form id="join-form" class="auth-form <?= $activeTab === 'join' ? 'active' : '' ?> join-active" method="post" action="auth.php">
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
          </div>

          <div class="auth-social">
            <p class="auth-social-label"><?= htmlspecialchars(t('auth.continue_with', 'Or continue with'), ENT_QUOTES, 'UTF-8') ?></p>
            <div class="auth-social-buttons">
              <button type="button" class="auth-social-btn" disabled>
                <svg width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="currentColor" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="currentColor" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="currentColor" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                Google
              </button>
              <button type="button" class="auth-social-btn" disabled>
                <svg width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                Facebook
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="auth.js"></script>
</body>
</html>
