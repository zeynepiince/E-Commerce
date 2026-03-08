<?php
require_once 'functions.php';

$page_title = "My Wishlist – STORY";
?>

<?php include 'includes/header.php'; ?>

<link rel="stylesheet" href="assets/css/wishlist.css">

<div class="wishlist-page">
  <div class="wishlist-container">
    <header class="wishlist-header">
      <h1 class="wishlist-title">
        My Wishlist <span class="wishlist-title-count" id="wishlistCount"></span>
      </h1>
      <p class="wishlist-subtitle">Products you saved for later</p>
    </header>

    <div id="wishlistContainer"></div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
