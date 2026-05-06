<?php
require_once 'functions.php';

$page_title = t("meta.home_title", "ZERA - Home");
$is_homepage = true;
$featuredProducts   = get_featured_products($pdo);
$forYouProducts     = get_random_products($pdo, 4);
$bestSellerProducts = get_random_products($pdo, 4);
$dealProducts       = get_random_products($pdo, 4);
$previouslyBoughtProducts = [];

if (!empty($_SESSION['user_id'])) {
    try {
        $stmtPreviouslyBought = $pdo->prepare("
            SELECT p.name, p.price, p.image_url
            FROM orders o
            INNER JOIN order_items oi ON oi.order_id = o.order_id
            INNER JOIN products p ON p.product_id = oi.product_id
            WHERE o.user_id = ?
            GROUP BY p.product_id, p.name, p.price, p.image_url
            ORDER BY MAX(o.order_date) DESC
            LIMIT 4
        ");
        $stmtPreviouslyBought->execute([(int) $_SESSION['user_id']]);
        $previouslyBoughtProducts = $stmtPreviouslyBought->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $previouslyBoughtProducts = [];
    }
}

$selectedCategory = $_GET['category'] ?? '';
$selectedSubCategory = $_GET['sub_category'] ?? '';
?>

<?php include 'includes/header.php'; ?>

<!-- Categories (navbar altında, hover ile alt kategoriler) -->
<section class="categories-section categories-nav-section">
  <nav class="categories-nav" id="categoriesNav">
    <?php
    // Kategoriler ve alt kategoriler
    $categories = [
        'women' => ['dress','blouse','skirts','women-accessories','women-shoes','bags'],
        'men' => ['shirt','pants','jacket','men-shoes','men-accessories'],
        'electronics' => ['computer-tablet','printer','phone','tv','speakers','camera'],
        'home' => ['furniture','decor','kitchen','bedding'],
        'beauty' => ['skincare','makeup','hair','perfume'],
        'sports' => ['fitness','outdoor','running','cycling'],
        'kids' => ['kids-clothing','kids-toys','games','school'],
        'toys' => ['action-figures','puzzles','board-games','educational-toys'],
        'gadgets' => ['smartwatch','headphones','smart-home','gadgets-accessories'],
        'books' => ['fiction','non-fiction','kids-books','education'],
        'jewelry' => ['rings','necklaces','bracelets','earrings','watches'],
        'pet' => ['dog','cat','pet-food','pet-toys'],
        'auto' => ['car-accessories','car-care','car-electronics'],
        'office' => ['stationery','desk','office-supplies'],
        'garden' => ['outdoor-plants','garden-tools','outdoor-furniture'],
        'health' => ['vitamins','wellness','medical'],
        'baby' => ['baby-clothing','baby-care','baby-toys'],
        'food' => ['snacks','beverages','gourmet'],
        'arts' => ['craft-supplies','art-materials','sewing']
    ];

    foreach ($categories as $cat => $subCats):
    ?>
      <div class="categories-nav-item">
        <a href="<?= htmlspecialchars(localized_path('products.php', ['category' => $cat]), ENT_QUOTES, 'UTF-8') ?>" class="categories-nav-label <?= $selectedCategory === $cat ? 'active' : '' ?>">
          <?= htmlspecialchars(localized_category_label($cat), ENT_QUOTES, 'UTF-8') ?>
        </a>
        <div class="categories-nav-sub">
          <?php foreach ($subCats as $sub): ?>
            <a href="<?= htmlspecialchars(localized_path('products.php', ['category' => $cat, 'sub_category' => $sub]), ENT_QUOTES, 'UTF-8') ?>" class="<?= $selectedSubCategory === $sub ? 'active' : '' ?>">
              <?= htmlspecialchars(localized_category_label($sub), ENT_QUOTES, 'UTF-8') ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </nav>
</section>

<section class="promo-section">
  <div class="promo-bar">
    <a href="#flash-sale" class="promo-item">
      <span class="promo-pill promo-pill--today">🔥 <?= htmlspecialchars(t("home.promo.today_only", "Today only"), ENT_QUOTES, 'UTF-8') ?></span>
      <span class="promo-desc"><?= htmlspecialchars(t("home.promo.electronics_discount", "Up to 40% off on electronics"), ENT_QUOTES, 'UTF-8') ?></span>
    </a>
    <a href="<?= htmlspecialchars(localized_path('products.php'), ENT_QUOTES, 'UTF-8') ?>" class="promo-item">
      <span class="promo-pill promo-pill--shipping">🚚 <?= htmlspecialchars(t("home.free_shipping", "Free shipping"), ENT_QUOTES, 'UTF-8') ?></span>
      <span class="promo-desc"><?= htmlspecialchars(t("home.over_75", "On orders over $75"), ENT_QUOTES, 'UTF-8') ?></span>
    </a>
    <a href="<?= htmlspecialchars(localized_path('products.php'), ENT_QUOTES, 'UTF-8') ?>" class="promo-item">
      <span class="promo-pill promo-pill--members">⭐ <?= htmlspecialchars(t("home.promo.members", "Members"), ENT_QUOTES, 'UTF-8') ?></span>
      <span class="promo-desc"><?= htmlspecialchars(t("home.promo.fashion_discount", "Extra 10% off selected fashion"), ENT_QUOTES, 'UTF-8') ?></span>
    </a>
  </div>
</section>

<section class="hero-section" id="top">
  <div class="hero-slider">
  <!-- Slide 1: New arrivals (first impression) -->
  <div class="hero-slide hero-slide--active hero-slide--arrivals">
    <div class="hero-slide-bg" style="background-image:url('https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=1600')"></div>
    <div class="hero hero--center">
      <div class="hero-copy">
        <h2 class="hero-title hero-title--arrivals"><?= htmlspecialchars(t("home.hero.new_arrivals", "New arrivals just dropped"), ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="hero-subtitle"><?= htmlspecialchars(t("home.hero.new_arrivals_sub", "Be the first to discover our latest picks for the season."), ENT_QUOTES, 'UTF-8') ?></p>
        <a href="#new-arrivals" class="hero-cta"><?= htmlspecialchars(t("home.shop_now", "Shop Now"), ENT_QUOTES, 'UTF-8') ?></a>
      </div>
    </div>
  </div>
  <!-- Slide 2: Style picks -->
  <div class="hero-slide hero-slide--editors">
    <div class="hero-slide-bg" style="background-image:url('https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=1600')"></div>
    <div class="hero hero--center">
      <div class="hero-copy">
        <h2 class="hero-title hero-title--editors"><?= htmlspecialchars(t("home.hero.editors", "Style picks from our editors"), ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="hero-subtitle"><?= htmlspecialchars(t("home.hero.editors_sub", "Minimal, timeless pieces that work from desk to dinner."), ENT_QUOTES, 'UTF-8') ?></p>
        <a href="<?= htmlspecialchars(localized_path('products.php'), ENT_QUOTES, 'UTF-8') ?>" class="hero-cta hero-cta--outline"><?= htmlspecialchars(t("home.see_collection", "See Collection"), ENT_QUOTES, 'UTF-8') ?></a>
      </div>
    </div>
  </div>
  <!-- Slide 3: Weekend sale -->
  <div class="hero-slide hero-slide--sale">
    <div class="hero-slide-bg" style="background-image:url('https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=1600')"></div>
    <div class="hero hero--center">
      <div class="hero-copy">
        <h2 class="hero-title hero-title--sale"><?= htmlspecialchars(t("home.hero.weekend_sale", "Weekend sale on gadgets"), ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="hero-subtitle"><?= htmlspecialchars(t("home.hero.weekend_sale_sub", "Save on headphones, watches and accessories for 48 hours only."), ENT_QUOTES, 'UTF-8') ?></p>
        <a href="<?= htmlspecialchars(localized_path('products.php', ['category' => 'electronics']), ENT_QUOTES, 'UTF-8') ?>" class="hero-cta"><?= htmlspecialchars(t("home.view_tech_deals", "View Tech Deals"), ENT_QUOTES, 'UTF-8') ?></a>
      </div>
    </div>
  </div>
  <button type="button" class="hero-arrow hero-arrow--prev" aria-label="Previous slide">‹</button>
  <button type="button" class="hero-arrow hero-arrow--next" aria-label="Next slide">›</button>
  <div class="hero-dots" id="heroDots"></div>
  </div>
</section>

<!-- Featured ürünler grid -->
<section class="products" id="featured">
  <h3><?= htmlspecialchars(t("home.selected_for_you", "Selected for you"), ENT_QUOTES, 'UTF-8') ?></h3>
  <p class="subtitle">
    <?= htmlspecialchars(t("home.selected_for_you_sub", "Products our customers love — chosen for quality, design and comfort."), ENT_QUOTES, 'UTF-8') ?>
  </p>

  <div class="featured-grid featured-grid--product-cards">
    <?php foreach ($featuredProducts as $idx => $product): ?>
      <?php $badges = get_product_badges($product, 'featured', $idx); include 'includes/product_card.php'; ?>
    <?php endforeach; ?>
  </div>
</section>

<?php if (!empty($previouslyBoughtProducts)): ?>
<section class="products" id="previously-bought">
  <h3><?= htmlspecialchars(t("home.previously_bought", "You bought these before"), ENT_QUOTES, 'UTF-8') ?></h3>
  <p class="subtitle">
    <?= htmlspecialchars(t("home.previously_bought_sub", "Reorder your past purchases quickly."), ENT_QUOTES, 'UTF-8') ?>
  </p>

  <div class="featured-grid featured-grid--product-cards">
    <?php foreach ($previouslyBoughtProducts as $idx => $product): ?>
      <?php $badges = get_product_badges($product, 'recommended', $idx); include 'includes/product_card.php'; ?>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="products">
  <h3><?= htmlspecialchars(t("home.best_sellers", "Best sellers"), ENT_QUOTES, 'UTF-8') ?></h3>
  <p class="subtitle">
    <?= htmlspecialchars(t("home.best_sellers_sub", "Community favorites everyone is adding to cart."), ENT_QUOTES, 'UTF-8') ?>
  </p>

  <div class="slider slider--product-cards">
    <?php foreach ($bestSellerProducts as $idx => $product): ?>
      <div class="slider-product-wrap">
        <?php $badges = get_product_badges($product, 'best-sellers', $idx); include 'includes/product_card.php'; ?>
        <?php $addedCount = rand(24, 140); ?>
        <p class="product-card-social-proof"><?= $addedCount ?> people have this in their cart</p>
      </div>
    <?php endforeach; ?>
    <?php foreach ($bestSellerProducts as $idx => $product): ?>
      <div class="slider-product-wrap">
        <?php $badges = get_product_badges($product, 'best-sellers', $idx); include 'includes/product_card.php'; ?>
        <?php $addedCount = rand(24, 140); ?>
        <p class="product-card-social-proof"><?= $addedCount ?> people have this in their cart</p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- Flash sale / limited time offers -->
<section class="products flash-sale-section" id="flash-sale">
  <div class="flash-sale-header">
    <div>
      <h3><?= htmlspecialchars(t("home.flash_sale", "Flash sale – limited time"), ENT_QUOTES, 'UTF-8') ?></h3>
      <p class="subtitle">
        <?= htmlspecialchars(t("home.flash_sale_sub", "Deals that end soon. Don’t miss out."), ENT_QUOTES, 'UTF-8') ?>
      </p>
    </div>
    <div class="flash-timer" data-countdown-hours="6">
      <span class="flash-label"><?= htmlspecialchars(t("home.ends_in", "Ends in"), ENT_QUOTES, 'UTF-8') ?></span>
      <span class="flash-time" id="flashCountdown">06:00:00</span>
    </div>
  </div>

  <div class="featured-grid featured-grid--product-cards">
    <?php foreach ($dealProducts as $idx => $product): ?>
      <?php $badges = array_merge(get_product_badges($product, 'on-sale', $idx), [['key' => 'flash-deal', 'label' => 'Flash deal']]); include 'includes/product_card.php'; ?>
    <?php endforeach; ?>
  </div>
</section>

<section class="products" id="on-sale">
  <h3><?= htmlspecialchars(t("home.on_sale", "On sale"), ENT_QUOTES, 'UTF-8') ?></h3>
  <p class="subtitle">
    <?= htmlspecialchars(t("home.on_sale_sub", "Campaign products with special prices."), ENT_QUOTES, 'UTF-8') ?>
  </p>

  <div class="featured-grid featured-grid--product-cards">
    <?php foreach ($dealProducts as $idx => $product): ?>
      <?php $badges = get_product_badges($product, 'on-sale', $idx); include 'includes/product_card.php'; ?>
    <?php endforeach; ?>
  </div>
</section>

<section class="products wishlist-preview-section">
  <h3><?= htmlspecialchars(t("home.your_favourites", "Your favourites"), ENT_QUOTES, 'UTF-8') ?></h3>
  <p class="subtitle">
    <?= htmlspecialchars(t("home.your_favourites_sub", "A quick look at items you’ve saved."), ENT_QUOTES, 'UTF-8') ?>
  </p>

  <div id="wishlistPreview"></div>

  <p style="margin-top:12px;font-size:13px;">
    <a href="<?= htmlspecialchars(localized_path('wishlist.php'), ENT_QUOTES, 'UTF-8') ?>" style="color:#ff6f00;text-decoration:none;"><?= htmlspecialchars(t("home.view_all_wishlist", "View all wishlist items"), ENT_QUOTES, 'UTF-8') ?> →</a>
  </p>
</section>

<section class="products" id="new-arrivals">
  <h3><?= htmlspecialchars(t("home.recommended_for_you", "Recommended for you"), ENT_QUOTES, 'UTF-8') ?></h3>
  <p class="subtitle">
    <?= htmlspecialchars(t("home.recommended_for_you_sub", "A mix of products picked to match your taste."), ENT_QUOTES, 'UTF-8') ?>
  </p>

  <div class="featured-grid featured-grid--product-cards">
    <?php foreach ($forYouProducts as $idx => $product): ?>
      <?php $badges = get_product_badges($product, 'recommended', $idx); include 'includes/product_card.php'; ?>
    <?php endforeach; ?>
  </div>
</section>

<section class="products" id="recently-viewed">
  <h3><?= htmlspecialchars(t("home.recently_viewed", "Recently viewed"), ENT_QUOTES, 'UTF-8') ?></h3>
  <p class="subtitle">
    <?= htmlspecialchars(t("home.recently_viewed_sub", "Pick up where you left off."), ENT_QUOTES, 'UTF-8') ?>
  </p>
  <div id="recentlyViewed"></div>
</section>

<section class="products seasonal-section">
  <h3><?= htmlspecialchars(t("home.seasonal_collections", "Seasonal collections"), ENT_QUOTES, 'UTF-8') ?></h3>
  <p class="subtitle">
    <?= htmlspecialchars(t("home.seasonal_collections_sub", "Curated edits for the moment: from cozy essentials to city breaks."), ENT_QUOTES, 'UTF-8') ?>
  </p>
  <div class="seasonal-grid">
    <div class="seasonal-card">
      <div class="seasonal-image seasonal-image--warm"></div>
      <h4><?= htmlspecialchars(t("home.seasonal.spring_title", "Spring refresh"), ENT_QUOTES, 'UTF-8') ?></h4>
      <p><?= htmlspecialchars(t("home.seasonal.spring_desc", "Light layers, fresh colors and everyday accessories."), ENT_QUOTES, 'UTF-8') ?></p>
      <button onclick="window.location.href='<?= htmlspecialchars(localized_path('products.php', ['seasonal' => 'spring']), ENT_QUOTES, 'UTF-8') ?>'"><?= htmlspecialchars(t("home.seasonal.spring_cta", "Shop the edit"), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
    <div class="seasonal-card">
      <div class="seasonal-image seasonal-image--tech"></div>
      <h4><?= htmlspecialchars(t("home.seasonal.tech_title", "Work & create"), ENT_QUOTES, 'UTF-8') ?></h4>
      <p><?= htmlspecialchars(t("home.seasonal.tech_desc", "Desks, screens and tools for a focused setup."), ENT_QUOTES, 'UTF-8') ?></p>
      <button onclick="window.location.href='<?= htmlspecialchars(localized_path('products.php', ['seasonal' => 'tech']), ENT_QUOTES, 'UTF-8') ?>'"><?= htmlspecialchars(t("home.seasonal.tech_cta", "See tech picks"), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
    <div class="seasonal-card">
      <div class="seasonal-image seasonal-image--travel"></div>
      <h4><?= htmlspecialchars(t("home.seasonal.travel_title", "Weekend escapes"), ENT_QUOTES, 'UTF-8') ?></h4>
      <p><?= htmlspecialchars(t("home.seasonal.travel_desc", "Bags, shoes and outfits made for short trips."), ENT_QUOTES, 'UTF-8') ?></p>
      <button onclick="window.location.href='<?= htmlspecialchars(localized_path('products.php', ['seasonal' => 'travel']), ENT_QUOTES, 'UTF-8') ?>'"><?= htmlspecialchars(t("home.seasonal.travel_cta", "Get ready"), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
  </div>
</section>

<section class="brand-strip">
  <div class="brand-strip-inner">
    <span class="brand-label"><?= htmlspecialchars(t("home.popular_brands", "Popular brands"), ENT_QUOTES, 'UTF-8') ?></span>
    <div class="brand-logos">
      <span class="brand-logo">NOVA</span>
      <span class="brand-logo">URBAN STUDIO</span>
      <span class="brand-logo">PURE HOME</span>
      <span class="brand-logo">SOUNDLY</span>
      <span class="brand-logo">MOVE+</span>
    </div>
  </div>
</section>

<section class="info-section">
  <div class="info-grid">
    <div class="info-col">
      <h4><?= htmlspecialchars(t("home.blog_title", "From our blog"), ENT_QUOTES, 'UTF-8') ?></h4>
      <p><strong><?= htmlspecialchars(t("home.blog_post_1_title", "5 essentials for your hybrid work setup"), ENT_QUOTES, 'UTF-8') ?></strong></p>
      <p style="font-size:13px;color:#6b7280;"><?= htmlspecialchars(t("home.blog_post_1_desc", "Create a space that keeps you focused and comfortable all day."), ENT_QUOTES, 'UTF-8') ?></p>
      <p><strong><?= htmlspecialchars(t("home.blog_post_2_title", "How to build a capsule wardrobe"), ENT_QUOTES, 'UTF-8') ?></strong></p>
      <p style="font-size:13px;color:#6b7280;"><?= htmlspecialchars(t("home.blog_post_2_desc", "Timeless pieces that make getting dressed effortless."), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <div class="info-col">
      <h4><?= htmlspecialchars(t("home.shipping_returns_title", "Shipping & returns"), ENT_QUOTES, 'UTF-8') ?></h4>
      <ul>
        <li><?= htmlspecialchars(t("home.shipping_returns_item_1", "Free shipping over $75"), ENT_QUOTES, 'UTF-8') ?></li>
        <li><?= htmlspecialchars(t("home.shipping_returns_item_2", "30-day hassle-free returns"), ENT_QUOTES, 'UTF-8') ?></li>
        <li><?= htmlspecialchars(t("home.shipping_returns_item_3", "Real-time order tracking"), ENT_QUOTES, 'UTF-8') ?></li>
      </ul>
    </div>
    <div class="info-col">
      <h4><?= htmlspecialchars(t("home.newsletter_title", "Stay in the loop"), ENT_QUOTES, 'UTF-8') ?></h4>
      <p style="font-size:13px;color:#6b7280;"><?= htmlspecialchars(t("home.newsletter_sub", "Be the first to know about new drops and limited deals."), ENT_QUOTES, 'UTF-8') ?></p>
      <form onsubmit="event.preventDefault();" class="newsletter-form">
        <input type="email" placeholder="<?= htmlspecialchars(t("home.newsletter_placeholder", "Enter your email"), ENT_QUOTES, 'UTF-8') ?>" required />
        <button type="submit"><?= htmlspecialchars(t("home.newsletter_cta", "Join newsletter"), ENT_QUOTES, 'UTF-8') ?></button>
      </form>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>

