<?php
// product.php
require_once 'config/db.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$product_id = intval($_GET['id']); // Sanitize input

$sql = "SELECT id, name, description, price, image FROM products WHERE id = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Error preparing statement: " . $conn->error);
}
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

$product = null;
if ($result->num_rows > 0) {
    $product = $result->fetch_assoc();
}
$stmt->close();

// Wishlist toggle (requires login)
$wl_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wishlist_action']) && isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    if ($_POST['wishlist_action'] === 'add') {
        $stmt = $conn->prepare("INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $uid, $product_id);
        $stmt->execute();
        $stmt->close();
        $wl_message = 'added';
    } elseif ($_POST['wishlist_action'] === 'remove') {
        $stmt = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $uid, $product_id);
        $stmt->execute();
        $stmt->close();
        $wl_message = 'removed';
    }
    header("Location: product.php?id=" . $product_id . "&wl=" . $wl_message);
    exit;
}

// Check current wishlist state
$in_wishlist = false;
if (isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $uid, $product_id);
    $stmt->execute();
    $in_wishlist = $stmt->get_result()->num_rows > 0;
    $stmt->close();
}

if ($product === null) {
    $page_title = "Product not found";
    include 'header.php';
    echo "<section class='page'><div class='container'><div class='surface p-5 text-center'>"
       . "<h2 class='mb-3'>Product not found</h2>"
       . "<p class='mb-4' style='color:var(--muted);'>That product doesn’t exist or was removed.</p>"
       . "<a href='index.php' class='btn'>Back to shop</a></div></div></section>";
    include 'footer.php';
    $conn->close();
    exit;
}

$page_title = $product['name'];
include 'header.php';
?>
  <section class="page">
    <div class="container">
      <div class="row g-5">
        <div class="col-lg-6">
          <?php
          $img = (!empty($product['image']) && file_exists('uploads/' . $product['image']))
               ? 'uploads/' . htmlspecialchars($product['image'])
               : 'uploads/default_placeholder.png';
          echo "<img src='" . $img . "' alt='" . htmlspecialchars($product['name']) . "' class='detail-img'>";
          ?>
        </div>
        <div class="col-lg-6">
          <div class="eyebrow reveal d1">My E-Shop</div>
          <h2 class="mt-2 mb-3 reveal d2" style="font-size:2.4rem;"><?php echo htmlspecialchars($product['name']); ?></h2>
          <p class="detail-price reveal d2">$<?php echo htmlspecialchars($product['price']); ?></p>
          <hr class="rule my-4 reveal d2">
          <div class="eyebrow reveal d3">Description</div>
          <p class="detail-desc mt-2 reveal d3"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
          <div class="d-flex gap-2 mt-3 reveal d3">
            <a href="cart.php?action=add&id=<?php echo $product['id']; ?>" class="btn">Add to cart</a>
            <?php if (isset($_SESSION['user_id'])): ?>
              <form method="post">
                <input type="hidden" name="wishlist_action" value="<?php echo $in_wishlist ? 'remove' : 'add'; ?>">
                <button type="submit" class="btn <?php echo $in_wishlist ? 'btn-soft-danger' : 'btn-ghost'; ?>">
                  <?php echo $in_wishlist ? '♥ Wishlisted' : '♡ Wishlist'; ?>
                </button>
              </form>
            <?php else: ?>
              <a href="login.php" class="btn btn-ghost">♡ Wishlist</a>
            <?php endif; ?>
          </div>
          <?php if (isset($_GET['wl'])): ?>
            <div class="alert <?php echo $_GET['wl'] === 'added' ? 'alert-success' : 'alert-danger'; ?> mt-3" style="font-size:.88rem;padding:.5rem .9rem;">
              <?php echo $_GET['wl'] === 'added' ? 'Added to your wishlist.' : 'Removed from wishlist.'; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <div class="mt-5"><a href="index.php" class="nav-link-x">&larr; Back to all products</a></div>
    </div>
  </section>
<?php include 'footer.php'; $conn->close(); ?>
