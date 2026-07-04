<?php
// seller/orders.php — seller views and updates their own store's orders.
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header('Location: ../login.php'); exit;
}

require_once '../config/store.php';
if (!$active_store) { header('Location: setup.php'); exit; }

$store_id = (int)$active_store['id'];
$message  = '';

// Update order status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['status'])) {
    $allowed = ['Pending','Processing','Shipped','Delivered','Cancelled'];
    $oid     = (int)$_POST['order_id'];
    $status  = $_POST['status'];
    if (in_array($status, $allowed)) {
        // Verify order belongs to this store
        $upd = $conn->prepare("UPDATE orders SET status = ? WHERE id = ? AND store_id = ?");
        $upd->bind_param('sii', $status, $oid, $store_id);
        $upd->execute();
        $upd->close();
        $message = "<div class='alert alert-success'>Order #$oid status updated to $status.</div>";
    }
}

$orders = $conn->prepare(
    "SELECT o.id, o.total_amount, o.status, o.created_at, o.shipping_address, u.username, u.email
     FROM orders o JOIN users u ON u.id = o.user_id
     WHERE o.store_id = ? ORDER BY o.created_at DESC"
);
$orders->bind_param('i', $store_id);
$orders->execute();
$result = $orders->get_result();
$orders->close();

$statuses = ['Pending','Processing','Shipped','Delivered','Cancelled'];

$page_title = 'My Orders';
$nav_mode   = 'seller';
include '../header.php';
?>
<section class="page">
  <div class="container">
    <div class="page-head"><div class="eyebrow">Orders</div><h2 class="mb-0">My Orders</h2></div>
    <?php echo $message; ?>
    <div class="table-shell">
      <table class="table theme-table">
        <thead>
          <tr><th>#</th><th>Customer</th><th>Amount</th><th>Address</th><th>Status</th><th>Date</th><th>Update</th></tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows > 0): while ($o = $result->fetch_assoc()): ?>
          <tr>
            <td><?php echo $o['id']; ?></td>
            <td><?php echo htmlspecialchars($o['username']); ?><br>
                <small style="color:var(--muted);"><?php echo htmlspecialchars($o['email']); ?></small></td>
            <td>₹<?php echo number_format($o['total_amount'], 2); ?></td>
            <td><small><?php echo htmlspecialchars(substr($o['shipping_address'],0,40)); ?>…</small></td>
            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($o['status']); ?></span></td>
            <td><?php echo date('d M Y', strtotime($o['created_at'])); ?></td>
            <td>
              <form method="post" class="d-flex gap-1">
                <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                <select name="status" class="form-select form-select-sm" style="width:auto;">
                  <?php foreach ($statuses as $s): ?>
                    <option value="<?php echo $s; ?>" <?php echo $o['status']===$s?'selected':''; ?>>
                      <?php echo $s; ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-outline-secondary">Save</button>
              </form>
            </td>
          </tr>
          <?php endwhile; else: ?>
          <tr><td colspan="7" class="text-center" style="color:var(--muted);">No orders yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
<?php include '../footer.php'; $conn->close(); ?>
