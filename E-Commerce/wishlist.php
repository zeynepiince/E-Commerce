<?php
require_once 'functions.php';

$page_title = t("meta.wishlist_title", "ZERA - Favorites");
?>

<?php include 'includes/header.php'; ?>

<link rel="stylesheet" href="<?= htmlspecialchars(asset_url('assets/css/wishlist.css'), ENT_QUOTES, 'UTF-8') ?>?v=2">

<div class="wishlist-page">
  <div class="wishlist-container">
    <header class="wishlist-header">
      <h1 class="wishlist-title">
        <?= htmlspecialchars(t("wishlist.title", "My Wishlist"), ENT_QUOTES, 'UTF-8') ?> <span class="wishlist-title-count" id="wishlistCount"></span>
      </h1>
      <p class="wishlist-subtitle"><?= htmlspecialchars(t("wishlist.subtitle", "Products you saved for later"), ENT_QUOTES, 'UTF-8') ?></p>
    </header>

    <div id="wishlistContainer"></div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
