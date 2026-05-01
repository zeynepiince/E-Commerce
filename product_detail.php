<?php
require_once 'functions.php';

// Basit örnek: ?name= ile ürün detayı
$productName = isset($_GET['name']) ? trim($_GET['name']) : null;
$product   = null;

if ($productName) {
    $stmt = $pdo->prepare("
    SELECT 
        p.product_id,
        p.name,
        p.price,
        p.image_url,
        p.description,
        p.stock_quantity,
        c.category_name AS category
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE p.name = ?
    ");
    $stmt->execute([$productName]);
    $dbProduct = $stmt->fetch();

    if ($dbProduct) {
        $product = [
            'product_id' => $dbProduct['product_id'],
            'name' => $dbProduct['name'],
            'price' => $dbProduct['price'],
            'image_url' => $dbProduct['image_url'],
            'description' => $dbProduct['description'],
            'stock_quantity' => $dbProduct['stock_quantity'],
            'category' => $dbProduct['category'],
            'likes' => rand(10, 100) // placeholder beğeni sayısı
        ];
    }
}

$page_title = $product ? ($product['name'] . " – STORY") : "Product Detail – STORY";
?>

<?php include 'includes/header.php'; ?>

<section class="products">
  <?php if ($product): ?>
    <?php
      $cartId = (int) $product['product_id'];
      $productImage = !empty($product['image_url'])
      ? $product['image_url']
      : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff';
    ?>
    <div class="product-detail">
      <div class="product-detail-image" style="position:relative;">
        <button
          type="button"
          class="wishlist-btn"
          data-id="<?= $cartId ?>"
          onclick="toggleFavorite(
            <?= $cartId ?>,
            <?= json_encode($product['name']) ?>,
            <?= json_encode($productImage) ?>
          )"
        >
          ♡
        </button>
        <img 
          src="<?= htmlspecialchars($productImage, ENT_QUOTES, 'UTF-8') ?>" 
          alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>"  
          style="max-width:400px; max-height:400px; object-fit:contain; display:block; margin:auto;"
        >
      </div>
      <div class="product-detail-info">
        <h3><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?></h3>
        <p class="product-detail-price">
          $<?= htmlspecialchars($product['price'], ENT_QUOTES, 'UTF-8') ?>
        </p>
        <p class="product-detail-likes">
          <?= $product['likes'] ?> people liked this
        </p>
        <p class="product-detail-description">
          <?= htmlspecialchars($product['description'] ?: 'Carefully selected for your daily style and comfort.', ENT_QUOTES, 'UTF-8') ?>
        </p>
        <div class="product-detail-meta">
          <div class="product-detail-meta-row">
            <span class="meta-label">Seller</span>
            <span class="meta-value">STORY Partner</span>
          </div>
          <div class="product-detail-meta-row">
            <span class="meta-label">Shipping</span>
            <span class="meta-value">Free shipping · 2–4 business days</span>
          </div>
          <div class="product-detail-meta-row">
            <span class="meta-label">Stock</span>
            <span class="meta-value">
              <?= ((int)($product['stock_quantity'] ?? 0) > 0) ? 'In stock' : 'Out of stock' ?>
            </span>
          </div>
        </div>
        <button
          class="btn-full-width"
          style="max-width:260px;"
          onclick="addToCart(
            <?= $cartId ?>,
            <?= json_encode($product['name']) ?>,
            <?= (float) $product['price'] ?>,
            <?= json_encode($productImage) ?>
          )"
        >
          Add to Cart
        </button>
      </div>
    </div>
  <?php else: ?>
    <h3>Product not found</h3>
    <p class="subtitle">
      The product you are looking for does not exist.
    </p>
  <?php endif; ?>
</section>

<?php if ($product): ?>
  <script>
    window.addEventListener("DOMContentLoaded", function () {
      if (typeof addRecentlyViewed === "function") {
        addRecentlyViewed({
          id: <?= $cartId ?>,
          name: <?= json_encode($product['name']) ?>,
          imageUrl: <?= json_encode($productImage) ?>,
          price: <?= (float) $product['price'] ?>
        });
      }
    });
  </script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
