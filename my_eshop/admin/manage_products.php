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
            if (!empty($row_img['image']) && file_exists("../uploads/" . $row_img['image'])) {
                unlink("../uploads/" . $row_img['image']); // Delete the image file
            }
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
$products_result = $conn->query("SELECT id, name, price, image FROM products ORDER BY created_at DESC");
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

      <div class="table-shell">
        <table class="table theme-table">
          <thead>
            <tr>
              <th>Image</th>
              <th>Name</th>
              <th>Price</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php
            if ($products_result && $products_result->num_rows > 0) {
                while ($product = $products_result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>";
                    if (!empty($product['image']) && file_exists('../uploads/' . $product['image'])) {
                        echo "<img src='../uploads/" . htmlspecialchars($product['image']) . "' alt='" . htmlspecialchars($product['name']) . "' style='width:54px;height:54px;object-fit:cover;'>";
                    } else {
                        echo "<span style='color:var(--muted);font-size:.85rem;'>No image</span>";
                    }
                    echo "</td>";
                    echo "<td>" . htmlspecialchars($product['name']) . "</td>";
                    echo "<td>$" . htmlspecialchars($product['price']) . "</td>";
                    echo "<td class='text-end'>";
                    echo "<a href='manage_products.php?action=delete&id=" . $product['id'] . "' class='btn btn-sm btn-soft-danger delete-product-btn'>Delete</a>";
                    echo "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='4' class='text-center' style='color:var(--muted);'>No products found.</td></tr>";
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
<?php include '../footer.php'; $conn->close(); ?>
