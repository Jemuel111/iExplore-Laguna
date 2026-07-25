<?php
// ============================================================
// IEXPLORE LAGUNA — Hotels Page (Enhanced v3)
// pages/hotels.php — Pagination + Sort + Compact layout
// ============================================================
$page_title  = 'Hotels & Resorts';
$active_page = 'hotels';
require_once __DIR__ . '/../includes/header.php';

ensure_hotel_amenities_table();

$filter_city   = input('city',      'get', '');
$filter_stars  = (int) input('stars',     'get', 0);
$filter_budget = (int) input('max_price', 'get', 0);
$filter_sort   = input('sort',      'get', 'stars');
$view_mode     = input('view',      'get', 'grid');

// Amenities come in as a checkbox array (amenities[]=X&amenities[]=Y),
// which the generic input() helper can't handle (it expects scalars).
$filter_amenities = (isset($_GET['amenities']) && is_array($_GET['amenities']))
    ? array_values(array_filter(array_map('strval', $_GET['amenities'])))
    : [];

// Pagination
$per_page = 9;
$page     = max(1, (int) input('p', 'get', 1));

$where  = ['h.is_active = 1', 'h.is_verified = 1'];
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
if (!empty($filter_amenities)) {
    $placeholders = implode(',', array_fill(0, count($filter_amenities), '?'));
    $where[]  = "(SELECT COUNT(DISTINCT label) FROM hotel_amenities WHERE hotel_id = h.id AND label IN ($placeholders)) = ?";
    foreach ($filter_amenities as $a) { $params[] = $a; }
    $params[] = count($filter_amenities);
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

// Map view shows every filtered result at once, not just the current page
$map_hotels = [];
if ($view_mode === 'map') {
    $map_hotels = db_fetch_all(
        "SELECT h.id, h.name, h.star_rating, h.price_min, h.latitude, h.longitude, c.name AS city_name
         FROM hotels h
         JOIN cities c ON h.city_id = c.id
         WHERE {$where_sql} AND h.latitude IS NOT NULL AND h.longitude IS NOT NULL
         ORDER BY {$order_sql}",
        $params
    );
}

$cities = db_fetch_all("SELECT id, name, slug FROM cities ORDER BY name");

// Distinct amenity labels across all hotels, for the filter checkboxes
$all_amenity_labels = db_fetch_all(
    "SELECT DISTINCT label, MIN(icon) AS icon FROM hotel_amenities GROUP BY label ORDER BY label ASC"
);

// Batch-fetch amenities for just the hotels shown on this page (avoids
// running a query per card)
$hotel_amenities_map = [];
$hotel_main_photo_map = [];
if (!empty($hotels)) {
    $hotelIds = array_column($hotels, 'id');
    $placeholders = implode(',', array_fill(0, count($hotelIds), '?'));
    $rows = db_fetch_all(
        "SELECT hotel_id, label, icon FROM hotel_amenities WHERE hotel_id IN ($placeholders) ORDER BY id ASC",
        $hotelIds
    );
    foreach ($rows as $row) {
        $hotel_amenities_map[$row['hotel_id']][] = $row;
    }

    // Prefer a photo uploaded via "Manage Hotel Photos" over the legacy
    // cover_url field — that's the field editors actually use now, but
    // listing cards were still only checking cover_url, so uploads there
    // never showed up until you clicked into the hotel.
    ensure_hotel_photos_table();
    $photoRows = db_fetch_all(
        "SELECT hotel_id, url FROM hotel_photos
         WHERE hotel_id IN ($placeholders)
         ORDER BY photo_type='main' DESC, sort_order ASC, id ASC",
        $hotelIds
    );
    foreach ($photoRows as $row) {
        if (!isset($hotel_main_photo_map[$row['hotel_id']])) {
            $hotel_main_photo_map[$row['hotel_id']] = $row['url'];
        }
    }
}

$base_qs = http_build_query(array_filter([
    'city' => $filter_city, 'stars' => $filter_stars ?: '',
    'max_price' => $filter_budget ?: '',
    'sort' => $filter_sort !== 'stars' ? $filter_sort : '',
    'view' => $view_mode !== 'grid' ? $view_mode : '',
    'amenities' => $filter_amenities,
]));
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
    <div class="d-flex align-items-center gap-2 flex-wrap py-2 toolbar-scroll-row">

      <!-- Star quick filters -->
      <div class="d-flex gap-1 flex-grow-1 flex-wrap">
        <a href="?<?= http_build_query(array_filter(['city'=>$filter_city,'max_price'=>$filter_budget?:'','view'=>$view_mode!=='grid'?$view_mode:'','amenities'=>$filter_amenities])) ?>"
           class="filter-pill <?= !$filter_stars ? 'active' : '' ?>">All Stars</a>
        <?php for ($s = 5; $s >= 1; $s--): ?>
        <a href="?<?= http_build_query(array_filter(['city'=>$filter_city,'stars'=>$s,'max_price'=>$filter_budget?:'','view'=>$view_mode!=='grid'?$view_mode:'','amenities'=>$filter_amenities])) ?>"
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
          <a href="?<?= http_build_query(array_filter(['city'=>$filter_city,'stars'=>$filter_stars?:'','max_price'=>$filter_budget?:'','sort'=>$filter_sort!=='stars'?$filter_sort:'','amenities'=>$filter_amenities])) ?>"
             class="btn btn-outline-secondary <?= $view_mode==='grid'?'active':'' ?>" title="Grid view">
            <i class="bi bi-grid-3x3-gap"></i>
          </a>
          <a href="?<?= http_build_query(array_filter(['city'=>$filter_city,'stars'=>$filter_stars?:'','max_price'=>$filter_budget?:'','sort'=>$filter_sort!=='stars'?$filter_sort:'','view'=>'list','amenities'=>$filter_amenities])) ?>"
             class="btn btn-outline-secondary <?= $view_mode==='list'?'active':'' ?>" title="List view">
            <i class="bi bi-list-ul"></i>
          </a>
          <a href="?<?= http_build_query(array_filter(['city'=>$filter_city,'stars'=>$filter_stars?:'','max_price'=>$filter_budget?:'','sort'=>$filter_sort!=='stars'?$filter_sort:'','view'=>'map','amenities'=>$filter_amenities])) ?>"
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
        <?php if (!empty($all_amenity_labels)): ?>
        <div class="mb-3">
          <label class="form-label">Amenities</label>
          <div style="max-height:160px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;padding:.6rem .75rem">
            <?php foreach ($all_amenity_labels as $al): ?>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="amenities[]"
                     value="<?= e($al['label']) ?>" id="am-<?= md5($al['label']) ?>"
                     <?= in_array($al['label'], $filter_amenities, true) ? 'checked' : '' ?>>
              <label class="form-check-label small" for="am-<?= md5($al['label']) ?>">
                <i class="bi <?= e($al['icon']) ?> me-1" style="color:var(--green-mid)"></i><?= e($al['label']) ?>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
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
    <?php if ($view_mode === 'map'): ?>

      <?php if (empty($map_hotels)): ?>
      <div class="text-center py-5">
        <i class="bi bi-pin-map fs-1 text-muted d-block mb-3"></i>
        <h5>No mappable hotels found</h5>
        <p class="text-muted">Try adjusting your filters, or switch back to grid view.</p>
      </div>
      <?php else: ?>
      <div id="hotels-map" style="height:600px;border-radius:var(--radius-sm);overflow:hidden"></div>
      <p class="text-muted small mt-2 mb-0">
        <i class="bi bi-info-circle me-1"></i>Showing <?= count($map_hotels) ?> hotel<?= count($map_hotels)!=1?'s':'' ?> matching your filters.
      </p>
      <?php endif; ?>

    <?php elseif (empty($hotels)): ?>
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
        $amenities = $hotel_amenities_map[$hotel['id']] ?? [];
      ?>
      <div class="col-sm-6 col-xl-4">
        <div class="card-app h-100">
          <a href="hotel.php?id=<?= $hotel['id'] ?>" class="text-decoration-none">
          <?php $cardImg = $hotel_main_photo_map[$hotel['id']] ?? $hotel['cover_url'] ?? ''; ?>
          <?php if (!empty($cardImg)): ?>
          <div class="card-img-placeholder" style="height:130px;padding:0;overflow:hidden">
            <img src="<?= e($cardImg) ?>" alt="<?= e($hotel['name']) ?>" style="width:100%;height:100%;object-fit:cover">
          </div>
          <?php else: ?>
          <div class="card-img-placeholder" style="height:130px;font-size:2.2rem">🏨</div>
          <?php endif; ?>
          </a>
          <div class="card-body-app d-flex flex-column">
            <div class="mb-1" style="color:var(--sand-dark);font-size:.85rem">
              <?= str_repeat('★', $hotel['star_rating']) . str_repeat('☆', 5 - $hotel['star_rating']) ?>
              <small class="text-muted ms-1"><?= $hotel['star_rating'] ?>-star</small>
            </div>
            <a href="hotel.php?id=<?= $hotel['id'] ?>" class="text-decoration-none" style="color:inherit">
              <h5 class="card-title-app mb-1" style="font-size:.98rem"><?= e($hotel['name']) ?></h5>
            </a>
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
              <?php foreach (array_slice($amenities,0,4) as $a): ?>
                <span style="font-size:.68rem;background:var(--green-pale);color:var(--green-dark);padding:.15rem .5rem;border-radius:20px">
                  <i class="bi <?= e($a['icon']) ?> me-1"></i><?= e($a['label']) ?>
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
              <a href="hotel.php?id=<?= $hotel['id'] ?>" class="btn btn-sm" style="padding:.3rem .9rem;background:#8e2434;color:#fff;border-radius:var(--radius-pill)">
                <i class="bi bi-calendar-check me-1"></i>Book
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
      <?php foreach ($hotels as $hotel):
        $amenities = $hotel_amenities_map[$hotel['id']] ?? [];
      ?>
      <div class="spot-list-item">
        <?php $cardImg = $hotel_main_photo_map[$hotel['id']] ?? $hotel['cover_url'] ?? ''; ?>
        <?php if (!empty($cardImg)): ?>
        <div class="spot-emoji-box" style="padding:0;overflow:hidden">
          <img src="<?= e($cardImg) ?>" alt="<?= e($hotel['name']) ?>" style="width:100%;height:100%;object-fit:cover">
        </div>
        <?php else: ?>
        <div class="spot-emoji-box" style="font-size:1.6rem">🏨</div>
        <?php endif; ?>
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
              <a href="hotel.php?id=<?= $hotel['id'] ?>" class="btn btn-sm" style="padding:.3rem .8rem;font-size:.78rem;background:#8e2434;color:#fff;border-radius:var(--radius-pill)">
                <i class="bi bi-calendar-check me-1"></i>Book
              </a>
            </div>
          </div>
          <?php if ($amenities): ?>
          <div class="d-flex flex-wrap gap-1 mt-1">
            <?php foreach (array_slice($amenities,0,5) as $a): ?>
              <span style="font-size:.68rem;background:var(--green-pale);color:var(--green-dark);padding:.12rem .45rem;border-radius:20px">
                <i class="bi <?= e($a['icon']) ?> me-1"></i><?= e($a['label']) ?>
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

<?php if ($view_mode === 'map' && !empty($map_hotels)): ?>
// ── Map view: pin every filtered hotel at once ────────────────
// Wrapped in DOMContentLoaded: Leaflet's JS loads at the bottom of the
// page (in footer.php), which comes AFTER this script block in document
// order — so we must wait until everything has finished loading.
document.addEventListener('DOMContentLoaded', function() {
  const hotelPins = <?= json_encode($map_hotels) ?>;
  const hotelsMap = L.map('hotels-map', { zoomControl: true });
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 18
  }).addTo(hotelsMap);

  const hotelPinIcon = L.divIcon({
    className: '',
    html: `<div style="width:30px;height:30px;border-radius:50% 50% 50% 0;
             background:#8e2434;border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.3);
             transform:rotate(-45deg);display:flex;align-items:center;justify-content:center;">
             <span style="transform:rotate(45deg);font-size:14px">🏨</span></div>`,
    iconSize: [30, 30], iconAnchor: [15, 30], popupAnchor: [0, -30],
  });

  const hotelBounds = [];
  hotelPins.forEach(h => {
    hotelBounds.push([h.latitude, h.longitude]);
    const stars = '★'.repeat(h.star_rating || 0);
    const price = h.price_min ? '₱' + Number(h.price_min).toLocaleString() + '+/night' : 'Price varies';
    L.marker([h.latitude, h.longitude], { icon: hotelPinIcon })
      .addTo(hotelsMap)
      .bindPopup(`
        <div style="min-width:160px">
          <strong>${h.name}</strong><br>
          <span style="color:#e9c46a">${stars}</span><br>
          <span style="font-size:.85rem;color:#666">${h.city_name} · ${price}</span><br>
          <a href="<?= APP_URL ?>/pages/hotel.php?id=${h.id}"
             style="display:inline-block;margin-top:.4rem;padding:.25rem .7rem;background:#8e2434;color:#fff;
                    border-radius:6px;font-size:.78rem;text-decoration:none">View & Book</a>
        </div>
      `);
  });
  hotelsMap.fitBounds(hotelBounds, { padding: [40, 40] });
  setTimeout(() => hotelsMap.invalidateSize(), 200);
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
