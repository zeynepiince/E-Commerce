<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/payments/IyzicoService.php';

$lang = get_current_lang();
$token = trim((string) ($_POST['token'] ?? $_GET['token'] ?? ''));

if ($token === '') {
    header('Location: ' . localized_path('orders.php', ['payment' => 'error']));
    exit;
}

try {
    $result = iyzico_retrieve_checkout($token, $lang);
    update_payment_by_token($pdo, $token, $result['success'] ? 'success' : 'failed', $result['raw']);

    $orderId = resolve_order_id_from_iyzico_result($pdo, $token, $result);

    if ($orderId === null) {
        header('Location: ' . localized_path('orders.php', ['payment' => 'error']));
        exit;
    }

    $ownerStmt = $pdo->prepare('SELECT user_id, payment_status FROM orders WHERE order_id = ? LIMIT 1');
    $ownerStmt->execute([$orderId]);
    $orderRow = $ownerStmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($orderRow)) {
        header('Location: ' . localized_path('orders.php', ['payment' => 'error']));
        exit;
    }

    if (!empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] !== (int) ($orderRow['user_id'] ?? 0)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }

    if ($result['success']) {
        if (($orderRow['payment_status'] ?? '') === 'awaiting_payment') {
            fulfill_order_stock($pdo, $orderId);
            mark_order_payment_paid($pdo, $orderId);
        }
        header('Location: ' . localized_path('orders.php', [
            'order_id' => $orderId,
            'payment' => 'success',
        ]));
        exit;
    }

    if (($orderRow['payment_status'] ?? '') === 'awaiting_payment') {
        mark_order_payment_failed($pdo, $orderId);
    }
    header('Location: ' . localized_path('orders.php', [
        'order_id' => $orderId,
        'payment' => 'failed',
    ]));
} catch (Throwable $e) {
    header('Location: ' . localized_path('orders.php', ['payment' => 'error']));
}
