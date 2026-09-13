<?php
require_once 'config/db.php';
require_once 'config/catalogue.php';
$page_title = 'My E-Shop — Diecast Models';
include 'header.php';

// Whole catalogue, newest first. Filtering is client-side (see the script at the
// bottom) so browsing stays instant on a catalogue this size.
$products    = $conn->query(
    "SELECT id, name, brand, manufacturer, scale, description, price, image, created_at
     FROM products ORDER BY created_at DESC"
);
$total_count = $products ? $products->num_rows : 0;

// Filter chips are built from what is actually listed, so they never offer a
// value that would return nothing.
$filter_groups = [
    'brand'        => ['label' => 'Marque',       'values' => catalogue_values($conn, 'brand')],
    'manufacturer' => ['label' => 'Manufacturer', 'values' => catalogue_values($conn, 'manufacturer')],
    'scale'        => ['label' => 'Scale',        'values' => catalogue_values($conn, 'scale')],
];

// Pre-populate the search box when arriving with ?search=
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
?>

<style>
/* ── Shop home ─────────────────────────────────────────────── */

/* Hero */
.ld-hero{position:relative;overflow:hidden;border-bottom:1px solid var(--line);}
.ld-hero .stripes{position:absolute;inset:0;pointer-events:none;
  background:repeating-linear-gradient(102deg,rgba(255,61,0,.14) 0 3px,transparent 3px 58px);}
.ld-hero .glow{position:absolute;top:-140px;right:-120px;width:620px;height:620px;pointer-events:none;
  background:radial-gradient(circle,rgba(255,61,0,.30),transparent 66%);}
.hero-grid{position:relative;padding:clamp(48px,7vw,96px) 0 clamp(36px,5vw,64px);
  max-width:1040px;margin:0 auto;text-align:center;}
.ld-badge{display:inline-flex;align-items:center;gap:9px;border:1px solid rgba(198,255,0,.45);
  padding:7px 14px;font-family:var(--mono);font-size:11px;font-weight:700;letter-spacing:.18em;
  text-transform:uppercase;color:var(--lime);margin-bottom:26px;}
.ld-badge .blip{width:7px;height:7px;background:var(--lime);border-radius:50%;animation:blip 1.6s infinite;}
.ld-h1{font-family:var(--display);font-size:clamp(34px,5.6vw,82px);line-height:.92;letter-spacing:-.035em;
  text-transform:uppercase;margin:0 0 22px;}
.ld-h1 em{color:var(--accent);font-style:italic;display:inline-block;}
.ld-lead{max-width:46ch;font-size:clamp(15px,1.35vw,18px);line-height:1.6;color:var(--text-muted);
  margin:0 auto 34px;}
.ld-btns{display:flex;flex-wrap:wrap;gap:14px;margin-bottom:0;justify-content:center;}

/* Scale chips + search */
.ld-scales{border-bottom:1px solid var(--line);background:var(--bg-2);position:sticky;top:0;z-index:30;}
.scale-row{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:18px 0;}
.scale-row .lbl{font-family:var(--mono);font-size:10.5px;font-weight:700;letter-spacing:.18em;
  text-transform:uppercase;color:var(--text-faint);margin-right:6px;}
.chip{font-family:var(--mono);font-size:11.5px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;
  padding:9px 14px;cursor:pointer;border:1px solid var(--line-2);background:transparent;color:var(--text-muted);
  transition:border-color .15s ease,color .15s ease,background .15s ease;}
.chip:hover{border-color:rgba(198,255,0,.6);color:var(--text);}
.chip-on{border-color:var(--lime);background:var(--lime);color:var(--on-accent);}
.shop-search{margin-left:auto;min-width:220px;flex:0 1 320px;}
.shop-search .form-control{padding:9px 12px;font-size:13px;}

/* Catalogue */
.ld-shop{padding:clamp(44px,5vw,72px) 0 clamp(56px,6vw,88px);}
.sec-head{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;flex-wrap:wrap;margin-bottom:32px;}
.sec-eyebrow{font-family:var(--mono);font-size:10.5px;font-weight:700;letter-spacing:.2em;
  text-transform:uppercase;color:var(--lime);margin-bottom:12px;}
.count{font-family:var(--mono);font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:var(--text-faint);}
.model-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:18px;}
.mc{background:var(--surface);border:1px solid var(--line);display:block;color:inherit;
  transition:border-color .15s ease;}
.mc:hover{border-color:rgba(255,61,0,.6);color:inherit;}
.mc-img{position:relative;aspect-ratio:1/1;background:var(--bg-2);overflow:hidden;}
.mc-img img{width:100%;height:100%;object-fit:cover;display:block;}
.mc-body{padding:16px;}
.mc-spec{font-family:var(--mono);font-size:10.5px;letter-spacing:.14em;text-transform:uppercase;
  color:var(--text-faint);margin-bottom:7px;}
.mc-scale{position:absolute;top:10px;left:10px;background:var(--bg);color:var(--lime);
  font-family:var(--mono);font-size:10px;font-weight:700;letter-spacing:.14em;padding:5px 8px;
  pointer-events:none;}
.mc-name{font-size:15px;font-weight:700;margin:0 0 6px;line-height:1.35;font-family:var(--body);
  text-transform:none;letter-spacing:0;}
.mc-desc{font-size:13px;color:var(--text-muted);margin:0 0 12px;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.mc-price{font-family:var(--display);font-size:18px;}

/* Empty states */
.empty{border:1px dashed var(--line-strong);padding:48px 24px;text-align:center;
  background:repeating-linear-gradient(-45deg,rgba(255,255,255,.03) 0 10px,transparent 10px 20px);}
.empty h3{font-size:20px;margin:0 0 10px;}
.empty p{color:var(--text-muted);margin:0 auto;max-width:44ch;}

/* Trust row */
.ld-steps{border-top:1px solid var(--line);}
.cards-1px{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1px;
  background:var(--line);}
.step-card{background:var(--bg);padding:34px 30px;}
.step-num{font-family:var(--mono);font-size:12px;font-weight:700;letter-spacing:.2em;
  color:var(--accent);margin-bottom:24px;}
.step-card h3{font-size:20px;margin:0 0 12px;}
.step-card p{font-size:14.5px;line-height:1.65;color:var(--text-muted);margin:0;}

@media (max-width:720px){ .shop-search{margin-left:0;flex:1 1 100%;} }
</style>

<!-- ── HERO ──────────────────────────────────────────────────── -->
<section class="ld-hero" id="top">
  <div class="stripes"></div><div class="glow"></div>
  <div class="container">
    <div class="hero-grid">
      <div class="ld-badge"><span class="blip"></span>Hand-picked diecast</div>
      <h1 class="ld-h1">
        Small scale.<br>
        <em>Full throttle</em><br>
        collecting.
      </h1>
      <p class="ld-lead">
        Rare and premium diecast models &mdash; every piece hand-picked,
        photographed and checked before it ships.
      </p>
      <div class="ld-btns">
        <a href="#catalogue" class="btn"><span>Browse the collection</span></a>
      </div>
    </div>
  </div>
</section>

<!-- ── FILTER BAR ────────────────────────────────────────────── -->
<section class="ld-scales">
  <div class="container">
    <div class="scale-row">
      <span class="lbl">Filter</span>
      <button class="chip chip-on" data-facet="" data-value="">All</button>
      <?php foreach ($filter_groups as $facet => $g): ?>
        <?php foreach ($g['values'] as $v): ?>
          <button class="chip" data-facet="<?php echo $facet; ?>"
                  data-value="<?php echo htmlspecialchars($v); ?>"
                  title="<?php echo htmlspecialchars($g['label']); ?>">
            <?php echo htmlspecialchars($v); ?>
          </button>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <div class="shop-search">
        <input type="search" id="shopSearch" class="form-control" placeholder="Search models…"
               value="<?php echo htmlspecialchars($search_term); ?>" autocomplete="off">
      </div>
    </div>
  </div>
</section>

<!-- ── CATALOGUE ─────────────────────────────────────────────── -->
<section class="ld-shop" id="catalogue">
  <div class="container">
    <div class="sec-head">
      <div>
        <div class="sec-eyebrow">The collection</div>
        <h2>All models</h2>
      </div>
      <span class="count" id="resultCount"><?php echo $total_count; ?> model<?php echo $total_count !== 1 ? 's' : ''; ?></span>
    </div>

    <?php if ($total_count > 0): ?>
      <div class="model-grid" id="modelGrid">
        <?php while ($p = $products->fetch_assoc()):
          $spec = array_filter([$p['manufacturer'], $p['scale']]);
        ?>
          <a href="product.php?id=<?php echo (int)$p['id']; ?>" class="mc product-item"
             data-name="<?php echo htmlspecialchars(strtolower($p['name'] . ' ' . $p['description'] . ' ' . $p['brand'] . ' ' . $p['manufacturer'])); ?>"
             data-brand="<?php echo htmlspecialchars($p['brand']); ?>"
             data-manufacturer="<?php echo htmlspecialchars($p['manufacturer']); ?>"
             data-scale="<?php echo htmlspecialchars($p['scale']); ?>">
            <div class="mc-img">
              <img src="<?php echo htmlspecialchars(media_url($p['image'] ?? '')); ?>"
                   alt="<?php echo htmlspecialchars($p['name']); ?>" loading="lazy">
              <?php if ($p['scale'] !== ''): ?>
                <span class="mc-scale"><?php echo htmlspecialchars($p['scale']); ?></span>
              <?php endif; ?>
            </div>
            <div class="mc-body">
              <?php if ($spec): ?>
                <div class="mc-spec"><?php echo htmlspecialchars(implode(' · ', $spec)); ?></div>
              <?php endif; ?>
              <h3 class="mc-name"><?php echo htmlspecialchars($p['name']); ?></h3>
              <p class="mc-desc"><?php echo htmlspecialchars(mb_substr($p['description'], 0, 90)); ?></p>
              <div class="mc-price">&#8377;<?php echo number_format((float)$p['price']); ?></div>
            </div>
          </a>
        <?php endwhile; ?>
      </div>

      <div class="empty" id="noResults" style="display:none;">
        <h3>Nothing matches</h3>
        <p>No models match that search. Try a different term or clear the filter.</p>
      </div>

    <?php else: ?>
      <div class="empty">
        <h3>The shelf is empty</h3>
        <p>
          No models listed yet.
          <?php if (!empty($_SESSION['is_admin'])): ?>
            <a href="/admin/add_product.php">Add your first product</a> to get started.
          <?php else: ?>
            Check back soon — new stock is added regularly.
          <?php endif; ?>
        </p>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- ── HOW IT WORKS ──────────────────────────────────────────── -->
<section class="ld-steps">
  <div class="container-fluid p-0">
    <div class="cards-1px">
      <div class="step-card">
        <div class="step-num">/ 01</div>
        <h3>Hand-picked stock</h3>
        <p>Every model is sourced, inspected and photographed personally — casting year, box condition and opening parts noted on the listing.</p>
      </div>
      <div class="step-card">
        <div class="step-num">/ 02</div>
        <h3>Secure checkout</h3>
        <p>Pay by UPI, card or wallet through Razorpay. Card details never touch this server.</p>
      </div>
      <div class="step-card">
        <div class="step-num">/ 03</div>
        <h3>Packed &amp; tracked</h3>
        <p>Collector-grade packaging as standard, dispatched with tracking so you can follow it door to door.</p>
      </div>
    </div>
  </div>
</section>

<script>
(function () {
  var grid = document.getElementById('modelGrid');
  if (!grid) return;

  var items  = Array.prototype.slice.call(grid.querySelectorAll('.product-item'));
  var search = document.getElementById('shopSearch');
  var none   = document.getElementById('noResults');
  var count  = document.getElementById('resultCount');

  // One active facet at a time: {facet: 'brand', value: 'Porsche'} or null for All.
  var active = null;

  function apply() {
    var q = (search.value || '').trim().toLowerCase();
    var shown = 0;

    items.forEach(function (el) {
      var matchesSearch = q === '' || (el.dataset.name || '').indexOf(q) !== -1;
      // Exact match on the attribute, so "1:64" never matches "1:6".
      var matchesFacet  = active === null || el.dataset[active.facet] === active.value;
      var ok = matchesSearch && matchesFacet;
      el.style.display = ok ? '' : 'none';
      if (ok) shown++;
    });

    none.style.display = shown === 0 ? '' : 'none';
    count.textContent  = shown + (shown === 1 ? ' model' : ' models');
  }

  document.querySelectorAll('.chip').forEach(function (chip) {
    chip.addEventListener('click', function () {
      document.querySelectorAll('.chip').forEach(function (c) { c.classList.remove('chip-on'); });
      chip.classList.add('chip-on');
      var facet = chip.dataset.facet;
      active = facet ? { facet: facet, value: chip.dataset.value } : null;
      apply();
    });
  });

  search.addEventListener('input', apply);
  apply();   // honours ?search= on load
})();
</script>

<?php include 'footer.php'; $conn->close(); ?>
