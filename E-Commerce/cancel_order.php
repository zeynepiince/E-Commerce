<?php
require_once 'functions.php';
header('Content-Type: application/json; charset=utf-8');

$user_id = require_login();

if (!isset($_POST['order_id'])) {
    echo json_encode(["success" => false, "error" => "Order ID missing"]);
    exit;
}

$order_id = (int) $_POST['order_id'];

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "UPDATE orders SET status = 'cancelled'
         WHERE order_id = ? AND user_id = ? AND status = 'pending'"
    );
    $stmt->execute([$order_id, $user_id]);

    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        echo json_encode(["success" => false, "error" => "Order cannot be cancelled"]);
        exit;
    }

    $itemsStmt = $pdo->prepare(
        "SELECT product_id, quantity FROM order_items
         WHERE order_id = ? AND product_id IS NOT NULL"
    );
    $itemsStmt->execute([$order_id]);
    $restoreStmt = $pdo->prepare(
        "UPDATE products SET stock_quantity = COALESCE(stock_quantity, 0) + ?
         WHERE product_id = ?"
    );
    $restored = 0;
    foreach ($itemsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $qty = max(0, (int) ($row['quantity'] ?? 0));
        $pid = (int) ($row['product_id'] ?? 0);
        if ($qty > 0 && $pid > 0) {
            $restoreStmt->execute([$qty, $pid]);
            $restored += $qty;
        }
    }

    $pdo->commit();
    echo json_encode(["success" => true, "restored_units" => $restored]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
