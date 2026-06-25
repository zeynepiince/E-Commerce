<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth/AuthService.php';
require_once __DIR__ . '/payments/IyzicoService.php';

$lang = get_current_lang();
$token = trim((string) ($_POST['token'] ?? $_GET['token'] ?? ''));

if ($token === '') {
    header('Location: ' . localized_path('orders.php', ['payment' => 'error']));
    exit;
}

/**
 * iyzico cross-site POST does not send SameSite=Lax cookies; restore order owner after verified callback.
 */
function payment_callback_restore_user_session(PDO $pdo, int $orderUserId): void
{
    if ($orderUserId <= 0 || !empty($_SESSION['user_id'])) {
        return;
    }

    $userStmt = $pdo->prepare('SELECT user_id, full_name, email FROM users WHERE user_id = ? LIMIT 1');
    $userStmt->execute([$orderUserId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($user)) {
        auth_set_session_user($user);
    }
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

    $orderUserId = (int) ($orderRow['user_id'] ?? 0);

    if (!empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] !== $orderUserId) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }

    payment_callback_restore_user_session($pdo, $orderUserId);

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
