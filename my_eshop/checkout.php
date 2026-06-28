<?php
// checkout.php
require_once 'config/db.php';
require_once 'config/payment.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['checkout_redirect_message'] = "Please login to proceed to checkout.";
    header("Location: login.php");
    exit;
}
if (empty($_SESSION['cart']) && !isset($_GET['razorpay_success'])) {
    header("Location: cart.php");
    exit;
}

$errors       = [];
$old          = [];
$order_done   = false;
$order_id     = null;
$cart_items   = $_SESSION['cart'] ?? [];
$total_amount = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cart_items));

// ── Razorpay redirect (after modal payment + verify) ──────────────────────
if (isset($_GET['razorpay_success'], $_SESSION['last_order_id'])) {
    $order_done  = true;
    $order_id    = (int)$_SESSION['last_order_id'];
    $cart_items  = [];
    unset($_SESSION['last_order_id']);
}

// ── Authorize.Net card payment (form POST) ────────────────────────────────
if (!$order_done && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = $_POST['payment_method'] ?? 'card';

    $s_street = trim($_POST['s_street'] ?? '');
    $s_city   = trim($_POST['s_city']   ?? '');
    $s_state  = trim($_POST['s_state']  ?? '');
    $s_pin    = trim($_POST['s_pin']    ?? '');
    $s_phone  = trim($_POST['s_phone']  ?? '');

    $billing_same = isset($_POST['billing_same']);
    if ($billing_same) {
        $b_street = $s_street; $b_city  = $s_city;
        $b_state  = $s_state;  $b_pin   = $s_pin;
        $b_phone  = $s_phone;
    } else {
        $b_street = trim($_POST['b_street'] ?? '');
        $b_city   = trim($_POST['b_city']   ?? '');
        $b_state  = trim($_POST['b_state']  ?? '');
        $b_pin    = trim($_POST['b_pin']    ?? '');
        $b_phone  = trim($_POST['b_phone']  ?? '');
    }

    $old = compact('s_street','s_city','s_state','s_pin','s_phone',
                   'b_street','b_city','b_state','b_pin','b_phone',
                   'billing_same','payment_method');

    // Validate shipping
    if ($s_street === '') $errors['s_street'] = 'Street address is required.';
    if ($s_city   === '') $errors['s_city']   = 'City is required.';
    if ($s_state  === '') $errors['s_state']  = 'State is required.';
    if ($s_pin    === '') $errors['s_pin']    = 'PIN / ZIP code is required.';
    elseif (!preg_match('/^\d{4,10}$/', $s_pin)) $errors['s_pin'] = 'PIN must be 4–10 digits.';
    if ($s_phone  === '') $errors['s_phone']  = 'Phone number is required.';
    elseif (!preg_match('/^\+?[\d\s\-\(\)]{7,15}$/', $s_phone)) $errors['s_phone'] = 'Enter a valid phone number.';

    // Validate billing (skip when same as shipping)
    if (!$billing_same) {
        if ($b_street === '') $errors['b_street'] = 'Street address is required.';
        if ($b_city   === '') $errors['b_city']   = 'City is required.';
        if ($b_state  === '') $errors['b_state']  = 'State is required.';
        if ($b_pin    === '') $errors['b_pin']    = 'PIN / ZIP code is required.';
        elseif (!preg_match('/^\d{4,10}$/', $b_pin)) $errors['b_pin'] = 'PIN must be 4–10 digits.';
        if ($b_phone  === '') $errors['b_phone']  = 'Phone number is required.';
        elseif (!preg_match('/^\+?[\d\s\-\(\)]{7,15}$/', $b_phone)) $errors['b_phone'] = 'Enter a valid phone number.';
    }

    // Card payment via Authorize.Net
    if ($payment_method === 'card' && empty($errors)) {
        $opaque_d = trim($_POST['opaque_descriptor'] ?? '');
        $opaque_v = trim($_POST['opaque_value']      ?? '');

        if (!$opaque_d || !$opaque_v) {
            $errors['payment'] = 'Card tokenisation failed — please refresh the page and try again.';
        } else {
            $charge = authnet_charge($total_amount, $opaque_d, $opaque_v);
            if (!$charge['ok']) {
                $errors['payment'] = $charge['message'];
            } else {
                $addr = "{$s_street}\n{$s_city}, {$s_state} – {$s_pin}\nPhone: {$s_phone}";
                [$ok, $oid] = create_db_order($conn, (int)$_SESSION['user_id'],
                                              $total_amount, $addr, 'Paid', $cart_items);
                if ($ok) {
                    $_SESSION['cart'] = [];
                    $order_done = true;
                    $order_id   = $oid;
                } else {
                    $txn = $charge['transaction_id'] ?? 'unknown';
                    $errors['payment'] = "Order creation failed after payment. Contact support — transaction ID: {$txn}";
                }
            }
        }
    }
}

// ── Display helpers ───────────────────────────────────────────────────────
$bs_checked  = empty($old) || !empty($old['billing_same']);
$bf_hidden   = $bs_checked ? 'display:none;' : '';
$authnet_on  = AUTHNET_LOGIN_ID && AUTHNET_CLIENT_KEY;
$razorpay_on = RAZORPAY_KEY_ID  && RAZORPAY_KEY_SECRET;
$active_tab  = $old['payment_method'] ?? ($authnet_on ? 'card' : 'razorpay');

function fclass(array $errors, string $k): string {
    return 'form-control' . (isset($errors[$k]) ? ' co-invalid' : '');
}
function fval(array $old, string $k): string { return htmlspecialchars($old[$k] ?? ''); }
function ferr(array $errors, string $k): string {
    return isset($errors[$k])
        ? '<div class="co-field-err">' . htmlspecialchars($errors[$k]) . '</div>'
        : '';
}

$page_title = "Checkout";
include 'header.php';
?>

<style>
/* ── Checkout ──────────────────────────────────────────────── */
.co-section{background:var(--card);border:1px solid var(--line);border-radius:4px;
  box-shadow:var(--shadow-sm);padding:1.6rem 1.8rem;margin-bottom:1.25rem;}
.co-title{font-family:'Fraunces',serif;font-size:1.1rem;font-weight:500;
  margin:0 0 1.2rem;padding-bottom:.75rem;border-bottom:1px solid var(--line);}
.co-field-err{font-size:.82rem;color:var(--danger);margin-top:.3rem;}
.co-invalid{border-color:var(--danger)!important;}
.co-invalid:focus{box-shadow:0 0 0 .15rem rgba(155,59,54,.18)!important;}
.same-toggle{display:flex;align-items:center;gap:.5rem;cursor:pointer;
  font-size:.88rem;font-weight:600;color:var(--ink);user-select:none;}
.same-toggle input{width:1rem;height:1rem;accent-color:var(--green);cursor:pointer;flex-shrink:0;}

/* Payment method tabs */
.pay-tabs{display:flex;gap:0;border:1px solid var(--line);border-radius:4px;overflow:hidden;margin-bottom:1.4rem;}
.pay-tab{flex:1;padding:.75rem 1rem;font-weight:700;font-size:.88rem;background:var(--ivory-2);
  border:0;border-right:1px solid var(--line);color:var(--muted);cursor:pointer;transition:all .2s;}
.pay-tab:last-child{border-right:0;}
.pay-tab.active{background:var(--green);color:#F6F2EA;}
.pay-tab:hover:not(.active){background:var(--ivory);color:var(--ink);}

/* Card type radio buttons */
.card-type-group{display:flex;gap:.75rem;margin-bottom:1.2rem;}
.ct-label{flex:1;cursor:pointer;}
.ct-label input{display:none;}
.ct-box{border:1.5px solid var(--line);border-radius:4px;padding:.7rem 1rem;
  text-align:center;font-weight:700;font-size:.88rem;color:var(--muted);
  background:var(--ivory-2);transition:all .2s;}
.ct-label input:checked+.ct-box{border-color:var(--green);color:var(--green);background:#EEF3EE;}
.ct-box:hover{border-color:var(--brass);color:var(--ink);}

/* Card brand badge */
.card-brand-badge{display:inline-block;font-size:.7rem;font-weight:700;
  letter-spacing:.06em;text-transform:uppercase;padding:.15rem .55rem;border-radius:3px;
  background:var(--ivory);border:1px solid var(--line);color:var(--muted);
  margin-left:.5rem;vertical-align:middle;transition:all .2s;}
.card-brand-badge.detected{color:var(--green);border-color:#CBDAC7;background:#EEF3EE;}

/* Razorpay section */
.rzp-box{background:var(--ivory-2);border:1px solid var(--line);border-radius:4px;
  padding:1.6rem;text-align:center;}
.rzp-btn{background:#072654;color:#fff;border:0;border-radius:4px;
  padding:.9rem 2rem;font-size:1rem;font-weight:700;cursor:pointer;
  letter-spacing:.02em;transition:background .2s;min-width:220px;}
.rzp-btn:hover{background:#0b3a7a;}
.rzp-btn:disabled{opacity:.6;cursor:not-allowed;}

/* Success card */
.order-success-icon{width:68px;height:68px;border-radius:50%;background:#EEF3EE;
  display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;
  font-size:2rem;color:var(--green);}

@media(min-width:992px){.summary-sticky{position:sticky;top:90px;}}
</style>

<section class="page">
  <div class="container">
    <div class="page-head reveal d1">
      <div class="eyebrow">Almost there</div>
      <h2 class="mb-0">Checkout</h2>
    </div>

    <?php if ($order_done): ?>
    <!-- ── Order success ──────────────────────────────────────────────── -->
    <div class="surface p-5 text-center reveal d2" style="max-width:520px;margin:0 auto;">
      <div class="order-success-icon">✓</div>
      <div class="eyebrow mb-2">Payment Confirmed</div>
      <h3 class="serif mb-2">Thank you for your purchase!</h3>
      <p style="color:var(--muted);">
        Your order <strong>#<?php echo $order_id; ?></strong> has been placed and payment received.
      </p>
      <div class="d-flex gap-2 justify-content-center mt-4">
        <a href="profile.php" class="btn">View My Orders</a>
        <a href="index.php"   class="btn btn-ghost">Continue Shopping</a>
      </div>
    </div>

    <?php elseif (!empty($cart_items)): ?>
    <!-- ── Checkout form ──────────────────────────────────────────────── -->
    <?php if (isset($errors['payment'])): ?>
      <div class="alert alert-danger mb-3"><?php echo htmlspecialchars($errors['payment']); ?></div>
    <?php endif; ?>
    <?php if (!$authnet_on && !$razorpay_on): ?>
      <div class="alert alert-danger mb-3">
        ⚠ No payment gateway is configured. Set <code>AUTHNET_*</code> or <code>RAZORPAY_*</code>
        environment variables and restart the container.
      </div>
    <?php endif; ?>

    <form action="checkout.php" method="post" id="checkoutForm" novalidate>
      <input type="hidden" name="payment_method" id="paymentMethod"
             value="<?php echo htmlspecialchars($active_tab); ?>">
      <!-- Authorize.Net opaqueData tokens (populated by Accept.js) -->
      <input type="hidden" name="opaque_descriptor" id="opaqueDescriptor">
      <input type="hidden" name="opaque_value"      id="opaqueValue">

      <div class="row g-4 align-items-start">

        <!-- ── LEFT: form ──────────────────────────────────── -->
        <div class="col-lg-7">

          <!-- ┌─ ① Shipping Address ──────────────────────── -->
          <div class="co-section reveal d2">
            <h3 class="co-title">① Shipping Address</h3>
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label" for="s_street">Street Address</label>
                <input id="s_street" name="s_street" type="text"
                       class="<?php echo fclass($errors,'s_street'); ?>"
                       placeholder="123 Main Street, Apt 4B"
                       value="<?php echo fval($old,'s_street'); ?>">
                <?php echo ferr($errors,'s_street'); ?>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="s_city">City</label>
                <input id="s_city" name="s_city" type="text"
                       class="<?php echo fclass($errors,'s_city'); ?>"
                       placeholder="Mumbai"
                       value="<?php echo fval($old,'s_city'); ?>">
                <?php echo ferr($errors,'s_city'); ?>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="s_state">State</label>
                <input id="s_state" name="s_state" type="text"
                       class="<?php echo fclass($errors,'s_state'); ?>"
                       placeholder="Maharashtra"
                       value="<?php echo fval($old,'s_state'); ?>">
                <?php echo ferr($errors,'s_state'); ?>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="s_pin">PIN / ZIP Code</label>
                <input id="s_pin" name="s_pin" type="text" inputmode="numeric"
                       class="<?php echo fclass($errors,'s_pin'); ?>"
                       placeholder="400001"
                       value="<?php echo fval($old,'s_pin'); ?>">
                <?php echo ferr($errors,'s_pin'); ?>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="s_phone">Phone Number</label>
                <input id="s_phone" name="s_phone" type="tel"
                       class="<?php echo fclass($errors,'s_phone'); ?>"
                       placeholder="+91 98765 43210"
                       value="<?php echo fval($old,'s_phone'); ?>">
                <?php echo ferr($errors,'s_phone'); ?>
              </div>
            </div>
          </div>

          <!-- ┌─ ② Billing Address ────────────────────────── -->
          <div class="co-section reveal d3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2"
                 style="padding-bottom:.75rem;border-bottom:1px solid var(--line);margin-bottom:1.2rem;">
              <h3 class="co-title mb-0" style="border:0;padding:0;">② Billing Address</h3>
              <label class="same-toggle">
                <input type="checkbox" name="billing_same" id="billingSame"
                       <?php echo $bs_checked ? 'checked' : ''; ?>>
                Same as shipping address
              </label>
            </div>
            <div id="billingFields" style="<?php echo $bf_hidden; ?>">
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label" for="b_street">Street Address</label>
                  <input id="b_street" name="b_street" type="text"
                         class="<?php echo fclass($errors,'b_street'); ?>"
                         placeholder="123 Main Street, Apt 4B"
                         value="<?php echo fval($old,'b_street'); ?>">
                  <?php echo ferr($errors,'b_street'); ?>
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="b_city">City</label>
                  <input id="b_city" name="b_city" type="text"
                         class="<?php echo fclass($errors,'b_city'); ?>"
                         placeholder="Mumbai"
                         value="<?php echo fval($old,'b_city'); ?>">
                  <?php echo ferr($errors,'b_city'); ?>
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="b_state">State</label>
                  <input id="b_state" name="b_state" type="text"
                         class="<?php echo fclass($errors,'b_state'); ?>"
                         placeholder="Maharashtra"
                         value="<?php echo fval($old,'b_state'); ?>">
                  <?php echo ferr($errors,'b_state'); ?>
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="b_pin">PIN / ZIP Code</label>
                  <input id="b_pin" name="b_pin" type="text" inputmode="numeric"
                         class="<?php echo fclass($errors,'b_pin'); ?>"
                         placeholder="400001"
                         value="<?php echo fval($old,'b_pin'); ?>">
                  <?php echo ferr($errors,'b_pin'); ?>
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="b_phone">Phone Number</label>
                  <input id="b_phone" name="b_phone" type="tel"
                         class="<?php echo fclass($errors,'b_phone'); ?>"
                         placeholder="+91 98765 43210"
                         value="<?php echo fval($old,'b_phone'); ?>">
                  <?php echo ferr($errors,'b_phone'); ?>
                </div>
              </div>
            </div>
            <p id="billingSameNote"
               style="color:var(--muted);font-size:.88rem;margin:0;<?php echo $bs_checked ? '' : 'display:none;'; ?>">
              Billing address will match shipping address.
            </p>
          </div>

          <!-- ┌─ ③ Payment ─────────────────────────────────── -->
          <div class="co-section reveal d4">
            <h3 class="co-title">③ Payment</h3>

            <!-- Payment method tabs -->
            <?php if ($authnet_on || $razorpay_on): ?>
            <div class="pay-tabs">
              <?php if ($authnet_on): ?>
              <button type="button" class="pay-tab <?php echo $active_tab === 'card' ? 'active' : ''; ?>"
                      data-tab="card">💳 Credit / Debit Card</button>
              <?php endif; ?>
              <?php if ($razorpay_on): ?>
              <button type="button" class="pay-tab <?php echo $active_tab === 'razorpay' ? 'active' : ''; ?>"
                      data-tab="razorpay">📱 UPI / Netbanking / Wallet</button>
              <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- ── Card tab (Authorize.Net Accept.js) ─────── -->
            <?php if ($authnet_on): ?>
            <div id="tab-card" <?php echo $active_tab !== 'card' ? 'style="display:none;"' : ''; ?>>

              <!-- Credit / Debit selector (UI only — Authorize.Net detects from number) -->
              <div class="card-type-group">
                <label class="ct-label">
                  <input type="radio" name="ui_card_type" value="credit" id="ctCredit" checked>
                  <div class="ct-box">💳 Credit Card</div>
                </label>
                <label class="ct-label">
                  <input type="radio" name="ui_card_type" value="debit" id="ctDebit">
                  <div class="ct-box">🏧 Debit Card</div>
                </label>
              </div>

              <div class="row g-3">
                <!-- NOTE: Card fields have IDs but NO name attributes.
                     Accept.js reads them by ID and returns a one-time token.
                     Raw card data never reaches our server (PCI-compliant). -->
                <div class="col-12">
                  <label class="form-label" for="cardNumber">
                    Card Number
                    <span class="card-brand-badge" id="cardBrandBadge">16 digits</span>
                  </label>
                  <input id="cardNumber" type="text" inputmode="numeric"
                         autocomplete="cc-number" maxlength="19"
                         class="form-control" placeholder="0000  0000  0000  0000">
                  <div class="co-field-err" id="err-cardNumber"></div>
                </div>
                <div class="col-12">
                  <label class="form-label" for="cardName">Cardholder Name</label>
                  <input id="cardName" type="text" autocomplete="cc-name"
                         class="form-control" placeholder="Name as printed on card">
                  <div class="co-field-err" id="err-cardName"></div>
                </div>
                <div class="col-6">
                  <label class="form-label" for="cardExpiry">Expiry Date</label>
                  <input id="cardExpiry" type="text" inputmode="numeric"
                         autocomplete="cc-exp" maxlength="5"
                         class="form-control" placeholder="MM/YY">
                  <div class="co-field-err" id="err-cardExpiry"></div>
                </div>
                <div class="col-6">
                  <label class="form-label" for="cardCvv">
                    CVV <span style="font-weight:400;color:var(--muted);font-size:.82rem;">(3–4 digits)</span>
                  </label>
                  <input id="cardCvv" type="password" inputmode="numeric"
                         autocomplete="cc-csc" maxlength="4"
                         class="form-control" placeholder="•••">
                  <div class="co-field-err" id="err-cardCvv"></div>
                </div>
              </div>
              <div class="co-field-err mt-2" id="err-acceptJs"></div>
            </div>
            <?php endif; ?>

            <!-- ── Razorpay tab ────────────────────────────── -->
            <?php if ($razorpay_on): ?>
            <div id="tab-razorpay" <?php echo $active_tab !== 'razorpay' ? 'style="display:none;"' : ''; ?>>
              <div class="rzp-box">
                <p style="color:var(--muted);margin-bottom:1.2rem;font-size:.92rem;">
                  Pay securely via UPI, Netbanking, Wallets, or Cards through Razorpay.
                  Your shipping details above will be saved with the order.
                </p>
                <button type="button" id="rzpPayBtn" class="rzp-btn">
                  Pay <?php echo RAZORPAY_CURRENCY; ?> <?php echo number_format($total_amount, 2); ?> &nbsp;→
                </button>
                <div class="co-field-err mt-2" id="rzpStatus"></div>
              </div>
            </div>
            <?php endif; ?>

          </div>

          <!-- Submit (shown for card tab only) -->
          <button type="submit" id="cardSubmitBtn" class="btn w-100 reveal d5"
                  style="padding:1rem 1.6rem;font-size:1rem;
                         <?php echo ($active_tab === 'razorpay' && !$authnet_on) ? 'display:none;' : ''; ?>">
            Pay &amp; Place Order · $<?php echo number_format($total_amount, 2); ?>
          </button>

        </div><!-- /col-lg-7 -->

        <!-- ── RIGHT: Order Summary ──────────────────────── -->
        <div class="col-lg-5">
          <div class="cart-summary summary-sticky reveal d2">
            <h3 class="serif mb-3" style="font-size:1.1rem;">Order Summary</h3>
            <?php foreach ($cart_items as $item): ?>
              <div class="d-flex justify-content-between py-2"
                   style="border-bottom:1px solid var(--line);font-size:.9rem;">
                <span><?php echo htmlspecialchars($item['name']); ?>
                      <span style="color:var(--muted);">×<?php echo $item['quantity']; ?></span></span>
                <span>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
              </div>
            <?php endforeach; ?>
            <div class="d-flex justify-content-between align-items-center mt-3 pt-1">
              <span class="cart-total" style="font-size:1.2rem;">Total</span>
              <span class="cart-total" style="font-size:1.2rem;color:var(--brass-deep);">
                $<?php echo number_format($total_amount, 2); ?>
              </span>
            </div>
          </div>
        </div>

      </div><!-- /row -->
    </form>

    <?php else: ?>
    <div class="surface p-5 text-center">
      <p class="mb-3" style="color:var(--muted);">Your cart is empty.</p>
      <a href="cart.php" class="btn">Return to cart</a>
    </div>
    <?php endif; ?>

    <p class="mt-4"><a href="index.php" class="nav-link-x">&larr; Continue shopping</a></p>
  </div>
</section>

<?php if ($authnet_on && !$order_done): ?>
<script src="<?php echo AUTHNET_JS_URL; ?>" charset="utf-8"></script>
<?php endif; ?>
<?php if ($razorpay_on && !$order_done): ?>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<?php endif; ?>

<?php if (!$order_done): ?>
<script>
(function () {
  // ── Constants from PHP ────────────────────────────────────────
  const AUTHNET_ON    = <?php echo json_encode($authnet_on); ?>;
  const RAZORPAY_ON   = <?php echo json_encode($razorpay_on); ?>;
  const AUTHNET_CK    = <?php echo json_encode(AUTHNET_CLIENT_KEY); ?>;
  const AUTHNET_LID   = <?php echo json_encode(AUTHNET_LOGIN_ID); ?>;
  const RZP_CURRENCY  = <?php echo json_encode(RAZORPAY_CURRENCY); ?>;
  const TOTAL_AMOUNT  = <?php echo json_encode($total_amount); ?>;
  const USER_NAME     = <?php echo json_encode($_SESSION['username'] ?? ''); ?>;
  const USER_EMAIL    = <?php echo json_encode($_SESSION['email']    ?? ''); ?>;

  // ── Element references ────────────────────────────────────────
  const form          = document.getElementById('checkoutForm');
  const payMethodFld  = document.getElementById('paymentMethod');
  const cardSubmitBtn = document.getElementById('cardSubmitBtn');
  const billingSame   = document.getElementById('billingSame');
  const billingFields = document.getElementById('billingFields');
  const billingSameNote = document.getElementById('billingSameNote');
  const shipIds = ['s_street','s_city','s_state','s_pin','s_phone'];
  const billIds = ['b_street','b_city','b_state','b_pin','b_phone'];

  // ── Helpers ───────────────────────────────────────────────────
  function v(id) { return (document.getElementById(id) || {}).value || ''; }
  function setErr(id, msg) {
    const el = document.getElementById(id); if (!el) return;
    el.classList.add('co-invalid');
    const box = document.getElementById('err-' + id) || el.nextElementSibling;
    if (box) box.textContent = msg;
    return false;
  }
  function clrErr(id) {
    const el = document.getElementById(id); if (!el) return true;
    el.classList.remove('co-invalid');
    const box = document.getElementById('err-' + id) || el.nextElementSibling;
    if (box && box.classList.contains('co-field-err')) box.textContent = '';
    return true;
  }
  function chkReq(id, label) {
    return v(id).trim() !== '' ? clrErr(id) : setErr(id, label + ' is required.');
  }

  // ── Billing same-as-shipping ──────────────────────────────────
  function applyBillingSame() {
    if (billingSame.checked) {
      billingFields.style.display = 'none';
      billingSameNote.style.display = '';
      shipIds.forEach((sid, i) => {
        const b = document.getElementById(billIds[i]);
        const s = document.getElementById(sid);
        if (b && s) b.value = s.value;
      });
    } else {
      billingFields.style.display = '';
      billingSameNote.style.display = 'none';
    }
  }
  billingSame.addEventListener('change', applyBillingSame);
  shipIds.forEach((sid, i) => {
    const el = document.getElementById(sid);
    if (el) el.addEventListener('input', () => {
      if (billingSame.checked) {
        const b = document.getElementById(billIds[i]);
        if (b) b.value = el.value;
      }
    });
  });

  // ── Payment tab switching ─────────────────────────────────────
  document.querySelectorAll('.pay-tab').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.pay-tab').forEach(t => t.classList.remove('active'));
      btn.classList.add('active');
      const tab = btn.dataset.tab;
      payMethodFld.value = tab;

      const cardPanel = document.getElementById('tab-card');
      const rzpPanel  = document.getElementById('tab-razorpay');
      if (cardPanel) cardPanel.style.display = tab === 'card'     ? '' : 'none';
      if (rzpPanel)  rzpPanel.style.display  = tab === 'razorpay' ? '' : 'none';

      if (cardSubmitBtn) cardSubmitBtn.style.display = tab === 'card' ? '' : 'none';
    });
  });

  // ── Card number: format + brand detect ───────────────────────
  const cardInput      = document.getElementById('cardNumber');
  const cardBrandBadge = document.getElementById('cardBrandBadge');
  if (cardInput) {
    const brands = [
      { name:'VISA',       re:/^4/ },
      { name:'Mastercard', re:/^5[1-5]/ },
      { name:'Amex',       re:/^3[47]/ },
      { name:'Discover',   re:/^6(?:011|5)/ },
      { name:'RuPay',      re:/^6[0-9]/ },
    ];
    cardInput.addEventListener('input', function () {
      let d = this.value.replace(/\D/g,'').slice(0,16);
      this.value = d.replace(/(.{4})(?=.)/g,'$1 ');
      if (cardBrandBadge) {
        const brand = brands.find(b => b.re.test(d));
        cardBrandBadge.textContent = brand ? brand.name : '16 digits';
        cardBrandBadge.classList.toggle('detected', !!brand);
      }
    });
  }

  // ── Expiry: auto-slash MM/YY ──────────────────────────────────
  const expiryInput = document.getElementById('cardExpiry');
  if (expiryInput) {
    expiryInput.addEventListener('input', function () {
      let d = this.value.replace(/\D/g,'');
      if (d.length > 2) d = d.slice(0,2) + '/' + d.slice(2,4);
      this.value = d;
    });
    expiryInput.addEventListener('keydown', function (e) {
      if (e.key === 'Backspace' && this.value.endsWith('/')) {
        e.preventDefault(); this.value = this.value.slice(0,-1);
      }
    });
  }

  // ── CVV: digits only ─────────────────────────────────────────
  const cvvInput = document.getElementById('cardCvv');
  if (cvvInput) cvvInput.addEventListener('input', function () {
    this.value = this.value.replace(/\D/g,'').slice(0,4);
  });

  // ── PIN: digits only ─────────────────────────────────────────
  ['s_pin','b_pin'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', () => { el.value = el.value.replace(/\D/g,''); });
  });

  // ── Address field validation (shared by card + Razorpay) ─────
  function validateAddress() {
    let ok = true;
    ok = chkReq('s_street','Street address') && ok;
    ok = chkReq('s_city',  'City')           && ok;
    ok = chkReq('s_state', 'State')          && ok;

    const sPin = v('s_pin');
    if (!sPin)               { setErr('s_pin','PIN / ZIP code is required.'); ok=false; }
    else if (!/^\d{4,10}$/.test(sPin)) { setErr('s_pin','PIN must be 4–10 digits.'); ok=false; }
    else clrErr('s_pin');

    const sPhone = v('s_phone');
    if (!sPhone) { setErr('s_phone','Phone number is required.'); ok=false; }
    else if (!/^\+?[\d\s\-\(\)]{7,15}$/.test(sPhone.trim())) { setErr('s_phone','Enter a valid phone number.'); ok=false; }
    else clrErr('s_phone');

    if (!billingSame.checked) {
      ok = chkReq('b_street','Street address') && ok;
      ok = chkReq('b_city',  'City')           && ok;
      ok = chkReq('b_state', 'State')          && ok;
      const bPin = v('b_pin');
      if (!bPin) { setErr('b_pin','PIN / ZIP code is required.'); ok=false; }
      else if (!/^\d{4,10}$/.test(bPin)) { setErr('b_pin','PIN must be 4–10 digits.'); ok=false; }
      else clrErr('b_pin');
      const bPhone = v('b_phone');
      if (!bPhone) { setErr('b_phone','Phone number is required.'); ok=false; }
      else if (!/^\+?[\d\s\-\(\)]{7,15}$/.test(bPhone.trim())) { setErr('b_phone','Enter a valid phone number.'); ok=false; }
      else clrErr('b_phone');
    }
    return ok;
  }

  // ── Card field validation (UI only — Accept.js does the real check) ──
  function validateCard() {
    let ok = true;
    const digits = v('cardNumber').replace(/\D/g,'');
    if (digits.length !== 16) { setErr('cardNumber','Card number must be exactly 16 digits.'); ok=false; }
    else clrErr('cardNumber');

    if (!v('cardName').trim()) { setErr('cardName','Cardholder name is required.'); ok=false; }
    else clrErr('cardName');

    const expRe = /^(0[1-9]|1[0-2])\/(\d{2})$/;
    const expM  = expRe.exec(v('cardExpiry').trim());
    if (!expM) { setErr('cardExpiry','Enter a valid expiry date (MM/YY).'); ok=false; }
    else {
      const now = new Date();
      const ey  = 2000 + parseInt(expM[2],10), em = parseInt(expM[1],10);
      if (ey < now.getFullYear() || (ey === now.getFullYear() && em < now.getMonth()+1)) {
        setErr('cardExpiry','This card has expired.'); ok=false;
      } else clrErr('cardExpiry');
    }

    if (!/^\d{3,4}$/.test(v('cardCvv'))) { setErr('cardCvv','CVV must be 3 or 4 digits.'); ok=false; }
    else clrErr('cardCvv');

    return ok;
  }

  // ── Card form submit (Accept.js tokenisation) ─────────────────
  if (form && AUTHNET_ON) {
    const submitOrigLabel = cardSubmitBtn ? cardSubmitBtn.textContent : '';

    form.addEventListener('submit', function (e) {
      e.preventDefault();

      if (!validateAddress()) {
        document.querySelector('.co-invalid')?.scrollIntoView({behavior:'smooth',block:'center'});
        return;
      }
      if (!validateCard()) {
        document.querySelector('.co-invalid')?.scrollIntoView({behavior:'smooth',block:'center'});
        return;
      }

      if (!AUTHNET_CK || !AUTHNET_LID) {
        document.getElementById('err-acceptJs').textContent =
          'Card gateway not configured. Please contact support.';
        return;
      }

      if (cardSubmitBtn) { cardSubmitBtn.disabled=true; cardSubmitBtn.textContent='Processing…'; }

      const expParts = v('cardExpiry').split('/');
      Accept.dispatchData({
        authData: { clientKey: AUTHNET_CK, apiLoginID: AUTHNET_LID },
        cardData:  {
          cardNumber: v('cardNumber').replace(/\D/g,''),
          month:      expParts[0] || '',
          year:       '20' + (expParts[1] || ''),
          cardCode:   v('cardCvv'),
        },
      }, function (response) {
        if (response.messages.resultCode === 'Error') {
          if (cardSubmitBtn) { cardSubmitBtn.disabled=false; cardSubmitBtn.textContent=submitOrigLabel; }
          const msg = (response.messages.message || []).map(m => m.text).join(' ');
          document.getElementById('err-acceptJs').textContent = msg || 'Card tokenisation failed.';
          return;
        }
        document.getElementById('opaqueDescriptor').value = response.opaqueData.dataDescriptor;
        document.getElementById('opaqueValue').value      = response.opaqueData.dataValue;
        form.submit(); // Bypasses the event listener — form submits with opaqueData
      });
    });
  }

  // ── Razorpay payment button ───────────────────────────────────
  const rzpPayBtn = document.getElementById('rzpPayBtn');
  if (rzpPayBtn && RAZORPAY_ON) {
    rzpPayBtn.addEventListener('click', function () {
      if (!validateAddress()) {
        document.querySelector('.co-invalid')?.scrollIntoView({behavior:'smooth',block:'center'});
        return;
      }

      rzpPayBtn.disabled = true;
      rzpPayBtn.textContent = 'Connecting to Razorpay…';
      document.getElementById('rzpStatus').textContent = '';

      const payload = {
        s_street: v('s_street'), s_city:  v('s_city'),
        s_state:  v('s_state'),  s_pin:   v('s_pin'),   s_phone: v('s_phone'),
        billing_same: billingSame.checked,
        b_street: v('b_street'), b_city:  v('b_city'),
        b_state:  v('b_state'),  b_pin:   v('b_pin'),   b_phone: v('b_phone'),
      };

      fetch('/payment/razorpay_create.php', {
        method:  'POST',
        headers: {'Content-Type':'application/json'},
        body:    JSON.stringify(payload),
      })
      .then(r => r.json())
      .then(data => {
        if (!data.ok) {
          rzpPayBtn.disabled = false;
          rzpPayBtn.textContent = 'Pay ' + RZP_CURRENCY + ' ' + TOTAL_AMOUNT.toFixed(2) + ' →';
          document.getElementById('rzpStatus').textContent = data.error || 'Could not start payment.';
          return;
        }

        const rzp = new Razorpay({
          key:         data.key_id,
          amount:      data.amount,
          currency:    data.currency,
          name:        'My E-Shop',
          description: 'Order Payment',
          order_id:    data.razorpay_order_id,
          handler: function (response) {
            rzpPayBtn.textContent = 'Verifying payment…';
            fetch('/payment/razorpay_verify.php', {
              method:  'POST',
              headers: {'Content-Type':'application/json'},
              body:    JSON.stringify({
                razorpay_order_id:   response.razorpay_order_id,
                razorpay_payment_id: response.razorpay_payment_id,
                razorpay_signature:  response.razorpay_signature,
              }),
            })
            .then(r => r.json())
            .then(result => {
              if (result.ok) {
                window.location.href = '/checkout.php?razorpay_success=1';
              } else {
                document.getElementById('rzpStatus').textContent =
                  result.error || 'Payment received but order creation failed. Contact support.';
                rzpPayBtn.disabled = false;
                rzpPayBtn.textContent = 'Retry';
              }
            });
          },
          prefill:  { name: USER_NAME, email: USER_EMAIL, contact: v('s_phone') },
          theme:    { color: '#36473C' },
          modal:    {
            ondismiss: function () {
              rzpPayBtn.disabled = false;
              rzpPayBtn.textContent = 'Pay ' + RZP_CURRENCY + ' ' + TOTAL_AMOUNT.toFixed(2) + ' →';
            },
          },
        });
        rzp.open();
      })
      .catch(() => {
        rzpPayBtn.disabled = false;
        rzpPayBtn.textContent = 'Pay ' + RZP_CURRENCY + ' ' + TOTAL_AMOUNT.toFixed(2) + ' →';
        document.getElementById('rzpStatus').textContent = 'Network error. Please try again.';
      });
    });
  }

})();
</script>
<?php endif; ?>

<?php include 'footer.php'; $conn->close(); ?>
