<?php

require_once __DIR__ . '/AuthService.php';

const OAUTH_PROVIDERS = ['google', 'facebook'];

function oauth_absolute_url(string $path, array $params = []): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = function_exists('site_base_path') ? site_base_path() : '';
    $url = $scheme . '://' . $host . ($base !== '' ? $base : '') . '/' . ltrim($path, '/');
    if ($params !== []) {
        $url .= '?' . http_build_query($params);
    }
    return $url;
}

function oauth_env(string $key): string
{
    return trim((string) (getenv($key) ?: ''));
}

function oauth_google_enabled(): bool
{
    return oauth_env('GOOGLE_CLIENT_ID') !== '' && oauth_env('GOOGLE_CLIENT_SECRET') !== '';
}

function oauth_facebook_enabled(): bool
{
    return oauth_env('FACEBOOK_APP_ID') !== '' && oauth_env('FACEBOOK_APP_SECRET') !== '';
}

function oauth_provider_enabled(string $provider): bool
{
    return match ($provider) {
        'google' => oauth_google_enabled(),
        'facebook' => oauth_facebook_enabled(),
        default => false,
    };
}

function oauth_callback_uri(): string
{
    $custom = oauth_env('OAUTH_REDIRECT_URI');
    if ($custom !== '') {
        return $custom;
    }
    return oauth_absolute_url('oauth_callback.php');
}

function oauth_start_url(string $provider, ?string $returnUrl = null): ?string
{
    if (!oauth_provider_enabled($provider)) {
        return null;
    }
    $returnUrl = auth_safe_return_url($returnUrl);
    $params = [
        'provider' => $provider,
        'return' => $returnUrl,
    ];
    return oauth_absolute_url('oauth_start.php', $params);
}

/** @return array{google:?string,facebook:?string} */
function oauth_login_links(?string $returnUrl = null): array
{
    return [
        'google' => oauth_start_url('google', $returnUrl),
        'facebook' => oauth_start_url('facebook', $returnUrl),
    ];
}

function oauth_ensure_columns(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_ASSOC) as $col) {
        $columns[(string) $col['Field']] = true;
    }
    if (!isset($columns['google_id'])) {
        $pdo->exec('ALTER TABLE users ADD COLUMN google_id VARCHAR(255) NULL DEFAULT NULL AFTER password_hash');
    }
    if (!isset($columns['facebook_id'])) {
        $pdo->exec('ALTER TABLE users ADD COLUMN facebook_id VARCHAR(255) NULL DEFAULT NULL AFTER google_id');
    }
}

function oauth_begin(string $provider, string $returnUrl): void
{
    if (!in_array($provider, OAUTH_PROVIDERS, true) || !oauth_provider_enabled($provider)) {
        http_response_code(404);
        echo 'OAuth provider not configured.';
        exit;
    }

    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;
    $_SESSION['oauth_provider'] = $provider;
    $_SESSION['oauth_return'] = auth_safe_return_url($returnUrl);

    $redirectUri = oauth_callback_uri();

    if ($provider === 'google') {
        $query = http_build_query([
            'client_id' => oauth_env('GOOGLE_CLIENT_ID'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]);
        header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $query);
        exit;
    }

    $query = http_build_query([
        'client_id' => oauth_env('FACEBOOK_APP_ID'),
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'email,public_profile',
        'state' => $state,
    ]);
    header('Location: https://www.facebook.com/v18.0/dialog/oauth?' . $query);
    exit;
}

function oauth_http_post_form(string $url, array $fields): array
{
    $body = http_build_query($fields);
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $body,
            'timeout' => 20,
            'ignore_errors' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    if ($raw === false) {
        throw new RuntimeException('OAuth token request failed.');
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Invalid OAuth token response.');
    }
    return $decoded;
}

function oauth_http_get_json(string $url): array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
            'ignore_errors' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    if ($raw === false) {
        throw new RuntimeException('OAuth profile request failed.');
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Invalid OAuth profile response.');
    }
    return $decoded;
}

/**
 * @return array{provider_id:string,email:string,name:string}
 */
function oauth_fetch_profile(string $provider, string $code): array
{
    $redirectUri = oauth_callback_uri();

    if ($provider === 'google') {
        $token = oauth_http_post_form('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => oauth_env('GOOGLE_CLIENT_ID'),
            'client_secret' => oauth_env('GOOGLE_CLIENT_SECRET'),
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);
        if (empty($token['access_token'])) {
            throw new RuntimeException('Google token exchange failed.');
        }
        $profile = oauth_http_get_json('https://www.googleapis.com/oauth2/v3/userinfo?access_token=' . rawurlencode((string) $token['access_token']));
        $email = trim((string) ($profile['email'] ?? ''));
        if ($email === '') {
            throw new RuntimeException('Google account has no email.');
        }
        return [
            'provider_id' => (string) ($profile['sub'] ?? ''),
            'email' => $email,
            'name' => trim((string) ($profile['name'] ?? 'Google User')),
        ];
    }

    $tokenUrl = 'https://graph.facebook.com/v18.0/oauth/access_token?' . http_build_query([
        'client_id' => oauth_env('FACEBOOK_APP_ID'),
        'client_secret' => oauth_env('FACEBOOK_APP_SECRET'),
        'redirect_uri' => $redirectUri,
        'code' => $code,
    ]);
    $token = oauth_http_get_json($tokenUrl);
    if (empty($token['access_token'])) {
        throw new RuntimeException('Facebook token exchange failed.');
    }
    $profileUrl = 'https://graph.facebook.com/me?' . http_build_query([
        'fields' => 'id,name,email',
        'access_token' => $token['access_token'],
    ]);
    $profile = oauth_http_get_json($profileUrl);
    $email = trim((string) ($profile['email'] ?? ''));
    if ($email === '') {
        throw new RuntimeException('Facebook account has no email. Allow email permission.');
    }
    return [
        'provider_id' => (string) ($profile['id'] ?? ''),
        'email' => $email,
        'name' => trim((string) ($profile['name'] ?? 'Facebook User')),
    ];
}

function oauth_find_or_create_user(PDO $pdo, string $provider, array $profile): array
{
    oauth_ensure_columns($pdo);

    $idColumn = $provider === 'google' ? 'google_id' : 'facebook_id';
    $providerId = (string) ($profile['provider_id'] ?? '');
    $email = trim((string) ($profile['email'] ?? ''));
    $name = trim((string) ($profile['name'] ?? 'User'));

    if ($providerId === '' || $email === '') {
        throw new RuntimeException('Incomplete OAuth profile.');
    }

    $stmt = $pdo->prepare("SELECT user_id, full_name, email FROM users WHERE {$idColumn} = ? LIMIT 1");
    $stmt->execute([$providerId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        return $existing;
    }

    $stmt = $pdo->prepare('SELECT user_id, full_name, email FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $byEmail = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($byEmail) {
        $update = $pdo->prepare("UPDATE users SET {$idColumn} = ? WHERE user_id = ?");
        $update->execute([$providerId, (int) $byEmail['user_id']]);
        return $byEmail;
    }

    $hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    $insert = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, {$idColumn}) VALUES (?, ?, ?, ?)");
    $insert->execute([$name, $email, $hash, $providerId]);

    return [
        'user_id' => (int) $pdo->lastInsertId(),
        'full_name' => $name,
        'email' => $email,
    ];
}

function oauth_complete_callback(PDO $pdo, string $code, string $state): string
{
    $expectedState = (string) ($_SESSION['oauth_state'] ?? '');
    $provider = (string) ($_SESSION['oauth_provider'] ?? '');
    $returnUrl = auth_safe_return_url($_SESSION['oauth_return'] ?? 'index.php');

    unset($_SESSION['oauth_state'], $_SESSION['oauth_provider'], $_SESSION['oauth_return']);

    if ($expectedState === '' || !hash_equals($expectedState, $state)) {
        throw new RuntimeException(t('auth.oauth_state_invalid', 'Sign-in session expired. Please try again.'));
    }
    if (!in_array($provider, OAUTH_PROVIDERS, true)) {
        throw new RuntimeException(t('auth.oauth_provider_invalid', 'Unknown sign-in provider.'));
    }

    $profile = oauth_fetch_profile($provider, $code);
    $user = oauth_find_or_create_user($pdo, $provider, $profile);
    auth_set_session_user($user);

    return $returnUrl;
}
