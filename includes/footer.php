<?php
// ============================================================
// iEXPLORE LAGUNA — HTML Footer Partial (Polished v2)
// ============================================================
?>

<!-- ── Footer ─────────────────────────────────────────────── -->
<footer class="site-footer mt-auto pt-5 pb-4">
  <div class="container">
    <div class="row g-4 g-lg-5">

      <!-- Brand col -->
      <div class="col-lg-4">
        <div class="d-flex align-items-center gap-2 mb-3">
          <span class="brand-icon-sm"><i class="bi bi-map-fill"></i></span>
          <span class="fw-bold fs-5"><span style="font-style:italic;color:var(--sand-dark)">i</span>Explore <span style="color:var(--sand-dark)">Laguna</span></span>
        </div>
        <p class="small mb-3" style="color:rgba(255,255,255,.55);line-height:1.75">
          A smart travel planning system for exploring the beautiful
          province of Laguna, Philippines. Plan routes, discover
          tourist spots, and estimate your budget — all in one place.
        </p>
        <div class="d-flex gap-2">
          <span class="badge rounded-pill px-3 py-2" style="background:rgba(255,255,255,.1);color:rgba(255,255,255,.7);font-size:.72rem">
            <i class="bi bi-geo-alt me-1"></i>Laguna, PH
          </span>
          <span class="badge rounded-pill px-3 py-2" style="background:rgba(255,255,255,.1);color:rgba(255,255,255,.7);font-size:.72rem">
            <i class="bi bi-mortarboard me-1"></i>Capstone 2026
          </span>
        </div>
      </div>

      <!-- Quick links -->
      <div class="col-6 col-sm-4 col-lg-2 offset-lg-1">
        <h6 class="footer-heading mb-3">Explore</h6>
        <ul class="list-unstyled footer-links">
          <li><a href="<?= APP_URL ?>/pages/planner.php"><i class="bi bi-compass me-2 opacity-50"></i>Trip Planner</a></li>
          <li><a href="<?= APP_URL ?>/pages/spots.php"><i class="bi bi-geo-alt me-2 opacity-50"></i>Tourist Spots</a></li>
          <li><a href="<?= APP_URL ?>/pages/hotels.php"><i class="bi bi-building me-2 opacity-50"></i>Hotels</a></li>
          <li><a href="<?= APP_URL ?>/pages/budget.php"><i class="bi bi-calculator me-2 opacity-50"></i>Budget Estimator</a></li>
        </ul>
      </div>

      <!-- Cities -->
      <div class="col-6 col-sm-4 col-lg-2">
        <h6 class="footer-heading mb-3">Cities</h6>
        <ul class="list-unstyled footer-links">
          <li><a href="<?= APP_URL ?>/pages/spots.php?city=calamba">Calamba</a></li>
          <li><a href="<?= APP_URL ?>/pages/spots.php?city=los-banos">Los Baños</a></li>
          <li><a href="<?= APP_URL ?>/pages/spots.php?city=san-pablo">San Pablo</a></li>
          <li><a href="<?= APP_URL ?>/pages/spots.php?city=pagsanjan">Pagsanjan</a></li>
          <li><a href="<?= APP_URL ?>/pages/spots.php?city=nagcarlan">Nagcarlan</a></li>
        </ul>
      </div>

      <!-- Account -->
      <div class="col-6 col-sm-4 col-lg-2">
        <h6 class="footer-heading mb-3">Account</h6>
        <ul class="list-unstyled footer-links">
          <?php if (is_logged_in()):
            $__u = current_user(); ?>
            <li><a href="<?= APP_URL ?>/pages/itineraries.php"><i class="bi bi-journal-bookmark me-2 opacity-50"></i>My Itineraries</a></li>
            <li><a href="<?= APP_URL ?>/pages/my-orders.php"><i class="bi bi-bag-check me-2 opacity-50"></i>My Orders</a></li>
            <?php if ($__u['role'] === 'shop_owner'): ?>
            <li><a href="<?= APP_URL ?>/pages/shop-dashboard.php"><i class="bi bi-shop me-2 opacity-50"></i>My Shop</a></li>
            <?php endif; ?>
            <?php if ($__u['role'] === 'hotel_owner'): ?>
            <li><a href="<?= APP_URL ?>/pages/hotel-dashboard.php"><i class="bi bi-building me-2 opacity-50"></i>My Hotel</a></li>
            <?php endif; ?>
            <li><a href="<?= APP_URL ?>/pages/logout.php"><i class="bi bi-box-arrow-right me-2 opacity-50"></i>Logout</a></li>
          <?php else: ?>
            <li><a href="<?= APP_URL ?>/pages/login.php"><i class="bi bi-box-arrow-in-right me-2 opacity-50"></i>Login</a></li>
            <li><a href="<?= APP_URL ?>/pages/register.php"><i class="bi bi-person-plus me-2 opacity-50"></i>Register</a></li>
          <?php endif; ?>
        </ul>
      </div>

    </div>

    <hr class="footer-divider">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
      <p class="small mb-0" style="color:rgba(255,255,255,.45)">
        &copy; <?= date('Y') ?> <em>i</em>Explore Laguna &mdash; A capstone project.
      </p>
      <p class="small mb-0" style="color:rgba(255,255,255,.45)">
        Map data &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" class="footer-link">OpenStreetMap</a> contributors
      </p>
    </div>
  </div>
</footer>
<!-- ── End Footer ─────────────────────────────────────────── -->

<!-- Scroll to top -->
<button id="scroll-top" aria-label="Scroll to top">
  <i class="bi bi-chevron-up"></i>
</button>

<!-- Toast wrapper -->
<div id="toast-wrapper"></div>

<!-- Bootstrap JS bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<!-- Leaflet Routing Machine -->
<script src="https://cdn.jsdelivr.net/npm/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css">
<!-- App JS -->
<script src="<?= APP_URL ?>/assets/js/app.js"></script>

</body>
</html>
