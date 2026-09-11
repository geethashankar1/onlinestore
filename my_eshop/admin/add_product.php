<?php
// admin/add_product.php
require_once '../config/db.php'; // Note the path to db.php

// Admin protection (basic - ensure user is logged in and is an admin)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    $_SESSION['admin_redirect_message'] = "You are not authorized to access this page.";
    header("Location: ../login.php");
    exit;
}

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $conn->real_escape_string(trim($_POST['name']));
    $description = $conn->real_escape_string(trim($_POST['description']));
    $price = floatval($_POST['price']);
    $image_name = '';

    if (empty($name) || empty($description) || $price <= 0) {
        $message = "<div class='alert alert-danger'>Name, description, and a valid price are required.</div>";
    } else {
        // Handle image upload — media_store() validates type/size and writes to
        // Cloudinary in production or uploads/ locally, returning the DB value.
        if (isset($_FILES['product_image'])) {
            $upload_error = null;
            $image_name   = media_store($_FILES['product_image'], 'products', $upload_error);
            if ($upload_error !== null) {
                $message = "<div class='alert alert-danger'>" . htmlspecialchars($upload_error) . "</div>";
            }
        }


        if (empty($message) || $image_name !== '') { // Proceed if no upload error or if upload was successful
             $stmt = $conn->prepare("INSERT INTO products (name, description, price, image) VALUES (?, ?, ?, ?)");
             if ($stmt) {
                $stmt->bind_param("ssds", $name, $description, $price, $image_name);
                if ($stmt->execute()) {
                    $message = "<div class='alert alert-success'>Product added successfully!</div>";
                    // Clear form fields or redirect
                } else {
                    $message = "<div class='alert alert-danger'>Error adding product: " . $stmt->error . "</div>";
                }
                $stmt->close();
            } else {
                $message = "<div class='alert alert-danger'>Database error: " . $conn->error . "</div>";
            }
        }
    }
}
$page_title = "Add Product";
$nav_mode = 'admin';
include '../header.php';
?>
  <section class="page">
    <div class="container">
      <div class="page-head"><div class="eyebrow">Catalogue</div><h2 class="mb-0">Add New Product</h2></div>
      <?php echo $message; ?>
      <div class="surface form-shell wide">
        <form action="add_product.php" method="post" enctype="multipart/form-data">
          <div class="mb-3">
            <label for="name" class="form-label">Product Name</label>
            <input type="text" id="name" name="name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea id="description" name="description" rows="5" class="form-control" required></textarea>
          </div>
          <div class="mb-3">
            <label for="price" class="form-label">Price ($)</label>
            <input type="number" id="price" name="price" step="0.01" min="0.01" class="form-control" required>
          </div>
          <div class="mb-4">
            <label for="product_image" class="form-label">Product Image</label>
            <input type="file" id="product_image" name="product_image" accept="image/*" class="form-control">
          </div>
          <button type="submit" class="btn">Add Product</button>
        </form>
      </div>
    </div>
  </section>
<?php include '../footer.php'; $conn->close(); ?>
