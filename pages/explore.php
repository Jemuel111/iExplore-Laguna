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
ensure_all_laguna_cities();
$cities = db_fetch_all("SELECT id, name, slug, latitude, longitude FROM cities ORDER BY name");

// Fetch all active spots
$spots = db_fetch_all(
    "SELECT s.id, s.name, s.category, s.rating, s.entrance_fee, s.description,
            s.operating_hours, s.latitude, s.longitude,
            c.name AS city_name, c.id AS city_id, 'spot' AS item_type,
            (SELECT url FROM spot_photos WHERE spot_id = s.id AND photo_type = 'main' LIMIT 1) AS main_photo_url
     FROM tourist_spots s
     JOIN cities c ON s.city_id = c.id
     WHERE s.is_active = 1
     ORDER BY s.rating DESC, s.name ASC"
);

// Fetch all active hotels
$hotels = db_fetch_all(
    "SELECT h.id, h.name, h.star_rating, h.price_min, h.price_max,
            h.address, h.phone, h.latitude, h.longitude,
            c.name AS city_name, c.id AS city_id, 'hotel' AS item_type
     FROM hotels h
     JOIN cities c ON h.city_id = c.id
     WHERE h.is_active = 1 AND h.is_verified = 1
     ORDER BY h.star_rating DESC, h.price_min ASC"
);

// Fetch all active shops
$shops = db_fetch_all(
    "SELECT s.id, s.name, s.category, s.description, s.address, s.open_time, s.close_time,
            COALESCE(s.latitude, c.latitude)   AS latitude,
            COALESCE(s.longitude, c.longitude) AS longitude,
            c.name AS city_name, c.id AS city_id, 'shop' AS item_type
     FROM shops s
     JOIN cities c ON s.city_id = c.id
     WHERE s.is_active = 1 AND s.is_verified = 1
     ORDER BY s.name ASC"
);

// Shop category metadata now comes from shop_categories() in helpers.php.

// Category metadata now comes from the shared spot_categories() helper
// in helpers.php instead of a locally duplicated array.
?>

<!-- Page hero -->
<section class="py-3" style="background:linear-gradient(135deg,var(--maroon-dark),var(--maroon-mid));color:#fff">
  <div class="container">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
      <div class="d-flex align-items-center gap-3">
        <i class="bi bi-basket3-fill fs-2" style="color:var(--sand-dark)"></i>
        <div>
          <h1 class="mb-0 fs-3" style="font-family:'Playfair Display',serif">Explore &amp; Plan</h1>
          <p class="mb-0 small opacity-75">Pick spots, hotels &amp; shops, then generate your perfect itinerary</p>
        </div>
      </div>
      <!-- Cart button — only needed where the sidebar isn't visible (below lg) -->
      <button class="btn d-flex align-items-center gap-2 d-lg-none" id="open-cart-btn"
              style="background:var(--sand-dark);color:var(--maroon-dark);font-weight:700;border-radius:var(--radius-pill);padding:.5rem 1.2rem">
        <i class="bi bi-basket3-fill"></i>
        My List
        <span id="cart-count-badge"
              style="background:var(--maroon-dark);color:#fff;border-radius:50%;width:22px;height:22px;font-size:.75rem;display:inline-flex;align-items:center;justify-content:center;font-weight:700">
          0
        </span>
      </button>
    </div>
  </div>
</section>

<!-- ══════════════ Route-based city discovery (new) ══════════════
     For visitors who don't know Laguna at all: pick a start and end
     point, see every town actually along that route, choose which
     to include, then the grid below narrows to just those cities. ── -->
<section class="py-3" id="route-picker-section" style="background:var(--sand)">
  <div class="container">

    <!-- Step 1: pick origin/destination -->
    <div id="rp-step-1" class="form-panel mx-auto" style="max-width:640px">
      <h6 class="fw-bold mb-2" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">
        <i class="bi bi-signpost-split me-2" style="color:var(--maroon-light)"></i>New to Laguna? Plan by route.
      </h6>
      <p class="small text-muted mb-3">Pick a starting city and a destination — we'll show every town you'll actually pass through, so you know what to expect before picking spots.</p>
      <div class="row g-2 align-items-end">
        <div class="col-sm-5">
          <label class="form-label small mb-1">Starting City</label>
          <select class="form-select form-select-sm" id="rp-origin-select">
            <option value="">— Select —</option>
            <?php foreach ($cities as $c): ?>
              <option value="<?= $c['id'] ?>" data-lat="<?= $c['latitude'] ?>" data-lng="<?= $c['longitude'] ?>" data-name="<?= e($c['name']) ?>"><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-sm-2 text-center">
          <button class="btn btn-sm btn-outline-secondary rounded-pill" id="rp-swap-btn" title="Swap">
            <i class="bi bi-arrow-down-up"></i>
          </button>
        </div>
        <div class="col-sm-5">
          <label class="form-label small mb-1">Destination City</label>
          <select class="form-select form-select-sm" id="rp-dest-select">
            <option value="">— Select —</option>
            <?php foreach ($cities as $c): ?>
              <option value="<?= $c['id'] ?>" data-lat="<?= $c['latitude'] ?>" data-lng="<?= $c['longitude'] ?>" data-name="<?= e($c['name']) ?>"><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary-app flex-grow-1" id="rp-find-btn">
          <i class="bi bi-search me-2"></i>Show Towns Along This Route
        </button>
        <button class="btn btn-outline-secondary" id="rp-skip-btn">
          Skip, show me everything
        </button>
      </div>
    </div>

    <!-- Step 2: map + city checklist -->
    <div id="rp-step-2" class="d-none">
      <div class="row g-3">
        <div class="col-lg-5">
          <div class="form-panel mb-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h6 class="fw-bold mb-0" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">
                <i class="bi bi-map me-2" style="color:var(--maroon-light)"></i>Towns you'll pass through
              </h6>
              <button class="btn btn-sm btn-outline-secondary" id="rp-edit-route-btn">
                <i class="bi bi-pencil me-1"></i>Edit
              </button>
            </div>
            <p class="small text-muted mb-3">Your start and destination are always included — check any others you'd like to explore too.</p>
            <div id="rp-city-list"></div>
          </div>
          <button class="btn btn-primary-app w-100" id="rp-continue-btn">
            Explore Spots in These Cities <i class="bi bi-arrow-right ms-2"></i>
          </button>
        </div>
        <div class="col-lg-7">
          <div class="form-panel p-0 overflow-hidden" style="height:420px">
            <div id="rp-map" style="height:100%"></div>
          </div>
        </div>
      </div>
    </div>

  </div>
</section>

<div id="explore-main-content" class="d-none">

<!-- Tab + filter toolbar -->
<div class="spots-toolbar sticky-top" style="top:56px;z-index:100">
  <div class="container">
    <div class="d-flex align-items-center gap-2 flex-wrap py-2 toolbar-scroll-row">

      <!-- Tab pills -->
      <div class="d-flex gap-1" id="type-tabs">
        <button class="filter-pill active" data-tab="all"><i class="bi bi-grid me-1"></i>All</button>
        <button class="filter-pill" data-tab="spot"><i class="bi bi-geo-alt me-1"></i>Spots</button>
        <button class="filter-pill" data-tab="hotel"><i class="bi bi-building me-1"></i>Hotels</button>
        <button class="filter-pill" data-tab="shop"><i class="bi bi-shop me-1"></i>Shops</button>
      </div>

      <div class="vr d-none d-md-block mx-1" style="opacity:.2"></div>

      <!-- Category quick filter (spots only) -->
      <div class="d-flex gap-1 flex-wrap" id="cat-filters">
        <button class="filter-pill active" data-cat="">All Categories</button>
        <?php foreach (spot_categories() as $key => $meta): ?>
        <button class="filter-pill" data-cat="<?= $key ?>"><i class="bi <?= $meta['icon'] ?> me-1"></i><?= $meta['label'] ?></button>
        <?php endforeach; ?>
      </div>

      <div class="ms-auto d-flex gap-2 align-items-center flex-wrap">
        <!-- City filter — repopulated to just the chosen route cities once a route is picked -->
        <select id="city-filter" class="form-select form-select-sm" style="width:auto;font-size:.8rem">
          <option value="">All Cities</option>
          <?php foreach ($cities as $c): ?>
          <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <!-- Search -->
        <div class="position-relative" style="flex:1 1 120px;min-width:0">
          <i class="bi bi-search position-absolute" style="left:.65rem;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:.8rem;pointer-events:none"></i>
          <input type="text" id="explore-search" class="form-control form-control-sm"
                 placeholder="Search…" style="padding-left:2rem;width:100%;max-width:160px;font-size:.82rem">
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
        Showing <strong style="color:var(--maroon-dark)" id="results-count">0</strong> items
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
        $catMeta = spot_categories()[$spot['category']] ?? ['bg'=>'#f1f5f9','fg'=>'#334155','icon'=>'bi-geo-alt','label'=>$spot['category']];
        $bg = $catMeta['bg']; $fg = $catMeta['fg'];
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
          <div class="explore-card-img" style="<?= !empty($spot['main_photo_url']) ? "background-image:url('".e($spot['main_photo_url'])."');background-size:cover;background-position:center;" : "background:{$bg}" ?>">
            <?php if (empty($spot['main_photo_url'])): ?>
            <span class="explore-emoji"><i class="bi <?= $catMeta['icon'] ?>"></i></span>
            <?php endif; ?>
            <!-- Add to cart btn -->
            <button class="add-to-cart-btn" onclick="toggleCart('spot',<?= $spot['id'] ?>,'<?= e(addslashes($spot['name'])) ?>','<?= e($spot['city_name']) ?>',<?= (float)$spot['entrance_fee'] ?>,<?= $spot['city_id'] ?>,<?= (float)$spot['latitude'] ?>,<?= (float)$spot['longitude'] ?>)"
                    data-key="spot-<?= $spot['id'] ?>" title="Add to My List">
              <i class="bi bi-plus-lg"></i>
            </button>
          </div>
          <div class="explore-card-body">
            <div class="mb-1">
              <span class="cat-badge" style="background:<?= $bg ?>;color:<?= $fg ?>">
                <?= $catMeta['label'] ?>
              </span>
            </div>
            <h6 class="explore-card-title">
              <a href="spot-detail.php?id=<?= $spot['id'] ?>" class="text-decoration-none" style="color:inherit"><?= e($spot['name']) ?></a>
            </h6>
            <div class="explore-card-meta">
              <i class="bi bi-geo-alt text-maroon"></i>
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
                <?= $spot['entrance_fee'] > 0 ? '₱'.number_format($spot['entrance_fee'],0) : 'Free' ?>
              </span>
              <button class="btn-add-list" onclick="toggleCart('spot',<?= $spot['id'] ?>,'<?= e(addslashes($spot['name'])) ?>','<?= e($spot['city_name']) ?>',<?= (float)$spot['entrance_fee'] ?>,<?= $spot['city_id'] ?>,<?= (float)$spot['latitude'] ?>,<?= (float)$spot['longitude'] ?>)"
                      data-key="spot-<?= $spot['id'] ?>">
                <i class="bi bi-plus-lg me-1"></i><span class="btn-add-label">Add</span>
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
            <span class="explore-emoji"><i class="bi bi-building"></i></span>
            <button class="add-to-cart-btn" onclick="toggleCart('hotel',<?= $hotel['id'] ?>,'<?= e(addslashes($hotel['name'])) ?>','<?= e($hotel['city_name']) ?>',<?= (float)$hotel['price_min'] ?>,<?= $hotel['city_id'] ?>,<?= (float)$hotel['latitude'] ?>,<?= (float)$hotel['longitude'] ?>)"
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
              <i class="bi bi-geo-alt text-maroon"></i>
              <span><?= e($hotel['city_name']) ?></span>
            </div>
            <div class="explore-card-footer">
              <span class="explore-price paid">
                ₱<?= number_format($hotel['price_min'],0) ?><span style="font-size:.7rem;color:var(--text-muted)">/night</span>
              </span>
              <button class="btn-add-list" onclick="toggleCart('hotel',<?= $hotel['id'] ?>,'<?= e(addslashes($hotel['name'])) ?>','<?= e($hotel['city_name']) ?>',<?= (float)$hotel['price_min'] ?>,<?= $hotel['city_id'] ?>,<?= (float)$hotel['latitude'] ?>,<?= (float)$hotel['longitude'] ?>)"
                      data-key="hotel-<?= $hotel['id'] ?>">
                <i class="bi bi-plus-lg me-1"></i><span class="btn-add-label">Add</span>
              </button>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>

      <?php foreach ($shops as $shop): ?>
      <div class="col-sm-6 col-xl-4 explore-item"
           data-type="shop"
           data-id="<?= $shop['id'] ?>"
           data-city="<?= $shop['city_id'] ?>"
           data-cat=""
           data-fee="0"
           data-name="<?= e(strtolower($shop['name'])) ?>"
           data-cityname="<?= e(strtolower($shop['city_name'])) ?>">
        <div class="explore-card h-100" data-id="shop-<?= $shop['id'] ?>">
          <div class="explore-card-img" style="background:var(--sand)">
            <span class="explore-emoji"><i class="bi <?= shop_category_icon($shop['category']) ?>"></i></span>
            <button class="add-to-cart-btn" onclick="toggleCart('shop',<?= $shop['id'] ?>,'<?= e(addslashes($shop['name'])) ?>','<?= e($shop['city_name']) ?>',0,<?= $shop['city_id'] ?>,<?= (float)$shop['latitude'] ?>,<?= (float)$shop['longitude'] ?>)"
                    data-key="shop-<?= $shop['id'] ?>" title="Add to My List">
              <i class="bi bi-plus-lg"></i>
            </button>
          </div>
          <div class="explore-card-body">
            <div class="mb-1">
              <span class="cat-badge" style="background:var(--maroon-pale);color:var(--maroon-dark)">
                <i class="bi <?= shop_category_icon($shop['category']) ?> me-1"></i><?= shop_category_label($shop['category']) ?>
              </span>
            </div>
            <h6 class="explore-card-title"><?= e($shop['name']) ?></h6>
            <div class="explore-card-meta">
              <i class="bi bi-geo-alt text-maroon"></i>
              <span><?= e($shop['city_name']) ?></span>
            </div>
            <?php if ($shop['open_time'] && $shop['close_time']): ?>
            <div class="explore-card-meta mt-1">
              <i class="bi bi-clock text-muted"></i>
              <span class="text-muted" style="font-size:.74rem"><?= date('g:i A', strtotime($shop['open_time'])) ?>–<?= date('g:i A', strtotime($shop['close_time'])) ?></span>
            </div>
            <?php endif; ?>
            <div class="explore-card-footer">
              <span class="explore-price free"><i class="bi bi-shop me-1"></i>Order there</span>
              <button class="btn-add-list" onclick="toggleCart('shop',<?= $shop['id'] ?>,'<?= e(addslashes($shop['name'])) ?>','<?= e($shop['city_name']) ?>',0,<?= $shop['city_id'] ?>,<?= (float)$shop['latitude'] ?>,<?= (float)$shop['longitude'] ?>)"
                      data-key="shop-<?= $shop['id'] ?>">
                <i class="bi bi-plus-lg me-1"></i><span class="btn-add-label">Add</span>
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

</div><!-- /#explore-main-content -->

<!-- ── Cart Offcanvas (mobile) ────────────────────────────── -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="cart-offcanvas" style="width:100%;max-width:380px">
  <div class="offcanvas-header" style="background:var(--maroon-dark);color:#fff">
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
      <div class="modal-header" style="background:linear-gradient(135deg,var(--maroon-dark),var(--maroon-mid));color:#fff;border:none">
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
        <button class="btn btn-sm" style="background:var(--maroon-mid);color:#fff"
                onclick="printItinerary()">
          <i class="bi bi-printer me-1"></i>Print
        </button>
        <button class="btn btn-sm" style="background:var(--sand-dark);color:var(--maroon-dark);font-weight:700"
                id="save-itin-btn" onclick="saveItinerary()">
          <i class="bi bi-bookmark-check me-2"></i>Save This Itinerary
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ── Styles ─────────────────────────────────────────────── -->
<style>
/* Route picker (city checklist rows) */
.tb-city-row {
  display: flex; align-items: center; gap: .6rem;
  padding: .6rem .7rem; border: 1px solid var(--border); border-radius: var(--radius-sm);
  margin-bottom: .5rem; cursor: pointer; transition: all .15s;
}
.tb-city-row:hover { background: var(--sand); }
.tb-city-row.locked { background: var(--sand); opacity: .85; cursor: default; }
.tb-city-row .tb-city-name { font-weight: 600; }
.tb-city-row .tb-city-tag { font-size: .7rem; color: var(--text-muted); margin-left: auto; }

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
  border-color:var(--maroon-light);
}
.explore-card.in-cart {
  border-color:var(--maroon-mid);
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
  color:var(--maroon-mid);
  font-size:1rem;
  display:flex;
  align-items:center;
  justify-content:center;
  cursor:pointer;
  transition:all .18s ease;
  box-shadow:var(--shadow-sm);
}
.add-to-cart-btn:hover, .explore-card.in-cart .add-to-cart-btn {
  background:var(--maroon-mid);
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
  border:1.5px solid var(--maroon-mid);
  color:var(--maroon-mid);
  background:transparent;
  cursor:pointer;
  transition:all .18s ease;
  white-space:nowrap;
}
.btn-add-list:hover {
  background:var(--maroon-mid);
  color:#fff;
}
.explore-card.in-cart .btn-add-list {
  background:var(--maroon-mid);
  color:#fff;
}
.explore-card.in-cart .btn-add-list i::before { content:"\F62B"; }
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
  background:linear-gradient(135deg,var(--maroon-dark),var(--maroon-mid));
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
  font-size:1.1rem;
  color: var(--maroon-dark);
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
  background:var(--maroon-pale);
  padding:.7rem 1.25rem;
  font-weight:700;
  color:var(--maroon-dark);
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
  color:var(--maroon-mid);
  padding-top:.1rem;
}
.itinerary-icon {
  width:36px;
  height:36px;
  border-radius:8px;
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:1.15rem;
  color: var(--maroon-dark);
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
let lastGeneratedItinerary = null;

// ── Route picker state ───────────────────────────────────────
// When set (via the route picker below), the grid narrows to only these
// city IDs and Save Itinerary uses the real chosen endpoints instead of
// guessing a "primary city" from whichever cities ended up in the cart.
let includedCityIds = null; // null = no route chosen yet, show everything
let rpOrigin = null, rpDest = null;
let rpRouteLine = null, rpMap = null, rpMapMarkers = [];
let rpCityOrder = []; // city IDs in actual travel order, for grouping the spot grid

const ALL_CITIES_RP = <?= json_encode(array_map(function($c){
  return ['id'=>(int)$c['id'],'name'=>$c['name'],'latitude'=>(float)$c['latitude'],'longitude'=>(float)$c['longitude']];
}, $cities)) ?>;
// Max extra distance (km) a town is allowed to add to the trip — measured as
// (origin→town + town→destination) minus the direct origin→destination
// distance — before it stops counting as "along the way". This replaces an
// older nearest-point-on-line approach, which clamped to the line's
// endpoints and could falsely flag towns that just happened to sit within
// the radius of the origin or destination, even if they were off to the
// side in a completely different direction from the actual route.
const DETOUR_THRESHOLD_KM_RP = 5;

function haversineKmRP(lat1, lon1, lat2, lon2) {
  const R = 6371, dLat=(lat2-lat1)*Math.PI/180, dLon=(lon2-lon1)*Math.PI/180;
  const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLon/2)**2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}
// How much farther the trip becomes if it detours through this city on the
// way from origin to dest. ~0 for a city that's genuinely on the road;
// large for a city that's only near one endpoint but off in another
// direction (which is what let towns like Calauan/Rizal slip in before).
function detourKmRP(city, origin, dest) {
  const viaCity = haversineKmRP(origin.latitude, origin.longitude, city.latitude, city.longitude)
                + haversineKmRP(city.latitude, city.longitude, dest.latitude, dest.longitude);
  const direct  = haversineKmRP(origin.latitude, origin.longitude, dest.latitude, dest.longitude);
  return viaCity - direct;
}

document.getElementById('rp-swap-btn').addEventListener('click', () => {
  const o = document.getElementById('rp-origin-select');
  const d = document.getElementById('rp-dest-select');
  [o.value, d.value] = [d.value, o.value];
});

document.getElementById('rp-skip-btn').addEventListener('click', () => {
  includedCityIds = null;
  document.getElementById('route-picker-section').classList.add('d-none');
  document.getElementById('explore-main-content').classList.remove('d-none');
  filterItems();
});

document.getElementById('rp-edit-route-btn').addEventListener('click', () => {
  document.getElementById('rp-step-1').classList.remove('d-none');
  document.getElementById('rp-step-2').classList.add('d-none');
});

document.getElementById('rp-find-btn').addEventListener('click', async () => {
  const oOpt = document.getElementById('rp-origin-select').selectedOptions[0];
  const dOpt = document.getElementById('rp-dest-select').selectedOptions[0];
  if (!oOpt.value || !dOpt.value) {
    IExploreApp.toast('Please select both a starting city and a destination.', 'warning');
    return;
  }
  if (oOpt.value === dOpt.value) {
    IExploreApp.toast('Starting city and destination must be different.', 'warning');
    return;
  }

  rpOrigin = { id: parseInt(oOpt.value), name: oOpt.dataset.name, latitude: parseFloat(oOpt.dataset.lat), longitude: parseFloat(oOpt.dataset.lng) };
  rpDest   = { id: parseInt(dOpt.value), name: dOpt.dataset.name, latitude: parseFloat(dOpt.dataset.lat), longitude: parseFloat(dOpt.dataset.lng) };

  const btn = document.getElementById('rp-find-btn');
  IExploreApp.setLoading(btn, true);

  try {
    const res = await fetch(
      `<?= APP_URL ?>/api/routes.php?action=directions&origin_lat=${rpOrigin.latitude}&origin_lng=${rpOrigin.longitude}&dest_lat=${rpDest.latitude}&dest_lng=${rpDest.longitude}`
    ).then(r => r.json());
    rpRouteLine = (res.success && res.data.coordinates && res.data.coordinates.length)
      ? res.data.coordinates
      : [[rpOrigin.latitude, rpOrigin.longitude], [rpDest.latitude, rpDest.longitude]];
  } catch (err) {
    rpRouteLine = [[rpOrigin.latitude, rpOrigin.longitude], [rpDest.latitude, rpDest.longitude]];
  }

  const alongRouteUnsorted = ALL_CITIES_RP.filter(c => {
    if (c.id === rpOrigin.id || c.id === rpDest.id) return true;
    return detourKmRP(c, rpOrigin, rpDest) <= DETOUR_THRESHOLD_KM_RP;
  });

  // Order cities the way a traveler actually encounters them — projected
  // position along the origin→destination line — rather than the
  // alphabetical order they came from the database in. This is what makes
  // the grouped spot sections below read as "start → ... → destination"
  // instead of a random shuffle.
  const routeDx = rpDest.longitude - rpOrigin.longitude;
  const routeDy = rpDest.latitude  - rpOrigin.latitude;
  const routeLenSq = routeDx*routeDx + routeDy*routeDy;
  function projectAlongRoute(c) {
    if (routeLenSq === 0) return 0;
    const dx = c.longitude - rpOrigin.longitude, dy = c.latitude - rpOrigin.latitude;
    return (dx*routeDx + dy*routeDy) / routeLenSq;
  }
  const alongRouteMiddle = alongRouteUnsorted.filter(c => c.id !== rpOrigin.id && c.id !== rpDest.id);
  alongRouteMiddle.sort((a, b) => projectAlongRoute(a) - projectAlongRoute(b));
  // Origin and destination are forced to the two ends regardless of their
  // own projection values. Without this, a town whose projection happens
  // to fall outside the 0–1 range (behind the start, or past the end —
  // both are geometrically possible for a town that's still within the
  // curved road corridor but off to the side of the straight start→end
  // line) could bump the actual start/destination out of first/last
  // place, which looks obviously wrong in the checklist even though the
  // sort itself is doing exactly what it was told to.
  const originCity = alongRouteUnsorted.find(c => c.id === rpOrigin.id);
  const destCity    = alongRouteUnsorted.find(c => c.id === rpDest.id);
  const alongRoute = [originCity, ...alongRouteMiddle, destCity].filter(Boolean);

  includedCityIds = new Set(alongRoute.map(c => c.id)); // pre-select all found
  rpCityOrder = alongRoute.map(c => c.id); // travel order, used to group the spot grid later

  renderRpCityList(alongRoute);
  document.getElementById('rp-step-1').classList.add('d-none');
  document.getElementById('rp-step-2').classList.remove('d-none');

  // Leaflet must initialize (or at least recompute its size) only once
  // its container is actually visible — doing it beforehand produces a
  // blank/broken map confined to the wrong dimensions. rAF ensures the
  // d-none removal above has actually been painted first.
  requestAnimationFrame(() => renderRpMap(alongRoute));

  IExploreApp.setLoading(btn, false);
});

function renderRpCityList(alongRoute) {
  const container = document.getElementById('rp-city-list');
  container.innerHTML = alongRoute.map(c => {
    const isEndpoint = c.id === rpOrigin.id || c.id === rpDest.id;
    const tag = c.id === rpOrigin.id ? 'Starting city' : c.id === rpDest.id ? 'Destination' : 'Along the way';
    return `
      <div class="tb-city-row ${isEndpoint ? 'locked' : ''}" data-city-id="${c.id}">
        <input type="checkbox" class="form-check-input" ${includedCityIds.has(c.id) ? 'checked' : ''} ${isEndpoint ? 'disabled' : ''}>
        <span class="tb-city-name">${c.name}</span>
        <span class="tb-city-tag">${tag}</span>
      </div>`;
  }).join('');

  container.querySelectorAll('.tb-city-row:not(.locked)').forEach(row => {
    row.addEventListener('click', () => {
      const cb = row.querySelector('input');
      cb.checked = !cb.checked;
      const cityId = parseInt(row.dataset.cityId);
      if (cb.checked) includedCityIds.add(cityId); else includedCityIds.delete(cityId);
      updateRpMarkerStyle(cityId, cb.checked);
    });
  });
}

function renderRpMap(alongRoute) {
  if (rpMap) { rpMap.remove(); rpMap = null; }
  rpMap = L.map('rp-map', { zoomControl: true }).setView([rpOrigin.latitude, rpOrigin.longitude], 10);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap', maxZoom: 18 }).addTo(rpMap);
  L.polyline(rpRouteLine, { color: '#6b0f14', weight: 4, opacity: 0.8 }).addTo(rpMap);

  rpMapMarkers = [];
  const bounds = [];
  alongRoute.forEach(c => {
    const isEndpoint = c.id === rpOrigin.id || c.id === rpDest.id;
    const marker = L.circleMarker([c.latitude, c.longitude], {
      radius: isEndpoint ? 10 : 8,
      fillColor: isEndpoint ? '#6b0f14' : (includedCityIds.has(c.id) ? '#e2574c' : '#c9c2b4'),
      color: '#fff', weight: 2, fillOpacity: 1,
    }).addTo(rpMap).bindPopup(`<strong>${c.name}</strong>`);
    marker._cityId = c.id;
    rpMapMarkers.push(marker);
    bounds.push([c.latitude, c.longitude]);
  });
  if (bounds.length) rpMap.fitBounds(bounds, { padding: [30, 30] });
  // Belt-and-suspenders: some browsers still miscalculate tile bounds on
  // the very first paint even after the rAF above.
  setTimeout(() => rpMap.invalidateSize(), 150);
}

function updateRpMarkerStyle(cityId, included) {
  const marker = rpMapMarkers.find(m => m._cityId === cityId);
  if (marker) marker.setStyle({ fillColor: included ? '#e2574c' : '#c9c2b4' });
}

document.getElementById('rp-continue-btn').addEventListener('click', () => {
  if (!includedCityIds.size) {
    IExploreApp.toast('Select at least one city to continue.', 'warning');
    return;
  }

  // Narrow the existing city-filter dropdown to just the chosen cities,
  // instead of showing all ~15 Laguna towns when only a few are relevant.
  const cityFilterEl = document.getElementById('city-filter');
  const includedCities = ALL_CITIES_RP.filter(c => includedCityIds.has(c.id));
  cityFilterEl.innerHTML = '<option value="">All Selected Cities</option>' +
    includedCities.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
  activeCity = '';

  groupGridByCity();

  document.getElementById('route-picker-section').classList.add('d-none');
  document.getElementById('explore-main-content').classList.remove('d-none');
  filterItems();
  window.scrollTo({ top: 0, behavior: 'smooth' });
});

// Re-arranges the existing spot/hotel/shop cards (already rendered
// server-side, sorted by rating) into sections headed by city name, in
// actual travel order — so a first-time visitor can see at a glance
// which spots are in which town, instead of one flat mixed grid with
// only a dropdown to tell them apart.
function groupGridByCity() {
  const grid = document.getElementById('explore-grid');

  // Remove any headers from a previous grouping (e.g. picking a
  // different route without a full page reload).
  grid.querySelectorAll('.explore-city-header').forEach(h => h.remove());

  const cityNameById = {};
  ALL_CITIES_RP.forEach(c => { cityNameById[c.id] = c.name; });

  rpCityOrder.forEach(cityId => {
    const items = Array.from(grid.querySelectorAll(`.explore-item[data-city="${cityId}"]`));
    if (!items.length) return; // no spots/hotels/shops on file for this city

    const header = document.createElement('div');
    header.className = 'col-12 explore-city-header';
    header.dataset.city = cityId;
    header.innerHTML = `
      <div class="d-flex align-items-center gap-2 mt-2 mb-1 pb-2" style="border-bottom:2px solid var(--maroon-pale)">
        <i class="bi bi-geo-alt-fill" style="color:var(--maroon-light)"></i>
        <h5 class="mb-0" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">${cityNameById[cityId] || ''}</h5>
        <span class="small text-muted explore-city-header-count"></span>
      </div>`;

    grid.appendChild(header);
    items.forEach(item => grid.appendChild(item)); // moves, not clones — filters/onclick stay intact
  });
}


// City name → id, needed to save the itinerary (API stores origin/dest as city IDs)
const CITY_NAME_TO_ID = <?= json_encode(array_column($cities, 'id', 'name')) ?>;

// ── Filter State ─────────────────────────────────────────────
let activeTab = 'all';   // all | spot | hotel | shop
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
  document.querySelectorAll('.filter-pill[data-cat]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.filter-pill[data-cat]').forEach(b => b.classList.remove('active'));
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

    const matchTab    = activeTab === 'all' || t === activeTab;
    const matchCat    = !activeCat  || cat === activeCat;
    const matchCity   = !activeCity || city === activeCity;
    const matchRoute  = !includedCityIds || includedCityIds.has(parseInt(city));
    const matchFree   = !freeOnly   || fee === 0;
    const matchQ      = !searchQ    || name.includes(searchQ) || cn.includes(searchQ);

    const show = matchTab && matchCat && matchCity && matchRoute && matchFree && matchQ;
    el.style.display = show ? '' : 'none';
    if (show) visible++;
  });

  document.getElementById('results-count').textContent = visible;
  document.getElementById('empty-state').classList.toggle('d-none', visible > 0);

  // Keep city section headers in sync — hide a city's header entirely if
  // every item under it just got filtered out (e.g. switching to the
  // "Hotels" tab when a city only has spots on file), and show a live
  // count of what's actually visible in each section.
  document.querySelectorAll('.explore-city-header').forEach(header => {
    const cityId = header.dataset.city;
    const cityItems = document.querySelectorAll(`.explore-item[data-city="${cityId}"]`);
    let visibleInCity = 0;
    cityItems.forEach(el => { if (el.style.display !== 'none') visibleInCity++; });
    header.style.display = visibleInCity > 0 ? '' : 'none';
    const countEl = header.querySelector('.explore-city-header-count');
    if (countEl) countEl.textContent = visibleInCity + (visibleInCity === 1 ? ' item' : ' items');
  });
}

// ── Cart: toggle add/remove ───────────────────────────────────
function toggleCart(type, id, name, city, price, cityId, lat, lng) {
  const key = type + '-' + id;
  const idx = cart.findIndex(i => i.key === key);
  if (idx >= 0) {
    cart.splice(idx, 1);
  } else {
    cart.push({ key, type, id, name, city, price, cityId, lat, lng });
  }
  saveCart();
  restoreCartUI();
}

function saveCart() {
  localStorage.setItem('iexplore_cart', JSON.stringify(cart));
}

function clearCart() {
  if (cart.length === 0) return;
  if (!confirm('Remove all ' + cart.length + ' item' + (cart.length !== 1 ? 's' : '') + ' from your list?')) return;
  cart = [];
  saveCart();
  restoreCartUI();
}

function restoreCartUI() {
  // Update card states
  document.querySelectorAll('.explore-card').forEach(card => {
    const key = card.dataset.id;
    const inCart = cart.some(i => i.key === key);
    card.classList.toggle('in-cart', inCart);

    const label = card.querySelector('.btn-add-label');
    if (label) label.textContent = inCart ? 'Added' : 'Add';
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
        <p class="mb-0 small">Nothing added yet.<br>Browse spots, hotels &amp; shops above.</p>
      </div>`;
  } else {
    listEl.innerHTML = cart.map(item => {
      const iconClass = item.type === 'hotel' ? 'bi-building'
        : item.type === 'shop'  ? 'bi-shop'
        : 'bi-geo-alt';
      const priceStr = item.type === 'hotel'
        ? (item.price > 0 ? '₱'+Number(item.price).toLocaleString()+'/night' : 'Price varies')
        : item.type === 'shop'
        ? 'Order there'
        : (item.price > 0 ? '₱'+Number(item.price).toLocaleString() : 'Free');
      return `
        <div class="cart-item">
          <div class="cart-item-icon" style="background:${item.type==='hotel'?'#e8f4f8':item.type==='shop'?'var(--sand)':'var(--maroon-pale)'}">
            <i class="bi ${iconClass}"></i>
          </div>
          <div class="cart-item-info">
            <div class="cart-item-name">${item.name}</div>
            <div class="cart-item-sub">${item.city} · ${priceStr}</div>
          </div>
          <button class="cart-item-remove" onclick="toggleCart('${item.type}',${item.id},'${item.name.replace(/'/g,"\\'")}','${item.city}',${item.price},${item.cityId},${item.lat},${item.lng})" title="Remove">
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

// ── Real fare / last-mile / lunch helpers ───────────────────────
// Same approach as the Trip Planner's itinerary builder — reused here
// (rather than shared via a module, to avoid a riskier cross-file
// refactor) so a cart-built itinerary reads with the same quality
// instead of vague, unpriced placeholder text.
function estimateTravelMinutesGI(km) { return Math.round(km / 30 * 60) + 10; }
function formatDurationGI(mins) {
  if (mins < 60) return `${mins} min`;
  const h = Math.floor(mins / 60), m = mins % 60;
  return m > 0 ? `${h}h ${m}m` : `${h}h`;
}
const TRANSPORT_LABELS_GI = { jeepney:'Jeepney', bus:'Bus', tricycle:'Tricycle', private_car:'Private Car', fx_uv:'FX / UV Express' };
const TRANSPORT_ICONS_GI  = { jeepney:'bi-truck-front', bus:'bi-bus-front', tricycle:'bi-bicycle', private_car:'bi-car-front', fx_uv:'bi-minecart' };
function transportLabelGI(t) { return TRANSPORT_LABELS_GI[t] || t; }
function transportIconGI(t)  { return TRANSPORT_ICONS_GI[t] || 'bi-bus-front'; }

let fareCacheGI = new Map();
async function getCityFareGI(originCityId, destCityId) {
  if (!originCityId || !destCityId || originCityId === destCityId) return null;
  const key = `${originCityId}-${destCityId}`;
  if (fareCacheGI.has(key)) return fareCacheGI.get(key);
  let cheapest = null;
  try {
    const res = await fetch(`<?= APP_URL ?>/api/routes.php?action=route&origin=${originCityId}&dest=${destCityId}`).then(r => r.json());
    const options = res.success ? res.data.transport_options : [];
    cheapest = (options && options.length) ? options[0] : null;
  } catch (err) { console.warn('Fare lookup failed', key, err); }
  fareCacheGI.set(key, cheapest);
  return cheapest;
}

let foodCacheGI = new Map();
const SHOP_CATEGORY_LABELS_GI = { restaurant:'Restaurant', cafe:'Café', street_food:'Street food stall', bakery:'Bakery', milktea:'Milk tea shop' };
async function getNearbyFoodGI(cityId, lat, lng) {
  if (!cityId) return null;
  if (!foodCacheGI.has(cityId)) {
    try {
      const res = await fetch(`<?= APP_URL ?>/api/shops.php?action=nearby_food&city=${cityId}`).then(r => r.json());
      foodCacheGI.set(cityId, res.success ? res.data : []);
    } catch (err) { foodCacheGI.set(cityId, []); }
  }
  const shops = foodCacheGI.get(cityId);
  if (!shops || !shops.length) return null;
  let nearest = null, nearestKm = Infinity;
  for (const shop of shops) {
    const km = haversineKmRP(lat, lng, shop.latitude, shop.longitude);
    if (km < nearestKm) { nearestKm = km; nearest = shop; }
  }
  return nearest ? { ...nearest, distance_km: nearestKm } : null;
}

function lastMileSuggestionGI(km) {
  const mins = estimateTravelMinutesGI(km);
  if (km <= 0.8) return `<i class="bi bi-person-walking me-1"></i>Walk (~${formatDurationGI(Math.max(mins, 5))})`;
  if (km <= 3)   return `<i class="bi bi-bicycle me-1"></i>Ride a tricycle (~₱15–20, ~${formatDurationGI(mins)})`;
  return `<i class="bi bi-bicycle me-1"></i>Tricycle or multicab (~₱20–30, ~${formatDurationGI(mins)}, fare varies)`;
}

function minutesToLabelGI(mins) {
  let h = Math.floor(mins / 60) % 24, m = mins % 60;
  const ap = h >= 12 ? 'PM' : 'AM';
  h = h % 12; if (h === 0) h = 12;
  return `${h}:${String(m).padStart(2,'0')} ${ap}`;
}

const CITY_LOOKUP_GI = {};
ALL_CITIES_RP.forEach(c => { CITY_LOOKUP_GI[c.id] = c; });

// ── Generate Itinerary ────────────────────────────────────────
async function generateItinerary(btn) {
  if (cart.length === 0) {
    alert('Add at least one spot or hotel to your list first!');
    return;
  }
  if (btn) IExploreApp.setLoading(btn, true);

  // Separate spots, shops, and hotels
  const spots  = cart.filter(i => i.type === 'spot');
  const shops  = cart.filter(i => i.type === 'shop');
  const hotels = cart.filter(i => i.type === 'hotel');

  // Group spots + shops by city for logical day ordering
  const cityGroups = {};
  spots.forEach(s => {
    if (!cityGroups[s.city]) cityGroups[s.city] = { spots: [], shops: [], cityId: s.cityId };
    cityGroups[s.city].spots.push(s);
  });
  shops.forEach(s => {
    if (!cityGroups[s.city]) cityGroups[s.city] = { spots: [], shops: [], cityId: s.cityId };
    cityGroups[s.city].shops.push(s);
  });

  // Order cities the way a traveler would actually encounter them if a
  // route was picked upfront; otherwise fall back to the order they were
  // added to the list.
  let cityList = Object.keys(cityGroups);
  if (rpCityOrder.length) {
    const nameById = {};
    ALL_CITIES_RP.forEach(c => { nameById[c.id] = c.name; });
    const orderedNames = rpCityOrder.map(id => nameById[id]).filter(n => cityList.includes(n));
    const remaining = cityList.filter(n => !orderedNames.includes(n));
    cityList = [...orderedNames, ...remaining];
  }

  // Build days — max 3 spots per day, shops tag along on the first chunk
  const days = [];
  let dayNum = 1;
  cityList.forEach(city => {
    const citySpots = cityGroups[city].spots;
    const cityShops = cityGroups[city].shops;
    const cityId    = cityGroups[city].cityId;
    if (citySpots.length === 0) {
      days.push({ day: dayNum++, city, cityId, spots: [], shops: cityShops, hotel: null });
    } else {
      for (let i = 0; i < citySpots.length; i += 3) {
        days.push({
          day: dayNum++, city, cityId,
          spots: citySpots.slice(i, i + 3),
          shops: i === 0 ? cityShops : [],
          hotel: null,
        });
      }
    }
  });

  if (hotels.length) {
    hotels.forEach((h, idx) => { if (days[idx]) days[idx].hotel = h; });
  }

  // Build modal HTML — now with a real running clock per day so travel
  // time, fares, and lunch actually land at sensible, non-overlapping
  // times instead of picking from a fixed array of guessed slots.
  let html = '';
  let lastCityId = null, lastLat = null, lastLng = null;

  for (const d of days) {
    html += `<div class="itinerary-day">
      <div class="itinerary-day-header">
        <i class="bi bi-calendar3 me-2"></i>Day ${d.day} — ${d.city}
      </div>`;

    let time = 8 * 60; // 8:00 AM
    const cityInfo = CITY_LOOKUP_GI[d.cityId];

    // Inter-city travel — only when this day's city differs from the
    // last place we were (skips this on day 1, and on consecutive days
    // spent chunking through the same city).
    if (d.cityId && lastCityId && d.cityId !== lastCityId) {
      const fare = await getCityFareGI(lastCityId, d.cityId);
      const hopKm = fare && fare.distance_km ? parseFloat(fare.distance_km)
        : (cityInfo ? haversineKmRP(lastLat, lastLng, cityInfo.latitude, cityInfo.longitude) : 0);
      const travelMins = fare && fare.duration_min ? fare.duration_min : estimateTravelMinutesGI(hopKm);
      const heading = fare ? `Board a ${transportLabelGI(fare.transport_type)} to ${d.city}` : `Travel to ${d.city}`;
      const desc = fare
        ? `${fare.fare_php > 0 ? '₱'+parseFloat(fare.fare_php).toFixed(2) : 'Own vehicle'} · ${fare.distance_km} km · ${formatDurationGI(fare.duration_min)}`
        : `${hopKm.toFixed(1)} km · no fixed fare on file — try tricycle/habal-habal and negotiate`;
      html += `
        <div class="itinerary-row">
          <div class="itinerary-time">${minutesToLabelGI(time)}</div>
          <div class="itinerary-icon" style="background:var(--maroon-pale)"><i class="bi ${fare ? transportIconGI(fare.transport_type) : 'bi-bus-front'}"></i></div>
          <div class="itinerary-info">
            <div class="itinerary-name">${heading}</div>
            <div class="itinerary-sub">${desc}</div>
          </div>
          <div class="itinerary-cost">${fare && fare.fare_php > 0 ? '₱'+parseFloat(fare.fare_php).toFixed(2) : '—'}</div>
        </div>`;
      time += travelMins;
    } else if (d.day === 1) {
      html += `
        <div class="itinerary-row">
          <div class="itinerary-time">${minutesToLabelGI(time)}</div>
          <div class="itinerary-icon" style="background:var(--maroon-pale)"><i class="bi bi-sunrise"></i></div>
          <div class="itinerary-info">
            <div class="itinerary-name">Start your day in ${d.city}</div>
            <div class="itinerary-sub">Pack your bags and get ready to explore.</div>
          </div>
          <div class="itinerary-cost">—</div>
        </div>`;
      time += 15;
    }

    let lunchInserted = false;
    for (const s of d.spots) {
      // Last-mile hop from wherever we just were to this specific spot.
      const fromLat = lastLat ?? (cityInfo ? cityInfo.latitude : s.lat);
      const fromLng = lastLng ?? (cityInfo ? cityInfo.longitude : s.lng);
      const legKm = haversineKmRP(fromLat, fromLng, s.lat, s.lng);
      const legLabel = lastMileSuggestionGI(legKm);
      const cost = parseFloat(s.price) > 0 ? '₱'+Number(s.price).toLocaleString()+' entrance' : 'Free entry';

      html += `
        <div class="itinerary-row">
          <div class="itinerary-time">${minutesToLabelGI(time)}</div>
          <div class="itinerary-icon" style="background:var(--maroon-pale)"><i class="bi bi-geo-alt"></i></div>
          <div class="itinerary-info">
            <div class="itinerary-name">${s.name}</div>
            <div class="itinerary-sub">${s.city} · ${legLabel} (${legKm.toFixed(1)} km)</div>
          </div>
          <div class="itinerary-cost">${cost}</div>
        </div>`;
      time += 120;
      lastLat = s.lat; lastLng = s.lng; lastCityId = s.cityId;

      // Real lunch suggestion once the clock crosses noon, anchored to
      // whichever spot the traveler just finished — same logic as the
      // Trip Planner, instead of a generic unlocated food mention.
      if (!lunchInserted && time >= 12 * 60) {
        const nearbyFood = await getNearbyFoodGI(s.cityId, s.lat, s.lng);
        const lunchDesc = nearbyFood
          ? `${SHOP_CATEGORY_LABELS_GI[nearbyFood.category] || 'Eatery'} <strong>${nearbyFood.name}</strong> is about ${nearbyFood.distance_km < 1 ? Math.round(nearbyFood.distance_km*1000)+'m' : nearbyFood.distance_km.toFixed(1)+' km'} from ${s.name}.`
          : `No listed eateries near ${s.name} yet — ask locally, or try Laguna specialties like buko pie, kesong puti, or fresh bangus.`;
        html += `
          <div class="itinerary-row">
            <div class="itinerary-time">${minutesToLabelGI(time)}</div>
            <div class="itinerary-icon" style="background:var(--sand)"><i class="bi bi-cup-hot"></i></div>
            <div class="itinerary-info">
              <div class="itinerary-name">Lunch Break</div>
              <div class="itinerary-sub">${lunchDesc}</div>
            </div>
            <div class="itinerary-cost">—</div>
          </div>`;
        time += 60;
        lunchInserted = true;
      }
    }

    d.shops.forEach(sh => {
      html += `
        <div class="itinerary-row">
          <div class="itinerary-time">${minutesToLabelGI(time)}</div>
          <div class="itinerary-icon" style="background:var(--sand)"><i class="bi bi-shop"></i></div>
          <div class="itinerary-info">
            <div class="itinerary-name">Stop by ${sh.name}</div>
            <div class="itinerary-sub">${sh.city} · order ahead for pickup</div>
          </div>
          <div class="itinerary-cost">—</div>
        </div>`;
      time += 30;
    });

    if (d.hotel) {
      html += `
        <div class="itinerary-row" style="background:var(--sand)">
          <div class="itinerary-time">6:00 PM</div>
          <div class="itinerary-icon" style="background:#e8f4f8"><i class="bi bi-building"></i></div>
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
          <div class="itinerary-icon" style="background:#e8f4f8"><i class="bi bi-house"></i></div>
          <div class="itinerary-info">
            <div class="itinerary-name">Head home or rest</div>
            <div class="itinerary-sub">End of Day ${d.day}</div>
          </div>
          <div class="itinerary-cost">—</div>
        </div>`;
    }

    html += `</div>`;
  }

  // Summary footer
  const totalCost = cart.reduce((s,i) => s + (parseFloat(i.price)||0), 0);
  html += `
    <div style="padding:1rem 1.25rem;background:var(--maroon-pale)">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
          <div style="font-size:.82rem;color:var(--maroon-dark);font-weight:600">
            <i class="bi bi-info-circle me-1"></i>
            ${days.length} day${days.length>1?'s':''} · ${spots.length} spot${spots.length!==1?'s':''} · ${shops.length} shop${shops.length!==1?'s':''} · ${hotels.length} hotel${hotels.length!==1?'s':''}
          </div>
          <div style="font-size:.75rem;color:var(--text-muted)">Times reflect real travel estimates where fare data is available.</div>
        </div>
        <div style="font-weight:700;font-size:1.05rem;color:var(--maroon-dark)">
          Est. Total: ₱${totalCost.toLocaleString()}
        </div>
      </div>
    </div>`;

  document.getElementById('modal-title-text').textContent =
    `Your ${days.length}-Day Laguna Itinerary`;
  document.getElementById('itinerary-modal-body').innerHTML = html;
  if (btn) IExploreApp.setLoading(btn, false);
  new bootstrap.Modal(document.getElementById('itinerary-modal')).show();


  // Keep this around so the Save button can persist it
  lastGeneratedItinerary = { days, spots, shops, hotels, totalCost };
}

function printItinerary() {
  window.print();
}

function saveItinerary() {
  <?php if (!$user): ?>
  if (confirm('Log in to save your itinerary. Go to login page?')) {
    window.location.href = '<?= APP_URL ?>/pages/login.php?redirect=<?= urlencode(APP_URL.'/pages/explore.php') ?>';
  }
  return;
  <?php else: ?>
  if (!lastGeneratedItinerary) {
    IExploreApp.toast('Generate an itinerary first.', 'warning');
    return;
  }

  const { days, hotels, totalCost } = lastGeneratedItinerary;

  // If the person picked an actual route up front, use those real
  // endpoints — far more accurate than guessing an origin/destination
  // from whichever cities happened to end up in the cart.
  let originId, destId, titleCity;
  if (rpOrigin && rpDest) {
    originId  = rpOrigin.id;
    destId    = rpDest.id;
    titleCity = rpDest.name;
  } else {
    // Fallback (no route picked, e.g. "Skip, show me everything"):
    // use the most-visited city as a stand-in for both origin/destination.
    const cityCounts = {};
    days.forEach(d => { cityCounts[d.city] = (cityCounts[d.city] || 0) + 1; });
    const primaryCity = Object.keys(cityCounts).sort((a,b) => cityCounts[b]-cityCounts[a])[0] || Object.keys(CITY_NAME_TO_ID)[0];
    originId = destId = CITY_NAME_TO_ID[primaryCity];
    titleCity = primaryCity;
  }

  if (!originId || !destId) {
    IExploreApp.toast('Could not determine a city for this itinerary.', 'danger');
    return;
  }

  const btn = document.getElementById('save-itin-btn');
  if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving…'; }

  fetch('<?= APP_URL ?>/api/itineraries.php?action=save', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN },
    body: JSON.stringify({
      origin_id: originId,
      dest_id:   destId,
      days:      days.length,
      persons:   1,
      budget_level: 'midrange',
      transport_pref: 'any',
      total_budget: totalCost,
      itinerary_json: days,
      title: `My ${titleCity} Trip (${days.length}d)`,
    })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      IExploreApp.toast('Itinerary saved! View it in My Itineraries.', 'success');
      setTimeout(() => { window.location.href = '<?= APP_URL ?>/pages/itineraries.php'; }, 1200);
    } else {
      IExploreApp.toast(data.message || 'Failed to save itinerary.', 'danger');
      if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-bookmark-check me-2"></i>Save This Itinerary'; }
    }
  })
  .catch(() => {
    IExploreApp.toast('Connection error. Please try again.', 'danger');
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-bookmark-check me-2"></i>Save This Itinerary'; }
  });
  <?php endif; ?>
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
