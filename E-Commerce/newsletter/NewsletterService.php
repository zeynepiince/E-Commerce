<?php

function newsletter_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS newsletter_subscribers (
          subscriber_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          email VARCHAR(255) NOT NULL,
          user_id INT NULL DEFAULT NULL,
          source VARCHAR(50) NOT NULL DEFAULT 'homepage',
          subscribed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (subscriber_id),
          UNIQUE KEY uq_newsletter_email (email),
          KEY idx_newsletter_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_ASSOC) as $col) {
        $columns[(string) $col['Field']] = true;
    }
    if (!isset($columns['newsletter_opt_in'])) {
        $pdo->exec('ALTER TABLE users ADD COLUMN newsletter_opt_in TINYINT(1) NOT NULL DEFAULT 0');
    }
    if (!isset($columns['email_notifications'])) {
        $pdo->exec('ALTER TABLE users ADD COLUMN email_notifications TINYINT(1) NOT NULL DEFAULT 1');
    }
}

/**
 * @return array{success:bool, message:string, message_type:string}
 */
function newsletter_subscribe(PDO $pdo, string $email, ?int $userId = null, string $source = 'homepage'): array
{
    newsletter_ensure_schema($pdo);

    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'message' => t('newsletter.error_invalid_email', 'Please enter a valid email address.'),
            'message_type' => 'error',
        ];
    }

    if ($userId === null && !empty($_SESSION['user_id'])) {
        $userId = (int) $_SESSION['user_id'];
    }

    if ($userId !== null && $userId > 0) {
        $stmt = $pdo->prepare('UPDATE users SET newsletter_opt_in = 1 WHERE user_id = ?');
        $stmt->execute([$userId]);
    }

    $stmt = $pdo->prepare('SELECT subscriber_id FROM newsletter_subscribers WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        if ($userId !== null && $userId > 0) {
            $pdo->prepare('UPDATE newsletter_subscribers SET user_id = ? WHERE email = ?')->execute([$userId, $email]);
        }
        return [
            'success' => true,
            'message' => t('newsletter.already_subscribed', 'You are already on our newsletter list.'),
            'message_type' => 'success',
        ];
    }

    $insert = $pdo->prepare(
        'INSERT INTO newsletter_subscribers (email, user_id, source) VALUES (?, ?, ?)'
    );
    $insert->execute([$email, $userId ?: null, $source]);

    return [
        'success' => true,
        'message' => t('newsletter.subscribed', 'Thanks! You have been added to our newsletter.'),
        'message_type' => 'success',
    ];
}

/** @return array{newsletter_opt_in:bool,email_notifications:bool} */
function newsletter_get_user_preferences(PDO $pdo, int $userId): array
{
    newsletter_ensure_schema($pdo);

    $stmt = $pdo->prepare('SELECT newsletter_opt_in, email_notifications FROM users WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'newsletter_opt_in' => (bool) ((int) ($row['newsletter_opt_in'] ?? 0)),
        'email_notifications' => (bool) ((int) ($row['email_notifications'] ?? 1)),
    ];
}

function newsletter_save_user_preferences(PDO $pdo, int $userId, bool $emailNotifications, bool $newsletterOptIn): void
{
    newsletter_ensure_schema($pdo);

    $stmt = $pdo->prepare(
        'UPDATE users SET email_notifications = ?, newsletter_opt_in = ? WHERE user_id = ?'
    );
    $stmt->execute([
        $emailNotifications ? 1 : 0,
        $newsletterOptIn ? 1 : 0,
        $userId,
    ]);

    if ($newsletterOptIn) {
        $userStmt = $pdo->prepare('SELECT email FROM users WHERE user_id = ? LIMIT 1');
        $userStmt->execute([$userId]);
        $email = strtolower(trim((string) ($userStmt->fetchColumn() ?: '')));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $exists = $pdo->prepare('SELECT subscriber_id FROM newsletter_subscribers WHERE email = ? LIMIT 1');
            $exists->execute([$email]);
            if (!$exists->fetch()) {
                $insert = $pdo->prepare(
                    'INSERT INTO newsletter_subscribers (email, user_id, source) VALUES (?, ?, ?)'
                );
                $insert->execute([$email, $userId, 'profile']);
            } else {
                $pdo->prepare('UPDATE newsletter_subscribers SET user_id = ? WHERE email = ?')
                    ->execute([$userId, $email]);
            }
        }
    }
}
