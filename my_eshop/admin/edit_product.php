<?php
// admin/edit_product.php — edit one product.
require_once '../config/db.php';
require_once '../config/catalogue.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    $_SESSION['admin_redirect_message'] = "You are not authorized to access this page.";
    header("Location: ../login.php");
    exit;
}

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
if ($product_id <= 0) {
    header('Location: manage_products.php');
    exit;
}

// Load the row first — every branch below needs the current image.
$stmt = $conn->prepare("SELECT id, name, brand, manufacturer, scale, description, price, image FROM products WHERE id = ?");
$stmt->bind_param('i', $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    $page_title = 'Product not found';
    $nav_mode   = 'admin';
    include '../header.php';
    echo '<section class="page"><div class="container"><div class="surface p-5 text-center">'
       . '<h2 class="mb-3">Product not found</h2>'
       . '<p class="mb-4" style="color:var(--text-muted);">That product does not exist or was already deleted.</p>'
       . '<a href="manage_products.php" class="btn"><span>Back to products</span></a>'
       . '</div></div></section>';
    include '../footer.php';
    $conn->close();
    exit;
}

$message = '';
$old     = $product;   // form shows current values unless a POST overrides them

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name         = trim($_POST['name'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $brand        = trim($_POST['brand'] ?? '');
    $manufacturer = trim($_POST['manufacturer'] ?? '');
    $scale        = trim($_POST['scale'] ?? '');
    $price        = floatval($_POST['price'] ?? 0);
    $remove_image = isset($_POST['remove_image']);

    $old = compact('name','description','brand','manufacturer','scale')
         + ['price' => $_POST['price'] ?? '', 'image' => $product['image'], 'id' => $product_id];

    // Scale comes from a closed dropdown; anything else is tampering.
    if ($scale !== '' && !in_array($scale, CATALOGUE_SCALES, true)) {
        $scale = '';
    }

    if ($name === '' || $description === '' || $price <= 0) {
        $message = "<div class='alert alert-danger'>Name, description, and a valid price are required.</div>";
    } else {
        $image_name = $product['image'];   // keep what's there unless changed

        // A new upload replaces the old one; only then is the old file removed.
        if (isset($_FILES['product_image']) && ($_FILES['product_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload_error = null;
            $uploaded = media_store($_FILES['product_image'], 'products', $upload_error);
            if ($upload_error !== null) {
                $message = "<div class='alert alert-danger'>" . htmlspecialchars($upload_error) . "</div>";
            } elseif ($uploaded !== '') {
                media_delete($product['image']);
                $image_name = $uploaded;
            }
        } elseif ($remove_image && $product['image'] !== '') {
            media_delete($product['image']);
            $image_name = '';
        }

        if ($message === '') {
            $upd = $conn->prepare(
                "UPDATE products
                    SET name = ?, brand = ?, manufacturer = ?, scale = ?,
                        description = ?, price = ?, image = ?
                  WHERE id = ?"
            );
            $upd->bind_param('sssssdsi', $name, $brand, $manufacturer, $scale, $description, $price, $image_name, $product_id);
            if ($upd->execute()) {
                // Redirect after POST so a refresh doesn't resubmit the edit.
                $_SESSION['product_update_message'] = 'Product updated: ' . htmlspecialchars($name);
                $upd->close();
                $conn->close();
                header('Location: manage_products.php');
                exit;
            }
            $message = "<div class='alert alert-danger'>Error updating product: " . htmlspecialchars($upd->error) . "</div>";
            $upd->close();
        }
    }
}

$brand_options = catalogue_suggestions($conn, 'brand');
$manu_options  = catalogue_suggestions($conn, 'manufacturer');

function fv(array $old, string $k): string { return htmlspecialchars((string)($old[$k] ?? '')); }

$page_title = 'Edit Product';
$nav_mode   = 'admin';
include '../header.php';
?>
  <section class="page">
    <div class="container">
      <div class="page-head">
        <div class="eyebrow">Catalogue</div>
        <h2 class="mb-0">Edit Product</h2>
      </div>
      <?php echo $message; ?>

      <div class="surface form-shell wide">
        <form action="edit_product.php" method="post" enctype="multipart/form-data">
          <input type="hidden" name="id" value="<?php echo (int)$product_id; ?>">

          <div class="mb-3">
            <label for="name" class="form-label">Product Name</label>
            <input type="text" id="name" name="name" class="form-control"
                   value="<?php echo fv($old,'name'); ?>" required>
          </div>

          <?php /* Three-up on desktop, stacked on mobile — same grid as Add. */ ?>
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
            <?php if (!empty($product['image'])): ?>
              <div class="d-flex align-items-center gap-3 flex-wrap mb-2">
                <img src="<?php echo htmlspecialchars(media_url($product['image'])); ?>"
                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                     style="width:72px;height:72px;object-fit:cover;border:1px solid var(--line);flex:0 0 auto;">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image" value="1">
                  <label class="form-check-label" for="remove_image">Remove current image</label>
                </div>
              </div>
            <?php endif; ?>
            <input type="file" id="product_image" name="product_image" accept="image/*" class="form-control">
            <div class="pw-rules">Leave empty to keep the current image.</div>
          </div>

          <div class="d-flex gap-2 flex-wrap">
            <button type="submit" class="btn"><span>Save changes</span></button>
            <a href="manage_products.php" class="btn btn-ghost"><span>Cancel</span></a>
          </div>
        </form>
      </div>
    </div>
  </section>
<?php include '../footer.php'; $conn->close(); ?>
