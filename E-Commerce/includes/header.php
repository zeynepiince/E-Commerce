<?php
if (!function_exists("t")) {
    require_once __DIR__ . "/../functions.php";
}
$currentLang = get_current_lang();
$pageFile = current_page_file();
$metaPrefix = match ($pageFile) {
    "index.php" => "home",
    "products.php" => "products",
    "checkout.php" => "checkout",
    "orders.php" => "orders",
    "wishlist.php" => "wishlist",
    "product_detail.php" => "product_detail",
    default => "default",
};
if (!isset($page_title)) {
    $page_title = t("meta.{$metaPrefix}_title", t("meta.default_title", "ZERA - Smart Online Store"));
}
$metaDescription = t("meta.{$metaPrefix}_description", t("meta.default_description", "Discover products and shop smarter with ZERA."));
$currentBasePath = site_base_path();
$canonicalUrl = localized_path($pageFile, $_GET, $currentLang);
$altEn = localized_path($pageFile, $_GET, "en");
$altTr = localized_path($pageFile, $_GET, "tr");
$switchLang = $currentLang === "en" ? "tr" : "en";
$switchLangUrl = localized_path($pageFile, $_GET, $switchLang);

// Simple user info placeholder (to be filled with real auth later)
$loggedInName = $_SESSION['user_name'] ?? null;
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang, ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="UTF-8" />
  <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') ?>" />
  <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>" />
  <link rel="alternate" hreflang="en" href="<?= htmlspecialchars($altEn, ENT_QUOTES, 'UTF-8') ?>" />
  <link rel="alternate" hreflang="tr" href="<?= htmlspecialchars($altTr, ENT_QUOTES, 'UTF-8') ?>" />
  <link rel="alternate" hreflang="x-default" href="<?= htmlspecialchars($altEn, ENT_QUOTES, 'UTF-8') ?>" />

  <link rel="stylesheet" href="assets/css/style.css?v=<?= urlencode((string) @filemtime(__DIR__ . '/../assets/css/style.css')) ?>">
  <link rel="stylesheet" href="assets/css/navbar.css?v=<?= urlencode((string) @filemtime(__DIR__ . '/../assets/css/navbar.css')) ?>">
  <link rel="stylesheet" href="assets/css/auth-modal.css?v=<?= urlencode((string) @filemtime(__DIR__ . '/../assets/css/auth-modal.css')) ?>">
  <?php if (isset($is_homepage) && $is_homepage): ?>
  <link rel="stylesheet" href="assets/css/homepage.css?v=<?= urlencode((string) @filemtime(__DIR__ . '/../assets/css/homepage.css')) ?>">
  <link rel="stylesheet" href="assets/css/products.css?v=<?= urlencode((string) @filemtime(__DIR__ . '/../assets/css/products.css')) ?>">
  <?php endif; ?>
  <?php if (isset($is_checkout) && $is_checkout): ?>
  <link rel="stylesheet" href="assets/css/checkout.css?v=<?= urlencode((string) @filemtime(__DIR__ . '/../assets/css/checkout.css')) ?>">
  <?php endif; ?>
</head>
<body>

<header class="elegant-header">
  <button type="button" class="nav-hamburger" onclick="toggleNavMenu()" aria-label="<?= htmlspecialchars(t("nav.menu", "Menu"), ENT_QUOTES, 'UTF-8') ?>">☰</button>
  <div class="header-left">
    <a href="<?= htmlspecialchars(localized_path('index.php'), ENT_QUOTES, 'UTF-8') ?>" class="logo">ZERA</a>

    <nav class="nav-categories" id="navCategories">
      <a href="<?= htmlspecialchars(localized_path('index.php'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t("nav.home", "Home"), ENT_QUOTES, 'UTF-8') ?></a>
      <a href="<?= htmlspecialchars(localized_path('products.php'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t("nav.products", "Products"), ENT_QUOTES, 'UTF-8') ?></a>
      <a href="<?= htmlspecialchars(localized_path('orders.php'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t("nav.orders", "Orders"), ENT_QUOTES, 'UTF-8') ?></a>
      <a href="<?= htmlspecialchars(localized_path('wishlist.php'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t("nav.favorites", "Favorites"), ENT_QUOTES, 'UTF-8') ?></a>
    </nav>

    <form action="<?= htmlspecialchars(localized_path('products.php'), ENT_QUOTES, 'UTF-8') ?>" method="get" class="nav-search-form">
      <input type="text" name="q" class="nav-search" placeholder="<?= htmlspecialchars(t("nav.search_placeholder", "Search products..."), ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
    </form>

  </div>

  <div class="header-right">
    <div class="lang-switch" role="group" aria-label="<?= htmlspecialchars(t("nav.language_switch", "Language switch"), ENT_QUOTES, 'UTF-8') ?>">
      <a href="<?= htmlspecialchars(localized_path($pageFile, $_GET, 'en'), ENT_QUOTES, 'UTF-8') ?>" class="lang-switch-option <?= $currentLang === 'en' ? 'active' : '' ?>">EN</a>
      <a href="<?= htmlspecialchars(localized_path($pageFile, $_GET, 'tr'), ENT_QUOTES, 'UTF-8') ?>" class="lang-switch-option <?= $currentLang === 'tr' ? 'active' : '' ?>">TR</a>
    </div>
    <button type="button" class="nav-cart-trigger" onclick="toggleCart()" aria-label="<?= htmlspecialchars(t("nav.cart", "Cart"), ENT_QUOTES, 'UTF-8') ?>">
      <span class="nav-cart-icon">🛒</span>
      <span class="nav-cart-text"><?= htmlspecialchars(t("nav.cart", "Cart"), ENT_QUOTES, 'UTF-8') ?></span>
      <span id="cartCount" class="nav-cart-count">0</span>
    </button>
    <?php if ($loggedInName): ?>
      <a href="<?= htmlspecialchars(localized_path('profile.php'), ENT_QUOTES, 'UTF-8') ?>" class="nav-action"><?= htmlspecialchars(t("nav.hi", "Hi"), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($loggedInName, ENT_QUOTES, 'UTF-8') ?></a>
      <a href="logout.php" class="nav-action"><?= htmlspecialchars(t("nav.logout", "Log out"), ENT_QUOTES, 'UTF-8') ?></a>
    <?php else: ?>
      <a href="auth.php" class="nav-action highlight"><?= htmlspecialchars(t("nav.signin_join", "Sign In / Join"), ENT_QUOTES, 'UTF-8') ?></a>
    <?php endif; ?>
  </div>
</header>

