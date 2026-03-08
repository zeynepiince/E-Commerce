<?php
if (!isset($page_title)) {
    $page_title = "STORY – Smart Online Store";
}

// Simple user info placeholder (to be filled with real auth later)
$loggedInName = $_SESSION['user_name'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/navbar.css">
  <link rel="stylesheet" href="assets/css/auth-modal.css">
  <?php if (isset($is_homepage) && $is_homepage): ?>
  <link rel="stylesheet" href="assets/css/homepage.css">
  <link rel="stylesheet" href="assets/css/products.css">
  <?php endif; ?>
  <?php if (isset($is_checkout) && $is_checkout): ?>
  <link rel="stylesheet" href="assets/css/checkout.css">
  <?php endif; ?>
</head>
<body>

<header class="elegant-header">
  <button type="button" class="nav-hamburger" onclick="toggleNavMenu()" aria-label="Menu">☰</button>
  <div class="header-left">
    <a href="index.php" class="logo">STORY</a>

    <nav class="nav-categories" id="navCategories">
      <a href="index.php">Home</a>
      <a href="products.php">Products</a>
      <a href="orders.php">Orders</a>
      <a href="wishlist.php">Wishlist</a>
    </nav>

    <input type="text" class="nav-search" placeholder="Search" />
  </div>

  <div class="header-right">
    <span class="nav-action" onclick="toggleCart()">
      🛒 <span id="cartCount">0</span>
    </span>
    <?php if ($loggedInName): ?>
      <a href="profile.php" class="nav-action">Hi, <?= htmlspecialchars($loggedInName, ENT_QUOTES, 'UTF-8') ?></a>
      <a href="logout.php" class="nav-action">Log out</a>
    <?php else: ?>
      <a href="auth.php" class="nav-action highlight">Sign In / Join</a>
    <?php endif; ?>
  </div>
</header>

