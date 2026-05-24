<?php
// ============================================================
// IEXPLORE LAGUNA — Hotels Page (Enhanced v3)
// pages/hotels.php — Pagination + Sort + Compact layout
// ============================================================
$page_title  = 'Hotels & Resorts';
$active_page = 'hotels';
require_once __DIR__ . '/../includes/header.php';

$filter_city   = input('city',      'get', '');
$filter_stars  = (int) input('stars',     'get', 0);
$filter_budget = (int) input('max_price', 'get', 0);
$filter_sort   = input('sort',      'get', 'stars');
$view_mode     = input('view',      'get', 'grid');

// Pagination
$per_page = 9;
$page     = max(1, (int) input('p', 'get', 1));

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

if ($filter_sort === 'price_asc') {
    $order_sql = 'h.price_min ASC';
} elseif ($filter_sort === 'price_desc') {
    $order_sql = 'h.price_min DESC';
} elseif ($filter_sort === 'name') {
    $order_sql = 'h.name ASC';
} else {
    $order_sql = 'h.star_rating DESC, h.price_min ASC';
}

// Total count
$total_count = db_fetch_one(
    "SELECT COUNT(*) as n FROM hotels h JOIN cities c ON h.city_id = c.id WHERE {$where_sql}",
    $params
)['n'] ?? 0;

$total_pages = max(1, ceil($total_count / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$hotels = db_fetch_all(
    "SELECT h.*, c.name AS city_name, c.slug AS city_slug
     FROM hotels h
     JOIN cities c ON h.city_id = c.id
     WHERE {$where_sql}
     ORDER BY {$order_sql}
     LIMIT {$per_page} OFFSET {$offset}",
    $params
);

$cities = db_fetch_all("SELECT id, name, slug FROM cities ORDER BY name");

$base_qs = http_build_query(array_filter([
    'city' => $filter_city, 'stars' => $filter_stars ?: '',
    'max_price' => $filter_budget ?: '',
    'sort' => $filter_sort !== 'stars' ? $filter_sort : '',
    'view' => $view_mode !== 'grid' ? $view_mode : '',
]));

$amenityIcons = [
    'wifi'       => ['bi-wifi','WiFi'],
    'pool'       => ['bi-water','Pool'],
    'restaurant' => ['bi-cup-hot','Restaurant'],
    'spa'        => ['bi-heart-pulse','Spa'],
    'parking'    => ['bi-p-square','Parking'],
    'gym'        => ['bi-lightning','Gym'],
    'aircon'     => ['bi-thermometer-snow','A/C'],
];
?>

<section class="py-3" style="background:linear-gradient(135deg,var(--green-dark),var(--green-mid));color:#fff">
  <div class="container">
    <div class="d-flex align-items-center gap-3">
      <i class="bi bi-building fs-2" style="color:var(--sand-dark)"></i>
      <div>
        <h1 class="mb-0 fs-3" style="font-family:'Playfair Display',serif">Hotels &amp; Resorts</h1>
        <p class="mb-0 small opacity-75">
          <?= number_format($total_count) ?> accommodation<?= $total_count !== 1 ? 's' : '' ?> in Laguna
        </p>
      </div>
    </div>
  </div>
</section>

<!-- Toolbar -->
<div class="spots-toolbar sticky-top" style="top:56px;z-index:100">
  <div class="container">
    <div class="d-flex align-items-center gap-2 flex-wrap py-2">

      <!-- Star quick filters -->
      <div class="d-flex gap-1 flex-grow-1 flex-wrap">
        <a href="?<?= http_build_query(array_filter(['city'=>$filter_city,'max_price'=>$filter_budget?:'','view'=>$view_mode!=='grid'?$view_mode:''])) ?>"
           class="filter-pill <?= !$filter_stars ? 'active' : '' ?>">All Stars</a>
        <?php for ($s = 5; $s >= 1; $s--): ?>
        <a href="?<?= http_build_query(array_filter(['city'=>$filter_city,'stars'=>$s,'max_price'=>$filter_budget?:'','view'=>$view_mode!=='grid'?$view_mode:''])) ?>"
           class="filter-pill <?= $filter_stars===$s ? 'active' : '' ?>">
          <?= str_repeat('★',$s) ?>
        </a>
        <?php endfor; ?>
      </div>

      <div class="d-flex gap-2 align-items-center ms-auto">
        <select class="form-select form-select-sm" style="width:auto;font-size:.8rem" onchange="applySort(this.value)">
          <option value="stars"      <?= $filter_sort==='stars'?'selected':'' ?>>⭐ Top Rated</option>
          <option value="price_asc"  <?= $filter_sort==='price_asc'?'selected':'' ?>>💰 Price ↑</option>
          <option value="price_desc" <?= $filter_sort==='price_desc'?'selected':'' ?>>💰 Price ↓</option>
          <option value="name"       <?= $filter_sort==='name'?'selected':'' ?>>🔤 A–Z</option>
        </select>
        <div class="btn-group btn-group-sm" role="group">
          <a href="?<?= http_build_query(array_filter(['city'=>$filter_city,'stars'=>$filter_stars?:'','max_price'=>$filter_budget?:'','sort'=>$filter_sort!=='stars'?$filter_sort:''])) ?>"
             class="btn btn-outline-secondary <?= $view_mode==='grid'?'active':'' ?>" title="Grid view">
            <i class="bi bi-grid-3x3-gap"></i>
          </a>
          <a href="?<?= http_build_query(array_filter(['city'=>$filter_city,'stars'=>$filter_stars?:'','max_price'=>$filter_budget?:'','sort'=>$filter_sort!=='stars'?$filter_sort:'','view'=>'list'])) ?>"
             class="btn btn-outline-secondary <?= $view_mode==='list'?'active':'' ?>" title="List view">
            <i class="bi bi-list-ul"></i>
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<section class="py-4">
<div class="container">
<div class="row g-4">

  <!-- Filters sidebar -->
  <div class="col-lg-3">
    <div class="form-panel">
      <h6 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--green-dark)">
        <i class="bi bi-funnel me-2" style="color:var(--green-light)"></i>Filter Hotels
      </h6>
      <form method="GET">
        <input type="hidden" name="view" value="<?= e($view_mode) ?>">
        <div class="mb-3">
          <label class="form-label">City</label>
          <select class="form-select form-select-sm" name="city">
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
          <select class="form-select form-select-sm" name="stars">
            <option value="0">Any Stars</option>
            <?php for ($s = 5; $s >= 1; $s--): ?>
            <option value="<?= $s ?>" <?= $filter_stars===$s?'selected':'' ?>>
              <?= str_repeat('★',$s) . str_repeat('☆',5-$s) ?>
            </option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Max Price / Night</label>
          <select class="form-select form-select-sm" name="max_price">
            <option value="0">Any Price</option>
            <option value="1000"  <?= $filter_budget===1000?'selected':'' ?>>Under ₱1,000</option>
            <option value="2000"  <?= $filter_budget===2000?'selected':'' ?>>Under ₱2,000</option>
            <option value="3500"  <?= $filter_budget===3500?'selected':'' ?>>Under ₱3,500</option>
            <option value="6000"  <?= $filter_budget===6000?'selected':'' ?>>Under ₱6,000</option>
          </select>
        </div>
        <div class="mb-4">
          <label class="form-label">Sort By</label>
          <select class="form-select form-select-sm" name="sort">
            <option value="stars"      <?= $filter_sort==='stars'?'selected':'' ?>>⭐ Top Rated</option>
            <option value="price_asc"  <?= $filter_sort==='price_asc'?'selected':'' ?>>💰 Price Low→High</option>
            <option value="price_desc" <?= $filter_sort==='price_desc'?'selected':'' ?>>💰 Price High→Low</option>
            <option value="name"       <?= $filter_sort==='name'?'selected':'' ?>>🔤 A–Z</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary-app w-100 mb-2">
          <i class="bi bi-search me-2"></i>Apply Filters
        </button>
        <a href="hotels.php" class="btn btn-outline-secondary w-100 btn-sm">Clear</a>
      </form>
    </div>

    <!-- Results summary -->
    <div class="mt-3 p-3" style="background:#fff;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:.83rem">
      <div class="fw-bold mb-1" style="color:var(--green-dark)">
        <i class="bi bi-bar-chart-fill me-1"></i>Results
      </div>
      <div class="text-muted">
        Showing <strong style="color:var(--charcoal)"><?= count($hotels) ?></strong>
        of <strong style="color:var(--charcoal)"><?= number_format($total_count) ?></strong> hotels
        <?php if ($total_pages > 1): ?>
        <br>Page <?= $page ?> of <?= $total_pages ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Hotels content -->
  <div class="col-lg-9">
    <?php if (empty($hotels)): ?>
      <div class="text-center py-5">
        <i class="bi bi-building fs-1 text-muted d-block mb-3"></i>
        <h5>No hotels found</h5>
        <p class="text-muted">Try adjusting your filters.</p>
        <a href="hotels.php" class="btn btn-primary-app">View All Hotels</a>
      </div>
    <?php else: ?>

    <!-- ── GRID VIEW ── -->
    <?php if ($view_mode !== 'list'): ?>
    <div class="row g-3">
      <?php foreach ($hotels as $hotel):
        $amenities = json_decode($hotel['amenities'] ?? '[]', true) ?: [];
      ?>
      <div class="col-sm-6 col-xl-4">
        <div class="card-app h-100">
          <div class="card-img-placeholder" style="height:130px;font-size:2.2rem">🏨</div>
          <div class="card-body-app d-flex flex-column">
            <div class="mb-1" style="color:var(--sand-dark);font-size:.85rem">
              <?= str_repeat('★', $hotel['star_rating']) . str_repeat('☆', 5 - $hotel['star_rating']) ?>
              <small class="text-muted ms-1"><?= $hotel['star_rating'] ?>-star</small>
            </div>
            <h5 class="card-title-app mb-1" style="font-size:.98rem"><?= e($hotel['name']) ?></h5>
            <div class="card-meta mb-1">
              <i class="bi bi-geo-alt text-green"></i>
              <span><?= e($hotel['city_name']) ?></span>
            </div>
            <?php if ($hotel['address']): ?>
            <div class="mb-2" style="font-size:.75rem;color:var(--text-muted)">
              <i class="bi bi-pin-map me-1"></i><?= e(mb_strimwidth($hotel['address'],0,45,'…')) ?>
            </div>
            <?php endif; ?>
            <!-- Amenities -->
            <?php if ($amenities): ?>
            <div class="d-flex flex-wrap gap-1 mb-2">
              <?php foreach (array_slice($amenities,0,4) as $a):
                [$icon,$label] = $amenityIcons[$a] ?? ['bi-check',''];
                if (!$label) continue; ?>
                <span style="font-size:.68rem;background:var(--green-pale);color:var(--green-dark);padding:.15rem .5rem;border-radius:20px">
                  <i class="bi <?= $icon ?> me-1"></i><?= $label ?>
                </span>
              <?php endforeach; ?>
              <?php if (count($amenities) > 4): ?>
              <span style="font-size:.68rem;color:var(--text-muted)">+<?= count($amenities)-4 ?> more</span>
              <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="d-flex justify-content-between align-items-center mt-auto pt-2" style="border-top:1px solid var(--border)">
              <div>
                <span class="fw-bold" style="color:var(--green-mid);font-size:1.05rem">
                  ₱<?= number_format($hotel['price_min'], 0) ?>
                </span>
                <span class="text-muted" style="font-size:.78rem"> – ₱<?= number_format($hotel['price_max'], 0) ?></span>
                <div style="font-size:.68rem;color:var(--text-muted)">per night</div>
              </div>
              <?php if ($hotel['phone']): ?>
              <a href="tel:<?= e($hotel['phone']) ?>" class="btn btn-sm btn-outline-app" style="padding:.3rem .7rem">
                <i class="bi bi-telephone me-1"></i>Call
              </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- ── LIST VIEW ── -->
    <?php else: ?>
    <div class="d-flex flex-column gap-2">
      <?php foreach ($hotels as $hotel):
        $amenities = json_decode($hotel['amenities'] ?? '[]', true) ?: [];
      ?>
      <div class="spot-list-item">
        <div class="spot-emoji-box" style="font-size:1.6rem">🏨</div>
        <div class="flex-grow-1 min-w-0">
          <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap">
            <div>
              <div class="mb-0" style="color:var(--sand-dark);font-size:.8rem">
                <?= str_repeat('★',$hotel['star_rating']) . str_repeat('☆',5-$hotel['star_rating']) ?>
              </div>
              <h6 class="mb-0 fw-bold" style="font-family:'Playfair Display',serif;font-size:.95rem">
                <?= e($hotel['name']) ?>
              </h6>
              <div class="card-meta mt-1" style="font-size:.78rem">
                <i class="bi bi-geo-alt text-green"></i>
                <span><?= e($hotel['city_name']) ?></span>
                <?php if ($hotel['address']): ?>
                <span>·</span><span class="text-muted"><?= e(mb_strimwidth($hotel['address'],0,35,'…')) ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-shrink-0">
              <div class="text-end">
                <div class="fw-bold" style="color:var(--green-mid);font-size:.95rem">
                  ₱<?= number_format($hotel['price_min'],0) ?>–<?= number_format($hotel['price_max'],0) ?>
                </div>
                <div style="font-size:.7rem;color:var(--text-muted)">per night</div>
              </div>
              <?php if ($hotel['phone']): ?>
              <a href="tel:<?= e($hotel['phone']) ?>" class="btn btn-sm btn-outline-app" style="padding:.3rem .7rem;font-size:.78rem">
                <i class="bi bi-telephone"></i>
              </a>
              <?php endif; ?>
            </div>
          </div>
          <?php if ($amenities): ?>
          <div class="d-flex flex-wrap gap-1 mt-1">
            <?php foreach (array_slice($amenities,0,5) as $a):
              [$icon,$label] = $amenityIcons[$a] ?? ['bi-check',''];
              if (!$label) continue; ?>
              <span style="font-size:.68rem;background:var(--green-pale);color:var(--green-dark);padding:.12rem .45rem;border-radius:20px">
                <i class="bi <?= $icon ?> me-1"></i><?= $label ?>
              </span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ── PAGINATION ── -->
    <?php if ($total_pages > 1): ?>
    <nav class="mt-4" aria-label="Hotels pagination">
      <ul class="pagination pagination-app justify-content-center mb-0">
        <li class="page-item <?= $page<=1?'disabled':'' ?>">
          <a class="page-link" href="?<?= $base_qs ?>&p=<?= $page-1 ?>"><i class="bi bi-chevron-left"></i></a>
        </li>
        <?php
        $range=2; $start=max(1,$page-$range); $end=min($total_pages,$page+$range);
        if($start>1): ?><li class="page-item"><a class="page-link" href="?<?= $base_qs ?>&p=1">1</a></li><?php
          if($start>2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif;
        endif;
        for($i=$start;$i<=$end;$i++): ?>
        <li class="page-item <?= $i===$page?'active':'' ?>">
          <a class="page-link" href="?<?= $base_qs ?>&p=<?= $i ?>"><?= $i ?></a>
        </li>
        <?php endfor;
        if($end<$total_pages):
          if($end<$total_pages-1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
          <li class="page-item"><a class="page-link" href="?<?= $base_qs ?>&p=<?= $total_pages ?>"><?= $total_pages ?></a></li>
        <?php endif; ?>
        <li class="page-item <?= $page>=$total_pages?'disabled':'' ?>">
          <a class="page-link" href="?<?= $base_qs ?>&p=<?= $page+1 ?>"><i class="bi bi-chevron-right"></i></a>
        </li>
      </ul>
      <p class="text-center text-muted small mt-2">
        Showing <?= ($offset+1) ?>–<?= min($offset+$per_page,$total_count) ?> of <?= number_format($total_count) ?> hotels
      </p>
    </nav>
    <?php endif; ?>

    <?php endif; ?>
  </div>

</div>
</div>
</section>

<script>
function applySort(val) {
  const url = new URL(window.location.href);
  url.searchParams.set('sort', val);
  url.searchParams.delete('p');
  window.location.href = url.toString();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
