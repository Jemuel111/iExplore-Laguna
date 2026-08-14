<?php
// ============================================================
// iEXPLORE LAGUNA — Homepage (Polished v2)
// ============================================================
$page_title  = 'Home';
$active_page = 'home';
require_once 'includes/header.php';

$featured_spots = db_fetch_all(
  "SELECT s.*, c.name AS city_name,
          (SELECT url FROM spot_photos WHERE spot_id = s.id AND photo_type = 'main' LIMIT 1) AS main_photo_url
   FROM tourist_spots s
   JOIN cities c ON s.city_id = c.id
   WHERE s.is_active = 1
   ORDER BY s.rating DESC
   LIMIT 6"
);
$budgetRanges = get_budget_level_ranges();

$cities = db_fetch_all("SELECT id, name, slug FROM cities ORDER BY name");

// Real photography for the hero/CTA backgrounds instead of a flat
// gradient block — pulled from actual uploaded spot photos so the
// homepage always reflects real content, not a stock placeholder.
$hero_photo = db_fetch_one(
  "SELECT url FROM spot_photos WHERE photo_type='main' ORDER BY id ASC LIMIT 1"
)['url'] ?? null;
$cta_photo = db_fetch_one(
  "SELECT url FROM spot_photos WHERE photo_type='main' ORDER BY id DESC LIMIT 1"
)['url'] ?? $hero_photo;

// Stats from DB
$stat_spots  = db_fetch_one("SELECT COUNT(*) as n FROM tourist_spots WHERE is_active=1")['n'] ?? '17+';
$stat_hotels = db_fetch_one("SELECT COUNT(*) as n FROM hotels WHERE is_active=1")['n'] ?? '9';
ensure_all_laguna_cities();
$stat_cities = db_fetch_one("SELECT COUNT(*) as n FROM cities")['n'] ?? '10+';
$stat_routes = db_fetch_one("SELECT COUNT(*) as n FROM routes")['n'] ?? '24+';

function spot_badge(string $category): string {
  $labels = [
    'nature'=>'Nature','heritage'=>'Heritage','waterfall'=>'Waterfall',
    'hotspring'=>'Hot Spring','museum'=>'Museum','religious'=>'Religious',
    'beach_lake'=>'Lake/Beach','adventure'=>'Adventure','food'=>'Food',
  ];
  $label = htmlspecialchars($labels[$category] ?? $category, ENT_QUOTES, 'UTF-8');
  $cat   = htmlspecialchars($category, ENT_QUOTES, 'UTF-8');
  return "<span class=\"badge-category badge-{$cat}\">{$label}</span>";
}
?>

<!-- ── HERO ────────────────────────────────────────────────── -->
<section class="hero-section">
  <?php if ($hero_photo): ?>
  <div class="hero-bg" style="background-image:url('<?= e($hero_photo) ?>')"></div>
  <?php endif; ?>
  <div class="container position-relative" style="z-index:1">
    <div class="row">
      <div class="col-lg-7 col-xl-6">
        <div class="fade-up">
          <span class="hero-eyebrow">Smart Travel Planner · Laguna, Philippines</span>
        </div>
        <h1 class="hero-title fade-up fade-up-1">
          Discover the <em>Heart</em> of Laguna Province
        </h1>
        <p class="hero-subtitle fade-up fade-up-2">
          <strong style="color:var(--sand-dark);font-style:italic">i</strong>Explore Laguna
          helps you plan your perfect Laguna trip —
          optimized routes, tourist spots along the way,
          real budget estimates, and auto-generated itineraries.
        </p>
        <div class="d-flex flex-wrap gap-3 fade-up fade-up-3">
          <a href="pages/planner.php" class="btn btn-accent btn-lg px-4 py-2 fw-bold">
            <i class="bi bi-compass me-2"></i>Plan My Trip
          </a>
          <a href="pages/spots.php" class="btn btn-outline-light btn-lg px-4 py-2">
            <i class="bi bi-geo-alt me-2"></i>Explore Spots
          </a>
        </div>

        <!-- Trust badges -->
        <div class="d-flex flex-wrap gap-3 mt-4 fade-up fade-up-4">
          <div class="d-flex align-items-center gap-2" style="color:rgba(255,255,255,.7);font-size:.83rem">
            <i class="bi bi-map-fill" style="color:var(--sand-dark)"></i>
            <span>Interactive Map</span>
          </div>
          <div class="d-flex align-items-center gap-2" style="color:rgba(255,255,255,.7);font-size:.83rem">
            <i class="bi bi-calculator-fill" style="color:var(--sand-dark)"></i>
            <span>Budget Estimator</span>
          </div>
          <div class="d-flex align-items-center gap-2" style="color:rgba(255,255,255,.7);font-size:.83rem">
            <i class="bi bi-journal-check" style="color:var(--sand-dark)"></i>
            <span>Auto Itinerary</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── Search bar overlapping hero + stats ──────────────────── -->
<div class="container position-relative">
  <div class="hero-search-bar fade-up fade-up-2">
    <div class="d-flex align-items-center gap-2 mb-1">
      <i class="bi bi-signpost-split text-green"></i>
      <h6 class="fw-bold mb-0" style="font-family:'Playfair Display',serif;color:var(--green-dark);font-size:.95rem">
        Quick Route Planner
      </h6>
    </div>
    <form id="quick-plan-form" class="row g-3 g-lg-0 align-items-end mt-1" novalidate>
      <div class="col-6 col-lg-3">
        <label class="field-label"><i class="bi bi-geo-alt me-1"></i>From</label>
        <select class="form-select" id="qp-origin" name="origin" required>
          <option value="">Select city</option>
          <?php foreach ($cities as $c): ?>
            <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-lg-3 hero-search-field">
        <label class="field-label"><i class="bi bi-flag me-1"></i>To</label>
        <select class="form-select" id="qp-dest" name="destination" required>
          <option value="">Select city</option>
          <?php foreach ($cities as $c): ?>
            <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-lg-2 hero-search-field">
        <label class="field-label"><i class="bi bi-calendar3 me-1"></i>Days</label>
        <select class="form-select" id="qp-days" name="days">
          <option value="1">1 day</option>
          <option value="2">2 days</option>
          <option value="3">3 days</option>
        </select>
      </div>
      <div class="col-6 col-lg-3 hero-search-field">
        <label class="field-label"><i class="bi bi-wallet2 me-1"></i>Budget</label>
        <select class="form-select" id="qp-budget" name="budget_level">
          <option value="budget">₱<?= number_format($budgetRanges['budget']['min']) ?>–<?= number_format($budgetRanges['budget']['max']) ?>/day</option>
          <option value="midrange" selected>₱<?= number_format($budgetRanges['midrange']['min']) ?>–<?= number_format($budgetRanges['midrange']['max']) ?>/day</option>
          <option value="upscale">₱<?= number_format($budgetRanges['upscale']['min']) ?>–<?= number_format($budgetRanges['upscale']['max']) ?>/day</option>
        </select>
      </div>
      <div class="col-12 col-lg-1">
        <button type="submit" class="btn btn-primary-app w-100 py-2 hero-search-submit" title="Find Route & Budget">
          <i class="bi bi-search me-2 d-lg-none"></i><span class="d-lg-none">Find Route &amp; Budget</span>
          <i class="bi bi-search d-none d-lg-inline"></i>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ── STATS STRIP ──────────────────────────────────────────── -->
<section class="stat-strip" style="margin-top:2.5rem">
  <div class="container">
    <div class="row g-0 text-center">
      <?php
      $stats = [
        [$stat_cities . ' of 30', 'Cities &amp; Municipalities', 'bi-buildings-fill'],
        [$stat_spots,  'Tourist Destinations',         'bi-geo-alt-fill'],
        [$stat_hotels, 'Hotels &amp; Resorts',          'bi-house-heart-fill'],
        [$stat_routes, 'Transport Routes',              'bi-signpost-split-fill'],
      ];
      foreach ($stats as [$num, $label, $icon]): ?>
      <div class="col-6 col-md-3 stat-item reveal">
        <i class="bi <?= $icon ?> fs-3 text-green mb-2 d-block"></i>
        <div class="stat-num"><?= $num ?></div>
        <div class="stat-lbl"><?= $label ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── FEATURED SPOTS ───────────────────────────────────────── -->
<section class="py-4">
  <div class="container">
    <div class="text-center mb-4 reveal">
      <span class="section-label">Must-Visit</span>
      <h2 class="section-title">Top Tourist Spots in Laguna</h2>
      <p class="section-subtitle mx-auto text-muted">
        From crater lakes to colonial churches and thrilling waterfalls —
        Laguna has something for every traveller.
      </p>
      <div class="divider-fancy mx-auto mt-3"></div>
    </div>

    <div class="row g-4">
      <?php
      $catIcons = ['nature'=>'bi-tree','heritage'=>'bi-bank','waterfall'=>'bi-droplet',
                   'hotspring'=>'bi-fire','museum'=>'bi-columns-gap','religious'=>'bi-building',
                   'beach_lake'=>'bi-water','adventure'=>'bi-compass','food'=>'bi-cup-hot'];
      $i = 0;
      foreach ($featured_spots as $spot): $i++;
      ?>
      <div class="col-sm-6 col-lg-4 reveal fade-up-<?= min($i,6) ?>">
        <div class="card-app h-100">
          <a href="pages/spot-detail.php?id=<?= $spot['id'] ?>" class="text-decoration-none">
          <?php if (!empty($spot['main_photo_url'])): ?>
          <div class="card-img-placeholder" style="padding:0;overflow:hidden">
            <img src="<?= e($spot['main_photo_url']) ?>" alt="<?= e($spot['name']) ?>" style="width:100%;height:100%;object-fit:cover">
          </div>
          <?php else: ?>
          <div class="card-img-placeholder">
            <i class="bi <?= $catIcons[$spot['category']] ?? 'bi-geo-alt' ?>"></i>
          </div>
          <?php endif; ?>
          </a>
          <div class="card-body-app">
            <div class="mb-2"><?= spot_badge($spot['category']) ?></div>
            <a href="pages/spot-detail.php?id=<?= $spot['id'] ?>" class="text-decoration-none">
              <h5 class="card-title-app" style="color:var(--charcoal)"><?= e($spot['name']) ?></h5>
            </a>
            <div class="card-meta mb-2">
              <i class="bi bi-geo-alt text-green"></i>
              <span><?= e($spot['city_name']) ?></span>
              <span>·</span>
              <span style="color:var(--sand-dark)"><?= str_repeat('★', round($spot['rating'])) ?></span>
              <small><?= number_format($spot['rating'],1) ?></small>
            </div>
            <p class="text-muted small mb-3" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">
              <?= e($spot['description'] ?? '') ?>
            </p>
            <div class="d-flex justify-content-between align-items-center">
              <span class="fw-bold">
                <?= $spot['entrance_fee'] > 0
                    ? '<span style="color:var(--terracotta)">₱ ' . number_format($spot['entrance_fee'],2) . '</span>'
                    : '<span class="text-success"><i class="bi bi-ticket-perforated me-1"></i>Free Entry</span>' ?>
              </span>
              <a href="pages/spot-detail.php?id=<?= $spot['id'] ?>"
                 class="btn btn-sm btn-primary-app">View Details</a>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="text-center mt-5 reveal">
      <a href="pages/spots.php" class="btn btn-primary-app px-5">
        View All Tourist Spots <i class="bi bi-arrow-right ms-2"></i>
      </a>
    </div>
  </div>
</section>

<!-- ── HOW IT WORKS ─────────────────────────────────────────── -->
<section class="py-4" style="background:var(--green-pale)">
  <div class="container">
    <div class="row g-5 align-items-center">
      <div class="col-lg-5 reveal">
        <span class="section-label">How it Works</span>
        <h2 class="section-title">Plan Your Laguna Trip in 4 Easy Steps</h2>
        <div class="divider-fancy mt-3 mb-3"></div>
        <p class="text-muted">
          From picking a route to getting a ready-made day-by-day plan —
          the whole thing takes minutes, not hours of tab-switching between
          maps, blogs, and spreadsheets.
        </p>
      </div>
      <div class="col-lg-7 reveal">
        <?php
        $steps = [
          ['bi-geo-alt-fill',    '1',  'Choose Your Route',   'Select your start and destination city from our list of Laguna municipalities.'],
          ['bi-map-fill',        '2',  'Explore the Map',     'See your route on an interactive map with tourist spots highlighted along the way.'],
          ['bi-calculator-fill', '3',  'Estimate Budget',     'Get a detailed breakdown: transport, entrance fees, food, and accommodation.'],
          ['bi-journal-check',   '4',  'Get Your Itinerary',  'Receive an auto-generated day-by-day travel plan based on your preferences.'],
        ];
        foreach ($steps as [$icon, $num, $title, $desc]): ?>
        <div class="step-row">
          <div class="step-row-line"></div>
          <div class="step-num"><?= $num ?></div>
          <div class="pt-1">
            <div class="step-row-title"><i class="bi <?= $icon ?> text-green me-2"></i><?= $title ?></div>
            <p class="text-muted small mb-0"><?= $desc ?></p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- ── CTA ──────────────────────────────────────────────────── -->
<section class="py-4 cta-section">
  <?php if ($cta_photo): ?>
  <div class="cta-bg" style="background-image:url('<?= e($cta_photo) ?>')"></div>
  <?php endif; ?>
  <div class="container text-center position-relative" style="z-index:1">
    <div class="reveal">
      <span class="section-label" style="color:var(--sand-dark)">Start Now</span>
      <h2 class="section-title mt-1" style="color:#fff">Ready to Explore Laguna?</h2>
      <p class="mb-4" style="color:rgba(255,255,255,.72);max-width:480px;margin:0 auto 1.75rem">
        Start planning your trip now — it's free, fast, and built specifically for Laguna travel.
      </p>
      <div class="d-flex flex-wrap gap-3 justify-content-center">
        <a href="pages/planner.php" class="btn btn-accent btn-lg px-5 py-2 fw-bold">
          <i class="bi bi-compass me-2"></i>Open Trip Planner
        </a>
        <a href="pages/spots.php" class="btn btn-outline-light btn-lg px-4 py-2">
          <i class="bi bi-geo-alt me-2"></i>Browse Spots
        </a>
      </div>
    </div>
  </div>
</section>

<script>
document.getElementById('quick-plan-form').addEventListener('submit', function(e) {
  e.preventDefault();
  const originField = document.getElementById('qp-origin');
  const destField    = document.getElementById('qp-dest');
  const origin = originField.value;
  const dest   = destField.value;
  const days   = document.getElementById('qp-days').value;
  const budget = document.getElementById('qp-budget').value;

  // Clear any previous error highlight before re-checking
  [originField, destField].forEach(f => f.closest('.col-6').classList.remove('field-error'));

  if (!origin || !dest) {
    if (!origin) originField.closest('.col-6').classList.add('field-error');
    if (!dest)   destField.closest('.col-6').classList.add('field-error');
    IExploreApp.toast('Please select both origin and destination.', 'warning');
    return;
  }
  if (origin === dest) {
    originField.closest('.col-6').classList.add('field-error');
    destField.closest('.col-6').classList.add('field-error');
    IExploreApp.toast('Origin and destination must be different cities.', 'warning');
    return;
  }
  const params = new URLSearchParams({ origin, destination: dest, days, budget_level: budget });
  window.location.href = `pages/planner.php?${params}`;
});

['qp-origin', 'qp-dest'].forEach(id => {
  document.getElementById(id).addEventListener('change', function() {
    this.closest('.col-6').classList.remove('field-error');
  });
});
</script>

<?php require_once 'includes/footer.php'; ?>
