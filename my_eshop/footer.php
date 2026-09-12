  </main>
  <footer class="site-footer">
    <div class="container py-5">
      <div class="row g-5">
        <!-- Brand + tagline -->
        <div class="col-lg-4">
          <div class="f-brand"><span class="dot"><span>M</span></span> My E&#8209;Shop</div>
          <p class="mt-3 mb-0" style="max-width:32ch;color:var(--text-faint);font-size:14px;line-height:1.6;">
            The marketplace for diecast collectors, enthusiasts and independent resellers.
          </p>
        </div>

        <!-- Shop links -->
        <div class="col-6 col-lg-2">
          <div class="f-label mb-3">Shop</div>
          <div class="d-flex flex-column gap-2">
            <a href="/index.php#stores">Browse stores</a>
            <a href="/index.php">All models</a>
            <a href="/cart.php">Your cart</a>
          </div>
        </div>

        <!-- Account links — reflect who is actually signed in.
             Read from $_SESSION rather than header.php's $logged_in/$is_admin so
             this stays correct even on a page that includes the footer alone. -->
        <?php
          $f_logged_in = isset($_SESSION['user_id']);
          $f_role      = $_SESSION['role'] ?? 'customer';
        ?>
        <div class="col-6 col-lg-3">
          <div class="f-label mb-3">Account</div>
          <div class="d-flex flex-column gap-2">
            <?php if ($f_logged_in): ?>
              <?php if ($f_role === 'seller'): ?>
                <a href="/seller/dashboard.php">Seller dashboard</a>
              <?php elseif ($f_role === 'super_admin'): ?>
                <a href="/admin/manage_products.php">Admin panel</a>
              <?php endif; ?>
              <a href="/profile.php">Your profile</a>
              <a href="/orders.php">Your orders</a>
              <a href="/logout.php">Logout</a>
            <?php else: ?>
              <a href="/login.php">Login</a>
              <a href="/register.php">Register</a>
              <a href="/register.php">Open a store</a>
            <?php endif; ?>
          </div>
        </div>

        <!-- Scales -->
        <div class="col-6 col-lg-3">
          <div class="f-label mb-3">Scales</div>
          <div class="d-flex flex-column gap-2">
            <a href="/index.php#stores">1:18 &amp; 1:24</a>
            <a href="/index.php#stores">1:43 &amp; 1:64</a>
            <a href="/index.php#stores">Vintage &amp; tin</a>
          </div>
        </div>
      </div>

      <div class="f-bottom mt-5 pt-4 d-flex flex-column flex-md-row justify-content-between gap-2">
        <span>&copy; <?php echo date('Y'); ?> My E-Shop. All rights reserved.</span>
        <span>Built for collectors</span>
      </div>
    </div>
  </footer>
</div><!-- /app-shell -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/js/script.js"></script>
</body>
</html>
