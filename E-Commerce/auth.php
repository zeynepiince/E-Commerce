<?php
session_start();
require_once __DIR__ . '/db.php';

// Ensure users table exists
try {
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(120) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        address VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )
    ");
} catch (PDOException $e) {
    // Table may already exist with different structure
}

// Redirect if already logged in
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
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
            $authMessage = 'All fields are required.';
            $authMessageType = 'error';
            $activeTab = 'join';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $authMessage = 'Please enter a valid email address.';
            $authMessageType = 'error';
            $activeTab = 'join';
        } elseif (strlen($password) < 8) {
            $authMessage = 'Password must be at least 8 characters.';
            $authMessageType = 'error';
            $activeTab = 'join';
        } elseif ($password !== $confirm) {
            $authMessage = 'Passwords do not match.';
            $authMessageType = 'error';
            $activeTab = 'join';
        } else {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $authMessage = 'An account with this email already exists.';
                $authMessageType = 'error';
                $activeTab = 'join';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)");
                $stmt->execute([$name, $email, $hash]);
                $authMessage = 'Account created successfully! You can now sign in.';
                $authMessageType = 'success';
                $activeTab = 'signin';
            }
        }
    } elseif ($action === 'signin') {
        // Sign In
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $authMessage = 'Email and password are required.';
            $authMessageType = 'error';
            $activeTab = 'signin';
        } else {
            $stmt = $pdo->prepare("SELECT user_id, full_name, email, password_hash FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_email'] = $user['email'];
                header('Location: index.php');
                exit;
            } else {
                $authMessage = 'Invalid email or password.';
                $authMessageType = 'error';
                $activeTab = 'signin';
            }
        }
    }
}

$page_title = 'Sign In | STORY – Smart Online Store';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="auth.css">
</head>
<body>
  <div class="auth-split">
    <div class="auth-hero">
      <div class="auth-hero-overlay"></div>
      <div class="auth-hero-content">
        <h1 class="auth-hero-title">STORY</h1>
        <p class="auth-hero-tagline">Smart Online Store</p>
        <p class="auth-hero-desc">Discover curated fashion, tech, and lifestyle essentials.</p>
      </div>
      <div class="auth-hero-float">✨</div>
    </div>

    <div class="auth-form-section">
      <div class="auth-form-wrapper">
        <div class="auth-card">
          <div class="auth-header">
            <h2 class="auth-card-title">Welcome back</h2>
            <p class="auth-card-subtitle">Sign in or create an account</p>
          </div>

          <?php if ($authMessage): ?>
            <div class="auth-message auth-message--<?= htmlspecialchars($authMessageType, ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars($authMessage, ENT_QUOTES, 'UTF-8') ?>
            </div>
          <?php endif; ?>

          <div class="auth-tabs">
            <button type="button" class="auth-tab <?= $activeTab === 'signin' ? 'active' : '' ?>" data-tab="signin">Sign In</button>
            <button type="button" class="auth-tab <?= $activeTab === 'join' ? 'active' : '' ?>" data-tab="join">Join</button>
          </div>

          <div class="auth-forms">
            <form id="signin-form" class="auth-form <?= $activeTab === 'signin' ? 'active' : '' ?>" method="post" action="auth.php">
              <input type="hidden" name="action" value="signin">
              <div class="input-group">
                <span class="input-icon">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                    <polyline points="22,6 12,13 2,6"/>
                  </svg>
                </span>
                <input type="email" id="signin-email" name="email" placeholder="Email" required autocomplete="email" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
              </div>
              <div class="input-group input-group--password">
                <span class="input-icon">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                  </svg>
                </span>
                <input type="password" id="signin-password" name="password" placeholder="Password" required autocomplete="current-password">
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
              <a href="#" class="forgot-link">Forgot password?</a>
              <button type="submit" class="auth-btn">Sign In</button>
              <p class="auth-switch">
                Don't have an account? <button type="button" class="auth-link" data-switch="join">Join</button>
              </p>
            </form>

            <form id="join-form" class="auth-form <?= $activeTab === 'join' ? 'active' : '' ?> join-active" method="post" action="auth.php">
              <input type="hidden" name="action" value="join">
              <div class="input-group">
                <span class="input-icon">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                  </svg>
                </span>
                <input type="text" id="join-name" name="name" placeholder="Full Name" required autocomplete="name" value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
              </div>
              <div class="input-group">
                <span class="input-icon">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                    <polyline points="22,6 12,13 2,6"/>
                  </svg>
                </span>
                <input type="email" id="join-email" name="email" placeholder="Email" required autocomplete="email" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
              </div>
              <div class="input-group input-group--password">
                <span class="input-icon">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                  </svg>
                </span>
                <input type="password" id="join-password" name="password" placeholder="Password" required autocomplete="new-password">
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
                <input type="password" id="join-confirm" name="confirm" placeholder="Confirm Password" required autocomplete="new-password">
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
              <button type="submit" class="auth-btn">Create Account</button>
              <p class="auth-switch">
                Already have an account? <button type="button" class="auth-link" data-switch="signin">Sign In</button>
              </p>
            </form>
          </div>

          <div class="auth-social">
            <p class="auth-social-label">Or continue with</p>
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
