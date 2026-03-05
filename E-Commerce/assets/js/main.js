let cart = [];
let total = 0;
let favorites = [];
let recentlyViewed = [];

// Initialize cart from localStorage on load
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
  }

  initHeroSlider();
  initFlashCountdown();
  applyWishlistState();
  renderWishlistPreview();
  renderWishlist();
  renderRecentlyViewed();
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
}

function renderWishlist() {
  const container = document.getElementById("wishlistContainer");
  if (!container) return;

  container.innerHTML = "";

  if (!favorites.length) {
    container.innerHTML = "<p>You have no favourites yet.</p>";
    return;
  }

  const grid = document.createElement("div");
  grid.className = "featured-grid";

  favorites.forEach(f => {
    const safeImage = f.imageUrl || "https://images.unsplash.com/photo-1542291026-7eec264c27ff";
    const priceDisplay = typeof f.price === "number" ? `$${f.price}` : "";
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
      ${priceDisplay ? `<p class="price">${priceDisplay}</p>` : ""}
      <p style="font-size:12px;color:#6b7280;margin:4px 0 8px;">
        ${f.seller || "STORY Partner"} · ${f.shipping || "Free shipping"}
      </p>
      <div class="cart-line">
        <div class="cart-qty-buttons">
          <button onclick="wishlistChangeQty(${f.id}, -1)"><span class="qty-icon">−</span></button>
          <span>${f.qty || 1}</span>
          <button onclick="wishlistChangeQty(${f.id}, 1)"><span class="qty-icon">+</span></button>
        </div>
        ${priceDisplay ? `<span class="cart-line-price">$${(f.price || 0) * (f.qty || 1)}</span>` : ""}
      </div>
      <button
        class="btn-full-width"
        style="margin-top:8px;"
        onclick="addFavoriteToCart(${f.id})"
      >
        Add to Cart
      </button>
      <button
        type="button"
        class="cart-remove"
        style="margin-top:4px;"
        onclick="wishlistRemove(${f.id})"
      >
        Remove
      </button>
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
  const slides = document.querySelectorAll(".hero-slide");
  const dots = document.querySelectorAll(".hero-dot");
  if (!slides.length || !dots.length) return;

  let current = 0;

  function showSlide(index) {
    current = (index + slides.length) % slides.length;
    slides.forEach((slide, i) => {
      slide.classList.toggle("hero-slide--active", i === current);
    });
    dots.forEach((dot, i) => {
      dot.classList.toggle("hero-dot--active", i === current);
    });
  }

  dots.forEach((dot, index) => {
    dot.addEventListener("click", () => showSlide(index));
  });

  setInterval(() => {
    showSlide(current + 1);
  }, 6000);
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
    container.innerHTML = "<p>Your cart is empty.</p>";
    return;
  }

  const list = document.createElement("div");
  let subtotal = 0;
  let shippingTotal = 0;

  cart.forEach(item => {
    const line = item.price * item.qty;
    subtotal += line;

    const safeName = (item.name || "Product").replace(/'/g, "\\'");
    const safeImage = (item.imageUrl || "https://images.unsplash.com/photo-1542291026-7eec264c27ff").replace(/'/g, "\\'");
    const favActive = isFavorite(item.id);

    const row = document.createElement("div");
    row.className = "cart-item";
    row.innerHTML = `
      <div class="cart-item-image">
        ${item.imageUrl ? `<img src="${item.imageUrl}" alt="${item.name}">` : ""}
      </div>
      <div class="cart-item-info">
        <div style="display:flex;align-items:center;gap:6px;justify-content:space-between;">
          <strong>${item.name}</strong>
          <button
            type="button"
            class="wishlist-btn checkout-wishlist-btn ${favActive ? "wishlist-btn--active" : ""}"
            data-id="${item.id}"
            onclick="toggleFavorite(${item.id}, '${safeName}', '${safeImage}')"
          >
            ${favActive ? "♥" : "♡"}
          </button>
        </div>
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
          <span class="cart-line-price">$${line}</span>
        </div>
        ${item.saving ? `<div class="cart-saving">You save $${item.saving}</div>` : ""}
        <button class="cart-remove" onclick="removeFromCart(${item.id})">Remove</button>
      </div>
    `;
    list.appendChild(row);
  });

  const summary = document.createElement("div");
  summary.className = "checkout-summary-totals";
  const shippingDisplay = shippingTotal === 0 ? "Free" : `$${shippingTotal}`;
  const grandTotal = subtotal + shippingTotal;

  summary.innerHTML = `
    <div class="checkout-line">
      <span>Subtotal</span>
      <span>$${subtotal}</span>
    </div>
    <div class="checkout-line">
      <span>Shipping</span>
      <span>${shippingDisplay}</span>
    </div>
    <div class="checkout-line checkout-line-total">
      <span>Total</span>
      <span>$${grandTotal}</span>
    </div>
  `;

  container.appendChild(list);
  container.appendChild(summary);
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

  fetch("/chatbotv2/E-Commerce/checkout.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ cart })
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        alert("Order saved! ID: " + data.order_id);
        cart = [];
        renderCart();
        saveCart();
      } else {
        alert("Checkout failed: " + data.error);
        console.log(data);
      }
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

