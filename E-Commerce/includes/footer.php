<?php
if (!function_exists("t")) {
  require_once __DIR__ . "/../functions.php";
}
$appLang = get_current_lang();
?>
<footer class="site-footer">
  <div class="footer-inner">

    <!-- Support -->
    <div class="footer-col">
      <h5><?= htmlspecialchars(t("footer.support", "Support"), ENT_QUOTES, 'UTF-8') ?></h5>
      <div class="footer-link-group">
        <a href="#" onclick="toggleFooterInfo(event, 'help')"><?= htmlspecialchars(t("footer.help_center", "Help Center"), ENT_QUOTES, 'UTF-8') ?></a>
        <div id="help" class="footer-info" style="display:none; font-size:13px; margin-top:5px;">
          <p><?= htmlspecialchars(t("footer.help_center_desc", "Find guides, FAQs, and support articles to help you with orders, shipping, and returns."), ENT_QUOTES, 'UTF-8') ?></p>
        </div>

        <a href="#" onclick="toggleFooterInfo(event, 'shipping')"><?= htmlspecialchars(t("footer.shipping_returns", "Shipping & Returns"), ENT_QUOTES, 'UTF-8') ?></a>
        <div id="shipping" class="footer-info" style="display:none; font-size:13px; margin-top:5px;">
          <p><?= htmlspecialchars(t("footer.shipping_returns_desc", "Learn about shipping options, delivery times, and our return policy."), ENT_QUOTES, 'UTF-8') ?></p>
        </div>

        <a href="#" onclick="toggleFooterInfo(event, 'faq')"><?= htmlspecialchars(t("footer.faq", "FAQ"), ENT_QUOTES, 'UTF-8') ?></a>
        <div id="faq" class="footer-info" style="display:none; font-size:13px; margin-top:5px;">
          <p><?= htmlspecialchars(t("footer.faq_desc", "Answers to common questions about products, accounts, and payments."), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
      </div>
    </div>

    <!-- About -->
    <div class="footer-col">
      <h5><?= htmlspecialchars(t("footer.about", "About"), ENT_QUOTES, 'UTF-8') ?></h5>
      <div class="footer-link-group">
        <a href="#" onclick="toggleFooterInfo(event, 'ourStory')"><?= htmlspecialchars(t("footer.our_story", "Our Story"), ENT_QUOTES, 'UTF-8') ?></a>
        <div id="ourStory" class="footer-info" style="display:none; font-size:13px; margin-top:5px;">
          <p><?= htmlspecialchars(t("footer.our_story_desc", "Learn how ZERA started, our mission, and what inspires our collections."), ENT_QUOTES, 'UTF-8') ?></p>
        </div>

        <a href="#" onclick="toggleFooterInfo(event, 'careers')"><?= htmlspecialchars(t("footer.careers", "Careers"), ENT_QUOTES, 'UTF-8') ?></a>
        <div id="careers" class="footer-info" style="display:none; font-size:13px; margin-top:5px;">
          <p><?= htmlspecialchars(t("footer.careers_desc", "Explore career opportunities and join our team in shaping the ZERA experience."), ENT_QUOTES, 'UTF-8') ?></p>
        </div>

        <a href="#" onclick="toggleFooterInfo(event, 'contact')"><?= htmlspecialchars(t("footer.contact", "Contact"), ENT_QUOTES, 'UTF-8') ?></a>
        <div id="contact" class="footer-info" style="display:none; font-size:13px; margin-top:5px;">
          <p><?= htmlspecialchars(t("footer.contact_desc", "Get in touch with our support team for questions or feedback."), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
      </div>
    </div>

    <!-- Follow -->
    <div class="footer-col">
      <h5><?= htmlspecialchars(t("footer.follow", "Follow"), ENT_QUOTES, 'UTF-8') ?></h5>
      <div class="footer-link-group">
        <a href="#" onclick="toggleFooterInfo(event, 'instagram')"><?= htmlspecialchars(t("footer.instagram", "Instagram"), ENT_QUOTES, 'UTF-8') ?></a>
        <div id="instagram" class="footer-info" style="display:none; font-size:13px; margin-top:5px;">
          <p><?= htmlspecialchars(t("footer.instagram_desc", "Follow us on Instagram for the latest product drops, promotions, and stories."), ENT_QUOTES, 'UTF-8') ?></p>
        </div>

        <a href="#" onclick="toggleFooterInfo(event, 'twitter')"><?= htmlspecialchars(t("footer.twitter", "Twitter"), ENT_QUOTES, 'UTF-8') ?></a>
        <div id="twitter" class="footer-info" style="display:none; font-size:13px; margin-top:5px;">
          <p><?= htmlspecialchars(t("footer.twitter_desc", "Stay updated with news, product announcements, and community highlights on Twitter."), ENT_QUOTES, 'UTF-8') ?></p>
        </div>

        <a href="#" onclick="toggleFooterInfo(event, 'facebook')"><?= htmlspecialchars(t("footer.facebook", "Facebook"), ENT_QUOTES, 'UTF-8') ?></a>
        <div id="facebook" class="footer-info" style="display:none; font-size:13px; margin-top:5px;">
          <p><?= htmlspecialchars(t("footer.facebook_desc", "Join our Facebook community to share feedback, reviews, and connect with other fans."), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
      </div>
    </div>

    <!-- Copyright -->
    <div class="footer-col">
      <p>&copy; <?= date('Y') ?> ZERA. <?= htmlspecialchars(t("footer.rights", "All rights reserved."), ENT_QUOTES, 'UTF-8') ?></p>
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
  <div class="chat-header"><?= htmlspecialchars(t("chat.title", "Customer Support"), ENT_QUOTES, 'UTF-8') ?></div>
  <div class="chat-body" id="chatBody">
    <div class="msg bot"><?= htmlspecialchars(t("chat.welcome", "Hi 👋 Looking for recommendations or help?"), ENT_QUOTES, 'UTF-8') ?></div>
  </div>
  <div id="quick-actions" class="qa-container"></div>
  <div class="chat-input">
    <input id="userInput" placeholder="<?= htmlspecialchars(t("chat.input_placeholder", "Type your message..."), ENT_QUOTES, 'UTF-8') ?>" />
    <button onclick="sendMessage()"><?= htmlspecialchars(t("chat.send", "Send"), ENT_QUOTES, 'UTF-8') ?></button>
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
        <button type="button" class="auth-modal-tab active" data-tab="signin"><?= htmlspecialchars(t("auth.signin", "Sign In"), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="auth-modal-tab" data-tab="join"><?= htmlspecialchars(t("auth.join", "Join"), ENT_QUOTES, 'UTF-8') ?></button>
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
            <input type="email" id="auth-signin-email" name="email" placeholder="<?= htmlspecialchars(t("auth.email", "Email"), ENT_QUOTES, 'UTF-8') ?>" required autocomplete="email">
          </div>
          <div class="auth-input-group">
            <span class="auth-input-icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
              </svg>
            </span>
            <input type="password" id="auth-signin-password" name="password" placeholder="<?= htmlspecialchars(t("auth.password", "Password"), ENT_QUOTES, 'UTF-8') ?>" required autocomplete="current-password">
          </div>
          <a href="#" class="auth-forgot-link"><?= htmlspecialchars(t("auth.forgot_password", "Forgot password?"), ENT_QUOTES, 'UTF-8') ?></a>
          <button type="submit" class="auth-modal-btn"><?= htmlspecialchars(t("auth.signin", "Sign In"), ENT_QUOTES, 'UTF-8') ?></button>
          <p class="auth-modal-switch">
            <?= htmlspecialchars(t("auth.no_account", "Don't have an account?"), ENT_QUOTES, 'UTF-8') ?> <button type="button" class="auth-modal-link" data-switch="join"><?= htmlspecialchars(t("auth.join", "Join"), ENT_QUOTES, 'UTF-8') ?></button>
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
            <input type="text" id="auth-join-name" name="name" placeholder="<?= htmlspecialchars(t("auth.full_name", "Full Name"), ENT_QUOTES, 'UTF-8') ?>" required autocomplete="name">
          </div>
          <div class="auth-input-group">
            <span class="auth-input-icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                <polyline points="22,6 12,13 2,6"/>
              </svg>
            </span>
            <input type="email" id="auth-join-email" name="email" placeholder="<?= htmlspecialchars(t("auth.email", "Email"), ENT_QUOTES, 'UTF-8') ?>" required autocomplete="email">
          </div>
          <div class="auth-input-group">
            <span class="auth-input-icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
              </svg>
            </span>
            <input type="password" id="auth-join-password" name="password" placeholder="<?= htmlspecialchars(t("auth.password", "Password"), ENT_QUOTES, 'UTF-8') ?>" required autocomplete="new-password">
          </div>
          <div class="auth-input-group">
            <span class="auth-input-icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
              </svg>
            </span>
            <input type="password" id="auth-join-confirm" name="confirm" placeholder="<?= htmlspecialchars(t("auth.confirm_password", "Confirm Password"), ENT_QUOTES, 'UTF-8') ?>" required autocomplete="new-password">
          </div>
          <button type="submit" class="auth-modal-btn"><?= htmlspecialchars(t("auth.create_account", "Create Account"), ENT_QUOTES, 'UTF-8') ?></button>
          <p class="auth-modal-switch">
            <?= htmlspecialchars(t("auth.have_account", "Already have an account?"), ENT_QUOTES, 'UTF-8') ?> <button type="button" class="auth-modal-link" data-switch="signin"><?= htmlspecialchars(t("auth.signin", "Sign In"), ENT_QUOTES, 'UTF-8') ?></button>
          </p>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="cart" id="cart">
  <h3><?= htmlspecialchars(t("cart.title", "Your Cart"), ENT_QUOTES, 'UTF-8') ?></h3>
  <div id="cartItems"></div>
  <p><strong><?= htmlspecialchars(t("cart.total", "Total"), ENT_QUOTES, 'UTF-8') ?>: $<span id="total">0</span></strong></p>
  <button onclick="goToCheckout()" class="btn-full-width">
    <?= htmlspecialchars(t("cart.checkout", "Checkout"), ENT_QUOTES, 'UTF-8') ?>
  </button>
</div>

<script>
window.APP_LANG = <?= json_encode($appLang) ?>;
</script>

<script src="assets/js/main.js?v=<?= urlencode((string) @filemtime(__DIR__ . '/../assets/js/main.js')) ?>"></script>
<?php if (isset($is_homepage) && $is_homepage): ?>
<script src="assets/js/homepage.js?v=<?= urlencode((string) @filemtime(__DIR__ . '/../assets/js/homepage.js')) ?>"></script>
<?php endif; ?>

<script src="assets/js/main.js"></script>
</body>
</html>

