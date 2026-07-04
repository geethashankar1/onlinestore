<?php
// seller/dashboard.php — seller's home dashboard.
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header('Location: ../login.php'); exit;
}

require_once '../config/store.php';
if (!$active_store) {
    header('Location: setup.php'); exit;
}

$store_id = (int)$active_store['id'];

// Stats
$total_products = $conn->query("SELECT COUNT(*) FROM products WHERE store_id = $store_id")->fetch_row()[0];
$total_orders   = $conn->query("SELECT COUNT(*) FROM orders   WHERE store_id = $store_id")->fetch_row()[0];
$revenue_row    = $conn->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE store_id = $store_id AND status != 'Cancelled'")->fetch_row();
$revenue        = $revenue_row[0];

// Recent orders
$recent = $conn->query(
    "SELECT o.id, o.total_amount, o.status, o.created_at, u.username
     FROM orders o JOIN users u ON u.id = o.user_id
     WHERE o.store_id = $store_id ORDER BY o.created_at DESC LIMIT 5"
);

$page_title = $active_store['name'] . ' — Dashboard';
$nav_mode   = 'seller';
include '../header.php';
?>
<section class="page">
  <div class="container">
    <div class="page-head">
      <div class="eyebrow">Seller Dashboard</div>
      <h2 class="mb-0"><?php echo htmlspecialchars($active_store['name']); ?></h2>
      <p class="mt-1" style="color:var(--muted);">
        Your store: <a href="/shop/<?php echo htmlspecialchars($active_store['slug']); ?>" target="_blank">
          /shop/<?php echo htmlspecialchars($active_store['slug']); ?>
        </a>
      </p>
    </div>

    <!-- Stats row -->
    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="surface p-4 text-center">
          <div style="font-size:2rem;font-weight:700;"><?php echo $total_products; ?></div>
          <div style="color:var(--muted);">Products</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="surface p-4 text-center">
          <div style="font-size:2rem;font-weight:700;"><?php echo $total_orders; ?></div>
          <div style="color:var(--muted);">Orders</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="surface p-4 text-center">
          <div style="font-size:2rem;font-weight:700;">₹<?php echo number_format($revenue, 2); ?></div>
          <div style="color:var(--muted);">Revenue</div>
        </div>
      </div>
    </div>

    <!-- Quick links -->
    <div class="row g-3 mb-4">
      <div class="col-auto"><a href="add_product.php" class="btn">+ Add Product</a></div>
      <div class="col-auto"><a href="products.php"    class="btn btn-outline-secondary">Manage Products</a></div>
      <div class="col-auto"><a href="orders.php"      class="btn btn-outline-secondary">View Orders</a></div>
      <div class="col-auto"><a href="settings.php"    class="btn btn-outline-secondary">Store Settings</a></div>
    </div>

    <!-- Recent orders -->
    <div class="surface p-4">
      <h5 class="mb-3">Recent Orders</h5>
      <?php if ($recent && $recent->num_rows > 0): ?>
      <table class="table theme-table">
        <thead><tr><th>#</th><th>Customer</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
          <?php while ($r = $recent->fetch_assoc()): ?>
          <tr>
            <td><?php echo $r['id']; ?></td>
            <td><?php echo htmlspecialchars($r['username']); ?></td>
            <td>₹<?php echo number_format($r['total_amount'], 2); ?></td>
            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($r['status']); ?></span></td>
            <td><?php echo date('d M Y', strtotime($r['created_at'])); ?></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
      <?php else: ?>
        <p style="color:var(--muted);">No orders yet. Share your store link to get started!</p>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php include '../footer.php'; $conn->close(); ?>
