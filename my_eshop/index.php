<?php
require_once 'config/db.php';
$page_title = 'My E-Shop — The Diecast Marketplace';
include 'header.php';

// Featured active stores (up to 5 — the 6th slot is the "Your garage here" CTA card)
$stores = $conn->query(
    "SELECT s.id, s.name, s.slug, s.logo, s.description, s.primary_color, s.created_at,
            (SELECT COUNT(*) FROM products p WHERE p.store_id = s.id) AS product_count
     FROM stores s WHERE s.status = 'active' ORDER BY s.created_at DESC LIMIT 5"
);

// Hero counters — real figures, not decoration.
$stat_models = (int)($conn->query("SELECT COUNT(*) c FROM products")->fetch_assoc()['c'] ?? 0);
$stat_stores = (int)($conn->query("SELECT COUNT(*) c FROM stores WHERE status='active'")->fetch_assoc()['c'] ?? 0);

// Latest models across the marketplace.
$latest = $conn->query(
    "SELECT p.id, p.name, p.price, p.image, s.name AS store_name, s.slug AS store_slug
     FROM products p LEFT JOIN stores s ON s.id = p.store_id
     ORDER BY p.created_at DESC LIMIT 4"
);
?>

<style>
/* ── Landing page ─────────────────────────────────────────── */

/* Hero */
.ld-hero{position:relative;overflow:hidden;border-bottom:1px solid var(--line);}
.ld-hero .stripes{position:absolute;inset:0;pointer-events:none;
  background:repeating-linear-gradient(102deg,rgba(255,61,0,.14) 0 3px,transparent 3px 58px);}
.ld-hero .glow{position:absolute;top:-140px;right:-120px;width:620px;height:620px;pointer-events:none;
  background:radial-gradient(circle,rgba(255,61,0,.30),transparent 66%);}
/* Width in px, not ch: ch resolves against THIS element's 15px font-size, so a
   ch cap here silently squeezes the 88px display headline inside it. */
.hero-grid{position:relative;padding:clamp(48px,7vw,104px) 0 clamp(40px,5vw,72px);
  max-width:1040px;margin:0 auto;text-align:center;}
.ld-badge{display:inline-flex;align-items:center;gap:9px;border:1px solid rgba(198,255,0,.45);
  padding:7px 14px;font-family:var(--mono);font-size:11px;font-weight:700;letter-spacing:.18em;
  text-transform:uppercase;color:var(--lime);margin-bottom:26px;}
.ld-badge .blip{width:7px;height:7px;background:var(--lime);border-radius:50%;animation:blip 1.6s infinite;}
/* Three lines, broken exactly where the <br>s are. No max-width and no
   text-wrap:balance — both would let the browser re-wrap and fragment it. */
.ld-h1{font-family:var(--display);font-size:clamp(34px,5.6vw,82px);line-height:.92;letter-spacing:-.035em;
  text-transform:uppercase;margin:0 0 22px;}
/* inline-block keeps "Full throttle" from being split across a line break */
.ld-h1 em{color:var(--accent);font-style:italic;display:inline-block;}
.ld-lead{max-width:46ch;font-size:clamp(15px,1.35vw,18px);line-height:1.6;color:var(--text-muted);
  margin:0 auto 34px;}
.ld-btns{display:flex;flex-wrap:wrap;gap:14px;margin-bottom:40px;justify-content:center;}

/* Hero stats — symmetric padding so the three columns read as centred */
.stat-row{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));border-top:1px solid rgba(255,255,255,.12);}
.stat-row > div{padding:20px 18px 0;}
.stat-row > div + div{border-left:1px solid rgba(255,255,255,.12);}
.stat-n{font-family:var(--display);font-size:clamp(24px,2.6vw,34px);letter-spacing:-.02em;}
.stat-l{font-family:var(--mono);font-size:10.5px;letter-spacing:.16em;text-transform:uppercase;
  color:var(--text-faint);margin-top:6px;}

/* Scale chips */
.ld-scales{border-bottom:1px solid var(--line);background:var(--bg-2);}
.scale-row{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:22px 0;}
.scale-row .lbl{font-family:var(--mono);font-size:10.5px;font-weight:700;letter-spacing:.18em;
  text-transform:uppercase;color:var(--text-faint);margin-right:6px;}
.chip{font-family:var(--mono);font-size:11.5px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;
  padding:9px 14px;cursor:pointer;border:1px solid var(--line-2);background:transparent;color:var(--text-muted);
  transition:border-color .15s ease,color .15s ease,background .15s ease;}
.chip:hover{border-color:rgba(198,255,0,.6);color:var(--text);}
.chip-on{border-color:var(--lime);background:var(--lime);color:var(--on-accent);}

/* How it works — 1px gaps read as hairline rules between cards */
.ld-steps{border-bottom:1px solid var(--line);}
.cards-1px{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1px;
  background:var(--line);margin:clamp(48px,6vw,84px) 0;}
.step-card{background:var(--bg);padding:34px 30px;}
.step-num{font-family:var(--mono);font-size:12px;font-weight:700;letter-spacing:.2em;
  color:var(--accent);margin-bottom:24px;}
.step-card h3{font-size:20px;margin:0 0 12px;}
.step-card p{font-size:14.5px;line-height:1.65;color:var(--text-muted);margin:0;}

/* Section heads */
.sec-head{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;flex-wrap:wrap;margin-bottom:36px;}
.sec-eyebrow{font-family:var(--mono);font-size:10.5px;font-weight:700;letter-spacing:.2em;
  text-transform:uppercase;color:var(--lime);margin-bottom:12px;}
.view-all{font-family:var(--mono);font-size:12px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;
  color:var(--text);border-bottom:1px solid var(--accent);padding-bottom:4px;}
.view-all:hover{color:var(--accent);}

/* Featured stores */
.ld-stores{border-bottom:1px solid var(--line);padding:clamp(52px,6vw,88px) 0;}
.sc-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:18px;}
.sc{display:flex;flex-direction:column;background:var(--surface);border:1px solid var(--line);
  color:var(--text);transition:border-color .15s ease;}
.sc:hover{border-color:rgba(255,61,0,.6);color:var(--text);}
.sc-stripe{height:4px;}
.sc-cover{aspect-ratio:5/4;background:var(--bg-2);display:grid;place-items:center;overflow:hidden;}
.sc-cover img{width:100%;height:100%;object-fit:cover;display:block;}
.sc-initial{font-family:var(--display);font-size:44px;color:var(--on-accent);width:100%;height:100%;
  display:grid;place-items:center;}
.sc-body{padding:20px;display:flex;flex-direction:column;flex:1;}
.sc-name{font-family:var(--display);font-size:17px;text-transform:uppercase;margin:0 0 8px;}
.sc-desc{font-size:13.5px;line-height:1.55;color:var(--text-muted);margin:0 0 16px;flex:1;}
.sc-foot{display:flex;justify-content:space-between;font-family:var(--mono);font-size:11px;
  letter-spacing:.1em;text-transform:uppercase;color:var(--text-faint);}
.sc-foot .go{color:var(--lime);}
.sc-cta{border:1px dashed var(--line-strong);background:
  repeating-linear-gradient(-45deg,rgba(255,255,255,.03) 0 10px,transparent 10px 20px);
  padding:22px;justify-content:space-between;min-height:240px;}
.sc-cta:hover{border-color:var(--lime);}
.sc-cta-title{font-family:var(--display);font-size:19px;text-transform:uppercase;margin:0 0 10px;}
.sc-cta-desc{font-size:13.5px;line-height:1.55;color:var(--text-muted);margin:0 0 16px;}
.sc-cta-link{font-family:var(--mono);font-size:12px;font-weight:700;letter-spacing:.12em;
  text-transform:uppercase;color:var(--lime);}

/* Latest models */
.ld-models{border-bottom:1px solid var(--line);background:var(--bg-2);padding:clamp(52px,6vw,88px) 0;}
.model-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;}
.mc{background:var(--bg);border:1px solid var(--line);transition:border-color .15s ease;}
.mc:hover{border-color:rgba(255,61,0,.6);}
.mc-img{position:relative;aspect-ratio:1/1;background:var(--surface);}
.mc-img img{width:100%;height:100%;object-fit:cover;display:block;}
.mc-body{padding:16px;}
.mc-store{font-family:var(--mono);font-size:10.5px;letter-spacing:.14em;text-transform:uppercase;
  color:var(--text-faint);margin-bottom:7px;}
.mc-name{font-size:15px;font-weight:700;margin:0 0 12px;line-height:1.35;font-family:var(--body);
  text-transform:none;letter-spacing:0;}
.mc-price{font-family:var(--display);font-size:17px;}

/* For sellers — the one light section */
.ld-sell{padding:clamp(56px,7vw,96px) 0;}
.sell-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:clamp(32px,4vw,64px);}
.ld-sell h2{font-size:clamp(32px,4.4vw,58px);line-height:.92;letter-spacing:-.035em;margin:0 0 20px;}
.feature-row{display:grid;grid-template-columns:44px minmax(0,1fr);gap:18px;padding:22px 0;
  border-top:1px solid var(--paper-line);}
.feature-row:last-child{border-bottom:1px solid var(--paper-line);}
.roman{font-family:var(--mono);font-size:12px;font-weight:700;letter-spacing:.14em;color:var(--accent-on-paper);}
.feature-title{font-family:var(--display);font-size:15px;text-transform:uppercase;margin:0 0 6px;}
.feature-desc{font-size:14.5px;line-height:1.6;color:var(--on-paper-muted);margin:0;}
.feature-desc code{font-family:var(--mono);font-size:13.5px;color:var(--accent-on-paper);}

/* CTA band */
.ld-cta{position:relative;overflow:hidden;background:var(--accent);color:var(--on-accent);
  padding:clamp(56px,7vw,92px) 0;text-align:center;}
.ld-cta .stripes{position:absolute;inset:0;pointer-events:none;
  background:repeating-linear-gradient(90deg,rgba(10,11,13,.16) 0 2px,transparent 2px 46px);}
.ld-cta h2{position:relative;font-size:clamp(32px,5.2vw,68px);line-height:.92;letter-spacing:-.035em;
  margin:0 0 18px;color:var(--on-accent);text-wrap:balance;}
.ld-cta p{position:relative;font-size:16.5px;line-height:1.6;color:rgba(10,11,13,.78);max-width:46ch;margin:0 auto 32px;}
.ld-cta .ld-btns{position:relative;justify-content:center;margin-bottom:0;}
.btn-ink{background:var(--on-accent);color:var(--paper);border:1px solid var(--on-accent);}
.btn-ink:hover{background:var(--paper);color:var(--on-accent);border-color:var(--paper);}
.btn-ink-outline{background:transparent;color:var(--on-accent);border:1px solid rgba(10,11,13,.5);}
.btn-ink-outline:hover{background:rgba(10,11,13,.1);color:var(--on-accent);}

@media (max-width:720px){
  .stat-row{grid-template-columns:1fr;border-top:0;}
  .stat-row > div{border-left:0!important;padding:14px 0!important;border-top:1px solid rgba(255,255,255,.12);}
}
</style>

<!-- ── HERO ──────────────────────────────────────────────────── -->
<section class="ld-hero" id="top">
  <div class="stripes"></div><div class="glow"></div>
  <div class="container">
    <div class="hero-grid">
      <div>
        <div class="ld-badge"><span class="blip"></span>The diecast marketplace</div>
        <h1 class="ld-h1">
          Small scale.<br>
          <em>Full throttle</em><br>
          collecting.
        </h1>
        <p class="ld-lead">
          Rare and premium diecast from independent sellers &mdash; every store is its own
          garage, every model handpicked before it ships.
        </p>
        <div class="ld-btns">
          <a href="#stores" class="btn"><span>Browse stores</span></a>
          <a href="register.php" class="btn btn-ghost"><span>Start selling free &rarr;</span></a>
        </div>
        <div class="stat-row">
          <div>
            <div class="stat-n"><?php echo number_format($stat_models); ?></div>
            <div class="stat-l">Models listed</div>
          </div>
          <div>
            <div class="stat-n"><?php echo number_format($stat_stores); ?></div>
            <div class="stat-l">Seller garages</div>
          </div>
          <div>
            <div class="stat-n" style="color:var(--lime);">0%</div>
            <div class="stat-l">Listing fees</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── SCALE CHIPS ───────────────────────────────────────────── -->
<section class="ld-scales">
  <div class="container">
    <div class="scale-row">
      <span class="lbl">Shop by class</span>
      <button class="chip chip-on" onclick="selectScale(this)">All</button>
      <?php foreach (['1:12','1:18','1:24','1:43','1:64','Vintage','JDM','F1','Rally'] as $sc): ?>
        <button class="chip" onclick="selectScale(this)"><?php echo $sc; ?></button>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── HOW IT WORKS ──────────────────────────────────────────── -->
<section class="ld-steps">
  <div class="container">
    <div class="cards-1px">
      <div class="step-card">
        <div class="step-num">/ 01</div>
        <h3>Pick a garage</h3>
        <p>Every seller runs a branded storefront with their own livery, curation and specialisation — JDM, rally, F1 or vintage tin.</p>
      </div>
      <div class="step-card">
        <div class="step-num">/ 02</div>
        <h3>Find the model</h3>
        <p>Search by marque, maker or scale. Photos, description and pricing on every listing.</p>
      </div>
      <div class="step-card">
        <div class="step-num">/ 03</div>
        <h3>Checkout &amp; track</h3>
        <p>Secure Razorpay checkout, packed by the seller, tracked door to door.</p>
      </div>
    </div>
  </div>
</section>

<!-- ── FEATURED STORES ───────────────────────────────────────── -->
<section class="ld-stores" id="stores">
  <div class="container">
    <div class="sec-head">
      <div>
        <div class="sec-eyebrow">The paddock</div>
        <h2>Featured garages</h2>
      </div>
      <a href="#stores" class="view-all">View all stores &rarr;</a>
    </div>

    <div class="sc-grid">
      <?php if ($stores && $stores->num_rows > 0):
        while ($s = $stores->fetch_assoc()):
          $color   = !empty($s['primary_color']) ? $s['primary_color'] : '#FF3D00';
          $initial = strtoupper(mb_substr($s['name'], 0, 1));
          $pc      = (int)$s['product_count'];
      ?>
        <a href="/shop/<?php echo htmlspecialchars($s['slug']); ?>" class="sc">
          <div class="sc-stripe" style="background:<?php echo htmlspecialchars($color); ?>;"></div>
          <div class="sc-cover">
            <?php if (!empty($s['logo'])): ?>
              <img src="<?php echo htmlspecialchars(media_url($s['logo'], 'stores')); ?>"
                   alt="<?php echo htmlspecialchars($s['name']); ?>">
            <?php else: ?>
              <div class="sc-initial" style="background:<?php echo htmlspecialchars($color); ?>;"><?php echo $initial; ?></div>
            <?php endif; ?>
          </div>
          <div class="sc-body">
            <h3 class="sc-name"><?php echo htmlspecialchars($s['name']); ?></h3>
            <p class="sc-desc">
              <?php echo $s['description']
                ? htmlspecialchars(mb_substr($s['description'], 0, 72)) . '&hellip;'
                : 'Visit this garage'; ?>
            </p>
            <div class="sc-foot">
              <span><?php echo $pc; ?> model<?php echo $pc !== 1 ? 's' : ''; ?></span>
              <span class="go">Visit &rarr;</span>
            </div>
          </div>
        </a>
      <?php endwhile; endif; ?>

      <a href="register.php" class="sc sc-cta">
        <div class="f-label" style="margin:0;">Slot open</div>
        <div>
          <h3 class="sc-cta-title">Your garage here</h3>
          <p class="sc-cta-desc">Open a free storefront and join the grid in minutes.</p>
          <span class="sc-cta-link">Start selling &rarr;</span>
        </div>
      </a>
    </div>
  </div>
</section>

<!-- ── LATEST MODELS ─────────────────────────────────────────── -->
<?php if ($latest && $latest->num_rows > 0): ?>
<section class="ld-models" id="models">
  <div class="container">
    <div class="sec-head">
      <h2>On the grid <span style="color:var(--text-ghost);">/ new in</span></h2>
      <a href="#stores" class="view-all">All models &rarr;</a>
    </div>
    <div class="model-grid">
      <?php while ($m = $latest->fetch_assoc()): ?>
        <a href="/product.php?id=<?php echo (int)$m['id']; ?>" class="mc" style="display:block;color:inherit;">
          <div class="mc-img">
            <img src="<?php echo htmlspecialchars(media_url($m['image'] ?? '')); ?>"
                 alt="<?php echo htmlspecialchars($m['name']); ?>">
          </div>
          <div class="mc-body">
            <div class="mc-store"><?php echo htmlspecialchars($m['store_name'] ?? 'Marketplace'); ?></div>
            <h3 class="mc-name"><?php echo htmlspecialchars($m['name']); ?></h3>
            <div class="mc-price">₹<?php echo number_format((float)$m['price']); ?></div>
          </div>
        </a>
      <?php endwhile; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ── WHY SELL HERE ─────────────────────────────────────────── -->
<section class="ld-sell on-paper" id="sell">
  <div class="container">
    <div class="sell-grid">
      <div>
        <div class="eyebrow" style="margin-bottom:14px;">For sellers</div>
        <h2>Your garage,<br>your livery.</h2>
        <p style="font-size:16px;line-height:1.6;max-width:38ch;margin:0 0 30px;">
          Turn a shelf of models into a storefront. Free to open, no listing fees,
          payouts straight to your account.
        </p>
        <a href="register.php" class="btn btn-ink"><span>Start for free &rarr;</span></a>
      </div>
      <div>
        <div class="feature-row">
          <div class="roman">01</div>
          <div>
            <h3 class="feature-title">Full branding control</h3>
            <p class="feature-desc">Upload your logo, pick a livery colour, write your garage story.</p>
          </div>
        </div>
        <div class="feature-row">
          <div class="roman">02</div>
          <div>
            <h3 class="feature-title">Your own URL</h3>
            <p class="feature-desc">A unique link like <code>/shop/yourstore</code> you can share anywhere.</p>
          </div>
        </div>
        <div class="feature-row">
          <div class="roman">03</div>
          <div>
            <h3 class="feature-title">Razorpay payments</h3>
            <p class="feature-desc">UPI, cards and wallets. Payments settle directly to your account.</p>
          </div>
        </div>
        <div class="feature-row">
          <div class="roman">04</div>
          <div>
            <h3 class="feature-title">Order management</h3>
            <p class="feature-desc">Track and update orders from your dashboard — buyers notified automatically.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── CTA BAND ──────────────────────────────────────────────── -->
<section class="ld-cta">
  <div class="stripes"></div>
  <div class="container">
    <h2>Ready to sell your collection?</h2>
    <p>Set up your garage in under five minutes. Free to start — register as a seller and launch.</p>
    <div class="ld-btns">
      <a href="register.php" class="btn btn-ink"><span>Open your store free</span></a>
      <a href="#stores" class="btn btn-ink-outline"><span>Browse stores</span></a>
    </div>
  </div>
</section>

<script>
function selectScale(btn){
  document.querySelectorAll('.chip').forEach(function(c){ c.classList.remove('chip-on'); });
  btn.classList.add('chip-on');
}
</script>

<?php include 'footer.php'; $conn->close(); ?>
