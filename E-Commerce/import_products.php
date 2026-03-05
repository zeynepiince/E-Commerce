<?php
/**
 * Simple one-off importer to pull products from a public API
 * and insert/update them into the local `products` table.
 *
 * Usage (in browser):
 *   /E-Commerce/import_products.php               -> Fake Store API, default limit 20
 *   /E-Commerce/import_products.php?limit=50      -> Fake Store API, limit 50
 *   /E-Commerce/import_products.php?source=dummy  -> DummyJSON API, default limit 20
 *
 * IMPORTANT:
 * This script assumes your `products` table has (at least) columns:
 *   external_id (INT, UNIQUE)
 *   name (VARCHAR)
 *   description (TEXT, NULLable)
 *   price (DECIMAL)
 *   category (VARCHAR)
 *   image_url (TEXT or VARCHAR)
 */

require_once 'functions.php';

header('Content-Type: text/plain; charset=utf-8');

$source = $_GET['source'] ?? 'fake';
$limit  = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
if ($limit <= 0) {
    $limit = 20;
}

function fetch_from_fake_store(int $limit): array
{
    $url = "https://fakestoreapi.com/products?limit=" . $limit;
    $json = @file_get_contents($url);
    if ($json === false) {
        throw new RuntimeException("Failed to fetch from Fake Store API");
    }
    $data = json_decode($json, true);
    if (!is_array($data)) {
        throw new RuntimeException("Invalid JSON from Fake Store API");
    }
    $out = [];
    foreach ($data as $item) {
        $out[] = [
            'external_id' => (int) ($item['id'] ?? 0),
            'name'        => $item['title'] ?? 'Product',
            'description' => $item['description'] ?? null,
            'price'       => (float) ($item['price'] ?? 0),
            'category'    => $item['category'] ?? 'general',
            'image_url'   => $item['image'] ?? null,
        ];
    }
    return $out;
}

function fetch_from_dummy_json(int $limit): array
{
    $url = "https://dummyjson.com/products?limit=" . $limit;
    $json = @file_get_contents($url);
    if ($json === false) {
        throw new RuntimeException("Failed to fetch from DummyJSON API");
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded) || !isset($decoded['products']) || !is_array($decoded['products'])) {
        throw new RuntimeException("Invalid JSON from DummyJSON API");
    }
    $out = [];
    foreach ($decoded['products'] as $item) {
        $images = $item['images'] ?? [];
        $thumb  = $item['thumbnail'] ?? (is_array($images) && count($images) ? $images[0] : null);
        $out[] = [
            'external_id' => (int) ($item['id'] ?? 0),
            'name'        => $item['title'] ?? 'Product',
            'description' => $item['description'] ?? null,
            'price'       => (float) ($item['price'] ?? 0),
            'category'    => $item['category'] ?? 'general',
            'image_url'   => $thumb,
        ];
    }
    return $out;
}

try {
    if ($source === 'dummy') {
        $products = fetch_from_dummy_json($limit);
    } else {
        $products = fetch_from_fake_store($limit);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo "Error fetching API data: " . $e->getMessage() . PHP_EOL;
    exit;
}

if (!count($products)) {
    echo "No products fetched from API.\n";
    exit;
}

// IMPORTANT: requires products.external_id to be UNIQUE
// This version only uses columns that exist in your current schema:
// external_id, name, category, price, image_url
$sql = "
  INSERT INTO products (external_id, name, price, category, image_url)
  VALUES (:external_id, :name, :price, :category, :image_url)
  ON DUPLICATE KEY UPDATE
    name      = VALUES(name),
    price     = VALUES(price),
    category  = VALUES(category),
    image_url = VALUES(image_url)
";

$stmt = $pdo->prepare($sql);

$inserted = 0;
$updated  = 0;

foreach ($products as $p) {
    if (empty($p['external_id'])) {
        continue;
    }
    $stmt->execute([
        ':external_id' => $p['external_id'],
        ':name'        => $p['name'],
        ':price'       => $p['price'],
        ':category'    => $p['category'],
        ':image_url'   => $p['image_url'],
    ]);

    // crude way to guess insert vs update: check affected rows
    $affected = $stmt->rowCount();
    if ($affected === 1) {
        $inserted++;
    } elseif ($affected === 2) {
        $updated++;
    }
}

echo "Imported from source: " . ($source === 'dummy' ? 'DummyJSON' : 'Fake Store API') . PHP_EOL;
echo "Limit: {$limit}" . PHP_EOL;
echo "Inserted: {$inserted}" . PHP_EOL;
echo "Updated:  {$updated}" . PHP_EOL;

