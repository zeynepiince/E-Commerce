<?php

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/newsletter/NewsletterService.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
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

$email = (string) ($payload['email'] ?? '');
$userId = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
$result = newsletter_subscribe($pdo, $email, $userId, 'homepage');

if (!$result['success']) {
    http_response_code(422);
}

echo json_encode([
    'success' => $result['success'],
    'message' => $result['message'],
    'message_type' => $result['message_type'],
]);
