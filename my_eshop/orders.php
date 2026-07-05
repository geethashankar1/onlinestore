<?php
// orders.php — customer's order history, optionally scoped to one seller's store
require_once 'config/db.php';

// Resolve store context
$store_slug = '';
$_raw = isset($_GET['store']) ? $_GET['store'] : (isset($_SESSION['current_store_slug']) ? $_SESSION['current_store_slug'] : '');
if (preg_match('/^[a-z0-9_-]+$/', $_raw)) {
    $store_slug = $_raw;
    $_SESSION['current_store_slug'] = $store_slug;
}
$in_store = ($store_slug !== '');

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    $dest = $in_store ? '/login.php?store=' . $store_slug : 'login.php';
    header('Location: ' . $dest);
    exit;
}

$uid = (int)$_SESSION['user_id'];

// Resolve store record when in store context
$store_name = '';
$store_id   = 0;
if ($in_store) {
    $s_raw  = $conn->real_escape_string($store_slug);
    $s_row  = $conn->query("SELECT id, name FROM stores WHERE slug='$s_raw' AND status='active'")->fetch_assoc();
    if ($s_row) {
        $store_id   = (int)$s_row['id'];
        $store_name = $s_row['name'];
    } else {
        // Invalid store slug in URL — just show all orders
        $in_store = false;
        $store_slug = '';
    }
}

// Fetch orders
if ($in_store && $store_id) {
    $stmt = $conn->prepare(
        "SELECT o.id, o.total_amount, o.created_at, o.status, o.shipping_address
         FROM orders o
         WHERE o.user_id = ? AND o.store_id = ?
         ORDER BY o.created_at DESC"
    );
    $stmt->bind_param('ii', $uid, $store_id);
} else {
    $stmt = $conn->prepare(
        "SELECT o.id, o.total_amount, o.created_at, o.status, o.shipping_address
         FROM orders o
         WHERE o.user_id = ?
         ORDER BY o.created_at DESC"
    );
    $stmt->bind_param('i', $uid);
}
$stmt->execute();
$orders_result = $stmt->get_result();
$stmt->close();

$page_title = $in_store ? 'My Orders — ' . $store_name : 'My Orders';
if ($in_store) {
    $nav_mode = 'storefront';
    // Load store branding for header
    $store_row = $conn->query("SELECT name, logo, primary_color FROM stores WHERE id=$store_id")->fetch_assoc();
    if ($store_row) {
        $brand_name  = htmlspecialchars($store_row['name']);
        $brand_color = htmlspecialchars($store_row['primary_color']);
        $brand_logo  = !empty($store_row['logo']) ? '/uploads/stores/' . htmlspecialchars($store_row['logo']) : '';
    }
}
include 'header.php';
?>

<section class="page">
  <div class="container">
    <div class="page-head">
      <div class="eyebrow"><?php echo $in_store ? htmlspecialchars($store_name) : 'Account'; ?></div>
      <h2 class="mb-0">My Orders</h2>
    </div>

    <?php if ($orders_result && $orders_result->num_rows > 0): ?>
    <div class="table-shell">
      <table class="table theme-table">
        <thead>
          <tr>
            <th>Order #</th>
            <th>Date</th>
            <th>Total</th>
            <th>Status</th>
            <th>Shipping address</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($o = $orders_result->fetch_assoc()): ?>
          <tr>
            <td>#<?php echo $o['id']; ?></td>
            <td><?php echo date('M d, Y', strtotime($o['created_at'])); ?></td>
            <td>₹<?php echo number_format($o['total_amount'], 2); ?></td>
            <td><span class="status-badge"><?php echo htmlspecialchars($o['status']); ?></span></td>
            <td style="font-size:.88rem;color:var(--muted);max-width:220px;">
              <?php echo nl2br(htmlspecialchars($o['shipping_address'])); ?>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="surface p-5 text-center">
      <p class="mb-3" style="color:var(--muted);">You haven't placed any orders yet.</p>
      <a href="<?php echo $in_store ? '/shop/' . $store_slug : 'index.php'; ?>" class="btn">Start shopping</a>
    </div>
    <?php endif; ?>

    <div class="mt-4">
      <a href="<?php echo $in_store ? '/shop/' . $store_slug : 'index.php'; ?>" class="nav-link-x">&larr; Back to store</a>
    </div>
  </div>
</section>

<?php include 'footer.php'; $conn->close(); ?>
