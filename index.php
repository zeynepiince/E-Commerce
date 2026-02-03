<?php
session_start();
require_once "db.php";
$stmt = $pdo->query(
  "SELECT `name`, price, image_url
   FROM products
   WHERE is_featured = 1"
);

$products = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>STORY – Smart Online Store</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="navbar.css">
</head>

<body>

<header class="elegant-header">

  <div class="header-left">
    <div class="logo">STORY</div>

    <nav class="nav-categories">
      <a href="#">Women</a>
      <a href="#">Men</a>
      <a href="#">Electronics</a>
      <a href="#">Home</a>
      <a href="#">Beauty</a>
      <a href="#">Sports</a>
    </nav>

    <input 
      type="text" 
      class="nav-search"
      placeholder="Search"
    />
  </div>

  <div class="header-right">
    <span class="nav-action">Favorites</span>
    <span class="nav-action" onclick="toggleCart()">Cart</span>
    <span class="nav-action highlight">Sign In / Join</span>
  </div>

</header>


<section class="hero">
<div>
<h2>Not Just Shopping.<br>A Curated Experience.</h2>
<p>
Discover thoughtfully selected products that fit your lifestyle.
Simple, fast and designed to make everyday choices easier.
</p>
<button>Discover Collection</button>
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
</section>

<section class="products">
<h3>Handpicked For You</h3>
<p class="subtitle">
Products our customers love — chosen for quality, design and comfort.
</p>

<div class="cart" id="cart">
  <h3>Your Cart</h3>
  <div id="cartItems"></div>
  <p><strong>Total: $<span id="total">0</span></strong></p>

  <button onclick="checkout()" style="
    width:100%;
    margin-top:12px;
    padding:14px;
    border:none;
    border-radius:14px;
    background:#0f766e;
    color:white;
    cursor:pointer;
  ">
    Checkout
  </button>
</div>



<div class="slider">
  <div class="card">
    <img src="https://i.pinimg.com/736x/32/b7/93/32b793477200f25a6c410a1d3709e57e.jpg">
    <h4>Smartphone Pro</h4>
    <p class="price">$749</p>
    <button onclick="addToCart(1,'Smartphone Pro',749)">Add to Cart</button>
  </div>

  <div class="card">
    <img src="https://i.pinimg.com/1200x/c8/e1/bb/c8e1bb4b40fe99a2eaeb38bff2cf5c67.jpg">
    <h4>Wireless Headphones</h4>
    <p class="price">$229</p>
    <button onclick="addToCart(2,'Wireless Headphones',229)">Add to Cart</button>
  </div>

  <div class="card">
    <img src="https://i.pinimg.com/1200x/15/ed/9a/15ed9a436572e0ea15ed3d8b3ed3b0a9.jpg">
    <h4>Running Sneakers</h4>
    <p class="price">$139</p>
    <button onclick="addToCart(3,'Running Sneakers',139)">Add to Cart</button>
  </div>

  <div class="card">
    <img src="https://i.pinimg.com/736x/6e/4d/58/6e4d581588b86051db9756bf9e7827d7.jpg">
    <h4>Espresso Machine</h4>
    <p class="price">$289</p>
    <button onclick="addToCart(4,'Espresso Machine',289)">Add to Cart</button>
  </div>

  <div class="card">
    <img src="https://i.pinimg.com/1200x/ec/40/1e/ec401eef9905acd71dda8af7403022fc.jpg">
    <h4>Minimal Desk Lamp</h4>
    <p class="price">$89</p>
    <button onclick="addToCart(5,'Desk Lamp',89)">Add to Cart</button>
  </div>
</div>
</section>


<button class="chat-toggle" onclick="toggleChat()">💬</button>

<div class="chatbot" id="chatbot">
<div class="chat-header">Customer Support</div>
<div class="chat-body" id="chatBody">
<div class="msg bot">Hi 👋 Looking for recommendations or help?</div>
</div>
<div class="chat-input">
<input id="userInput" placeholder="Type your message..." />
<button onclick="sendMessage()">Send</button>
</div>
</div>
<script>

let cart = [];
let total = 0;

function toggleCart() {
  document.getElementById("cart").classList.toggle("active");
}

function addToCart(id, name, price) {

  const existing = cart.find(item => item.id === id);

  if (existing) {
    existing.qty++;
  } else {
    cart.push({ id, name, price, qty: 1 });
  }

  renderCart();
}

function toggleChat() {
  document.getElementById("chatbot").classList.toggle("active");
}

function sendMessage() {
  const input = document.getElementById("userInput");
  const message = input.value.trim();

  if (message === "") return;

  const chatBody = document.getElementById("chatBody");

  // USER MESSAGE
  const userMsg = document.createElement("div");
  userMsg.className = "msg user";
  userMsg.innerText = message;
  chatBody.appendChild(userMsg);

  input.value = "";

  // SEND TO BACKEND
  fetch("/chatbotv2/chatbot.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ message })
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
  cartItems.innerHTML = "";
  total = 0;

  cart.forEach(item => {
    total += item.price * item.qty;

    const div = document.createElement("div");
    div.className = "cart-item";
    div.innerHTML = `
      <span>${item.name} x ${item.qty}</span>
      <span>$${item.price * item.qty}</span>
    `;
    cartItems.appendChild(div);
  });

  document.getElementById("total").innerText = total;
}

/* CHECKOUT */
function checkout() {
  console.log(cart);
  if (cart.length === 0) {
    alert("Cart is empty");
    return;
  }

  fetch("/chatbotv2/checkout.php", {
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
    } else {
      alert("Checkout failed: " + data.error);
      console.log(data);

    }
  });
}
</script>
