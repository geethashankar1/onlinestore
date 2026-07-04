<?php
// shop/index.php — public storefront for a specific seller store.
// URL: /shop/{slug}  ->  /shop/index.php?slug={slug}  (via .htaccess)
require_once '../config/db.php';
require_once '../config/store.php';

if (!$active_store) {
    http_response_code(404);
    include '../header.php';
    echo '<section class="page"><div class="container"><div class="alert alert-danger mt-5">Store not found.</div></div></section>';
    include '../footer.php';
    exit;
}

$store_id = (int)$active_store['id'];

// Store products
$stmt = $conn->prepare(
    "SELECT id, name, description, price, image FROM products WHERE store_id = ? ORDER BY created_at DESC"
);
$stmt->bind_param('i', $store_id);
$stmt->execute();
$products = $stmt->get_result();
$stmt->close();

$page_title = htmlspecialchars($active_store['name']);
$brand_color = htmlspecialchars($active_store['primary_color']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $page_title; ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Mulish:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/theme.css">
<style>
  .store-header{background:<?php echo $brand_color; ?>;color:#fff;padding:3rem 1rem;text-align:center;}
  .store-header h1{font-size:2.2rem;font-weight:700;margin:0;}
  .store-logo{width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid rgba(255,255,255,.3);margin-bottom:1rem;}
</style>
</head>
<body>
<div class="app-shell">

  <!-- Store header / branding -->
  <div class="store-header">
    <?php if (!empty($active_store['logo'])): ?>
      <img src="/uploads/stores/<?php echo htmlspecialchars($active_store['logo']); ?>"
           class="store-logo" alt="<?php echo $page_title; ?> logo">
    <?php endif; ?>
    <h1><?php echo $page_title; ?></h1>
    <?php if (!empty($active_store['description'])): ?>
      <p class="mt-2 mb-0" style="opacity:.85;"><?php echo htmlspecialchars($active_store['description']); ?></p>
    <?php endif; ?>
  </div>

  <main>
    <section class="page">
      <div class="container">
        <?php if ($products->num_rows === 0): ?>
          <p class="text-center mt-5" style="color:var(--muted);">No products listed yet. Check back soon!</p>
        <?php else: ?>
        <div class="row g-4 mt-2">
          <?php while ($p = $products->fetch_assoc()): ?>
          <div class="col-6 col-md-4 col-lg-3">
            <a href="/product.php?id=<?php echo $p['id']; ?>&store=<?php echo htmlspecialchars($active_store['slug']); ?>"
               class="product-card">
              <?php
              $img = (!empty($p['image']) && file_exists('../uploads/'.$p['image']))
                     ? '/uploads/'.htmlspecialchars($p['image'])
                     : '/uploads/default_placeholder.png';
              ?>
              <div class="product-img-wrap">
                <img src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($p['name']); ?>">
              </div>
              <div class="product-info">
                <div class="product-name"><?php echo htmlspecialchars($p['name']); ?></div>
                <div class="product-price">₹<?php echo number_format($p['price'], 2); ?></div>
              </div>
            </a>
          </div>
          <?php endwhile; ?>
        </div>
        <?php endif; ?>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <div class="container text-center">
      <small style="color:var(--muted);">
        <?php echo $page_title; ?> — powered by <a href="/">MyEShop</a>
      </small>
    </div>
  </footer>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
