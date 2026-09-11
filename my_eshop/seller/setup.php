<?php
// seller/setup.php — first-time store creation for a newly registered seller.
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header('Location: ../login.php'); exit;
}

// If seller already has a store, go to dashboard
$check = $conn->prepare("SELECT id FROM stores WHERE seller_id = ?");
$check->bind_param('i', $_SESSION['user_id']);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    header('Location: dashboard.php'); exit;
}
$check->close();

$message = '';
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name']        ?? '');
    $slug        = strtolower(trim(preg_replace('/[^a-z0-9_-]/i', '', $_POST['slug'] ?? '')));
    $description = trim($_POST['description'] ?? '');
    $color       = preg_match('/^#[0-9a-f]{6}$/i', $_POST['primary_color'] ?? '') ? $_POST['primary_color'] : '#C2542A';

    if ($name  === '') $errors[] = 'Store name is required.';
    if ($slug  === '') $errors[] = 'Store URL slug is required.';
    if (strlen($slug) < 3) $errors[] = 'Slug must be at least 3 characters.';

    if (empty($errors)) {
        // Check slug uniqueness
        $dup = $conn->prepare("SELECT id FROM stores WHERE slug = ?");
        $dup->bind_param('s', $slug);
        $dup->execute();
        if ($dup->get_result()->num_rows > 0) $errors[] = 'That URL slug is already taken. Choose another.';
        $dup->close();
    }

    // Handle logo upload — Cloudinary in production, uploads/stores/ locally.
    $logo = '';
    if (empty($errors) && isset($_FILES['logo'])) {
        $upload_error = null;
        $logo = media_store($_FILES['logo'], 'stores', $upload_error);
        if ($upload_error !== null) {
            $errors[] = $upload_error;
        }
    }

    if (empty($errors)) {
        $stmt = $conn->prepare(
            "INSERT INTO stores (seller_id, name, slug, description, logo, primary_color) VALUES (?,?,?,?,?,?)"
        );
        $stmt->bind_param('isssss', $_SESSION['user_id'], $name, $slug, $description, $logo, $color);
        if ($stmt->execute()) {
            header('Location: dashboard.php'); exit;
        } else {
            $errors[] = 'Could not create store: ' . $stmt->error;
        }
        $stmt->close();
    }
}

$page_title = 'Set Up Your Store';
$nav_mode   = 'seller';
include '../header.php';
?>
<section class="page">
  <div class="container">
    <div class="surface form-shell wide">
      <div class="text-center mb-4">
        <div class="eyebrow">Welcome, seller</div>
        <h2 class="mt-2 mb-0">Set Up Your Store</h2>
      </div>

      <?php foreach ($errors as $e): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($e); ?></div>
      <?php endforeach; ?>

      <form method="post" enctype="multipart/form-data">
        <div class="mb-3">
          <label class="form-label">Store Name</label>
          <input name="name" type="text" class="form-control" required
                 value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Store URL Slug <small class="text-muted">(letters, numbers, hyphens only)</small></label>
          <div class="input-group">
            <span class="input-group-text">/shop/</span>
            <input name="slug" type="text" class="form-control" required pattern="[a-z0-9_-]+"
                   value="<?php echo htmlspecialchars($_POST['slug'] ?? ''); ?>"
                   oninput="this.value=this.value.toLowerCase().replace(/[^a-z0-9_-]/g,'')">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Description <small class="text-muted">(optional)</small></label>
          <textarea name="description" rows="3" class="form-control"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Store Logo <small class="text-muted">(optional, max 2MB)</small></label>
          <input name="logo" type="file" class="form-control" accept="image/*">
        </div>
        <div class="mb-4">
          <label class="form-label">Brand Colour</label>
          <input name="primary_color" type="color" class="form-control form-control-color"
                 value="<?php echo htmlspecialchars($_POST['primary_color'] ?? '#C2542A'); ?>">
        </div>
        <button type="submit" class="btn w-100">Launch My Store</button>
      </form>
    </div>
  </div>
</section>
<?php include '../footer.php'; $conn->close(); ?>
