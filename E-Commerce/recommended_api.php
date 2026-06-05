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

$payload = $_POST;
$contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
if (stripos($contentType, 'application/json') !== false) {
    $decoded = json_decode(file_get_contents('php://input') ?: '', true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}

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
