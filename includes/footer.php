<?php
// ============================================================
// LAKBAY LAGUNA — HTML Footer Partial
// includes/footer.php
// ============================================================
?>

<!-- ── Footer ─────────────────────────────────────────────── -->
<footer class="site-footer mt-auto pt-5 pb-4">
  <div class="container">
    <div class="row g-4">

      <!-- Brand col -->
      <div class="col-lg-4">
        <div class="d-flex align-items-center gap-2 mb-3">
          <span class="brand-icon-sm"><i class="bi bi-map-fill"></i></span>
          <span class="fw-bold fs-5"><span style="font-style:italic;color:var(--sand-dark)">i</span>Explore <span class="brand-accent">Laguna</span></span>
        </div>
        <p class="text-muted small mb-0">
          A smart travel planning system for exploring the beautiful
          province of Laguna, Philippines. Plan routes, discover
          tourist spots, and estimate your budget — all in one place.
        </p>
      </div>

      <!-- Quick links -->
      <div class="col-6 col-lg-2 offset-lg-1">
        <h6 class="footer-heading mb-3">Explore</h6>
        <ul class="list-unstyled footer-links">
          <li><a href="<?= APP_URL ?>/pages/planner.php">Trip Planner</a></li>
          <li><a href="<?= APP_URL ?>/pages/spots.php">Tourist Spots</a></li>
          <li><a href="<?= APP_URL ?>/pages/hotels.php">Hotels</a></li>
          <li><a href="<?= APP_URL ?>/pages/budget.php">Budget Estimator</a></li>
        </ul>
      </div>

      <!-- Cities -->
      <div class="col-6 col-lg-2">
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
      <div class="col-6 col-lg-2">
        <h6 class="footer-heading mb-3">Account</h6>
        <ul class="list-unstyled footer-links">
          <?php if (is_logged_in()): ?>
            <li><a href="<?= APP_URL ?>/pages/itineraries.php">My Itineraries</a></li>
            <li><a href="<?= APP_URL ?>/pages/logout.php">Logout</a></li>
          <?php else: ?>
            <li><a href="<?= APP_URL ?>/pages/login.php">Login</a></li>
            <li><a href="<?= APP_URL ?>/pages/register.php">Register</a></li>
          <?php endif; ?>
        </ul>
      </div>

    </div>

    <hr class="footer-divider mt-4 mb-3">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
      <p class="text-muted small mb-0">
      &copy; <?= date('Y') ?> <em>i</em>Explore Laguna. A capstone project.
      </p>
      <p class="text-muted small mb-0">
        Map data &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" class="footer-link">OpenStreetMap</a> contributors
      </p>
    </div>
  </div>
</footer>
<!-- ── End Footer ─────────────────────────────────────────── -->

<!-- Bootstrap JS bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<!-- App JS -->
<script src="<?= APP_URL ?>/assets/js/app.js"></script>

</body>
</html>
