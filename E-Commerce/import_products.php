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

/**
 * Ana kategori + ürün adı (+ açıklama) bilgisinden sub_category tahmin eder.
 * Site navigasyonundaki slug'larla aynı değerleri döner (women/dress, men/shirt, electronics/phone vs.).
 */
function infer_subcategory(?string $categoryName, string $productName, ?string $description = ''): ?string
{
    $cat = strtolower(trim((string) $categoryName));
    $hay = strtolower(trim($productName . ' ' . (string) $description));
    if ($hay === '') {
        return null;
    }

    $catAliases = [
        "women's clothing" => 'women',
        'womens clothing' => 'women',
        "men's clothing" => 'men',
        'mens clothing' => 'men',
        'jewelery' => 'jewelry',
        'jewellery' => 'jewelry',
        'fashion' => '',
        'electronic' => 'electronics',
    ];
    if (isset($catAliases[$cat])) {
        $cat = $catAliases[$cat];
    }

    $rules = [
        'women' => [
            'women-shoes'        => ['heel', 'pump', 'ballet flat', 'stiletto', 'wedge', 'women.*shoe', 'women.*sneaker'],
            'women-accessories'  => ['scarf', 'belt', 'wallet', 'handbag', 'clutch', 'sunglasses', 'tote'],
            'bags'               => ['bag', 'backpack', 'purse', 'satchel', 'duffel', 'luggage'],
            'dress'              => ['dress', 'gown', 'sundress', 'frock', 'maxi'],
            'blouse'             => ['blouse', 'tank top', 'crop top', 'camisole', 'tunic'],
            'skirts'             => ['skirt'],
        ],
        'men' => [
            'men-shoes'          => ['sneaker', 'oxford', 'derby', 'loafer', 'cleat', 'trainer', 'air jordan', 'nike air'],
            'men-accessories'    => ['tie', 'cufflink', 'belt', 'wallet', 'sunglasses', 'cap', 'hat'],
            'shirt'              => ['shirt', 'tshirt', 't-shirt', 'tee', 'polo', 'henley'],
            'pants'              => ['pant', 'jean', 'trouser', 'chino', 'short', 'jogger', 'slim fit', 'casual fit'],
            'jacket'             => ['jacket', 'coat', 'blazer', 'hoodie', 'sweatshirt', 'parka', 'bomber'],
        ],
        'electronics' => [
            'phone'              => ['phone', 'iphone', 'galaxy', 'pixel', 'smartphone', 'android'],
            'computer-tablet'    => ['laptop', 'macbook', 'tablet', 'ipad', 'notebook', 'chromebook', 'monitor', 'keyboard', 'mouse', 'ssd', 'hard drive', 'matebook', 'zenbook', 'xps', 'thinkpad', 'acer', 'asus', 'lenovo', 'dell', 'huawei', 'sandisk', 'silicon power'],
            'smart-home'         => ['echo', 'alexa', 'google home', 'nest', 'smart bulb', 'smart plug'],
            'tv'                 => ['tv', 'television', 'oled', 'qled'],
            'speakers'           => ['speaker', 'soundbar', 'subwoofer'],
            'camera'             => ['camera', 'lens', 'dslr', 'mirrorless', 'webcam'],
            'printer'            => ['printer', 'toner', 'cartridge', 'ink'],
        ],
        'home' => [
            'kitchen'            => ['kitchen', 'cookware', 'cooker', 'stove', 'oven', 'pot', 'pan', 'knife', 'utensil', 'blender', 'microwave', 'kettle', 'dish', 'spatula', 'whisk', 'chopping', 'cutting board', 'peeler', 'grater', 'tongs', 'turner', 'slicer', 'spoon', 'fork', 'plate', 'cup', 'mug', 'glass', 'tray', 'rolling pin', 'spice', 'ice cube', 'lunch box', 'squeezer', 'espresso', 'coffee maker', 'toaster'],
            'bedding'            => ['bedding', 'sheet', 'duvet', 'comforter', 'pillowcase', 'blanket', 'mattress', 'linen'],
            'decor'              => ['decor', 'vase', 'mirror', 'frame', 'candle', 'rug', 'cushion', 'pillow', 'wall art'],
            'furniture'          => ['furniture', 'chair', 'table', 'desk', 'sofa', 'couch', 'shelf', 'cabinet', 'ottoman'],
        ],
        'beauty' => [
            'perfume'            => ['perfume', 'fragrance', 'cologne', 'parfum', 'eau de'],
            'makeup'             => ['makeup', 'lipstick', 'mascara', 'foundation', 'eyeshadow', 'blush', 'concealer'],
            'hair'               => ['shampoo', 'conditioner', 'hair', 'dryer', 'straightener'],
            'skincare'           => ['skin', 'serum', 'cream', 'moistur', 'cleanser', 'toner', 'sunscreen', 'lotion'],
        ],
        'sports' => [
            'running'            => ['running', 'runner', 'jogging', 'marathon'],
            'cycling'            => ['bike', 'bicycle', 'cycle', 'cycling'],
            'fitness'            => ['fitness', 'dumbbell', 'gym', 'yoga', 'kettlebell', 'workout'],
            'outdoor'            => ['tent', 'camping', 'hiking', 'outdoor', 'trail'],
        ],
        'kids' => [
            'kids-toys'          => ['toy', 'lego', 'plush', 'doll', 'playset'],
            'kids-clothing'      => ['kid', 'toddler', 'infant', 'child', 'boys', 'girls', 'onesie'],
            'games'              => ['game', 'puzzle', 'board game', 'card game'],
            'school'             => ['school', 'notebook', 'pencil', 'backpack'],
        ],
        'toys' => [
            'puzzles'            => ['puzzle', 'jigsaw'],
            'board-games'        => ['board game', 'monopoly', 'scrabble'],
            'educational-toys'   => ['educational', 'stem', 'learning'],
            'action-figures'     => ['action figure', 'figurine', 'collectible'],
        ],
        'gadgets' => [
            'smartwatch'         => ['smartwatch', 'apple watch', 'fitness tracker', 'wearable'],
            'headphones'         => ['headphone', 'earbud', 'airpod', 'earphone', 'headset'],
            'smart-home'         => ['smart home', 'alexa', 'google home', 'nest', 'thermostat', 'smart bulb'],
            'gadgets-accessories'=> ['charger', 'cable', 'usb', 'adapter', 'power bank'],
        ],
        'books' => [
            'kids-books'         => ['children book', 'picture book', 'storybook'],
            'non-fiction'        => ['biography', 'history', 'self-help', 'essay'],
            'fiction'            => ['novel', 'fiction', 'mystery', 'thriller', 'romance', 'fantasy'],
            'education'          => ['textbook', 'workbook', 'study', 'reference'],
        ],
        'jewelry' => [
            'watches'            => ['watch', 'timepiece', 'rolex', 'longines'],
            'rings'              => ['ring', 'micropave', 'princess'],
            'necklaces'          => ['necklace', 'pendant', 'chain'],
            'bracelets'          => ['bracelet', 'bangle'],
            'earrings'           => ['earring', 'stud', 'hoop'],
        ],
        'pet' => [
            'pet-food'           => ['pet food', 'dog food', 'cat food', 'treat'],
            'pet-toys'           => ['pet toy', 'chew', 'squeaky'],
            'dog'                => ['dog', 'puppy', 'canine', 'leash', 'collar'],
            'cat'                => ['cat', 'kitten', 'feline', 'litter'],
        ],
        'auto' => [
            'car-electronics'    => ['dash cam', 'car charger', 'stereo', 'gps'],
            'car-care'           => ['wax', 'polish', 'car wash', 'detailing'],
            'car-accessories'    => ['car accessory', 'seat cover', 'mount', 'automotive'],
        ],
        'office' => [
            'stationery'         => ['pen', 'pencil', 'marker', 'notebook', 'stapler', 'paper'],
            'desk'               => ['desk', 'organizer'],
            'office-supplies'    => ['binder', 'folder', 'clip', 'tape', 'office'],
        ],
        'garden' => [
            'outdoor-plants'     => ['plant', 'seed', 'planter', 'garden'],
            'garden-tools'       => ['shovel', 'rake', 'hoe', 'pruner'],
            'outdoor-furniture'  => ['patio', 'hammock', 'deck'],
        ],
        'health' => [
            'vitamins'           => ['vitamin', 'supplement', 'omega', 'multivitamin'],
            'medical'            => ['thermometer', 'first aid', 'bandage', 'medical'],
            'wellness'           => ['wellness', 'massage', 'aromatherapy'],
        ],
        'baby' => [
            'baby-toys'          => ['rattle', 'teether', 'baby toy'],
            'baby-care'          => ['diaper', 'wipe', 'bottle', 'pacifier', 'stroller'],
            'baby-clothing'      => ['baby', 'infant', 'onesie', 'romper', 'newborn'],
        ],
        'food' => [
            'beverages'          => ['beverage', 'drink', 'juice', 'soda', 'coffee', 'tea', 'water', 'milk'],
            'snacks'             => ['snack', 'chip', 'cracker', 'bar', 'nuts', 'rice', 'egg', 'cucumber', 'pepper', 'onion', 'kiwi', 'lemon', 'mulberry', 'strawberry', 'potato', 'fruit', 'vegetable'],
            'gourmet'            => ['gourmet', 'chocolate', 'honey', 'jam', 'olive', 'protein powder'],
        ],
        'arts' => [
            'art-materials'      => ['paint', 'canvas', 'brush', 'sketch', 'easel'],
            'craft-supplies'     => ['craft', 'glue', 'felt', 'yarn', 'bead'],
            'sewing'             => ['sewing', 'thread', 'needle', 'fabric', 'button'],
        ],
    ];

    $tryMatch = function (array $catRules) use ($hay): ?string {
        foreach ($catRules as $sub => $kws) {
            foreach ($kws as $kw) {
                if ($kw === '') {
                    continue;
                }
                if (stripos($hay, $kw) !== false) {
                    return $sub;
                }
            }
        }
        return null;
    };

    if ($cat !== '' && isset($rules[$cat])) {
        $hit = $tryMatch($rules[$cat]);
        if ($hit !== null) {
            return $hit;
        }
    }

    $order = $cat !== '' && isset($rules[$cat]) ? array_diff(array_keys($rules), [$cat]) : array_keys($rules);
    foreach ($order as $parent) {
        $hit = $tryMatch($rules[$parent]);
        if ($hit !== null) {
            return $hit;
        }
    }
    return null;
}

if (!empty($_GET['backfill_subcat'])) {
    $onlyEmpty = empty($_GET['force']);
    try {
        $where = $onlyEmpty ? "WHERE p.sub_category IS NULL OR p.sub_category = ''" : "";
        $rows = $pdo->query("
            SELECT p.product_id, p.name, p.description, c.category_name AS category
            FROM products p
            LEFT JOIN categories c ON c.category_id = p.category_id
            $where
        ")->fetchAll(PDO::FETCH_ASSOC);

        $updateStmt = $pdo->prepare("UPDATE products SET sub_category = ? WHERE product_id = ?");
        $updated = 0;
        $byCat = [];
        foreach ($rows as $row) {
            $sub = infer_subcategory(
                (string) ($row['category'] ?? ''),
                (string) ($row['name'] ?? ''),
                (string) ($row['description'] ?? '')
            );
            if ($sub !== null && $sub !== '') {
                $updateStmt->execute([$sub, (int) $row['product_id']]);
                $updated++;
                $key = ($row['category'] ?? 'unknown') . ' → ' . $sub;
                $byCat[$key] = ($byCat[$key] ?? 0) + 1;
            }
        }

        echo "Sub-category backfill complete.\n";
        echo "Scanned rows: " . count($rows) . "\n";
        echo "Updated rows: {$updated}\n";
        if (!empty($byCat)) {
            echo "\nDistribution:\n";
            arsort($byCat);
            foreach ($byCat as $k => $v) {
                echo "  {$k}: {$v}\n";
            }
        }
        echo "\nHint: add ?force=1 to also overwrite existing sub_category values.\n";
    } catch (Throwable $e) {
        http_response_code(500);
        echo "Backfill failed: " . $e->getMessage() . PHP_EOL;
    }
    exit;
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

/**
 * API'den gelen kategori string'ini products.category_id'ye eşler.
 * Eşleşme yoksa NULL döner.
 */
function resolve_category_id(PDO $pdo, string $apiCategory): ?int
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $rows = $pdo->query("SELECT category_id, category_name FROM categories")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $cache[strtolower(trim((string) $r['category_name']))] = (int) $r['category_id'];
        }
    }
    $key = strtolower(trim($apiCategory));
    if ($key === '') {
        return null;
    }
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $aliases = [
        "women's clothing" => ['women', 'womens', 'womens-dresses', 'womens-shoes', 'womens-bags', 'tops', 'sunglasses'],
        "men's clothing"   => ['men', 'mens', 'mens-shirts', 'mens-shoes'],
        'electronics'      => ['electronic', 'smartphones', 'laptops', 'tablets', 'mobile-accessories', 'mens-watches', 'womens-watches'],
        'jewelery'         => ['jewelry', 'jewellery', 'womens-jewellery'],
        'home'             => ['home-decoration', 'furniture', 'kitchen-accessories', 'home-appliances', 'groceries', 'beauty', 'fragrances', 'skin-care', 'skincare'],
        'fashion'          => ['vehicle', 'motorcycle', 'sports-accessories', 'sports', 'sportswear'],
    ];
    foreach ($aliases as $dbCat => $apiKeys) {
        if (in_array($key, $apiKeys, true) && isset($cache[$dbCat])) {
            return $cache[$dbCat];
        }
    }

    foreach ($cache as $name => $id) {
        if (str_contains($key, $name) || str_contains($name, $key)) {
            return $id;
        }
    }
    return null;
}

$sql = "
  INSERT INTO products (external_id, name, description, price, category_id, sub_category, image_url, stock_quantity)
  VALUES (:external_id, :name, :description, :price, :category_id, :sub_category, :image_url, :stock_quantity)
  ON DUPLICATE KEY UPDATE
    name           = VALUES(name),
    description    = VALUES(description),
    price          = VALUES(price),
    category_id    = VALUES(category_id),
    sub_category   = COALESCE(VALUES(sub_category), products.sub_category),
    image_url      = VALUES(image_url),
    stock_quantity = COALESCE(products.stock_quantity, VALUES(stock_quantity))
";

$existsStmt = $pdo->prepare("SELECT 1 FROM products WHERE external_id = ? LIMIT 1");
$stmt = $pdo->prepare($sql);

$inserted = 0;
$updated  = 0;
$skipped  = 0;

foreach ($products as $p) {
    if ((int) ($p['external_id'] ?? 0) < 1) {
        $skipped++;
        continue;
    }

    $existsStmt->execute([$p['external_id']]);
    $existed = (bool) $existsStmt->fetchColumn();

    $categoryId  = resolve_category_id($pdo, (string) ($p['category'] ?? ''));
    $subCategory = infer_subcategory(
        (string) ($p['category'] ?? ''),
        (string) ($p['name'] ?? ''),
        (string) ($p['description'] ?? '')
    );

    $stmt->execute([
        ':external_id'    => $p['external_id'],
        ':name'           => mb_substr((string) $p['name'], 0, 120),
        ':description'    => $p['description'],
        ':price'          => $p['price'],
        ':category_id'    => $categoryId,
        ':sub_category'   => $subCategory,
        ':image_url'      => $p['image_url'],
        ':stock_quantity' => 100,
    ]);

    if ($existed) {
        $updated++;
    } else {
        $inserted++;
    }
}

echo "Imported from source: " . ($source === 'dummy' ? 'DummyJSON' : 'Fake Store API') . PHP_EOL;
echo "Limit: {$limit}" . PHP_EOL;
echo "Processed rows: " . count($products) . PHP_EOL;
echo "Inserted: {$inserted}" . PHP_EOL;
echo "Updated:  {$updated}" . PHP_EOL;
if ($skipped > 0) {
    echo "Skipped (missing external_id): {$skipped}" . PHP_EOL;
}

