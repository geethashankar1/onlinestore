<?php
// seller/settings.php — update store branding.
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
    $color       = preg_match('/^#[0-9a-f]{6}$/i', $_POST['primary_color'] ?? '') ? $_POST['primary_color'] : $active_store['primary_color'];
    $logo        = $active_store['logo'];

    if ($name === '') {
        $message = "<div class='alert alert-danger'>Store name is required.</div>";
    } else {
        // Handle new logo upload
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp']) && $_FILES['logo']['size'] <= 2*1024*1024) {
                $dir = '../uploads/stores/';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                // Delete old logo
                if ($logo && file_exists($dir . $logo)) unlink($dir . $logo);
                $logo = 'store_' . uniqid('', true) . '.' . $ext;
                move_uploaded_file($_FILES['logo']['tmp_name'], $dir . $logo);
            }
        }

        $upd = $conn->prepare(
            "UPDATE stores SET name=?, description=?, logo=?, primary_color=? WHERE id=?"
        );
        $upd->bind_param('ssssi', $name, $description, $logo, $color, $store_id);
        $upd->execute();
        $upd->close();
        // Refresh store data
        $active_store['name'] = $name;
        $active_store['description'] = $description;
        $active_store['logo'] = $logo;
        $active_store['primary_color'] = $color;
        $message = "<div class='alert alert-success'>Store settings saved.</div>";
    }
}

$page_title = 'Store Settings';
$nav_mode   = 'seller';
include '../header.php';
?>
<section class="page">
  <div class="container">
    <div class="page-head"><div class="eyebrow">Settings</div><h2 class="mb-0">Store Settings</h2></div>
    <?php echo $message; ?>
    <div class="surface form-shell wide">
      <form method="post" enctype="multipart/form-data">
        <div class="mb-3">
          <label class="form-label">Store Name</label>
          <input name="name" type="text" class="form-control" required
                 value="<?php echo htmlspecialchars($active_store['name']); ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Store URL</label>
          <div class="form-control bg-light">/shop/<?php echo htmlspecialchars($active_store['slug']); ?></div>
          <small class="text-muted">URL slug cannot be changed after creation.</small>
        </div>
        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea name="description" rows="3" class="form-control"><?php echo htmlspecialchars($active_store['description'] ?? ''); ?></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Store Logo
            <?php if ($active_store['logo']): ?>
              <img src="../uploads/stores/<?php echo htmlspecialchars($active_store['logo']); ?>"
                   style="height:36px;margin-left:8px;border-radius:4px;">
            <?php endif; ?>
          </label>
          <input name="logo" type="file" class="form-control" accept="image/*">
        </div>
        <div class="mb-4">
          <label class="form-label">Brand Colour</label>
          <input name="primary_color" type="color" class="form-control form-control-color"
                 value="<?php echo htmlspecialchars($active_store['primary_color']); ?>">
        </div>
        <button type="submit" class="btn">Save Settings</button>
        <a href="dashboard.php" class="btn btn-outline-secondary ms-2">Back</a>
      </form>
    </div>
  </div>
</section>
<?php include '../footer.php'; $conn->close(); ?>
