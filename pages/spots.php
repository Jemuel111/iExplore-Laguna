<?php
// ============================================================
// IEXPLORE LAGUNA — Tourist Spots Page (Enhanced v3)
// pages/spots.php — Pagination + View toggle + Compact layout
// ============================================================
$page_title  = 'Tourist Spots';
$active_page = 'spots';
require_once __DIR__ . '/../includes/header.php';

// Filters from GET
$filter_city = input('city', 'get', '');
$filter_cat  = input('category', 'get', '');
$filter_free = input('free', 'get', '');
$filter_sort = input('sort', 'get', 'rating');
$view_mode   = input('view', 'get', 'grid'); // grid | list

// Pagination
$per_page    = 12;
$page        = max(1, (int) input('p', 'get', 1));
$offset      = ($page - 1) * $per_page;

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

if ($filter_sort === 'name') {
    $order_sql = 's.name ASC';
} elseif ($filter_sort === 'fee') {
    $order_sql = 's.entrance_fee ASC';
} else {
    $order_sql = 's.rating DESC, s.name ASC';
}

// Count total for pagination
$total_count = db_fetch_one(
    "SELECT COUNT(*) as n FROM tourist_spots s JOIN cities c ON s.city_id = c.id WHERE {$where_sql}",
    $params
)['n'] ?? 0;

$total_pages = max(1, ceil($total_count / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

// Fetch paginated spots
$spots = db_fetch_all(
    "SELECT s.*, c.name AS city_name, c.slug AS city_slug
     FROM tourist_spots s
     JOIN cities c ON s.city_id = c.id
     WHERE {$where_sql}
     ORDER BY {$order_sql}
     LIMIT {$per_page} OFFSET {$offset}",
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

$catLabels = [
    'nature'=>'Nature','heritage'=>'Heritage','waterfall'=>'Waterfall',
    'hotspring'=>'Hot Spring','museum'=>'Museum','religious'=>'Religious',
    'beach_lake'=>'Lake/Beach','adventure'=>'Adventure','food'=>'Food',
];
$emojis = ['nature'=>'🌿','heritage'=>'🏛️','waterfall'=>'💧','hotspring'=>'♨️',
           'museum'=>'🏺','religious'=>'⛪','beach_lake'=>'🏞️','adventure'=>'🧗','food'=>'🍜'];
$badgeColors = [
    'nature'=>['#d8f3dc','#1a3a2a'],'heritage'=>['#fef3c7','#92400e'],
    'waterfall'=>['#dbeafe','#1e40af'],'hotspring'=>['#ffe4e6','#9f1239'],
    'museum'=>['#f3e8ff','#6b21a8'],'religious'=>['#fff7ed','#9a3412'],
    'beach_lake'=>['#e0f2fe','#075985'],'adventure'=>['#fef9c3','#713f12'],
    'food'=>['#fce7f3','#9d174d'],
];

// Build base query string (no page param) for pagination links
$base_qs = http_build_query(array_filter([
    'city' => $filter_city, 'category' => $filter_cat,
    'free' => $filter_free, 'sort' => $filter_sort !== 'rating' ? $filter_sort : '',
    'view' => $view_mode !== 'grid' ? $view_mode : '',
]));
?>

<!-- Page header -->
<section class="py-3" style="background:linear-gradient(135deg,var(--green-dark),var(--green-mid));color:#fff">
  <div class="container">
    <div class="d-flex align-items-center gap-3">
      <i class="bi bi-geo-alt-fill fs-2" style="color:var(--sand-dark)"></i>
      <div>
        <h1 class="mb-0 fs-3" style="font-family:'Playfair Display',serif">Tourist Spots</h1>
        <p class="mb-0 small opacity-75">
          <?= number_format($total_count) ?> spot<?= $total_count !== 1 ? 's' : '' ?> across Laguna province
        </p>
      </div>
    </div>
  </div>
</section>

<!-- ── Sticky toolbar ───────────────────────────────────────── -->
<div class="spots-toolbar sticky-top" style="top:56px;z-index:100">
  <div class="container">
    <div class="d-flex align-items-center gap-2 flex-wrap py-2">

      <!-- Category pills -->
      <div class="d-flex gap-1 flex-wrap flex-grow-1">
        <a href="?<?= http_build_query(array_filter(['city'=>$filter_city,'free'=>$filter_free,'view'=>$view_mode!=='grid'?$view_mode:''])) ?>"
           class="filter-pill <?= !$filter_cat ? 'active' : '' ?>">All</a>
        <?php foreach ($categories as $val => $label): ?>
        <a href="?<?= http_build_query(array_filter(['city'=>$filter_city,'category'=>$val,'free'=>$filter_free,'view'=>$view_mode!=='grid'?$view_mode:''])) ?>"
           class="filter-pill <?= $filter_cat === $val ? 'active' : '' ?>">
          <?= $label ?>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- Right controls -->
      <div class="d-flex gap-2 align-items-center ms-auto">
        <!-- Sort -->
        <select class="form-select form-select-sm" style="width:auto;font-size:.8rem" onchange="applySort(this.value)">
          <option value="rating"  <?= $filter_sort==='rating'?'selected':'' ?>>⭐ Top Rated</option>
          <option value="name"    <?= $filter_sort==='name'?'selected':'' ?>>🔤 A–Z</option>
          <option value="fee"     <?= $filter_sort==='fee'?'selected':'' ?>>💰 Price ↑</option>
        </select>

        <!-- View toggle -->
        <div class="btn-group btn-group-sm" role="group">
          <a href="?<?= http_build_query(array_filter(['city'=>$filter_city,'category'=>$filter_cat,'free'=>$filter_free,'sort'=>$filter_sort!=='rating'?$filter_sort:''])) ?>"
             class="btn btn-outline-secondary <?= $view_mode==='grid'?'active':'' ?>" title="Grid view">
            <i class="bi bi-grid-3x3-gap"></i>
          </a>
          <a href="?<?= http_build_query(array_filter(['city'=>$filter_city,'category'=>$filter_cat,'free'=>$filter_free,'sort'=>$filter_sort!=='rating'?$filter_sort:'','view'=>'list'])) ?>"
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

  <!-- ── Filters sidebar ───────────────────────────────── -->
  <div class="col-lg-3">
    <div class="form-panel">
      <h6 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--green-dark)">
        <i class="bi bi-funnel me-2" style="color:var(--green-light)"></i>Filter Spots
      </h6>

      <form method="GET" action="">
        <input type="hidden" name="view" value="<?= e($view_mode) ?>">

        <!-- City filter -->
        <div class="mb-3">
          <label class="form-label">City / Municipality</label>
          <select class="form-select form-select-sm" name="city">
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
          <select class="form-select form-select-sm" name="category">
            <option value="">All Categories</option>
            <?php foreach ($categories as $val => $label): ?>
              <option value="<?= $val ?>" <?= $filter_cat === $val ? 'selected' : '' ?>>
                <?= $label ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Sort -->
        <div class="mb-3">
          <label class="form-label">Sort By</label>
          <select class="form-select form-select-sm" name="sort">
            <option value="rating" <?= $filter_sort==='rating'?'selected':'' ?>>⭐ Top Rated</option>
            <option value="name"   <?= $filter_sort==='name'?'selected':'' ?>>🔤 A–Z</option>
            <option value="fee"    <?= $filter_sort==='fee'?'selected':'' ?>>💰 Price ↑</option>
          </select>
        </div>

        <!-- Free entry toggle -->
        <div class="mb-4">
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

    <!-- Results summary -->
    <div class="mt-3 p-3" style="background:#fff;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:.83rem">
      <div class="fw-bold mb-1" style="color:var(--green-dark)">
        <i class="bi bi-bar-chart-fill me-1"></i>Results
      </div>
      <div class="text-muted">
        Showing <strong style="color:var(--charcoal)"><?= count($spots) ?></strong>
        of <strong style="color:var(--charcoal)"><?= number_format($total_count) ?></strong> spots
        <?php if ($total_pages > 1): ?>
        <br>Page <?= $page ?> of <?= $total_pages ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ── Spots grid / list ──────────────────────────────── -->
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
      <span class="text-muted small">Active:</span>
      <?php if ($filter_city): ?>
        <span class="badge rounded-pill" style="background:var(--green-pale);color:var(--green-dark);padding:.35rem .85rem">
          📍 <?= e($filter_city) ?> <a href="?<?= http_build_query(array_filter(['category'=>$filter_cat,'free'=>$filter_free,'view'=>$view_mode!=='grid'?$view_mode:''])) ?>" class="text-decoration-none ms-1" style="color:var(--green-dark)">×</a>
        </span>
      <?php endif; ?>
      <?php if ($filter_cat): ?>
        <span class="badge rounded-pill" style="background:var(--green-pale);color:var(--green-dark);padding:.35rem .85rem">
          <?= e($categories[$filter_cat] ?? $filter_cat) ?> <a href="?<?= http_build_query(array_filter(['city'=>$filter_city,'free'=>$filter_free,'view'=>$view_mode!=='grid'?$view_mode:''])) ?>" class="text-decoration-none ms-1" style="color:var(--green-dark)">×</a>
        </span>
      <?php endif; ?>
      <?php if ($filter_free): ?>
        <span class="badge rounded-pill" style="background:var(--green-pale);color:var(--green-dark);padding:.35rem .85rem">
          🎉 Free Entry <a href="?<?= http_build_query(array_filter(['city'=>$filter_city,'category'=>$filter_cat,'view'=>$view_mode!=='grid'?$view_mode:''])) ?>" class="text-decoration-none ms-1" style="color:var(--green-dark)">×</a>
        </span>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ── GRID VIEW ── -->
    <?php if ($view_mode !== 'list'): ?>
    <div class="row g-3">
      <?php foreach ($spots as $spot):
        [$bg,$fg] = $badgeColors[$spot['category']] ?? ['#f1f5f9','#334155'];
      ?>
      <div class="col-sm-6 col-xl-4">
        <div class="card-app h-100">
          <div class="card-img-placeholder" style="height:150px;font-size:2.5rem">
            <?= $emojis[$spot['category']] ?? '📍' ?>
          </div>
          <div class="card-body-app d-flex flex-column">
            <div class="mb-2">
              <span style="background:<?= $bg ?>;color:<?= $fg ?>;padding:.2rem .7rem;border-radius:20px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em">
                <?= $catLabels[$spot['category']] ?? $spot['category'] ?>
              </span>
            </div>
            <h5 class="card-title-app mb-1" style="font-size:1rem"><?= e($spot['name']) ?></h5>
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
            <div class="d-flex justify-content-between align-items-center mt-auto pt-2"
                 style="border-top:1px solid var(--border)">
              <span class="fw-bold">
                <?= $spot['entrance_fee'] > 0
                    ? '<span style="color:var(--terracotta)">₱ ' . number_format($spot['entrance_fee'], 0) . '</span>'
                    : '<span style="color:#16a34a;font-size:.82rem">🎉 Free</span>' ?>
              </span>
              <a href="planner.php?destination=<?= $spot['city_id'] ?>"
                 class="btn btn-sm btn-outline-app">
                <i class="bi bi-compass me-1"></i>Plan
              </a>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- ── LIST VIEW ── -->
    <?php else: ?>
    <div class="d-flex flex-column gap-2">
      <?php foreach ($spots as $spot):
        [$bg,$fg] = $badgeColors[$spot['category']] ?? ['#f1f5f9','#334155'];
      ?>
      <div class="spot-list-item">
        <div class="spot-emoji-box"><?= $emojis[$spot['category']] ?? '📍' ?></div>
        <div class="flex-grow-1 min-w-0">
          <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap">
            <div>
              <h6 class="mb-0 fw-bold" style="font-family:'Playfair Display',serif;font-size:.95rem">
                <?= e($spot['name']) ?>
              </h6>
              <div class="card-meta mt-1" style="font-size:.78rem">
                <i class="bi bi-geo-alt text-green"></i>
                <span><?= e($spot['city_name']) ?></span>
                <span>·</span>
                <span style="color:var(--sand-dark)">★ <?= number_format($spot['rating'],1) ?></span>
                <?php if ($spot['operating_hours']): ?>
                <span>·</span>
                <i class="bi bi-clock text-muted"></i>
                <span class="text-muted"><?= e(mb_strimwidth($spot['operating_hours'],0,30,'…')) ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-shrink-0">
              <span style="background:<?= $bg ?>;color:<?= $fg ?>;padding:.18rem .6rem;border-radius:20px;font-size:.7rem;font-weight:700;white-space:nowrap">
                <?= $catLabels[$spot['category']] ?? $spot['category'] ?>
              </span>
              <span class="fw-bold" style="font-size:.85rem;<?= $spot['entrance_fee']>0 ? 'color:var(--terracotta)' : 'color:#16a34a' ?>">
                <?= $spot['entrance_fee'] > 0 ? '₱'.number_format($spot['entrance_fee'],0) : 'Free' ?>
              </span>
              <a href="planner.php?destination=<?= $spot['city_id'] ?>"
                 class="btn btn-sm btn-outline-app" style="padding:.3rem .7rem;font-size:.78rem">
                Plan
              </a>
            </div>
          </div>
          <?php if ($spot['description']): ?>
          <p class="text-muted mb-0 mt-1" style="font-size:.8rem;display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden">
            <?= e($spot['description']) ?>
          </p>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ── PAGINATION ────────────────────────────────────── -->
    <?php if ($total_pages > 1): ?>
    <nav class="mt-4" aria-label="Spots pagination">
      <ul class="pagination pagination-app justify-content-center mb-0">

        <!-- Prev -->
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="?<?= $base_qs ?>&p=<?= $page - 1 ?>">
            <i class="bi bi-chevron-left"></i>
          </a>
        </li>

        <?php
        $range = 2;
        $start = max(1, $page - $range);
        $end   = min($total_pages, $page + $range);
        if ($start > 1): ?>
          <li class="page-item"><a class="page-link" href="?<?= $base_qs ?>&p=1">1</a></li>
          <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $start; $i <= $end; $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
          <a class="page-link" href="?<?= $base_qs ?>&p=<?= $i ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>

        <?php if ($end < $total_pages): ?>
          <?php if ($end < $total_pages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
          <li class="page-item"><a class="page-link" href="?<?= $base_qs ?>&p=<?= $total_pages ?>"><?= $total_pages ?></a></li>
        <?php endif; ?>

        <!-- Next -->
        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
          <a class="page-link" href="?<?= $base_qs ?>&p=<?= $page + 1 ?>">
            <i class="bi bi-chevron-right"></i>
          </a>
        </li>
      </ul>
      <p class="text-center text-muted small mt-2">
        Showing <?= ($offset + 1) ?>–<?= min($offset + $per_page, $total_count) ?> of <?= number_format($total_count) ?> spots
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
