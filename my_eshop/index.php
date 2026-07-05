<?php
require_once 'config/db.php';
$page_title = 'DiecastHub — The Diecast Car Marketplace';
include 'header.php';

// Featured active stores (up to 5 — 6th slot is the "Your store here" CTA card)
$stores = $conn->query(
    "SELECT s.id, s.name, s.slug, s.logo, s.description, s.primary_color, s.created_at,
            (SELECT COUNT(*) FROM products p WHERE p.store_id = s.id) AS product_count
     FROM stores s WHERE s.status = 'active' ORDER BY s.created_at DESC LIMIT 5"
);
?>

<style>
/* ── Landing page ─────────────────────────────────────────── */

/* Hero */
.ld-hero{background:var(--green-deep);color:var(--ivory);padding:6rem 0 4rem;}
.ld-badge{display:inline-block;border:1px solid rgba(201,184,145,.35);border-radius:999px;
  padding:.35rem 1.1rem;font-size:.72rem;font-weight:700;letter-spacing:.14em;
  color:var(--brass);text-transform:uppercase;margin-bottom:2rem;}
.ld-h1{font-family:'Fraunces',serif;font-size:clamp(3rem,7vw,5.5rem);font-weight:600;
  line-height:1.05;color:var(--ivory);margin:0;}
.ld-h1 em{color:var(--brass);font-style:italic;}
.ld-lead{color:#C9C3B2;font-size:1.1rem;max-width:520px;margin:.75rem auto 0;}
.ld-btns{display:flex;flex-wrap:wrap;gap:.9rem;justify-content:center;margin-top:2.25rem;}
.btn-brass{background:var(--brass);border:1.5px solid var(--brass);color:#1C1A16;
  border-radius:4px;padding:.6rem 1.5rem;font-size:.88rem;font-weight:700;
  text-decoration:none;display:inline-block;transition:opacity .2s;}
.btn-brass:hover{opacity:.88;color:#1C1A16;}
.btn-dark-ghost{background:transparent;border:1.5px solid rgba(201,184,145,.4);
  color:var(--ivory);border-radius:4px;padding:.6rem 1.5rem;font-size:.88rem;font-weight:600;
  text-decoration:none;display:inline-block;transition:border-color .2s;}
.btn-dark-ghost:hover{border-color:var(--brass);color:var(--ivory);}

/* Scale ticker */
.scale-ticker{display:flex;flex-wrap:wrap;justify-content:center;align-items:center;
  gap:.35rem .6rem;margin-top:3.5rem;padding-top:3rem;
  border-top:1px solid rgba(201,184,145,.15);}
.scale-ticker span{font-size:.75rem;font-weight:600;letter-spacing:.1em;color:#8A8378;
  text-transform:uppercase;}
.scale-ticker .sep{color:rgba(201,184,145,.3);font-size:.8rem;}

/* How it works */
.ld-steps{background:var(--green-deep);padding:0 0 5rem;}
.step-card{border:1px solid rgba(201,184,145,.12);border-radius:6px;
  padding:2rem 1.75rem;height:100%;}
.step-num{font-family:'Fraunces',serif;font-size:.95rem;color:var(--brass);
  font-weight:600;letter-spacing:.06em;margin-bottom:1rem;}
.step-card h5{color:var(--ivory);font-size:1.05rem;margin:0 0 .6rem;}
.step-card p{color:#8A8378;font-size:.9rem;margin:0;}

/* Featured stores */
.ld-stores{background:var(--ivory);padding:5rem 0;}
.stores-head{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:2rem;}
.view-all{font-size:.85rem;color:var(--brass-deep);font-weight:600;text-decoration:none;}
.view-all:hover{color:var(--ink);}

.sc{background:#fff;border:1px solid var(--line);border-radius:6px;
  padding:1.5rem;display:flex;flex-direction:column;height:100%;
  text-decoration:none;color:var(--ink);transition:box-shadow .2s,transform .2s;}
.sc:hover{box-shadow:var(--shadow);transform:translateY(-3px);color:var(--ink);}
.sc-avatar{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;
  justify-content:center;font-family:'Fraunces',serif;font-size:1.15rem;
  font-weight:600;color:#fff;flex-shrink:0;}
.sc-name{font-size:1rem;font-weight:700;color:var(--ink);line-height:1.2;}
.sc-badge{display:inline-block;background:#EAF3EC;color:#2C6B34;font-size:.65rem;
  font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:.15rem .5rem;
  border-radius:3px;margin-left:.4rem;vertical-align:middle;}
.sc-desc{color:var(--muted);font-size:.88rem;margin:.75rem 0 auto;flex-grow:1;}
.sc-foot{font-size:.82rem;color:var(--brass-deep);font-weight:600;margin-top:1rem;}

.sc-cta{background:var(--ivory-2);border:1px dashed var(--line);}
.sc-cta:hover{box-shadow:none;transform:none;}
.sc-cta-title{font-family:'Fraunces',serif;font-size:1.15rem;color:var(--ink);margin-bottom:.5rem;}
.sc-cta-desc{color:var(--muted);font-size:.88rem;margin-bottom:auto;}
.sc-cta-link{color:var(--brass-deep);font-size:.88rem;font-weight:700;
  text-decoration:none;margin-top:1.25rem;display:inline-block;}

/* Why sell */
.ld-sell{background:var(--ivory-2);padding:5rem 0;}
.feature-row{display:flex;align-items:baseline;gap:1.5rem;padding:1.4rem 0;
  border-bottom:1px solid var(--line);}
.feature-row:first-of-type{border-top:1px solid var(--line);}
.roman{font-family:'Fraunces',serif;font-size:.95rem;color:var(--brass);
  font-weight:600;min-width:2rem;flex-shrink:0;}
.feature-title{font-size:.95rem;font-weight:700;color:var(--ink);min-width:180px;flex-shrink:0;}
.feature-desc{font-size:.9rem;color:var(--muted);}
.feature-desc a{color:var(--brass-deep);text-decoration:none;}

/* CTA band */
.ld-cta{background:var(--green-deep);color:var(--ivory);padding:6rem 0;text-align:center;}
.ld-cta h2{font-family:'Fraunces',serif;font-size:clamp(2rem,4vw,3rem);
  font-weight:600;color:var(--ivory);margin-bottom:.75rem;}
.ld-cta p{color:#C9C3B2;max-width:480px;margin:0 auto 2.5rem;font-size:1rem;}
</style>

<!-- ── HERO ──────────────────────────────────────────────────── -->
<section class="ld-hero">
  <div class="container text-center">
    <div class="ld-badge">The Diecast Car Marketplace</div>
    <h1 class="ld-h1">
      Small cars.<br>
      <em>Serious</em> collections.
    </h1>
    <p class="ld-lead">
      Rare and premium diecast from independent sellers &mdash; every store
      unique, every model handpicked.
    </p>
    <div class="ld-btns">
      <a href="#stores" class="btn-brass">Browse Stores</a>
      <a href="register.php" class="btn-dark-ghost">Start Selling Free &rarr;</a>
    </div>

    <!-- Scale ticker -->
    <div class="scale-ticker">
      <span>1:18</span><span class="sep">&middot;</span>
      <span>1:24</span><span class="sep">&middot;</span>
      <span>1:43</span><span class="sep">&middot;</span>
      <span>1:64</span><span class="sep">&middot;</span>
      <span>Vintage</span><span class="sep">&middot;</span>
      <span>JDM</span><span class="sep">&middot;</span>
      <span>F1</span><span class="sep">&middot;</span>
      <span>Rally</span>
    </div>
  </div>
</section>

<!-- ── HOW IT WORKS ──────────────────────────────────────────── -->
<section class="ld-steps">
  <div class="container">
    <div class="row g-3">
      <div class="col-md-4">
        <div class="step-card">
          <div class="step-num">01</div>
          <h5>Browse independent stores</h5>
          <p>Each seller runs their own branded storefront with a curated selection of diecast models.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="step-card">
          <div class="step-num">02</div>
          <h5>Find your model</h5>
          <p>Search by name, brand, or scale. Photos, description, and pricing on every listing.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="step-card">
          <div class="step-num">03</div>
          <h5>Buy with confidence</h5>
          <p>Secure checkout via Razorpay. Orders tracked from the seller directly to your door.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── FEATURED STORES ───────────────────────────────────────── -->
<section class="ld-stores" id="stores">
  <div class="container">
    <div class="stores-head">
      <h2 style="font-family:'Fraunces',serif;font-size:2rem;font-weight:600;margin:0;">
        Featured stores
      </h2>
      <a href="#stores" class="view-all">View all &rarr;</a>
    </div>

    <div class="row g-3">
      <?php if ($stores && $stores->num_rows > 0):
        while ($s = $stores->fetch_assoc()):
          $color    = !empty($s['primary_color']) ? $s['primary_color'] : '#36473C';
          $initial  = strtoupper(mb_substr($s['name'], 0, 1));
          $is_new   = (strtotime($s['created_at']) > strtotime('-30 days'));
          $pc       = (int)$s['product_count'];
      ?>
      <div class="col-sm-6 col-lg-4">
        <a href="/shop/<?php echo htmlspecialchars($s['slug']); ?>" class="sc">
          <div class="d-flex align-items-center gap-3 mb-1">
            <?php if (!empty($s['logo'])): ?>
              <img src="/uploads/stores/<?php echo htmlspecialchars($s['logo']); ?>"
                   class="sc-avatar" style="object-fit:cover;"
                   alt="<?php echo htmlspecialchars($s['name']); ?>">
            <?php else: ?>
              <div class="sc-avatar" style="background:<?php echo $color; ?>;">
                <?php echo $initial; ?>
              </div>
            <?php endif; ?>
            <div class="sc-name">
              <?php echo htmlspecialchars($s['name']); ?>
              <?php if ($is_new): ?>
                <span class="sc-badge">New Store</span>
              <?php endif; ?>
            </div>
          </div>
          <p class="sc-desc">
            <?php echo $s['description']
              ? htmlspecialchars(mb_substr($s['description'], 0, 80)) . '&hellip;'
              : 'Visit this store'; ?>
          </p>
          <div class="sc-foot">
            <?php if ($pc > 0): ?>
              <?php echo $pc; ?> model<?php echo $pc !== 1 ? 's' : ''; ?> listed &middot;
            <?php endif; ?>
            Visit store &rarr;
          </div>
        </a>
      </div>
      <?php endwhile; endif; ?>

      <!-- "Your store here" CTA card -->
      <div class="col-sm-6 col-lg-4">
        <div class="sc sc-cta" style="justify-content:center;">
          <p class="sc-cta-title">Your store here</p>
          <p class="sc-cta-desc">
            Open a free storefront and join the marketplace in minutes.
          </p>
          <a href="register.php" class="sc-cta-link">Start selling &rarr;</a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── WHY SELL HERE ─────────────────────────────────────────── -->
<section class="ld-sell">
  <div class="container">
    <div class="row g-5 align-items-center">
      <div class="col-lg-4">
        <div class="eyebrow mb-2">For sellers</div>
        <h2 style="font-family:'Fraunces',serif;font-size:2.2rem;font-weight:600;line-height:1.15;">
          Your own store,<br>your own brand.
        </h2>
        <a href="register.php" class="btn-brass d-inline-block mt-4">Start for free &rarr;</a>
      </div>
      <div class="col-lg-8">
        <div class="feature-row">
          <span class="roman">I.</span>
          <span class="feature-title">Full branding control</span>
          <span class="feature-desc">Upload your logo, pick a brand colour, write your store description.</span>
        </div>
        <div class="feature-row">
          <span class="roman">II.</span>
          <span class="feature-title">Your own URL</span>
          <span class="feature-desc">
            A unique link like <a href="register.php">/shop/yourstore</a> you can share anywhere.
          </span>
        </div>
        <div class="feature-row">
          <span class="roman">III.</span>
          <span class="feature-title">Razorpay payments</span>
          <span class="feature-desc">Accept UPI, cards, and wallets. Payments go straight to your account.</span>
        </div>
        <div class="feature-row">
          <span class="roman">IV.</span>
          <span class="feature-title">Order management</span>
          <span class="feature-desc">Track and update orders from your dashboard. Customers notified automatically.</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── CTA BAND ──────────────────────────────────────────────── -->
<section class="ld-cta">
  <div class="container">
    <h2>Ready to sell your collection?</h2>
    <p>
      Set up your store in under 5 minutes. Free to start &mdash; just
      register as a seller and launch.
    </p>
    <div class="ld-btns">
      <a href="register.php" class="btn-brass">Open Your Store Free</a>
      <a href="#stores" class="btn-dark-ghost">Browse Stores</a>
    </div>
  </div>
</section>

<?php include 'footer.php'; $conn->close(); ?>
