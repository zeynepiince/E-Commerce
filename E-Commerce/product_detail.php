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
      p.sub_category,
      COALESCE(c.category_name, '') AS category
      FROM products p
      LEFT JOIN categories c ON c.category_id = p.category_id
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
            'sub_category' => $dbProduct['sub_category'],
            'likes' => rand(10, 100)
        ];
    }
}

$page_title = $product ? ($product['name'] . " – ZERA") : t("meta.product_detail_title", "ZERA - Product Details");
?>

<?php include 'includes/header.php'; ?>

<section class="products">
  <?php if ($product): ?>
    <?php 
      $cartId = (int) ($product['product_id'] ?? 0);
      $inStock = ((int)($product['stock_quantity'] ?? 0) > 0);
    ?>
    <div class="product-detail">
      <div class="product-detail-image" style="position:relative;">
        <button
          type="button"
          class="wishlist-btn product-detail-wishlist"
          data-id="<?= (int) $cartId ?>"
          data-name="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>"
          data-image="<?= htmlspecialchars($product['image_url'] ?: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff', ENT_QUOTES, 'UTF-8') ?>"
          data-price="<?= htmlspecialchars((string) ((float) $product['price']), ENT_QUOTES, 'UTF-8') ?>"
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
              <span class="meta-value">
                <?= $inStock ? 'In stock' : 'Out of stock' ?>
              </span>
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
          <?= !$inStock ? 'disabled' : '' ?>
          onclick="<?= $inStock ? "addToCartWithSelectedSize(
            this,
            " . (int)$cartId . ",
            " . htmlspecialchars(json_encode($product['name']), ENT_QUOTES, 'UTF-8') . ",
            " . (float)$product['price'] . ",
            " . htmlspecialchars(json_encode($product['image_url'] ?: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff'), ENT_QUOTES, 'UTF-8') . "
          )" : "return false;" ?>"
        >
           <?= $inStock ? htmlspecialchars(t("product_detail.add_to_cart", "Add to Cart"), ENT_QUOTES, 'UTF-8') : 'Out of Stock' ?>
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
<?php $recentId = (int)($cartId ?? 0); ?>
<script>
window.addEventListener("load", function () {
  const favBtn = document.querySelector(".product-detail-wishlist");

  if (favBtn) {
    const id = Number(favBtn.dataset.id);
    const name = favBtn.dataset.name || "";
    const image = favBtn.dataset.image || "";
    const price = Number(favBtn.dataset.price || 0);

    function refreshHeart() {
      if (typeof isFavorite === "function" && isFavorite(id)) {
        favBtn.classList.add("wishlist-btn--active");
        favBtn.textContent = "♥";
      } else {
        favBtn.classList.remove("wishlist-btn--active");
        favBtn.textContent = "♡";
      }
    }

    refreshHeart();

    favBtn.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();

      if (typeof toggleFavorite === "function") {
        toggleFavorite(id, name, image, price);
        setTimeout(refreshHeart, 80);
      } else {
        console.log("toggleFavorite function not found");
      }
    });
  }

  if (typeof addRecentlyViewed === "function") {
    addRecentlyViewed({
      id: <?= $recentId ?>,
      name: <?= json_encode($product['name']) ?>,
      imageUrl: <?= json_encode($product['image_url'] ?: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff') ?>,
      price: <?= (float) $product['price'] ?>,
      stockQuantity: <?= (int) ($product['stock_quantity'] ?? 0) ?>
    });
  }
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
