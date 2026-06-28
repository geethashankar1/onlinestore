<?php
// cart.php
require_once 'config/db.php'; // Session is started in db.php

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = array();
}

// Add item to cart
if (isset($_GET['action']) && $_GET['action'] == 'add' && isset($_GET['id'])) {
    $product_id = intval($_GET['id']);

    // Check if product exists and get details (optional, but good for price consistency)
    $stmt = $conn->prepare("SELECT id, name, price, image FROM products WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($product_details = $result->fetch_assoc()) {
            if (isset($_SESSION['cart'][$product_id])) {
                $_SESSION['cart'][$product_id]['quantity']++;
            } else {
                $_SESSION['cart'][$product_id] = array(
                    'id' => $product_id,
                    'name' => $product_details['name'],
                    'price' => $product_details['price'],
                    'image' => $product_details['image'],
                    'quantity' => 1
                );
            }
        }
        $stmt->close();
    }
    // Redirect to cart page to prevent re-adding on refresh
    header('Location: cart.php');
    exit;
}

// Update item quantity
if (isset($_POST['action']) && $_POST['action'] == 'update' && isset($_POST['product_id'])) {
    $product_id_update = intval($_POST['product_id']);
    $quantity_update = intval($_POST['quantity']);

    if ($quantity_update > 0 && isset($_SESSION['cart'][$product_id_update])) {
        $_SESSION['cart'][$product_id_update]['quantity'] = $quantity_update;
    } elseif ($quantity_update <= 0 && isset($_SESSION['cart'][$product_id_update])) {
        unset($_SESSION['cart'][$product_id_update]); // Remove if quantity is 0 or less
    }
    header('Location: cart.php');
    exit;
}


// Remove item from cart
if (isset($_GET['action']) && $_GET['action'] == 'remove' && isset($_GET['id'])) {
    $product_id_remove = intval($_GET['id']);
    if (isset($_SESSION['cart'][$product_id_remove])) {
        unset($_SESSION['cart'][$product_id_remove]);
    }
    header('Location: cart.php');
    exit;
}

// Clear cart
if (isset($_GET['action']) && $_GET['action'] == 'clear') {
    $_SESSION['cart'] = array();
    header('Location: cart.php');
    exit;
}

$cart_items = $_SESSION['cart'];
$total_price = 0;
$page_title = "Your Cart";
include 'header.php';
?>
  <section class="page">
    <div class="container">
      <div class="page-head"><div class="eyebrow">Your selection</div><h2 class="mb-0">Shopping Cart</h2></div>

      <?php if (!empty($cart_items)): ?>
        <div class="row g-4">
          <div class="col-lg-8">
            <?php foreach ($cart_items as $item): ?>
              <div class="cart-row">
                <?php
                $img = (!empty($item['image']) && file_exists('uploads/' . $item['image']))
                     ? 'uploads/' . htmlspecialchars($item['image'])
                     : 'uploads/default_placeholder.png';
                echo "<img src='" . $img . "' alt='" . htmlspecialchars($item['name']) . "'>";
                ?>
                <div class="flex-grow-1">
                  <div class="c-name"><?php echo htmlspecialchars($item['name']); ?></div>
                  <div class="c-meta">$<?php echo htmlspecialchars($item['price']); ?> each</div>
                  <form action="cart.php" method="post" class="d-flex align-items-center gap-2 mt-2 flex-wrap">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                    <label class="c-meta mb-0">Qty</label>
                    <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" class="form-control" style="width:80px;">
                    <button type="submit" class="btn btn-sm">Update</button>
                    <a href="cart.php?action=remove&id=<?php echo $item['id']; ?>" class="btn btn-sm btn-soft-danger">Remove</a>
                  </form>
                </div>
                <div class="text-end">
                  <div class="c-meta">Subtotal</div>
                  <div class="c-name">$<?php
                    $subtotal = $item['price'] * $item['quantity'];
                    echo number_format($subtotal, 2);
                    $total_price += $subtotal;
                  ?></div>
                </div>
              </div>
            <?php endforeach; ?>
            <a href="cart.php?action=clear" class="btn btn-soft-warn btn-sm">Clear cart</a>
          </div>

          <div class="col-lg-4">
            <div class="cart-summary">
              <h3 class="serif mb-3" style="font-size:1.2rem;">Summary</h3>
              <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="cart-total">Total</span>
                <span class="cart-total" style="color:var(--brass-deep);">$<?php echo number_format($total_price, 2); ?></span>
              </div>
              <a href="checkout.php" class="btn w-100">Proceed to checkout</a>
              <a href="index.php" class="d-block text-center mt-3 nav-link-x">Continue shopping</a>
            </div>
          </div>
        </div>
      <?php else: ?>
        <div class="surface p-5 text-center">
          <p class="mb-3" style="color:var(--muted);">Your cart is empty.</p>
          <a href="index.php" class="btn">Continue shopping</a>
        </div>
      <?php endif; ?>
    </div>
  </section>
<?php include 'footer.php'; $conn->close(); ?>
