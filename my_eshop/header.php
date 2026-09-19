<?php
// header.php — shared top layout. Include AFTER config/db.php (session already started).
// Optional vars a page may set before including: $page_title (string), $nav_mode ('shop'|'admin').
$page_title = $page_title ?? 'My E-Shop';
$nav_mode   = $nav_mode   ?? 'shop';
// Sum of quantities, not number of lines — two of one model reads as 2.
// cart_count() picks the account's cart when signed in, the session otherwise.
$cart_count = isset($conn) ? cart_count($conn) : 0;
$is_admin   = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
$logged_in  = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($page_title); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=Archivo:ital,wght@0,400;0,500;0,600;0,700;1,600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/theme.css">
</head>
<body>
<div class="app-shell">
  <?php
  // Ticker repeats its content three times so the -50% keyframe loops seamlessly.
  $ticker = ['Free delivery over ₹999','Hand-picked diecast','1:64 to 1:12 scale','Authenticity checked'];
  ?>
  <div class="announce">
    <div class="ticker">
      <?php for ($i = 0; $i < 3; $i++): foreach ($ticker as $t): ?>
        <span><?php echo $t; ?></span><span>◆</span>
      <?php endforeach; endfor; ?>
    </div>
  </div>
  <div class="nav-shell">
    <nav class="navbar navbar-expand-lg container py-3">
      <a class="brand" href="<?php echo $nav_mode === 'admin' ? '/admin/manage_products.php' : '/index.php'; ?>">
        <span class="dot"><span>M</span></span>
        <span>My E&#8209;Shop</span><?php if ($nav_mode === 'admin'): ?> <small>Admin</small><?php endif; ?>
      </a>
      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#siteNav"><span class="navbar-toggler-icon"></span></button>
      <div class="collapse navbar-collapse justify-content-end" id="siteNav">
        <ul class="navbar-nav align-items-lg-center gap-lg-4 mt-3 mt-lg-0">
        <?php if ($nav_mode === 'admin'): ?>
          <li class="nav-item"><a class="nav-link-x" href="/index.php">View Shop</a></li>
          <li class="nav-item"><a class="nav-link-x" href="/admin/add_product.php">Add Product</a></li>
          <li class="nav-item"><a class="nav-link-x" href="/admin/manage_products.php">Products</a></li>
          <li class="nav-item"><a class="nav-link-x" href="/admin/view_orders.php">Orders</a></li>
          <li class="nav-item mt-2 mt-lg-0"><a class="nav-link-x" href="/logout.php">Logout</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link-x" href="/index.php">Shop</a></li>
          <li class="nav-item mt-2 mt-lg-0"><a class="cart-pill" href="/cart.php">Cart <span class="n"><?php echo str_pad((string)$cart_count, 2, "0", STR_PAD_LEFT); ?></span></a></li>
          <?php if ($logged_in): ?>
            <?php if ($is_admin): ?><li class="nav-item"><a class="nav-link-x" href="/admin/manage_products.php">Admin</a></li><?php endif; ?>
            <li class="nav-item"><a class="nav-link-x" href="/profile.php">Profile</a></li>
            <li class="nav-item"><a class="nav-link-x" href="/logout.php">Logout</a></li>
          <?php else: ?>
            <li class="nav-item"><a class="nav-link-x" href="/login.php">Login</a></li>
            <li class="nav-item"><a class="cta-register" href="/register.php"><span>Register</span></a></li>
          <?php endif; ?>
        <?php endif; ?>
        </ul>
      </div>
    </nav>
  </div>
  <main>
    <?php if (!empty($_SESSION['flash'])): ?>
      <?php /* One-shot message set before a redirect; cleared on display. */ ?>
      <div class="container pt-4">
        <div class="alert alert-success mb-0"><?php echo htmlspecialchars($_SESSION['flash']); ?></div>
      </div>
      <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>
