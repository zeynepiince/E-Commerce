<?php
require_once 'functions.php';

$page_title = "STORY – Smart Online Store";
$featuredProducts   = get_featured_products($pdo);
$forYouProducts     = get_random_products($pdo, 4);
$bestSellerProducts = get_random_products($pdo, 4);
$dealProducts       = get_random_products($pdo, 4);
?>

<?php include 'includes/header.php'; ?>


<section class="promo-section">
  <div class="promo-bar">
    <div class="promo-item">
      <span class="promo-pill">🔥 Today only</span>
      <span>Up to 40% off on electronics</span>
    </div>
    <div class="promo-item">
      <span class="promo-pill">🚚 Free shipping</span>
      <span>On orders over $75</span>
    </div>
    <div class="promo-item">
      <span class="promo-pill">⭐ Members</span>
      <span>Extra 10% off selected fashion</span>
    </div>
  </div>
</section>

<section class="hero-section" id="top">
  <div class="hero-slider">
  <div class="hero-slide hero-slide--active">
    <div class="hero hero--fresh">
      <div class="hero-copy">
        <h2 class="hero-title hero-title--fresh">
          Fresh drops for<br>your everyday
        </h2>
        <p class="hero-subtitle">
          New season essentials in tech and fashion, curated for a fast lifestyle.
        </p>
        <button class="hero-cta" onclick="window.location.href='#new-arrivals'">Shop new arrivals</button>
      </div>
      <div class="hero-grid">
        <img src="https://i.pinimg.com/736x/a0/9f/92/a09f929f75c624b10194b335cd8da7dd.jpg">
        <img src="https://i.pinimg.com/736x/30/35/6d/30356dec92cfd4dbd55efe5baaa37eed.jpg">
        <img src="https://images.unsplash.com/photo-1523275335684-37898b6baf30">
        <img src="https://i.pinimg.com/1200x/02/30/46/023046ef32b5d5836875737e83316e1f.jpg">
        <img src="https://i.pinimg.com/1200x/7f/89/8c/7f898c8c51454e69eee5ee3d826f7f40.jpg">
        <img src="https://images.unsplash.com/photo-1524758631624-e2822e304c36">
        <img src="https://i.pinimg.com/1200x/1c/cb/80/1ccb80c78a42fd925bb405fa1388d5d1.jpg">
        <img src="https://i.pinimg.com/1200x/65/44/a8/6544a82a026f71ff348ede1b75b147fb.jpg">
        <img src="https://i.pinimg.com/1200x/01/72/2b/01722b3d35197d315402729c573d7281.jpg">
      </div>
    </div>
  </div>
  <div class="hero-slide">
    <div class="hero hero--center hero--sale">
      <div class="hero-copy">
        <h2 class="hero-title hero-title--sale">
          Weekend sale<br>on gadgets
        </h2>
        <p class="hero-subtitle">
          Save on headphones, watches and accessories for 48 hours only.
        </p>
        <button class="hero-cta" onclick="window.location.href='#flash-sale'">View tech deals</button>
      </div>
    </div>
  </div>
  <div class="hero-slide">
    <div class="hero hero--center hero--editors">
      <div class="hero-copy">
        <h2 class="hero-title hero-title--editors">
          Style picks<br>from our editors
        </h2>
        <p class="hero-subtitle">
          Minimal, timeless pieces that work from desk to dinner.
        </p>
        <button class="hero-cta hero-cta--outline" onclick="window.location.href='products.php'">See collection</button>
      </div>
    </div>
  </div>
  <div class="hero-dots">
    <button class="hero-dot hero-dot--active" data-slide="0"></button>
    <button class="hero-dot" data-slide="1"></button>
    <button class="hero-dot" data-slide="2"></button>
  </div>
  </div>
</section>

<!-- Popüler kategoriler -->
<section class="categories-section">
  <h3>Popular categories</h3>
  <p class="subtitle">
    Explore our most-loved sections.
  </p>
  <div class="categories-grid">
    <a class="category-pill" href="products.php?category=women">Women</a>
    <a class="category-pill" href="products.php?category=men">Men</a>
    <a class="category-pill" href="products.php?category=electronics">Electronics</a>
    <a class="category-pill" href="products.php?category=home">Home</a>
    <a class="category-pill" href="products.php?category=beauty">Beauty</a>
    <a class="category-pill" href="products.php?category=sports">Sports</a>
  </div>
</section>

<!-- Featured ürünler grid -->
<section class="products" id="featured">
  <h3>Selected for you</h3>
  <p class="subtitle">
    Products our customers love — chosen for quality, design and comfort.
  </p>

  <div class="featured-grid">
    <?php foreach ($featuredProducts as $index => $product): ?>
      <div class="card featured-card">
        <button
          type="button"
          class="wishlist-btn"
          data-id="<?= (int) ($index + 1) ?>"
          onclick="toggleFavorite(
            <?= (int) ($index + 1) ?>,
            '<?= htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES, 'UTF-8') ?>',
            '<?= htmlspecialchars($product['image_url'] ?: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff', ENT_QUOTES, 'UTF-8') ?>'
          )"
        >
          ♡
        </button>
        <img src="<?= htmlspecialchars($product['image_url'] ?: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff', ENT_QUOTES, 'UTF-8') ?>">
        <h4><?= htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES, 'UTF-8') ?></h4>
        <p class="price">
          $<?= htmlspecialchars($product['price'] ?? '0', ENT_QUOTES, 'UTF-8') ?>
        </p>
        <button
          onclick="addToCart(<?= (int) ($index + 1) ?>,'<?= htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES, 'UTF-8') ?>',<?= (float) ($product['price'] ?? 0) ?>,'<?= htmlspecialchars($product['image_url'] ?: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff', ENT_QUOTES, 'UTF-8') ?>')"
        >
          Add to Cart
        </button>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="products">
  <h3>Best sellers</h3>
  <p class="subtitle">
    Community favorites everyone is adding to cart.
  </p>

  <div class="slider">
    <?php foreach ($bestSellerProducts as $index => $product): ?>
      <div class="card">
        <span class="badge badge-hot">Hot</span>
        <button
          type="button"
          class="wishlist-btn"
          data-id="<?= (int) ($index + 101) ?>"
          onclick="toggleFavorite(
            <?= (int) ($index + 101) ?>,
            '<?= htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES, 'UTF-8') ?>',
            '<?= htmlspecialchars($product['image_url'] ?: 'https://images.unsplash.com/photo-1510552776732-01acc9a4c83b', ENT_QUOTES, 'UTF-8') ?>'
          )"
        >
          ♡
        </button>
        <img src="<?= htmlspecialchars($product['image_url'] ?: 'https://images.unsplash.com/photo-1510552776732-01acc9a4c83b', ENT_QUOTES, 'UTF-8') ?>">
        <h4><?= htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES, 'UTF-8') ?></h4>
        <p class="price">$<?= htmlspecialchars($product['price'] ?? '0', ENT_QUOTES, 'UTF-8') ?></p>
        <button
          onclick="addToCart(<?= (int) ($index + 101) ?>,'<?= htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES, 'UTF-8') ?>',<?= (float) ($product['price'] ?? 0) ?>,'<?= htmlspecialchars($product['image_url'] ?: 'https://images.unsplash.com/photo-1510552776732-01acc9a4c83b', ENT_QUOTES, 'UTF-8') ?>')"
        >
          Add to Cart
        </button>
        <?php $addedCount = rand(24, 140); ?>
        <p class="social-proof"><?= $addedCount ?> people have this in their cart</p>
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

  <div class="featured-grid">
    <?php foreach ($dealProducts as $index => $product): ?>
      <div class="card featured-card">
        <span class="badge badge-sale">Flash deal</span>
        <button
          type="button"
          class="wishlist-btn"
          data-id="<?= (int) ($index + 201) ?>"
          onclick="toggleFavorite(
            <?= (int) ($index + 201) ?>,
            '<?= htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES, 'UTF-8') ?>',
            '<?= htmlspecialchars($product['image_url'] ?: 'https://images.unsplash.com/photo-1512499617640-c2f999018b72', ENT_QUOTES, 'UTF-8') ?>'
          )"
        >
          ♡
        </button>
        <img src="<?= htmlspecialchars($product['image_url'] ?: 'https://images.unsplash.com/photo-1512499617640-c2f999018b72', ENT_QUOTES, 'UTF-8') ?>">
        <h4><?= htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES, 'UTF-8') ?></h4>
        <p class="price">$<?= htmlspecialchars($product['price'] ?? '0', ENT_QUOTES, 'UTF-8') ?></p>
        <button
          onclick="addToCart(<?= (int) ($index + 201) ?>,'<?= htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES, 'UTF-8') ?>',<?= (float) ($product['price'] ?? 0) ?>,'<?= htmlspecialchars($product['image_url'] ?: 'https://images.unsplash.com/photo-1512499617640-c2f999018b72', ENT_QUOTES, 'UTF-8') ?>')"
        >
          Add to Cart
        </button>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="products" id="on-sale">
  <h3>On sale</h3>
  <p class="subtitle">
    Campaign products with special prices.
  </p>

  <div class="featured-grid">
    <?php foreach ($dealProducts as $index => $product): ?>
      <div class="card featured-card card--has-quickview">
        <span class="badge badge-sale">Deal</span>
        <button
          type="button"
          class="wishlist-btn"
          data-id="<?= (int) ($index + 201) ?>"
          onclick="toggleFavorite(
            <?= (int) ($index + 201) ?>,
            '<?= htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES, 'UTF-8') ?>',
            '<?= htmlspecialchars($product['image_url'] ?: 'https://images.unsplash.com/photo-1512499617640-c2f999018b72', ENT_QUOTES, 'UTF-8') ?>'
          )"
        >
          ♡
        </button>
        <img src="<?= htmlspecialchars($product['image_url'] ?: 'https://images.unsplash.com/photo-1512499617640-c2f999018b72', ENT_QUOTES, 'UTF-8') ?>">
        <h4><?= htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES, 'UTF-8') ?></h4>
        <p class="price">$<?= htmlspecialchars($product['price'] ?? '0', ENT_QUOTES, 'UTF-8') ?></p>
        <button
          onclick="addToCart(<?= (int) ($index + 201) ?>,'<?= htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES, 'UTF-8') ?>',<?= (float) ($product['price'] ?? 0) ?>,'<?= htmlspecialchars($product['image_url'] ?: 'https://images.unsplash.com/photo-1512499617640-c2f999018b72', ENT_QUOTES, 'UTF-8') ?>')"
        >
          Add to Cart
        </button>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="products" id="new-arrivals">
  <h3>Recommended for you</h3>
  <p class="subtitle">
    A mix of products picked to match your taste.
  </p>

  <div class="featured-grid">
    <?php foreach ($forYouProducts as $index => $product): ?>
      <div class="card featured-card">
        <button
          type="button"
          class="wishlist-btn"
          data-id="<?= (int) ($index + 301) ?>"
          onclick="toggleFavorite(
            <?= (int) ($index + 301) ?>,
            '<?= htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES, 'UTF-8') ?>',
            '<?= htmlspecialchars($product['image_url'] ?: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff', ENT_QUOTES, 'UTF-8') ?>'
          )"
        >
          ♡
        </button>
        <img src="<?= htmlspecialchars($product['image_url'] ?: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff', ENT_QUOTES, 'UTF-8') ?>">
        <h4><?= htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES, 'UTF-8') ?></h4>
        <p class="price">
          $<?= htmlspecialchars($product['price'] ?? '0', ENT_QUOTES, 'UTF-8') ?>
        </p>
        <button
          onclick="addToCart(
            <?= (int) ($index + 301) ?>,
            '<?= htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES, 'UTF-8') ?>',
            <?= (float) ($product['price'] ?? 0) ?>,
            '<?= htmlspecialchars($product['image_url'] ?: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff', ENT_QUOTES, 'UTF-8') ?>'
          )"
        >
          Add to Cart
        </button>
      </div>
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

