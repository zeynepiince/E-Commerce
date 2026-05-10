<?php
if (!function_exists("t")) {
  require_once __DIR__ . "/../functions.php";
}

$productId = (int) ($product['product_id'] ?? (($idx ?? 0) + 1));
$imageUrl = !empty($product['image_url']) ? $product['image_url'] : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff';

$productName = $product['name'] ?? t("product.card.fallback_name", "Product");
$productPrice = (float) ($product['price'] ?? 0);
$productDesc = localized_product_description($product ?? []);
$sizeList = get_product_sizes($product ?? []);

$stockQuantity = (int) ($product['stock_quantity'] ?? 0);
$inStock = $stockQuantity > 0;

$seller = $product['seller'] ?? t("product.card.seller", "ZERA Partner");
$shipping = $product['shipping'] ?? t("product.card.shipping", "Free shipping");

$badgeList = [];
if (isset($badges) && is_array($badges)) {
  $badgeList = $badges;
} elseif (!empty($badge)) {
  $badgeList = is_array($badge) ? $badge : [$badge];
}
?>

<div class="product-card <?= !$inStock ? 'product-card--out-of-stock' : '' ?>">
  <button 
    type="button" 
    class="product-card-wishlist wishlist-btn" 
    data-id="<?= $productId ?>" 
    data-name="<?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8') ?>" 
    data-image="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" 
    data-price="<?= htmlspecialchars((string) $productPrice, ENT_QUOTES, 'UTF-8') ?>"
    data-stock="<?= (int) $stockQuantity ?>"
  >
    ♡
  </button>

  <a href="<?= htmlspecialchars(localized_path('product_detail.php', ['name' => $productName]), ENT_QUOTES, 'UTF-8') ?>" class="product-card-link">
    <?php if (!empty($badgeList)): ?>
      <div class="product-card-badges">
        <?php foreach ($badgeList as $b): ?>
          <?php
            $slug = is_array($b)
              ? ($b['key'] ?? 'default')
              : (is_string($b) ? strtolower(preg_replace('/\s+/', '-', trim($b))) : 'default');

            $label = is_array($b) ? ($b['label'] ?? $slug) : $b;
          ?>

          <span 
            class="product-card-badge product-card-badge--<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>" 
            title="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"
          >
            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
          </span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="product-card-image">
      <img 
        src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" 
        alt="<?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8') ?>" 
        loading="lazy" 
        onerror="this.src='https://images.unsplash.com/photo-1542291026-7eec264c27ff';this.onerror=null;"
      >
    </div>

    <div class="product-card-overlay">
      <span class="product-card-quickview">
        <?= htmlspecialchars(t("product.card.view_details", "View Details"), ENT_QUOTES, 'UTF-8') ?>
      </span>
    </div>
  </a>

  <div class="product-card-body" data-size-host="1">
    <a 
      href="<?= htmlspecialchars(localized_path('product_detail.php', ['name' => $productName]), ENT_QUOTES, 'UTF-8') ?>" 
      class="product-card-name"
    >
      <?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8') ?>
    </a>

    <p class="product-card-desc">
      <?= htmlspecialchars($productDesc, ENT_QUOTES, 'UTF-8') ?>
    </p>

    <p class="product-card-price">
      $<?= number_format($productPrice, 2) ?>
    </p>

    <?php if (!empty($sizeList)): ?>
      <p class="product-card-meta">
        <?= htmlspecialchars(t("product.card.size", "Size"), ENT_QUOTES, 'UTF-8') ?>:
        <?= htmlspecialchars(implode(" · ", $sizeList), ENT_QUOTES, 'UTF-8') ?>
      </p>
    <?php endif; ?>

    <p class="product-card-meta">
      <?= htmlspecialchars($seller, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($shipping, ENT_QUOTES, 'UTF-8') ?>
    </p>

    <button 
      type="button"
      class="product-card-add <?= !$inStock ? 'product-card-add--disabled' : '' ?>"
      <?= !$inStock ? 'disabled' : '' ?>
      onclick='<?= $inStock
        ? "addToCart(" .
            $productId . ", " .
            json_encode($productName) . ", " .
            $productPrice . ", " .
            json_encode($imageUrl) .
          ")"
        : "return false;"
      ?>'
    >
      <?= $inStock
        ? htmlspecialchars(t("product.card.add_to_cart", "Add to Cart"), ENT_QUOTES, 'UTF-8')
        : htmlspecialchars(t("product.card.out_of_stock", "Out of Stock"), ENT_QUOTES, 'UTF-8')
      ?>
    </button>
  </div>
</div>