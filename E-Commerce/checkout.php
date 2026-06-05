<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'functions.php';
require_once __DIR__ . '/payments/IyzicoService.php';

// JSON API — iyzico ödeme başlatma
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_SERVER['CONTENT_TYPE'])
    && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false
) {
    header('Content-Type: application/json; charset=utf-8');
    csrf_require(true);

    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data) || empty($data['cart']) || !is_array($data['cart'])) {
        echo json_encode(['success' => false, 'error' => 'Empty cart']);
        exit;
    }

    $shipping = is_array($data['shipping'] ?? null) ? $data['shipping'] : [];
    $requiredShipping = ['full_name', 'email', 'address', 'city', 'zip'];
    foreach ($requiredShipping as $field) {
        if (trim((string) ($shipping[$field] ?? '')) === '') {
            echo json_encode([
                'success' => false,
                'error' => 'Missing shipping field: ' . $field,
            ]);
            exit;
        }
    }

    $phoneCountry = trim((string) ($shipping['phone_country'] ?? 'TR'));
    $phoneNumber = preg_replace('/\D+/', '', (string) ($shipping['phone_number'] ?? ''));
    $dial = '+90';
    if ($phoneCountry === 'US') {
        $dial = '+1';
    } elseif ($phoneCountry === 'GB') {
        $dial = '+44';
    }
    $shipping['phone'] = $phoneNumber !== '' ? $dial . $phoneNumber : '';
    unset($shipping['phone_number']);

    $user_id = require_login();

    if (!iyzico_is_configured()) {
        echo json_encode([
            'success' => false,
            'error' => 'iyzico is not configured. Set IYZICO_API_KEY and IYZICO_SECRET_KEY in .env',
            'code' => 'iyzico_not_configured',
        ]);
        exit;
    }

    try {
        $userStmt = $pdo->prepare('SELECT user_id, full_name, email FROM users WHERE user_id = ? LIMIT 1');
        $userStmt->execute([$user_id]);
        $userRow = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [
            'user_id' => $user_id,
            'full_name' => (string) ($shipping['full_name'] ?? 'Customer'),
            'email' => (string) ($shipping['email'] ?? ''),
        ];

        $created = create_awaiting_payment_order($pdo, $user_id, $data['cart'], $shipping);
        $lang = get_current_lang();
        $callbackUrl = site_absolute_url('payment_callback.php', ['lang' => $lang]);

        $iyzico = iyzico_initialize_checkout(
            $created['order_id'],
            $created['conversation_id'],
            (float) $created['total_usd'],
            $created['lines'],
            $shipping,
            $userRow,
            $callbackUrl,
            $lang
        );

        save_payment_record(
            $pdo,
            $created['order_id'],
            $created['conversation_id'],
            $iyzico['token'],
            'pending',
            (float) $iyzico['paid_price_try'],
            $iyzico['raw'] ?? null
        );

        echo json_encode([
            'success' => true,
            'order_id' => $created['order_id'],
            'payment_provider' => 'iyzico',
            'payment_page_url' => $iyzico['payment_page_url'],
            'token' => $iyzico['token'],
            'amount_try' => $iyzico['paid_price_try'],
        ]);
    } catch (Throwable $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
        ]);
    }
    exit;
}

require_login();

$page_title = t('meta.checkout_title', 'ZERA - Checkout');
$is_checkout = true;
$iyzico_ready = iyzico_is_configured();
$checkout_user_name = $_SESSION['user_name'] ?? '';
$checkout_user_email = $_SESSION['user_email'] ?? '';

include 'includes/header.php';
?>

<link rel="stylesheet" href="assets/css/checkout.css">

<main class="checkout-page">
  <div class="checkout-container">
    <h1 class="checkout-title"><?= htmlspecialchars(t('checkout.title', 'Checkout'), ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="checkout-subtitle"><?= htmlspecialchars(t('checkout.subtitle', 'Review your order and complete your purchase securely.'), ENT_QUOTES, 'UTF-8') ?></p>

    <?php if (!$iyzico_ready): ?>
      <div class="checkout-alert checkout-alert--warn">
        <?= htmlspecialchars(t('checkout.iyzico_not_configured', 'iyzico payment is not configured on this server. Add API keys to .env to enable checkout.'), ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <div class="checkout-layout">
      <div class="checkout-left">
        <form id="checkoutForm" class="checkout-form" onsubmit="event.preventDefault(); checkout();">
          <section class="checkout-card checkout-section">
            <h2 class="checkout-card-title"><?= htmlspecialchars(t('checkout.customer_shipping', 'Customer & Shipping Information'), ENT_QUOTES, 'UTF-8') ?></h2>
            <div class="checkout-fields">
              <div class="checkout-field">
                <label for="full_name"><?= htmlspecialchars(t('checkout.full_name', 'Full Name'), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars((string) $checkout_user_name, ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= htmlspecialchars(t('checkout.full_name_placeholder', 'John Doe'), ENT_QUOTES, 'UTF-8') ?>" required>
              </div>
              <div class="checkout-field">
                <label for="email"><?= htmlspecialchars(t('checkout.email', 'Email'), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars((string) $checkout_user_email, ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= htmlspecialchars(t('checkout.email_placeholder', 'john@example.com'), ENT_QUOTES, 'UTF-8') ?>" required>
              </div>
              <div class="checkout-field">
                <label for="phone_number"><?= htmlspecialchars(t('checkout.phone', 'Phone'), ENT_QUOTES, 'UTF-8') ?></label>
                <div class="checkout-phone-group">
                  <select id="phone_country" name="phone_country" class="checkout-phone-country" aria-label="<?= htmlspecialchars(t('checkout.country', 'Country'), ENT_QUOTES, 'UTF-8') ?>">
                    <option value="TR" data-dial="+90" data-len="10" selected>🇹🇷 +90 Türkiye</option>
                    <option value="US" data-dial="+1" data-len="10">🇺🇸 +1 United States</option>
                    <option value="GB" data-dial="+44" data-len="10">🇬🇧 +44 United Kingdom</option>
                    <option value="DE" data-dial="+49" data-len="11">🇩🇪 +49 Germany</option>
                    <option value="FR" data-dial="+33" data-len="9">🇫🇷 +33 France</option>
                  </select>
                  <input type="tel" id="phone_number" name="phone_number" class="checkout-phone-number" inputmode="numeric" autocomplete="tel-national" placeholder="<?= htmlspecialchars(t('checkout.phone_placeholder', 'Phone number'), ENT_QUOTES, 'UTF-8') ?>" maxlength="10">
                </div>
              </div>
              <div class="checkout-field checkout-field--full">
                <label for="address"><?= htmlspecialchars(t('checkout.address', 'Address'), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="text" id="address" name="address" placeholder="<?= htmlspecialchars(t('checkout.address_placeholder', '123 Main Street, Apt 4'), ENT_QUOTES, 'UTF-8') ?>" required>
              </div>
              <div class="checkout-field">
                <label for="city"><?= htmlspecialchars(t('checkout.city', 'City'), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="text" id="city" name="city" placeholder="<?= htmlspecialchars(t('checkout.city_placeholder', 'Istanbul'), ENT_QUOTES, 'UTF-8') ?>" required>
              </div>
              <div class="checkout-field">
                <label for="zip"><?= htmlspecialchars(t('checkout.postal_code', 'Postal Code'), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="text" id="zip" name="zip" placeholder="<?= htmlspecialchars(t('checkout.postal_code_placeholder', '34000'), ENT_QUOTES, 'UTF-8') ?>" required>
              </div>
            </div>
          </section>

          <section class="checkout-card checkout-section checkout-iyzico-card">
            <h2 class="checkout-card-title"><?= htmlspecialchars(t('checkout.payment', 'Payment'), ENT_QUOTES, 'UTF-8') ?></h2>
            <div class="iyzico-payment-box">
              <div class="iyzico-payment-logo">iyzico</div>
              <p class="iyzico-payment-text">
                <?= htmlspecialchars(t('checkout.iyzico_description', 'You will complete your card payment securely on the iyzico payment page. Card details are not stored on our servers.'), ENT_QUOTES, 'UTF-8') ?>
              </p>
              <ul class="iyzico-payment-features">
                <li><?= htmlspecialchars(t('checkout.iyzico_feature_installment', 'Installment options'), ENT_QUOTES, 'UTF-8') ?></li>
                <li><?= htmlspecialchars(t('checkout.iyzico_feature_3ds', '3D Secure support'), ENT_QUOTES, 'UTF-8') ?></li>
                <li><?= htmlspecialchars(t('checkout.iyzico_feature_secure', 'PCI-DSS compliant infrastructure'), ENT_QUOTES, 'UTF-8') ?></li>
              </ul>
            </div>
          </section>

          <button type="submit" class="checkout-submit checkout-submit--iyzico" id="checkoutSubmit" <?= $iyzico_ready ? '' : 'disabled' ?>>
            <?= htmlspecialchars(t('checkout.pay_with_iyzico', 'Pay with iyzico'), ENT_QUOTES, 'UTF-8') ?>
          </button>

          <div class="checkout-trust">
            <span class="trust-item">🔒 <?= htmlspecialchars(t('checkout.trust.secure', 'Secure Payment'), ENT_QUOTES, 'UTF-8') ?></span>
            <span class="trust-item">🚚 <?= htmlspecialchars(t('checkout.trust.fast_shipping', 'Fast Shipping'), ENT_QUOTES, 'UTF-8') ?></span>
            <span class="trust-item">↩️ <?= htmlspecialchars(t('checkout.trust.easy_returns', 'Easy Returns'), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        </form>
      </div>

      <aside class="checkout-right">
        <div class="checkout-summary-card" id="checkoutSummaryCard">
          <h2 class="checkout-summary-title"><?= htmlspecialchars(t('checkout.order_summary', 'Order Summary'), ENT_QUOTES, 'UTF-8') ?></h2>
          <div id="checkoutCartSummary" class="checkout-summary-content"></div>
          <p class="checkout-summary-note"><?= htmlspecialchars(t('checkout.charged_in_try', 'Payment is processed in TRY via iyzico.'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
      </aside>
    </div>
  </div>
</main>

<?php include 'includes/footer.php'; ?>
