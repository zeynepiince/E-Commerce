<?php
/**
 * Simple one-off importer to pull products from a public API
 * and insert/update them into the local `products` table.
 *
 * Usage (local dev only — IMPORT_PRODUCTS_ENABLED=true + admin login in browser):
 *   /E-Commerce/import_products.php               -> Fake Store API, default limit 20
 *   /E-Commerce/import_products.php?limit=50      -> Fake Store API, limit 50
 *   /E-Commerce/import_products.php?source=dummy  -> DummyJSON API, default limit 20
 *   /E-Commerce/import_products.php?women=1       -> DummyJSON women's categories (dresses, tops, shoes, bags)
 *
 * Production: leave IMPORT_PRODUCTS_ENABLED unset/false (URL returns 403).
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

require_import_products_access();

header('Content-Type: text/plain; charset=utf-8');

$source = $_GET['source'] ?? 'fake';
$limit  = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
if ($limit <= 0) {
    $limit = 20;
}

/**
 * Kelime sınırı ile eşleşme — "strainer" içindeki "trainer" gibi yanlış pozitifleri önler.
 */
function classification_keyword_matches(string $keyword, string $haystack): bool
{
    $keyword = trim($keyword);
    if ($keyword === '' || $haystack === '') {
        return false;
    }
    if (str_contains($keyword, '.*')) {
        return (bool) preg_match('/' . $keyword . '/i', $haystack);
    }
    return (bool) preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $haystack);
}

/**
 * sub_category slug'ından site ana kategori slug'ı (women, men, jewelry, home, …).
 */
function sub_category_parent_slug(?string $subCategory): ?string
{
    static $map = [
        'women-shoes' => 'women', 'women-accessories' => 'women', 'bags' => 'women',
        'dress' => 'women', 'blouse' => 'women', 'skirts' => 'women',
        'men-shoes' => 'men', 'men-accessories' => 'men', 'shirt' => 'men',
        'pants' => 'men', 'jacket' => 'men',
        'phone' => 'electronics', 'computer-tablet' => 'electronics', 'smart-home' => 'electronics',
        'tv' => 'electronics', 'speakers' => 'electronics', 'camera' => 'electronics', 'printer' => 'electronics',
        'kitchen' => 'home', 'bedding' => 'home', 'decor' => 'home', 'furniture' => 'home',
        'perfume' => 'beauty', 'makeup' => 'beauty', 'hair' => 'beauty', 'skincare' => 'beauty',
        'running' => 'sports', 'cycling' => 'sports', 'fitness' => 'sports', 'outdoor' => 'sports',
        'kids-toys' => 'kids', 'kids-clothing' => 'kids', 'games' => 'kids', 'school' => 'kids',
        'puzzles' => 'toys', 'board-games' => 'toys', 'educational-toys' => 'toys', 'action-figures' => 'toys',
        'smartwatch' => 'gadgets', 'headphones' => 'gadgets', 'gadgets-accessories' => 'gadgets',
        'kids-books' => 'books', 'non-fiction' => 'books', 'fiction' => 'books', 'education' => 'books',
        'watches' => 'jewelry', 'rings' => 'jewelry', 'necklaces' => 'jewelry', 'bracelets' => 'jewelry', 'earrings' => 'jewelry',
        'pet-food' => 'pet', 'pet-toys' => 'pet', 'dog' => 'pet', 'cat' => 'pet',
        'car-electronics' => 'auto', 'car-care' => 'auto', 'car-accessories' => 'auto',
        'stationery' => 'office', 'desk' => 'office', 'office-supplies' => 'office',
        'outdoor-plants' => 'garden', 'garden-tools' => 'garden', 'outdoor-furniture' => 'garden',
        'vitamins' => 'health', 'medical' => 'health', 'wellness' => 'health',
        'baby-toys' => 'baby', 'baby-care' => 'baby', 'baby-clothing' => 'baby',
        'beverages' => 'food', 'snacks' => 'food', 'gourmet' => 'food',
        'art-materials' => 'arts', 'craft-supplies' => 'arts', 'sewing' => 'arts',
    ];
    $sub = strtolower(trim((string) $subCategory));
    return $map[$sub] ?? null;
}

/**
 * sub_category + API kategorisinden products.category_id tahmin eder.
 */
function infer_category_id(PDO $pdo, ?string $subCategory, ?string $apiCategory = null): ?int
{
    static $slugToDbName = [
        'women'       => "women's clothing",
        'men'         => "men's clothing",
        'electronics' => 'Electronics',
        'jewelry'     => 'jewelery',
        'home'        => 'Home',
        'beauty'      => 'Home',
        'sports'      => 'Fashion',
        'kids'        => 'Fashion',
        'toys'        => 'Fashion',
        'gadgets'     => 'Electronics',
        'books'       => 'Home',
        'pet'         => 'Home',
        'auto'        => 'Fashion',
        'office'      => 'Home',
        'garden'      => 'Home',
        'health'      => 'Home',
        'baby'        => 'Home',
        'food'        => 'Home',
        'arts'        => 'Home',
    ];

    $parent = sub_category_parent_slug($subCategory);
    if ($parent !== null && isset($slugToDbName[$parent])) {
        $resolved = resolve_category_id($pdo, $slugToDbName[$parent]);
        if ($resolved !== null) {
            return $resolved;
        }
    }

    return resolve_category_id($pdo, (string) ($apiCategory ?? ''));
}

/**
 * Kadın giyim sinyalleri erkek kategorisinde yanlış eşleşmeyi önler (ör. "Short Frock" → dress, men/pants değil).
 */
function infer_womens_clothing_subcategory_override(string $nameHay, string $fullHay): ?string
{
    if ($nameHay === '' && $fullHay === '') {
        return null;
    }

    foreach (['frock', 'gown', 'sundress', 'maxi dress', 'evening dress', 'cocktail dress', 'party dress'] as $signal) {
        if (classification_keyword_matches($signal, $nameHay) || classification_keyword_matches($signal, $fullHay)) {
            return 'dress';
        }
    }

    foreach (['skirt', 'corset'] as $signal) {
        if (classification_keyword_matches($signal, $nameHay) || classification_keyword_matches($signal, $fullHay)) {
            return 'skirts';
        }
    }

    $combined = trim($nameHay . ' ' . $fullHay);
    if ((classification_keyword_matches('dress', $nameHay) || classification_keyword_matches('dress', $fullHay))
        && !preg_match('/\b(dress shirt|shirt dress)\b/i', $combined)) {
        return 'dress';
    }

    if (classification_keyword_matches('blouse', $nameHay) || classification_keyword_matches('blouse', $fullHay)) {
        return 'blouse';
    }

    return null;
}

/**
 * Ana kategori + ürün adı (+ açıklama) bilgisinden sub_category tahmin eder.
 * Site navigasyonundaki slug'larla aynı değerleri döner (women/dress, men/shirt, electronics/phone vs.).
 */
function infer_subcategory(?string $categoryName, string $productName, ?string $description = ''): ?string
{
    $cat = strtolower(trim((string) $categoryName));
    $nameHay = strtolower(trim($productName));
    $fullHay = strtolower(trim($productName . ' ' . (string) $description));
    if ($nameHay === '' && $fullHay === '') {
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

    $womenOverride = infer_womens_clothing_subcategory_override($nameHay, $fullHay);
    if ($womenOverride !== null) {
        return $womenOverride;
    }

    $rules = [
        'women' => [
            'women-shoes'        => ['heel', 'pump', 'ballet flat', 'stiletto', 'wedge', 'women.*shoe', 'women.*sneaker'],
            'women-accessories'  => ['scarf', 'belt', 'wallet', 'handbag', 'clutch', 'sunglasses', 'tote'],
            'bags'               => ['bag', 'backpack', 'purse', 'satchel', 'duffel', 'luggage'],
            'dress'              => ['dress', 'gown', 'sundress', 'frock', 'maxi', 'suit'],
            'blouse'             => ['blouse', 'tank top', 'crop top', 'camisole', 'tunic'],
            'shirt'              => ['shirt', 'tshirt', 't-shirt', 'tee', 'polo', 'top'],
            'skirts'             => ['skirt', 'corset'],
        ],
        'men' => [
            'men-shoes'          => ['sneaker', 'oxford', 'derby', 'loafer', 'cleats', 'cleat', 'trainers', 'air jordan', 'nike air', 'baseball cleats', 'baseball cleat'],
            'men-accessories'    => ['tie', 'cufflink', 'belt', 'wallet', 'sunglasses', 'cap', 'hat'],
            'shirt'              => ['shirt', 'tshirt', 't-shirt', 'tee', 'polo', 'henley'],
            'pants'              => ['pant', 'jean', 'trouser', 'chino', 'shorts', 'bermuda', 'jogger', 'slim fit', 'casual fit'],
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
            'kitchen'            => ['kitchen', 'cookware', 'cooker', 'stove', 'oven', 'wok', 'strainer', 'colander', 'mesh strainer', 'pot', 'pan', 'knife', 'utensil', 'blender', 'microwave', 'kettle', 'dish', 'spatula', 'whisk', 'chopping', 'cutting board', 'peeler', 'grater', 'tongs', 'turner', 'slicer', 'spoon', 'fork', 'plate', 'cup', 'mug', 'glass', 'tray', 'rolling pin', 'spice', 'ice cube', 'lunch box', 'squeezer', 'espresso', 'coffee maker', 'toaster'],
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
            'earrings'           => ['earring', 'stud', 'hoop', 'pierced'],
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

    $tryMatch = function (array $catRules, string $hay): ?string {
        if ($hay === '') {
            return null;
        }
        foreach ($catRules as $sub => $kws) {
            $sorted = $kws;
            usort($sorted, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
            foreach ($sorted as $kw) {
                if (classification_keyword_matches($kw, $hay)) {
                    return $sub;
                }
            }
        }
        return null;
    };

    if ($cat !== '' && isset($rules[$cat])) {
        $hit = $tryMatch($rules[$cat], $nameHay);
        if ($hit !== null) {
            return $hit;
        }
        $hit = $tryMatch($rules[$cat], $fullHay);
        if ($hit !== null) {
            return $hit;
        }
    }

    // Ürün adı önce; kitap anahtar kelimeleri (history vb.) açıklamada yanlış eşleşmesin diye books en sonda.
    $crossParentOrder = [
        'jewelry', 'electronics', 'home', 'beauty', 'men', 'women', 'food', 'pet',
        'sports', 'gadgets', 'kids', 'toys', 'auto', 'office', 'garden', 'health', 'baby', 'arts', 'books',
    ];
    if ($cat !== '' && isset($rules[$cat])) {
        $crossParentOrder = array_values(array_diff($crossParentOrder, [$cat]));
    }

    foreach ($crossParentOrder as $parent) {
        if (!isset($rules[$parent])) {
            continue;
        }
        $hit = $tryMatch($rules[$parent], $nameHay);
        if ($hit !== null) {
            return $hit;
        }
    }

    foreach ($crossParentOrder as $parent) {
        if (!isset($rules[$parent])) {
            continue;
        }
        $hit = $tryMatch($rules[$parent], $fullHay);
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

        $updateStmt = $pdo->prepare("UPDATE products SET sub_category = ?, category_id = ? WHERE product_id = ?");
        $updated = 0;
        $byCat = [];
        foreach ($rows as $row) {
            $sub = infer_subcategory(
                (string) ($row['category'] ?? ''),
                (string) ($row['name'] ?? ''),
                (string) ($row['description'] ?? '')
            );
            if ($sub !== null && $sub !== '') {
                $categoryId = infer_category_id($pdo, $sub, (string) ($row['category'] ?? ''));
                $updateStmt->execute([$sub, $categoryId, (int) $row['product_id']]);
                $updated++;
                $key = ($row['category'] ?? 'unknown') . ' → ' . $sub;
                $byCat[$key] = ($byCat[$key] ?? 0) + 1;
            }
        }

        echo "Product classification backfill complete.\n";
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

/** @return array<int, string> */
function dummy_json_womens_categories(): array
{
    return ['womens-dresses', 'tops', 'womens-shoes', 'womens-bags'];
}

function normalize_dummy_json_product(array $item): array
{
    $images = $item['images'] ?? [];
    $thumb  = $item['thumbnail'] ?? (is_array($images) && count($images) ? $images[0] : null);

    return [
        'external_id' => (int) ($item['id'] ?? 0),
        'name'        => $item['title'] ?? 'Product',
        'description' => $item['description'] ?? null,
        'price'       => (float) ($item['price'] ?? 0),
        'category'    => $item['category'] ?? 'general',
        'image_url'   => $thumb,
    ];
}

function fetch_from_dummy_json_categories(array $categories): array
{
    $out = [];
    $seen = [];
    foreach ($categories as $category) {
        $category = trim((string) $category);
        if ($category === '') {
            continue;
        }
        $url = 'https://dummyjson.com/products/category/' . rawurlencode($category);
        $json = @file_get_contents($url);
        if ($json === false) {
            throw new RuntimeException("Failed to fetch DummyJSON category: {$category}");
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded) || !isset($decoded['products']) || !is_array($decoded['products'])) {
            throw new RuntimeException("Invalid JSON from DummyJSON category: {$category}");
        }
        foreach ($decoded['products'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $normalized = normalize_dummy_json_product($item);
            $externalId = (int) ($normalized['external_id'] ?? 0);
            if ($externalId < 1 || isset($seen[$externalId])) {
                continue;
            }
            $seen[$externalId] = true;
            $out[] = $normalized;
        }
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
        if (!is_array($item)) {
            continue;
        }
        $out[] = normalize_dummy_json_product($item);
    }
    return $out;
}

// CLI'de yanlışlıkla include edildiğinde API import çalışmasın (backfill veya RUN_PRODUCT_IMPORT hariç).
if (PHP_SAPI === 'cli' && empty($_GET['backfill_subcat']) && getenv('RUN_PRODUCT_IMPORT') !== '1') {
    return;
}

try {
    if (!empty($_GET['women'])) {
        $products = fetch_from_dummy_json_categories(dummy_json_womens_categories());
        echo "Import source: DummyJSON women's categories\n";
    } elseif ($source === 'dummy') {
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
        'electronics'      => ['electronic', 'smartphones', 'laptops', 'tablets', 'mobile-accessories'],
        'jewelery'         => ['jewelry', 'jewellery', 'womens-jewellery', 'mens-watches', 'womens-watches', 'watches'],
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

    $subCategory = infer_subcategory(
        (string) ($p['category'] ?? ''),
        (string) ($p['name'] ?? ''),
        (string) ($p['description'] ?? '')
    );
    $categoryId  = infer_category_id($pdo, $subCategory, (string) ($p['category'] ?? ''));

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

if (!empty($_GET['women'])) {
    echo "Imported from source: DummyJSON women's categories (" . implode(', ', dummy_json_womens_categories()) . ")" . PHP_EOL;
} else {
    echo "Imported from source: " . ($source === 'dummy' ? 'DummyJSON' : 'Fake Store API') . PHP_EOL;
    echo "Limit: {$limit}" . PHP_EOL;
}
echo "Processed rows: " . count($products) . PHP_EOL;
echo "Inserted: {$inserted}" . PHP_EOL;
echo "Updated:  {$updated}" . PHP_EOL;
if ($skipped > 0) {
    echo "Skipped (missing external_id): {$skipped}" . PHP_EOL;
}

