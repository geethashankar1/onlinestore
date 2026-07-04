<?php
// seller/products.php — manage seller's own products.
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header('Location: ../login.php'); exit;
}

require_once '../config/store.php';
if (!$active_store) { header('Location: setup.php'); exit; }

$store_id = (int)$active_store['id'];
$message  = '';

if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete') {
    $pid = (int)$_GET['id'];
    // Verify this product belongs to seller's store
    $chk = $conn->prepare("SELECT image FROM products WHERE id = ? AND store_id = ?");
    $chk->bind_param('ii', $pid, $store_id);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    $chk->close();

    if ($row) {
        if (!empty($row['image']) && file_exists('../uploads/' . $row['image'])) {
            unlink('../uploads/' . $row['image']);
        }
        $del = $conn->prepare("DELETE FROM products WHERE id = ? AND store_id = ?");
        $del->bind_param('ii', $pid, $store_id);
        $del->execute();
        $del->close();
        $message = "<div class='alert alert-success'>Product deleted.</div>";
    } else {
        $message = "<div class='alert alert-danger'>Product not found.</div>";
    }
}

$result = $conn->prepare(
    "SELECT id, name, price, image, created_at FROM products WHERE store_id = ? ORDER BY created_at DESC"
);
$result->bind_param('i', $store_id);
$result->execute();
$products = $result->get_result();
$result->close();

$page_title = 'My Products';
$nav_mode   = 'seller';
include '../header.php';
?>
<section class="page">
  <div class="container">
    <div class="page-head d-flex justify-content-between align-items-end flex-wrap gap-2">
      <div><div class="eyebrow">Catalogue</div><h2 class="mb-0">My Products</h2></div>
      <a href="add_product.php" class="btn">+ Add Product</a>
    </div>
    <?php echo $message; ?>
    <div class="table-shell">
      <table class="table theme-table">
        <thead>
          <tr><th>Image</th><th>Name</th><th>Price</th><th>Added</th><th class="text-end">Actions</th></tr>
        </thead>
        <tbody>
          <?php if ($products->num_rows > 0): while ($p = $products->fetch_assoc()): ?>
          <tr>
            <td>
              <?php if (!empty($p['image']) && file_exists('../uploads/' . $p['image'])): ?>
                <img src="../uploads/<?php echo htmlspecialchars($p['image']); ?>"
                     style="width:54px;height:54px;object-fit:cover;">
              <?php else: ?><span style="color:var(--muted);font-size:.85rem;">No image</span><?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($p['name']); ?></td>
            <td>₹<?php echo number_format($p['price'], 2); ?></td>
            <td><?php echo date('d M Y', strtotime($p['created_at'])); ?></td>
            <td class="text-end">
              <a href="products.php?action=delete&id=<?php echo $p['id']; ?>"
                 class="btn btn-sm btn-soft-danger"
                 onclick="return confirm('Delete this product?')">Delete</a>
            </td>
          </tr>
          <?php endwhile; else: ?>
          <tr><td colspan="5" class="text-center" style="color:var(--muted);">
            No products yet. <a href="add_product.php">Add your first product</a>.
          </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
<?php include '../footer.php'; $conn->close(); ?>
