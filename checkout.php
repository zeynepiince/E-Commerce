<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'functions.php';

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
            $stmtItem->execute([
                $order_id,
                $item["id"],
                $item["qty"],
                $item["price"]
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

$page_title = "Checkout – STORY";
$is_checkout = true;

include 'includes/header.php';
?>

<link rel="stylesheet" href="assets/css/checkout.css">

<main class="checkout-page">
  <div class="checkout-container">
    <h1 class="checkout-title">Checkout</h1>
    <p class="checkout-subtitle">Review your order and complete your purchase securely.</p>

    <div class="checkout-layout">
      <!-- Left: Customer & Payment -->
      <div class="checkout-left">
        <form id="checkoutForm" class="checkout-form" onsubmit="event.preventDefault(); checkout();">
          <!-- Customer & Shipping -->
          <section class="checkout-card checkout-section">
            <h2 class="checkout-card-title">Customer & Shipping Information</h2>
            <div class="checkout-fields">
              <div class="checkout-field">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" placeholder="John Doe" required>
              </div>
              <div class="checkout-field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="john@example.com" required>
              </div>
              <div class="checkout-field">
                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone" placeholder="+1 (555) 000-0000">
              </div>
              <div class="checkout-field checkout-field--full">
                <label for="address">Address</label>
                <input type="text" id="address" name="address" placeholder="123 Main Street, Apt 4" required>
              </div>
              <div class="checkout-field">
                <label for="city">City</label>
                <input type="text" id="city" name="city" placeholder="New York" required>
              </div>
              <div class="checkout-field">
                <label for="zip">Postal Code</label>
                <input type="text" id="zip" name="zip" placeholder="10001" required>
              </div>
            </div>
          </section>

          <!-- Payment -->
          <section class="checkout-card checkout-section">
            <h2 class="checkout-card-title">Payment</h2>
            <div class="payment-methods">
              <label class="payment-method payment-method--active">
                <input type="radio" name="payment" value="card" checked>
                <span class="payment-method-label">Credit Card</span>
              </label>
              <label class="payment-method">
                <input type="radio" name="payment" value="paypal">
                <span class="payment-method-label">PayPal</span>
              </label>
            </div>

            <div class="payment-fields" id="paymentFields">
              <div class="checkout-field checkout-field--full">
                <label for="card_number">Card Number</label>
                <input type="text" id="card_number" name="card_number" placeholder="•••• •••• •••• ••••" maxlength="19">
              </div>
              <div class="checkout-field-row">
                <div class="checkout-field">
                  <label for="expiry">Expiry Date</label>
                  <input type="text" id="expiry" name="expiry" placeholder="MM/YY" maxlength="5">
                </div>
                <div class="checkout-field">
                  <label for="cvv">CVV</label>
                  <input type="text" id="cvv" name="cvv" placeholder="123" maxlength="4">
                </div>
              </div>
              <div class="checkout-field checkout-field--full">
                <label for="card_holder">Card Holder Name</label>
                <input type="text" id="card_holder" name="card_holder" placeholder="John Doe">
              </div>
            </div>
          </section>

          <button type="submit" class="checkout-submit" id="checkoutSubmit">
            Complete Purchase
          </button>

          <div class="checkout-trust">
            <span class="trust-item">🔒 Secure Payment</span>
            <span class="trust-item">🚚 Fast Shipping</span>
            <span class="trust-item">↩️ Easy Returns</span>
          </div>
        </form>
      </div>

      <!-- Right: Order Summary (sticky) -->
      <aside class="checkout-right">
        <div class="checkout-summary-card" id="checkoutSummaryCard">
          <h2 class="checkout-summary-title">Order Summary</h2>
          <div id="checkoutCartSummary" class="checkout-summary-content"></div>
        </div>
      </aside>
    </div>
  </div>
</main>

<?php include 'includes/footer.php'; ?>
