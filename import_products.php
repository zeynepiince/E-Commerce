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
 * This script assumes your `products` table has:
 *   external_id
 *   category_id
 *   name
 *   description
 *   price
 *   image_url
 *   stock_quantity
 *
 * and a `categories` table with:
 *   category_id
 *   category_name
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
// This version uses the normalized schema:
// categories.category_name
// products.category_id
$productSql = "
  INSERT INTO products (external_id, category_id, name, description, price, image_url, stock_quantity)
  VALUES (:external_id, :category_id, :name, :description, :price, :image_url, :stock_quantity)
  ON DUPLICATE KEY UPDATE
    category_id     = VALUES(category_id),
    name            = VALUES(name),
    description     = VALUES(description),
    price           = VALUES(price),
    image_url       = VALUES(image_url),
    stock_quantity  = VALUES(stock_quantity)
";

$productStmt = $pdo->prepare($productSql);

$categorySql = "
  INSERT INTO categories (category_name)
  VALUES (:category_name)
  ON DUPLICATE KEY UPDATE category_name = VALUES(category_name)
";

$categoryStmt = $pdo->prepare($categorySql);

$inserted = 0;
$updated  = 0;

foreach ($products as $p) {
    if (empty($p['external_id'])) {
        continue;
    }

    $categoryName = $p['category'] ?? 'general';

    // 1) kategori varsa ekle, varsa geç
    $categoryStmt->execute([
        ':category_name' => $categoryName
    ]);

    // 2) category_id al
    $catIdStmt = $pdo->prepare("
        SELECT category_id 
        FROM categories 
        WHERE category_name = ?
    ");
    $catIdStmt->execute([$categoryName]);
    $categoryId = $catIdStmt->fetchColumn();

    // 3) ürünü category_id ile ekle
    $productStmt->execute([
        ':external_id' => $p['external_id'],
        ':category_id' => $categoryId,
        ':name' => $p['name'],
        ':description' => $p['description'],
        ':price' => $p['price'],
        ':image_url' => $p['image_url'],
        ':stock_quantity' => rand(10, 100)
    ]);

    $affected = $productStmt->rowCount();
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

