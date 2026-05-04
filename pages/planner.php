<?php
// ============================================================
// IEXPLORE LAGUNA — Trip Planner Page
// pages/planner.php
// ============================================================
$page_title  = 'Trip Planner';
$active_page = 'planner';
require_once __DIR__ . '/../includes/header.php';

// Pre-fill from query string (from homepage quick form)
$pre_origin  = (int) input('origin',      'get', 0);
$pre_dest    = (int) input('destination', 'get', 0);
$pre_days    = (int) input('days',        'get', 1);
$pre_budget  = input('budget_level', 'get', 'midrange');
$pre_persons = (int) input('persons',     'get', 1);

// Load all cities for dropdowns
$cities = db_fetch_all("SELECT id, name, slug, latitude, longitude FROM cities ORDER BY name");

// Transport type labels
$transport_labels = [
    'jeepney'     => ['label' => 'Jeepney',      'icon' => 'bi-truck-front'],
    'bus'         => ['label' => 'Bus',           'icon' => 'bi-bus-front'],
    'tricycle'    => ['label' => 'Tricycle',      'icon' => 'bi-bicycle'],
    'private_car' => ['label' => 'Private Car',   'icon' => 'bi-car-front'],
    'fx_uv'       => ['label' => 'FX / UV Express','icon'=> 'bi-minecart'],
];
?>

<!-- Page header -->
<section class="py-4" style="background:linear-gradient(135deg,var(--green-dark),var(--green-mid));color:#fff">
  <div class="container">
    <div class="d-flex align-items-center gap-3">
      <i class="bi bi-compass fs-2" style="color:var(--sand-dark)"></i>
      <div>
        <h1 class="mb-0 fs-3" style="font-family:'Playfair Display',serif">Trip Planner</h1>
        <p class="mb-0 small opacity-75">Select your route, explore spots, and estimate your budget</p>
      </div>
    </div>
  </div>
</section>

<section class="py-4">
<div class="container-fluid px-3 px-lg-4">
<div class="row g-3">

  <!-- ── LEFT PANEL: Controls ──────────────────────────── -->
  <div class="col-lg-3 col-xl-3">

    <!-- Route Selector -->
    <div class="form-panel mb-3">
      <h6 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--green-dark)">
        <i class="bi bi-signpost-split me-2" style="color:var(--green-light)"></i>Plan Your Route
      </h6>

      <div class="mb-3">
        <label class="form-label">Starting Point</label>
        <select class="form-select" id="origin-select">
          <option value="">— Select city —</option>
          <?php foreach ($cities as $c): ?>
            <option value="<?= $c['id'] ?>"
              data-lat="<?= $c['latitude'] ?>" data-lng="<?= $c['longitude'] ?>"
              <?= $pre_origin === (int)$c['id'] ? 'selected' : '' ?>>
              <?= e($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Swap button -->
      <div class="text-center mb-3">
        <button class="btn btn-sm btn-outline-secondary rounded-pill px-3" id="swap-btn"
                title="Swap origin and destination">
          <i class="bi bi-arrow-down-up me-1"></i>Swap
        </button>
      </div>

      <div class="mb-3">
        <label class="form-label">Destination</label>
        <select class="form-select" id="dest-select">
          <option value="">— Select city —</option>
          <?php foreach ($cities as $c): ?>
            <option value="<?= $c['id'] ?>"
              data-lat="<?= $c['latitude'] ?>" data-lng="<?= $c['longitude'] ?>"
              <?= $pre_dest === (int)$c['id'] ? 'selected' : '' ?>>
              <?= e($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="row g-2 mb-3">
        <div class="col-6">
          <label class="form-label">Days</label>
          <select class="form-select" id="days-select">
            <?php for ($d = 1; $d <= 5; $d++): ?>
              <option value="<?= $d ?>" <?= $pre_days === $d ? 'selected' : '' ?>>
                <?= $d ?> day<?= $d > 1 ? 's' : '' ?>
              </option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="col-6">
          <label class="form-label">Persons</label>
          <select class="form-select" id="persons-select">
            <?php for ($p = 1; $p <= 10; $p++): ?>
              <option value="<?= $p ?>" <?= $pre_persons === $p ? 'selected' : '' ?>>
                <?= $p ?> pax
              </option>
            <?php endfor; ?>
          </select>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">Budget Level</label>
        <select class="form-select" id="budget-select">
          <option value="budget"   <?= $pre_budget==='budget'   ? 'selected':'' ?>>💰 Budget</option>
          <option value="midrange" <?= $pre_budget==='midrange' ? 'selected':'' ?>>💳 Mid-range</option>
          <option value="upscale"  <?= $pre_budget==='upscale'  ? 'selected':'' ?>>💎 Upscale</option>
        </select>
      </div>

      <button class="btn btn-primary-app w-100" id="plan-btn">
        <i class="bi bi-search me-2"></i>Find Route
      </button>
    </div>

    <!-- Route Summary (hidden until searched) -->
    <div id="route-summary" class="d-none">

      <!-- Transport options -->
      <div class="form-panel mb-3">
        <h6 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--green-dark)">
          <i class="bi bi-bus-front me-2" style="color:var(--green-light)"></i>Transport Options
        </h6>
        <div id="transport-list"></div>
      </div>

      <!-- Quick stats -->
      <div class="form-panel mb-3" id="route-stats-panel">
        <h6 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--green-dark)">
          <i class="bi bi-info-circle me-2" style="color:var(--green-light)"></i>Route Info
        </h6>
        <div id="route-stats"></div>
      </div>

    </div>

    <!-- No route found message -->
    <div id="no-route-msg" class="d-none alert" style="background:var(--sand);border:1px solid var(--sand-dark);border-radius:var(--radius-sm)">
      <i class="bi bi-exclamation-triangle me-2" style="color:var(--terracotta)"></i>
      <small>No direct route data found. Showing map with straight-line path.</small>
    </div>

  </div>

  <!-- ── CENTER PANEL: Map ──────────────────────────────── -->
  <div class="col-lg-5 col-xl-6">

    <!-- Map -->
    <div class="position-relative mb-3">
    <div id="trip-map"></div>

      <!-- Map legend -->
      <div class="position-absolute bottom-0 start-0 m-2 p-2 bg-white rounded shadow-sm"
           style="font-size:.75rem;z-index:999;border:1px solid var(--border)">
        <div class="d-flex align-items-center gap-2 mb-1">
          <span style="width:14px;height:14px;background:#2d6a4f;border-radius:50%;display:inline-block"></span>
          <span>Start / End</span>
        </div>
        <div class="d-flex align-items-center gap-2 mb-1">
          <span style="width:14px;height:14px;background:var(--terracotta);border-radius:50%;display:inline-block"></span>
          <span>Tourist Spot</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span style="width:18px;height:3px;background:#2d6a4f;display:inline-block;border-radius:2px"></span>
          <span>Route</span>
        </div>
      </div>

      <!-- Loading overlay -->
      <div id="map-loading" class="position-absolute top-0 start-0 w-100 h-100"
     style="display:none;background:rgba(255,255,255,.7);border-radius:var(--radius);z-index:1000;align-items:center;justify-content:center">
    </div>

    <!-- Spots along route -->
    <div id="spots-section" class="d-none">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="fw-bold mb-0" style="font-family:'Playfair Display',serif;color:var(--green-dark)">
          <i class="bi bi-geo-alt-fill me-2" style="color:var(--terracotta)"></i>
          Tourist Spots Along This Route
        </h6>
        <span id="spots-count" class="badge rounded-pill" style="background:var(--green-pale);color:var(--green-dark)"></span>
      </div>

      <!-- Category filter -->
      <div class="d-flex flex-wrap gap-2 mb-3" id="category-filters">
        <button class="btn btn-sm filter-btn active" data-cat="all"
                style="border-radius:20px;font-size:.78rem;padding:.25rem .75rem">All</button>
      </div>

      <div id="spots-grid" class="row g-2"></div>
    </div>

  </div>

  <!-- ── RIGHT PANEL: Budget + Itinerary ───────────────── -->
  <div class="col-lg-4 col-xl-3">

    <div id="right-panel-placeholder" class="form-panel text-center py-5" style="color:var(--text-muted)">
      <i class="bi bi-map fs-1 d-block mb-3" style="color:var(--green-pale)"></i>
      <p class="mb-0 small">Select origin and destination<br>to see budget &amp; itinerary.</p>
    </div>

    <!-- Budget Panel (hidden until searched) -->
    <div id="budget-panel-wrap" class="d-none mb-3">
      <div class="budget-panel">
        <div class="total-label mb-1">Estimated Total Budget</div>
        <div class="total-amount mb-3" id="total-budget">₱ 0.00</div>
        <div id="budget-breakdown"></div>
      </div>
      <div class="mt-2 text-end">
        <small class="text-muted">
          <i class="bi bi-info-circle me-1"></i>
          Estimates for <span id="budget-persons">1</span> person,
          <span id="budget-days">1</span> day(s)
        </small>
      </div>
    </div>

    <!-- Itinerary Panel (hidden until searched) -->
    <div id="itinerary-panel" class="d-none">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="fw-bold mb-0" style="font-family:'Playfair Display',serif;color:var(--green-dark)">
          <i class="bi bi-journal-bookmark me-2" style="color:var(--green-light)"></i>Suggested Itinerary
        </h6>
        <button class="btn btn-sm btn-outline-app" id="save-itinerary-btn">
          <i class="bi bi-bookmark-plus me-1"></i>Save
        </button>
      </div>
      <div id="itinerary-days"></div>
    </div>

  </div>

</div><!-- /row -->
</div><!-- /container -->
</section>

<!-- ── Spot Detail Modal ────────────────────────────────────── -->
<div class="modal fade" id="spotModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:var(--radius);border:none">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title" id="modalSpotName" style="font-family:'Playfair Display',serif"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="modalSpotBody"></div>
    </div>
  </div>
</div>

<!-- ── JS ──────────────────────────────────────────────────── -->
<script>
document.addEventListener('DOMContentLoaded', function() {
/* ============================================================
   TRIP PLANNER — Main JS
   ============================================================ */

// Fix API base — pages/ is one level deep, api/ is at root
const API_BASE = '<?= APP_URL ?>/api/';

// ── Map init ────────────────────────────────────────────────
const map = L.map('trip-map', { zoomControl: true }).setView([14.17, 121.24], 10);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
  maxZoom: 18,
}).addTo(map);

// Critical: force Leaflet to recalculate container size after layout settles
setTimeout(() => { map.invalidateSize(); }, 100);
setTimeout(() => { map.invalidateSize(); }, 400);
setTimeout(() => { map.invalidateSize(); }, 800);
window.addEventListener('resize', () => map.invalidateSize());

// State
let routeLayer    = null;
let markerLayer   = L.layerGroup().addTo(map);
let allSpots      = [];
let routeData     = null;
let selectedTransport = null;

// ── Custom marker icons ─────────────────────────────────────
function makeIcon(color, icon = '●', size = 32) {
  return L.divIcon({
    className: '',
    html: `<div style="
      width:${size}px;height:${size}px;border-radius:50% 50% 50% 0;
      background:${color};border:2px solid #fff;
      box-shadow:0 2px 8px rgba(0,0,0,.25);
      transform:rotate(-45deg);display:flex;align-items:center;justify-content:center;">
      <span style="transform:rotate(45deg);font-size:${size*.4}px;color:#fff">${icon}</span>
    </div>`,
    iconSize: [size, size],
    iconAnchor: [size/2, size],
    popupAnchor: [0, -size],
  });
}

const iconStart = makeIcon('#2d6a4f', '▶', 34);
const iconEnd   = makeIcon('#1a3a2a', '■', 34);
const iconSpot  = makeIcon('#c77c48', '★', 28);

// ── Plan button ─────────────────────────────────────────────
document.getElementById('plan-btn').addEventListener('click', planRoute);

// Swap button
document.getElementById('swap-btn').addEventListener('click', () => {
  const o = document.getElementById('origin-select');
  const d = document.getElementById('dest-select');
  const tmp = o.value;
  o.value = d.value;
  d.value = tmp;
});

// ── FIX: Auto-plan INSIDE DOMContentLoaded so planRoute is in scope ──
<?php if ($pre_origin && $pre_dest): ?>
setTimeout(planRoute, 500);
<?php endif; ?>

// ── Main plan function ──────────────────────────────────────
async function planRoute() {
  const origin = document.getElementById('origin-select').value;
  const dest   = document.getElementById('dest-select').value;
  const days   = parseInt(document.getElementById('days-select').value);
  const persons= parseInt(document.getElementById('persons-select').value);
  const budget = document.getElementById('budget-select').value;

  if (!origin || !dest) {
    IExploreApp.toast('Please select both origin and destination.', 'warning');
    return;
  }
  if (origin === dest) {
    IExploreApp.toast('Origin and destination must be different cities.', 'warning');
    return;
  }

  // Show loading
  showMapLoading(true);
  IExploreApp.setLoading(document.getElementById('plan-btn'), true);

  // Clear previous
  markerLayer.clearLayers();
  if (routeLayer) { map.removeLayer(routeLayer); routeLayer = null; }

  try {
    // Fetch route + spots in parallel
    const [routeRes, spotsRes] = await Promise.all([
      fetch(API_BASE + `routes.php?action=route&origin=${origin}&dest=${dest}`).then(r=>r.json()),
      fetch(API_BASE + `routes.php?action=spots&origin=${origin}&dest=${dest}`).then(r=>r.json()),
    ]);

    if (!routeRes.success) {
      IExploreApp.toast(routeRes.message || 'Route not found.', 'error');
      return;
    }

    routeData = routeRes.data;
    allSpots  = spotsRes.success ? spotsRes.data : [];

    // Draw map
    drawRoute(routeData);
    drawSpotMarkers(allSpots);

    // Render UI panels
    renderTransportOptions(routeData.transport_options);
    renderRouteStats(routeData);
    renderSpotsGrid(allSpots);
    renderBudget(routeData, allSpots, days, persons, budget);
    renderItinerary(routeData, allSpots, days);

    // Show panels
    document.getElementById('route-summary').classList.remove('d-none');
    document.getElementById('spots-section').classList.remove('d-none');
    document.getElementById('budget-panel-wrap').classList.remove('d-none');
    document.getElementById('itinerary-panel').classList.remove('d-none');
    document.getElementById('right-panel-placeholder').classList.add('d-none');
    document.getElementById('no-route-msg').classList.toggle(
      'd-none', routeData.has_route
    );

    // Update budget meta
    document.getElementById('budget-persons').textContent = persons;
    document.getElementById('budget-days').textContent    = days;

  } catch(err) {
    IExploreApp.toast('Something went wrong. Please try again.', 'error');
    console.error(err);
  } finally {
    showMapLoading(false);
    IExploreApp.setLoading(document.getElementById('plan-btn'), false);
  }
}

// ── Draw route line on map ──────────────────────────────────
function drawRoute(data) {
  const { origin, destination, waypoints } = data;

  const latlngs = waypoints.map(w => [w.lat, w.lng]);

  routeLayer = L.polyline(latlngs, {
    color: '#2d6a4f',
    weight: 5,
    opacity: .85,
    dashArray: null,
    lineJoin: 'round',
  }).addTo(map);

  // Animate route draw
  routeLayer.setStyle({ dashArray: '12 8', dashOffset: '0' });
  let offset = 0;
  const anim = setInterval(() => {
    offset -= 2;
    routeLayer.setStyle({ dashOffset: String(offset) });
  }, 40);
  setTimeout(() => {
    clearInterval(anim);
    routeLayer.setStyle({ dashArray: null });
  }, 1600);

  // Origin marker
  L.marker([origin.latitude, origin.longitude], { icon: iconStart })
    .addTo(markerLayer)
    .bindPopup(`<div class="popup-title">${origin.name}</div>
                <div class="popup-meta"><i class="bi bi-geo-alt"></i> Starting Point</div>`);

  // Destination marker
  L.marker([destination.latitude, destination.longitude], { icon: iconEnd })
    .addTo(markerLayer)
    .bindPopup(`<div class="popup-title">${destination.name}</div>
                <div class="popup-meta"><i class="bi bi-geo-alt"></i> Destination</div>`);

  // Fit map to route
  map.fitBounds(routeLayer.getBounds(), { padding: [40, 40] });
}

// ── Draw tourist spot markers ───────────────────────────────
function drawSpotMarkers(spots) {
  spots.forEach(spot => {
    const fee = spot.entrance_fee > 0
      ? `₱ ${parseFloat(spot.entrance_fee).toFixed(2)}`
      : 'Free Entry';

    L.marker([spot.latitude, spot.longitude], { icon: iconSpot })
      .addTo(markerLayer)
      .bindPopup(`
        <div class="popup-title">${spot.name}</div>
        <div class="popup-meta">
          <i class="bi bi-building"></i> ${spot.city_name}
          &nbsp;·&nbsp;
          <span style="color:var(--sand-dark)">★</span> ${parseFloat(spot.rating).toFixed(1)}
        </div>
        <div class="popup-fee">${fee}</div>
        <div class="popup-meta mt-1">
          <i class="bi bi-clock"></i> ${spot.operating_hours || 'Hours vary'}
        </div>
      `);
  });
}

// ── Render transport options ────────────────────────────────
function renderTransportOptions(options) {
  const container = document.getElementById('transport-list');
  if (!options.length) {
    container.innerHTML = `<p class="text-muted small mb-0">No direct transport data found.</p>`;
    return;
  }

  const icons = {
    jeepney:'bi-truck-front', bus:'bi-bus-front', tricycle:'bi-bicycle',
    private_car:'bi-car-front', fx_uv:'bi-minecart'
  };
  const labels = {
    jeepney:'Jeepney', bus:'Bus', tricycle:'Tricycle',
    private_car:'Private Car', fx_uv:'FX / UV Express'
  };

  container.innerHTML = options.map((t, i) => `
    <div class="transport-option p-2 mb-2 rounded-2 ${i===0?'selected':''}"
         data-index="${i}"
         style="border:1.5px solid ${i===0?'var(--green-light)':'var(--border)'};
                background:${i===0?'var(--green-pale)':'#fff'};
                cursor:pointer;transition:all .2s">
      <div class="d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <i class="bi ${icons[t.transport_type]||'bi-truck'} fs-5 text-green"></i>
          <div>
            <div class="fw-bold small">${labels[t.transport_type]||t.transport_type}</div>
            <div style="font-size:.75rem;color:var(--text-muted)">${t.distance_km} km · ${formatDuration(t.duration_min)}</div>
          </div>
        </div>
        <div class="text-end">
          <div class="fw-bold text-green small">
            ${t.fare_php > 0 ? '₱ '+parseFloat(t.fare_php).toFixed(2) : 'Own vehicle'}
          </div>
          <div style="font-size:.7rem;color:var(--text-muted)">per person</div>
        </div>
      </div>
      ${t.notes ? `<div style="font-size:.72rem;color:var(--text-muted);margin-top:.35rem">
        <i class="bi bi-info-circle me-1"></i>${t.notes}</div>` : ''}
    </div>
  `).join('');

  selectedTransport = options[0];

  // Click to select transport
  container.querySelectorAll('.transport-option').forEach(el => {
    el.addEventListener('click', () => {
      container.querySelectorAll('.transport-option').forEach(e => {
        e.style.borderColor = 'var(--border)';
        e.style.background  = '#fff';
      });
      el.style.borderColor = 'var(--green-light)';
      el.style.background  = 'var(--green-pale)';
      selectedTransport = options[parseInt(el.dataset.index)];
    });
  });
}

// ── Render route stats ──────────────────────────────────────
function renderRouteStats(data) {
  const t = data.transport_options[0] || {};
  document.getElementById('route-stats').innerHTML = `
    <div class="route-bar flex-column gap-2 p-0 shadow-none border-0 bg-transparent">
      <div class="route-stat">
        <i class="bi bi-geo-alt-fill"></i>
        <div><div class="val">${t.distance_km || '—'} km</div><div class="lbl">Distance</div></div>
      </div>
      <div class="route-stat">
        <i class="bi bi-clock"></i>
        <div><div class="val">${t.duration_min ? formatDuration(t.duration_min) : '—'}</div><div class="lbl">Travel Time</div></div>
      </div>
      <div class="route-stat">
        <i class="bi bi-cash-coin"></i>
        <div><div class="val">${t.fare_php > 0 ? '₱ '+parseFloat(t.fare_php).toFixed(2) : 'Own vehicle'}</div><div class="lbl">Min. Fare</div></div>
      </div>
    </div>
  `;
}

// ── Render spots grid with category filter ──────────────────
function renderSpotsGrid(spots, filterCat = 'all') {
  // Build category filter buttons
  const cats = ['all', ...new Set(spots.map(s => s.category))];
  const filterBar = document.getElementById('category-filters');
  filterBar.innerHTML = cats.map(cat => `
    <button class="btn btn-sm filter-btn ${cat === filterCat ? 'active' : ''}" data-cat="${cat}"
            style="border-radius:20px;font-size:.78rem;padding:.25rem .75rem;
                   background:${cat===filterCat?'var(--green-mid)':'#fff'};
                   color:${cat===filterCat?'#fff':'var(--charcoal)'};
                   border:1.5px solid ${cat===filterCat?'var(--green-mid)':'var(--border)'}">
      ${cat === 'all' ? 'All' : catLabel(cat)}
    </button>
  `).join('');

  filterBar.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', () => renderSpotsGrid(allSpots, btn.dataset.cat));
  });

  const filtered = filterCat === 'all' ? spots : spots.filter(s => s.category === filterCat);
  document.getElementById('spots-count').textContent = `${filtered.length} spot${filtered.length!==1?'s':''}`;

  const grid = document.getElementById('spots-grid');
  if (!filtered.length) {
    grid.innerHTML = `<div class="col-12 text-center text-muted py-3 small">No spots found for this category.</div>`;
    return;
  }

  grid.innerHTML = filtered.map(spot => `
    <div class="col-12">
      <div class="d-flex gap-3 p-3 bg-white rounded-3 align-items-start"
           style="border:1px solid var(--border);cursor:pointer;transition:all .2s"
           onmouseenter="this.style.borderColor='var(--green-light)'"
           onmouseleave="this.style.borderColor='var(--border)'"
           onclick="showSpotDetail(${spot.id})">
        <div style="width:52px;height:52px;border-radius:10px;background:var(--green-pale);
                    display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0">
          ${catEmoji(spot.category)}
        </div>
        <div class="flex-grow-1 min-width-0">
          <div class="fw-bold small mb-1" style="color:var(--charcoal)">${spot.name}</div>
          <div style="font-size:.75rem;color:var(--text-muted)" class="d-flex gap-2 flex-wrap">
            <span><i class="bi bi-geo-alt"></i> ${spot.city_name}</span>
            <span>·</span>
            <span style="color:var(--sand-dark)">★ ${parseFloat(spot.rating).toFixed(1)}</span>
            <span>·</span>
            <span class="fw-bold" style="color:var(--terracotta)">
              ${spot.entrance_fee > 0 ? '₱'+parseFloat(spot.entrance_fee).toFixed(0) : 'Free'}
            </span>
          </div>
        </div>
        <i class="bi bi-chevron-right text-muted small mt-1"></i>
      </div>
    </div>
  `).join('');
}

// ── Render budget breakdown ─────────────────────────────────
async function renderBudget(routeData, spots, days, persons, budgetLevel) {
  const origin_id = routeData.origin.id;
  const dest_id   = routeData.destination.id;

  const res = await fetch(
    API_BASE + `budget.php?action=estimate&origin=${origin_id}&dest=${dest_id}&days=${days}&persons=${persons}&level=${budgetLevel}`
  ).then(r => r.json());

  if (!res.success) return;
  const b = res.data;

  // Entrance fees from spots
  const totalFees = spots.reduce((sum, s) => sum + parseFloat(s.entrance_fee), 0);

  document.getElementById('total-budget').textContent = formatPeso(b.grand_total + totalFees * persons);

  document.getElementById('budget-breakdown').innerHTML = `
    ${budgetRow('bi-bus-front',   'Transport',    b.transport * persons)}
    ${budgetRow('bi-house-door',  'Accommodation', b.accommodation * persons * days)}
    ${budgetRow('bi-cup-hot',     'Food',          b.food * persons * days)}
    ${budgetRow('bi-ticket',      'Entrance Fees', totalFees * persons)}
    ${budgetRow('bi-three-dots',  'Miscellaneous', b.misc * persons)}
  `;
}

function budgetRow(icon, label, amount) {
  return `<div class="budget-row">
    <span class="label"><i class="bi ${icon} me-2"></i>${label}</span>
    <span class="amount">${formatPeso(amount)}</span>
  </div>`;
}

// ── Render itinerary ────────────────────────────────────────
function renderItinerary(routeData, spots, days) {
  const container = document.getElementById('itinerary-days');
  const spotsPerDay = Math.ceil(spots.length / days) || 2;
  let html = '';

  for (let day = 1; day <= days; day++) {
    const daySpots = spots.slice((day-1)*spotsPerDay, day*spotsPerDay);
    const cityName = day === 1
      ? routeData.origin.name
      : (day === days ? routeData.destination.name : 'En Route');

    html += `
      <div class="itinerary-day">
        <div class="day-dot">${day}</div>
        <div class="day-label">Day ${day} — ${cityName}</div>
    `;

    if (day === 1) {
      html += itineraryItem('7:00 AM', 'bi-sun', 'Depart from ' + routeData.origin.name,
        'Prepare your bags and head to the terminal early.');
      html += itineraryItem('8:00 AM', 'bi-bus-front', 'Travel to ' + routeData.destination.name,
        routeData.transport_options[0]
          ? `Via ${routeData.transport_options[0].transport_type} · ${formatDuration(routeData.transport_options[0].duration_min)}`
          : 'Check transport options above.');
    }

    let time = day === 1 ? 10 : 8;
    daySpots.forEach(spot => {
      const timeStr = `${time > 12 ? time-12 : time}:00 ${time >= 12 ? 'PM' : 'AM'}`;
      html += itineraryItem(timeStr, 'bi-geo-alt-fill', spot.name,
        `${spot.city_name} · ${spot.entrance_fee > 0 ? '₱'+parseFloat(spot.entrance_fee).toFixed(0) : 'Free'} entrance`,
        true);
      time += 2;
    });

    html += itineraryItem('12:00 PM', 'bi-cup-hot', 'Lunch Break',
      'Try local Laguna specialties: buko pie, kesong puti, or fresh bangus.');

    if (day === days) {
      html += itineraryItem('5:00 PM', 'bi-house-check', 'Check-in / Rest',
        'Settle in at your accommodation and rest for the next day.');
    }

    html += '</div>';
  }

  container.innerHTML = html;
}

function itineraryItem(time, icon, name, desc, isSpot = false) {
  return `
    <div class="itinerary-item">
      <div class="item-time">${time}</div>
      <div>
        <i class="bi ${icon} me-1" style="color:${isSpot?'var(--terracotta)':'var(--green-light)'}"></i>
        <span class="item-name">${name}</span>
        <div class="item-desc">${desc}</div>
      </div>
    </div>
  `;
}

// ── Spot detail modal ───────────────────────────────────────
window.showSpotDetail = function(spotId) {
  const spot = allSpots.find(s => s.id == spotId);
  if (!spot) return;

  document.getElementById('modalSpotName').textContent = spot.name;
  document.getElementById('modalSpotBody').innerHTML = `
    <div class="mb-3" style="background:var(--green-pale);border-radius:10px;padding:1.5rem;text-align:center;font-size:3rem">
      ${catEmoji(spot.category)}
    </div>
    <table class="table table-sm small">
      <tr><td class="text-muted">City</td><td class="fw-bold">${spot.city_name}</td></tr>
      <tr><td class="text-muted">Category</td><td>${catLabel(spot.category)}</td></tr>
      <tr><td class="text-muted">Entrance Fee</td>
          <td class="fw-bold" style="color:var(--terracotta)">
            ${spot.entrance_fee > 0 ? '₱ '+parseFloat(spot.entrance_fee).toFixed(2) : '🎉 Free Entry'}
          </td></tr>
      <tr><td class="text-muted">Operating Hours</td><td>${spot.operating_hours || 'Hours vary'}</td></tr>
      <tr><td class="text-muted">Rating</td>
          <td><span style="color:var(--sand-dark)">★</span> ${parseFloat(spot.rating).toFixed(1)} / 5.0</td></tr>
    </table>
    <p class="text-muted small mb-2">${spot.description || ''}</p>
    <button class="btn btn-sm btn-primary-app w-100"
            onclick="map.flyTo([${spot.latitude},${spot.longitude}],15);
                     bootstrap.Modal.getInstance(document.getElementById('spotModal')).hide()">
      <i class="bi bi-geo me-2"></i>View on Map
    </button>
  `;
  new bootstrap.Modal(document.getElementById('spotModal')).show();
};

// ── Save itinerary ──────────────────────────────────────────
document.getElementById('save-itinerary-btn').addEventListener('click', async () => {
  <?php if (!is_logged_in()): ?>
    IExploreApp.toast('Please log in to save your itinerary.', 'warning');
    setTimeout(() => window.location.href = '<?= APP_URL ?>/pages/login.php', 1500);
  <?php else: ?>
    if (!routeData) { IExploreApp.toast('Plan a route first.', 'warning'); return; }
    const btn = document.getElementById('save-itinerary-btn');
    IExploreApp.setLoading(btn, true);
    const res = await fetch(API_BASE + 'itineraries.php?action=save', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        origin_id:    routeData.origin.id,
        dest_id:      routeData.destination.id,
        days:         parseInt(document.getElementById('days-select').value),
        persons:      parseInt(document.getElementById('persons-select').value),
        budget_level: document.getElementById('budget-select').value,
        transport_pref: selectedTransport?.transport_type || 'any',
      })
    }).then(r => r.json());
    IExploreApp.setLoading(btn, false);
    if (res.success) {
      IExploreApp.toast('Itinerary saved! View it in My Itineraries.', 'success');
    } else {
      IExploreApp.toast(res.message || 'Could not save itinerary.', 'error');
    }
  <?php endif; ?>
});

// ── Helpers ─────────────────────────────────────────────────
function showMapLoading(show) {
  const el = document.getElementById('map-loading');
  el.style.display = show ? 'flex' : 'none';
}

function formatDuration(min) {
  const h = Math.floor(min / 60), m = min % 60;
  if (h === 0) return `${m} min`;
  if (m === 0) return `${h} hr`;
  return `${h} hr ${m} min`;
}

function formatPeso(amount) {
  return '₱ ' + parseFloat(amount||0).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
}

function catLabel(cat) {
  const m = {nature:'Nature',heritage:'Heritage',waterfall:'Waterfall',hotspring:'Hot Spring',
             museum:'Museum',religious:'Religious',beach_lake:'Lake/Beach',adventure:'Adventure',food:'Food'};
  return m[cat] || cat;
}

function catEmoji(cat) {
  const m = {nature:'🌿',heritage:'🏛️',waterfall:'💧',hotspring:'♨️',
             museum:'🏺',religious:'⛪',beach_lake:'🏞️',adventure:'🧗',food:'🍜'};
  return m[cat] || '📍';
}

}); // end DOMContentLoaded
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>