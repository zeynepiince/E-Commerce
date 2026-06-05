<?php

require_once __DIR__ . '/../mail/MailService.php';
require_once __DIR__ . '/../security/Security.php';

function auth_safe_return_url(?string $raw): string
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

function auth_set_session_user(array $user): void
{
    session_regenerate_id(true);
    csrf_rotate();
    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['user_name'] = (string) ($user['full_name'] ?? $user['name'] ?? '');
    $_SESSION['user_email'] = (string) ($user['email'] ?? '');
}

/**
 * @return array{success:bool, message?:string, message_type?:string, active_tab?:string, user?:array}
 */
function auth_process_signin(PDO $pdo, string $email, string $password): array
{
    $email = trim($email);

    if (login_rate_limited()) {
        return [
            'success' => false,
            'message' => t('auth.signin_rate_limit', 'Too many sign-in attempts. Please try again later.'),
            'message_type' => 'error',
            'active_tab' => 'signin',
            'code' => 'rate_limited',
        ];
    }

    if ($email === '' || $password === '') {
        return [
            'success' => false,
            'message' => t('auth.error_required', 'Email and password are required.'),
            'message_type' => 'error',
            'active_tab' => 'signin',
        ];
    }

    $stmt = $pdo->prepare('SELECT user_id, full_name, email, password_hash FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        login_rate_record_failure();
        return [
            'success' => false,
            'message' => t('auth.error_invalid_credentials', 'Invalid email or password.'),
            'message_type' => 'error',
            'active_tab' => 'signin',
        ];
    }

    login_rate_clear();
    auth_set_session_user($user);

    return [
        'success' => true,
        'user' => [
            'user_id' => (int) $user['user_id'],
            'name' => (string) $user['full_name'],
            'email' => (string) $user['email'],
        ],
    ];
}

/**
 * @return array{success:bool, message?:string, message_type?:string, active_tab?:string, user?:array}
 */
function auth_process_join(PDO $pdo, string $name, string $email, string $password, string $confirm, string $lang): array
{
    $name = trim($name);
    $email = trim($email);

    if ($name === '' || $email === '' || $password === '' || $confirm === '') {
        return [
            'success' => false,
            'message' => t('auth.error_all_fields', 'All fields are required.'),
            'message_type' => 'error',
            'active_tab' => 'join',
        ];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'message' => t('auth.error_invalid_email', 'Please enter a valid email address.'),
            'message_type' => 'error',
            'active_tab' => 'join',
        ];
    }

    if (strlen($password) < 8) {
        return [
            'success' => false,
            'message' => t('auth.error_password_min', 'Password must be at least 8 characters.'),
            'message_type' => 'error',
            'active_tab' => 'join',
        ];
    }

    if ($password !== $confirm) {
        return [
            'success' => false,
            'message' => t('auth.error_passwords_mismatch', 'Passwords do not match.'),
            'message_type' => 'error',
            'active_tab' => 'join',
        ];
    }

    $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return [
            'success' => false,
            'message' => t('auth.error_email_exists', 'An account with this email already exists.'),
            'message_type' => 'error',
            'active_tab' => 'join',
        ];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)');
    $stmt->execute([$name, $email, $hash]);

    $welcomeSent = send_welcome_email($name, $email, $lang);
    $message = $welcomeSent
        ? t('auth.success_account_created_welcome_mail', 'Account created! A welcome email has been sent. You can now sign in.')
        : t('auth.success_account_created', 'Account created successfully! You can now sign in.');

    return [
        'success' => true,
        'message' => $message,
        'message_type' => 'success',
        'active_tab' => 'signin',
    ];
}
