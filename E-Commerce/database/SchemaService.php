<?php

/**
 * Idempotent schema guards for incremental migrations not covered elsewhere.
 * Safe to call on every request (runs once per process).
 */
function ensure_application_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    ensure_product_catalog_columns($pdo);
    ensure_chatbot_feedback_table($pdo);
    ensure_support_interactions_table($pdo);
    ensure_support_interactions_sender_check($pdo);
}

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
         LIMIT 1'
    );
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
         LIMIT 1'
    );
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

function ensure_product_catalog_columns(PDO $pdo): void
{
    if (!table_exists($pdo, 'products')) {
        return;
    }

    $alters = [];
    if (!column_exists($pdo, 'products', 'badges')) {
        $alters[] = "ADD COLUMN badges JSON NULL COMMENT 'Product labels'";
    }
    if (!column_exists($pdo, 'products', 'sizes')) {
        $alters[] = 'ADD COLUMN sizes VARCHAR(255) NULL DEFAULT NULL AFTER stock_quantity';
    }

    if ($alters !== []) {
        $pdo->exec('ALTER TABLE products ' . implode(', ', $alters));
    }
}

function ensure_chatbot_feedback_table(PDO $pdo): void
{
    if (table_exists($pdo, 'chatbot_feedback')) {
        return;
    }

    $pdo->exec("
        CREATE TABLE chatbot_feedback (
          feedback_id INT NOT NULL AUTO_INCREMENT,
          user_id INT NULL DEFAULT NULL,
          helpful TINYINT(1) NOT NULL,
          intent VARCHAR(50) NULL DEFAULT NULL,
          source VARCHAR(50) NULL DEFAULT NULL,
          confidence DECIMAL(4,2) NULL DEFAULT NULL,
          used_ai TINYINT(1) NOT NULL DEFAULT 0,
          escalated_to_human TINYINT(1) NOT NULL DEFAULT 0,
          experiment_mode VARCHAR(20) NULL DEFAULT NULL,
          experiment_bucket VARCHAR(20) NULL DEFAULT NULL,
          experiment_variants JSON NULL,
          user_message TEXT NULL,
          bot_reply TEXT NULL,
          page VARCHAR(255) NULL DEFAULT NULL,
          created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (feedback_id),
          KEY idx_chatbot_feedback_intent (intent),
          KEY idx_chatbot_feedback_created (created_at),
          KEY idx_chatbot_feedback_user (user_id),
          CONSTRAINT fk_chatbot_feedback_user
            FOREIGN KEY (user_id) REFERENCES users (user_id)
            ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function ensure_support_interactions_table(PDO $pdo): void
{
    if (table_exists($pdo, 'support_interactions')) {
        return;
    }

    $pdo->exec("
        CREATE TABLE support_interactions (
          interaction_id INT NOT NULL AUTO_INCREMENT,
          user_id INT NOT NULL,
          message TEXT NOT NULL,
          sender ENUM('user', 'bot') NOT NULL,
          intent VARCHAR(50) NULL DEFAULT NULL,
          recommended_product_id INT NULL DEFAULT NULL,
          created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (interaction_id),
          KEY idx_support_interactions_user (user_id),
          KEY idx_support_interactions_product (recommended_product_id),
          CONSTRAINT fk_support_interactions_user
            FOREIGN KEY (user_id) REFERENCES users (user_id)
            ON DELETE CASCADE ON UPDATE CASCADE,
          CONSTRAINT fk_support_interactions_product
            FOREIGN KEY (recommended_product_id) REFERENCES products (product_id)
            ON DELETE SET NULL ON UPDATE CASCADE,
          CONSTRAINT chk_support_interactions_sender CHECK (sender IN ('user', 'bot'))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function ensure_support_interactions_sender_check(PDO $pdo): void
{
    if (!table_exists($pdo, 'support_interactions')) {
        return;
    }

    try {
        $row = $pdo->query('SHOW CREATE TABLE support_interactions')->fetch(PDO::FETCH_NUM);
        $ddl = strtolower((string) ($row[1] ?? ''));
        if ($ddl !== '' && (str_contains($ddl, "'bot'") || str_contains($ddl, '`bot`'))) {
            return;
        }
    } catch (Throwable $e) {
        // Fall through to repair attempt.
    }

    try {
        $pdo->exec('ALTER TABLE support_interactions DROP CHECK support_interactions_chk_1');
    } catch (Throwable $e) {
        // Constraint missing or already correct.
    }

    try {
        $pdo->exec("
            ALTER TABLE support_interactions
            ADD CONSTRAINT support_interactions_chk_1 CHECK (sender IN ('user', 'bot'))
        ");
    } catch (Throwable $e) {
        // Enum/CHECK already allows bot.
    }
}
