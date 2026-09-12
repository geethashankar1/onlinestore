<?php
// header.php — shared top layout. Include AFTER config/db.php (session already started).
// Optional vars a page may set before including: $page_title (string), $nav_mode ('shop'|'admin').
$page_title  = isset($page_title)  ? $page_title  : 'My E-Shop';
$nav_mode    = isset($nav_mode)    ? $nav_mode    : 'shop';
$brand_name  = isset($brand_name)  ? $brand_name  : 'My E&#8209;Shop';
$brand_logo  = isset($brand_logo)  ? $brand_logo  : '';   // URL string or empty
$brand_color = isset($brand_color) ? $brand_color : '';   // hex or empty
$store_slug  = isset($store_slug)  ? $store_slug  : '';   // set by storefront pages
$cart_count  = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
$is_admin    = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
$logged_in   = isset($_SESSION['user_id']);
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
<?php if ($brand_color): ?>
<?php
/* Must come AFTER theme.css or the default --accent wins and the seller's
   chosen colour silently does nothing.

   --on-accent is computed, not fixed: the theme's default is near-black, which
   would be unreadable on a dark brand colour. Picking it from the colour's
   relative luminance (WCAG) keeps button labels legible whatever the seller
   chooses. */
$hex = ltrim($brand_color, '#');
if (strlen($hex) === 3) { $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2]; }
$lum = 1.0;
if (strlen($hex) === 6 && ctype_xdigit($hex)) {
    $chan = [];
    foreach ([0, 2, 4] as $i) {
        $c = hexdec(substr($hex, $i, 2)) / 255;
        $chan[] = $c <= 0.04045 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
    }
    $lum = 0.2126 * $chan[0] + 0.7152 * $chan[1] + 0.0722 * $chan[2];
}
$on_accent = $lum > 0.4 ? '#0A0B0D' : '#F4F2ED';
?>
<style>:root{
  --accent:<?php echo htmlspecialchars($brand_color); ?>;
  --accent-hover:color-mix(in srgb, <?php echo htmlspecialchars($brand_color); ?> 82%, <?php echo $lum > 0.4 ? '#000' : '#fff'; ?>);
  --on-accent:<?php echo $on_accent; ?>;
}</style>
<?php endif; ?>
</head>
<body>
<div class="app-shell">
  <?php
  // Ticker repeats its content three times so the -50% keyframe loops seamlessly.
  $ticker = ['Free delivery over ₹999','Independent sellers only','1:64 to 1:12 scale','Authenticity checked'];
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
      <?php
      if ($nav_mode === 'seller')      $brand_href = '/seller/dashboard.php';
      elseif ($nav_mode === 'storefront') $brand_href = '/shop/' . htmlspecialchars($store_slug);
      else                              $brand_href = '/index.php';
      ?>
      <?php
      // Badge letter: the store's initial on a storefront, "M" for the marketplace.
      $brand_initial = strtoupper(mb_substr(trim(html_entity_decode(strip_tags($brand_name))), 0, 1)) ?: 'M';
      ?>
      <a class="brand" href="<?php echo $brand_href; ?>">
        <?php if ($brand_logo): ?>
          <img src="<?php echo htmlspecialchars($brand_logo); ?>" alt="<?php echo htmlspecialchars(strip_tags($brand_name)); ?>"
               style="height:30px;width:30px;object-fit:cover;flex:0 0 auto;">
        <?php else: ?>
          <span class="dot"><span><?php echo htmlspecialchars($brand_initial); ?></span></span>
        <?php endif; ?>
        <span><?php echo $brand_name; ?></span><?php if ($nav_mode === 'admin'): ?> <small>Admin</small><?php endif; ?>
      </a>
      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#siteNav"><span class="navbar-toggler-icon"></span></button>
      <div class="collapse navbar-collapse justify-content-end" id="siteNav">
        <ul class="navbar-nav align-items-lg-center gap-lg-4 mt-3 mt-lg-0">
        <?php if ($nav_mode === 'seller'): ?>
          <li class="nav-item"><a class="nav-link-x" href="/seller/dashboard.php">Dashboard</a></li>
          <li class="nav-item"><a class="nav-link-x" href="/seller/add_product.php">Add Product</a></li>
          <li class="nav-item"><a class="nav-link-x" href="/seller/products.php">Products</a></li>
          <li class="nav-item"><a class="nav-link-x" href="/seller/orders.php">Orders</a></li>
          <li class="nav-item"><a class="nav-link-x" href="/seller/settings.php">Settings</a></li>
          <li class="nav-item mt-2 mt-lg-0"><a class="nav-link-x" href="/logout.php">Logout</a></li>
        <?php elseif ($nav_mode === 'admin'): ?>
          <li class="nav-item"><a class="nav-link-x" href="/index.php">View Shop</a></li>
          <li class="nav-item"><a class="nav-link-x" href="/admin/add_product.php">Add Product</a></li>
          <li class="nav-item"><a class="nav-link-x" href="/admin/manage_products.php">Manage Products</a></li>
          <li class="nav-item"><a class="nav-link-x" href="/admin/view_orders.php">View Orders</a></li>
          <li class="nav-item"><a class="nav-link-x" href="/admin/stores.php">Stores</a></li>
          <li class="nav-item mt-2 mt-lg-0"><a class="nav-link-x" href="/logout.php">Logout</a></li>
        <?php elseif ($nav_mode === 'storefront'): ?>
          <li class="nav-item"><a class="nav-link-x" href="/shop/<?php echo htmlspecialchars($store_slug); ?>">Home</a></li>
          <li class="nav-item mt-2 mt-lg-0"><a class="cart-pill" href="/cart.php?store=<?php echo htmlspecialchars($store_slug); ?>">Cart <span class="n"><?php echo str_pad((string)$cart_count, 2, "0", STR_PAD_LEFT); ?></span></a></li>
          <?php if ($logged_in): ?>
            <li class="nav-item"><a class="nav-link-x" href="/orders.php?store=<?php echo htmlspecialchars($store_slug); ?>">My Orders</a></li>
            <li class="nav-item"><a class="nav-link-x" href="/logout.php?store=<?php echo htmlspecialchars($store_slug); ?>">Logout</a></li>
          <?php else: ?>
            <li class="nav-item"><a class="nav-link-x" href="/login.php?store=<?php echo htmlspecialchars($store_slug); ?>">Login</a></li>
            <li class="nav-item"><a class="cta-register" href="/register.php?store=<?php echo htmlspecialchars($store_slug); ?>"><span>Register</span></a></li>
          <?php endif; ?>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link-x" href="/index.php">Home</a></li>
          <li class="nav-item"><a class="nav-link-x" href="/index.php#stores">Browse Stores</a></li>
          <li class="nav-item mt-2 mt-lg-0"><a class="cart-pill" href="/cart.php">Cart <span class="n"><?php echo str_pad((string)$cart_count, 2, "0", STR_PAD_LEFT); ?></span></a></li>
          <?php if ($logged_in): ?>
            <?php if ($is_admin): ?><li class="nav-item"><a class="nav-link-x" href="/admin/add_product.php">Admin</a></li><?php endif; ?>
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
