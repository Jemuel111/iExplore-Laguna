<?php
// ============================================================
// iEXPLORE LAGUNA — Explore & Build Itinerary Page
// pages/explore.php
// Browse all spots + hotels, add to cart, generate itinerary
// ============================================================
$page_title  = 'Explore & Plan';
$active_page = 'explore';
require_once __DIR__ . '/../includes/header.php';

// Fetch all cities for filter
$cities = db_fetch_all("SELECT id, name, slug FROM cities ORDER BY name");

// Fetch all active spots
$spots = db_fetch_all(
    "SELECT s.id, s.name, s.category, s.rating, s.entrance_fee, s.description,
            s.operating_hours, c.name AS city_name, c.id AS city_id, 'spot' AS item_type
     FROM tourist_spots s
     JOIN cities c ON s.city_id = c.id
     WHERE s.is_active = 1
     ORDER BY s.rating DESC, s.name ASC"
);

// Fetch all active hotels
$hotels = db_fetch_all(
    "SELECT h.id, h.name, h.star_rating, h.price_min, h.price_max,
            h.address, h.phone, c.name AS city_name, c.id AS city_id, 'hotel' AS item_type
     FROM hotels h
     JOIN cities c ON h.city_id = c.id
     WHERE h.is_active = 1
     ORDER BY h.star_rating DESC, h.price_min ASC"
);

$catLabels = [
    'nature'=>'Nature','heritage'=>'Heritage','waterfall'=>'Waterfall',
    'hotspring'=>'Hot Spring','museum'=>'Museum','religious'=>'Religious',
    'beach_lake'=>'Lake/Beach','adventure'=>'Adventure','food'=>'Food',
];
$catEmojis = [
    'nature'=>'🌿','heritage'=>'🏛️','waterfall'=>'💧','hotspring'=>'♨️',
    'museum'=>'🏺','religious'=>'⛪','beach_lake'=>'🏞️','adventure'=>'🧗','food'=>'🍜',
];
$catColors = [
    'nature'=>['#d8f3dc','#1a3a2a'],'heritage'=>['#fef3c7','#92400e'],
    'waterfall'=>['#dbeafe','#1e40af'],'hotspring'=>['#ffe4e6','#9f1239'],
    'museum'=>['#f3e8ff','#6b21a8'],'religious'=>['#fff7ed','#9a3412'],
    'beach_lake'=>['#e0f2fe','#075985'],'adventure'=>['#fef9c3','#713f12'],
    'food'=>['#fce7f3','#9d174d'],
];
?>

<!-- Page hero -->
<section class="py-3" style="background:linear-gradient(135deg,var(--green-dark),var(--green-mid));color:#fff">
  <div class="container">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
      <div class="d-flex align-items-center gap-3">
        <i class="bi bi-basket3-fill fs-2" style="color:var(--sand-dark)"></i>
        <div>
          <h1 class="mb-0 fs-3" style="font-family:'Playfair Display',serif">Explore &amp; Plan</h1>
          <p class="mb-0 small opacity-75">Pick spots &amp; hotels, then generate your perfect itinerary</p>
        </div>
      </div>
      <!-- Cart button (shown on mobile too) -->
      <button class="btn d-flex align-items-center gap-2" id="open-cart-btn"
              style="background:var(--sand-dark);color:var(--green-dark);font-weight:700;border-radius:var(--radius-pill);padding:.5rem 1.2rem">
        <i class="bi bi-basket3-fill"></i>
        My List
        <span id="cart-count-badge"
              style="background:var(--green-dark);color:#fff;border-radius:50%;width:22px;height:22px;font-size:.75rem;display:inline-flex;align-items:center;justify-content:center;font-weight:700">
          0
        </span>
      </button>
    </div>
  </div>
</section>

<!-- Tab + filter toolbar -->
<div class="spots-toolbar sticky-top" style="top:56px;z-index:100">
  <div class="container">
    <div class="d-flex align-items-center gap-2 flex-wrap py-2">

      <!-- Tab pills -->
      <div class="d-flex gap-1" id="type-tabs">
        <button class="filter-pill active" data-tab="all">🗺️ All</button>
        <button class="filter-pill" data-tab="spot">📍 Spots</button>
        <button class="filter-pill" data-tab="hotel">🏨 Hotels</button>
      </div>

      <div class="vr d-none d-md-block mx-1" style="opacity:.2"></div>

      <!-- Category quick filter (spots only) -->
      <div class="d-flex gap-1 flex-wrap" id="cat-filters">
        <button class="filter-pill active" data-cat="">All Categories</button>
        <?php foreach ($catLabels as $key => $label): ?>
        <button class="filter-pill" data-cat="<?= $key ?>"><?= $catEmojis[$key] ?> <?= $label ?></button>
        <?php endforeach; ?>
      </div>

      <div class="ms-auto d-flex gap-2 align-items-center">
        <!-- City filter -->
        <select id="city-filter" class="form-select form-select-sm" style="width:auto;font-size:.8rem">
          <option value="">All Cities</option>
          <?php foreach ($cities as $c): ?>
          <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <!-- Search -->
        <div class="position-relative">
          <i class="bi bi-search position-absolute" style="left:.65rem;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:.8rem;pointer-events:none"></i>
          <input type="text" id="explore-search" class="form-control form-control-sm"
                 placeholder="Search…" style="padding-left:2rem;width:160px;font-size:.82rem">
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Main layout: grid + cart sidebar -->
<div class="container py-4">
<div class="row g-4">

  <!-- ── Items Grid ────────────────────────────────────────── -->
  <div class="col-lg-8 col-xl-9" id="explore-grid-col">

    <!-- Stats row -->
    <div class="d-flex align-items-center justify-content-between mb-3">
      <span class="text-muted small" id="results-label">
        Showing <strong style="color:var(--green-dark)" id="results-count">0</strong> items
      </span>
      <div class="d-flex gap-2 align-items-center">
        <label class="form-check form-switch mb-0 d-flex align-items-center gap-2 small" style="cursor:pointer">
          <input class="form-check-input" type="checkbox" id="free-only-toggle" style="cursor:pointer">
          <span>Free entry only</span>
        </label>
      </div>
    </div>

    <!-- Items grid -->
    <div class="row g-3" id="explore-grid">

      <?php foreach ($spots as $spot):
        [$bg,$fg] = $catColors[$spot['category']] ?? ['#f1f5f9','#334155'];
      ?>
      <div class="col-sm-6 col-xl-4 explore-item"
           data-type="spot"
           data-id="<?= $spot['id'] ?>"
           data-city="<?= $spot['city_id'] ?>"
           data-cat="<?= e($spot['category']) ?>"
           data-fee="<?= (float)$spot['entrance_fee'] ?>"
           data-name="<?= e(strtolower($spot['name'])) ?>"
           data-cityname="<?= e(strtolower($spot['city_name'])) ?>">
        <div class="explore-card h-100" data-id="spot-<?= $spot['id'] ?>">
          <!-- Image placeholder -->
          <div class="explore-card-img" style="background:<?= $bg ?>">
            <span class="explore-emoji"><?= $catEmojis[$spot['category']] ?? '📍' ?></span>
            <!-- Add to cart btn -->
            <button class="add-to-cart-btn" onclick="toggleCart('spot',<?= $spot['id'] ?>,'<?= e(addslashes($spot['name'])) ?>','<?= e($spot['city_name']) ?>',<?= (float)$spot['entrance_fee'] ?>,'spot')"
                    data-key="spot-<?= $spot['id'] ?>" title="Add to My List">
              <i class="bi bi-plus-lg"></i>
            </button>
          </div>
          <div class="explore-card-body">
            <div class="mb-1">
              <span class="cat-badge" style="background:<?= $bg ?>;color:<?= $fg ?>">
                <?= $catLabels[$spot['category']] ?? $spot['category'] ?>
              </span>
            </div>
            <h6 class="explore-card-title"><?= e($spot['name']) ?></h6>
            <div class="explore-card-meta">
              <i class="bi bi-geo-alt text-green"></i>
              <span><?= e($spot['city_name']) ?></span>
              <span>·</span>
              <span style="color:var(--sand-dark)">★ <?= number_format($spot['rating'],1) ?></span>
            </div>
            <?php if ($spot['operating_hours']): ?>
            <div class="explore-card-meta mt-1">
              <i class="bi bi-clock text-muted"></i>
              <span class="text-muted" style="font-size:.74rem"><?= e(mb_strimwidth($spot['operating_hours'],0,35,'…')) ?></span>
            </div>
            <?php endif; ?>
            <div class="explore-card-footer">
              <span class="explore-price <?= $spot['entrance_fee'] > 0 ? 'paid' : 'free' ?>">
                <?= $spot['entrance_fee'] > 0 ? '₱'.number_format($spot['entrance_fee'],0) : '🎉 Free' ?>
              </span>
              <button class="btn-add-list" onclick="toggleCart('spot',<?= $spot['id'] ?>,'<?= e(addslashes($spot['name'])) ?>','<?= e($spot['city_name']) ?>',<?= (float)$spot['entrance_fee'] ?>,'spot')"
                      data-key="spot-<?= $spot['id'] ?>">
                <i class="bi bi-plus-lg me-1"></i>Add
              </button>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>

      <?php foreach ($hotels as $hotel): ?>
      <div class="col-sm-6 col-xl-4 explore-item"
           data-type="hotel"
           data-id="<?= $hotel['id'] ?>"
           data-city="<?= $hotel['city_id'] ?>"
           data-cat=""
           data-fee="<?= (float)$hotel['price_min'] ?>"
           data-name="<?= e(strtolower($hotel['name'])) ?>"
           data-cityname="<?= e(strtolower($hotel['city_name'])) ?>">
        <div class="explore-card h-100" data-id="hotel-<?= $hotel['id'] ?>">
          <div class="explore-card-img" style="background:#e8f4f8">
            <span class="explore-emoji">🏨</span>
            <button class="add-to-cart-btn" onclick="toggleCart('hotel',<?= $hotel['id'] ?>,'<?= e(addslashes($hotel['name'])) ?>','<?= e($hotel['city_name']) ?>',<?= (float)$hotel['price_min'] ?>,'hotel')"
                    data-key="hotel-<?= $hotel['id'] ?>" title="Add to My List">
              <i class="bi bi-plus-lg"></i>
            </button>
          </div>
          <div class="explore-card-body">
            <div class="mb-1" style="color:var(--sand-dark);font-size:.82rem">
              <?= str_repeat('★',$hotel['star_rating']) . str_repeat('☆',5-$hotel['star_rating']) ?>
            </div>
            <h6 class="explore-card-title"><?= e($hotel['name']) ?></h6>
            <div class="explore-card-meta">
              <i class="bi bi-geo-alt text-green"></i>
              <span><?= e($hotel['city_name']) ?></span>
            </div>
            <div class="explore-card-footer">
              <span class="explore-price paid">
                ₱<?= number_format($hotel['price_min'],0) ?><span style="font-size:.7rem;color:var(--text-muted)">/night</span>
              </span>
              <button class="btn-add-list" onclick="toggleCart('hotel',<?= $hotel['id'] ?>,'<?= e(addslashes($hotel['name'])) ?>','<?= e($hotel['city_name']) ?>',<?= (float)$hotel['price_min'] ?>,'hotel')"
                      data-key="hotel-<?= $hotel['id'] ?>">
                <i class="bi bi-plus-lg me-1"></i>Add
              </button>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>

    </div><!-- /explore-grid -->

    <!-- Empty state -->
    <div id="empty-state" class="text-center py-5 d-none">
      <i class="bi bi-search fs-1 text-muted d-block mb-3"></i>
      <h5 class="fw-bold">No results found</h5>
      <p class="text-muted">Try adjusting your search or filters.</p>
    </div>

  </div><!-- /col -->

  <!-- ── Cart Sidebar (desktop) ────────────────────────────── -->
  <div class="col-lg-4 col-xl-3 d-none d-lg-block">
    <div class="cart-sidebar" id="cart-sidebar-desktop">
      <?php include __DIR__ . '/../includes/cart_panel.php'; ?>
    </div>
  </div>

</div>
</div>

<!-- ── Cart Offcanvas (mobile) ────────────────────────────── -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="cart-offcanvas" style="max-width:380px">
  <div class="offcanvas-header" style="background:var(--green-dark);color:#fff">
    <h5 class="offcanvas-title" style="font-family:'Playfair Display',serif">
      <i class="bi bi-basket3-fill me-2" style="color:var(--sand-dark)"></i>My List
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body p-0">
    <?php include __DIR__ . '/../includes/cart_panel.php'; ?>
  </div>
</div>

<!-- ── Itinerary Modal ────────────────────────────────────── -->
<div class="modal fade" id="itinerary-modal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content" style="border-radius:var(--radius);overflow:hidden">
      <div class="modal-header" style="background:linear-gradient(135deg,var(--green-dark),var(--green-mid));color:#fff;border:none">
        <h5 class="modal-title" style="font-family:'Playfair Display',serif">
          <i class="bi bi-calendar2-week me-2" style="color:var(--sand-dark)"></i>
          <span id="modal-title-text">Generated Itinerary</span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0" id="itinerary-modal-body">
        <!-- filled by JS -->
      </div>
      <div class="modal-footer" style="border-top:1px solid var(--border);background:var(--cream)">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-sm" style="background:var(--green-mid);color:#fff"
                onclick="printItinerary()">
          <i class="bi bi-printer me-1"></i>Print
        </button>
        <button class="btn btn-sm" style="background:var(--sand-dark);color:var(--green-dark);font-weight:700"
                onclick="saveItinerary()">
          <i class="bi bi-floppy me-1"></i>Save Itinerary
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ── Styles ─────────────────────────────────────────────── -->
<style>
/* Cards */
.explore-card {
  background:#fff;
  border:1.5px solid var(--border);
  border-radius:var(--radius);
  overflow:hidden;
  transition:transform .22s ease, box-shadow .22s ease, border-color .22s ease;
  cursor:default;
  position:relative;
}
.explore-card:hover {
  transform:translateY(-4px);
  box-shadow:var(--shadow-md);
  border-color:var(--green-light);
}
.explore-card.in-cart {
  border-color:var(--green-mid);
  box-shadow:0 0 0 3px rgba(45,106,79,.15);
}
.explore-card-img {
  height:120px;
  display:flex;
  align-items:center;
  justify-content:center;
  position:relative;
}
.explore-emoji { font-size:2.8rem; }
.add-to-cart-btn {
  position:absolute;
  top:.5rem;
  right:.5rem;
  width:32px;
  height:32px;
  border-radius:50%;
  border:none;
  background:rgba(255,255,255,.9);
  color:var(--green-mid);
  font-size:1rem;
  display:flex;
  align-items:center;
  justify-content:center;
  cursor:pointer;
  transition:all .18s ease;
  box-shadow:var(--shadow-sm);
}
.add-to-cart-btn:hover, .explore-card.in-cart .add-to-cart-btn {
  background:var(--green-mid);
  color:#fff;
}
.explore-card.in-cart .add-to-cart-btn i::before { content:"\F62B"; } /* bi-check-lg */
.explore-card-body { padding:.85rem; }
.explore-card-title {
  font-family:'Playfair Display',serif;
  font-size:.93rem;
  font-weight:700;
  margin-bottom:.3rem;
  color:var(--charcoal);
  display:-webkit-box;
  -webkit-line-clamp:2;
  -webkit-box-orient:vertical;
  overflow:hidden;
}
.explore-card-meta {
  display:flex;
  align-items:center;
  gap:.3rem;
  font-size:.78rem;
  color:var(--text-muted);
}
.explore-card-footer {
  display:flex;
  align-items:center;
  justify-content:space-between;
  margin-top:.75rem;
  padding-top:.65rem;
  border-top:1px solid var(--border);
}
.explore-price { font-weight:700; font-size:.88rem; }
.explore-price.free { color:#16a34a; }
.explore-price.paid { color:var(--terracotta); }
.cat-badge {
  font-size:.68rem;
  font-weight:700;
  text-transform:uppercase;
  letter-spacing:.06em;
  padding:.15rem .6rem;
  border-radius:20px;
}
.btn-add-list {
  font-size:.78rem;
  font-weight:600;
  padding:.28rem .75rem;
  border-radius:var(--radius-pill);
  border:1.5px solid var(--green-mid);
  color:var(--green-mid);
  background:transparent;
  cursor:pointer;
  transition:all .18s ease;
  white-space:nowrap;
}
.btn-add-list:hover {
  background:var(--green-mid);
  color:#fff;
}
.explore-card.in-cart .btn-add-list {
  background:var(--green-mid);
  color:#fff;
}
.explore-card.in-cart .btn-add-list i::before { content:"\F62B"; }
.explore-card.in-cart .btn-add-list::after { content:' Added'; }
.explore-card.in-cart .btn-add-list i { display:none; }

/* Cart sidebar */
.cart-sidebar {
  position:sticky;
  top:110px;
  background:#fff;
  border:1.5px solid var(--border);
  border-radius:var(--radius);
  overflow:hidden;
  max-height:calc(100vh - 130px);
  display:flex;
  flex-direction:column;
}

/* Cart panel (shared desktop + offcanvas) */
.cart-header {
  background:linear-gradient(135deg,var(--green-dark),var(--green-mid));
  color:#fff;
  padding:1rem 1.25rem .85rem;
}
.cart-items-list {
  flex:1;
  overflow-y:auto;
  padding:.75rem;
  min-height:80px;
}
.cart-item {
  display:flex;
  align-items:flex-start;
  gap:.65rem;
  padding:.6rem .75rem;
  border-radius:var(--radius-sm);
  border:1px solid var(--border);
  margin-bottom:.5rem;
  background:var(--cream);
  animation: fadeUp .2s ease both;
}
.cart-item-icon {
  width:36px;
  height:36px;
  border-radius:8px;
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:1.2rem;
  flex-shrink:0;
}
.cart-item-info { flex:1; min-width:0; }
.cart-item-name {
  font-size:.83rem;
  font-weight:600;
  color:var(--charcoal);
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}
.cart-item-sub { font-size:.74rem; color:var(--text-muted); }
.cart-item-remove {
  background:none;
  border:none;
  color:var(--text-muted);
  cursor:pointer;
  padding:.1rem .3rem;
  border-radius:4px;
  font-size:.9rem;
  flex-shrink:0;
  transition:color .18s;
}
.cart-item-remove:hover { color:#dc2626; }
.cart-footer {
  padding:.85rem 1rem;
  border-top:1px solid var(--border);
  background:var(--cream);
}
.cart-empty-msg {
  text-align:center;
  padding:1.5rem .75rem;
  color:var(--text-muted);
}
.cart-empty-msg i { font-size:2rem; display:block; margin-bottom:.5rem; }

/* Itinerary modal */
.itinerary-day {
  border-bottom:1px solid var(--border);
}
.itinerary-day:last-child { border-bottom:none; }
.itinerary-day-header {
  background:var(--green-pale);
  padding:.7rem 1.25rem;
  font-weight:700;
  color:var(--green-dark);
  font-family:'Playfair Display',serif;
  font-size:1rem;
}
.itinerary-row {
  display:flex;
  gap:1rem;
  padding:.75rem 1.25rem;
  border-bottom:1px solid var(--border);
  align-items:flex-start;
}
.itinerary-row:last-child { border-bottom:none; }
.itinerary-time {
  min-width:65px;
  font-size:.8rem;
  font-weight:700;
  color:var(--green-mid);
  padding-top:.1rem;
}
.itinerary-icon {
  width:36px;
  height:36px;
  border-radius:8px;
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:1.3rem;
  flex-shrink:0;
}
.itinerary-info { flex:1; }
.itinerary-name { font-weight:600; font-size:.92rem; color:var(--charcoal); }
.itinerary-sub { font-size:.78rem; color:var(--text-muted); margin-top:.1rem; }
.itinerary-cost {
  font-size:.8rem;
  font-weight:700;
  color:var(--terracotta);
  white-space:nowrap;
}

@media print {
  nav, .spots-toolbar, .cart-sidebar, #open-cart-btn, .modal-footer { display:none !important; }
  .modal { position:static !important; }
  .modal-dialog { max-width:100% !important; margin:0 !important; }
}
</style>

<!-- ── JavaScript ─────────────────────────────────────────── -->
<script>
// ── Cart State ────────────────────────────────────────────────
let cart = JSON.parse(localStorage.getItem('iexplore_cart') || '[]');

// ── Filter State ─────────────────────────────────────────────
let activeTab = 'all';   // all | spot | hotel
let activeCat = '';
let activeCity = '';
let searchQ    = '';
let freeOnly   = false;

// ── Init ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  restoreCartUI();
  filterItems();

  // Tab pills
  document.querySelectorAll('[data-tab]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('[data-tab]').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      activeTab = btn.dataset.tab;
      // hide cat filters when hotel-only
      document.getElementById('cat-filters').style.display =
        activeTab === 'hotel' ? 'none' : '';
      filterItems();
    });
  });

  // Category pills
  document.querySelectorAll('[data-cat]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('[data-cat]').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      activeCat = btn.dataset.cat;
      filterItems();
    });
  });

  // City filter
  document.getElementById('city-filter').addEventListener('change', e => {
    activeCity = e.target.value;
    filterItems();
  });

  // Search
  document.getElementById('explore-search').addEventListener('input', e => {
    searchQ = e.target.value.toLowerCase().trim();
    filterItems();
  });

  // Free only
  document.getElementById('free-only-toggle').addEventListener('change', e => {
    freeOnly = e.target.checked;
    filterItems();
  });

  // Open cart (mobile)
  document.getElementById('open-cart-btn').addEventListener('click', () => {
    const oc = new bootstrap.Offcanvas(document.getElementById('cart-offcanvas'));
    oc.show();
  });
});

// ── Filter & hide items ───────────────────────────────────────
function filterItems() {
  const items = document.querySelectorAll('.explore-item');
  let visible = 0;
  items.forEach(el => {
    const t    = el.dataset.type;
    const cat  = el.dataset.cat;
    const city = el.dataset.city;
    const fee  = parseFloat(el.dataset.fee);
    const name = el.dataset.name;
    const cn   = el.dataset.cityname;

    const matchTab  = activeTab === 'all' || t === activeTab;
    const matchCat  = !activeCat  || cat === activeCat;
    const matchCity = !activeCity || city === activeCity;
    const matchFree = !freeOnly   || fee === 0;
    const matchQ    = !searchQ    || name.includes(searchQ) || cn.includes(searchQ);

    const show = matchTab && matchCat && matchCity && matchFree && matchQ;
    el.style.display = show ? '' : 'none';
    if (show) visible++;
  });

  document.getElementById('results-count').textContent = visible;
  document.getElementById('empty-state').classList.toggle('d-none', visible > 0);
}

// ── Cart: toggle add/remove ───────────────────────────────────
function toggleCart(type, id, name, city, price, icon) {
  const key = type + '-' + id;
  const idx = cart.findIndex(i => i.key === key);
  if (idx >= 0) {
    cart.splice(idx, 1);
  } else {
    cart.push({ key, type, id, name, city, price });
  }
  saveCart();
  restoreCartUI();
}

function saveCart() {
  localStorage.setItem('iexplore_cart', JSON.stringify(cart));
}

function restoreCartUI() {
  // Update card states
  document.querySelectorAll('.explore-card').forEach(card => {
    const key = card.dataset.id;
    card.classList.toggle('in-cart', cart.some(i => i.key === key));
  });

  // Update badge
  const count = cart.length;
  document.getElementById('cart-count-badge').textContent = count;

  // Render cart lists (desktop + offcanvas share same include, update both)
  renderCartPanel('cart-panel-desktop');
  renderCartPanel('cart-panel-offcanvas');
}

function renderCartPanel(panelId) {
  const panel = document.getElementById(panelId);
  if (!panel) return;

  const listEl  = panel.querySelector('.cart-items-list');
  const totalEl = panel.querySelector('.cart-total-cost');
  const countEl = panel.querySelector('.cart-item-count');

  if (!listEl) return;

  if (cart.length === 0) {
    listEl.innerHTML = `
      <div class="cart-empty-msg">
        <i class="bi bi-basket3"></i>
        <p class="mb-0 small">Nothing added yet.<br>Browse spots &amp; hotels above.</p>
      </div>`;
  } else {
    listEl.innerHTML = cart.map(item => {
      const emoji = item.type === 'hotel' ? '🏨'
        : (item.icon || '📍');
      const priceStr = item.price > 0
        ? (item.type === 'hotel' ? '₱'+Number(item.price).toLocaleString()+'/night' : '₱'+Number(item.price).toLocaleString())
        : (item.type === 'hotel' ? 'Price varies' : 'Free');
      return `
        <div class="cart-item">
          <div class="cart-item-icon" style="background:${item.type==='hotel'?'#e8f4f8':'var(--green-pale)'}">
            ${emoji}
          </div>
          <div class="cart-item-info">
            <div class="cart-item-name">${item.name}</div>
            <div class="cart-item-sub">${item.city} · ${priceStr}</div>
          </div>
          <button class="cart-item-remove" onclick="toggleCart('${item.type}',${item.id},'${item.name.replace(/'/g,"\\'")}','${item.city}',${item.price},'${item.type}')" title="Remove">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>`;
    }).join('');
  }

  if (countEl) countEl.textContent = cart.length + ' item' + (cart.length !== 1 ? 's' : '');

  // Total estimate
  const total = cart.reduce((s, i) => s + (parseFloat(i.price) || 0), 0);
  if (totalEl) totalEl.textContent = total > 0 ? '₱' + total.toLocaleString() : '—';
}

// ── Generate Itinerary ────────────────────────────────────────
function generateItinerary() {
  if (cart.length === 0) {
    alert('Add at least one spot or hotel to your list first!');
    return;
  }

  // Separate spots and hotels
  const spots  = cart.filter(i => i.type === 'spot');
  const hotels = cart.filter(i => i.type === 'hotel');

  // Group spots by city for logical day ordering
  const cityGroups = {};
  spots.forEach(s => {
    if (!cityGroups[s.city]) cityGroups[s.city] = [];
    cityGroups[s.city].push(s);
  });

  // Build days — max 3 spots per day
  const days = [];
  let dayNum = 1;
  const cityList = Object.keys(cityGroups);

  cityList.forEach(city => {
    const citySpots = cityGroups[city];
    // chunk into groups of 3
    for (let i = 0; i < citySpots.length; i += 3) {
      days.push({
        day: dayNum++,
        city,
        spots: citySpots.slice(i, i + 3),
        hotel: null,
      });
    }
  });

  // Assign hotels — last hotel on the last day of each city if available
  if (hotels.length) {
    hotels.forEach((h, idx) => {
      if (days[idx]) days[idx].hotel = h;
    });
  }

  // Build modal HTML
  const startTimes = ['8:00 AM','9:30 AM','11:00 AM','1:00 PM','2:30 PM','4:00 PM'];
  let html = '';

  days.forEach(d => {
    html += `<div class="itinerary-day">
      <div class="itinerary-day-header">
        📅 Day ${d.day} — ${d.city}
      </div>`;

    // Depart
    html += `
      <div class="itinerary-row">
        <div class="itinerary-time">7:00 AM</div>
        <div class="itinerary-icon" style="background:var(--green-pale)">🌅</div>
        <div class="itinerary-info">
          <div class="itinerary-name">Depart for ${d.city}</div>
          <div class="itinerary-sub">Prepare your bags and head to the terminal early.</div>
        </div>
        <div class="itinerary-cost">—</div>
      </div>`;

    d.spots.forEach((s, idx) => {
      const t = startTimes[idx + 1] || startTimes[startTimes.length - 1];
      const cost = parseFloat(s.price) > 0 ? '₱'+Number(s.price).toLocaleString()+' entrance' : 'Free entry';
      html += `
        <div class="itinerary-row">
          <div class="itinerary-time">${t}</div>
          <div class="itinerary-icon" style="background:var(--green-pale)">📍</div>
          <div class="itinerary-info">
            <div class="itinerary-name">${s.name}</div>
            <div class="itinerary-sub">${s.city}</div>
          </div>
          <div class="itinerary-cost">${cost}</div>
        </div>`;
    });

    if (d.hotel) {
      html += `
        <div class="itinerary-row" style="background:var(--sand)">
          <div class="itinerary-time">6:00 PM</div>
          <div class="itinerary-icon" style="background:#e8f4f8">🏨</div>
          <div class="itinerary-info">
            <div class="itinerary-name">Check-in: ${d.hotel.name}</div>
            <div class="itinerary-sub">${d.hotel.city}</div>
          </div>
          <div class="itinerary-cost">₱${Number(d.hotel.price).toLocaleString()}/night</div>
        </div>`;
    } else {
      html += `
        <div class="itinerary-row" style="background:var(--sand)">
          <div class="itinerary-time">6:00 PM</div>
          <div class="itinerary-icon" style="background:#e8f4f8">🏠</div>
          <div class="itinerary-info">
            <div class="itinerary-name">Head home or rest</div>
            <div class="itinerary-sub">End of Day ${d.day}</div>
          </div>
          <div class="itinerary-cost">—</div>
        </div>`;
    }

    html += `</div>`;
  });

  // Summary footer
  const totalCost = cart.reduce((s,i) => s + (parseFloat(i.price)||0), 0);
  html += `
    <div style="padding:1rem 1.25rem;background:var(--green-pale)">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
          <div style="font-size:.82rem;color:var(--green-dark);font-weight:600">
            <i class="bi bi-info-circle me-1"></i>
            ${days.length} day${days.length>1?'s':''} · ${spots.length} spot${spots.length!==1?'s':''} · ${hotels.length} hotel${hotels.length!==1?'s':''}
          </div>
          <div style="font-size:.75rem;color:var(--text-muted)">Times are approximate. Allow buffer for travel.</div>
        </div>
        <div style="font-weight:700;font-size:1.05rem;color:var(--green-dark)">
          Est. Total: ₱${totalCost.toLocaleString()}
        </div>
      </div>
    </div>`;

  document.getElementById('modal-title-text').textContent =
    `Your ${days.length}-Day Laguna Itinerary`;
  document.getElementById('itinerary-modal-body').innerHTML = html;
  new bootstrap.Modal(document.getElementById('itinerary-modal')).show();
}

function printItinerary() {
  window.print();
}

function saveItinerary() {
  // Redirect to planner with notification, or save via API if user is logged in
  <?php if ($user): ?>
  alert('Itinerary saved! You can view it in My Itineraries.');
  <?php else: ?>
  if (confirm('Log in to save your itinerary. Go to login page?')) {
    window.location.href = '<?= APP_URL ?>/pages/login.php';
  }
  <?php endif; ?>
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
