<?php
require_once 'functions.php';

// Basit örnek: ?name= ile ürün detayı
$productName = isset($_GET['name']) ? trim($_GET['name']) : null;
$product   = null;

if ($productName) {
    $stmt = $pdo->prepare("
      SELECT p.name, p.price, p.image_url, COALESCE(c.category_name, '') AS category
      FROM products p
      LEFT JOIN categories c ON c.category_id = p.category_id
      WHERE p.name = ?
    ");
    $stmt->execute([$productName]);
    $dbProduct = $stmt->fetch();

    if ($dbProduct) {
        $product = [
            'name' => $dbProduct['name'],
            'price' => $dbProduct['price'],
            'image_url' => $dbProduct['image_url'],
            'likes' => rand(10, 100) // placeholder beğeni sayısı
        ];
    }
}

$page_title = $product ? ($product['name'] . " – ZERA") : t("meta.product_detail_title", "ZERA - Product Details");
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
          data-name="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>"
          data-image="<?= htmlspecialchars($product['image_url'] ?: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff', ENT_QUOTES, 'UTF-8') ?>"
          data-price="<?= htmlspecialchars((string) ((float) $product['price']), ENT_QUOTES, 'UTF-8') ?>"
          onclick="toggleFavorite(
            <?= (int) $cartId ?>,
            '<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>',
            '<?= htmlspecialchars($product['image_url'] ?: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff', ENT_QUOTES, 'UTF-8') ?>',
            <?= (float) $product['price'] ?>
          )"
        >
          ♡
        </button>
        <img 
          src="<?= htmlspecialchars($product['image_url'] ?: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff', ENT_QUOTES, 'UTF-8') ?>" 
          alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>" 
          style="max-width:400px; max-height:400px; object-fit:contain; display:block; margin:auto;"
        >
      </div>
      <div class="product-detail-info" data-size-host="1">
        <h3><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?></h3>
        <p class="product-detail-price">
          $<?= htmlspecialchars($product['price'], ENT_QUOTES, 'UTF-8') ?>
        </p>
        <p class="product-detail-likes">
          <?= $product['likes'] ?> <?= htmlspecialchars(t("product_detail.people_liked", "people liked this"), ENT_QUOTES, 'UTF-8') ?>
        </p>
        <p class="product-detail-description">
          <?= htmlspecialchars(localized_product_description($product), ENT_QUOTES, 'UTF-8') ?>
        </p>
        <div class="product-detail-meta">
          <div class="product-detail-meta-row">
            <span class="meta-label"><?= htmlspecialchars(t("product_detail.seller", "Seller"), ENT_QUOTES, 'UTF-8') ?></span>
            <span class="meta-value">ZERA Partner</span>
          </div>
          <div class="product-detail-meta-row">
            <span class="meta-label"><?= htmlspecialchars(t("product_detail.shipping", "Shipping"), ENT_QUOTES, 'UTF-8') ?></span>
            <span class="meta-value"><?= htmlspecialchars(t("product_detail.shipping_value", "Free shipping · 2–4 business days"), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <div class="product-detail-meta-row">
            <span class="meta-label"><?= htmlspecialchars(t("product_detail.stock", "Stock"), ENT_QUOTES, 'UTF-8') ?></span>
            <span class="meta-value"><?= htmlspecialchars(t("product_detail.stock_value", "In stock"), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <?php $sizeList = get_product_sizes($product); ?>
          <?php if (!empty($sizeList)): ?>
          <div class="product-detail-meta-row">
            <span class="meta-label"><?= htmlspecialchars(t("product_detail.size", "Size"), ENT_QUOTES, 'UTF-8') ?></span>
            <span class="meta-value meta-value--chips">
              <?php foreach ($sizeList as $s): ?>
                <button type="button" class="size-chip size-chip--pick" onclick="selectProductSize(this, '<?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?>')"><?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?></button>
              <?php endforeach; ?>
            </span>
          </div>
          <input type="hidden" class="product-size-selected" value="">
          <?php endif; ?>
        </div>
        <div style="max-width:260px;">
        <button
          class="btn-full-width"
          style="max-width:260px;"
          onclick="addToCartWithSelectedSize(
            this,
            <?= (int) $cartId ?>,
            '<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>',
            <?= (float) $product['price'] ?>,
            '<?= htmlspecialchars($product['image_url'] ?: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff', ENT_QUOTES, 'UTF-8') ?>'
          )"
        >
          <?= htmlspecialchars(t("product_detail.add_to_cart", "Add to Cart"), ENT_QUOTES, 'UTF-8') ?>
        </button>
        </div>
      </div>
    </div>
  <?php else: ?>
    <h3><?= htmlspecialchars(t("product_detail.not_found_title", "Product not found"), ENT_QUOTES, 'UTF-8') ?></h3>
    <p class="subtitle">
      <?= htmlspecialchars(t("product_detail.not_found_text", "The product you are looking for does not exist."), ENT_QUOTES, 'UTF-8') ?>
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
