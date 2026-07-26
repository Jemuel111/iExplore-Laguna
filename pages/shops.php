<?php
// ============================================================
// IEXPLORE LAGUNA — Shops Page
// pages/shops.php — Browse local shops (milk tea, cafes, pasalubong, etc.)
// Mirrors pages/hotels.php pattern
// ============================================================
$page_title  = 'Local Shops';
$active_page = 'shops';
require_once __DIR__ . '/../includes/header.php';

$filter_city     = input('city',     'get', '');
$filter_category = input('category', 'get', '');
$filter_sort     = input('sort',     'get', 'name');
$view_mode       = input('view',     'get', 'grid');

// Pagination
$per_page = 9;
$page     = max(1, (int) input('p', 'get', 1));

$where  = ['s.is_active = 1', 's.is_verified = 1'];
$params = [];

if ($filter_city) {
    $where[]  = 'c.slug = ?';
    $params[] = $filter_city;
}
if ($filter_category) {
    $where[]  = 's.category = ?';
    $params[] = $filter_category;
}

$where_sql = implode(' AND ', $where);

if ($filter_sort === 'city') {
    $order_sql = 'c.name ASC, s.name ASC';
} elseif ($filter_sort === 'category') {
    $order_sql = 's.category ASC, s.name ASC';
} else {
    $order_sql = 's.name ASC';
}

// Total count
$total_count = db_fetch_one(
    "SELECT COUNT(*) as n FROM shops s JOIN cities c ON s.city_id = c.id WHERE {$where_sql}",
    $params
)['n'] ?? 0;

$total_pages = max(1, ceil($total_count / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$shops = db_fetch_all(
    "SELECT s.*, c.name AS city_name, c.slug AS city_slug
     FROM shops s
     JOIN cities c ON s.city_id = c.id
     WHERE {$where_sql}
     ORDER BY {$order_sql}
     LIMIT {$per_page} OFFSET {$offset}",
    $params
);

// Map view shows every filtered result at once, not just the current page.
// Shops without their own exact pin fall back to their city's center point.
$map_shops = [];
if ($view_mode === 'map') {
    $map_shops = db_fetch_all(
        "SELECT s.id, s.name, s.category,
                COALESCE(s.latitude, c.latitude)   AS latitude,
                COALESCE(s.longitude, c.longitude) AS longitude,
                (s.latitude IS NULL) AS is_approximate,
                c.name AS city_name
         FROM shops s
         JOIN cities c ON s.city_id = c.id
         WHERE {$where_sql}
         ORDER BY {$order_sql}",
        $params
    );
}

$cities = db_fetch_all("SELECT id, name, slug FROM cities ORDER BY name");

// Category metadata now comes from the shared shop_categories() helper
// in helpers.php instead of a locally duplicated emoji array.

$base_qs = http_build_query(array_filter([
    'city' => $filter_city, 'category' => $filter_category,
    'sort' => $filter_sort !== 'name' ? $filter_sort : '',
    'view' => $view_mode !== 'grid' ? $view_mode : '',
]));
?>

<section class="py-3" style="background:linear-gradient(135deg,var(--green-dark),var(--green-mid));color:#fff">
  <div class="container">
    <div class="d-flex align-items-center gap-3">
      <i class="bi bi-shop fs-2" style="color:var(--sand-dark)"></i>
      <div>
        <h1 class="mb-0 fs-3" style="font-family:'Playfair Display',serif">Local Shops</h1>
        <p class="mb-0 small opacity-75">
          <?= number_format($total_count) ?> shop<?= $total_count !== 1 ? 's' : '' ?> in Laguna — milk tea, pasalubong, cafés &amp; more
        </p>
      </div>
    </div>
  </div>
</section>

<!-- Toolbar -->
<div class="spots-toolbar sticky-top" style="top:56px;z-index:100">
  <div class="container">
    <div class="d-flex align-items-center gap-2 flex-wrap py-2 toolbar-scroll-row">

      <!-- Category quick filters -->
      <div class="d-flex gap-1 flex-grow-1 flex-wrap">
        <a href="?<?= http_build_query(array_filter(['city'=>$filter_city,'view'=>$view_mode!=='grid'?$view_mode:''])) ?>"
           class="filter-pill <?= !$filter_category ? 'active' : '' ?>">All</a>
        <?php foreach (shop_categories() as $key => $meta): ?>
        <a href="?<?= http_build_query(array_filter(['city'=>$filter_city,'category'=>$key,'view'=>$view_mode!=='grid'?$view_mode:''])) ?>"
           class="filter-pill <?= $filter_category===$key ? 'active' : '' ?>">
          <i class="bi <?= $meta['icon'] ?> me-1"></i><?= $meta['label'] ?>
        </a>
        <?php endforeach; ?>
      </div>

      <div class="d-flex gap-2 align-items-center ms-auto">
        <select class="form-select form-select-sm" style="width:auto;font-size:.8rem" onchange="applySort(this.value)">
          <option value="name"     <?= $filter_sort==='name'?'selected':'' ?>>Name: A–Z</option>
          <option value="city"     <?= $filter_sort==='city'?'selected':'' ?>>By City</option>
          <option value="category" <?= $filter_sort==='category'?'selected':'' ?>>By Category</option>
        </select>
        <div class="btn-group btn-group-sm" role="group">
          <a href="?<?= http_build_query(array_filter(['city'=>$filter_city,'category'=>$filter_category,'sort'=>$filter_sort!=='name'?$filter_sort:''])) ?>"
             class="btn btn-outline-secondary <?= $view_mode==='grid'?'active':'' ?>" title="Grid view">
            <i class="bi bi-grid-3x3-gap"></i>
          </a>
          <a href="?<?= http_build_query(array_filter(['city'=>$filter_city,'category'=>$filter_category,'sort'=>$filter_sort!=='name'?$filter_sort:'','view'=>'list'])) ?>"
             class="btn btn-outline-secondary <?= $view_mode==='list'?'active':'' ?>" title="List view">
            <i class="bi bi-list-ul"></i>
          </a>
          <a href="?<?= http_build_query(array_filter(['city'=>$filter_city,'category'=>$filter_category,'sort'=>$filter_sort!=='name'?$filter_sort:'','view'=>'map'])) ?>"
             class="btn btn-outline-secondary <?= $view_mode==='map'?'active':'' ?>" title="Map view">
            <i class="bi bi-pin-map"></i>
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
        <i class="bi bi-funnel me-2" style="color:var(--green-light)"></i>Filter Shops
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
          <label class="form-label">Category</label>
          <select class="form-select form-select-sm" name="category">
            <option value="">All Categories</option>
            <?php foreach (shop_categories() as $key => $meta): ?>
            <option value="<?= e($key) ?>" <?= $filter_category===$key?'selected':'' ?>>
              <?= $meta['label'] ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-4">
          <label class="form-label">Sort By</label>
          <select class="form-select form-select-sm" name="sort">
            <option value="name"     <?= $filter_sort==='name'?'selected':'' ?>>Name: A–Z</option>
            <option value="city"     <?= $filter_sort==='city'?'selected':'' ?>>By City</option>
            <option value="category" <?= $filter_sort==='category'?'selected':'' ?>>By Category</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary-app w-100 mb-2">
          <i class="bi bi-search me-2"></i>Apply Filters
        </button>
        <a href="shops.php" class="btn btn-outline-secondary w-100 btn-sm">Clear</a>
      </form>
    </div>

    <!-- Results summary -->
    <div class="mt-3 p-3" style="background:#fff;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:.83rem">
      <div class="fw-bold mb-1" style="color:var(--green-dark)">
        <i class="bi bi-bar-chart-fill me-1"></i>Results
      </div>
      <div class="text-muted">
        Showing <strong style="color:var(--charcoal)"><?= count($shops) ?></strong>
        of <strong style="color:var(--charcoal)"><?= number_format($total_count) ?></strong> shops
        <?php if ($total_pages > 1): ?>
        <br>Page <?= $page ?> of <?= $total_pages ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Shops content -->
  <div class="col-lg-9">
    <?php if ($view_mode === 'map'): ?>

      <?php if (empty($map_shops)): ?>
      <div class="text-center py-5">
        <i class="bi bi-pin-map fs-1 text-muted d-block mb-3"></i>
        <h5>No mappable shops found</h5>
        <p class="text-muted">Try adjusting your filters, or switch back to grid view.</p>
      </div>
      <?php else: ?>
      <div id="shops-map" style="height:600px;border-radius:var(--radius-sm);overflow:hidden"></div>
      <p class="text-muted small mt-2 mb-0">
        <i class="bi bi-info-circle me-1"></i>Showing <?= count($map_shops) ?> shop<?= count($map_shops)!=1?'s':'' ?> matching your filters.
        Pins marked <em>(approx.)</em> use the city center since an exact address hasn't been pinned yet.
      </p>
      <?php endif; ?>

    <?php elseif (empty($shops)): ?>
      <div class="text-center py-5">
        <i class="bi bi-shop fs-1 text-muted d-block mb-3"></i>
        <h5>No shops found</h5>
        <p class="text-muted">Try adjusting your filters.</p>
        <a href="shops.php" class="btn btn-primary-app">View All Shops</a>
      </div>
    <?php else: ?>

    <!-- ── GRID VIEW ── -->
    <?php if ($view_mode !== 'list'): ?>
    <div class="row g-3">
      <?php foreach ($shops as $shop): ?>
      <div class="col-sm-6 col-xl-4">
        <div class="card-app h-100">
          <?php if (!empty($shop['cover_url'])): ?>
          <div class="card-img-placeholder" style="height:130px;padding:0;overflow:hidden">
            <img src="<?= e($shop['cover_url']) ?>" alt="<?= e($shop['name']) ?>" style="width:100%;height:100%;object-fit:cover">
          </div>
          <?php else: ?>
          <div class="card-img-placeholder" style="height:130px;font-size:2.2rem">
            <i class="bi <?= shop_category_icon($shop['category']) ?>"></i>
          </div>
          <?php endif; ?>
          <div class="card-body-app d-flex flex-column">
            <div class="mb-1">
              <span class="badge" style="background:var(--green-pale);color:var(--green-dark);font-size:.72rem">
                <i class="bi <?= shop_category_icon($shop['category']) ?> me-1"></i><?= shop_category_label($shop['category']) ?>
              </span>
            </div>
            <h5 class="card-title-app mb-1" style="font-size:.98rem"><?= e($shop['name']) ?></h5>
            <div class="card-meta mb-1">
              <i class="bi bi-geo-alt text-green"></i>
              <span><?= e($shop['city_name']) ?></span>
            </div>
            <?php if ($shop['address']): ?>
            <div class="mb-2" style="font-size:.75rem;color:var(--text-muted)">
              <i class="bi bi-pin-map me-1"></i><?= e(mb_strimwidth($shop['address'],0,45,'…')) ?>
            </div>
            <?php endif; ?>
            <?php if ($shop['open_time'] && $shop['close_time']): ?>
            <div class="mb-2" style="font-size:.75rem;color:var(--text-muted)">
              <i class="bi bi-clock me-1"></i><?= date('g:i A', strtotime($shop['open_time'])) ?> – <?= date('g:i A', strtotime($shop['close_time'])) ?>
              <?= $shop['open_days'] ? ' · '.e($shop['open_days']) : '' ?>
            </div>
            <?php endif; ?>
            <?php if ($shop['description']): ?>
            <p class="text-muted small mb-3" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">
              <?= e($shop['description']) ?>
            </p>
            <?php endif; ?>
            <div class="mt-auto">
              <a href="shop.php?id=<?= $shop['id'] ?>" class="btn btn-sm btn-primary-app w-100">
                <i class="bi bi-bag-check me-1"></i>View & Order
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
      <?php foreach ($shops as $shop): ?>
      <div class="d-flex align-items-center gap-3 p-3" style="background:#fff;border:1px solid var(--border);border-radius:var(--radius-sm)">
        <div style="width:52px;height:52px;background:var(--green-pale);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.6rem;color:var(--green-mid);flex-shrink:0">
          <i class="bi <?= shop_category_icon($shop['category']) ?>"></i>
        </div>
        <div class="flex-grow-1 min-w-0">
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="fw-bold" style="font-size:.92rem"><?= e($shop['name']) ?></span>
            <span class="badge" style="background:var(--green-pale);color:var(--green-dark);font-size:.68rem">
              <i class="bi <?= shop_category_icon($shop['category']) ?> me-1"></i><?= shop_category_label($shop['category']) ?>
            </span>
          </div>
          <div class="small text-muted">
            <i class="bi bi-geo-alt me-1"></i><?= e($shop['city_name']) ?>
            <?= $shop['address'] ? ' — '.e($shop['address']) : '' ?>
          </div>
        </div>
        <a href="shop.php?id=<?= $shop['id'] ?>" class="btn btn-sm btn-primary-app flex-shrink-0">
          <i class="bi bi-bag-check me-1"></i>View & Order
        </a>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <nav class="mt-4">
      <ul class="pagination justify-content-center">
        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
        <li class="page-item <?= $p===$page?'active':'' ?>">
          <a class="page-link" href="?<?= $base_qs ? $base_qs.'&' : '' ?>p=<?= $p ?>"><?= $p ?></a>
        </li>
        <?php endfor; ?>
      </ul>
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

<?php if ($view_mode === 'map' && !empty($map_shops)): ?>
// ── Map view: pin every filtered shop at once ──────────────────
// Wrapped in DOMContentLoaded: Leaflet's JS loads at the bottom of the
// page (in footer.php), which comes AFTER this script block in document
// order — so we must wait until everything has finished loading.
document.addEventListener('DOMContentLoaded', function() {
  const shopPins = <?= json_encode($map_shops) ?>;
  const shopsMap = L.map('shops-map', { zoomControl: true });
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 18
  }).addTo(shopsMap);

  const shopPinIcon = L.divIcon({
    className: '',
    html: `<div style="width:30px;height:30px;border-radius:50% 50% 50% 0;
             background:var(--terracotta,#c77c48);border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.3);
             transform:rotate(-45deg);display:flex;align-items:center;justify-content:center;">
             <i class="bi bi-shop" style="transform:rotate(45deg);font-size:14px;color:#fff"></i></div>`,
    iconSize: [30, 30], iconAnchor: [15, 30], popupAnchor: [0, -30],
  });

  const shopBounds = [];
  shopPins.forEach(s => {
    shopBounds.push([s.latitude, s.longitude]);
    const approx = s.is_approximate == 1 ? ' <em>(approx.)</em>' : '';
    L.marker([s.latitude, s.longitude], { icon: shopPinIcon })
      .addTo(shopsMap)
      .bindPopup(`
        <div style="min-width:160px">
          <strong>${s.name}</strong><br>
          <span style="font-size:.85rem;color:#666">${s.city_name}${approx}</span><br>
          <a href="<?= APP_URL ?>/pages/shop.php?id=${s.id}"
             style="display:inline-block;margin-top:.4rem;padding:.25rem .7rem;background:var(--terracotta,#c77c48);color:#fff;
                    border-radius:6px;font-size:.78rem;text-decoration:none">View &amp; Order</a>
        </div>
      `);
  });
  shopsMap.fitBounds(shopBounds, { padding: [40, 40] });
  setTimeout(() => shopsMap.invalidateSize(), 200);
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
