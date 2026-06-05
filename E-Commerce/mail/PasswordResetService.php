<?php

require_once __DIR__ . '/MailService.php';

function password_reset_absolute_url(string $path, array $params = []): string
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

function ensure_password_resets_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS password_resets (
          reset_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          user_id INT UNSIGNED NOT NULL,
          token_hash CHAR(64) NOT NULL,
          expires_at DATETIME NOT NULL,
          used_at DATETIME NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (reset_id),
          KEY idx_password_resets_token (token_hash),
          KEY idx_password_resets_user (user_id),
          KEY idx_password_resets_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function password_reset_rate_limited(): bool
{
    $bucket = $_SESSION['forgot_password_rate'] ?? ['window_start' => time(), 'count' => 0];
    if (!is_array($bucket)) {
        $bucket = ['window_start' => time(), 'count' => 0];
    }
    if ((time() - (int) ($bucket['window_start'] ?? 0)) > 3600) {
        $bucket = ['window_start' => time(), 'count' => 0];
    }
    $bucket['count'] = (int) ($bucket['count'] ?? 0) + 1;
    $_SESSION['forgot_password_rate'] = $bucket;
    return $bucket['count'] > 5;
}

/**
 * @return array{sent: bool, mail_configured: bool}
 */
function request_password_reset(PDO $pdo, string $email, ?string $lang = null): array
{
    ensure_password_resets_table($pdo);

    $lang = $lang ?: (function_exists('get_current_lang') ? get_current_lang() : 'tr');
    if (!in_array($lang, ['tr', 'en'], true)) {
        $lang = 'tr';
    }

    if (!mail_is_enabled()) {
        return ['sent' => false, 'mail_configured' => false];
    }

    $email = trim(strtolower($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['sent' => true, 'mail_configured' => true];
    }

    $stmt = $pdo->prepare('SELECT user_id, full_name, email FROM users WHERE LOWER(email) = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($user)) {
        return ['sent' => true, 'mail_configured' => true];
    }

    $userId = (int) ($user['user_id'] ?? 0);
    if ($userId <= 0) {
        return ['sent' => true, 'mail_configured' => true];
    }

    $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? OR expires_at < NOW()')->execute([$userId]);

    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');

    $insert = $pdo->prepare(
        'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)'
    );
    $insert->execute([$userId, $tokenHash, $expiresAt]);

    $resetUrl = password_reset_absolute_url('reset_password.php', [
        'token' => $rawToken,
        'lang' => $lang,
    ]);

    $content = build_password_reset_email_content(
        (string) ($user['full_name'] ?? ''),
        $resetUrl,
        $lang
    );

    $sent = send_html_mail(
        (string) ($user['email'] ?? $email),
        (string) ($user['full_name'] ?? ''),
        $content['subject'],
        $content['html'],
        $content['text']
    );

    return ['sent' => $sent, 'mail_configured' => true];
}

/**
 * @return array{subject: string, html: string, text: string}
 */
function build_password_reset_email_content(string $name, string $resetUrl, string $lang = 'tr'): array
{
    $site = mail_site_name();
    $firstName = trim($name) !== '' ? trim(explode(' ', trim($name), 2)[0]) : '';

    if ($lang === 'tr') {
        $subject = "{$site} — Şifre sıfırlama";
        $greeting = $firstName !== '' ? "Merhaba {$firstName}," : 'Merhaba,';
        $text = "{$greeting}\n\n"
            . "{$site} hesabınız için şifre sıfırlama talebi aldık.\n"
            . "Aşağıdaki bağlantı 1 saat geçerlidir:\n{$resetUrl}\n\n"
            . "Bu talebi siz yapmadıysanız bu e-postayı yok sayabilirsiniz.\n\n"
            . "{$site}";
        $html = '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#1f2937;max-width:560px;">'
            . '<h2 style="color:#ff6f00;margin:0 0 12px;">Şifre sıfırlama</h2>'
            . '<p>' . htmlspecialchars($greeting, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p>' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8') . ' hesabınız için şifre sıfırlama talebi aldık. Yeni şifrenizi belirlemek için aşağıdaki butona tıklayın.</p>'
            . '<p style="margin:24px 0;"><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '" style="background:#ff6f00;color:#fff;padding:12px 20px;border-radius:8px;text-decoration:none;font-weight:600;">Şifremi sıfırla</a></p>'
            . '<p style="font-size:13px;color:#6b7280;">Bağlantı 1 saat geçerlidir. Talebi siz yapmadıysanız bu e-postayı yok sayabilirsiniz.</p>'
            . '</div>';
    } else {
        $subject = "{$site} — Reset your password";
        $greeting = $firstName !== '' ? "Hi {$firstName}," : 'Hi,';
        $text = "{$greeting}\n\n"
            . "We received a password reset request for your {$site} account.\n"
            . "Use this link within 1 hour:\n{$resetUrl}\n\n"
            . "If you did not request this, you can ignore this email.\n\n"
            . "{$site}";
        $html = '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#1f2937;max-width:560px;">'
            . '<h2 style="color:#ff6f00;margin:0 0 12px;">Reset your password</h2>'
            . '<p>' . htmlspecialchars($greeting, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p>We received a password reset request for your ' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8') . ' account.</p>'
            . '<p style="margin:24px 0;"><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '" style="background:#ff6f00;color:#fff;padding:12px 20px;border-radius:8px;text-decoration:none;font-weight:600;">Reset password</a></p>'
            . '<p style="font-size:13px;color:#6b7280;">This link expires in 1 hour. If you did not request a reset, ignore this email.</p>'
            . '</div>';
    }

    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}

/**
 * @return array{valid: bool, user_id: int|null, reason: string}
 */
function validate_password_reset_token(PDO $pdo, string $rawToken): array
{
    ensure_password_resets_table($pdo);

    $rawToken = trim($rawToken);
    if ($rawToken === '' || !preg_match('/^[a-f0-9]{64}$/i', $rawToken)) {
        return ['valid' => false, 'user_id' => null, 'reason' => 'invalid'];
    }

    $tokenHash = hash('sha256', $rawToken);
    $stmt = $pdo->prepare(
        'SELECT reset_id, user_id, expires_at, used_at
         FROM password_resets
         WHERE token_hash = ?
         LIMIT 1'
    );
    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        return ['valid' => false, 'user_id' => null, 'reason' => 'invalid'];
    }
    if (!empty($row['used_at'])) {
        return ['valid' => false, 'user_id' => null, 'reason' => 'used'];
    }
    if (strtotime((string) ($row['expires_at'] ?? '')) < time()) {
        return ['valid' => false, 'user_id' => null, 'reason' => 'expired'];
    }

    return ['valid' => true, 'user_id' => (int) ($row['user_id'] ?? 0), 'reason' => 'ok'];
}

function complete_password_reset(PDO $pdo, string $rawToken, string $newPassword): bool
{
    $check = validate_password_reset_token($pdo, $rawToken);
    if (!$check['valid'] || empty($check['user_id'])) {
        return false;
    }

    if (strlen($newPassword) < 8) {
        return false;
    }

    $userId = (int) $check['user_id'];
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $tokenHash = hash('sha256', trim($rawToken));

    $pdo->beginTransaction();
    try {
        $updateUser = $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
        $updateUser->execute([$hash, $userId]);

        $markUsed = $pdo->prepare(
            'UPDATE password_resets SET used_at = NOW() WHERE token_hash = ? AND used_at IS NULL'
        );
        $markUsed->execute([$tokenHash]);

        $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? AND token_hash <> ?')
            ->execute([$userId, $tokenHash]);

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
