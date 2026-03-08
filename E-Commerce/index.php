<?php
require_once 'functions.php';

$page_title = "STORY – Smart Online Store";
$is_homepage = true;
$featuredProducts   = get_featured_products($pdo);
$forYouProducts     = get_random_products($pdo, 4);
$bestSellerProducts = get_random_products($pdo, 4);
$dealProducts       = get_random_products($pdo, 4);
?>

<?php include 'includes/header.php'; ?>

<!-- Categories (navbar altında, hover ile alt kategoriler) -->
<section class="categories-section categories-nav-section">
  <nav class="categories-nav" id="categoriesNav">
    <div class="categories-nav-item">
      <a href="products.php?category=women" class="categories-nav-label">Women</a>
      <div class="categories-nav-sub">
        <a href="products.php?category=dress">Dress</a>
        <a href="products.php?category=blouse">Blouse</a>
        <a href="products.php?category=skirts">Skirts</a>
        <a href="products.php?category=women-accessories">Accessories</a>
        <a href="products.php?category=women-shoes">Shoes</a>
        <a href="products.php?category=bags">Bags</a>
      </div>
    </div>
    <div class="categories-nav-item">
      <a href="products.php?category=men" class="categories-nav-label">Men</a>
      <div class="categories-nav-sub">
        <a href="products.php?category=shirt">Shirt</a>
        <a href="products.php?category=pants">Pants</a>
        <a href="products.php?category=jacket">Jacket</a>
        <a href="products.php?category=men-shoes">Shoes</a>
        <a href="products.php?category=men-accessories">Accessories</a>
      </div>
    </div>
    <div class="categories-nav-item">
      <a href="products.php?category=electronics" class="categories-nav-label">Electronics</a>
      <div class="categories-nav-sub">
        <a href="products.php?category=computer-tablet">Computer & Tablet</a>
        <a href="products.php?category=printer">Printer</a>
        <a href="products.php?category=phone">Phone</a>
        <a href="products.php?category=tv">TV</a>
        <a href="products.php?category=speakers">Speakers</a>
        <a href="products.php?category=camera">Camera</a>
      </div>
    </div>
    <div class="categories-nav-item">
      <a href="products.php?category=home" class="categories-nav-label">Home</a>
      <div class="categories-nav-sub">
        <a href="products.php?category=furniture">Furniture</a>
        <a href="products.php?category=decor">Decor</a>
        <a href="products.php?category=kitchen">Kitchen</a>
        <a href="products.php?category=bedding">Bedding</a>
      </div>
    </div>
    <div class="categories-nav-item">
      <a href="products.php?category=beauty" class="categories-nav-label">Beauty</a>
      <div class="categories-nav-sub">
        <a href="products.php?category=skincare">Skincare</a>
        <a href="products.php?category=makeup">Makeup</a>
        <a href="products.php?category=hair">Hair</a>
        <a href="products.php?category=perfume">Perfume</a>
      </div>
    </div>
    <div class="categories-nav-item">
      <a href="products.php?category=sports" class="categories-nav-label">Sports</a>
      <div class="categories-nav-sub">
        <a href="products.php?category=fitness">Fitness</a>
        <a href="products.php?category=outdoor">Outdoor</a>
        <a href="products.php?category=running">Running</a>
        <a href="products.php?category=cycling">Cycling</a>
      </div>
    </div>
    <div class="categories-nav-item">
      <a href="products.php?category=kids" class="categories-nav-label">Kids</a>
      <div class="categories-nav-sub">
        <a href="products.php?category=kids-clothing">Clothing</a>
        <a href="products.php?category=kids-toys">Toys</a>
        <a href="products.php?category=games">Games</a>
        <a href="products.php?category=school">School</a>
      </div>
    </div>
    <div class="categories-nav-item">
      <a href="products.php?category=toys" class="categories-nav-label">Toys</a>
      <div class="categories-nav-sub">
        <a href="products.php?category=action-figures">Action Figures</a>
        <a href="products.php?category=puzzles">Puzzles</a>
        <a href="products.php?category=board-games">Board Games</a>
        <a href="products.php?category=educational-toys">Educational</a>
      </div>
    </div>
    <div class="categories-nav-item">
      <a href="products.php?category=gadgets" class="categories-nav-label">Gadgets</a>
      <div class="categories-nav-sub">
        <a href="products.php?category=smartwatch">Smartwatch</a>
        <a href="products.php?category=headphones">Headphones</a>
        <a href="products.php?category=smart-home">Smart Home</a>
        <a href="products.php?category=gadgets-accessories">Accessories</a>
      </div>
    </div>
    <div class="categories-nav-item">
      <a href="products.php?category=books" class="categories-nav-label">Books</a>
      <div class="categories-nav-sub">
        <a href="products.php?category=fiction">Fiction</a>
        <a href="products.php?category=non-fiction">Non-Fiction</a>
        <a href="products.php?category=kids-books">Kids Books</a>
        <a href="products.php?category=education">Education</a>
      </div>
    </div>
    <div class="categories-nav-item">
      <a href="products.php?category=jewelry" class="categories-nav-label">Jewelry</a>
      <div class="categories-nav-sub">
        <a href="products.php?category=rings">Rings</a>
        <a href="products.php?category=necklaces">Necklaces</a>
        <a href="products.php?category=bracelets">Bracelets</a>
        <a href="products.php?category=earrings">Earrings</a>
        <a href="products.php?category=watches">Watches</a>
      </div>
    </div>
    <div class="categories-nav-item">
      <a href="products.php?category=pet" class="categories-nav-label">Pet</a>
      <div class="categories-nav-sub">
        <a href="products.php?category=dog">Dog</a>
        <a href="products.php?category=cat">Cat</a>
        <a href="products.php?category=pet-food">Food</a>
        <a href="products.php?category=pet-toys">Toys</a>
      </div>
    </div>
    <div class="categories-nav-item">
      <a href="products.php?category=auto" class="categories-nav-label">Auto</a>
      <div class="categories-nav-sub">
        <a href="products.php?category=car-accessories">Accessories</a>
        <a href="products.php?category=car-care">Care</a>
        <a href="products.php?category=car-electronics">Electronics</a>
      </div>
    </div>
    <div class="categories-nav-item">
      <a href="products.php?category=office" class="categories-nav-label">Office</a>
      <div class="categories-nav-sub">
        <a href="products.php?category=stationery">Stationery</a>
        <a href="products.php?category=desk">Desk</a>
        <a href="products.php?category=office-supplies">Supplies</a>
      </div>
    </div>
    <div class="categories-nav-item">
      <a href="products.php?category=garden" class="categories-nav-label">Garden</a>
      <div class="categories-nav-sub">
        <a href="products.php?category=outdoor-plants">Plants</a>
        <a href="products.php?category=garden-tools">Tools</a>
        <a href="products.php?category=outdoor-furniture">Furniture</a>
      </div>
    </div>
    <div class="categories-nav-item">
      <a href="products.php?category=health" class="categories-nav-label">Health</a>
      <div class="categories-nav-sub">
        <a href="products.php?category=vitamins">Vitamins</a>
        <a href="products.php?category=wellness">Wellness</a>
        <a href="products.php?category=medical">Medical</a>
      </div>
    </div>
    <div class="categories-nav-item">
      <a href="products.php?category=baby" class="categories-nav-label">Baby</a>
      <div class="categories-nav-sub">
        <a href="products.php?category=baby-clothing">Clothing</a>
        <a href="products.php?category=baby-care">Care</a>
        <a href="products.php?category=baby-toys">Toys</a>
      </div>
    </div>
    <div class="categories-nav-item">
      <a href="products.php?category=food" class="categories-nav-label">Food & Drink</a>
      <div class="categories-nav-sub">
        <a href="products.php?category=snacks">Snacks</a>
        <a href="products.php?category=beverages">Beverages</a>
        <a href="products.php?category=gourmet">Gourmet</a>
      </div>
    </div>
    <div class="categories-nav-item">
      <a href="products.php?category=arts" class="categories-nav-label">Arts & Crafts</a>
      <div class="categories-nav-sub">
        <a href="products.php?category=craft-supplies">Supplies</a>
        <a href="products.php?category=art-materials">Art Materials</a>
        <a href="products.php?category=sewing">Sewing</a>
      </div>
    </div>
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

