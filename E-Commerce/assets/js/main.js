let cart = [];
let total = 0;
let favorites = [];
let recentlyViewed = [];
let lastChatIntent = "";
let lastUserMessage = "";
let lastDidYouMeanShownAt = 0;
let botReplyCountSinceDidYouMean = 0;
let lastRenderedCartCount = 0;
const CHAT_QUICK_ACTIONS_DEFAULT = [
  { en: "🛍️ Recommend me a product", tr: "🛍️ Bana ürün öner" },
  { en: "📦 Track my order", tr: "📦 Siparişimi takip et" },
  { en: "💳 Payment options", tr: "💳 Ödeme seçenekleri" },
  { en: "🔁 How do returns work?", tr: "🔁 İade nasıl yapılır?" }
];
const CHAT_QUICK_ACTIONS_PRODUCT = [
  { en: "Wireless only", tr: "Kablosuz olsun" },
  { en: "Cheaper alternatives", tr: "Daha ucuz alternatif" },
  { en: "Best sellers", tr: "En çok satanlar" },
  { en: "Under 500 TL", tr: "500 TL altı" }
];
const CHAT_QUICK_ACTIONS_ORDER = [
  { en: "Where is my shipment?", tr: "Kargo nerede?" },
  { en: "Delivery time", tr: "Teslimat süresi" },
  { en: "Cancel my order", tr: "Siparişi iptal et" }
];
const CHAT_QUICK_ACTIONS_PAYMENT = [
  { en: "Payment options", tr: "Ödeme seçenekleri" },
  { en: "Do you offer installments?", tr: "Taksit yapılıyor mu?" }
];
const CHAT_QUICK_ACTIONS_RETURNS_SHIPPING = [
  { en: "How do returns work?", tr: "İade nasıl yapılır?" },
  { en: "Shipping time", tr: "Kargo süresi" }
];

function appUrl(path) {
  const url = new URL(path, window.location.href);
  const lang = typeof window.APP_LANG === "string" ? window.APP_LANG : "";
  if (lang && !url.searchParams.get("lang")) {
    url.searchParams.set("lang", lang);
  }
  return url.toString();
}

function uiText(en, tr) {
  const lang = (typeof window.APP_LANG === "string" ? window.APP_LANG : "en").toLowerCase();
  return lang === "tr" ? tr : en;
}

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
  hydrateFavoritePrices();
  hydrateFavoritePricesFromServer();

  const checkoutSummary = document.getElementById("checkoutCartSummary");
  if (checkoutSummary) {
    renderCheckoutSummary();
    initCheckoutPayment();
    initCheckoutPhoneField();
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
  renderChatQuickPrompts();

  const chatInput = document.getElementById("userInput");
  if (chatInput) {
    chatInput.addEventListener("input", onUserTyping);
    chatInput.addEventListener("keydown", (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
        sendMessage();
      }
    });
  }

  // Event delegation for product card wishlist buttons (data-name = product card)
  document.addEventListener("click", (e) => {
    const btn = e.target.closest(".wishlist-btn[data-name]");
    if (!btn) return;
    e.preventDefault();
    const id = parseInt(btn.dataset.id, 10);
    const name = btn.dataset.name || "Product";
    const image = btn.dataset.image || "";
    const parsed = parseFloat(btn.dataset.price || "");
    const price = Number.isFinite(parsed) ? parsed : null;
    const stockQuantity = parseInt(btn.dataset.stock || "0", 10);
    if (id) toggleFavorite(id, name, image, price, stockQuantity);
  });

});

function renderChatQuickPrompts(force = false) {
  const container = document.getElementById("quick-actions");
  if (!container) return;
  if (!force && container.childElementCount > 0) return;
  container.innerHTML = "";
  const actions = getContextAwareQuickPrompts().slice(0, 5).map(localizeQuickAction);
  actions.forEach((text) => {
    const chip = document.createElement("button");
    chip.type = "button";
    chip.className = "qa-chip";
    chip.textContent = text;
    chip.onclick = () => sendQuickPrompt(text);
    container.appendChild(chip);
  });
  container.style.opacity = "1";
}

function getContextAwareQuickPrompts() {
  const msg = String(lastUserMessage || "").toLowerCase();
  if (lastChatIntent === "product_search") return CHAT_QUICK_ACTIONS_PRODUCT;
  if (lastChatIntent === "order_status" || msg.includes("order") || msg.includes("sipariş")) return CHAT_QUICK_ACTIONS_ORDER;
  if (lastChatIntent === "payment" || msg.includes("payment") || msg.includes("ödeme")) return CHAT_QUICK_ACTIONS_PAYMENT;
  if (lastChatIntent === "returns" || lastChatIntent === "shipping" || msg.includes("return") || msg.includes("kargo")) return CHAT_QUICK_ACTIONS_RETURNS_SHIPPING;
  return CHAT_QUICK_ACTIONS_DEFAULT;
}

function localizeQuickAction(item) {
  if (typeof item === "string") return item;
  const lang = (typeof window.APP_LANG === "string" ? window.APP_LANG : "en").toLowerCase();
  if (lang === "tr") return item.tr || item.en || "";
  return item.en || item.tr || "";
}

function sendQuickPrompt(text) {
  const input = document.getElementById("userInput");
  if (input) {
    input.value = text;
  }
  sendChatMessage(text);
}

function onUserTyping() {
  const container = document.getElementById("quick-actions");
  if (!container) return;
  container.style.opacity = "0.35";
}

function renderDidYouMean(chatBody, suggestions) {
  if (!chatBody || !Array.isArray(suggestions) || !suggestions.length) return;
  const now = Date.now();
  const minIntervalMs = 90000; // at most once every 90s
  const minBotRepliesBetween = 3; // and keep at least 3 bot replies between prompts
  if ((now - lastDidYouMeanShownAt) < minIntervalMs) return;
  if (botReplyCountSinceDidYouMean < minBotRepliesBetween) return;

  const wrap = document.createElement("div");
  wrap.className = "msg bot chat-didyoumean";

  const title = document.createElement("div");
  title.className = "chat-didyoumean-title";
  title.textContent = "Bunu mu demek istediniz?";
  wrap.appendChild(title);

  const chips = document.createElement("div");
  chips.className = "chat-didyoumean-chips";
  suggestions.slice(0, 4).forEach((text) => {
    const chip = document.createElement("button");
    chip.type = "button";
    chip.className = "chat-didyoumean-chip";
    chip.textContent = text;
    chip.onclick = () => sendQuickPrompt(text);
    chips.appendChild(chip);
  });
  wrap.appendChild(chips);
  chatBody.appendChild(wrap);
  lastDidYouMeanShownAt = now;
  botReplyCountSinceDidYouMean = 0;
}

function renderChatFeedbackControls(chatBody, botMsg, payload) {
  if (!chatBody || !botMsg) return;
  const wrap = document.createElement("div");
  wrap.className = "chat-feedback";

  const label = document.createElement("span");
  label.className = "chat-feedback-label";
  label.textContent = "Was this helpful?";
  wrap.appendChild(label);

  const yesBtn = document.createElement("button");
  yesBtn.type = "button";
  yesBtn.className = "chat-feedback-btn";
  yesBtn.textContent = "👍";

  const noBtn = document.createElement("button");
  noBtn.type = "button";
  noBtn.className = "chat-feedback-btn";
  noBtn.textContent = "👎";

  const lockButtons = (activeBtn) => {
    [yesBtn, noBtn].forEach((btn) => {
      btn.disabled = true;
      if (btn === activeBtn) btn.classList.add("chat-feedback-btn--active");
    });
  };

  yesBtn.onclick = () => submitChatFeedback(true, payload, lockButtons, yesBtn, wrap);
  noBtn.onclick = () => submitChatFeedback(false, payload, lockButtons, noBtn, wrap);

  wrap.appendChild(yesBtn);
  wrap.appendChild(noBtn);
  chatBody.appendChild(wrap);
}

function submitChatFeedback(isHelpful, payload, lockButtons, activeBtn, wrapEl) {
  lockButtons(activeBtn);
  fetch(appUrl("chatbot_feedback.php"), {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      action: "submit",
      helpful: isHelpful ? 1 : 0,
      intent: payload.intent || "general",
      source: payload.source || "rule",
      confidence: typeof payload.confidence === "number" ? payload.confidence : Number(payload.confidence || 0),
      used_ai: !!payload.used_ai,
      escalated_to_human: !!payload.escalated_to_human,
      user_message: payload.user_message || "",
      bot_reply: payload.bot_reply || "",
      page: window.location.pathname || ""
    })
  })
    .then(() => {
      if (wrapEl) {
        const thanks = document.createElement("span");
        thanks.className = "chat-feedback-thanks";
        thanks.textContent = "Thanks for your feedback!";
        wrapEl.appendChild(thanks);
      }
    })
    .catch(() => {
      if (wrapEl) {
        const error = document.createElement("span");
        error.className = "chat-feedback-thanks";
        error.textContent = "Could not save feedback.";
        wrapEl.appendChild(error);
      }
    });
}

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
        price: Number.isFinite(Number(f.price)) ? Number(f.price) : null,
        stockQuantity: Number.isFinite(Number(f.stockQuantity)) ? Number(f.stockQuantity) : 1,
        seller: f.seller || "ZERA Partner",
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
    stockQuantity: typeof item.stockQuantity === "number" ? item.stockQuantity : 1
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
    const stockQuantity =
      typeof p.stockQuantity === "number" ? p.stockQuantity : 1;
    const inStock = stockQuantity > 0;
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
      <button
        ${inStock ? "" : "disabled"}
        class="${inStock ? "" : "product-card-add--disabled"}"
        onclick="${
          inStock
            ? `addToCart(${id}, '${(p.name || "").replace(/'/g, "\\'")}', ${p.price || 0}, '${safeImage}')`
            : "return false;"
        }"
      >
        ${inStock ? "Add to Cart" : "Out of Stock"}
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

function getFavoritePriceFromDom(id) {
  const buttons = document.querySelectorAll(`.wishlist-btn[data-id="${id}"]`);
  if (!buttons.length) return null;

  for (const btn of buttons) {
    const dataPrice = parseFloat(btn.dataset.price || "");
    if (Number.isFinite(dataPrice) && dataPrice > 0) return dataPrice;

    const card = btn.closest(".product-card, .card, .wishlist-card, .cart-item, .checkout-summary-item");
    if (!card) continue;
    const priceEl = card.querySelector(".product-card-price, .price, .wishlist-card-price, .cart-line-price, .checkout-summary-item-line");
    if (!priceEl) continue;
    const numeric = parseFloat((priceEl.textContent || "").replace(/[^0-9.]/g, ""));
    if (Number.isFinite(numeric) && numeric > 0) return numeric;
  }

  return null;
}

function getFavoritePriceFromKnownSources(id, name) {
  const domPrice = getFavoritePriceFromDom(id);
  if (typeof domPrice === "number" && domPrice > 0) return domPrice;

  const cartItem = cart.find((c) => c.id === id && typeof c.price === "number" && c.price > 0);
  if (cartItem) return cartItem.price;

  const viewed = recentlyViewed.find((p) => p.id === id && typeof p.price === "number" && p.price > 0);
  if (viewed) return viewed.price;

  if (name) {
    const byName = recentlyViewed.find((p) => p.name === name && typeof p.price === "number" && p.price > 0);
    if (byName) return byName.price;
  }

  return null;
}

function hydrateFavoritePrices() {
  let changed = false;
  favorites = favorites.map((f) => {
    if (typeof f.price === "number" && f.price > 0) return f;
    const fixedPrice = getFavoritePriceFromKnownSources(f.id, f.name);
    if (typeof fixedPrice === "number" && fixedPrice > 0) {
      changed = true;
      return { ...f, price: fixedPrice };
    }
    return f;
  });
  if (changed) {
    saveFavorites();
    renderWishlist();
    renderWishlistPreview();
  }
}

function hydrateFavoritePricesFromServer() {
  const missing = favorites
    .filter((f) => !(typeof f.price === "number" && f.price > 0) && f.name)
    .map((f) => f.name);

  if (!missing.length) return;

  fetch(appUrl("wishlist_prices.php"), {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ names: missing })
  })
    .then((res) => res.json())
    .then((data) => {
      if (!data || !data.success || !data.prices) return;
      let changed = false;
      favorites = favorites.map((f) => {
        if (typeof f.price === "number" && f.price > 0) return f;
        const p = Number(data.prices[f.name]);
        if (Number.isFinite(p) && p > 0) {
          changed = true;
          return { ...f, price: p };
        }
        return f;
      });
      if (changed) {
        saveFavorites();
        renderWishlist();
        renderWishlistPreview();
      }
    })
    .catch(() => {});
}

function toggleFavorite(id, name, imageUrl, price = null, stockQuantity = 1) {
  if (isFavorite(id)) {
    favorites = favorites.filter(f => f.id !== id);
  } else {
    const finalPrice = typeof price === "number" && !Number.isNaN(price) && price > 0
      ? price
      : getFavoritePriceFromKnownSources(id, name);
    favorites.push({
      id,
      name,
      imageUrl: imageUrl || null,
      price: typeof finalPrice === "number" ? finalPrice : 0,
      stockQuantity: typeof stockQuantity === "number" ? stockQuantity : 1,
      seller: "ZERA Partner",
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
    countEl.textContent = favorites.length
      ? ` (${favorites.length} ${favorites.length === 1 ? "item" : "items"})`
      : "";
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
    const image = f.imageUrl || "https://images.unsplash.com/photo-1542291026-7eec264c27ff";
    const name = f.name || "Product";
    const price = Number.isFinite(Number(f.price)) ? Number(f.price) : 0;
    const stockQuantity = Number.isFinite(Number(f.stockQuantity)) ? Number(f.stockQuantity) : 1;
    const inStock = stockQuantity > 0;

    const card = document.createElement("div");
    card.className = "wishlist-card";

    card.innerHTML = `
      <a href="product_detail.php?name=${encodeURIComponent(name)}" class="wishlist-card-image-wrap">
        <img src="${image}" alt="${name}" loading="lazy">
        <span class="wishlist-card-badge">Saved</span>
      </a>

      <div class="wishlist-card-body">
        <a href="product_detail.php?name=${encodeURIComponent(name)}" class="wishlist-card-name">${name}</a>
        <p class="wishlist-card-price">$${price.toFixed(2)}</p>
        <p class="wishlist-card-meta">${f.seller || "ZERA Partner"} · ${f.shipping || "Free shipping"}</p>

        <div class="wishlist-card-actions">
          <button type="button" class="wishlist-card-add ${inStock ? "" : "wishlist-card-add--disabled"}" ${inStock ? "" : "disabled"}>
            ${inStock ? "Add to Cart" : "Out of Stock"}
          </button>

          <button type="button" class="wishlist-card-remove">
            Remove from Wishlist
          </button>
        </div>
      </div>
    `;

    const addBtn = card.querySelector(".wishlist-card-add");
    if (addBtn && inStock) {
      addBtn.addEventListener("click", () => addFavoriteToCart(f.id));
    }

    const removeBtn = card.querySelector(".wishlist-card-remove");
    if (removeBtn) {
      removeBtn.addEventListener("click", () => wishlistRemove(f.id));
    }

    grid.appendChild(card);
  });

  container.appendChild(grid);
}

function renderWishlistPreview() {
  const container = document.getElementById("wishlistPreview");
  if (!container) return;

  container.innerHTML = "";

  if (!favorites.length) {
    container.innerHTML = `<p style="font-size:13px;color:#6b7280;">${uiText("You have no favourites yet.", "Henüz favoriniz yok.")}</p>`;
    return;
  }

  const preview = favorites.slice(0, 4);
  const grid = document.createElement("div");
  grid.className = "featured-grid";

  preview.forEach(f => {
    const safeImage = f.imageUrl || "https://images.unsplash.com/photo-1542291026-7eec264c27ff";
    const card = document.createElement("div");
    const stockQuantity =
    Number.isFinite(Number(f.stockQuantity))
    ? Number(f.stockQuantity)
    : 1;

    const inStock = stockQuantity > 0;
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
        class="btn-full-width ${inStock ? "" : "product-card-add--disabled"}"
        style="margin-top:8px;"
        ${inStock ? "" : "disabled"}
        onclick="${inStock ? `addFavoriteToCart(${f.id})` : "return false;"}"
      >
        ${inStock ? "Add to Cart" : "Out of Stock"}
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
  document.body.classList.toggle("cart-open", cartEl.classList.contains("active"));
  if (backdrop) {
    backdrop.classList.toggle("active");
  }
}

function addToCartWithSelectedSize(triggerEl, id, name, price, imageUrl, seller, shipping, extra, saving) {
  const host = triggerEl && typeof triggerEl.closest === "function" ? triggerEl.closest("[data-size-host]") : null;
  const picker = host ? host.querySelector(".product-size-picker") : null;
  const sizeSelect = host ? host.querySelector(".product-size-select") : null;
  const sizeHidden = host ? host.querySelector(".product-size-selected") : null;
  const selectedChip = host ? host.querySelector(".size-chip--pick.is-selected") : null;
  const selectedSize = sizeSelect
    ? String(sizeSelect.value || "").trim()
    : (sizeHidden ? String(sizeHidden.value || "").trim() : (selectedChip ? String(selectedChip.dataset.size || "").trim() : ""));
  if ((sizeSelect || sizeHidden || selectedChip) && !selectedSize) {
    if (picker) picker.classList.add("is-open");
    if (sizeSelect) sizeSelect.focus();
    return;
  }
  addToCart(id, name, price, imageUrl, seller, shipping, extra, saving, selectedSize);
}

function selectProductSize(triggerEl, size) {
  const host = triggerEl && typeof triggerEl.closest === "function" ? triggerEl.closest("[data-size-host]") : null;
  if (!host) return;
  host.querySelectorAll(".size-chip--pick").forEach((chip) => chip.classList.remove("is-selected"));
  triggerEl.classList.add("is-selected");
  triggerEl.dataset.size = String(size || "");
  const hidden = host.querySelector(".product-size-selected");
  if (hidden) hidden.value = String(size || "");
}

function toggleSizePicker(triggerEl) {
  const host = triggerEl && typeof triggerEl.closest === "function" ? triggerEl.closest("[data-size-host]") : null;
  if (!host) return;
  const picker = host.querySelector(".product-size-picker");
  if (!picker) return;
  picker.classList.toggle("is-open");
  if (picker.classList.contains("is-open")) {
    const sizeSelect = host.querySelector(".product-size-select");
    if (sizeSelect) sizeSelect.focus();
  }
}

function addToCart(id, name, price, imageUrl, seller, shipping, extra, saving, selectedSize) {
  const normalizedSize = typeof selectedSize === "string" ? selectedSize.trim() : "";
  const existing = cart.find(item => item.id === id && (item.size || "") === normalizedSize);

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
      size: normalizedSize,
      qty: 1
    });
  }

  renderCart();
  saveCart();
  showAddToCartFeedback(name || "Product");
}

function showAddToCartFeedback(name) {
  let toast = document.getElementById("cartToast");
  if (!toast) {
    toast = document.createElement("div");
    toast.id = "cartToast";
    toast.className = "cart-toast";
    document.body.appendChild(toast);
  }
  toast.textContent = uiText(`${name} added to cart`, `${name} sepete eklendi`);
  toast.classList.add("show");
  setTimeout(() => {
    toast.classList.remove("show");
  }, 1300);

  const cartEl = document.getElementById("cart");
  if (cartEl) {
    cartEl.classList.add("flash");
    setTimeout(() => cartEl.classList.remove("flash"), 500);
  }
}

function toggleChat() {
  const chat = document.getElementById("chatbot");
  if (!chat) return;
  chat.style.zIndex = "2147483647";
  const chatToggle = document.querySelector(".chat-toggle");
  if (chatToggle) chatToggle.style.zIndex = "2147483646";
  chat.classList.toggle("active");
}

function sendMessage() {
  const input = document.getElementById("userInput");
  if (!input) return;

  const message = input.value.trim();
  if (message === "") return;
  input.value = "";
  sendChatMessage(message);
}

function sendChatMessage(message) {
  const chatBody = document.getElementById("chatBody");
  if (!chatBody) return;
  const finalMessage = String(message || "").trim();
  if (!finalMessage) return;
  lastUserMessage = finalMessage;

  const userMsg = document.createElement("div");
  userMsg.className = "msg user";
  userMsg.innerText = finalMessage;
  chatBody.appendChild(userMsg);

  const payload = {
    message: finalMessage,
    cart,
    page: window.location.pathname
  };
  const typingMsg = document.createElement("div");
  typingMsg.className = "msg bot msg-typing";
  typingMsg.id = "chatTypingIndicator";
  typingMsg.innerHTML = `
    <span class="typing-dot"></span>
    <span class="typing-dot"></span>
    <span class="typing-dot"></span>
  `;
  chatBody.appendChild(typingMsg);
  chatBody.scrollTop = chatBody.scrollHeight;

  fetch(appUrl("chatbot_api.php"), {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload)
  })
    .then(res => res.json())
    .then(data => {
      const typing = document.getElementById("chatTypingIndicator");
      if (typing) typing.remove();
      const botMsg = document.createElement("div");
      botMsg.className = "msg bot";
      botMsg.innerText = data.reply ?? uiText("Sorry, I didn’t understand.", "Üzgünüm, anlayamadım.");
      chatBody.appendChild(botMsg);
      botReplyCountSinceDidYouMean += 1;
      renderChatFeedbackControls(chatBody, botMsg, {
        intent: data.intent || "",
        source: data.source || "rule",
        confidence: data.confidence ?? 0,
        used_ai: !!data.used_ai,
        escalated_to_human: !!data.escalated_to_human,
        user_message: finalMessage,
        bot_reply: data.reply || ""
      });
      lastChatIntent = typeof data.intent === "string" ? data.intent : "";

      const suggested = Array.isArray(data.suggested_products) ? data.suggested_products : [];
      if (suggested.length) {
        const wrap = document.createElement("div");
        wrap.className = "msg bot chat-product-list";
        wrap.style.marginTop = "8px";
        wrap.innerHTML = suggested.map((p) => {
          const id = Number(p.product_id || 0);
          const name = String(p.name || "Product").replace(/'/g, "\\'");
          const image = String(p.image_url || "https://images.unsplash.com/photo-1542291026-7eec264c27ff").replace(/'/g, "\\'");
          const price = Number(p.price || 0);
          return `
            <div class="chat-product-card">
              <img src="${image}" alt="${name}" class="chat-product-card-image">
              <div style="flex:1;min-width:0;">
                <div class="chat-product-card-title">${name}</div>
                <div class="chat-product-card-price">$${price.toFixed(2)}</div>
                <div class="chat-product-card-meta">ZERA Partner · Free shipping</div>
              </div>
              <a href="product_detail.php?name=${encodeURIComponent(name)}" class="chat-product-card-view">View</a>
              <button class="chat-product-card-add" onclick="addToCart(${id}, '${name}', ${price}, '${image}')">Add</button>
            </div>
          `;
        }).join("");
        chatBody.appendChild(wrap);
      }
      const shouldShowDidYouMean =
        (Number(data.confidence || 0) < 0.55) &&
        !data.asked_clarification &&
        !data.escalated_to_human;
      if (shouldShowDidYouMean) {
        renderDidYouMean(chatBody, data.did_you_mean);
      }
      const redirectUrl = typeof data.redirect_url === "string" ? data.redirect_url : "";
      if (redirectUrl) {
        const cta = document.createElement("div");
        cta.className = "msg bot";
        cta.style.marginTop = "6px";
        cta.innerHTML = `<a href="${redirectUrl}" style="display:inline-block;padding:8px 12px;border-radius:8px;background:#111827;color:#fff;text-decoration:none;font-size:12px;">${uiText("Show all matching products", "Eşleşen tüm ürünleri göster")}</a>`;
        chatBody.appendChild(cta);
      }
      renderChatQuickPrompts(true);
      chatBody.scrollTop = chatBody.scrollHeight;
    })
    .catch(() => {
      const typing = document.getElementById("chatTypingIndicator");
      if (typing) typing.remove();
      const errMsg = document.createElement("div");
      errMsg.className = "msg bot";
      errMsg.innerText = uiText("Server error.", "Sunucu hatası.");
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
        ${(item.size || "") ? `<div class="cart-extra">${uiText("Size", "Beden")}: ${item.size}</div>` : ""}
        <div class="cart-meta">
          <span class="cart-seller">${item.seller || "ZERA Partner"}</span>
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
    if (count > lastRenderedCartCount) {
      countEl.classList.remove("bump");
      // restart animation
      void countEl.offsetWidth;
      countEl.classList.add("bump");
    }
  }
  lastRenderedCartCount = count;
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
    container.innerHTML = `<div class="checkout-empty">${uiText("Your cart is empty.", "Sepetiniz boş.")} <a href="${appUrl("products.php")}">${uiText("Continue shopping", "Alışverişe devam et")}</a></div>`;
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
        ${(item.size || "") ? `<p class="checkout-summary-item-price-unit">${uiText("Size", "Beden")}: ${item.size}</p>` : ""}
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
  const cardNumberInput = document.getElementById("card_number");
  const expiryInput = document.getElementById("expiry");
  const cvvInput = document.getElementById("cvv");

  if (!form || !paymentRadios.length) return;

  function updatePaymentUI() {
    const selected = form.querySelector('input[name="payment"]:checked');
    const isCard = selected && selected.value === "card";
    if (paymentFields) paymentFields.style.display = isCard ? "" : "none";
    paymentMethods.forEach((el) => el.classList.toggle("payment-method--active", el.querySelector("input") === selected));
  }

  paymentRadios.forEach((r) => r.addEventListener("change", updatePaymentUI));
  updatePaymentUI();

  if (cardNumberInput) {
    cardNumberInput.addEventListener("input", () => {
      const digitsOnly = (cardNumberInput.value || "").replace(/\D/g, "").slice(0, 16);
      cardNumberInput.value = digitsOnly.replace(/(\d{4})(?=\d)/g, "$1 ").trim();
    });
  }

  if (expiryInput) {
    expiryInput.addEventListener("input", () => {
      const digitsOnly = (expiryInput.value || "").replace(/\D/g, "").slice(0, 4);
      if (digitsOnly.length <= 2) {
        expiryInput.value = digitsOnly;
        return;
      }
      expiryInput.value = `${digitsOnly.slice(0, 2)}/${digitsOnly.slice(2)}`;
    });
  }

  if (cvvInput) {
    cvvInput.addEventListener("input", () => {
      cvvInput.value = (cvvInput.value || "").replace(/\D/g, "").slice(0, 4);
    });
  }
}

function initCheckoutPhoneField() {
  const countrySelect = document.getElementById("phone_country");
  const phoneInput = document.getElementById("phone_number");
  if (!countrySelect || !phoneInput) return;

  function setPhoneRules() {
    const selected = countrySelect.options[countrySelect.selectedIndex];
    const maxDigits = parseInt((selected && selected.dataset && selected.dataset.len) || "10", 10);
    const safeMaxDigits = Number.isFinite(maxDigits) && maxDigits > 0 ? maxDigits : 10;

    phoneInput.maxLength = safeMaxDigits;
    phoneInput.placeholder = uiText(
      `Number (${safeMaxDigits} digits)`,
      `Numara (${safeMaxDigits} hane)`
    );

    if ((phoneInput.value || "").length > safeMaxDigits) {
      phoneInput.value = phoneInput.value.slice(0, safeMaxDigits);
    }
  }

  phoneInput.addEventListener("input", () => {
    phoneInput.value = (phoneInput.value || "")
      .replace(/\D/g, "")
      .slice(0, phoneInput.maxLength);
  });

  countrySelect.addEventListener("change", setPhoneRules);
  setPhoneRules();
}

function goToCheckout() {
  if (cart.length === 0) {
    alert(uiText("Cart is empty", "Sepet boş"));
    return;
  }
  saveCart();
  window.location.href = appUrl("checkout.php");
}

function checkout() {
  if (cart.length === 0) {
    alert(uiText("Cart is empty", "Sepet boş"));
    return;
  }

  const btn = document.getElementById("checkoutSubmit");
  if (btn) {
    btn.disabled = true;
    btn.textContent = uiText("Processing...", "İşleniyor...");
  }

  fetch(appUrl("checkout.php"), {
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
        const raw = data.error || uiText("Unknown error", "Bilinmeyen hata");
        let msg = raw;
        if (/insufficient stock/i.test(raw)) {
          msg = uiText(
            "Some items in your cart are out of stock or low in stock. Please adjust quantities and try again.",
            "Sepetinizdeki bazı ürünler stokta yok veya yetersiz. Lütfen miktarı azaltıp tekrar deneyin."
          ) + "\n\n" + raw;
        } else if (/stock just changed/i.test(raw)) {
          msg = uiText(
            "Stock just changed. Please refresh the page and try again.",
            "Stok az önce değişti. Lütfen sayfayı yenileyip tekrar deneyin."
          );
        }
        alert(uiText("Checkout failed: ", "Ödeme başarısız: ") + msg);
        if (btn) { btn.disabled = false; btn.textContent = uiText("Complete Purchase", "Satın Almayı Tamamla"); }
      }
    })
    .catch(() => {
      alert(uiText("Network error. Please try again.", "Ağ hatası. Lütfen tekrar deneyin."));
      if (btn) { btn.disabled = false; btn.textContent = uiText("Complete Purchase", "Satın Almayı Tamamla"); }
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

