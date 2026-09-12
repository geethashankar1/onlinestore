<?php
// seller/welcome.php — post-login choice screen for sellers.
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header('Location: ../login.php'); exit;
}

require_once '../config/store.php';
$store       = $active_store;
$store_slug  = $store ? $store['slug'] : '';
$store_name  = $store ? htmlspecialchars($store['name']) : '';
$seller_name = htmlspecialchars($_SESSION['username'] ?? 'Seller');

$page_title = 'Welcome back, ' . $seller_name;
$nav_mode   = 'seller';
include '../header.php';
?>

<style>
.welcome-wrap{min-height:70vh;display:flex;align-items:center;justify-content:center;padding:3rem 1rem;}
.welcome-card{background:var(--surface);border:1px solid var(--line);border-radius:0;
  padding:2.5rem 2rem;max-width:560px;width:100%;text-align:center;}
.welcome-heading{font-size:2rem;font-weight:600;
  color:var(--ink);margin-bottom:.35rem;}
.welcome-sub{color:var(--muted);font-size:.95rem;margin-bottom:2.5rem;}
.choice-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
@media(max-width:480px){.choice-grid{grid-template-columns:1fr;}}
.choice-btn{display:flex;flex-direction:column;align-items:center;gap:.6rem;
  border:1.5px solid var(--line);border-radius:6px;padding:1.6rem 1rem;
  text-decoration:none;color:var(--ink);transition:border-color .2s,box-shadow .2s;}
.choice-btn:hover{border-color:var(--text-muted);box-shadow:var(--shadow-sm);color:var(--text);}
.choice-btn.primary{background:var(--accent);border-color:var(--accent);color:#fff;}
.choice-btn.primary:hover{background:var(--accent-hover);border-color:var(--accent-hover);color:#fff;}
.choice-icon{font-size:1.75rem;line-height:1;}
.choice-label{font-weight:700;font-size:.95rem;}
.choice-desc{font-size:.8rem;color:inherit;opacity:.7;}
.no-store-note{margin-top:2rem;font-size:.85rem;color:var(--muted);}
</style>

<div class="welcome-wrap">
  <div class="welcome-card reveal d1">
    <p class="welcome-sub">Welcome back</p>
    <h1 class="welcome-heading"><?php echo $seller_name; ?></h1>
    <p class="welcome-sub">Where would you like to go?</p>

    <div class="choice-grid">
      <a href="/seller/dashboard.php" class="choice-btn primary">
        <span class="choice-icon">&#9783;</span>
        <span class="choice-label">Dashboard</span>
        <span class="choice-desc">Manage products &amp; orders</span>
      </a>

      <?php if ($store_slug): ?>
      <a href="/shop/<?php echo htmlspecialchars($store_slug); ?>" class="choice-btn">
        <span class="choice-icon">&#127978;</span>
        <span class="choice-label">My Store</span>
        <span class="choice-desc"><?php echo $store_name; ?></span>
      </a>
      <?php else: ?>
      <a href="/seller/setup.php" class="choice-btn">
        <span class="choice-icon">&#43;</span>
        <span class="choice-label">Set Up Store</span>
        <span class="choice-desc">Create your storefront</span>
      </a>
      <?php endif; ?>
    </div>

    <?php if ($store_slug): ?>
    <p class="no-store-note">
      Your store URL: <a href="/shop/<?php echo htmlspecialchars($store_slug); ?>" style="color:var(--brass-deep);">/shop/<?php echo htmlspecialchars($store_slug); ?></a>
    </p>
    <?php endif; ?>
  </div>
</div>

<?php include '../footer.php'; $conn->close(); ?>
