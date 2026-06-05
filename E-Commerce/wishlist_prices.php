<?php

require_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'method_not_allowed']);
    exit;
}

csrf_require(true);

$payload = $_POST;
$contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
if (stripos($contentType, 'application/json') !== false) {
    $decoded = json_decode(file_get_contents('php://input') ?: '', true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}

$rawNames = $payload['names'] ?? null;
if (!is_array($rawNames)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'invalid_request']);
    exit;
}

$names = [];
foreach (array_slice($rawNames, 0, 50) as $name) {
    $name = trim((string) $name);
    if ($name === '') {
        continue;
    }
    $names[] = $name;
}

$names = array_values(array_unique($names));
$prices = [];

if ($names !== []) {
    $lookupKeys = [];
    foreach ($names as $name) {
        $lookupKeys[strtolower($name)] = $name;
    }

    $placeholders = implode(',', array_fill(0, count($lookupKeys), '?'));
    try {
        $stmt = $pdo->prepare("
            SELECT name, price
            FROM products
            WHERE LOWER(TRIM(name)) IN ($placeholders)
        ");
        $stmt->execute(array_keys($lookupKeys));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $dbKey = strtolower(trim((string) ($row['name'] ?? '')));
            if ($dbKey === '' || !isset($lookupKeys[$dbKey])) {
                continue;
            }
            $requestedName = $lookupKeys[$dbKey];
            $price = (float) ($row['price'] ?? 0);
            if ($price > 0) {
                $prices[$requestedName] = $price;
            }
        }
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'lookup_failed']);
        exit;
    }
}

echo json_encode([
    'success' => true,
    'prices' => $prices,
]);
