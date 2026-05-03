<?php
// ============================================================
// IEXPLORE LAGUNA — Tourist Spots Page
// pages/spots.php
// ============================================================
$page_title  = 'Tourist Spots';
$active_page = 'spots';
require_once __DIR__ . '/../includes/header.php';

// Filters from GET
$filter_city = input('city', 'get', '');
$filter_cat  = input('category', 'get', '');
$filter_free = input('free', 'get', '');

// Build query
$where  = ['s.is_active = 1'];
$params = [];

if ($filter_city) {
    $where[]  = 'c.slug = ?';
    $params[] = $filter_city;
}
if ($filter_cat) {
    $where[]  = 's.category = ?';
    $params[] = $filter_cat;
}
if ($filter_free === '1') {
    $where[] = 's.entrance_fee = 0';
}

$where_sql = implode(' AND ', $where);

$spots = db_fetch_all(
    "SELECT s.*, c.name AS city_name, c.slug AS city_slug
     FROM tourist_spots s
     JOIN cities c ON s.city_id = c.id
     WHERE {$where_sql}
     ORDER BY s.rating DESC, s.name ASC",
    $params
);

$cities = db_fetch_all("SELECT id, name, slug FROM cities ORDER BY name");

$categories = [
    'nature'    => '🌿 Nature',
    'heritage'  => '🏛️ Heritage',
    'waterfall' => '💧 Waterfall',
    'hotspring' => '♨️ Hot Spring',
    'museum'    => '🏺 Museum',
    'religious' => '⛪ Religious',
    'beach_lake'=> '🏞️ Lake/Beach',
    'adventure' => '🧗 Adventure',
    'food'      => '🍜 Food',
];
?>

<!-- Page header -->
<section class="py-4" style="background:linear-gradient(135deg,var(--green-dark),var(--green-mid));color:#fff">
  <div class="container">
    <div class="d-flex align-items-center gap-3">
      <i class="bi bi-geo-alt-fill fs-2" style="color:var(--sand-dark)"></i>
      <div>
        <h1 class="mb-0 fs-3" style="font-family:'Playfair Display',serif">Tourist Spots</h1>
        <p class="mb-0 small opacity-75">
          <?= count($spots) ?> spot<?= count($spots) !== 1 ? 's' : '' ?> found across Laguna province
        </p>
      </div>
    </div>
  </div>
</section>

<section class="py-4">
<div class="container">
<div class="row g-4">

  <!-- ── Filters sidebar ───────────────────────────────── -->
  <div class="col-lg-3">
    <div class="form-panel">
      <h6 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--green-dark)">
        <i class="bi bi-funnel me-2" style="color:var(--green-light)"></i>Filter Spots
      </h6>

      <form method="GET" action="">

        <!-- City filter -->
        <div class="mb-3">
          <label class="form-label">City / Municipality</label>
          <select class="form-select" name="city">
            <option value="">All Cities</option>
            <?php foreach ($cities as $c): ?>
              <option value="<?= e($c['slug']) ?>"
                <?= $filter_city === $c['slug'] ? 'selected' : '' ?>>
                <?= e($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Category filter -->
        <div class="mb-3">
          <label class="form-label">Category</label>
          <select class="form-select" name="category">
            <option value="">All Categories</option>
            <?php foreach ($categories as $val => $label): ?>
              <option value="<?= $val ?>" <?= $filter_cat === $val ? 'selected' : '' ?>>
                <?= $label ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Free entry toggle -->
        <div class="mb-3">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="free" value="1"
                   id="freeCheck" <?= $filter_free === '1' ? 'checked' : '' ?>>
            <label class="form-check-label small fw-600" for="freeCheck">
              Free entry only
            </label>
          </div>
        </div>

        <button type="submit" class="btn btn-primary-app w-100 mb-2">
          <i class="bi bi-search me-2"></i>Apply Filters
        </button>
        <a href="spots.php" class="btn btn-outline-secondary w-100 btn-sm">
          Clear Filters
        </a>
      </form>
    </div>

    <!-- Category quick links -->
    <div class="form-panel mt-3">
      <h6 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--green-dark)">
        Browse by Category
      </h6>
      <?php foreach ($categories as $val => $label): ?>
        <a href="?category=<?= $val ?>"
           class="d-flex align-items-center justify-content-between py-2 px-1 text-decoration-none"
           style="border-bottom:1px solid var(--border);font-size:.88rem;
                  color:<?= $filter_cat===$val ? 'var(--green-mid)' : 'var(--charcoal)' ?>;
                  font-weight:<?= $filter_cat===$val ? '700' : '400' ?>">
          <span><?= $label ?></span>
          <i class="bi bi-chevron-right small text-muted"></i>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── Spots grid ────────────────────────────────────── -->
  <div class="col-lg-9">

    <?php if (empty($spots)): ?>
      <div class="text-center py-5">
        <i class="bi bi-geo-alt fs-1 text-muted d-block mb-3"></i>
        <h5 class="fw-bold">No spots found</h5>
        <p class="text-muted">Try adjusting your filters.</p>
        <a href="spots.php" class="btn btn-primary-app">View All Spots</a>
      </div>
    <?php else: ?>

    <!-- Active filter pills -->
    <?php if ($filter_city || $filter_cat || $filter_free): ?>
    <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
      <span class="text-muted small">Active filters:</span>
      <?php if ($filter_city): ?>
        <span class="badge rounded-pill" style="background:var(--green-pale);color:var(--green-dark);padding:.35rem .85rem">
          <?= e($filter_city) ?> <a href="?<?= http_build_query(array_filter(['category'=>$filter_city,'free'=>$filter_free])) ?>" class="text-decoration-none ms-1" style="color:var(--green-dark)">×</a>
        </span>
      <?php endif; ?>
      <?php if ($filter_cat): ?>
        <span class="badge rounded-pill" style="background:var(--green-pale);color:var(--green-dark);padding:.35rem .85rem">
          <?= e($categories[$filter_cat] ?? $filter_cat) ?> <a href="?<?= http_build_query(array_filter(['city'=>$filter_city,'free'=>$filter_free])) ?>" class="text-decoration-none ms-1" style="color:var(--green-dark)">×</a>
        </span>
      <?php endif; ?>
      <?php if ($filter_free): ?>
        <span class="badge rounded-pill" style="background:var(--green-pale);color:var(--green-dark);padding:.35rem .85rem">
          Free Entry <a href="?<?= http_build_query(array_filter(['city'=>$filter_city,'category'=>$filter_cat])) ?>" class="text-decoration-none ms-1" style="color:var(--green-dark)">×</a>
        </span>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="row g-3">
      <?php foreach ($spots as $spot): ?>
      <div class="col-sm-6 col-xl-4">
        <div class="card-app h-100">

          <!-- Image / placeholder -->
          <div class="card-img-placeholder" style="height:170px;font-size:2.8rem">
            <?php
            $emojis = ['nature'=>'🌿','heritage'=>'🏛️','waterfall'=>'💧','hotspring'=>'♨️',
                       'museum'=>'🏺','religious'=>'⛪','beach_lake'=>'🏞️','adventure'=>'🧗','food'=>'🍜'];
            echo $emojis[$spot['category']] ?? '📍';
            ?>
          </div>

          <div class="card-body-app d-flex flex-column">
            <!-- Category badge -->
            <div class="mb-2">
              <?php
              $badgeColors = [
                'nature'=>['#d8f3dc','#1a3a2a'],'heritage'=>['#fef3c7','#92400e'],
                'waterfall'=>['#dbeafe','#1e40af'],'hotspring'=>['#ffe4e6','#9f1239'],
                'museum'=>['#f3e8ff','#6b21a8'],'religious'=>['#fff7ed','#9a3412'],
                'beach_lake'=>['#e0f2fe','#075985'],'adventure'=>['#fef9c3','#713f12'],
                'food'=>['#fce7f3','#9d174d'],
              ];
              [$bg,$fg] = $badgeColors[$spot['category']] ?? ['#f1f5f9','#334155'];
              $catLabels = ['nature'=>'Nature','heritage'=>'Heritage','waterfall'=>'Waterfall',
                            'hotspring'=>'Hot Spring','museum'=>'Museum','religious'=>'Religious',
                            'beach_lake'=>'Lake/Beach','adventure'=>'Adventure','food'=>'Food'];
              ?>
              <span style="background:<?= $bg ?>;color:<?= $fg ?>;padding:.2rem .7rem;border-radius:20px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em">
                <?= $catLabels[$spot['category']] ?? $spot['category'] ?>
              </span>
            </div>

            <h5 class="card-title-app mb-1"><?= e($spot['name']) ?></h5>

            <div class="card-meta mb-2">
              <i class="bi bi-geo-alt text-green"></i>
              <span><?= e($spot['city_name']) ?></span>
              <span>·</span>
              <span style="color:var(--sand-dark)">★</span>
              <span><?= number_format($spot['rating'], 1) ?></span>
            </div>

            <p class="text-muted small mb-3 flex-grow-1"
               style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">
              <?= e($spot['description']) ?>
            </p>

            <?php if ($spot['operating_hours']): ?>
            <div class="mb-2" style="font-size:.78rem;color:var(--text-muted)">
              <i class="bi bi-clock me-1"></i><?= e($spot['operating_hours']) ?>
            </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mt-auto pt-2"
                 style="border-top:1px solid var(--border)">
              <span class="fw-bold" style="color:var(--terracotta)">
                <?= $spot['entrance_fee'] > 0
                    ? '₱ ' . number_format($spot['entrance_fee'], 2)
                    : '<span style="color:#16a34a">🎉 Free Entry</span>' ?>
              </span>
              <a href="planner.php?destination=<?= $spot['city_id'] ?>"
                 class="btn btn-sm btn-outline-app">
                <i class="bi bi-compass me-1"></i>Plan Visit
              </a>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php endif; ?>
  </div>

</div>
</div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>