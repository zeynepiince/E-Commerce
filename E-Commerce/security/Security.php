<?php

function zera_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        return true;
    }
    return strtolower(trim((string) (getenv('FORCE_HTTPS') ?: 'false'))) === 'true';
}

function zera_init_session(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    $secure = zera_is_https();
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, '/; samesite=Lax', '', $secure, true);
    }

    session_start();

    if (empty($_SESSION['_session_boot'])) {
        session_regenerate_id(true);
        $_SESSION['_session_boot'] = true;
    }

    csrf_token();
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_rotate(): void
{
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_field_html(): string
{
    $token = csrf_token();
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_submitted_token(): ?string
{
    $header = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($header !== '') {
        return $header;
    }
    if (!empty($_POST['csrf_token']) && is_string($_POST['csrf_token'])) {
        return $_POST['csrf_token'];
    }
    return null;
}

function csrf_verify(?string $token = null): bool
{
    $token = $token ?? csrf_submitted_token();
    $expected = (string) ($_SESSION['_csrf_token'] ?? '');
    return is_string($token) && $token !== '' && $expected !== '' && hash_equals($expected, $token);
}

function csrf_deny(bool $asJson = true): void
{
    http_response_code(403);
    if ($asJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Invalid or missing CSRF token',
            'code' => 'csrf_invalid',
        ]);
    } else {
        echo 'Forbidden: invalid CSRF token';
    }
    exit;
}

function csrf_require(bool $asJson = true): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    if (!csrf_verify()) {
        csrf_deny($asJson);
    }
}

function rate_limit_storage_dir(): string
{
    $dir = rtrim(sys_get_temp_dir(), '/') . '/zera_rate_' . substr(hash('sha256', __DIR__), 0, 16);
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    return $dir;
}

function rate_limit_client_key(): string
{
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
    return hash('sha256', $ip);
}

/**
 * @return array{count:int,window_start:int,locked_until:int}
 */
function rate_limit_read(string $bucket): array
{
    $path = rate_limit_storage_dir() . '/' . preg_replace('/[^a-z0-9_\-]/i', '_', $bucket)
        . '_' . rate_limit_client_key() . '.json';
    if (!is_readable($path)) {
        return ['count' => 0, 'window_start' => time(), 'locked_until' => 0];
    }
    $raw = @file_get_contents($path);
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) {
        return ['count' => 0, 'window_start' => time(), 'locked_until' => 0];
    }
    return [
        'count' => (int) ($data['count'] ?? 0),
        'window_start' => (int) ($data['window_start'] ?? time()),
        'locked_until' => (int) ($data['locked_until'] ?? 0),
    ];
}

function rate_limit_write(string $bucket, array $state): void
{
    $path = rate_limit_storage_dir() . '/' . preg_replace('/[^a-z0-9_\-]/i', '_', $bucket)
        . '_' . rate_limit_client_key() . '.json';
    @file_put_contents($path, json_encode([
        'count' => (int) ($state['count'] ?? 0),
        'window_start' => (int) ($state['window_start'] ?? time()),
        'locked_until' => (int) ($state['locked_until'] ?? 0),
    ]), LOCK_EX);
}

function rate_limit_is_blocked(string $bucket, int $maxAttempts, int $windowSeconds, int $lockSeconds): bool
{
    $state = rate_limit_read($bucket);
    $now = time();

    if ($state['locked_until'] > $now) {
        return true;
    }

    if (($now - $state['window_start']) > $windowSeconds) {
        return false;
    }

    return $state['count'] >= $maxAttempts;
}

function rate_limit_record_failure(string $bucket, int $maxAttempts, int $windowSeconds, int $lockSeconds): void
{
    $state = rate_limit_read($bucket);
    $now = time();

    if ($state['locked_until'] > $now) {
        return;
    }

    if (($now - $state['window_start']) > $windowSeconds) {
        $state = ['count' => 0, 'window_start' => $now, 'locked_until' => 0];
    }

    $state['count']++;
    if ($state['count'] >= $maxAttempts) {
        $state['locked_until'] = $now + $lockSeconds;
    }

    rate_limit_write($bucket, $state);
}

function rate_limit_clear(string $bucket): void
{
    $path = rate_limit_storage_dir() . '/' . preg_replace('/[^a-z0-9_\-]/i', '_', $bucket)
        . '_' . rate_limit_client_key() . '.json';
    if (is_file($path)) {
        @unlink($path);
    }
}

function login_rate_limited(): bool
{
    $max = (int) (getenv('LOGIN_RATE_LIMIT_MAX') ?: 8);
    $window = (int) (getenv('LOGIN_RATE_LIMIT_WINDOW') ?: 900);
    $lock = (int) (getenv('LOGIN_RATE_LIMIT_LOCK') ?: 900);
    return rate_limit_is_blocked('login', max(1, $max), max(60, $window), max(60, $lock));
}

function login_rate_record_failure(): void
{
    $max = (int) (getenv('LOGIN_RATE_LIMIT_MAX') ?: 8);
    $window = (int) (getenv('LOGIN_RATE_LIMIT_WINDOW') ?: 900);
    $lock = (int) (getenv('LOGIN_RATE_LIMIT_LOCK') ?: 900);
    rate_limit_record_failure('login', max(1, $max), max(60, $window), max(60, $lock));
}

function login_rate_clear(): void
{
    rate_limit_clear('login');
}

function zera_destroy_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }
    session_destroy();
}
