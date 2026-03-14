<?php
// Genel yapılandırma ve ortak fonksiyonlar

session_start();
require_once __DIR__ . "/db.php";

/** Hepsiburada/Trendyol tarzı ürün etiketleri */
const PRODUCT_BADGES = [
  'hizli-teslimat' => 'Hızlı Teslimat',
  'cok-al-az-ode' => 'Çok Al Az Öde',
  'en-cok-favorilenen-1' => 'En Çok Favorilenenlerde 1. Ürün',
  'en-cok-favorilenen-2' => 'En Çok Favorilenenlerde 2. Ürün',
  'en-cok-satan' => 'En Çok Satan',
  '9-taksit' => 'Peşin Fiyatına 9 Taksit',
  'avantajli-urun' => 'Avantajlı Ürün',
  'son-10-gun-dusuk' => 'Son 10 Günün En Düşük Fiyatı',
  'on-sale' => 'İndirimde',
  'new' => 'Yeni',
  'trending' => 'Trend',
  'flash-deal' => 'Flash deal',
];

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
      return array_map(fn($k) => ['key' => $k, 'label' => PRODUCT_BADGES[$k] ?? $k], $dbBadges);
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

  return array_map(fn($k) => ['key' => $k, 'label' => PRODUCT_BADGES[$k] ?? $k], $badges);
}

/**
 * Öne çıkan ürünleri döndürür.
 */
function get_featured_products(PDO $pdo): array
{
    try {
        $stmt = $pdo->query(
            "SELECT `name`, `price`, `image_url`, `category`
             FROM products
             WHERE is_featured = 1
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
        "SELECT `name`, `price`, `image_url`, `category`
         FROM products
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

