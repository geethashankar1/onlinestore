<?php
// header.php — shared top layout. Include AFTER config/db.php (session already started).
// Optional vars a page may set before including: $page_title (string), $nav_mode ('shop'|'admin').
$page_title = isset($page_title) ? $page_title : 'My E-Shop';
$nav_mode   = isset($nav_mode) ? $nav_mode : 'shop';
$cart_count = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
$is_admin   = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
$logged_in  = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($page_title); ?> · My E-Shop</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,400&family=Mulish:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/theme.css">
</head>
<body>
<div class="app-shell">
  <div class="announce">Complimentary shipping on orders over $75 — handpicked, made to last</div>
  <div class="nav-shell">
    <nav class="navbar navbar-expand-lg container py-3">
      <a class="brand" href="/index.php">My E&#8209;Shop <span class="dot"></span><?php if ($nav_mode === 'admin'): ?> <small>Admin</small><?php endif; ?></a>
      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#siteNav"><span class="navbar-toggler-icon"></span></button>
      <div class="collapse navbar-collapse justify-content-end" id="siteNav">
        <ul class="navbar-nav align-items-lg-center gap-lg-4 mt-3 mt-lg-0">
        <?php if ($nav_mode === 'admin'): ?>
          <li class="nav-item"><a class="nav-link-x" href="/index.php">View Shop</a></li>
          <li class="nav-item"><a class="nav-link-x" href="/admin/add_product.php">Add Product</a></li>
          <li class="nav-item"><a class="nav-link-x" href="/admin/manage_products.php">Manage Products</a></li>
          <li class="nav-item"><a class="nav-link-x" href="/admin/view_orders.php">View Orders</a></li>
          <li class="nav-item mt-2 mt-lg-0"><a class="nav-link-x" href="/logout.php">Logout</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link-x" href="/index.php">Home</a></li>
          <li class="nav-item mt-2 mt-lg-0"><a class="cart-pill" href="/cart.php">Cart · <?php echo $cart_count; ?></a></li>
          <?php if ($logged_in): ?>
            <?php if ($is_admin): ?><li class="nav-item"><a class="nav-link-x" href="/admin/add_product.php">Admin</a></li><?php endif; ?>
            <li class="nav-item"><a class="nav-link-x" href="/profile.php">Profile</a></li>
            <li class="nav-item"><a class="nav-link-x" href="/logout.php">Logout</a></li>
          <?php else: ?>
            <li class="nav-item"><a class="nav-link-x" href="/login.php">Login</a></li>
            <li class="nav-item"><a class="nav-link-x" href="/register.php">Register</a></li>
          <?php endif; ?>
        <?php endif; ?>
        </ul>
      </div>
    </nav>
  </div>
  <main>
