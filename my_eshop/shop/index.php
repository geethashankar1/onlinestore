<?php
// shop/index.php — public storefront for a specific seller.
// URL: /shop/{slug}  (via shop/.htaccess rewrite)
require_once '../config/db.php';
require_once '../config/store.php';

if (!$active_store) {
    http_response_code(404);
    $page_title = 'Store Not Found';
    include '../header.php';
    echo '<section class="page"><div class="container"><div class="alert alert-danger mt-5">This store does not exist or is not active.</div></div></section>';
    include '../footer.php';
    $conn->close(); exit;
}

$store_id = (int)$active_store['id'];
$slug     = $active_store['slug'];

// Search within this store's products
$search_term = '';
if (isset($_GET['search'])) {
    $search_term = $conn->real_escape_string(trim($_GET['search']));
}

// Fetch products scoped to this store
$sql = "SELECT id, name, price, image, description FROM products WHERE store_id = $store_id";
if ($search_term !== '') {
    $sql .= " AND (name LIKE '%$search_term%' OR description LIKE '%$search_term%')";
}
$sql .= " ORDER BY created_at DESC";
$result = $conn->query($sql);

// Pass branding to header.php
$page_title  = htmlspecialchars($active_store['name']);
$brand_name  = htmlspecialchars($active_store['name']);
$brand_color = htmlspecialchars($active_store['primary_color']);
$brand_logo  = !empty($active_store['logo'])
               ? '/uploads/stores/' . htmlspecialchars($active_store['logo'])
               : '';

include '../header.php';
?>

  <!-- Hero — same structure as index.php but seller-branded -->
  <section class="hero">
    <div class="container">
      <div class="row align-items-center g-5">
        <div class="col-lg-6">
          <div class="eyebrow reveal d1"><?php echo htmlspecialchars($active_store['name']); ?></div>
          <h1 class="mt-3 reveal d2">
            <?php echo !empty($active_store['description'])
                       ? htmlspecialchars($active_store['description'])
                       : 'Quality diecast cars,<br>handpicked for collectors.'; ?>
          </h1>
          <hr class="rule my-4 reveal d2">
          <p class="lead-x reveal d3">Browse the full catalogue below.</p>
          <div class="d-flex flex-wrap gap-3 mt-4 reveal d3">
            <a href="#products" class="btn">Shop the collection</a>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="hero-figure reveal d3">
            <?php if ($brand_logo): ?>
              <img src="<?php echo $brand_logo; ?>"
                   alt="<?php echo htmlspecialchars($active_store['name']); ?>"
                   style="width:100%;max-height:420px;object-fit:contain;">
            <?php else: ?>
              <img src="https://placehold.co/760x560/2A382E/C9B891?text=<?php echo urlencode($active_store['name']); ?>" alt="Store banner">
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Products — identical markup to index.php -->
  <section id="products" class="page pt-2">
    <div class="container">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4 page-head">
        <div>
          <div class="eyebrow">The catalogue</div>
          <h2 class="mb-0">Products</h2>
        </div>
        <form method="GET" action="/shop/<?php echo $slug; ?>" class="search-wrap" style="max-width:420px;width:100%;">
          <div class="input-group">
            <input type="text" name="search" class="form-control" placeholder="Search products…"
                   value="<?php echo htmlspecialchars($search_term); ?>">
            <button type="submit" class="btn">Search</button>
          </div>
        </form>
      </div>

      <div class="row g-4">
        <?php
        if ($result && $result->num_rows > 0):
            while ($row = $result->fetch_assoc()):
                $img = (!empty($row['image']) && file_exists('../uploads/' . $row['image']))
                       ? '/uploads/' . htmlspecialchars($row['image'])
                       : '/uploads/default_placeholder.png';
        ?>
        <div class="col-12 col-sm-6 col-lg-4">
          <article class="product-card">
            <a href="/product.php?id=<?php echo $row['id']; ?>&store=<?php echo $slug; ?>" class="pc-img d-block">
              <img src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($row['name']); ?>">
            </a>
            <div class="pc-body">
              <h3 class="pc-name"><?php echo htmlspecialchars($row['name']); ?></h3>
              <p class="pc-desc"><?php echo htmlspecialchars(substr($row['description'], 0, 90)); ?>…</p>
              <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="pc-price">₹<?php echo number_format($row['price'], 2); ?></span>
                <a href="/product.php?id=<?php echo $row['id']; ?>&store=<?php echo $slug; ?>"
                   class="nav-link-x" style="font-size:.85rem;">View details</a>
              </div>
              <a href="/cart.php?action=add&id=<?php echo $row['id']; ?>" class="btn btn-brass-outline w-100">Add to cart</a>
            </div>
          </article>
        </div>
        <?php endwhile; else: ?>
        <div class="col-12">
          <div class="surface p-5 text-center">
            <p class="mb-0" style="color:var(--muted);">No products listed yet. Check back soon!</p>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

<?php include '../footer.php'; $conn->close(); ?>
