<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once "db.php";

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['cart']) || empty($data['cart'])) {
    echo json_encode([
        "success" => false,
        "error" => "Empty cart"
    ]);
    exit;
}

$cart = $data['cart'];


// şimdilik sabit user (login yok)
$user_id = 1;

try {
    $pdo->beginTransaction();

    // total hesapla
    $total = 0;
    foreach ($cart as $item) {
        $total += $item["price"] * $item["qty"];
    }

    // orders tablosu
    $stmt = $pdo->prepare(
        "INSERT INTO orders (user_id, total_amount, status)
         VALUES (?, ?, ?)"
    );
    $stmt->execute([$user_id, $total, "pending"]);

    $order_id = $pdo->lastInsertId();

    // order_items
    $stmtItem = $pdo->prepare(
        "INSERT INTO order_items (order_id, product_id, quantity, unit_price)
         VALUES (?, ?, ?, ?)"
    );

    foreach ($cart as $item) {
        $stmtItem->execute([
            $order_id,
            $item["id"],
            $item["qty"],
            $item["price"]
        ]);
    }

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "order_id" => $order_id
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
