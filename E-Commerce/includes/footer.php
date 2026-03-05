<footer class="site-footer">
  <div class="footer-inner">
    <div class="footer-col">
      <h5>Support</h5>
      <a href="#">Help center</a>
      <a href="#">Shipping & returns</a>
      <a href="#">FAQ</a>
    </div>
    <div class="footer-col">
      <h5>About</h5>
      <a href="#">Our story</a>
      <a href="#">Careers</a>
      <a href="#">Contact</a>
    </div>
    <div class="footer-col">
      <h5>Follow</h5>
      <a href="#">Instagram</a>
      <a href="#">Twitter</a>
      <a href="#">Facebook</a>
    </div>
    <div class="footer-col">
      <p>&copy; <?= date('Y') ?> STORY. All rights reserved.</p>
    </div>
  </div>
</footer>

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

<div class="cart-backdrop" id="cartBackdrop" onclick="toggleCart()"></div>

<div class="cart" id="cart">
  <h3>Your Cart</h3>
  <div id="cartItems"></div>
  <p><strong>Total: $<span id="total">0</span></strong></p>
  <button onclick="goToCheckout()" class="btn-full-width">
    Checkout
  </button>
</div>

<script src="assets/js/main.js"></script>
</body>
</html>

