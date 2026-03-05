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
</head>
<body>

<header class="elegant-header">
  <div class="header-left">
    <div class="logo">STORY</div>

    <nav class="nav-categories">
      <a href="index.php">Home</a>
      <a href="products.php">Products</a>
      <a href="orders.php">Orders</a>
      <a href="wishlist.php">Wishlist</a>
    </nav>

    <input 
      type="text" 
      class="nav-search"
      placeholder="Search"
    />
  </div>

  <div class="header-right">
    <span class="nav-action" onclick="toggleCart()">
      🛒 <span id="cartCount">0</span>
    </span>
    <?php if ($loggedInName): ?>
      <span class="nav-action">Hi, <?= htmlspecialchars($loggedInName, ENT_QUOTES, 'UTF-8') ?></span>
    <?php else: ?>
      <span class="nav-action highlight">Sign in / Join</span>
    <?php endif; ?>
  </div>
</header>

