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
        // Handle image upload
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
            $target_dir = "../uploads/"; // Relative to this admin script's location
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $image_extension = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = array("jpg", "jpeg", "png", "gif");

            if (in_array($image_extension, $allowed_extensions)) {
                if ($_FILES['product_image']['size'] <= 5000000) { // 5MB limit
                    // Generate a unique name for the image to prevent overwriting
                    $image_name = uniqid('product_', true) . '.' . $image_extension;
                    $target_file = $target_dir . $image_name;

                    if (!move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file)) {
                        $message = "<div class='alert alert-danger'>Sorry, there was an error uploading your file.</div>";
                        $image_name = ''; // Reset image name if upload failed
                    }
                } else {
                    $message = "<div class='alert alert-danger'>Sorry, your file is too large (max 5MB).</div>";
                    $image_name = '';
                }
            } else {
                $message = "<div class='alert alert-danger'>Sorry, only JPG, JPEG, PNG & GIF files are allowed.</div>";
                $image_name = '';
            }
        } elseif (isset($_FILES['product_image']) && $_FILES['product_image']['error'] != UPLOAD_ERR_NO_FILE) {
            $message = "<div class='alert alert-danger'>Error uploading image: code " . $_FILES['product_image']['error'] . "</div>";
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
