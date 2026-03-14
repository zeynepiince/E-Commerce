<?php
require_once 'functions.php';

$selectedCategory = $_GET['category'] ?? '';
$selectedSubCategory = $_GET['sub_category'] ?? '';
$minPrice = $_GET['min_price'] ?? '';
$maxPrice = $_GET['max_price'] ?? '';
$sort = $_GET['sort'] ?? 'popular';
$searchQuery = $_GET['q'] ?? '';

// Categories
$categories = [];
try {
    $catStmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $categories = [];
}

// Base query
$sql = "SELECT name, price, image_url, category, sub_category FROM products WHERE 1=1";
$params = [];

if ($selectedCategory !== '') {
    $sql .= " AND category = ?";
    $params[] = $selectedCategory;
}

if ($selectedSubCategory !== '') {
    $sql .= " AND sub_category = ?";
    $params[] = $selectedSubCategory;
}

if ($minPrice !== '' && is_numeric($minPrice)) {
    $sql .= " AND price >= ?";
    $params[] = (float) $minPrice;
}
if ($maxPrice !== '' && is_numeric($maxPrice)) {
    $sql .= " AND price <= ?";
    $params[] = (float) $maxPrice;
}
if ($searchQuery !== '') {
    $sql .= " AND (name LIKE ? OR category LIKE ? OR sub_category LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

switch ($sort) {
    case 'price-asc':
        $sql .= " ORDER BY price ASC";
        break;
    case 'price-desc':
        $sql .= " ORDER BY price DESC";
        break;
    case 'name':
        $sql .= " ORDER BY name ASC";
        break;
    default:
        $sql .= " ORDER BY RAND()";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$allProducts = $stmt->fetchAll();

// Section products
$bestSellers = get_random_products($pdo, 4);
$onSale = get_random_products($pdo, 4);
$recommended = get_random_products($pdo, 4);

$page_title = "Products – STORY";
?>
<?php include 'includes/header.php'; ?>

<link rel="stylesheet" href="assets/css/products.css">

<div class="products-page-wrapper">
<div class="products-page">
  <header class="products-header">
    <h1 class="products-title">
      <?= $selectedSubCategory ? htmlspecialchars($selectedSubCategory, ENT_QUOTES, 'UTF-8') : ($selectedCategory ? htmlspecialchars($selectedCategory, ENT_QUOTES, 'UTF-8') : 'Our Products') ?>
    </h1>
    <p class="products-subtitle">
      <?php if ($selectedSubCategory): ?>
        Browse <strong><?= htmlspecialchars($selectedSubCategory, ENT_QUOTES, 'UTF-8') ?></strong> in <strong><?= htmlspecialchars($selectedCategory, ENT_QUOTES, 'UTF-8') ?></strong>.
      <?php elseif ($selectedCategory): ?>
        Browse <strong><?= htmlspecialchars($selectedCategory, ENT_QUOTES, 'UTF-8') ?></strong> from our collection.
      <?php else: ?>
        Discover our <strong>curated</strong> collection of fashion, tech, and lifestyle essentials.
      <?php endif; ?>
    </p>
  </header>

  <!-- Filters -->
  <form method="get" class="products-toolbar">
    <div class="products-filters">
      <div class="products-filter-group">
        <label for="category">Category</label>
        <select name="category" id="category" class="products-select" onchange="this.form.submit()">
          <option value="">All categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedCategory === $cat ? 'selected' : '' ?>>
              <?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="products-filter-group">
        <label for="sub_category">Sub-category</label>
        <input type="text" name="sub_category" id="sub_category" class="products-input" placeholder="e.g. Speakers" value="<?= htmlspecialchars($selectedSubCategory, ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="products-filter-group">
        <label for="min_price">Min $</label>
        <input type="number" name="min_price" id="min_price" class="products-input" step="0.01" min="0" placeholder="0" value="<?= htmlspecialchars($minPrice, ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="products-filter-group">
        <label for="max_price">Max $</label>
        <input type="number" name="max_price" id="max_price" class="products-input" step="0.01" min="0" placeholder="999" value="<?= htmlspecialchars($maxPrice, ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="products-filter-group">
        <label for="sort">Sort by</label>
        <select name="sort" id="sort" class="products-select">
          <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Popularity</option>
          <option value="price-asc" <?= $sort === 'price-asc' ? 'selected' : '' ?>>Price: Low → High</option>
          <option value="price-desc" <?= $sort === 'price-desc' ? 'selected' : '' ?>>Price: High → Low</option>
          <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name A–Z</option>
        </select>
      </div>

      <button type="submit" class="products-filter-btn">Apply</button>
    </div>
  </form>

  <!-- Products Grid -->
  <section class="products-section">
    <h2 class="products-section-title">All Products</h2>
    <?php if ($allProducts): ?>
      <div class="products-grid" id="productsGrid">
        <?php foreach ($allProducts as $idx => $product): ?>
          <?php $badges = get_product_badges($product, 'all', $idx); ?>
          <?php include 'includes/product_card.php'; ?>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="products-empty">
        <p>No products match your filters.</p>
        <a href="products.php" class="products-empty-link">Clear filters</a>
      </div>
    <?php endif; ?>
  </section>
</div>
</div>

<script src="assets/js/products.js"></script>
<?php include 'includes/footer.php'; ?>
