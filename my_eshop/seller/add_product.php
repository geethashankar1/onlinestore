<?php
// seller/add_product.php — seller adds a product to their own store.
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header('Location: ../login.php'); exit;
}

require_once '../config/store.php';
if (!$active_store) { header('Location: setup.php'); exit; }

$store_id = (int)$active_store['id'];
$message  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name']        ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = floatval($_POST['price']   ?? 0);
    $image_name  = '';

    if ($name === '' || $description === '' || $price <= 0) {
        $message = "<div class='alert alert-danger'>Name, description, and a valid price are required.</div>";
    } else {
        if (isset($_FILES['product_image'])) {
            // media_store() validates type/size and writes to Cloudinary in
            // production or uploads/ locally, returning what to save in `image`.
            $upload_error = null;
            $image_name   = media_store($_FILES['product_image'], 'products', $upload_error);
            if ($upload_error !== null) {
                $message = "<div class='alert alert-danger'>" . htmlspecialchars($upload_error) . "</div>";
            }
        }

        if (empty($message) || $image_name !== '') {
            $stmt = $conn->prepare(
                "INSERT INTO products (store_id, name, description, price, image) VALUES (?,?,?,?,?)"
            );
            $stmt->bind_param('issds', $store_id, $name, $description, $price, $image_name);
            if ($stmt->execute()) {
                $message = "<div class='alert alert-success'>Product added successfully!</div>";
            } else {
                $message = "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
            }
            $stmt->close();
        }
    }
}

$page_title = 'Add Product';
$nav_mode   = 'seller';
include '../header.php';
?>
<section class="page">
  <div class="container">
    <div class="page-head"><div class="eyebrow">Catalogue</div><h2 class="mb-0">Add Product</h2></div>
    <?php echo $message; ?>
    <div class="surface form-shell wide">
      <form method="post" enctype="multipart/form-data">
        <div class="mb-3">
          <label class="form-label">Product Name</label>
          <input name="name" type="text" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea name="description" rows="5" class="form-control" required></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Price (₹)</label>
          <input name="price" type="number" step="0.01" min="0.01" class="form-control" required>
        </div>
        <div class="mb-4">
          <label class="form-label">Product Image <small class="text-muted">(optional, max 5MB)</small></label>
          <input name="product_image" type="file" class="form-control" accept="image/*">
        </div>
        <button type="submit" class="btn">Add Product</button>
        <a href="products.php" class="btn btn-outline-secondary ms-2">Cancel</a>
      </form>
    </div>
  </div>
</section>
<?php include '../footer.php'; $conn->close(); ?>
