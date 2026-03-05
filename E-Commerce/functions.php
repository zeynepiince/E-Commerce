<?php
// Genel yapılandırma ve ortak fonksiyonlar

session_start();
require_once __DIR__ . "/db.php";

/**
 * Öne çıkan ürünleri döndürür.
 */
function get_featured_products(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT `name`, `price`, `image_url`
         FROM products
         WHERE is_featured = 1"
    );

    return $stmt->fetchAll();
}

/**
 * Farklı bölümler için rastgele ürünler döndürür.
 */
function get_random_products(PDO $pdo, int $limit = 4): array
{
    $stmt = $pdo->prepare(
        "SELECT `name`, `price`, `image_url`
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

