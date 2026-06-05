<?php

function ensure_order_fulfillment_columns(PDO $pdo): void
{
    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM orders')->fetchAll(PDO::FETCH_ASSOC) as $col) {
        $columns[(string) ($col['Field'] ?? '')] = true;
    }

    $alters = [];
    if (!isset($columns['tracking_number'])) {
        $alters[] = 'ADD COLUMN tracking_number VARCHAR(64) NULL';
    }
    if (!isset($columns['carrier'])) {
        $alters[] = 'ADD COLUMN carrier VARCHAR(64) NULL';
    }
    if (!isset($columns['shipped_at'])) {
        $alters[] = 'ADD COLUMN shipped_at DATETIME NULL';
    }
    if (!isset($columns['delivered_at'])) {
        $alters[] = 'ADD COLUMN delivered_at DATETIME NULL';
    }

    if ($alters !== []) {
        $pdo->exec('ALTER TABLE orders ' . implode(', ', $alters));
    }
}

/**
 * @return array<int, string>
 */
function order_status_transitions(): array
{
    return [
        'pending' => ['shipped', 'cancelled'],
        'shipped' => ['delivered'],
    ];
}

function normalize_order_payment_status(string $paymentStatus): string
{
    $paymentStatus = strtolower(trim($paymentStatus));
    if ($paymentStatus === 'unpaid') {
        return 'awaiting_payment';
    }
    return $paymentStatus;
}

function resolve_order_display_status_key(string $fulfillmentStatus, string $paymentStatus): string
{
    $rawStatus = strtolower(trim($fulfillmentStatus));
    $paymentStatus = normalize_order_payment_status($paymentStatus);
    if (in_array($paymentStatus, ['awaiting_payment', 'failed'], true)) {
        return $paymentStatus;
    }
    if ($paymentStatus === 'paid' && $rawStatus === 'pending') {
        return 'processing';
    }
    return $rawStatus;
}

/**
 * @return array<string, mixed>|null
 */
function fetch_order_for_status_update(PDO $pdo, int $orderId): ?array
{
    ensure_order_fulfillment_columns($pdo);
    $stmt = $pdo->prepare(
        'SELECT order_id, user_id, status, payment_status, tracking_number, carrier, shipped_at, delivered_at
         FROM orders WHERE order_id = ? LIMIT 1'
    );
    $stmt->execute([$orderId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

/**
 * @return array{success: bool, status?: string, error?: string}
 */
function update_order_status(
    PDO $pdo,
    int $orderId,
    string $newStatus,
    ?string $trackingNumber = null,
    ?string $carrier = null
): array {
    ensure_order_fulfillment_columns($pdo);

    $newStatus = strtolower(trim($newStatus));
    $allowedStatuses = ['pending', 'shipped', 'delivered', 'cancelled'];
    if (!in_array($newStatus, $allowedStatuses, true)) {
        return ['success' => false, 'error' => 'Invalid status'];
    }

    $order = fetch_order_for_status_update($pdo, $orderId);
    if ($order === null) {
        return ['success' => false, 'error' => 'Order not found'];
    }

    $currentStatus = strtolower((string) ($order['status'] ?? 'pending'));
    $paymentStatus = normalize_order_payment_status((string) ($order['payment_status'] ?? 'paid'));

    if ($currentStatus === $newStatus) {
        return ['success' => true, 'status' => $newStatus];
    }

    $transitions = order_status_transitions();
    $allowedNext = $transitions[$currentStatus] ?? [];
    if (!in_array($newStatus, $allowedNext, true)) {
        return ['success' => false, 'error' => 'Status transition not allowed'];
    }

    if (in_array($newStatus, ['shipped', 'delivered'], true) && $paymentStatus !== 'paid') {
        return ['success' => false, 'error' => 'Order must be paid before shipping'];
    }

    if ($newStatus === 'shipped') {
        $trackingNumber = trim((string) $trackingNumber);
        if ($trackingNumber === '') {
            require_once __DIR__ . '/TrackingService.php';
            $trackingNumber = generate_demo_tracking_number($orderId);
        }
    }

    $pdo->beginTransaction();
    try {
        if ($newStatus === 'shipped') {
            $carrier = trim((string) ($carrier ?? ''));
            if ($carrier === '') {
                $carrier = 'ZERA Kargo';
            }
            $stmt = $pdo->prepare(
                "UPDATE orders
                 SET status = 'shipped', tracking_number = ?, carrier = ?, shipped_at = NOW(), delivered_at = NULL
                 WHERE order_id = ? AND status = 'pending'"
            );
            $stmt->execute([$trackingNumber, $carrier, $orderId]);
        } elseif ($newStatus === 'delivered') {
            $stmt = $pdo->prepare(
                "UPDATE orders
                 SET status = 'delivered', delivered_at = NOW()
                 WHERE order_id = ? AND status = 'shipped'"
            );
            $stmt->execute([$orderId]);
        } elseif ($newStatus === 'cancelled') {
            $stmt = $pdo->prepare(
                "UPDATE orders SET status = 'cancelled'
                 WHERE order_id = ? AND status = 'pending'"
            );
            $stmt->execute([$orderId]);

            if ($paymentStatus === 'paid') {
                $itemsStmt = $pdo->prepare(
                    'SELECT product_id, quantity FROM order_items WHERE order_id = ? AND product_id IS NOT NULL'
                );
                $itemsStmt->execute([$orderId]);
                $restoreStmt = $pdo->prepare(
                    'UPDATE products SET stock_quantity = COALESCE(stock_quantity, 0) + ? WHERE product_id = ?'
                );
                foreach ($itemsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $qty = max(0, (int) ($row['quantity'] ?? 0));
                    $pid = (int) ($row['product_id'] ?? 0);
                    if ($qty > 0 && $pid > 0) {
                        $restoreStmt->execute([$qty, $pid]);
                    }
                }
            }
        } else {
            $pdo->rollBack();
            return ['success' => false, 'error' => 'Unsupported transition'];
        }

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            return ['success' => false, 'error' => 'Order status could not be updated'];
        }

        $pdo->commit();
        return ['success' => true, 'status' => $newStatus];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
