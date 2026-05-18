<?php
require_once 'functions.php';

$selectedCategory = $_GET['category'] ?? '';
$selectedSubCategory = $_GET['sub_category'] ?? '';
$minPrice = $_GET['min_price'] ?? '';
$maxPrice = $_GET['max_price'] ?? '';
$sort = $_GET['sort'] ?? 'popular';
$searchQuery = $_GET['q'] ?? '';
$seasonal = strtolower(trim((string) ($_GET['seasonal'] ?? '')));

// Categories
$categories = [];
try {
    $catStmt = $pdo->query("
        SELECT category_name 
        FROM categories 
        ORDER BY category_name
    ");
    $categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $categories = [];
}

// Base query
$sql = "
    SELECT 
        p.product_id,
        p.external_id,
        p.name,
        p.price,
        p.image_url,
        p.is_featured,
        p.badges,
        p.description,
        p.stock_quantity,
        p.sub_category,
        c.category_name AS category
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE 1=1
";
$params = [];

if ($selectedCategory !== '') {
    $sql .= " AND LOWER(c.category_name) = LOWER(?)";
    $params[] = $selectedCategory;
}
if ($selectedSubCategory !== '') {
    $sql .= " AND LOWER(p.sub_category) = LOWER(?)";
    $params[] = $selectedSubCategory;
}

if ($minPrice !== '' && is_numeric($minPrice)) {
    $sql .= " AND p.price >= ?";
    $params[] = (float) $minPrice;
}
if ($maxPrice !== '' && is_numeric($maxPrice)) {
    $sql .= " AND p.price <= ?";
    $params[] = (float) $maxPrice;
}

if ($searchQuery !== '') {
    $tokens = preg_split('/\s+/u', trim($searchQuery)) ?: [];
    $tokens = array_values(array_filter(array_map('trim', $tokens), fn($t) => $t !== ''));
    if (!empty($tokens)) {
        $orParts = [];
        foreach (array_slice($tokens, 0, 6) as $t) {
            $orParts[] = "p.name LIKE ?";
            $orParts[] = "c.category_name LIKE ?";
            $orParts[] = "p.sub_category LIKE ?";
            $orParts[] = "p.description LIKE ?";

            $like = "%" . $t . "%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        $sql .= " AND (" . implode(" OR ", $orParts) . ")";
    }
}

if ($seasonal !== '') {
    switch ($seasonal) {
        case 'spring':
            $sql .= " AND (
                LOWER(c.category_name) IN ('women', 'men', 'beauty')
                OR LOWER(p.name) LIKE '%spring%'
                OR LOWER(p.name) LIKE '%dress%'
                OR LOWER(p.name) LIKE '%blouse%'
                OR LOWER(p.name) LIKE '%shirt%'
                OR LOWER(p.name) LIKE '%skirt%'
                OR LOWER(p.name) LIKE '%jacket%'
                OR LOWER(p.name) LIKE '%bag%'
                OR LOWER(p.name) LIKE '%accessor%'
            )";
            break;
        case 'tech':
            $sql .= " AND (
                LOWER(c.category_name) = 'electronics'
                OR LOWER(p.name) LIKE '%laptop%'
                OR LOWER(p.name) LIKE '%tablet%'
                OR LOWER(p.name) LIKE '%monitor%'
                OR LOWER(p.name) LIKE '%keyboard%'
                OR LOWER(p.name) LIKE '%mouse%'
                OR LOWER(p.name) LIKE '%headphone%'
                OR LOWER(p.name) LIKE '%earbud%'
                OR LOWER(p.name) LIKE '%smartwatch%'
                OR LOWER(p.name) LIKE '%speaker%'
                OR LOWER(p.name) LIKE '%camera%'
            )";
            break;
        case 'travel':
            $sql .= " AND (
                LOWER(p.name) LIKE '%travel%'
                OR LOWER(p.name) LIKE '%trip%'
                OR LOWER(p.name) LIKE '%suitcase%'
                OR LOWER(p.name) LIKE '%luggage%'
                OR LOWER(p.name) LIKE '%bag%'
                OR LOWER(p.name) LIKE '%backpack%'
                OR LOWER(p.name) LIKE '%shoe%'
                OR LOWER(p.name) LIKE '%sneaker%'
                OR LOWER(p.name) LIKE '%sandals%'
                OR LOWER(p.name) LIKE '%duffel%'
            )";
            break;
    }
}

switch ($sort) {
    case 'price-asc':
        $sql .= " ORDER BY p.price ASC";
        break;
    case 'price-desc':
        $sql .= " ORDER BY p.price DESC";
        break;
    case 'name':
        $sql .= " ORDER BY p.name ASC";
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

$page_title = t("meta.products_title", "ZERA - Products");
?>
<?php include 'includes/header.php'; ?>

<link rel="stylesheet" href="assets/css/products.css">

<div class="products-page-wrapper">
<div class="products-page">
  <header class="products-header">
    <h1 class="products-title">
      <?php if ($selectedSubCategory): ?>
        <?= htmlspecialchars(localized_category_label($selectedSubCategory), ENT_QUOTES, 'UTF-8') ?>
      <?php elseif ($selectedCategory): ?>
        <?= htmlspecialchars(localized_category_label($selectedCategory), ENT_QUOTES, 'UTF-8') ?>
      <?php else: ?>
        <?= htmlspecialchars(t("products.title", "Our Products"), ENT_QUOTES, 'UTF-8') ?>
      <?php endif; ?>
    </h1>
    <p class="products-subtitle">
      <?php if ($selectedCategory || $selectedSubCategory): ?>
         <?php $currentLabel = $selectedSubCategory ?: $selectedCategory; ?>

         Explore our <strong> <?= htmlspecialchars(localized_category_label($currentLabel), ENT_QUOTES, 'UTF-8') ?> </strong> collection.
        
        <?php else: ?>
          <?= htmlspecialchars(t("products.subtitle", "Discover our"), ENT_QUOTES, 'UTF-8') ?>
          <strong> <?= htmlspecialchars(t("products.subtitle_emphasis", "curated"), ENT_QUOTES, 'UTF-8') ?> </strong>
          <?= htmlspecialchars(t("products.subtitle_suffix", "collection of fashion, tech, and lifestyle essentials."), ENT_QUOTES, 'UTF-8') ?>
      <?php endif; ?>
    </p>
  </header>

  <!-- Filters -->
  <form method="get" class="products-toolbar">
    <div class="products-filters">
      <div class="products-filter-group">
        <label for="category"><?= htmlspecialchars(t("products.filter.category", "Category"), ENT_QUOTES, 'UTF-8') ?></label>
        <select name="category" id="category" class="products-select" onchange="this.form.submit()">
          <option value=""><?= htmlspecialchars(t("products.filter.all_categories", "All categories"), ENT_QUOTES, 'UTF-8') ?></option>
          <?php foreach ($categories as $cat): ?>
            <option 
              value="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>" 
              <?= strtolower($selectedCategory) === strtolower($cat) ? 'selected' : '' ?>
            >
              <?= htmlspecialchars(localized_category_label($cat), ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="products-filter-group">
        <label for="min_price"><?= htmlspecialchars(t("products.filter.min_price", "Min $"), ENT_QUOTES, 'UTF-8') ?></label>
        <input type="number" name="min_price" id="min_price" class="products-input" step="0.01" min="0" placeholder="0" value="<?= htmlspecialchars($minPrice, ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="products-filter-group">
        <label for="max_price"><?= htmlspecialchars(t("products.filter.max_price", "Max $"), ENT_QUOTES, 'UTF-8') ?></label>
        <input type="number" name="max_price" id="max_price" class="products-input" step="0.01" min="0" placeholder="999" value="<?= htmlspecialchars($maxPrice, ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="products-filter-group">
        <label for="sort"><?= htmlspecialchars(t("products.filter.sort_by", "Sort by"), ENT_QUOTES, 'UTF-8') ?></label>
        <select name="sort" id="sort" class="products-select">
          <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>><?= htmlspecialchars(t("products.sort.popularity", "Popularity"), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="price-asc" <?= $sort === 'price-asc' ? 'selected' : '' ?>><?= htmlspecialchars(t("products.sort.price_asc", "Price: Low -> High"), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="price-desc" <?= $sort === 'price-desc' ? 'selected' : '' ?>><?= htmlspecialchars(t("products.sort.price_desc", "Price: High -> Low"), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>><?= htmlspecialchars(t("products.sort.name_az", "Name A-Z"), ENT_QUOTES, 'UTF-8') ?></option>
        </select>
      </div>

      <button type="submit" class="products-filter-btn"><?= htmlspecialchars(t("products.filter.apply", "Apply"), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
  </form>

  <!-- Products Grid -->
  <section class="products-section">
    <h2 class="products-section-title"><?= htmlspecialchars(t("products.section.all_products", "All Products"), ENT_QUOTES, 'UTF-8') ?></h2>
    <?php if ($allProducts): ?>
      <div class="products-grid" id="productsGrid">
        <?php foreach ($allProducts as $idx => $product): ?>
          <?php $badges = get_product_badges($product, 'all', $idx); ?>
          <?php include 'includes/product_card.php'; ?>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="products-empty">
        <p><?= htmlspecialchars(t("products.empty", "No products match your filters."), ENT_QUOTES, 'UTF-8') ?></p>
        <a href="<?= htmlspecialchars(localized_path('products.php'), ENT_QUOTES, 'UTF-8') ?>" class="products-empty-link"><?= htmlspecialchars(t("products.clear_filters", "Clear filters"), ENT_QUOTES, 'UTF-8') ?></a>
      </div>
    <?php endif; ?>
  </section>
</div>
</div>

<script src="assets/js/products.js"></script>
<?php include 'includes/footer.php'; ?>