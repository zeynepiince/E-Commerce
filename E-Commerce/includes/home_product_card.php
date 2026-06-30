<?php
if (!function_exists("t")) {
  require_once __DIR__ . "/../functions.php";
}
$productId = (int) ($product['product_id'] ?? (($idx ?? 0) + 1));
if ($productId <= 0 && isset($product['name'])) {
    $productId = (int) abs(crc32((string) $product['name']));
}
$stockQuantity = (int) ($product['stock_quantity'] ?? 0);
$inStock = $stockQuantity > 0;
$imageUrl = !empty($product['image_url']) ? $product['image_url'] : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff';
$productName = $product['name'] ?? t("product.card.fallback_name", "Product");
$productPrice = (float) ($product['price'] ?? 0);
$productCategory = $product['category'] ?? '';
$sizeList = get_product_sizes($product);
$badge = $badge ?? null;
$rating = $rating ?? rand(38, 50) / 10;
$reviewCount = $reviewCount ?? rand(12, 340);
?>
<div class="home-product-card">
  <?php if ($badge): ?>
    <span class="home-product-badge home-product-badge--<?= htmlspecialchars(strtolower(str_replace(' ', '-', $badge)), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') ?></span>
  <?php endif; ?>
  <button type="button" class="home-product-wishlist wishlist-btn" data-id="<?= $productId ?>" data-name="<?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8') ?>" data-image="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" data-price="<?= htmlspecialchars((string) $productPrice, ENT_QUOTES, 'UTF-8') ?>" data-stock="<?= (int) $stockQuantity ?>">♡</button>
  <a href="<?= htmlspecialchars(localized_path('product_detail.php', ['name' => $productName]), ENT_QUOTES, 'UTF-8') ?>" class="home-product-link">
    <div class="home-product-image">
      <img src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8') ?>" loading="lazy" onerror="this.src='https://images.unsplash.com/photo-1542291026-7eec264c27ff';this.onerror=null;">
    </div>
    <div class="home-product-overlay">
      <span class="home-product-quickview"><?= htmlspecialchars(t("product.card.view_details", "View Details"), ENT_QUOTES, 'UTF-8') ?></span>
    </div>
  </a>
  <div class="home-product-body" data-size-host="1">
    <a href="<?= htmlspecialchars(localized_path('product_detail.php', ['name' => $productName]), ENT_QUOTES, 'UTF-8') ?>" class="home-product-name"><?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8') ?></a>
    <div class="home-product-rating">
      <span class="home-product-stars" style="--rating:<?= $rating ?>">★★★★★</span>
      <span class="home-product-reviews"><?= number_format($rating, 1) ?> (<?= $reviewCount ?>)</span>
    </div>
    <p class="home-product-price">$<?= number_format($productPrice, 2) ?></p>
    <?php if (!empty($sizeList)): ?>
      <p class="home-product-reviews"><?= htmlspecialchars(t("product.card.size", "Size"), ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars(implode(" · ", $sizeList), ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <button type="button" class="home-product-add <?= !$inStock ? 'product-card-add--disabled' : '' ?>" <?= !$inStock ? 'disabled' : '' ?> data-id="<?= $productId ?>" data-name="<?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8') ?>" data-price="<?= htmlspecialchars((string) $productPrice, ENT_QUOTES, 'UTF-8') ?>" data-image="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>"><?= $inStock ? htmlspecialchars(t("product.card.add_to_cart", "Add to Cart"), ENT_QUOTES, 'UTF-8') : htmlspecialchars(t("product.card.out_of_stock", "Out of Stock"), ENT_QUOTES, 'UTF-8') ?></button>
  </div>
</div>
