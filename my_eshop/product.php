<?php
// product.php
require_once 'config/db.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$product_id = intval($_GET['id']);

$sql = "SELECT id, name, brand, manufacturer, scale, description, price, image FROM products WHERE id = ?";
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
    $redir = 'product.php?id=' . $product_id . '&wl=' . $wl_message;
    header('Location: ' . $redir);
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
    $page_title = 'Product not found';
    include 'header.php';
    $back = 'index.php';
    echo '<section class="page"><div class="container"><div class="surface p-5 text-center">'
       . '<h2 class="mb-3">Product not found</h2>'
       . '<p class="mb-4" style="color:var(--muted);">That product does not exist or was removed.</p>'
       . '<a href="' . $back . '" class="btn">Back to shop</a></div></div></section>';
    include 'footer.php';
    $conn->close();
    exit;
}

$page_title = $product['name'];
include 'header.php';

$img = htmlspecialchars(media_url($product['image'] ?? ''));

$back_url = 'index.php';
$cart_url  = 'cart.php?action=add&id=' . $product['id'];
$login_url = 'login.php';
?>
  <section class="page">
    <div class="container">
      <div class="row g-5">
        <div class="col-lg-6">
          <img src="<?php echo $img; ?>"
               alt="<?php echo htmlspecialchars($product['name']); ?>"
               class="detail-img">
        </div>
        <div class="col-lg-6">
          <div class="eyebrow reveal d1">
            <?php echo htmlspecialchars($product['brand']) ?: 'My E-Shop'; ?>
          </div>
          <h2 class="mt-2 mb-3 reveal d2" style="font-size:2.4rem;">
            <?php echo htmlspecialchars($product['name']); ?>
          </h2>
          <p class="detail-price reveal d2">&#8377;<?php echo number_format((float)$product['price'], 2); ?></p>

          <?php
          // Spec strip — only the attributes that are actually filled in.
          $spec = array_filter([
              'Marque'       => $product['brand'],
              'Manufacturer' => $product['manufacturer'],
              'Scale'        => $product['scale'],
          ]);
          ?>
          <?php if ($spec): ?>
            <dl class="spec-list reveal d2">
              <?php foreach ($spec as $k => $v): ?>
                <div class="spec-row">
                  <dt><?php echo $k; ?></dt>
                  <dd><?php echo htmlspecialchars($v); ?></dd>
                </div>
              <?php endforeach; ?>
            </dl>
          <?php endif; ?>

          <hr class="rule my-4 reveal d2">
          <div class="eyebrow reveal d3">Description</div>
          <p class="detail-desc mt-2 reveal d3">
            <?php echo nl2br(htmlspecialchars($product['description'])); ?>
          </p>
          <div class="d-flex gap-2 mt-3 reveal d3">
            <a href="<?php echo $cart_url; ?>" class="btn">Add to cart</a>
            <?php if (isset($_SESSION['user_id'])): ?>
              <form method="post">
                <input type="hidden" name="wishlist_action"
                       value="<?php echo $in_wishlist ? 'remove' : 'add'; ?>">
                <button type="submit"
                        class="btn <?php echo $in_wishlist ? 'btn-soft-danger' : 'btn-ghost'; ?>">
                  <?php echo $in_wishlist ? "\xe2\x99\xa5 Wishlisted" : "\xe2\x99\xa1 Wishlist"; ?>
                </button>
              </form>
            <?php else: ?>
              <a href="<?php echo $login_url; ?>" class="btn btn-ghost">&#9825; Wishlist</a>
            <?php endif; ?>
          </div>
          <?php if (isset($_GET['wl'])): ?>
            <div class="alert <?php echo $_GET['wl'] === 'added' ? 'alert-success' : 'alert-danger'; ?> mt-3"
                 style="font-size:.88rem;padding:.5rem .9rem;">
              <?php echo $_GET['wl'] === 'added' ? 'Added to your wishlist.' : 'Removed from wishlist.'; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <div class="mt-5">
        <a href="<?php echo $back_url; ?>" class="nav-link-x">&larr; Back to all products</a>
      </div>
    </div>
  </section>
<?php include 'footer.php'; $conn->close(); ?>
