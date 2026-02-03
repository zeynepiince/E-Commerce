<?php
session_start();
require_once "db.php";

$data = json_decode(file_get_contents("php://input"), true);
$message = strtolower(trim($data["message"] ?? ""));

$reply = "I'm not sure I understood that. Can you rephrase?";

// Basit rule-based intent detection
if (str_contains($message, "hello") || str_contains($message, "hi")) {
    $reply = "Hello! 👋 How can I help you today?";
}
elseif (str_contains($message, "price")) {
    $reply = "You can find product prices on the product cards.";
}
elseif (str_contains($message, "order") || str_contains($message, "package") || str_contains($message, "shipment")) {
    // Örnek: sabit order durumu (login sonrası dinamik yapılacak)
    $order_status = "Your last order (#123) is being prepared and will be shipped tomorrow.";
    $reply = $order_status;
}
elseif (str_contains($message, "help")) {
    $reply = "Sure! I can help you with products, orders, or general questions.";
}

// LOG TO DB
$user_id = 1; // şimdilik sabit
$stmt = $pdo->prepare("
  INSERT INTO support_interactions (user_id, message, sender)
  VALUES (?, ?, 'user')
");
$stmt->execute([$user_id, $message]);

$stmt = $pdo->prepare("
  INSERT INTO support_interactions (user_id, message, sender)
  VALUES (?, ?, 'system')
");
$stmt->execute([$user_id, $reply]);

echo json_encode([
  "reply" => $reply
]);
