<?php
$cartId = isset($product['name']) ? abs(crc32($product['name'])) : (($idx ?? 0) + 1);
$productId = (int) ($cartId ?: (($idx ?? 0) + 1));
$imageUrl = !empty($product['image_url']) ? $product['image_url'] : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff';
$productName = $product['name'] ?? 'Product';
$productPrice = (float) ($product['price'] ?? 0);
$productCategory = $product['category'] ?? '';
$badge = $badge ?? null;
$rating = $rating ?? rand(38, 50) / 10;
$reviewCount = $reviewCount ?? rand(12, 340);
?>
<div class="home-product-card">
  <?php if ($badge): ?>
    <span class="home-product-badge home-product-badge--<?= htmlspecialchars(strtolower(str_replace(' ', '-', $badge)), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') ?></span>
  <?php endif; ?>
  <button type="button" class="home-product-wishlist wishlist-btn" data-id="<?= $productId ?>" onclick="toggleFavorite(<?= $productId ?>,<?= json_encode($productName) ?>,<?= json_encode($imageUrl) ?>)">♡</button>
  <a href="product_detail.php?name=<?= urlencode($productName) ?>" class="home-product-link">
    <div class="home-product-image">
      <img src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8') ?>" loading="lazy" onerror="this.src='https://images.unsplash.com/photo-1542291026-7eec264c27ff';this.onerror=null;">
    </div>
    <div class="home-product-overlay">
      <span class="home-product-quickview">View Details</span>
    </div>
  </a>
  <div class="home-product-body">
    <a href="product_detail.php?name=<?= urlencode($productName) ?>" class="home-product-name"><?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8') ?></a>
    <div class="home-product-rating">
      <span class="home-product-stars" style="--rating:<?= $rating ?>">★★★★★</span>
      <span class="home-product-reviews"><?= number_format($rating, 1) ?> (<?= $reviewCount ?>)</span>
    </div>
    <p class="home-product-price">$<?= number_format($productPrice, 2) ?></p>
    <button type="button" class="home-product-add" onclick="addToCart(<?= $productId ?>,<?= json_encode($productName) ?>,<?= $productPrice ?>,<?= json_encode($imageUrl) ?>)">Add to Cart</button>
  </div>
</div>
