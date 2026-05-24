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
    <div id="no-route-msg" class="d-none" style="background:var(--sand);border:1px solid var(--sand-dark);border-radius:var(--radius-sm);padding:.75rem 1rem;font-size:.82rem">
      <div class="d-flex gap-2 align-items-start">
        <i class="bi bi-map" style="color:var(--terracotta);flex-shrink:0;margin-top:.1rem"></i>
        <div>
          <div class="fw-bold mb-1" style="color:var(--green-dark)">Approximate path shown</div>
          <span class="text-muted">No road route in database for this pair. Spots along the way are still accurate — road data can be added to the <code>routes</code> table.</span>
        </div>
      </div>
    </div>

  </div>

  <!-- ── CENTER PANEL: Map ──────────────────────────────── -->
  <div class="col-lg-5 col-xl-6">

    <!-- Map -->
    <div class="position-relative mb-3" id="map-wrapper">
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
          <span>Road Route (OSRM)</span>
        </div>
      </div>

      <!-- Map spot count badge (top-right) -->
      <div id="map-spots-badge" class="position-absolute top-0 end-0 m-2 d-none"
           style="z-index:999">
        <span class="badge rounded-pill px-3 py-2"
              style="background:var(--green-dark);color:#fff;font-size:.78rem;box-shadow:0 2px 8px rgba(0,0,0,.2)">
          <i class="bi bi-geo-alt-fill me-1"></i>
          <span id="map-spots-badge-count">0</span> spots on map
        </span>
      </div>

      <!-- ── Slide-in Spot Detail Panel ────────────────────── -->
      <div id="map-spot-panel" class="map-spot-panel">
        <button id="map-spot-panel-close" class="map-spot-panel-close" title="Close">
          <i class="bi bi-x-lg"></i>
        </button>

        <!-- Emoji / category header -->
        <div id="msp-emoji-header" class="msp-emoji-header"></div>

        <div class="p-3">
          <!-- Category badge + name -->
          <div id="msp-badge" class="mb-1"></div>
          <h5 id="msp-name" class="msp-name mb-1"></h5>
          <div id="msp-city" class="msp-meta mb-2"></div>

          <!-- Rating bar -->
          <div id="msp-rating" class="msp-rating mb-3"></div>

          <!-- Quick info chips -->
          <div id="msp-chips" class="msp-chips mb-3"></div>

          <!-- Description -->
          <p id="msp-desc" class="msp-desc mb-3"></p>

          <!-- Actions -->
          <div class="d-flex gap-2">
            <button id="msp-fly-btn" class="btn btn-sm btn-primary-app flex-grow-1">
              <i class="bi bi-crosshair me-1"></i>Center on Map
            </button>
            <button id="msp-add-btn" class="btn btn-sm btn-outline-app">
              <i class="bi bi-images me-1"></i>Full Details
            </button>
          </div>
        </div>
      </div>

      <!-- Loading overlay -->
      <div id="map-loading" class="position-absolute top-0 start-0 w-100 h-100"
           style="display:none;background:rgba(255,255,255,.7);border-radius:var(--radius);z-index:1000;align-items:center;justify-content:center">
        <div class="text-center">
          <div class="spinner-app mb-2" style="width:2rem;height:2rem;border-width:3px"></div>
          <div class="small text-muted">Calculating road route…</div>
        </div>
      </div>
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

<!-- spotModal removed: replaced by slide-in .map-spot-panel -->

<!-- ── Hide LRM default turn-by-turn panel ─────────────────── -->
<style>
  .leaflet-routing-container { display: none !important; }
</style>

<!-- ── JS ──────────────────────────────────────────────────── -->
<script>
document.addEventListener('DOMContentLoaded', function() {
/* ============================================================
   TRIP PLANNER — Main JS
   ============================================================ */

const API_BASE = '<?= APP_URL ?>/api/';

// ── Map init ────────────────────────────────────────────────
const map = L.map('trip-map', { zoomControl: true }).setView([14.17, 121.24], 10);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
  maxZoom: 18,
}).addTo(map);

setTimeout(() => { map.invalidateSize(); }, 100);
setTimeout(() => { map.invalidateSize(); }, 400);
setTimeout(() => { map.invalidateSize(); }, 800);
window.addEventListener('resize', () => map.invalidateSize());

// State
let routeControl  = null;   // LRM routing control (replaces routeLayer)
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

// Auto-plan if pre-filled from query string
<?php if ($pre_origin && $pre_dest): ?>
setTimeout(planRoute, 500);
<?php endif; ?>

// ── Main plan function ──────────────────────────────────────
async function planRoute() {
  const origin  = document.getElementById('origin-select').value;
  const dest    = document.getElementById('dest-select').value;
  const days    = parseInt(document.getElementById('days-select').value);
  const persons = parseInt(document.getElementById('persons-select').value);
  const budget  = document.getElementById('budget-select').value;

  if (!origin || !dest) {
    IExploreApp.toast('Please select both origin and destination.', 'warning');
    return;
  }
  if (origin === dest) {
    IExploreApp.toast('Origin and destination must be different cities.', 'warning');
    return;
  }

  showMapLoading(true);
  IExploreApp.setLoading(document.getElementById('plan-btn'), true);

  // Clear previous markers (routing control cleared inside drawRoute)
  markerLayer.clearLayers();

  try {
    const [routeRes, spotsRes] = await Promise.all([
      fetch(API_BASE + `routes.php?action=route&origin=${origin}&dest=${dest}`).then(r => r.json()),
      fetch(API_BASE + `routes.php?action=spots&origin=${origin}&dest=${dest}`).then(r => r.json()),
    ]);

    if (!routeRes.success) {
      IExploreApp.toast(routeRes.message || 'Route not found.', 'error');
      showMapLoading(false);
      IExploreApp.setLoading(document.getElementById('plan-btn'), false);
      return;
    }

    routeData = routeRes.data;
    allSpots  = spotsRes.success ? spotsRes.data : [];

    drawRoute(routeData);
    drawSpotMarkers(allSpots);

    renderTransportOptions(routeData.transport_options);
    renderRouteStats(routeData);
    renderSpotsGrid(allSpots);
    renderBudget(routeData, allSpots, days, persons, budget);
    renderItinerary(routeData, allSpots, days);

    document.getElementById('route-summary').classList.remove('d-none');
    document.getElementById('spots-section').classList.remove('d-none');
    document.getElementById('budget-panel-wrap').classList.remove('d-none');
    document.getElementById('itinerary-panel').classList.remove('d-none');
    document.getElementById('right-panel-placeholder').classList.add('d-none');
    document.getElementById('no-route-msg').classList.toggle('d-none', routeData.has_route);

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

// ── Draw road-following route via OSRM ──────────────────────
function drawRoute(data) {
  const { origin, destination } = data;

  // Remove previous routing control
  if (routeControl) {
    routeControl.remove();
    routeControl = null;
  }

  const startLatLng = L.latLng(parseFloat(origin.latitude),      parseFloat(origin.longitude));
  const endLatLng   = L.latLng(parseFloat(destination.latitude), parseFloat(destination.longitude));

  routeControl = L.Routing.control({
    waypoints: [startLatLng, endLatLng],
    router: L.Routing.osrmv1({
      serviceUrl: 'https://router.project-osrm.org/route/v1',
      profile: 'driving',
      useHints: false,
    }),
    lineOptions: {
      styles: [{ color: '#2d6a4f', weight: 5, opacity: 0.85 }],
      extendToWaypoints: true,
      missingRouteTolerance: 0,
    },
    // Override default markers with our custom icons
    createMarker: function(i, waypoint) {
      const icon  = i === 0 ? iconStart : iconEnd;
      const name  = i === 0 ? origin.name : destination.name;
      const label = i === 0 ? 'Starting Point' : 'Destination';
      return L.marker(waypoint.latLng, { icon })
        .bindPopup(`<div class="popup-title">${name}</div>
                    <div class="popup-meta"><i class="bi bi-geo-alt"></i> ${label}</div>`);
    },
    show: false,              // hide turn-by-turn instructions panel
    addWaypoints: false,      // disable drag-to-add waypoints
    routeWhileDragging: false,
    fitSelectedRoutes: true,
    collapsible: false,
  }).addTo(map);

  // Fit map bounds once route is calculated
  routeControl.on('routesfound', function(e) {
    const coords = e.routes[0].coordinates;
    const bounds = L.latLngBounds(coords.map(c => [c.lat, c.lng]));
    map.fitBounds(bounds, { padding: [40, 40] });
  });

  // Fallback: draw straight dashed line if OSRM fails
  routeControl.on('routingerror', function(e) {
    console.warn('OSRM routing error, falling back to straight line:', e);
    const fallback = L.polyline([startLatLng, endLatLng], {
      color: '#2d6a4f',
      weight: 4,
      opacity: 0.6,
      dashArray: '10 6',
    }).addTo(map);

    // Add fallback markers manually since LRM failed
    L.marker(startLatLng, { icon: iconStart })
      .addTo(markerLayer)
      .bindPopup(`<div class="popup-title">${origin.name}</div>
                  <div class="popup-meta">Starting Point</div>`);
    L.marker(endLatLng, { icon: iconEnd })
      .addTo(markerLayer)
      .bindPopup(`<div class="popup-title">${destination.name}</div>
                  <div class="popup-meta">Destination</div>`);

    map.fitBounds([startLatLng, endLatLng], { padding: [40, 40] });

    // Replace routeControl with a removable stub
    routeControl = { remove: () => map.removeLayer(fallback) };

    IExploreApp.toast('Using approximate route (road data unavailable).', 'info');
  });
}

// ── Spot marker registry (id → marker) ─────────────────────
const spotMarkers = {};

// ── Draw tourist spot markers ───────────────────────────────
function drawSpotMarkers(spots) {
  // Update map badge
  const badge = document.getElementById('map-spots-badge');
  const badgeCount = document.getElementById('map-spots-badge-count');
  if (spots.length > 0) {
    badge.classList.remove('d-none');
    badgeCount.textContent = spots.length;
  }

  spots.forEach(spot => {
    const fee = spot.entrance_fee > 0
      ? `₱ ${parseFloat(spot.entrance_fee).toFixed(2)}`
      : 'Free Entry';

    const stars = '★'.repeat(Math.round(spot.rating)) + '☆'.repeat(5 - Math.round(spot.rating));

    // Rich Leaflet popup (quick preview on hover/click)
    const popup = L.popup({
      maxWidth: 240,
      className: 'spot-popup-rich',
      closeButton: true,
    }).setContent(`
      <div class="popup-rich-header" style="background:var(--green-pale);padding:.75rem 1rem .5rem;margin:-.4rem -.4rem .5rem;border-radius:8px 8px 0 0;text-align:center;font-size:2rem;line-height:1">
        ${catEmoji(spot.category)}
      </div>
      <div style="padding:0 .25rem">
        <div class="popup-title" style="font-size:.95rem">${spot.name}</div>
        <div class="popup-meta mb-1">
          <i class="bi bi-geo-alt" style="color:var(--green-mid)"></i>
          ${spot.city_name}
        </div>
        <div style="color:var(--sand-dark);font-size:.8rem;letter-spacing:.05em;margin-bottom:.35rem">${stars}
          <span style="color:var(--text-muted);margin-left:.25rem">${parseFloat(spot.rating).toFixed(1)}</span>
        </div>
        <div style="display:flex;gap:.5rem;align-items:center;margin-bottom:.5rem">
          <span style="background:var(--terracotta);color:#fff;font-size:.72rem;font-weight:700;padding:.15rem .5rem;border-radius:20px">
            ${fee}
          </span>
          <span style="font-size:.72rem;color:var(--text-muted)">
            <i class="bi bi-clock"></i> ${spot.operating_hours || 'Hours vary'}
          </span>
        </div>
        <a
          href="<?= APP_URL ?>/pages/spot-detail.php?id=${spot.id}"
          style="display:block;width:100%;padding:.35rem;background:var(--green-dark);color:#fff;border:none;border-radius:6px;font-size:.8rem;cursor:pointer;font-family:'DM Sans',sans-serif;text-align:center;text-decoration:none">
          <i class="bi bi-images me-1"></i>See Full Details & Photos
        </a>
      </div>
    `);

    const marker = L.marker([spot.latitude, spot.longitude], { icon: iconSpot })
      .addTo(markerLayer)
      .bindPopup(popup);

    // When marker popup opens directly, close slide-in panel to avoid overlap
    marker.on('popupopen', () => {
      document.getElementById('map-spot-panel').classList.remove('open');
      highlightSpotCard(spot.id);
    });
    marker.on('popupclose', () => {
      // Deactivate card highlight when popup is closed
      const card = document.getElementById(`spot-card-${spot.id}`);
      if (card) {
        card.classList.remove('spot-card-active');
        card.style.borderColor = 'var(--border)';
        card.style.background  = '#fff';
      }
    });

    spotMarkers[spot.id] = marker;
  });
}

// ── Render transport options ────────────────────────────────
function renderTransportOptions(options) {
  const container = document.getElementById('transport-list');
  if (!options.length) {
    container.innerHTML = `
      <div class="text-center py-2">
        <i class="bi bi-signpost-2 d-block mb-2" style="font-size:1.6rem;color:var(--green-pale)"></i>
        <p class="text-muted small mb-1 fw-500">No scheduled transport data</p>
        <p class="text-muted" style="font-size:.75rem;line-height:1.5">
          Try a <strong>private car</strong> or <strong>tricycle</strong> for this route.
          Check local terminals for jeepney schedules.
        </p>
      </div>`;
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
  const distVal  = t.distance_km  ? `${t.distance_km} km`        : '<span style="color:var(--text-muted);font-size:.85rem">Estimating…</span>';
  const timeVal  = t.duration_min ? formatDuration(t.duration_min): '<span style="color:var(--text-muted);font-size:.85rem">Varies</span>';
  const fareVal  = t.fare_php > 0 ? '₱ ' + parseFloat(t.fare_php).toFixed(2)
                                  : '<span style="font-size:.82rem">Own vehicle</span>';
  document.getElementById('route-stats').innerHTML = `
    <div class="route-bar flex-column gap-2 p-0 shadow-none border-0 bg-transparent">
      <div class="route-stat">
        <i class="bi bi-geo-alt-fill"></i>
        <div><div class="val">${distVal}</div><div class="lbl">Distance</div></div>
      </div>
      <div class="route-stat">
        <i class="bi bi-clock"></i>
        <div><div class="val">${timeVal}</div><div class="lbl">Travel Time</div></div>
      </div>
      <div class="route-stat">
        <i class="bi bi-cash-coin"></i>
        <div><div class="val">${fareVal}</div><div class="lbl">Min. Fare</div></div>
      </div>
    </div>
  `;
}

// ── Render spots grid with category filter ──────────────────
function renderSpotsGrid(spots, filterCat = 'all') {
  const cats = ['all', ...new Set(spots.map(s => s.category))];
  const filterBar = document.getElementById('category-filters');
  filterBar.innerHTML = cats.map(cat => `
    <button class="btn btn-sm filter-btn ${cat === filterCat ? 'active' : ''}" data-cat="${cat}"
            style="border-radius:20px;font-size:.78rem;padding:.25rem .75rem;
                   background:${cat===filterCat?'var(--green-mid)':'#fff'};
                   color:${cat===filterCat?'#fff':'var(--charcoal)'};
                   border:1.5px solid ${cat===filterCat?'var(--green-mid)':'var(--border)'}">
      ${catEmoji(cat !== 'all' ? cat : '')} ${cat === 'all' ? 'All' : catLabel(cat)}
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
      <div class="spot-card-row d-flex gap-3 p-3 bg-white rounded-3 align-items-start"
           id="spot-card-${spot.id}"
           data-spot-id="${spot.id}"
           style="border:1.5px solid var(--border);cursor:pointer;transition:all .22s"
           onmouseenter="this.style.borderColor='var(--green-light)';this.style.background='var(--green-pale)'"
           onmouseleave="this.style.borderColor=this.classList.contains('spot-card-active')?'var(--green-mid)':'var(--border)';this.style.background=this.classList.contains('spot-card-active')?'#eaf4ef':'#fff'"
           onclick="flyToSpot(${spot.id})">
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
        <div class="d-flex flex-column align-items-end gap-1">
          <i class="bi bi-map text-green small" title="Fly to on map"></i>
          <i class="bi bi-chevron-right text-muted small"></i>
        </div>
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

  const totalFees = spots.reduce((sum, s) => sum + parseFloat(s.entrance_fee), 0);

  document.getElementById('total-budget').textContent = formatPeso(b.grand_total + totalFees * persons);

  document.getElementById('budget-breakdown').innerHTML = `
    ${budgetRow('bi-bus-front',   'Transport',     b.transport * persons)}
    ${budgetRow('bi-house-door',  'Accommodation', b.accommodation * persons * days)}
    ${budgetRow('bi-cup-hot',     'Food',          b.food * persons * days)}
    ${budgetRow('bi-ticket',      'Entrance Fees', totalFees * persons)}
    ${budgetRow('bi-three-dots',  'Miscellaneous', b.misc * persons)}
  `;
}

function budgetRow(icon, label, amount) {
  return `<div class="budget-row">
    <span class="label" style="display:flex;align-items:center;gap:.5rem">
      <i class="bi ${icon}"></i>${label}
    </span>
    <span class="amount" style="white-space:nowrap">${formatPeso(amount)}</span>
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
    <div class="itinerary-item" style="display:flex;align-items:flex-start;gap:.75rem;padding:.9rem 1.1rem">
      <div class="item-time" style="min-width:64px;flex-shrink:0;white-space:nowrap">${time}</div>
      <div style="flex:1;min-width:0">
        <div style="display:flex;align-items:center;gap:.35rem;flex-wrap:nowrap;margin-bottom:.2rem">
          <i class="bi ${icon}" style="color:${isSpot?'var(--terracotta)':'var(--green-light)'};flex-shrink:0"></i>
          <span class="item-name" style="font-weight:600;font-size:.95rem">${name}</span>
        </div>
        <div class="item-desc" style="font-size:.82rem;color:var(--text-muted)">${desc}</div>
      </div>
    </div>
  `;
}

// ── Fly to spot on map + open panel ────────────────────────
window.flyToSpot = function(spotId) {
  const spot = allSpots.find(s => s.id == spotId);
  if (!spot) return;

  // Close any open popups first to avoid overlap
  map.closePopup();

  // Fly map to spot
  map.flyTo([spot.latitude, spot.longitude], 15, { animate: true, duration: 0.8 });

  // Open the slide-in detail panel only (no popup — keeps it clean)
  openSpotPanel(spotId);
  highlightSpotCard(spotId);
};

// ── Open slide-in panel with spot details ──────────────────
window.openSpotPanel = function(spotId) {
  const spot = allSpots.find(s => s.id == spotId);
  if (!spot) return;

  const panel     = document.getElementById('map-spot-panel');
  const fee       = spot.entrance_fee > 0
    ? `₱ ${parseFloat(spot.entrance_fee).toFixed(2)}`
    : '🎉 Free Entry';
  const fullStars = Math.round(spot.rating);
  const starsHtml = Array.from({length:5}, (_,i) =>
    `<i class="bi ${i < fullStars ? 'bi-star-fill' : 'bi-star'}"
        style="color:${i < fullStars ? 'var(--sand-dark)' : '#ccc'};font-size:.85rem"></i>`
  ).join('') + `<span style="font-size:.8rem;color:var(--text-muted);margin-left:.3rem">${parseFloat(spot.rating).toFixed(1)}</span>`;

  document.getElementById('msp-emoji-header').textContent = catEmoji(spot.category);
  document.getElementById('msp-emoji-header').style.background = catBg(spot.category);
  document.getElementById('msp-badge').innerHTML =
    `<span class="badge-category badge-${spot.category}">${catLabel(spot.category)}</span>`;
  document.getElementById('msp-name').textContent  = spot.name;
  document.getElementById('msp-city').innerHTML    =
    `<i class="bi bi-geo-alt-fill me-1" style="color:var(--green-mid)"></i>${spot.city_name}`;
  document.getElementById('msp-rating').innerHTML  = starsHtml;
  document.getElementById('msp-chips').innerHTML   = `
    <span class="msp-chip"><i class="bi bi-ticket me-1"></i>${fee}</span>
    <span class="msp-chip"><i class="bi bi-clock me-1"></i>${spot.operating_hours || 'Hours vary'}</span>
  `;
  document.getElementById('msp-desc').textContent =
    spot.description || 'No description available.';

  // Button actions
  document.getElementById('msp-fly-btn').onclick = () => {
    map.flyTo([spot.latitude, spot.longitude], 16, { animate: true, duration: 0.6 });
    const marker = spotMarkers[spot.id];
    if (marker) setTimeout(() => marker.openPopup(), 700);
  };
  document.getElementById('msp-add-btn').onclick = () => {
    window.location.href = `<?= APP_URL ?>/pages/spot-detail.php?id=${spot.id}`;
  };

  panel.classList.add('open');
  highlightSpotCard(spotId);
};

// ── Close panel when clicking empty map area ────────────────
map.on('click', () => {
  document.getElementById('map-spot-panel').classList.remove('open');
  document.querySelectorAll('.spot-card-active').forEach(el => {
    el.classList.remove('spot-card-active');
    el.style.borderColor = 'var(--border)';
    el.style.background  = '#fff';
  });
});

// ── Close panel button ──────────────────────────────────────
document.getElementById('map-spot-panel-close').addEventListener('click', () => {
  document.getElementById('map-spot-panel').classList.remove('open');
  document.querySelectorAll('.spot-card-active').forEach(el => {
    el.classList.remove('spot-card-active');
    el.style.borderColor = 'var(--border)';
    el.style.background  = '#fff';
  });
});

// ── Highlight matching spot card in the list ────────────────
function highlightSpotCard(spotId) {
  document.querySelectorAll('.spot-card-row').forEach(el => {
    el.classList.remove('spot-card-active');
    el.style.borderColor = 'var(--border)';
    el.style.background  = '#fff';
  });
  const card = document.getElementById(`spot-card-${spotId}`);
  if (card) {
    card.classList.add('spot-card-active');
    card.style.borderColor = 'var(--green-mid)';
    card.style.background  = '#eaf4ef';
    card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
}

// ── Legacy showSpotDetail kept for backward compat ──────────
window.showSpotDetail = window.flyToSpot;

// ── Category background color helper ───────────────────────
function catBg(cat) {
  const m = {
    nature:'#d8f3dc', heritage:'#fef3c7', waterfall:'#dbeafe',
    hotspring:'#ffe4e6', museum:'#f3e8ff', religious:'#fff7ed',
    beach_lake:'#e0f2fe', adventure:'#fef9c3', food:'#fce7f3'
  };
  return m[cat] || 'var(--green-pale)';
}

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
        origin_id:      routeData.origin.id,
        dest_id:        routeData.destination.id,
        days:           parseInt(document.getElementById('days-select').value),
        persons:        parseInt(document.getElementById('persons-select').value),
        budget_level:   document.getElementById('budget-select').value,
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
  document.getElementById('map-loading').style.display = show ? 'flex' : 'none';
}

function formatDuration(min) {
  const h = Math.floor(min / 60), m = min % 60;
  if (h === 0) return `${m} min`;
  if (m === 0) return `${h} hr`;
  return `${h} hr ${m} min`;
}

function formatPeso(amount) {
  return '₱ ' + parseFloat(amount||0).toLocaleString('en-PH',
    { minimumFractionDigits:2, maximumFractionDigits:2 });
}

function catLabel(cat) {
  const m = {
    nature:'Nature', heritage:'Heritage', waterfall:'Waterfall',
    hotspring:'Hot Spring', museum:'Museum', religious:'Religious',
    beach_lake:'Lake/Beach', adventure:'Adventure', food:'Food'
  };
  return m[cat] || cat;
}

function catEmoji(cat) {
  const m = {
    nature:'🌿', heritage:'🏛️', waterfall:'💧', hotspring:'♨️',
    museum:'🏺', religious:'⛪', beach_lake:'🏞️', adventure:'🧗', food:'🍜'
  };
  return m[cat] || '📍';
}

}); // end DOMContentLoaded
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>