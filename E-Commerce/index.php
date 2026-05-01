<?php
require_once 'functions.php';

$page_title = "STORY – Smart Online Store";
$is_homepage = true;
$featuredProducts   = get_featured_products($pdo);
$forYouProducts     = get_random_products($pdo, 4);
$bestSellerProducts = get_random_products($pdo, 4);
$dealProducts       = get_random_products($pdo, 4);

$selectedCategory = $_GET['category'] ?? '';
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
        <a href="products.php?category=<?= urlencode($cat) ?>" class="categories-nav-label <?= $selectedCategory === $cat ? 'active' : '' ?>">
          <?= htmlspecialchars(ucfirst($cat), ENT_QUOTES, 'UTF-8') ?>
        </a>
        <div class="categories-nav-sub">
          <?php foreach ($subCats as $sub): ?>
            <a href="products.php?category=<?= urlencode($cat) ?>">
              <?= htmlspecialchars(str_replace('-', ' ', ucwords($sub)), ENT_QUOTES, 'UTF-8') ?>
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
      <span class="promo-pill promo-pill--today">🔥 Today only</span>
      <span class="promo-desc">Up to 40% off on electronics</span>
    </a>
    <a href="products.php" class="promo-item">
      <span class="promo-pill promo-pill--shipping">🚚 Free shipping</span>
      <span class="promo-desc">On orders over $75</span>
    </a>
    <a href="products.php" class="promo-item">
      <span class="promo-pill promo-pill--members">⭐ Members</span>
      <span class="promo-desc">Extra 10% off selected fashion</span>
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
        <h2 class="hero-title hero-title--arrivals">New arrivals just dropped</h2>
        <p class="hero-subtitle">Be the first to discover our latest picks for the season.</p>
        <a href="#new-arrivals" class="hero-cta">Shop Now</a>
      </div>
    </div>
  </div>
  <!-- Slide 2: Style picks -->
  <div class="hero-slide hero-slide--editors">
    <div class="hero-slide-bg" style="background-image:url('https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=1600')"></div>
    <div class="hero hero--center">
      <div class="hero-copy">
        <h2 class="hero-title hero-title--editors">Style picks from our editors</h2>
        <p class="hero-subtitle">Minimal, timeless pieces that work from desk to dinner.</p>
        <a href="products.php" class="hero-cta hero-cta--outline">See Collection</a>
      </div>
    </div>
  </div>
  <!-- Slide 3: Weekend sale -->
  <div class="hero-slide hero-slide--sale">
    <div class="hero-slide-bg" style="background-image:url('https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=1600')"></div>
    <div class="hero hero--center">
      <div class="hero-copy">
        <h2 class="hero-title hero-title--sale">Weekend sale on gadgets</h2>
        <p class="hero-subtitle">Save on headphones, watches and accessories for 48 hours only.</p>
        <a href="products.php?category=electronics" class="hero-cta">View Tech Deals</a>
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
  <h3>Selected for you</h3>
  <p class="subtitle">
    Products our customers love — chosen for quality, design and comfort.
  </p>

  <div class="featured-grid featured-grid--product-cards">
    <?php foreach ($featuredProducts as $idx => $product): ?>
      <?php $badges = get_product_badges($product, 'featured', $idx); include 'includes/product_card.php'; ?>
    <?php endforeach; ?>
  </div>
</section>

<section class="products">
  <h3>Best sellers</h3>
  <p class="subtitle">
    Community favorites everyone is adding to cart.
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
      <h3>Flash sale – limited time</h3>
      <p class="subtitle">
        Deals that end soon. Don’t miss out.
      </p>
    </div>
    <div class="flash-timer" data-countdown-hours="6">
      <span class="flash-label">Ends in</span>
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
  <h3>On sale</h3>
  <p class="subtitle">
    Campaign products with special prices.
  </p>

  <div class="featured-grid featured-grid--product-cards">
    <?php foreach ($dealProducts as $idx => $product): ?>
      <?php $badges = get_product_badges($product, 'on-sale', $idx); include 'includes/product_card.php'; ?>
    <?php endforeach; ?>
  </div>
</section>

<section class="products" id="new-arrivals">
  <h3>Recommended for you</h3>
  <p class="subtitle">
    A mix of products picked to match your taste.
  </p>

  <div class="featured-grid featured-grid--product-cards">
    <?php foreach ($forYouProducts as $idx => $product): ?>
      <?php $badges = get_product_badges($product, 'recommended', $idx); include 'includes/product_card.php'; ?>
    <?php endforeach; ?>
  </div>
</section>

<section class="products" id="recently-viewed">
  <h3>Recently viewed</h3>
  <p class="subtitle">
    Pick up where you left off.
  </p>
  <div id="recentlyViewed"></div>
</section>

<section class="products wishlist-preview-section">
  <h3>Your favourites</h3>
  <p class="subtitle">
    A quick look at items you’ve saved.
  </p>

  <div id="wishlistPreview"></div>

  <p style="margin-top:12px;font-size:13px;">
    <a href="wishlist.php" style="color:#ff6f00;text-decoration:none;">View all wishlist items →</a>
  </p>
</section>

<section class="products seasonal-section">
  <h3>Seasonal collections</h3>
  <p class="subtitle">
    Curated edits for the moment: from cozy essentials to city breaks.
  </p>
  <div class="seasonal-grid">
    <div class="seasonal-card">
      <div class="seasonal-image seasonal-image--warm"></div>
      <h4>Spring refresh</h4>
      <p>Light layers, fresh colors and everyday accessories.</p>
      <button onclick="window.location.href='products.php'">Shop the edit</button>
    </div>
    <div class="seasonal-card">
      <div class="seasonal-image seasonal-image--tech"></div>
      <h4>Work & create</h4>
      <p>Desks, screens and tools for a focused setup.</p>
      <button onclick="window.location.href='products.php?category=electronics'">See tech picks</button>
    </div>
    <div class="seasonal-card">
      <div class="seasonal-image seasonal-image--travel"></div>
      <h4>Weekend escapes</h4>
      <p>Bags, shoes and outfits made for short trips.</p>
      <button onclick="window.location.href='products.php'">Get ready</button>
    </div>
  </div>
</section>

<section class="brand-strip">
  <div class="brand-strip-inner">
    <span class="brand-label">Popular brands</span>
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
      <h4>From our blog</h4>
      <p><strong>5 essentials for your hybrid work setup</strong></p>
      <p style="font-size:13px;color:#6b7280;">Create a space that keeps you focused and comfortable all day.</p>
      <p><strong>How to build a capsule wardrobe</strong></p>
      <p style="font-size:13px;color:#6b7280;">Timeless pieces that make getting dressed effortless.</p>
    </div>
    <div class="info-col">
      <h4>Shipping & returns</h4>
      <ul>
        <li>Free shipping over $75</li>
        <li>30-day hassle-free returns</li>
        <li>Real-time order tracking</li>
      </ul>
    </div>
    <div class="info-col">
      <h4>Stay in the loop</h4>
      <p style="font-size:13px;color:#6b7280;">Be the first to know about new drops and limited deals.</p>
      <form onsubmit="event.preventDefault();" class="newsletter-form">
        <input type="email" placeholder="Enter your email" required />
        <button type="submit">Join newsletter</button>
      </form>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>

