<?php
// admin/add_product.php
require_once '../config/db.php';
require_once '../config/catalogue.php';

// Admin protection (basic - ensure user is logged in and is an admin)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    $_SESSION['admin_redirect_message'] = "You are not authorized to access this page.";
    header("Location: ../login.php");
    exit;
}

$message = '';
$old     = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name         = trim($_POST['name'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $brand        = trim($_POST['brand'] ?? '');
    $manufacturer = trim($_POST['manufacturer'] ?? '');
    $scale        = trim($_POST['scale'] ?? '');
    $price        = floatval($_POST['price'] ?? 0);
    $image_name   = '';

    // Keep what was typed so a validation failure doesn't wipe the form.
    $old = compact('name','description','brand','manufacturer','scale') + ['price' => $_POST['price'] ?? ''];

    // Scale comes from a closed dropdown; anything else is tampering.
    if ($scale !== '' && !in_array($scale, CATALOGUE_SCALES, true)) {
        $scale = '';
    }

    if ($name === '' || $description === '' || $price <= 0) {
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
            $stmt = $conn->prepare(
                "INSERT INTO products (name, brand, manufacturer, scale, description, price, image)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            if ($stmt) {
                $stmt->bind_param("sssssds", $name, $brand, $manufacturer, $scale, $description, $price, $image_name);
                if ($stmt->execute()) {
                    $message = "<div class='alert alert-success'>Product added successfully. "
                             . "<a href='manage_products.php'>Manage products</a> or add another below.</div>";
                    $old = [];   // clear the form on success
                } else {
                    $message = "<div class='alert alert-danger'>Error adding product: " . htmlspecialchars($stmt->error) . "</div>";
                }
                $stmt->close();
            } else {
                $message = "<div class='alert alert-danger'>Database error: " . htmlspecialchars($conn->error) . "</div>";
            }
        }
    }
}

$brand_options = catalogue_suggestions($conn, 'brand');
$manu_options  = catalogue_suggestions($conn, 'manufacturer');

function fv(array $old, string $k): string { return htmlspecialchars($old[$k] ?? ''); }

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
            <input type="text" id="name" name="name" class="form-control"
                   value="<?php echo fv($old,'name'); ?>" required>
          </div>

          <?php /* Three-up on desktop, stacked on mobile — Bootstrap's grid
                   handles the breakpoint so nothing needs custom media queries. */ ?>
          <div class="row g-3 mb-3">
            <div class="col-12 col-md-4">
              <label for="brand" class="form-label">Brand <span style="text-transform:none;letter-spacing:0;">(car marque)</span></label>
              <input type="text" id="brand" name="brand" class="form-control" list="brandList"
                     value="<?php echo fv($old,'brand'); ?>" placeholder="Porsche" autocomplete="off">
              <datalist id="brandList">
                <?php foreach ($brand_options as $b): ?>
                  <option value="<?php echo htmlspecialchars($b); ?>"></option>
                <?php endforeach; ?>
              </datalist>
            </div>
            <div class="col-12 col-md-4">
              <label for="manufacturer" class="form-label">Manufacturer <span style="text-transform:none;letter-spacing:0;">(model maker)</span></label>
              <input type="text" id="manufacturer" name="manufacturer" class="form-control" list="manuList"
                     value="<?php echo fv($old,'manufacturer'); ?>" placeholder="Hot Wheels" autocomplete="off">
              <datalist id="manuList">
                <?php foreach ($manu_options as $m): ?>
                  <option value="<?php echo htmlspecialchars($m); ?>"></option>
                <?php endforeach; ?>
              </datalist>
            </div>
            <div class="col-12 col-md-4">
              <label for="scale" class="form-label">Scale</label>
              <select id="scale" name="scale" class="form-select">
                <option value="">—</option>
                <?php foreach (CATALOGUE_SCALES as $s): ?>
                  <option value="<?php echo $s; ?>" <?php echo (($old['scale'] ?? '') === $s) ? 'selected' : ''; ?>>
                    <?php echo $s; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea id="description" name="description" rows="5" class="form-control" required><?php echo fv($old,'description'); ?></textarea>
          </div>
          <div class="mb-3">
            <label for="price" class="form-label">Price (&#8377;)</label>
            <input type="number" id="price" name="price" step="0.01" min="0.01" class="form-control"
                   value="<?php echo fv($old,'price'); ?>" required>
          </div>
          <div class="mb-4">
            <label for="product_image" class="form-label">Product Image</label>
            <input type="file" id="product_image" name="product_image" accept="image/*" class="form-control">
          </div>
          <button type="submit" class="btn"><span>Add Product</span></button>
        </form>
      </div>
    </div>
  </section>
<?php include '../footer.php'; $conn->close(); ?>
