<?php
require_once 'functions.php';

// Filters
$selectedCategory = $_GET['category'] ?? '';
$minPrice         = $_GET['min_price'] ?? '';
$maxPrice         = $_GET['max_price'] ?? '';

// Category list
$catStmt = $pdo->query("SELECT DISTINCT category FROM products ORDER BY category");
$categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);

// Product query with filters (tabloda id kolonu olmadığı varsayımıyla)
$sql = "SELECT name, price, image_url, category FROM products WHERE 1=1";
$params = [];

if ($selectedCategory !== '') {
    $sql .= " AND category = ?";
    $params[] = $selectedCategory;
}
if ($minPrice !== '' && is_numeric($minPrice)) {
    $sql .= " AND price >= ?";
    $params[] = (float) $minPrice;
}
if ($maxPrice !== '' && is_numeric($maxPrice)) {
    $sql .= " AND price <= ?";
    $params[] = (float) $maxPrice;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$page_title = "Products – STORY";
?>

<?php include 'includes/header.php'; ?>

<section class="products">
  <h3>All Products</h3>
  <p class="subtitle">
    Browse our full catalog and refine by category and price.
  </p>

  <form method="get" class="filter-bar">
    <div class="filter-group">
      <label for="category">Category</label>
      <select name="category" id="category">
        <option value="">All</option>
        <?php foreach ($categories as $cat): ?>
          <option
            value="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>"
            <?= $selectedCategory === $cat ? 'selected' : '' ?>
          >
            <?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="filter-group">
      <label for="min_price">Min price</label>
      <input
        type="number"
        name="min_price"
        id="min_price"
        step="0.01"
        value="<?= htmlspecialchars($minPrice, ENT_QUOTES, 'UTF-8') ?>"
      >
    </div>

    <div class="filter-group">
      <label for="max_price">Max price</label>
      <input
        type="number"
        name="max_price"
        id="max_price"
        step="0.01"
        value="<?= htmlspecialchars($maxPrice, ENT_QUOTES, 'UTF-8') ?>"
      >
    </div>

    <button type="submit" class="btn-full-width" style="max-width:180px;">Apply filters</button>
  </form>

  <div class="featured-grid" style="margin-top:24px;">
    <?php if ($products): ?>
      <?php foreach ($products as $idx => $product): ?>
        <?php $cartId = crc32($product['name']); ?>
        <div class="card featured-card">
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
          <a href="product_detail.php?name=<?= urlencode($product['name']) ?>" style="text-decoration:none;color:inherit;">
            <img src="<?= htmlspecialchars($product['image_url'] ?: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff', ENT_QUOTES, 'UTF-8') ?>">
            <h4><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?></h4>
          </a>
          <p class="price">
            $<?= htmlspecialchars($product['price'], ENT_QUOTES, 'UTF-8') ?>
          </p>
          <p style="font-size:12px;color:#6b7280;margin:4px 0 8px;">
            <?= htmlspecialchars($product['category'] ?? '', ENT_QUOTES, 'UTF-8') ?>
          </p>
          <div style="display:flex;gap:8px;">
            <a
              href="product_detail.php?name=<?= urlencode($product['name']) ?>"
              class="btn-full-width"
              style="flex:1;text-align:center;text-decoration:none;display:inline-block;"
            >
              View details
            </a>
            <button
              style="flex:1;"
              class="btn-full-width"
              onclick="addToCart(<?= (int) $cartId ?>,'<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>',<?= (float) $product['price'] ?>,'<?= htmlspecialchars($product['image_url'] ?: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff', ENT_QUOTES, 'UTF-8') ?>')"
            >
              Add to Cart
            </button>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p>No products found for these filters.</p>
    <?php endif; ?>
  </div>
</section>

<?php include 'includes/footer.php'; ?>

