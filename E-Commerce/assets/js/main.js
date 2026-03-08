let cart = [];
let total = 0;
let favorites = [];
let recentlyViewed = [];

// Initialize cart from localStorage on load
function toggleNavMenu() {
  const nav = document.getElementById("navCategories");
  if (nav) nav.classList.toggle("nav-categories--open");
}

document.addEventListener("DOMContentLoaded", () => {
  try {
    const saved = localStorage.getItem("story_cart");
    if (saved) {
      cart = JSON.parse(saved);
    }
  } catch (e) {
    cart = [];
  }
  renderCart();
  loadFavorites();
  loadRecentlyViewed();

  const checkoutSummary = document.getElementById("checkoutCartSummary");
  if (checkoutSummary) {
    renderCheckoutSummary();
    initCheckoutPayment();
    const submitBtn = document.getElementById("checkoutSubmit");
    if (submitBtn) submitBtn.disabled = cart.length === 0;
  }

  initHeroSlider();
  initFlashCountdown();
  initAuthModal();
  applyWishlistState();
  renderWishlistPreview();
  renderWishlist();
  renderRecentlyViewed();

  // Event delegation for product card wishlist buttons (data-name = product card)
  document.addEventListener("click", (e) => {
    const btn = e.target.closest(".wishlist-btn[data-name]");
    if (!btn) return;
    e.preventDefault();
    const id = parseInt(btn.dataset.id, 10);
    const name = btn.dataset.name || "Product";
    const image = btn.dataset.image || "";
    if (id) toggleFavorite(id, name, image);
  });
});

function loadFavorites() {
  try {
    const saved = localStorage.getItem("story_favorites");
    if (saved) {
      favorites = JSON.parse(saved);
      // ensure structure
      favorites = favorites.map(f => ({
        id: f.id,
        name: f.name,
        imageUrl: f.imageUrl || null,
        price: typeof f.price === "number" ? f.price : null,
        seller: f.seller || "STORY Partner",
        shipping: f.shipping || "Free shipping",
        qty: typeof f.qty === "number" ? f.qty : 1,
      }));
    }
  } catch (e) {
    favorites = [];
  }
}

function saveFavorites() {
  try {
    localStorage.setItem("story_favorites", JSON.stringify(favorites));
  } catch (e) {}
}

function loadRecentlyViewed() {
  try {
    const saved = localStorage.getItem("story_recent");
    if (saved) {
      recentlyViewed = JSON.parse(saved);
    }
  } catch (e) {
    recentlyViewed = [];
  }
}

function saveRecentlyViewed() {
  try {
    localStorage.setItem("story_recent", JSON.stringify(recentlyViewed));
  } catch (e) {}
}

function addRecentlyViewed(item) {
  if (!item || !item.id) return;
  recentlyViewed = recentlyViewed.filter(p => p.id !== item.id);
  recentlyViewed.unshift({
    id: item.id,
    name: item.name || "Product",
    imageUrl: item.imageUrl || null,
    price: typeof item.price === "number" ? item.price : null,
  });
  if (recentlyViewed.length > 12) {
    recentlyViewed = recentlyViewed.slice(0, 12);
  }
  saveRecentlyViewed();
  renderRecentlyViewed();
}

function renderRecentlyViewed() {
  const container = document.getElementById("recentlyViewed");
  if (!container) return;
  container.innerHTML = "";

  if (!recentlyViewed.length) {
    container.innerHTML = "<p style=\"font-size:13px;color:#6b7280;\">You have not viewed any products yet.</p>";
    return;
  }

  const row = document.createElement("div");
  row.className = "carousel-row";

  recentlyViewed.forEach((p, idx) => {
    const safeImage = p.imageUrl || "https://images.unsplash.com/photo-1542291026-7eec264c27ff";
    const priceDisplay = typeof p.price === "number" ? `$${p.price}` : "";
    const id = p.id;
    const card = document.createElement("div");
    card.className = "card featured-card";
    card.innerHTML = `
      <button
        type="button"
        class="wishlist-btn ${isFavorite(id) ? "wishlist-btn--active" : ""}"
        data-id="${id}"
        onclick="toggleFavorite(${id}, '${(p.name || "").replace(/'/g, "\\'")}', '${safeImage}')"
      >
        ${isFavorite(id) ? "♥" : "♡"}
      </button>
      <img src="${safeImage}" alt="${p.name || "Product"}">
      <h4>${p.name || "Product"}</h4>
      ${priceDisplay ? `<p class="price">${priceDisplay}</p>` : ""}
      <button onclick="addToCart(${id}, '${(p.name || "").replace(/'/g, "\\'")}', ${p.price || 0}, '${safeImage}')">
        Add to Cart
      </button>
    `;
    row.appendChild(card);
  });

  container.appendChild(row);
}

function isFavorite(id) {
  return favorites.some(f => f.id === id);
}

function applyWishlistState() {
  const buttons = document.querySelectorAll(".wishlist-btn");
  buttons.forEach(btn => {
    const id = parseInt(btn.dataset.id, 10);
    if (!id) return;
    const active = isFavorite(id);
    btn.classList.toggle("wishlist-btn--active", active);
    btn.textContent = active ? "♥" : "♡";
  });
}

function toggleFavorite(id, name, imageUrl) {
  if (isFavorite(id)) {
    favorites = favorites.filter(f => f.id !== id);
  } else {
    favorites.push({
      id,
      name,
      imageUrl: imageUrl || null,
      price: null,
      seller: "STORY Partner",
      shipping: "Free shipping",
      qty: 1,
    });
  }
  saveFavorites();

  const buttons = document.querySelectorAll(`.wishlist-btn[data-id="${id}"]`);
  buttons.forEach(btn => {
    const active = isFavorite(id);
    btn.classList.toggle("wishlist-btn--active", active);
    btn.textContent = active ? "♥" : "♡";
  });

  renderWishlist();
  renderWishlistPreview();
}

function renderWishlist() {
  const container = document.getElementById("wishlistContainer");
  const countEl = document.getElementById("wishlistCount");
  if (!container) return;

  if (countEl) {
    countEl.textContent = favorites.length ? ` (${favorites.length} ${favorites.length === 1 ? "item" : "items"})` : "";
  }

  container.innerHTML = "";

  if (!favorites.length) {
    container.innerHTML = `
      <div class="wishlist-empty">
        <div class="wishlist-empty-icon">♡</div>
        <h2 class="wishlist-empty-title">Your wishlist is empty</h2>
        <p class="wishlist-empty-text">Save items you like by clicking the heart icon on product cards.</p>
        <a href="products.php" class="wishlist-empty-btn">Explore Products</a>
      </div>
    `;
    return;
  }

  const grid = document.createElement("div");
  grid.className = "wishlist-grid";

  favorites.forEach(f => {
    const safeImage = (f.imageUrl || "https://images.unsplash.com/photo-1542291026-7eec264c27ff").replace(/'/g, "\\'");
    const safeName = (f.name || "Product").replace(/'/g, "\\'");
    const priceDisplay = typeof f.price === "number" ? f.price : 0;
    const card = document.createElement("div");
    card.className = "wishlist-card";
    card.innerHTML = `
      <a href="product_detail.php?name=${encodeURIComponent(f.name || "Product")}" class="wishlist-card-image-wrap">
        <img src="${safeImage}" alt="${safeName}" loading="lazy">
        <span class="wishlist-card-badge">Saved</span>
      </a>
      <div class="wishlist-card-body">
        <a href="product_detail.php?name=${encodeURIComponent(f.name || "Product")}" class="wishlist-card-name">${safeName}</a>
        <p class="wishlist-card-price">$${priceDisplay.toFixed(2)}</p>
        <p class="wishlist-card-meta">${f.seller || "STORY Partner"} · ${f.shipping || "Free shipping"}</p>
        <div class="wishlist-card-actions">
          <button type="button" class="wishlist-card-add" onclick="addFavoriteToCart(${f.id})">Add to Cart</button>
          <button type="button" class="wishlist-card-remove" onclick="wishlistRemove(${f.id})">Remove from Wishlist</button>
        </div>
      </div>
    `;
    grid.appendChild(card);
  });

  container.appendChild(grid);
}

function renderWishlistPreview() {
  const container = document.getElementById("wishlistPreview");
  if (!container) return;

  container.innerHTML = "";

  if (!favorites.length) {
    container.innerHTML = "<p style=\"font-size:13px;color:#6b7280;\">You have no favourites yet.</p>";
    return;
  }

  const preview = favorites.slice(0, 4);
  const grid = document.createElement("div");
  grid.className = "featured-grid";

  preview.forEach(f => {
    const safeImage = f.imageUrl || "https://images.unsplash.com/photo-1542291026-7eec264c27ff";
    const card = document.createElement("div");
    card.className = "card featured-card";
    card.innerHTML = `
      <button
        type="button"
        class="wishlist-btn ${isFavorite(f.id) ? "wishlist-btn--active" : ""}"
        data-id="${f.id}"
        onclick="toggleFavorite(${f.id}, '${(f.name || "").replace(/'/g, "\\'")}', '${safeImage}')"
      >
        ${isFavorite(f.id) ? "♥" : "♡"}
      </button>
      <img src="${safeImage}" alt="${f.name || "Product"}">
      <h4>${f.name || "Product"}</h4>
      <button
        class="btn-full-width"
        style="margin-top:8px;"
        onclick="addFavoriteToCart(${f.id})"
      >
        Add to Cart
      </button>
    `;
    grid.appendChild(card);
  });

  container.appendChild(grid);
}

function wishlistChangeQty(id, delta) {
  const fav = favorites.find(f => f.id === id);
  if (!fav) return;
  fav.qty = (fav.qty || 1) + delta;
  if (fav.qty <= 0) {
    favorites = favorites.filter(f => f.id !== id);
  }
  saveFavorites();
  renderWishlist();
}

function wishlistRemove(id) {
  favorites = favorites.filter(f => f.id !== id);
  saveFavorites();
  renderWishlist();
}

function addFavoriteToCart(id) {
  const fav = favorites.find(f => f.id === id);
  if (!fav) return;
  const qty = fav.qty || 1;
  for (let i = 0; i < qty; i++) {
    addToCart(fav.id, fav.name || "Product", fav.price || 0, fav.imageUrl, fav.seller, fav.shipping);
  }
}

function initHeroSlider() {
  const slider = document.querySelector(".hero-slider");
  const slides = document.querySelectorAll(".hero-slide");
  const dotsContainer = document.getElementById("heroDots");
  if (!slides.length || !slider) return;

  let current = 0;
  let autoplayTimer = null;

  function showSlide(index) {
    current = (index + slides.length) % slides.length;
    slides.forEach((slide, i) => {
      slide.classList.toggle("hero-slide--active", i === current);
    });
    if (dotsContainer) {
      const dots = dotsContainer.querySelectorAll(".hero-dot");
      dots.forEach((dot, i) => {
        dot.classList.toggle("hero-dot--active", i === current);
      });
    }
  }

  function nextSlide() {
    showSlide(current + 1);
  }

  function prevSlide() {
    showSlide(current - 1);
  }

  function startAutoplay() {
    if (autoplayTimer) clearInterval(autoplayTimer);
    autoplayTimer = setInterval(nextSlide, 6000);
  }

  // Build dots dynamically (supports any number of slides)
  if (dotsContainer) {
    dotsContainer.innerHTML = "";
    slides.forEach((_, i) => {
      const dot = document.createElement("button");
      dot.type = "button";
      dot.className = "hero-dot" + (i === 0 ? " hero-dot--active" : "");
      dot.dataset.slide = String(i);
      dot.setAttribute("aria-label", "Go to slide " + (i + 1));
      dot.addEventListener("click", () => showSlide(i));
      dotsContainer.appendChild(dot);
    });
  }

  // Arrow navigation - use event delegation for reliability
  slider.addEventListener("click", (e) => {
    const target = e.target.closest("button");
    if (!target) return;
    if (target.classList.contains("hero-arrow--prev")) {
      e.preventDefault();
      prevSlide();
      startAutoplay();
    } else if (target.classList.contains("hero-arrow--next")) {
      e.preventDefault();
      nextSlide();
      startAutoplay();
    }
  });

  startAutoplay();
}

function initFlashCountdown() {
  const timerEl = document.querySelector(".flash-timer");
  if (!timerEl) return;

  const hours = parseInt(timerEl.dataset.countdownHours || "6", 10);
  const endTime = Date.now() + hours * 60 * 60 * 1000;
  const displayEl = timerEl.querySelector(".flash-time");
  if (!displayEl) return;

  function update() {
    const diff = endTime - Date.now();
    if (diff <= 0) {
      displayEl.textContent = "00:00:00";
      return;
    }
    const totalSeconds = Math.floor(diff / 1000);
    const h = String(Math.floor(totalSeconds / 3600)).padStart(2, "0");
    const m = String(Math.floor((totalSeconds % 3600) / 60)).padStart(2, "0");
    const s = String(totalSeconds % 60).padStart(2, "0");
    displayEl.textContent = `${h}:${m}:${s}`;
    requestAnimationFrame(() => {
      setTimeout(update, 1000);
    });
  }

  update();
}

function saveCart() {
  try {
    localStorage.setItem("story_cart", JSON.stringify(cart));
  } catch (e) {
    // ignore
  }
}

function toggleCart() {
  const cartEl = document.getElementById("cart");
  const backdrop = document.getElementById("cartBackdrop");
  if (!cartEl) return;
  cartEl.classList.toggle("active");
  if (backdrop) {
    backdrop.classList.toggle("active");
  }
}

function addToCart(id, name, price, imageUrl, seller, shipping, extra, saving) {
  const existing = cart.find(item => item.id === id);

  if (existing) {
    existing.qty++;
  } else {
    cart.push({
      id,
      name,
      price,
      imageUrl: imageUrl || null,
      seller: seller || "CERCEVECI",
      shipping: shipping || "Free shipping",
      extra: extra || "Popular choice · Ships in 2 days",
      saving: saving || 0,
      qty: 1
    });
  }

  renderCart();
  saveCart();
}

function toggleChat() {
  const chat = document.getElementById("chatbot");
  if (!chat) return;
  chat.classList.toggle("active");
}

function sendMessage() {
  const input = document.getElementById("userInput");
  if (!input) return;

  const message = input.value.trim();
  if (message === "") return;

  const chatBody = document.getElementById("chatBody");
  if (!chatBody) return;

  const userMsg = document.createElement("div");
  userMsg.className = "msg user";
  userMsg.innerText = message;
  chatBody.appendChild(userMsg);

  input.value = "";

  const payload = {
    message,
    cart,
    page: window.location.pathname
  };

  fetch("/chatbotv2/E-Commerce/chatbot.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload)
  })
    .then(res => res.json())
    .then(data => {
      const botMsg = document.createElement("div");
      botMsg.className = "msg bot";
      botMsg.innerText = data.reply ?? "Sorry, I didn’t understand.";
      chatBody.appendChild(botMsg);
      chatBody.scrollTop = chatBody.scrollHeight;
    })
    .catch(() => {
      const errMsg = document.createElement("div");
      errMsg.className = "msg bot";
      errMsg.innerText = "Server error.";
      chatBody.appendChild(errMsg);
    });
}

function renderCart() {
  const cartItems = document.getElementById("cartItems");
  const countEl = document.getElementById("cartCount");
  if (!cartItems) return;

  cartItems.innerHTML = "";
  total = 0;
  let count = 0;

  cart.forEach(item => {
    total += item.price * item.qty;
    count += item.qty;

    const div = document.createElement("div");
    div.className = "cart-item";
    div.innerHTML = `
      <div class="cart-item-image">
        ${item.imageUrl ? `<img src="${item.imageUrl}" alt="${item.name}">` : ""}
      </div>
      <div class="cart-item-info">
        <strong>${item.name}</strong>
        <div class="cart-meta">
          <span class="cart-seller">${item.seller || "STORY Partner"}</span>
          <span class="cart-shipping">${item.shipping || "Free shipping"}</span>
        </div>
        ${item.extra ? `<div class="cart-extra">${item.extra}</div>` : ""}
        <div class="cart-line">
          <div class="cart-qty-buttons">
            <button onclick="changeQty(${item.id}, -1)"><span class="qty-icon">−</span></button>
            <span>${item.qty}</span>
            <button onclick="changeQty(${item.id}, 1)"><span class="qty-icon">+</span></button>
          </div>
          <span class="cart-line-price">$${item.price * item.qty}</span>
        </div>
        ${item.saving ? `<div class="cart-saving">You save $${item.saving}</div>` : ""}
        <button class="cart-remove" onclick="removeFromCart(${item.id})">Remove</button>
      </div>
    `;
    cartItems.appendChild(div);
  });

  const totalEl = document.getElementById("total");
  if (totalEl) {
    totalEl.innerText = total;
  }

  if (countEl) {
    countEl.innerText = count;
  }
}

function changeQty(id, delta) {
  const item = cart.find(i => i.id === id);
  if (!item) return;
  item.qty += delta;
  if (item.qty <= 0) {
    cart = cart.filter(i => i.id !== id);
  }
  renderCart();
  saveCart();
  const checkoutSummary = document.getElementById("checkoutCartSummary");
  if (checkoutSummary) {
    renderCheckoutSummary();
  }
}

function removeFromCart(id) {
  cart = cart.filter(i => i.id !== id);
  renderCart();
  saveCart();
  const checkoutSummary = document.getElementById("checkoutCartSummary");
  if (checkoutSummary) {
    renderCheckoutSummary();
  }
}

function renderCheckoutSummary() {
  const container = document.getElementById("checkoutCartSummary");
  if (!container) return;

  container.innerHTML = "";

  if (cart.length === 0) {
    container.innerHTML = '<div class="checkout-empty">Your cart is empty. <a href="products.php">Continue shopping</a></div>';
    const submitBtn = document.getElementById("checkoutSubmit");
    if (submitBtn) submitBtn.disabled = true;
    return;
  }

  const itemsWrap = document.createElement("div");
  itemsWrap.className = "checkout-summary-items";
  let subtotal = 0;
  let shippingTotal = 0;
  let discountTotal = 0;

  cart.forEach(item => {
    const line = item.price * item.qty;
    subtotal += line;
    const imgUrl = item.imageUrl || "https://images.unsplash.com/photo-1542291026-7eec264c27ff";
    const name = item.name || "Product";

    const row = document.createElement("div");
    row.className = "checkout-summary-item";
    row.innerHTML = `
      <img class="checkout-summary-item-img" src="${imgUrl}" alt="${name}">
      <div class="checkout-summary-item-info">
        <p class="checkout-summary-item-name">${name}</p>
        <div class="checkout-summary-item-meta">
          <span class="checkout-summary-item-price-unit">$${item.price.toFixed(2)} each</span>
          <div class="checkout-summary-item-qty-controls">
            <button type="button" onclick="changeQty(${item.id}, -1)">−</button>
            <span>${item.qty}</span>
            <button type="button" onclick="changeQty(${item.id}, 1)">+</button>
          </div>
        </div>
      </div>
      <div class="checkout-summary-item-side">
        <span class="checkout-summary-item-line">$${line.toFixed(2)}</span>
        <button type="button" class="checkout-summary-item-remove" onclick="removeFromCart(${item.id})">🗑 Remove</button>
      </div>
    `;
    itemsWrap.appendChild(row);
  });

  const summary = document.createElement("div");
  summary.className = "checkout-summary-totals";
  const shippingDisplay = shippingTotal === 0 ? "Free" : `$${shippingTotal.toFixed(2)}`;
  const grandTotal = subtotal + shippingTotal - discountTotal;

  summary.innerHTML = `
    <div class="checkout-line">
      <span>Subtotal</span>
      <span>$${subtotal.toFixed(2)}</span>
    </div>
    <div class="checkout-line">
      <span>Shipping</span>
      <span>${shippingDisplay}</span>
    </div>
    <div class="checkout-line">
      <span>Discount</span>
      <span>${discountTotal > 0 ? "-$" + discountTotal.toFixed(2) : "—"}</span>
    </div>
    <div class="checkout-line checkout-line-total">
      <span>Total</span>
      <span>$${grandTotal.toFixed(2)}</span>
    </div>
  `;

  container.appendChild(itemsWrap);
  container.appendChild(summary);

  const submitBtn = document.getElementById("checkoutSubmit");
  if (submitBtn) submitBtn.disabled = cart.length === 0;
}

function initCheckoutPayment() {
  const form = document.getElementById("checkoutForm");
  const paymentFields = document.getElementById("paymentFields");
  const paymentRadios = form && form.querySelectorAll('input[name="payment"]');
  const paymentMethods = form && form.querySelectorAll(".payment-method");

  if (!form || !paymentRadios.length) return;

  function updatePaymentUI() {
    const selected = form.querySelector('input[name="payment"]:checked');
    const isCard = selected && selected.value === "card";
    if (paymentFields) paymentFields.style.display = isCard ? "" : "none";
    paymentMethods.forEach((el) => el.classList.toggle("payment-method--active", el.querySelector("input") === selected));
  }

  paymentRadios.forEach((r) => r.addEventListener("change", updatePaymentUI));
  updatePaymentUI();
}

function goToCheckout() {
  if (cart.length === 0) {
    alert("Cart is empty");
    return;
  }
  saveCart();
  window.location.href = "/chatbotv2/E-Commerce/checkout.php";
}

function checkout() {
  if (cart.length === 0) {
    alert("Cart is empty");
    return;
  }

  const btn = document.getElementById("checkoutSubmit");
  if (btn) {
    btn.disabled = true;
    btn.textContent = "Processing...";
  }

  fetch("/chatbotv2/E-Commerce/checkout.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ cart })
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        cart = [];
        renderCart();
        saveCart();
        window.location.href = "orders.php?order_id=" + data.order_id;
      } else {
        alert("Checkout failed: " + (data.error || "Unknown error"));
        if (btn) { btn.disabled = false; btn.textContent = "Complete Purchase"; }
      }
    })
    .catch(() => {
      alert("Network error. Please try again.");
      if (btn) { btn.disabled = false; btn.textContent = "Complete Purchase"; }
    });
}

function toggleOrderSection(id, type) {
  const panel = document.getElementById(`order-${type}-${id}`);
  if (!panel) return;
  panel.classList.toggle("order-panel--open");
}

function toggleOrderDetails(id) {
  toggleOrderSection(id, "details");
}

function toggleOrderTracking(id) {
  toggleOrderSection(id, "tracking");
}

/* Auth Modal */
function toggleAuthModal() {
  const modal = document.getElementById("authModal");
  if (!modal) return;
  const isOpen = modal.classList.toggle("active");
  document.body.style.overflow = isOpen ? "hidden" : "";
  modal.setAttribute("aria-hidden", !isOpen);
}

function initAuthModal() {
  const modal = document.getElementById("authModal");
  if (!modal) return;

  const tabs = modal.querySelectorAll(".auth-modal-tab");
  const forms = modal.querySelectorAll(".auth-modal-form");
  const signinForm = document.getElementById("auth-signin-form");
  const joinForm = document.getElementById("auth-join-form");

  function switchAuthTab(target) {
    const isJoin = target === "join";
    tabs.forEach((tab) => {
      tab.classList.toggle("active", tab.dataset.tab === target);
    });
    forms.forEach((form) => {
      form.classList.remove("active");
      form.classList.toggle("join-active", isJoin);
    });
    requestAnimationFrame(() => {
      const activeForm = isJoin ? joinForm : signinForm;
      if (activeForm) activeForm.classList.add("active");
    });
  }

  tabs.forEach((tab) => {
    tab.addEventListener("click", () => switchAuthTab(tab.dataset.tab));
  });

  modal.querySelectorAll(".auth-modal-link").forEach((link) => {
    link.addEventListener("click", (e) => {
      e.preventDefault();
      switchAuthTab(link.dataset.switch);
    });
  });

  if (signinForm) {
    signinForm.addEventListener("submit", (e) => {
      e.preventDefault();
      // Placeholder: add auth logic
    });
  }

  if (joinForm) {
    joinForm.addEventListener("submit", (e) => {
      e.preventDefault();
      const password = document.getElementById("auth-join-password")?.value;
      const confirm = document.getElementById("auth-join-confirm")?.value;
      if (password !== confirm) {
        alert("Passwords do not match.");
        return;
      }
      if (password && password.length < 8) {
        alert("Password must be at least 8 characters.");
        return;
      }
      // Placeholder: add registration logic
    });
  }

  const forgotLink = modal.querySelector(".auth-forgot-link");
  if (forgotLink) {
    forgotLink.addEventListener("click", (e) => {
      e.preventDefault();
      // Placeholder: forgot password
    });
  }

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && modal.classList.contains("active")) {
      toggleAuthModal();
    }
  });
}

