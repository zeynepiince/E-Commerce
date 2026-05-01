<?php
require_once "db.php";
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "error" => "Method not allowed"]);
    exit;
}

$payload = json_decode(file_get_contents("php://input"), true);
$names = $payload["names"] ?? [];

if (!is_array($names) || empty($names)) {
    echo json_encode(["success" => true, "prices" => new stdClass()]);
    exit;
}

// Keep only non-empty strings and cap request size.
$names = array_values(array_unique(array_filter(array_map(
    static fn($n) => is_string($n) ? trim($n) : "",
    $names
), static fn($n) => $n !== "")));
$names = array_slice($names, 0, 50);

if (empty($names)) {
    echo json_encode(["success" => true, "prices" => new stdClass()]);
    exit;
}

$placeholders = implode(",", array_fill(0, count($names), "?"));
$stmt = $pdo->prepare("SELECT name, price FROM products WHERE name IN ($placeholders)");
$stmt->execute($names);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$prices = [];
foreach ($rows as $row) {
    $name = (string) ($row["name"] ?? "");
    $price = isset($row["price"]) ? (float) $row["price"] : null;
    if ($name !== "" && is_float($price)) {
        $prices[$name] = $price;
    }
}

echo json_encode([
    "success" => true,
    "prices" => $prices
]);

