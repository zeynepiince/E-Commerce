<?php
// Genel yapılandırma ve ortak fonksiyonlar

session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/i18n.php";

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

function localized_category_label(?string $raw): string
{
    $value = trim((string) $raw);
    if ($value === "") return $value;

    $lang = function_exists("get_current_lang") ? get_current_lang() : "en";
    $key = strtolower(str_replace(["-", "_"], " ", $value));

    $mapTr = [
        "electronics" => "Elektronik",
        "women" => "Kadın",
        "men" => "Erkek",
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
    if ($raw !== "") {
        $parts = preg_split('/[,|\/]+/', $raw) ?: [];
        $sizes = array_values(array_filter(array_map(static fn($v) => trim((string) $v), $parts), static fn($v) => $v !== ""));
        if (!empty($sizes)) return array_slice($sizes, 0, 6);
    }

    $nameRaw = (string) ($product["name"] ?? "");
    $categoryRaw = (string) ($product["category"] ?? "");
    $name = function_exists("mb_strtolower") ? mb_strtolower($nameRaw, "UTF-8") : strtolower($nameRaw);
    $category = function_exists("mb_strtolower") ? mb_strtolower($categoryRaw, "UTF-8") : strtolower($categoryRaw);
    $ctx = $name . " " . $category;

    if (preg_match('/\b(shoe|sneaker|ayakkabı|ayakkabi|boots?|sandals?|running)\b/u', $ctx)) {
        return ["39", "40", "41", "42", "43"];
    }
    if (preg_match('/\b(women|kadın|kadin|dress|blouse|skirt|women shoes)\b/u', $ctx)) {
        return ["XS", "S", "M", "L", "XL"];
    }
    if (preg_match('/\b(men|erkek|shirt|pants|jacket|men shoes)\b/u', $ctx)) {
        return ["S", "M", "L", "XL", "XXL"];
    }
    if (preg_match('/\b(kids|çocuk|cocuk|baby|bebek)\b/u', $ctx)) {
        return ["2-3Y", "4-5Y", "6-7Y", "8-9Y"];
    }
    return [];
}

