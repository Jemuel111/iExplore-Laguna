<?php
// ============================================================
// IEXPLORE LAGUNA — Hotels Page
// pages/hotels.php
// ============================================================
$page_title  = 'Hotels & Resorts';
$active_page = 'hotels';
require_once __DIR__ . '/../includes/header.php';

$filter_city   = input('city',    'get', '');
$filter_stars  = (int) input('stars', 'get', 0);
$filter_budget = (int) input('max_price', 'get', 0);

$where  = ['h.is_active = 1'];
$params = [];

if ($filter_city) {
    $where[]  = 'c.slug = ?';
    $params[] = $filter_city;
}
if ($filter_stars) {
    $where[]  = 'h.star_rating = ?';
    $params[] = $filter_stars;
}
if ($filter_budget) {
    $where[]  = 'h.price_min <= ?';
    $params[] = $filter_budget;
}

$where_sql = implode(' AND ', $where);

$hotels = db_fetch_all(
    "SELECT h.*, c.name AS city_name, c.slug AS city_slug
     FROM hotels h
     JOIN cities c ON h.city_id = c.id
     WHERE {$where_sql}
     ORDER BY h.star_rating DESC, h.price_min ASC",
    $params
);

$cities = db_fetch_all("SELECT id, name, slug FROM cities ORDER BY name");
?>

<section class="py-4" style="background:linear-gradient(135deg,var(--green-dark),var(--green-mid));color:#fff">
  <div class="container">
    <div class="d-flex align-items-center gap-3">
      <i class="bi bi-building fs-2" style="color:var(--sand-dark)"></i>
      <div>
        <h1 class="mb-0 fs-3" style="font-family:'Playfair Display',serif">Hotels &amp; Resorts</h1>
        <p class="mb-0 small opacity-75">
          <?= count($hotels) ?> accommodation<?= count($hotels) !== 1 ? 's' : '' ?> found in Laguna
        </p>
      </div>
    </div>
  </div>
</section>

<section class="py-4">
<div class="container">
<div class="row g-4">

  <!-- Filters -->
  <div class="col-lg-3">
    <div class="form-panel">
      <h6 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--green-dark)">
        <i class="bi bi-funnel me-2" style="color:var(--green-light)"></i>Filter Hotels
      </h6>
      <form method="GET">
        <div class="mb-3">
          <label class="form-label">City</label>
          <select class="form-select" name="city">
            <option value="">All Cities</option>
            <?php foreach ($cities as $c): ?>
              <option value="<?= e($c['slug']) ?>" <?= $filter_city===$c['slug']?'selected':'' ?>>
                <?= e($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Star Rating</label>
          <select class="form-select" name="stars">
            <option value="0">Any Stars</option>
            <?php for ($s = 5; $s >= 1; $s--): ?>
            <option value="<?= $s ?>" <?= $filter_stars===$s?'selected':'' ?>>
              <?= str_repeat('★',$s) . str_repeat('☆',5-$s) ?>
            </option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Max Price per Night</label>
          <select class="form-select" name="max_price">
            <option value="0">Any Price</option>
            <option value="1000" <?= $filter_budget===1000?'selected':'' ?>>Under ₱1,000</option>
            <option value="2000" <?= $filter_budget===2000?'selected':'' ?>>Under ₱2,000</option>
            <option value="3500" <?= $filter_budget===3500?'selected':'' ?>>Under ₱3,500</option>
            <option value="6000" <?= $filter_budget===6000?'selected':'' ?>>Under ₱6,000</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary-app w-100 mb-2">
          <i class="bi bi-search me-2"></i>Apply Filters
        </button>
        <a href="hotels.php" class="btn btn-outline-secondary w-100 btn-sm">Clear</a>
      </form>
    </div>
  </div>

  <!-- Hotels list -->
  <div class="col-lg-9">
    <?php if (empty($hotels)): ?>
      <div class="text-center py-5">
        <i class="bi bi-building fs-1 text-muted d-block mb-3"></i>
        <h5>No hotels found</h5>
        <p class="text-muted">Try adjusting your filters.</p>
        <a href="hotels.php" class="btn btn-primary-app">View All Hotels</a>
      </div>
    <?php else: ?>
    <div class="row g-3">
      <?php foreach ($hotels as $hotel):
        $amenities = json_decode($hotel['amenities'] ?? '[]', true) ?: [];
        $amenityIcons = [
          'wifi'=>['bi-wifi','WiFi'], 'pool'=>['bi-water','Pool'],
          'restaurant'=>['bi-cup-hot','Restaurant'], 'spa'=>['bi-heart-pulse','Spa'],
          'parking'=>['bi-p-square','Parking'], 'gym'=>['bi-lightning','Gym'],
          'aircon'=>['bi-thermometer-snow','A/C'],
        ];
      ?>
      <div class="col-12 col-md-6">
        <div class="card-app h-100">
          <div class="card-img-placeholder" style="height:160px;font-size:2.5rem">🏨</div>
          <div class="card-body-app d-flex flex-column">

            <!-- Stars -->
            <div class="mb-1" style="color:var(--sand-dark);font-size:.9rem">
              <?= str_repeat('★', $hotel['star_rating']) . str_repeat('☆', 5 - $hotel['star_rating']) ?>
              <small class="text-muted ms-1"><?= $hotel['star_rating'] ?>-star</small>
            </div>

            <h5 class="card-title-app mb-1"><?= e($hotel['name']) ?></h5>

            <div class="card-meta mb-2">
              <i class="bi bi-geo-alt text-green"></i>
              <span><?= e($hotel['city_name']) ?></span>
            </div>

            <?php if ($hotel['address']): ?>
            <div class="mb-2" style="font-size:.78rem;color:var(--text-muted)">
              <i class="bi bi-pin-map me-1"></i><?= e($hotel['address']) ?>
            </div>
            <?php endif; ?>

            <p class="text-muted small mb-3 flex-grow-1"
               style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">
              <?= e($hotel['description']) ?>
            </p>

            <!-- Amenity icons -->
            <?php if ($amenities): ?>
            <div class="d-flex flex-wrap gap-2 mb-3">
              <?php foreach ($amenities as $a):
                [$icon, $label] = $amenityIcons[$a] ?? ['bi-check',''];
                if (!$label) continue; ?>
                <span style="font-size:.72rem;background:var(--green-pale);color:var(--green-dark);
                             padding:.2rem .6rem;border-radius:20px">
                  <i class="bi <?= $icon ?> me-1"></i><?= $label ?>
                </span>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mt-auto pt-2"
                 style="border-top:1px solid var(--border)">
              <div>
                <span class="fw-bold fs-5" style="color:var(--green-mid)">
                  ₱<?= number_format($hotel['price_min'], 0) ?>
                </span>
                <span class="text-muted small"> – ₱<?= number_format($hotel['price_max'], 0) ?></span>
                <div style="font-size:.72rem;color:var(--text-muted)">per night</div>
              </div>
              <?php if ($hotel['phone']): ?>
              <a href="tel:<?= e($hotel['phone']) ?>"
                 class="btn btn-sm btn-outline-app">
                <i class="bi bi-telephone me-1"></i>Call
              </a>
              <?php endif; ?>
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