<?php
require_once 'functions.php';

// Basit örnek: ?name= ile ürün detayı (tabloda id kolonu olmadığı varsayımıyla)
$productName = isset($_GET['name']) ? trim($_GET['name']) : null;
$product   = null;

if ($productName) {
    $stmt = $pdo->prepare("SELECT name, price, image_url FROM products WHERE name = ?");
    $stmt->execute([$productName]);
    $product = $stmt->fetch();
}

$page_title = $product ? ($product['name'] . " – STORY") : "Product Detail – STORY";
?>

<?php include 'includes/header.php'; ?>

<section class="products">
  <?php if ($product): ?>
    <?php $cartId = crc32($product['name']); ?>
    <div class="product-detail">
      <div class="product-detail-image" style="position:relative;">
        <button
          type="button"
          class="wishlist-btn"
          data-id="<?= (int) $cartId ?>"
          onclick="toggleFavorite(
            <?= (int) $cartId ?>,
            '<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>',
            '<?= htmlspecialchars($product['image_url'] ?: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff', ENT_QUOTES, 'UTF-8') ?>'
          )"
        >
          ♡
        </button>
        <img src="<?= htmlspecialchars($product['image_url'] ?: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff', ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="product-detail-info">
        <h3><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?></h3>
        <p class="product-detail-price">
          $<?= htmlspecialchars($product['price'], ENT_QUOTES, 'UTF-8') ?>
        </p>
        <p class="product-detail-description">
          <?= htmlspecialchars('Carefully selected for your daily style and comfort.', ENT_QUOTES, 'UTF-8') ?>
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
            <span class="meta-value">In stock</span>
          </div>
        </div>
        <button
          class="btn-full-width"
          style="max-width:260px;"
          onclick="addToCart(<?= (int) $cartId ?>,'<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>',<?= (float) $product['price'] ?>,'<?= htmlspecialchars($product['image_url'] ?: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff', ENT_QUOTES, 'UTF-8') ?>')"
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
          id: <?= (int) $cartId ?>,
          name: "<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>",
          imageUrl: "<?= htmlspecialchars($product['image_url'] ?: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff', ENT_QUOTES, 'UTF-8') ?>",
          price: <?= (float) $product['price'] ?>
        });
      }
    });
  </script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>

