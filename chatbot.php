<?php
session_start();
require_once "db.php";

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

$message = strtolower(trim($data["message"] ?? ""));
$cart    = $data["cart"] ?? [];

$user_id = $_SESSION['user_id'] ?? 1;

$reply = "I'm not sure I understood that. Can you rephrase?";
$intent = null;
$recommended_product_id = null;

// intents
if (str_contains($message, "hello") || str_contains($message, "hi")) {
    $intent = "greeting";
    $reply = "Hello! 👋 How can I help you today?";
}
elseif (str_contains($message, "price")) {
    $intent = "price";
    $reply = "You can find product prices on the product cards.";
}
elseif (str_contains($message, "order")) {
    $intent = "order_status";
    $reply = "Your last order is being prepared.";
}
elseif (str_contains($message, "cart")) {
    $intent = "cart_info";

    if (!empty($cart)) {
        $count = array_sum(array_column($cart, "qty"));
        $reply = "You have {$count} items in your cart.";
    } else {
        $reply = "Your cart is empty.";
    }
}
elseif (str_contains($message, "recommend")) {
    $intent = "recommendation";

    $stmt = $pdo->query("SELECT product_id, name FROM products ORDER BY RAND() LIMIT 1");
    $p = $stmt->fetch();

    if ($p) {
        $recommended_product_id = $p['product_id'];
        $reply = "I recommend: " . $p['name'];
    }
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO support_interactions 
        (user_id, message, sender, intent, recommended_product_id)
        VALUES (?, ?, ?, ?, ?)
    ");

    // user message
    $stmt->execute([
        $user_id,
        $message,
        'user',
        $intent,
        null
    ]);

    // bot message
    $stmt->execute([
        $user_id,
        $reply,
        'bot',
        $intent,
        $recommended_product_id
    ]);

    echo json_encode(["reply" => $reply]);

} catch (Exception $e) {
    echo json_encode([
        "reply" => "ERROR: " . $e->getMessage()
    ]);
}