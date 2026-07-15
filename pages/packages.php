<?php
// ============================================================
// iEXPLORE LAGUNA — Trip Packages (Tourist Browse)
// pages/packages.php
// ============================================================
$page_title  = 'Trip Packages';
$active_page = 'packages';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/package_card.php';

$packages = db_fetch_all(
    "SELECT p.*, c.name AS city_name, h.name AS hotel_name, h.star_rating,
            (SELECT COUNT(*) FROM package_spots ps WHERE ps.package_id = p.id) AS spot_count
     FROM packages p
     LEFT JOIN cities c ON p.city_id = c.id
     LEFT JOIN hotels h ON p.hotel_id = h.id
     WHERE p.is_active = 1
     ORDER BY p.estimated_price ASC"
);

$single_city = array_filter($packages, fn($p) => $p['scope'] === 'single_city');
$multi_city  = array_filter($packages, fn($p) => $p['scope'] === 'multi_city');
?>

<section class="py-4" style="background:linear-gradient(135deg,var(--green-dark),var(--green-mid));color:#fff">
  <div class="container">
    <div class="d-flex align-items-center gap-3">
      <i class="bi bi-box-seam-fill fs-2" style="color:var(--sand-dark)"></i>
      <div>
        <h1 class="mb-0 fs-3" style="font-family:'Playfair Display',serif">Trip Packages</h1>
        <p class="mb-0 small opacity-75">All-in-one trips — hotel + spots bundled at a fixed price. One click and you're set.</p>
      </div>
    </div>
  </div>
</section>

<div class="container py-4">

  <?php if (empty($packages)): ?>
    <div class="text-center py-5">
      <i class="bi bi-box-seam fs-1 text-muted d-block mb-3"></i>
      <h5>No packages available yet</h5>
      <p class="text-muted">Check back soon, or build your own trip with the <a href="explore.php">Explore &amp; Plan</a> page.</p>
    </div>
  <?php else: ?>

  <!-- ── Single-City Packages ── -->
  <?php if (!empty($single_city)): ?>
  <div class="d-flex align-items-center gap-2 mb-3 pb-2" style="border-bottom:2px solid var(--green-pale)">
    <i class="bi bi-geo-alt-fill" style="color:var(--green-mid)"></i>
    <h5 class="fw-bold mb-0" style="font-family:'Playfair Display',serif;color:var(--green-dark)">Single-City Packages</h5>
  </div>
  <div class="row g-3 mb-5">
    <?php foreach ($single_city as $p): ?>
    <div class="col-sm-6 col-xl-4">
      <?php render_package_card($p); ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- ── Multi-City Packages ── -->
  <?php if (!empty($multi_city)): ?>
  <div class="d-flex align-items-center gap-2 mb-3 pb-2" style="border-bottom:2px solid var(--green-pale)">
    <i class="bi bi-signpost-split-fill" style="color:var(--green-mid)"></i>
    <h5 class="fw-bold mb-0" style="font-family:'Playfair Display',serif;color:var(--green-dark)">Multi-City Packages</h5>
  </div>
  <div class="row g-3">
    <?php foreach ($multi_city as $p): ?>
    <div class="col-sm-6 col-xl-4">
      <?php render_package_card($p); ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
