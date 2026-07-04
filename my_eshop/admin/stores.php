<?php
// admin/stores.php — super admin manages all seller stores.
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../login.php'); exit;
}

$message = '';

// Handle status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['store_id'], $_POST['status'])) {
    $allowed = ['active','pending','suspended'];
    $sid     = (int)$_POST['store_id'];
    $status  = $_POST['status'];
    if (in_array($status, $allowed)) {
        $upd = $conn->prepare("UPDATE stores SET status = ? WHERE id = ?");
        $upd->bind_param('si', $status, $sid);
        $upd->execute();
        $upd->close();
        $message = "<div class='alert alert-success'>Store #$sid updated to $status.</div>";
    }
}

$stores = $conn->query(
    "SELECT s.*, u.username, u.email,
       (SELECT COUNT(*) FROM products p WHERE p.store_id = s.id) AS product_count,
       (SELECT COUNT(*) FROM orders   o WHERE o.store_id = s.id) AS order_count
     FROM stores s JOIN users u ON u.id = s.seller_id
     ORDER BY s.created_at DESC"
);

$page_title = 'Manage Stores';
$nav_mode   = 'admin';
include '../header.php';
?>
<section class="page">
  <div class="container">
    <div class="page-head"><div class="eyebrow">Platform</div><h2 class="mb-0">All Stores</h2></div>
    <?php echo $message; ?>
    <div class="table-shell">
      <table class="table theme-table">
        <thead>
          <tr>
            <th>#</th><th>Store</th><th>Seller</th><th>Products</th><th>Orders</th>
            <th>Status</th><th>Created</th><th>Update</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($stores->num_rows > 0): while ($s = $stores->fetch_assoc()): ?>
          <tr>
            <td><?php echo $s['id']; ?></td>
            <td>
              <strong><?php echo htmlspecialchars($s['name']); ?></strong><br>
              <small><a href="/shop/<?php echo htmlspecialchars($s['slug']); ?>" target="_blank">
                /shop/<?php echo htmlspecialchars($s['slug']); ?>
              </a></small>
            </td>
            <td><?php echo htmlspecialchars($s['username']); ?><br>
                <small style="color:var(--muted);"><?php echo htmlspecialchars($s['email']); ?></small></td>
            <td><?php echo $s['product_count']; ?></td>
            <td><?php echo $s['order_count']; ?></td>
            <td>
              <?php
              $badge = ['active'=>'bg-success','pending'=>'bg-warning text-dark','suspended'=>'bg-danger'];
              $cls   = $badge[$s['status']] ?? 'bg-secondary';
              ?>
              <span class="badge <?php echo $cls; ?>"><?php echo $s['status']; ?></span>
            </td>
            <td><?php echo date('d M Y', strtotime($s['created_at'])); ?></td>
            <td>
              <form method="post" class="d-flex gap-1">
                <input type="hidden" name="store_id" value="<?php echo $s['id']; ?>">
                <select name="status" class="form-select form-select-sm" style="width:auto;">
                  <?php foreach (['active','pending','suspended'] as $opt): ?>
                    <option value="<?php echo $opt; ?>" <?php echo $s['status']===$opt?'selected':''; ?>>
                      <?php echo ucfirst($opt); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-outline-secondary">Save</button>
              </form>
            </td>
          </tr>
          <?php endwhile; else: ?>
          <tr><td colspan="8" class="text-center" style="color:var(--muted);">No stores yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
<?php include '../footer.php'; $conn->close(); ?>
