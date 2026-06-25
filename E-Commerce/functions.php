<?php
// Genel yapılandırma ve ortak fonksiyonlar

require_once __DIR__ . '/security/Security.php';
zera_init_session();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/i18n.php";

/**
 * @param mixed $raw Comma-separated string or list of product IDs from localStorage wishlist.
 * @return array<int, int>
 */
function parse_favorite_product_ids(mixed $raw): array
{
    if (is_array($raw)) {
        $parts = $raw;
    } else {
        $text = trim((string) $raw);
        if ($text === '') {
            return [];
        }
        $parts = preg_split('/[\s,]+/', $text) ?: [];
    }

    $ids = [];
    foreach ($parts as $part) {
        $id = (int) $part;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }

    return array_values($ids);
}

/**
 * POST gövdesini okur (JSON, urlencoded veya $_POST).
 * Paylaşımlı hosting ortamlarında Content-Type başlığı eksik/bozuk olabilir.
 *
 * @return array<string, mixed>
 */
function zera_read_post_payload(): array
{
    $payload = [];
    $raw = file_get_contents('php://input') ?: '';
    if ($raw !== '') {
        $trimmed = ltrim($raw);
        if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $payload = zera_normalize_post_payload($decoded);
            }
        } else {
            $form = [];
            parse_str($raw, $form);
            if (is_array($form) && $form !== []) {
                $payload = zera_normalize_post_payload($form);
            }
        }
    }
    if ($payload === [] && $_POST !== []) {
        $payload = zera_normalize_post_payload($_POST);
    }
    return zera_merge_request_payload_fallback($payload);
}

/**
 * Hosting POST gövdesini sildiğinde GET / $_REQUEST yedeklerini birleştirir.
 *
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function zera_merge_request_payload_fallback(array $payload): array
{
    $sources = [$_GET, $_REQUEST];
    foreach (['message', 'quick_action', 'page', 'action', 'helpful'] as $key) {
        if (($payload[$key] ?? '') !== '') {
            continue;
        }
        foreach ($sources as $source) {
            if (!is_array($source) || !isset($source[$key])) {
                continue;
            }
            $value = $source[$key];
            if (is_string($value) && $value !== '') {
                $payload[$key] = $value;
                break;
            }
            if (is_numeric($value)) {
                $payload[$key] = $value;
                break;
            }
        }
    }
    return $payload;
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function zera_normalize_post_payload(array $payload): array
{
    foreach (['cart', 'experiment_variants', 'favorite_ids', 'shipping'] as $key) {
        if (!isset($payload[$key]) || !is_string($payload[$key])) {
            continue;
        }
        $decoded = json_decode($payload[$key], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $payload[$key] = $decoded;
        }
    }
    return $payload;
}

/**
 * Giriş yapmamış kullanıcıyı reddeder.
 *   - JSON istekleri (API) için 401 + JSON döner.
 *   - Sayfa istekleri için auth.php'ye yönlendirir; geri dönüş URL'sini ?return= olarak iletir.
 *
 * Korunmak istenen her sayfa/endpoint en üstte çağırmalı.
 */
function require_login(): int
{
    if (!empty($_SESSION['user_id'])) {
        return (int) $_SESSION['user_id'];
    }

    $isJson = (
        (isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
        || (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
        || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    );

    if ($isJson) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Unauthorized', 'code' => 'login_required']);
        exit;
    }

    $return = $_SERVER['REQUEST_URI'] ?? '';
    $location = 'auth.php';
    if ($return !== '') {
        $location .= '?return=' . urlencode($return);
    }
    header('Location: ' . $location);
    exit;
}

function admin_email(): string
{
    $raw = function_exists('zera_env') ? zera_env('ADMIN_EMAIL', '') : getenv('ADMIN_EMAIL');
    return strtolower(trim((string) ($raw ?: '')));
}

function is_admin_user(): bool
{
    $adminEmail = admin_email();
    if ($adminEmail === '' || empty($_SESSION['user_id'])) {
        return false;
    }
    $sessionEmail = strtolower(trim((string) ($_SESSION['user_email'] ?? '')));
    return $sessionEmail !== '' && $sessionEmail === $adminEmail;
}

/**
 * Yalnızca ADMIN_EMAIL ile eşleşen giriş yapmış kullanıcıya izin verir.
 */
function require_admin(): int
{
    $userId = require_login();
    if (!is_admin_user()) {
        $isJson = (
            (isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
            || (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
            || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        );
        if ($isJson) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Forbidden', 'code' => 'admin_required']);
            exit;
        }
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
    return $userId;
}

function import_products_enabled(): bool
{
    return strtolower(trim((string) (getenv('IMPORT_PRODUCTS_ENABLED') ?: 'false'))) === 'true';
}

/**
 * import_products.php — varsayılan kapalı (canlı güvenliği).
 * IMPORT_PRODUCTS_ENABLED=true iken: tarayıcıda admin, CLI'da yalnızca env yeterli.
 */
function require_import_products_access(): void
{
    if (!import_products_enabled()) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Forbidden: product import is disabled.';
        exit;
    }
    if (PHP_SAPI !== 'cli') {
        require_admin();
    }
}

/**
 * Bir kaynağın belirli bir kullanıcıya ait olduğunu doğrular.
 * Eşleşmezse JSON ise 403 JSON, değilse anasayfaya yönlendirir.
 */
function require_owner(int $ownerId, int $userId): void
{
    if ($ownerId === $userId) {
        return;
    }
    $isJson = isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false;
    if ($isJson) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Forbidden']);
        exit;
    }
    header('Location: index.php');
    exit;
}

/** Hepsiburada/Trendyol tarzı ürün etiketleri */
const PRODUCT_BADGES = [
  'hizli-teslimat' => ['tr' => 'Hızlı Teslimat', 'en' => 'Fast Delivery'],
  'cok-al-az-ode' => ['tr' => 'Çok Al Az Öde', 'en' => 'Buy More, Pay Less'],
  'en-cok-favorilenen-1' => ['tr' => 'En Çok Favorilenenlerde 1. Ürün', 'en' => 'Top Favorited #1'],
  'en-cok-favorilenen-2' => ['tr' => 'En Çok Favorilenenlerde 2. Ürün', 'en' => 'Top Favorited #2'],
  'en-cok-satan' => ['tr' => 'En Çok Satan', 'en' => 'Best Seller'],
  '9-taksit' => ['tr' => 'Peşin Fiyatına 9 Taksit', 'en' => '9 Installments'],
  'avantajli-urun' => ['tr' => 'Avantajlı Ürün', 'en' => 'Special Offer'],
  'son-10-gun-dusuk' => ['tr' => 'Son 10 Günün En Düşük Fiyatı', 'en' => 'Lowest Price in Last 10 Days'],
  'on-sale' => ['tr' => 'İndirimde', 'en' => 'On Sale'],
  'new' => ['tr' => 'Yeni', 'en' => 'New'],
  'trending' => ['tr' => 'Trend', 'en' => 'Trending'],
  'flash-deal' => ['tr' => 'Flash Fırsat', 'en' => 'Flash Deal'],
];

function get_badge_label(string $key): string
{
  $lang = function_exists('get_current_lang') ? get_current_lang() : 'en';
  $entry = PRODUCT_BADGES[$key] ?? null;
  if (is_array($entry)) {
    return (string) ($entry[$lang] ?? $entry['en'] ?? $entry['tr'] ?? $key);
  }
  return is_string($entry) ? $entry : $key;
}

/**
 * Ürün için etiketleri döndürür. DB'de badges varsa kullanır, yoksa section'a göre atar.
 */
function get_product_badges(array $product, ?string $section = null, int $idx = 0): array
{
  $badges = [];
  $hash = abs(crc32($product['name'] ?? ''));

  if (!empty($product['badges'])) {
    $dbBadges = is_string($product['badges']) ? json_decode($product['badges'], true) : $product['badges'];
    if (is_array($dbBadges)) {
      return array_map(fn($k) => ['key' => $k, 'label' => get_badge_label((string) $k)], $dbBadges);
    }
  }

  if ($section === 'featured') {
    if ($idx < 2) $badges[] = 'new';
    $pool = ['hizli-teslimat', 'avantajli-urun'];
    if ($hash % 2 === 0 && $idx >= 2) $badges[] = $pool[$hash % count($pool)];
  } elseif ($section === 'best-sellers') {
    $badges[] = 'en-cok-satan';
  } elseif ($section === 'on-sale') {
    $badges[] = 'on-sale';
    if ($hash % 3 === 0) $badges[] = 'son-10-gun-dusuk';
  } elseif ($section === 'recommended') {
    if ($idx === 0) $badges[] = 'en-cok-favorilenen-1';
    elseif ($idx === 1) $badges[] = 'en-cok-favorilenen-2';
    if ($hash % 4 === 0) $badges[] = 'avantajli-urun';
  }

  if (empty($badges) || $section === 'all') {
    $pool = ['hizli-teslimat', 'cok-al-az-ode', '9-taksit', 'avantajli-urun', 'son-10-gun-dusuk', 'en-cok-satan'];
    $n = min(2, ($hash % 3) + 1);
    for ($i = 0; $i < $n && $i < count($pool); $i++) {
      $pick = $pool[($hash + $i) % count($pool)];
      if (!in_array($pick, $badges)) $badges[] = $pick;
    }
  }

  return array_map(fn($k) => ['key' => $k, 'label' => get_badge_label((string) $k)], $badges);
}

/**
 * Öne çıkan ürünleri döndürür.
 */
function get_featured_products(PDO $pdo): array
{
    try {
        $stmt = $pdo->query(
            "SELECT 
                p.product_id,
                p.name,
                p.price,
                p.image_url,
                p.description,
                p.stock_quantity,
                p.badges,
                COALESCE(c.category_name, '') AS category
             FROM products p
             LEFT JOIN categories c ON c.category_id = p.category_id
             WHERE p.is_featured = 1
             LIMIT 8"
        );

        $rows = $stmt->fetchAll();
        if (!empty($rows)) return $rows;
    } catch (PDOException $e) {}

    return get_random_products($pdo, 8);
}
/**
 * Farklı bölümler için rastgele ürünler döndürür.
 */
function get_random_products(PDO $pdo, int $limit = 4): array
{
    $stmt = $pdo->prepare(
        "SELECT 
            p.product_id,
            p.name,
            p.price,
            p.image_url,
            p.description,
            p.stock_quantity,
            p.badges,
            COALESCE(c.category_name, '') AS category
         FROM products p
         LEFT JOIN categories c ON c.category_id = p.category_id
         ORDER BY RAND()
         LIMIT ?"
    );

    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Basit bir yönlendirme yardımcı fonksiyonu.
 */
function redirect(string $path): void
{
    header("Location: {$path}");
    exit;
}

/**
 * DB'deki tutarsız kategori adlarını ("women's clothing", "Jewelery" vb.)
 * site genelinde kullanılan slug'lara ("women", "jewelry") çevirir.
 * Slug zaten temizse aynısını döner.
 */
function normalize_category_slug(?string $raw): string
{
    $v = strtolower(trim((string) $raw));
    if ($v === "") return "";
    $map = [
        "women's clothing" => "women",
        "womens clothing"  => "women",
        "women clothing"   => "women",
        "men's clothing"   => "men",
        "mens clothing"    => "men",
        "men clothing"     => "men",
        "jewelery"         => "jewelry",
        "jewellery"        => "jewelry",
        "fashion"          => "fashion",
    ];
    return $map[$v] ?? $v;
}

/**
 * Slug'tan ("women", "jewelry") DB'de gerçekten depolanmış olabilecek
 * alternatif kategori adlarını döner. SQL filter'da
 *   WHERE LOWER(category_name) IN (?, ?, ...)
 * şeklinde kullanılır.
 */
function db_category_aliases(string $slug): array
{
    $slug = strtolower(trim($slug));
    $reverse = [
        "women"   => ["women", "women's clothing", "womens clothing"],
        "men"     => ["men", "men's clothing", "mens clothing"],
        "jewelry" => ["jewelry", "jewelery", "jewellery"],
    ];
    return $reverse[$slug] ?? [$slug];
}

function localized_category_label(?string $raw): string
{
    $value = trim((string) $raw);
    if ($value === "") return $value;

    $lang = function_exists("get_current_lang") ? get_current_lang() : "en";
    // Önce slug normalizasyonu, sonra map lookup
    $normalized = normalize_category_slug($value);
    $key = strtolower(str_replace(["-", "_"], " ", $normalized));

    $mapTr = [
        "electronics" => "Elektronik",
        "women" => "Kadın",
        "men" => "Erkek",
        "fashion" => "Moda",
        "home" => "Ev",
        "beauty" => "Güzellik",
        "sports" => "Spor",
        "kids" => "Çocuk",
        "toys" => "Oyuncak",
        "gadgets" => "Gadget",
        "books" => "Kitap",
        "jewelry" => "Takı",
        "pet" => "Evcil Hayvan",
        "auto" => "Oto",
        "office" => "Ofis",
        "garden" => "Bahçe",
        "health" => "Sağlık",
        "baby" => "Bebek",
        "food" => "Gıda",
        "arts" => "Sanat",
        "phone" => "Telefon",
        "headphone" => "Kulaklık",
        "wireless" => "Kablosuz",
        "running" => "Koşu",
        "shoe" => "Ayakkabı",
        "sneaker" => "Sneaker",
        "dress" => "Elbise",
        "blouse" => "Bluz",
        "skirts" => "Etek",
        "women accessories" => "Kadın Aksesuarları",
        "women shoes" => "Kadın Ayakkabıları",
        "bags" => "Çanta",
        "shirt" => "Gömlek",
        "pants" => "Pantolon",
        "jacket" => "Ceket",
        "men shoes" => "Erkek Ayakkabıları",
        "men accessories" => "Erkek Aksesuarları",
        "computer tablet" => "Bilgisayar ve Tablet",
        "printer" => "Yazıcı",
        "tv" => "Televizyon",
        "speakers" => "Hoparlör",
        "camera" => "Kamera",
        "furniture" => "Mobilya",
        "decor" => "Dekor",
        "kitchen" => "Mutfak",
        "bedding" => "Yatak Odası",
        "skincare" => "Cilt Bakımı",
        "makeup" => "Makyaj",
        "hair" => "Saç Bakımı",
        "perfume" => "Parfüm",
        "fitness" => "Fitness",
        "outdoor" => "Outdoor",
        "cycling" => "Bisiklet",
        "kids clothing" => "Çocuk Giyim",
        "kids toys" => "Çocuk Oyuncakları",
        "games" => "Oyun",
        "school" => "Okul",
        "action figures" => "Aksiyon Figürleri",
        "puzzles" => "Yapboz",
        "board games" => "Kutu Oyunları",
        "educational toys" => "Eğitici Oyuncaklar",
        "smartwatch" => "Akıllı Saat",
        "headphones" => "Kulaklık",
        "smart home" => "Akıllı Ev",
        "gadgets accessories" => "Gadget Aksesuarları",
        "fiction" => "Kurgu",
        "non fiction" => "Kurgu Dışı",
        "kids books" => "Çocuk Kitapları",
        "education" => "Eğitim",
        "rings" => "Yüzük",
        "necklaces" => "Kolye",
        "bracelets" => "Bileklik",
        "earrings" => "Küpe",
        "watches" => "Saat",
        "dog" => "Köpek",
        "cat" => "Kedi",
        "pet food" => "Evcil Hayvan Maması",
        "pet toys" => "Evcil Hayvan Oyuncakları",
        "car accessories" => "Araba Aksesuarları",
        "car care" => "Araba Bakım",
        "car electronics" => "Araba Elektroniği",
        "stationery" => "Kırtasiye",
        "desk" => "Masa",
        "office supplies" => "Ofis Malzemeleri",
        "outdoor plants" => "Dış Mekan Bitkileri",
        "garden tools" => "Bahçe Aletleri",
        "outdoor furniture" => "Dış Mekan Mobilyaları",
        "vitamins" => "Vitaminler",
        "wellness" => "Wellness",
        "medical" => "Medikal",
        "baby clothing" => "Bebek Giyim",
        "baby care" => "Bebek Bakımı",
        "baby toys" => "Bebek Oyuncakları",
        "snacks" => "Atıştırmalık",
        "beverages" => "İçecek",
        "gourmet" => "Gurme",
        "craft supplies" => "Hobi Malzemeleri",
        "art materials" => "Sanat Malzemeleri",
        "sewing" => "Dikiş",
    ];

    if ($lang === "tr" && isset($mapTr[$key])) {
        return $mapTr[$key];
    }
    return ucwords(str_replace(["-", "_"], " ", $value));
}

function localized_product_description(array $product): string
{
    $lang = function_exists("get_current_lang") ? get_current_lang() : "en";
    $name = trim((string) ($product["name"] ?? ""));
    $category = localized_category_label((string) ($product["category"] ?? ""));
    $dbDesc = trim((string) ($product["description"] ?? ""));

    // If DB description matches active language reasonably, prefer it.
    if ($dbDesc !== "") {
        $hasTurkishChars = preg_match('/[çğıöşüÇĞİÖŞÜ]/u', $dbDesc) === 1;
        if (($lang === "tr" && $hasTurkishChars) || ($lang === "en" && !$hasTurkishChars)) {
            return $dbDesc;
        }
    }

    if ($lang === "tr") {
        if ($category !== "") {
            return "{$category} kategorisinde öne çıkan bu ürün, günlük kullanım için dengeli kalite ve konfor sunar.";
        }
        if ($name !== "") {
            return "{$name} modeli, şık tasarımı ve pratik kullanımıyla günlük ihtiyaçlar için ideal bir seçimdir.";
        }
        return "Bu ürün, günlük ihtiyaçlar için kalite, konfor ve kullanım kolaylığını bir arada sunar.";
    }

    if ($category !== "") {
        return "A standout {$category} item offering balanced quality, comfort, and everyday usability.";
    }
    if ($name !== "") {
        return "{$name} is a practical daily-use option with a clean design and reliable comfort.";
    }
    return "This product combines quality, comfort, and practical everyday usability.";
}

function get_product_sizes(array $product): array
{
    $raw = trim((string) ($product["sizes"] ?? ""));
    if ($raw === "") {
        return [];
    }

    $parts = preg_split('/[,|\/]+/', $raw) ?: [];
    $sizes = array_values(array_filter(
        array_map(static fn($v) => trim((string) $v), $parts),
        static fn($v) => $v !== ""
    ));

    return $sizes === [] ? [] : array_slice($sizes, 0, 12);
}

