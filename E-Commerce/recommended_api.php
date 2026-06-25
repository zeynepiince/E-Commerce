<?php

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/recommended.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

csrf_require(true);

$payload = zera_read_post_payload();

// --- Chat message (InfinityFree blocks chatbot_*.php URLs) ---
$hasChatMessage = array_key_exists('message', $payload)
    || trim((string) ($payload['quick_action'] ?? '')) !== ''
    || trim((string) ($_GET['quick_action'] ?? '')) !== '';
if ($hasChatMessage) {
    require_once __DIR__ . '/chatbot/helpers.php';
    require_once __DIR__ . '/chatbot/responses.php';
    require_once __DIR__ . '/chatbot/actions.php';
    require_once __DIR__ . '/chatbot/ai.php';
    require_once __DIR__ . '/chatbot/intent.php';
    require_once __DIR__ . '/chatbot/consistency.php';
    $data = $payload;
    require __DIR__ . '/chatbot/serve_message.php';
    exit;
}

// --- Chat feedback (👍/👎) ---
if (($payload['action'] ?? '') === 'submit' && array_key_exists('helpful', $payload)) {
    $data = $payload;
    require __DIR__ . '/chatbot/serve_feedback.php';
    exit;
}

// --- Homepage recommendation grid ---
$favoriteIds = parse_favorite_product_ids($payload['favorite_ids'] ?? []);
$userId = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$limit = is_numeric($payload['limit'] ?? null) ? max(1, min(8, (int) $payload['limit'])) : 4;

$products = get_ai_recommendations($pdo, $userId, $limit, $favoriteIds);

ob_start();
foreach ($products as $idx => $product) {
    $badges = get_product_badges($product, 'recommended', $idx);
    include __DIR__ . '/includes/product_card.php';
}
$html = ob_get_clean() ?: '';

echo json_encode([
    'ok' => true,
    'count' => count($products),
    'html' => $html,
]);
