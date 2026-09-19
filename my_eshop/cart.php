<?php
// cart.php
require_once 'config/db.php';

// Signed in, the cart lives in the database and is the same cart the mobile
// app sees; as a guest it lives in the session until they log in. Both go
// through config/cart.php so the two never drift apart.

// Add item to cart
if (isset($_GET['action']) && $_GET['action'] == 'add' && isset($_GET['id'])) {
    cart_add($conn, intval($_GET['id']));
    header('Location: cart.php');
    exit;
}

// Update item quantity (POST from form)
if (isset($_POST['action']) && $_POST['action'] == 'update' && isset($_POST['product_id'])) {
    cart_set_qty($conn, intval($_POST['product_id']), intval($_POST['quantity']));
    header('Location: cart.php');
    exit;
}

// Remove item
if (isset($_GET['action']) && $_GET['action'] == 'remove' && isset($_GET['id'])) {
    cart_remove($conn, intval($_GET['id']));
    header('Location: cart.php');
    exit;
}

// Clear cart
if (isset($_GET['action']) && $_GET['action'] == 'clear') {
    cart_clear($conn);
    header('Location: cart.php');
    exit;
}

$cart_items  = cart_items($conn);
$total_price = 0;
$page_title  = "Your Cart";

include 'header.php';
?>

<style>
.qty-wrap{display:flex;align-items:center;gap:0;border:1px solid var(--line);border-radius:4px;overflow:hidden;width:fit-content;}
.qty-wrap input[type=number]{width:52px;border:none;text-align:center;padding:.3rem .25rem;font-size:.95rem;-moz-appearance:textfield;}
.qty-wrap input[type=number]::-webkit-outer-spin-button,
.qty-wrap input[type=number]::-webkit-inner-spin-button{-webkit-appearance:none;margin:0;}
.qty-wrap input[type=number]:focus{outline:none;background:var(--ivory-2);}
.qty-btn{background:var(--ivory-2);border:none;width:34px;height:36px;font-size:1.1rem;
  cursor:pointer;color:var(--ink);display:flex;align-items:center;justify-content:center;
  transition:background .15s;}
.qty-btn:hover{background:var(--line);}
</style>

<section class="page">
  <div class="container">
    <div class="page-head">
      <div class="eyebrow">Your selection</div>
      <h2 class="mb-0">Shopping Cart</h2>
    </div>

    <?php if (!empty($cart_items)): ?>
      <div class="row g-4">
        <div class="col-lg-8">

          <?php foreach ($cart_items as $item):
            $img = htmlspecialchars(media_url($item['image'] ?? ''));
            $subtotal = $item['price'] * $item['quantity'];
            $total_price += $subtotal;
          ?>
          <div class="cart-row"
               data-id="<?php echo $item['id']; ?>"
               data-price="<?php echo $item['price']; ?>">

            <img src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">

            <div class="flex-grow-1">
              <div class="c-name"><?php echo htmlspecialchars($item['name']); ?></div>
              <div class="c-meta">&#8377;<?php echo number_format($item['price'], 2); ?> each</div>

              <!-- Qty controls + hidden form -->
              <form id="qty-form-<?php echo $item['id']; ?>"
                    action="cart.php" method="post"
                    class="d-flex align-items-center gap-3 mt-2 flex-wrap">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">

                <div class="qty-wrap">
                  <button type="button" class="qty-btn qty-minus"
                          data-id="<?php echo $item['id']; ?>">&#8722;</button>
                  <input type="number" name="quantity"
                         value="<?php echo $item['quantity']; ?>"
                         min="1"
                         class="qty-input"
                         data-id="<?php echo $item['id']; ?>">
                  <button type="button" class="qty-btn qty-plus"
                          data-id="<?php echo $item['id']; ?>">+</button>
                </div>

                <a href="cart.php?action=remove&id=<?php echo $item['id']; ?>"
                   class="btn btn-sm btn-soft-danger">Remove</a>
              </form>
            </div>

            <div class="text-end flex-shrink-0">
              <div class="c-meta">Subtotal</div>
              <div class="c-name" id="subtotal-<?php echo $item['id']; ?>">
                &#8377;<?php echo number_format($subtotal, 2); ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>

          <a href="cart.php?action=clear" class="btn btn-soft-warn btn-sm mt-2">Clear cart</a>
        </div>

        <div class="col-lg-4">
          <div class="cart-summary">
            <h3 class="serif mb-3" style="font-size:1.2rem;">Summary</h3>
            <div class="d-flex justify-content-between align-items-center mb-3">
              <span class="cart-total">Total</span>
              <span class="cart-total" id="cart-total" style="color:var(--text);">
                &#8377;<?php echo number_format($total_price, 2); ?>
              </span>
            </div>
            <a href="checkout.php" class="btn w-100">Proceed to checkout</a>
            <a href="index.php"
               class="d-block text-center mt-3 nav-link-x">Continue shopping</a>
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

<script>
(function () {
  function fmt(n) {
    return '₹' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  function getQty(id) {
    var inp = document.querySelector('.qty-input[data-id="' + id + '"]');
    return inp ? Math.max(1, parseInt(inp.value) || 1) : 1;
  }

  function getPrice(id) {
    var row = document.querySelector('.cart-row[data-id="' + id + '"]');
    return row ? parseFloat(row.dataset.price) : 0;
  }

  function refreshSubtotal(id) {
    var el = document.getElementById('subtotal-' + id);
    if (el) el.textContent = fmt(getQty(id) * getPrice(id));
  }

  function refreshTotal() {
    var total = 0;
    document.querySelectorAll('.cart-row').forEach(function (row) {
      var id = row.dataset.id;
      total += getQty(id) * getPrice(id);
    });
    var el = document.getElementById('cart-total');
    if (el) el.textContent = fmt(total);
  }

  function submitForm(id) {
    var form = document.getElementById('qty-form-' + id);
    if (form) form.submit();
  }

  // +/- buttons
  document.querySelectorAll('.qty-plus').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id   = this.dataset.id;
      var inp  = document.querySelector('.qty-input[data-id="' + id + '"]');
      var val  = parseInt(inp.value) || 1;
      inp.value = val + 1;
      refreshSubtotal(id);
      refreshTotal();
      submitForm(id);
    });
  });

  document.querySelectorAll('.qty-minus').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id  = this.dataset.id;
      var inp = document.querySelector('.qty-input[data-id="' + id + '"]');
      var val = parseInt(inp.value) || 1;
      if (val <= 1) {
        window.location.href = 'cart.php?action=remove&id=' + id;
        return;
      }
      inp.value = val - 1;
      refreshSubtotal(id);
      refreshTotal();
      submitForm(id);
    });
  });

  // Manual input change
  document.querySelectorAll('.qty-input').forEach(function (inp) {
    inp.addEventListener('change', function () {
      var id  = this.dataset.id;
      var val = parseInt(this.value) || 1;
      if (val < 1) { this.value = 1; val = 1; }
      this.value = val;
      refreshSubtotal(id);
      refreshTotal();
      submitForm(id);
    });
  });
})();
</script>

<?php include 'footer.php'; $conn->close(); ?>
