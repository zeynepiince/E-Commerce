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
    $user_id = 1; // temporary static user

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

include 'includes/header.php';
?>

<section class="products">
  <h3>Checkout</h3>
  <p class="subtitle">
    Review your cart and enter your address and payment details to place your order.
  </p>

  <div class="product-detail">
    <div class="checkout-card">
      <h4>Order summary</h4>
      <div id="checkoutCartSummary"></div>
    </div>

    <div class="product-detail-info checkout-card">
      <form id="checkoutForm" onsubmit="event.preventDefault(); checkout();">
        <h4>Shipping address</h4>
        <div class="filter-group">
          <label for="full_name">Full name</label>
          <input type="text" id="full_name" name="full_name" required>
        </div>
        <div class="filter-group">
          <label for="address">Address</label>
          <input type="text" id="address" name="address" required>
        </div>
        <div class="filter-group">
          <label for="city">City</label>
          <input type="text" id="city" name="city" required>
        </div>
        <div class="filter-group">
          <label for="zip">Postal code</label>
          <input type="text" id="zip" name="zip" required>
        </div>

        <h4 style="margin-top:20px;">Payment</h4>
        <div class="filter-group">
          <label for="card_number">Card number</label>
          <input type="text" id="card_number" name="card_number" placeholder="•••• •••• •••• ••••">
        </div>
        <div class="filter-group">
          <label for="expiration">Expiration</label>
          <input type="text" id="expiration" name="expiration" placeholder="MM/YY">
        </div>

        <button type="submit" class="btn-full-width" style="max-width:260px;margin-top:20px;">
          Place order
        </button>
      </form>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>

