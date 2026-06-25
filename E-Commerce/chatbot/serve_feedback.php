<?php

/**
 * Chat thumbs up/down handler. Expects $data request payload.
 */

if (!isset($data) || !is_array($data) || ($data['action'] ?? '') !== 'submit') {
    echo json_encode(['ok' => false, 'error' => 'invalid_request']);
    exit;
}

$helpful = !empty($data['helpful']);
$userId = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
$intent = trim((string) ($data['intent'] ?? 'general'));
$source = trim((string) ($data['source'] ?? 'rule_based'));
$confidence = is_numeric($data['confidence'] ?? null) ? round((float) $data['confidence'], 2) : null;
$usedAi = !empty($data['used_ai']);
$escalated = !empty($data['escalated_to_human']);
$experimentMode = trim((string) ($data['experiment_mode'] ?? ''));
$experimentBucket = trim((string) ($data['experiment_bucket'] ?? ''));
$experimentVariants = $data['experiment_variants'] ?? null;
$userMessage = trim((string) ($data['user_message'] ?? ''));
$botReply = trim((string) ($data['bot_reply'] ?? ''));
$page = trim((string) ($data['page'] ?? ''));

if ($experimentMode === '' || $experimentBucket === '') {
    $memory = $_SESSION['chatbot_memory']['last_experiment'] ?? [];
    if (is_array($memory)) {
        if ($experimentMode === '' && !empty($memory['mode'])) {
            $experimentMode = (string) $memory['mode'];
        }
        if ($experimentBucket === '' && !empty($memory['bucket'])) {
            $experimentBucket = (string) $memory['bucket'];
        }
        if ($experimentVariants === null && !empty($memory['variants']) && is_array($memory['variants'])) {
            $experimentVariants = $memory['variants'];
        }
    }
}

$variantsJson = null;
if (is_array($experimentVariants)) {
    $variantsJson = json_encode($experimentVariants, JSON_UNESCAPED_UNICODE);
}

ensure_chatbot_feedback_table($pdo);

try {
    $stmt = $pdo->prepare("
        INSERT INTO chatbot_feedback (
            user_id, helpful, intent, source, confidence, used_ai, escalated_to_human,
            experiment_mode, experiment_bucket, experiment_variants,
            user_message, bot_reply, page
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        $helpful ? 1 : 0,
        $intent !== '' ? $intent : null,
        $source !== '' ? $source : null,
        $confidence,
        $usedAi ? 1 : 0,
        $escalated ? 1 : 0,
        $experimentMode !== '' ? $experimentMode : null,
        $experimentBucket !== '' ? $experimentBucket : null,
        $variantsJson,
        $userMessage !== '' ? $userMessage : null,
        $botReply !== '' ? $botReply : null,
        $page !== '' ? $page : null,
    ]);
    echo json_encode(['ok' => true, 'feedback_id' => (int) $pdo->lastInsertId()]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'save_failed']);
}
