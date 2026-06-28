<?php
// index.php
require_once 'config/db.php';

$search_term = '';
if (isset($_GET['search'])) {
    $search_term = $conn->real_escape_string($_GET['search']);
}
$page_title = "Home";
include 'header.php';
?>
  <section class="hero">
    <div class="container">
      <div class="row align-items-center g-5">
        <div class="col-lg-6">
          <div class="eyebrow reveal d1">Curated essentials</div>
          <h1 class="mt-3 reveal d2">Considered things,<br>beautifully made.</h1>
          <hr class="rule my-4 reveal d2">
          <p class="lead-x reveal d3">A small, deliberate collection — chosen for craft, restraint, and the way it ages. Browse the catalogue below.</p>
          <div class="d-flex flex-wrap gap-3 mt-4 reveal d3">
            <a href="#products" class="btn">Shop the collection</a>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="hero-figure reveal d3">
            <img src="https://placehold.co/760x560/2A382E/C9B891?text=%E2%9C%A6" alt="Featured">
          </div>
        </div>
      </div>
    </div>
  </section>

  <section id="products" class="page pt-2">
    <div class="container">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4 page-head">
        <div>
          <div class="eyebrow">The catalogue</div>
          <h2 class="mb-0">Our Products</h2>
        </div>
        <form method="GET" action="index.php" class="search-wrap" style="max-width:420px;width:100%;">
          <div class="input-group">
            <input type="text" name="search" class="form-control" placeholder="Search products…" value="<?php echo htmlspecialchars($search_term); ?>">
            <button type="submit" class="btn">Search</button>
          </div>
        </form>
      </div>

      <div class="row g-4">
        <?php
        $sql = "SELECT id, name, price, image, description FROM products";
        if (!empty($search_term)) {
            $sql .= " WHERE name LIKE '%$search_term%' OR description LIKE '%$search_term%'";
        }
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $img = (!empty($row['image']) && file_exists('uploads/' . $row['image']))
                     ? 'uploads/' . htmlspecialchars($row['image'])
                     : 'uploads/default_placeholder.png';
                echo "<div class='col-12 col-sm-6 col-lg-4'>";
                echo   "<article class='product-card'>";
                echo     "<a href='product.php?id=" . $row['id'] . "' class='pc-img d-block'><img src='" . $img . "' alt='" . htmlspecialchars($row['name']) . "'></a>";
                echo     "<div class='pc-body'>";
                echo       "<h3 class='pc-name'>" . htmlspecialchars($row['name']) . "</h3>";
                echo       "<p class='pc-desc'>" . htmlspecialchars(substr($row['description'], 0, 90)) . "…</p>";
                echo       "<div class='d-flex justify-content-between align-items-center mb-3'>";
                echo         "<span class='pc-price'>$" . htmlspecialchars($row['price']) . "</span>";
                echo         "<a href='product.php?id=" . $row['id'] . "' class='nav-link-x' style='font-size:.85rem;'>View details</a>";
                echo       "</div>";
                echo       "<a href='cart.php?action=add&id=" . $row['id'] . "' class='btn btn-brass-outline w-100'>Add to cart</a>";
                echo     "</div>";
                echo   "</article>";
                echo "</div>";
            }
        } else {
            echo "<div class='col-12'><div class='surface p-5 text-center'><p class='mb-0' style='color:var(--muted);'>No products found.</p></div></div>";
        }
        ?>
      </div>
    </div>
  </section>
<?php include 'footer.php'; $conn->close(); ?>
