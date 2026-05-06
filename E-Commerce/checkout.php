<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'functions.php';

function resolve_product_id_for_order_item(PDO $pdo, array $item): ?int
{
    $rawId = $item["id"] ?? null;
    if (is_numeric($rawId)) {
        $candidate = (int) $rawId;
        if ($candidate > 0) {
            $stmt = $pdo->prepare("SELECT product_id FROM products WHERE product_id = ? LIMIT 1");
            $stmt->execute([$candidate]);
            $found = $stmt->fetchColumn();
            if ($found !== false) return (int) $found;
        }
    }

    $name = trim((string) ($item["name"] ?? ""));
    if ($name !== "") {
        $stmt = $pdo->prepare("SELECT product_id FROM products WHERE name = ? LIMIT 1");
        $stmt->execute([$name]);
        $found = $stmt->fetchColumn();
        if ($found !== false) return (int) $found;
    }

    return null;
}

// JSON API for checkout (called from JS)
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_SERVER['CONTENT_TYPE'])
    && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false
) {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['cart']) || empty($data['cart'])) {
        echo json_encode([
            "success" => false,
            "error" => "Empty cart"
        ]);
        exit;
    }

    $cart    = $data['cart'];
    $user_id = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 1;

    try {
        $pdo->beginTransaction();

        $total = 0;
        foreach ($cart as $item) {
            $total += $item["price"] * $item["qty"];
        }

        $stmt = $pdo->prepare(
            "INSERT INTO orders (user_id, total_amount, status)
             VALUES (?, ?, ?)"
        );
        $stmt->execute([$user_id, $total, "pending"]);

        $order_id = $pdo->lastInsertId();

        $stmtItem = $pdo->prepare(
            "INSERT INTO order_items (order_id, product_id, quantity, unit_price)
             VALUES (?, ?, ?, ?)"
        );

        foreach ($cart as $item) {
            $resolvedProductId = resolve_product_id_for_order_item($pdo, is_array($item) ? $item : []);
            $stmtItem->execute([
                $order_id,
                $resolvedProductId,
                (int) ($item["qty"] ?? 1),
                (float) ($item["price"] ?? 0)
            ]);
        }

        $pdo->commit();

        echo json_encode([
            "success" => true,
            "order_id" => $order_id
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
    exit;
}

$page_title = t("meta.checkout_title", "ZERA - Checkout");
$is_checkout = true;

include 'includes/header.php';
?>

<link rel="stylesheet" href="assets/css/checkout.css">

<main class="checkout-page">
  <div class="checkout-container">
    <h1 class="checkout-title"><?= htmlspecialchars(t("checkout.title", "Checkout"), ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="checkout-subtitle"><?= htmlspecialchars(t("checkout.subtitle", "Review your order and complete your purchase securely."), ENT_QUOTES, 'UTF-8') ?></p>

    <div class="checkout-layout">
      <!-- Left: Customer & Payment -->
      <div class="checkout-left">
        <form id="checkoutForm" class="checkout-form" onsubmit="event.preventDefault(); checkout();">
          <!-- Customer & Shipping -->
          <section class="checkout-card checkout-section">
            <h2 class="checkout-card-title"><?= htmlspecialchars(t("checkout.customer_shipping", "Customer & Shipping Information"), ENT_QUOTES, 'UTF-8') ?></h2>
            <div class="checkout-fields">
              <div class="checkout-field">
                <label for="full_name"><?= htmlspecialchars(t("checkout.full_name", "Full Name"), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="text" id="full_name" name="full_name" placeholder="<?= htmlspecialchars(t("checkout.full_name_placeholder", "John Doe"), ENT_QUOTES, 'UTF-8') ?>" required>
              </div>
              <div class="checkout-field">
                <label for="email"><?= htmlspecialchars(t("checkout.email", "Email"), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="email" id="email" name="email" placeholder="<?= htmlspecialchars(t("checkout.email_placeholder", "john@example.com"), ENT_QUOTES, 'UTF-8') ?>" required>
              </div>
              <div class="checkout-field">
                <label for="phone_number"><?= htmlspecialchars(t("checkout.phone", "Phone"), ENT_QUOTES, 'UTF-8') ?></label>
                <div class="checkout-phone-group">
                  <select
                    id="phone_country"
                    name="phone_country"
                    class="checkout-phone-country"
                    aria-label="<?= htmlspecialchars(t("checkout.country", "Country"), ENT_QUOTES, 'UTF-8') ?>"
                  >
                    <option value="TR" data-dial="+90" data-len="10">🇹🇷 +90 Türkiye</option>
                    <option value="US" data-dial="+1" data-len="10">🇺🇸 +1 United States</option>
                    <option value="GB" data-dial="+44" data-len="10">🇬🇧 +44 United Kingdom</option>
                    <option value="DE" data-dial="+49" data-len="11">🇩🇪 +49 Germany</option>
                    <option value="FR" data-dial="+33" data-len="9">🇫🇷 +33 France</option>
                    <option value="IT" data-dial="+39" data-len="10">🇮🇹 +39 Italy</option>
                    <option value="ES" data-dial="+34" data-len="9">🇪🇸 +34 Spain</option>
                    <option value="NL" data-dial="+31" data-len="9">🇳🇱 +31 Netherlands</option>
                    <option value="AE" data-dial="+971" data-len="9">🇦🇪 +971 UAE</option>
                    <option value="SA" data-dial="+966" data-len="9">🇸🇦 +966 Saudi Arabia</option>
                  </select>
                  <input
                    type="tel"
                    id="phone_number"
                    name="phone_number"
                    class="checkout-phone-number"
                    inputmode="numeric"
                    autocomplete="tel-national"
                    placeholder="<?= htmlspecialchars(t("checkout.phone_placeholder", "Phone number"), ENT_QUOTES, 'UTF-8') ?>"
                    maxlength="10"
                  >
                </div>
              </div>
              <div class="checkout-field checkout-field--full">
                <label for="address"><?= htmlspecialchars(t("checkout.address", "Address"), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="text" id="address" name="address" placeholder="<?= htmlspecialchars(t("checkout.address_placeholder", "123 Main Street, Apt 4"), ENT_QUOTES, 'UTF-8') ?>" required>
              </div>
              <div class="checkout-field">
                <label for="city"><?= htmlspecialchars(t("checkout.city", "City"), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="text" id="city" name="city" placeholder="<?= htmlspecialchars(t("checkout.city_placeholder", "New York"), ENT_QUOTES, 'UTF-8') ?>" required>
              </div>
              <div class="checkout-field">
                <label for="zip"><?= htmlspecialchars(t("checkout.postal_code", "Postal Code"), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="text" id="zip" name="zip" placeholder="<?= htmlspecialchars(t("checkout.postal_code_placeholder", "10001"), ENT_QUOTES, 'UTF-8') ?>" required>
              </div>
            </div>
          </section>

          <!-- Payment -->
          <section class="checkout-card checkout-section">
            <h2 class="checkout-card-title"><?= htmlspecialchars(t("checkout.payment", "Payment"), ENT_QUOTES, 'UTF-8') ?></h2>
            <div class="payment-methods">
              <label class="payment-method payment-method--active">
                <input type="radio" name="payment" value="card" checked>
                <span class="payment-method-label"><?= htmlspecialchars(t("checkout.credit_card", "Credit Card"), ENT_QUOTES, 'UTF-8') ?></span>
              </label>
              <label class="payment-method">
                <input type="radio" name="payment" value="paypal">
                <span class="payment-method-label">PayPal</span>
              </label>
            </div>

            <div class="payment-fields" id="paymentFields">
              <div class="checkout-field checkout-field--full">
                <label for="card_number"><?= htmlspecialchars(t("checkout.card_number", "Card Number"), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="text" id="card_number" name="card_number" placeholder="<?= htmlspecialchars(t("checkout.card_number_placeholder", "•••• •••• •••• ••••"), ENT_QUOTES, 'UTF-8') ?>" maxlength="19" inputmode="numeric" autocomplete="cc-number" pattern="[0-9 ]*">
              </div>
              <div class="checkout-field-row">
                <div class="checkout-field">
                  <label for="expiry"><?= htmlspecialchars(t("checkout.expiry_date", "Expiry Date"), ENT_QUOTES, 'UTF-8') ?></label>
                  <input type="text" id="expiry" name="expiry" placeholder="<?= htmlspecialchars(t("checkout.expiry_date_placeholder", "MM/YY"), ENT_QUOTES, 'UTF-8') ?>" maxlength="5">
                </div>
                <div class="checkout-field">
                  <label for="cvv">CVV</label>
                  <input type="text" id="cvv" name="cvv" placeholder="<?= htmlspecialchars(t("checkout.cvv_placeholder", "123"), ENT_QUOTES, 'UTF-8') ?>" maxlength="4" inputmode="numeric" autocomplete="cc-csc" pattern="[0-9]*">
                </div>
              </div>
              <div class="checkout-field checkout-field--full">
                <label for="card_holder"><?= htmlspecialchars(t("checkout.card_holder", "Card Holder Name"), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="text" id="card_holder" name="card_holder" placeholder="<?= htmlspecialchars(t("checkout.card_holder_placeholder", "John Doe"), ENT_QUOTES, 'UTF-8') ?>">
              </div>
            </div>
          </section>

          <button type="submit" class="checkout-submit" id="checkoutSubmit">
            <?= htmlspecialchars(t("checkout.complete_purchase", "Complete Purchase"), ENT_QUOTES, 'UTF-8') ?>
          </button>

          <div class="checkout-trust">
            <span class="trust-item">🔒 <?= htmlspecialchars(t("checkout.trust.secure", "Secure Payment"), ENT_QUOTES, 'UTF-8') ?></span>
            <span class="trust-item">🚚 <?= htmlspecialchars(t("checkout.trust.fast_shipping", "Fast Shipping"), ENT_QUOTES, 'UTF-8') ?></span>
            <span class="trust-item">↩️ <?= htmlspecialchars(t("checkout.trust.easy_returns", "Easy Returns"), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        </form>
      </div>

      <!-- Right: Order Summary (sticky) -->
      <aside class="checkout-right">
        <div class="checkout-summary-card" id="checkoutSummaryCard">
          <h2 class="checkout-summary-title"><?= htmlspecialchars(t("checkout.order_summary", "Order Summary"), ENT_QUOTES, 'UTF-8') ?></h2>
          <div id="checkoutCartSummary" class="checkout-summary-content"></div>
        </div>
      </aside>
    </div>
  </div>
</main>

<?php include 'includes/footer.php'; ?>
