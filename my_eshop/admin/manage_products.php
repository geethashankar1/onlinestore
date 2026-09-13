<?php
// admin/manage_products.php
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    $_SESSION['admin_redirect_message'] = "You are not authorized to access this page.";
    header("Location: ../login.php");
    exit;
}

$message = '';

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $product_id_to_delete = intval($_GET['id']);

    // First, get the image name to delete the file
    $stmt_img = $conn->prepare("SELECT image FROM products WHERE id = ?");
    if ($stmt_img) {
        $stmt_img->bind_param("i", $product_id_to_delete);
        $stmt_img->execute();
        $result_img = $stmt_img->get_result();
        if ($row_img = $result_img->fetch_assoc()) {
            media_delete($row_img['image'] ?? ''); // Local file; Cloudinary URLs are left alone
        }
        $stmt_img->close();
    }

    // Then delete the product from DB
    $stmt_delete = $conn->prepare("DELETE FROM products WHERE id = ?");
    if ($stmt_delete) {
        $stmt_delete->bind_param("i", $product_id_to_delete);
        if ($stmt_delete->execute()) {
            $message = "<div class='alert alert-success'>Product deleted successfully.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error deleting product: " . $stmt_delete->error . "</div>";
        }
        $stmt_delete->close();
    } else {
        $message = "<div class='alert alert-danger'>Database error (prepare delete): " . $conn->error . "</div>";
    }
}


// Fetch products
$products_result = $conn->query(
    "SELECT id, name, brand, manufacturer, scale, price, image FROM products ORDER BY created_at DESC"
);
$page_title = "Manage Products";
$nav_mode = 'admin';
include '../header.php';
?>
  <section class="page">
    <div class="container">
      <div class="page-head"><div class="eyebrow">Catalogue</div><h2 class="mb-0">Manage Products</h2></div>
      <?php echo $message; ?>
      <?php if (isset($_SESSION['product_update_message'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['product_update_message']; unset($_SESSION['product_update_message']); ?></div>
      <?php endif; ?>

      <?php /* Brand/Manufacturer/Scale are hidden below md and folded under the
               product name instead, so the table never needs to scroll sideways
               on a phone. */ ?>
      <style>
        .attr-meta{display:none;font-family:var(--mono);font-size:10.5px;letter-spacing:.1em;
          text-transform:uppercase;color:var(--text-faint);margin-top:4px;}
        @media (max-width:767.98px){ .attr-meta{display:block;} }
      </style>

      <div class="table-shell">
        <table class="table theme-table">
          <thead>
            <tr>
              <th>Image</th>
              <th>Name</th>
              <th class="d-none d-md-table-cell">Brand</th>
              <th class="d-none d-md-table-cell">Manufacturer</th>
              <th class="d-none d-md-table-cell">Scale</th>
              <th>Price</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($products_result && $products_result->num_rows > 0): ?>
              <?php while ($product = $products_result->fetch_assoc()):
                $meta = array_filter([$product['brand'], $product['manufacturer'], $product['scale']]);
              ?>
                <tr>
                  <td>
                    <?php if (!empty($product['image'])): ?>
                      <img src="<?php echo htmlspecialchars(media_url($product['image'])); ?>"
                           alt="<?php echo htmlspecialchars($product['name']); ?>"
                           style="width:54px;height:54px;object-fit:cover;">
                    <?php else: ?>
                      <span style="color:var(--text-faint);font-size:.85rem;">No image</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php echo htmlspecialchars($product['name']); ?>
                    <?php if ($meta): ?>
                      <div class="attr-meta"><?php echo htmlspecialchars(implode(' · ', $meta)); ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($product['brand']) ?: '—'; ?></td>
                  <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($product['manufacturer']) ?: '—'; ?></td>
                  <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($product['scale']) ?: '—'; ?></td>
                  <td>&#8377;<?php echo number_format((float)$product['price'], 2); ?></td>
                  <td class="text-end">
                    <div class="d-inline-flex gap-2">
                      <a href="edit_product.php?id=<?php echo (int)$product['id']; ?>"
                         class="btn btn-sm btn-ghost">Edit</a>
                      <a href="manage_products.php?action=delete&id=<?php echo (int)$product['id']; ?>"
                         class="btn btn-sm btn-soft-danger delete-product-btn">Delete</a>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="7" class="text-center" style="color:var(--text-faint);">No products found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
<?php include '../footer.php'; $conn->close(); ?>
