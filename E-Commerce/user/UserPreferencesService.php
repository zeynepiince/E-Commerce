<?php

function user_prefs_ensure_schema(PDO $pdo): void
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
    if (!isset($columns['email_notifications'])) {
        $pdo->exec('ALTER TABLE users ADD COLUMN email_notifications TINYINT(1) NOT NULL DEFAULT 1');
    }
}

function user_prefs_get_email_notifications(PDO $pdo, int $userId): bool
{
    user_prefs_ensure_schema($pdo);

    $stmt = $pdo->prepare('SELECT email_notifications FROM users WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return (bool) ((int) ($row['email_notifications'] ?? 1));
}

function user_prefs_save_email_notifications(PDO $pdo, int $userId, bool $enabled): void
{
    user_prefs_ensure_schema($pdo);

    $stmt = $pdo->prepare('UPDATE users SET email_notifications = ? WHERE user_id = ?');
    $stmt->execute([$enabled ? 1 : 0, $userId]);
}
