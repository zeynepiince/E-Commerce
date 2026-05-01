<?php
require_once 'functions.php';

$page_title = "My Wishlist – STORY";

$favorites = [];

if (!empty($_SESSION['user_id'])) {
    $user_id = (int) $_SESSION['user_id'];

    $stmt = $pdo->prepare("
        SELECT 
            p.product_id,
            p.name,
            p.price,
            p.image_url,
            p.badges,
            c.category_name AS category
        FROM user_favorites uf
        JOIN products p ON uf.product_id = p.product_id
        LEFT JOIN categories c ON p.category_id = c.category_id
        WHERE uf.user_id = ?
        ORDER BY uf.created_at DESC
    ");

    $stmt->execute([$user_id]);
    $favorites = $stmt->fetchAll();
}
?>

<?php include 'includes/header.php'; ?>

<link rel="stylesheet" href="assets/css/wishlist.css">

<div class="wishlist-page">
  <div class="wishlist-container">
    <header class="wishlist-header">
      <h1 class="wishlist-title">
        My Wishlist <span class="wishlist-title-count" id="wishlistCount"></span>
      </h1>
      <p class="wishlist-subtitle">Products you saved for later</p>
    </header>

    <div id="wishlistContainer"></div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>