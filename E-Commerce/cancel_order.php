<?php
session_start();
require_once 'functions.php';

if (empty($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "error" => "Not logged in"]);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

if (!isset($_POST['order_id'])) {
    echo json_encode(["success" => false, "error" => "Order ID missing"]);
    exit;
}

$order_id = (int) $_POST['order_id'];

try {
    // Sadece pending olan sipariş iptal edilebilir
    $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE order_id = ? AND user_id = ? AND status = 'pending'");
    $stmt->execute([$order_id, $user_id]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(["success" => false, "error" => "Order cannot be cancelled"]);
        exit;
    }

    echo json_encode(["success" => true]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
