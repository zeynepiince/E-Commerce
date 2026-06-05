<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/orders/OrderStatusService.php';

header('Content-Type: application/json; charset=utf-8');
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

csrf_require(true);

$orderId = (int) ($_POST['order_id'] ?? 0);
$newStatus = trim((string) ($_POST['status'] ?? ''));
$trackingNumber = trim((string) ($_POST['tracking_number'] ?? ''));
$carrier = trim((string) ($_POST['carrier'] ?? ''));

if ($orderId <= 0 || $newStatus === '') {
    echo json_encode(['success' => false, 'error' => 'Missing order_id or status']);
    exit;
}

try {
    $result = update_order_status($pdo, $orderId, $newStatus, $trackingNumber, $carrier);
    echo json_encode($result);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
