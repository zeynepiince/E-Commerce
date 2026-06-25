<?php

require_once __DIR__ . '/bootstrap.php';

function resolve_product_id_for_order_item(PDO $pdo, array $item): ?int
{
    $rawId = $item['id'] ?? null;
    if (is_numeric($rawId)) {
        $candidate = (int) $rawId;
        if ($candidate > 0) {
            $stmt = $pdo->prepare('SELECT product_id FROM products WHERE product_id = ? LIMIT 1');
            $stmt->execute([$candidate]);
            $found = $stmt->fetchColumn();
            if ($found !== false) {
                return (int) $found;
            }
        }
    }

    $name = trim((string) ($item['name'] ?? ''));
    if ($name !== '') {
        $stmt = $pdo->prepare('SELECT product_id FROM products WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        $found = $stmt->fetchColumn();
        if ($found !== false) {
            return (int) $found;
        }
    }

    return null;
}

/**
 * @return array{total_usd: float, lines: array<int, array{product_id: ?int, qty: int, unit_price: float, name: string}>}
 */
function normalize_cart_lines(PDO $pdo, array $cart): array
{
    $lines = [];
    $total = 0.0;

    foreach ($cart as $item) {
        if (!is_array($item)) {
            continue;
        }
        $qty = max(1, (int) ($item['qty'] ?? 1));
        $unitPrice = (float) ($item['price'] ?? 0);
        $total += $unitPrice * $qty;
        $lines[] = [
            'product_id' => resolve_product_id_for_order_item($pdo, $item),
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'name' => trim((string) ($item['name'] ?? 'Product')),
        ];
    }

    return ['total_usd' => round($total, 2), 'lines' => $lines];
}

function assert_cart_stock_available(PDO $pdo, array $lines): void
{
    $stockSelect = $pdo->prepare('SELECT name, stock_quantity FROM products WHERE product_id = ? FOR UPDATE');

    foreach ($lines as $line) {
        $productId = $line['product_id'] ?? null;
        $qty = (int) ($line['qty'] ?? 1);
        if ($productId === null) {
            continue;
        }

        $stockSelect->execute([(int) $productId]);
        $row = $stockSelect->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new RuntimeException('Product not found (id=' . (int) $productId . ')');
        }
        $available = (int) ($row['stock_quantity'] ?? 0);
        if ($available < $qty) {
            throw new RuntimeException(
                'Insufficient stock for "' . ($row['name'] ?? '#' . $productId) .
                '" (requested ' . $qty . ', available ' . $available . ')'
            );
        }
    }
}

/**
 * @param array<string, mixed> $shipping
 * @return array{order_id: int, total_usd: float, conversation_id: string}
 */
function create_awaiting_payment_order(PDO $pdo, int $userId, array $cart, array $shipping): array
{
    $normalized = normalize_cart_lines($pdo, $cart);
    if ($normalized['lines'] === []) {
        throw new RuntimeException('Empty cart');
    }

    $pdo->beginTransaction();
    try {
        assert_cart_stock_available($pdo, $normalized['lines']);

        $stmt = $pdo->prepare(
            "INSERT INTO orders (user_id, total_amount, status, payment_status, payment_provider, shipping_json)
             VALUES (?, ?, 'pending', 'awaiting_payment', 'iyzico', ?)"
        );
        $stmt->execute([
            $userId,
            $normalized['total_usd'],
            json_encode($shipping, JSON_UNESCAPED_UNICODE),
        ]);
        $orderId = (int) $pdo->lastInsertId();

        $itemStmt = $pdo->prepare(
            'INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)'
        );
        foreach ($normalized['lines'] as $line) {
            $itemStmt->execute([
                $orderId,
                $line['product_id'],
                $line['qty'],
                $line['unit_price'],
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return [
        'order_id' => $orderId,
        'total_usd' => $normalized['total_usd'],
        'lines' => $normalized['lines'],
        'conversation_id' => 'zera_order_' . $orderId . '_' . bin2hex(random_bytes(4)),
    ];
}

function fulfill_order_stock(PDO $pdo, int $orderId): void
{
    $itemsStmt = $pdo->prepare(
        'SELECT product_id, quantity FROM order_items WHERE order_id = ? AND product_id IS NOT NULL'
    );
    $itemsStmt->execute([$orderId]);
    $stockSelect = $pdo->prepare('SELECT name, stock_quantity FROM products WHERE product_id = ? FOR UPDATE');
    $stockUpdate = $pdo->prepare(
        'UPDATE products SET stock_quantity = stock_quantity - ?
         WHERE product_id = ? AND stock_quantity >= ?'
    );

    $pdo->beginTransaction();
    try {
        foreach ($itemsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $qty = max(1, (int) ($row['quantity'] ?? 1));
            if ($productId <= 0) {
                continue;
            }

            $stockSelect->execute([$productId]);
            $product = $stockSelect->fetch(PDO::FETCH_ASSOC);
            if ($product === false) {
                throw new RuntimeException('Product not found during fulfillment (id=' . $productId . ')');
            }
            $available = (int) ($product['stock_quantity'] ?? 0);
            if ($available < $qty) {
                throw new RuntimeException(
                    'Insufficient stock for "' . ($product['name'] ?? '#' . $productId) .
                    '" at payment confirmation'
                );
            }

            $stockUpdate->execute([$qty, $productId, $qty]);
            if ($stockUpdate->rowCount() === 0) {
                throw new RuntimeException('Stock changed during payment confirmation');
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function mark_order_payment_paid(PDO $pdo, int $orderId): void
{
    $stmt = $pdo->prepare(
        "UPDATE orders SET payment_status = 'paid', status = 'pending'
         WHERE order_id = ? AND payment_status = 'awaiting_payment'"
    );
    $stmt->execute([$orderId]);
}

/**
 * Demo / test checkout — iyzico olmadan siparişi tamamlar.
 *
 * @param array<string, mixed> $shipping
 * @return array{order_id: int, total_usd: float, conversation_id: string}
 */
function complete_demo_checkout(PDO $pdo, int $userId, array $cart, array $shipping): array
{
    $created = create_awaiting_payment_order($pdo, $userId, $cart, $shipping);
    $orderId = (int) $created['order_id'];

    $providerStmt = $pdo->prepare("UPDATE orders SET payment_provider = 'demo' WHERE order_id = ?");
    $providerStmt->execute([$orderId]);

    fulfill_order_stock($pdo, $orderId);
    mark_order_payment_paid($pdo, $orderId);

    return $created;
}

function mark_order_payment_failed(PDO $pdo, int $orderId): void
{
    $stmt = $pdo->prepare(
        "UPDATE orders SET payment_status = 'failed'
         WHERE order_id = ? AND payment_status = 'awaiting_payment'"
    );
    $stmt->execute([$orderId]);
}

function save_payment_record(
    PDO $pdo,
    int $orderId,
    string $conversationId,
    ?string $token,
    string $status,
    float $amountTry,
    ?array $rawResponse = null
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO payments (order_id, provider, conversation_id, token, status, amount, currency, raw_response)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $orderId,
        'iyzico',
        $conversationId,
        $token,
        $status,
        $amountTry,
        'TRY',
        $rawResponse !== null ? json_encode($rawResponse, JSON_UNESCAPED_UNICODE) : null,
    ]);
}

function update_payment_by_token(PDO $pdo, string $token, string $status, ?array $rawResponse = null): void
{
    $stmt = $pdo->prepare(
        'UPDATE payments SET status = ?, raw_response = ?, updated_at = NOW() WHERE token = ?'
    );
    $stmt->execute([
        $status,
        $rawResponse !== null ? json_encode($rawResponse, JSON_UNESCAPED_UNICODE) : null,
        $token,
    ]);
}

function find_order_id_by_conversation(PDO $pdo, string $conversationId): ?int
{
    if ($conversationId === '') {
        return null;
    }
    $stmt = $pdo->prepare('SELECT order_id FROM payments WHERE conversation_id = ? ORDER BY payment_id DESC LIMIT 1');
    $stmt->execute([$conversationId]);
    $orderId = $stmt->fetchColumn();
    return $orderId !== false ? (int) $orderId : null;
}

function find_order_id_by_token(PDO $pdo, string $token): ?int
{
    if ($token === '') {
        return null;
    }
    $stmt = $pdo->prepare('SELECT order_id FROM payments WHERE token = ? ORDER BY payment_id DESC LIMIT 1');
    $stmt->execute([$token]);
    $orderId = $stmt->fetchColumn();
    return $orderId !== false ? (int) $orderId : null;
}

function resolve_order_id_from_iyzico_result(PDO $pdo, string $token, array $result): ?int
{
    $orderId = find_order_id_by_token($pdo, $token);
    if ($orderId !== null) {
        return $orderId;
    }

    $conversationId = (string) ($result['conversation_id'] ?? '');
    $orderId = find_order_id_by_conversation($pdo, $conversationId);
    if ($orderId !== null) {
        return $orderId;
    }

    if (preg_match('/zera_order_(\d+)_/i', $conversationId, $m)) {
        return (int) $m[1];
    }

    $basketId = (string) ($result['raw']['basketId'] ?? '');
    if (preg_match('/zera_basket_(\d+)/i', $basketId, $m)) {
        return (int) $m[1];
    }

    return null;
}
