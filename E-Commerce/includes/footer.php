<footer class="site-footer">
  <div class="footer-inner">

    <!-- Support -->
    <div class="footer-col">
      <h5>Support</h5>
      <div class="footer-link-group">
        <a href="#" onclick="toggleFooterInfo(event, 'help')">Help Center</a>
        <div id="help" class="footer-info" style="display:none; font-size:13px; margin-top:5px;">
          <p>Find guides, FAQs, and support articles to help you with orders, shipping, and returns.</p>
        </div>

        <a href="#" onclick="toggleFooterInfo(event, 'shipping')">Shipping & Returns</a>
        <div id="shipping" class="footer-info" style="display:none; font-size:13px; margin-top:5px;">
          <p>Learn about shipping options, delivery times, and our return policy.</p>
        </div>

        <a href="#" onclick="toggleFooterInfo(event, 'faq')">FAQ</a>
        <div id="faq" class="footer-info" style="display:none; font-size:13px; margin-top:5px;">
          <p>Answers to common questions about products, accounts, and payments.</p>
        </div>
      </div>
    </div>

    <!-- About -->
    <div class="footer-col">
      <h5>About</h5>
      <div class="footer-link-group">
        <a href="#" onclick="toggleFooterInfo(event, 'ourStory')">Our Story</a>
        <div id="ourStory" class="footer-info" style="display:none; font-size:13px; margin-top:5px;">
          <p>Learn how STORY started, our mission, and what inspires our collections.</p>
        </div>

        <a href="#" onclick="toggleFooterInfo(event, 'careers')">Careers</a>
        <div id="careers" class="footer-info" style="display:none; font-size:13px; margin-top:5px;">
          <p>Explore career opportunities and join our team in shaping the STORY experience.</p>
        </div>

        <a href="#" onclick="toggleFooterInfo(event, 'contact')">Contact</a>
        <div id="contact" class="footer-info" style="display:none; font-size:13px; margin-top:5px;">
          <p>Get in touch with our support team for questions or feedback.</p>
        </div>
      </div>
    </div>

    <!-- Follow -->
    <div class="footer-col">
      <h5>Follow</h5>
      <div class="footer-link-group">
        <a href="#" onclick="toggleFooterInfo(event, 'instagram')">Instagram</a>
        <div id="instagram" class="footer-info" style="display:none; font-size:13px; margin-top:5px;">
          <p>Follow us on Instagram for the latest product drops, promotions, and stories.</p>
        </div>

        <a href="#" onclick="toggleFooterInfo(event, 'twitter')">Twitter</a>
        <div id="twitter" class="footer-info" style="display:none; font-size:13px; margin-top:5px;">
          <p>Stay updated with news, product announcements, and community highlights on Twitter.</p>
        </div>

        <a href="#" onclick="toggleFooterInfo(event, 'facebook')">Facebook</a>
        <div id="facebook" class="footer-info" style="display:none; font-size:13px; margin-top:5px;">
          <p>Join our Facebook community to share feedback, reviews, and connect with other fans.</p>
        </div>
      </div>
    </div>

    <!-- Copyright -->
    <div class="footer-col">
      <p>&copy; <?= date('Y') ?> STORY. All rights reserved.</p>
    </div>

  </div>
</footer>

<script>
function toggleFooterInfo(e, id) {
  e.preventDefault();

  // Tüm footer-info bloklarını kapat
  const allInfos = document.querySelectorAll('.footer-info');
  allInfos.forEach(info => {
    if (info.id !== id) {
      info.style.display = 'none';
    }
  });

  // Tıklananı aç/kapat
  const info = document.getElementById(id);
  info.style.display = (info.style.display === 'none') ? 'block' : 'none';
}
</script>



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

<!-- Auth Modal -->
<div class="auth-modal" id="authModal" aria-hidden="true">
  <div class="auth-modal-backdrop" onclick="toggleAuthModal()"></div>
  <div class="auth-modal-dialog" role="dialog" aria-modal="true" aria-label="Sign In or Create Account">
    <button type="button" class="auth-modal-close" onclick="toggleAuthModal()" aria-label="Close">×</button>
    <div class="auth-modal-content">
      <div class="auth-modal-tabs">
        <button type="button" class="auth-modal-tab active" data-tab="signin">Sign In</button>
        <button type="button" class="auth-modal-tab" data-tab="join">Join</button>
      </div>
      <div class="auth-modal-forms">
        <form id="auth-signin-form" class="auth-modal-form active">
          <div class="auth-input-group">
            <span class="auth-input-icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                <polyline points="22,6 12,13 2,6"/>
              </svg>
            </span>
            <input type="email" id="auth-signin-email" name="email" placeholder="Email" required autocomplete="email">
          </div>
          <div class="auth-input-group">
            <span class="auth-input-icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
              </svg>
            </span>
            <input type="password" id="auth-signin-password" name="password" placeholder="Password" required autocomplete="current-password">
          </div>
          <a href="#" class="auth-forgot-link">Forgot password?</a>
          <button type="submit" class="auth-modal-btn">Sign In</button>
          <p class="auth-modal-switch">
            Don't have an account? <button type="button" class="auth-modal-link" data-switch="join">Join</button>
          </p>
        </form>
        <form id="auth-join-form" class="auth-modal-form">
          <div class="auth-input-group">
            <span class="auth-input-icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
              </svg>
            </span>
            <input type="text" id="auth-join-name" name="name" placeholder="Full Name" required autocomplete="name">
          </div>
          <div class="auth-input-group">
            <span class="auth-input-icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                <polyline points="22,6 12,13 2,6"/>
              </svg>
            </span>
            <input type="email" id="auth-join-email" name="email" placeholder="Email" required autocomplete="email">
          </div>
          <div class="auth-input-group">
            <span class="auth-input-icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
              </svg>
            </span>
            <input type="password" id="auth-join-password" name="password" placeholder="Password" required autocomplete="new-password">
          </div>
          <div class="auth-input-group">
            <span class="auth-input-icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
              </svg>
            </span>
            <input type="password" id="auth-join-confirm" name="confirm" placeholder="Confirm Password" required autocomplete="new-password">
          </div>
          <button type="submit" class="auth-modal-btn">Create Account</button>
          <p class="auth-modal-switch">
            Already have an account? <button type="button" class="auth-modal-link" data-switch="signin">Sign In</button>
          </p>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="cart" id="cart">
  <h3>Your Cart</h3>
  <div id="cartItems"></div>
  <p><strong>Total: $<span id="total">0</span></strong></p>
  <button onclick="goToCheckout()" class="btn-full-width">
    Checkout
  </button>
</div>

<script src="assets/js/main.js?v=99"></script>
<?php if (isset($is_homepage) && $is_homepage): ?>
<script src="assets/js/homepage.js"></script>
<?php endif; ?>
</body>
</html>

