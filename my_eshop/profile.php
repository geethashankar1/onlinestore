<?php
// profile.php — logged-in user's profile: details, wishlist, and order history.
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Remove from wishlist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_wishlist'])) {
    $pid = (int)$_POST['product_id'];
    $stmt = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $pid);
    $stmt->execute();
    $stmt->close();
    header("Location: profile.php");
    exit;
}

// User details
$stmt = $conn->prepare("SELECT id, username, email, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Most-recent shipping address (from latest order)
$stmt = $conn->prepare("SELECT shipping_address FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$addr_row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$latest_address = $addr_row ? $addr_row['shipping_address'] : null;

// Wishlist items
$stmt = $conn->prepare("
    SELECT p.id, p.name, p.price, p.image
    FROM wishlist w
    JOIN products p ON p.id = w.product_id
    WHERE w.user_id = ?
    ORDER BY w.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$wl_result = $stmt->get_result();
$wishlist = [];
while ($row = $wl_result->fetch_assoc()) $wishlist[] = $row;
$stmt->close();

// Orders
$stmt = $conn->prepare("
    SELECT id, total_amount, shipping_address, status, created_at
    FROM orders
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$ord_result = $stmt->get_result();
$orders = [];
while ($row = $ord_result->fetch_assoc()) $orders[] = $row;
$stmt->close();

// Order items for each order
foreach ($orders as &$order) {
    $stmt = $conn->prepare("
        SELECT oi.quantity, oi.price_at_purchase, p.name
        FROM order_items oi
        JOIN products p ON p.id = oi.product_id
        WHERE oi.order_id = ?
    ");
    $stmt->bind_param("i", $order['id']);
    $stmt->execute();
    $items_res = $stmt->get_result();
    $order['items'] = [];
    while ($item = $items_res->fetch_assoc()) $order['items'][] = $item;
    $stmt->close();
}
unset($order);

$page_title = "My Profile";
include 'header.php';
?>
  <section class="page">
    <div class="container">

      <!-- Page heading -->
      <div class="page-head reveal d1">
        <div class="eyebrow">Account</div>
        <h2 class="mb-0">My Profile</h2>
      </div>

      <!-- ── User details ─────────────────────────────────────────────── -->
      <div class="row g-4 mb-5">
        <div class="col-md-6 col-lg-4 reveal d2">
          <div class="surface p-4 h-100">
            <div class="eyebrow mb-3">Account Details</div>

            <div class="mb-3">
              <div class="form-label mb-1">Username</div>
              <div><?php echo htmlspecialchars($user['username']); ?></div>
            </div>

            <div class="mb-3">
              <div class="form-label mb-1">Email</div>
              <div><?php echo htmlspecialchars($user['email']); ?></div>
            </div>

            <?php if ($latest_address): ?>
            <div class="mb-3">
              <div class="form-label mb-1">Last Shipping Address</div>
              <div style="color:var(--muted);white-space:pre-wrap;font-size:.92rem;"><?php echo htmlspecialchars($latest_address); ?></div>
            </div>
            <?php endif; ?>

            <div>
              <div class="form-label mb-1">Member Since</div>
              <div style="color:var(--muted);"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></div>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Wishlist ──────────────────────────────────────────────────── -->
      <div class="d-flex align-items-baseline gap-3 mb-3 reveal d3">
        <h3 class="serif mb-0">Wishlist</h3>
        <span style="color:var(--muted);font-size:.9rem;"><?php echo count($wishlist); ?> item<?php echo count($wishlist) !== 1 ? 's' : ''; ?></span>
      </div>

      <?php if (empty($wishlist)): ?>
        <div class="surface p-4 mb-5 text-center" style="color:var(--muted);">
          Your wishlist is empty. <a href="index.php" class="nav-link-x">Browse products</a>
        </div>
      <?php else: ?>
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3 mb-5">
          <?php foreach ($wishlist as $wi): ?>
            <?php
              $img = (!empty($wi['image']) && file_exists('uploads/' . $wi['image']))
                   ? 'uploads/' . htmlspecialchars($wi['image'])
                   : 'uploads/default_placeholder.png';
            ?>
            <div class="col">
              <div class="product-card">
                <a href="product.php?id=<?php echo $wi['id']; ?>" class="pc-img d-block">
                  <img src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($wi['name']); ?>">
                </a>
                <div class="pc-body">
                  <div class="pc-name"><?php echo htmlspecialchars($wi['name']); ?></div>
                  <div class="pc-price mt-1 mb-3">$<?php echo number_format($wi['price'], 2); ?></div>
                  <div class="d-flex gap-2">
                    <a href="cart.php?action=add&id=<?php echo $wi['id']; ?>" class="btn btn-sm flex-fill">Add to cart</a>
                    <form method="post">
                      <input type="hidden" name="product_id" value="<?php echo $wi['id']; ?>">
                      <button type="submit" name="remove_wishlist" class="btn btn-sm btn-soft-danger" title="Remove from wishlist">✕</button>
                    </form>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- ── Order history ─────────────────────────────────────────────── -->
      <div class="d-flex align-items-baseline gap-3 mb-3 reveal d4">
        <h3 class="serif mb-0">My Orders</h3>
        <span style="color:var(--muted);font-size:.9rem;"><?php echo count($orders); ?> order<?php echo count($orders) !== 1 ? 's' : ''; ?></span>
      </div>

      <?php if (empty($orders)): ?>
        <div class="surface p-4 text-center" style="color:var(--muted);">
          You haven't placed any orders yet. <a href="index.php" class="nav-link-x">Start shopping</a>
        </div>
      <?php else: ?>
        <div class="d-flex flex-column gap-3">
          <?php foreach ($orders as $order): ?>
            <div class="surface p-4">
              <!-- Order header -->
              <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                <div>
                  <div class="eyebrow mb-1">Order #<?php echo $order['id']; ?></div>
                  <div style="color:var(--muted);font-size:.85rem;"><?php echo date('F j, Y · g:i A', strtotime($order['created_at'])); ?></div>
                </div>
                <div class="d-flex align-items-center gap-3">
                  <span style="font-family:'Fraunces',serif;font-size:1.1rem;color:var(--brass-deep);font-weight:600;">$<?php echo number_format($order['total_amount'], 2); ?></span>
                  <span class="status-badge"><?php echo htmlspecialchars($order['status']); ?></span>
                </div>
              </div>

              <!-- Order items -->
              <div style="border-top:1px solid var(--line);padding-top:.85rem;margin-bottom:.85rem;">
                <?php foreach ($order['items'] as $item): ?>
                  <div class="d-flex justify-content-between py-1" style="font-size:.9rem;">
                    <span><?php echo htmlspecialchars($item['name']); ?> <span style="color:var(--muted);">×<?php echo $item['quantity']; ?></span></span>
                    <span>$<?php echo number_format($item['price_at_purchase'] * $item['quantity'], 2); ?></span>
                  </div>
                <?php endforeach; ?>
              </div>

              <!-- Shipping address -->
              <div style="font-size:.85rem;color:var(--muted);">
                <strong style="color:var(--ink);">Shipped to:</strong>
                <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>
  </section>
<?php include 'footer.php'; $conn->close(); ?>
