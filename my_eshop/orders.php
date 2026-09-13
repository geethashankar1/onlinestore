<?php
// orders.php — customer's order history, optionally scoped to one seller's store
require_once 'config/db.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$uid = (int)$_SESSION['user_id'];

// Fetch orders
$stmt = $conn->prepare(
    "SELECT o.id, o.total_amount, o.created_at, o.status, o.shipping_address
     FROM orders o
     WHERE o.user_id = ?
     ORDER BY o.created_at DESC"
);
$stmt->bind_param('i', $uid);
$stmt->execute();
$orders_result = $stmt->get_result();
$stmt->close();

$page_title = 'My Orders';
include 'header.php';
?>

<section class="page">
  <div class="container">
    <div class="page-head">
      <div class="eyebrow">Account</div>
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
      <a href="index.php" class="btn">Start shopping</a>
    </div>
    <?php endif; ?>

    <div class="mt-4">
      <a href="index.php" class="nav-link-x">&larr; Back to shop</a>
    </div>
  </div>
</section>

<?php include 'footer.php'; $conn->close(); ?>
