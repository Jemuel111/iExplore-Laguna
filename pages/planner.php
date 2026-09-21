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
// Comma-separated list of spot IDs from a previously saved itinerary
// (via "Re-plan" on My Itineraries) — when present, the exact saved
// spots are restored into the itinerary instead of letting the page
// auto-pick a fresh set of top-rated spots along the route.
$pre_spot_ids = array_filter(array_map('intval', explode(',', input('spot_ids', 'get', ''))));

// Load all cities for dropdowns
$cities = db_fetch_all("SELECT id, name, slug, latitude, longitude FROM cities ORDER BY name");
$budgetRanges = get_budget_level_ranges();

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
<section class="py-4" style="background:linear-gradient(135deg,var(--maroon-dark),var(--maroon-mid));color:#fff">
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
      <h6 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">
        <i class="bi bi-signpost-split me-2" style="color:var(--maroon-light)"></i>Plan Your Route
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

      <div class="mb-3">
        <label class="form-label">Travel Date <span class="text-muted fw-normal">(optional)</span></label>
        <input type="date" class="form-control" id="travel-date-input" min="<?= date('Y-m-d') ?>"
               value="<?= e(input('travel_date', 'get', '')) ?>">
        <div class="form-text">Lets us warn you if a spot is closed on your actual trip date.</div>
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
          <option value="budget"   <?= $pre_budget==='budget'   ? 'selected':'' ?>>Budget (₱<?= number_format($budgetRanges['budget']['min']) ?>–<?= number_format($budgetRanges['budget']['max']) ?>/day)</option>
          <option value="midrange" <?= $pre_budget==='midrange' ? 'selected':'' ?>>Mid-range (₱<?= number_format($budgetRanges['midrange']['min']) ?>–<?= number_format($budgetRanges['midrange']['max']) ?>/day)</option>
          <option value="upscale"  <?= $pre_budget==='upscale'  ? 'selected':'' ?>>Upscale (₱<?= number_format($budgetRanges['upscale']['min']) ?>–<?= number_format($budgetRanges['upscale']['max']) ?>/day)</option>
        </select>
        <div class="form-text">Per person, per day (food + accommodation)</div>
      </div>

      <button class="btn btn-primary-app w-100" id="plan-btn">
        <i class="bi bi-search me-2"></i>Find Route
      </button>
    </div>

    <!-- Route Summary (hidden until searched) -->
    <div id="route-summary" class="d-none">

      <!-- Transport options -->
      <div class="form-panel mb-3">
        <h6 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">
          <i class="bi bi-bus-front me-2" style="color:var(--maroon-light)"></i>Transport Options
        </h6>
        <div id="transport-list"></div>
      </div>

      <!-- Quick stats -->
      <div class="form-panel mb-3" id="route-stats-panel">
        <h6 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">
          <i class="bi bi-info-circle me-2" style="color:var(--maroon-light)"></i>Route Info
        </h6>
        <div id="route-stats"></div>
      </div>

    </div>

    <!-- No route found message -->
    <div id="no-route-msg" class="d-none" style="background:var(--sand);border:1px solid var(--sand-dark);border-radius:var(--radius-sm);padding:.75rem 1rem;font-size:.82rem">
      <div class="d-flex gap-2 align-items-start">
        <i class="bi bi-map" style="color:var(--terracotta);flex-shrink:0;margin-top:.1rem"></i>
        <div>
          <div class="fw-bold mb-1" style="color:var(--maroon-dark)">Approximate path shown</div>
          <span class="text-muted">No road route in database for this pair. Spots along the way are still accurate — road data can be added to the <code>routes</code> table.</span>
        </div>
      </div>
    </div>

  </div>

  <!-- ── CENTER PANEL: Map ──────────────────────────────── -->
  <div class="col-lg-4 col-xl-4">

    <!-- Map -->
    <div class="position-relative mb-2" id="map-wrapper" style="overflow:hidden">
            <div id="trip-map"></div>

      <div class="traffic-map-legend" id="traffic-map-legend" hidden>
        <div class="traffic-map-legend-title">Live Traffic</div>
        <div class="traffic-map-legend-item"><span class="traffic-map-dot traffic-free"></span>Free flow</div>
        <div class="traffic-map-legend-item"><span class="traffic-map-dot traffic-moderate"></span>Moderate</div>
        <div class="traffic-map-legend-item"><span class="traffic-map-dot traffic-heavy"></span>Heavy</div>
      </div>

      <div class="planner-traffic-control" id="planner-traffic-control">
        <button type="button" id="traffic-toggle-btn" class="btn btn-sm btn-light planner-traffic-btn" aria-pressed="false">
          <i class="bi bi-traffic-cone me-1"></i>Traffic
        </button>
      </div>

      <!-- Map spot count badge (top-right) -->
      <div id="map-spots-badge" class="position-absolute top-0 end-0 m-2 d-none"
           style="z-index:999">
        <span class="badge rounded-pill px-3 py-2"
              style="background:var(--maroon-dark);color:#fff;font-size:.78rem;box-shadow:0 2px 8px rgba(0,0,0,.2)">
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
    </div><!-- /#map-wrapper -->

    <!-- Map legend — a plain caption strip under the map, not floating on top of it -->
    <div class="d-flex align-items-center justify-content-center flex-wrap gap-3 mb-3"
         style="font-size:.75rem;color:var(--text-muted);padding:.4rem 0">
      <div class="d-flex align-items-center gap-2">
        <span style="width:12px;height:12px;background:var(--maroon-mid);border-radius:50%;display:inline-block;flex-shrink:0"></span>
        <span>Start / End</span>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span style="width:12px;height:12px;background:var(--terracotta);border-radius:50%;display:inline-block;flex-shrink:0"></span>
        <span>Tourist Spot</span>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span style="width:16px;height:3px;background:var(--maroon-mid);display:inline-block;border-radius:2px;flex-shrink:0"></span>
        <span>Road Route (ORS)</span>
      </div>
    </div>

    <!-- Spots along route -->
    <div id="spots-section" class="d-none">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="fw-bold mb-0" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">
          <i class="bi bi-geo-alt-fill me-2" style="color:var(--terracotta)"></i>
          Tourist Spots Along This Route
        </h6>
        <span id="spots-count" class="badge rounded-pill" style="background:var(--maroon-pale);color:var(--maroon-dark)"></span>
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
  <div class="col-lg-5 col-xl-5">

    <div id="right-panel-placeholder" class="form-panel text-center py-5" style="color:var(--text-muted)">
      <i class="bi bi-map fs-1 d-block mb-3" style="color:var(--maroon-pale)"></i>
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
        <h5 class="fw-bold mb-0" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">
          <i class="bi bi-journal-bookmark me-2" style="color:var(--maroon-light)"></i>Suggested Itinerary
        </h5>
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
/* Slide-in spot detail panel over the map.
   This block was empty, so .map-spot-panel had no positioning or
   hide/show rule at all — it rendered as a plain static block on every
   page load (visible even before any spot was clicked), and its
   "Center on Map" / "Full Details" buttons only get their onclick
   handlers wired up inside openSpotPanel(), which runs when a spot
   marker or card is actually clicked. So without this CSS, the panel
   you see on load has buttons with no handler attached yet — they
   look real but do nothing until a spot has actually been opened. */
.map-spot-panel {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  width: min(320px, 100%);
  background: #fff;
  box-shadow: -4px 0 16px rgba(0,0,0,.12);
  border-left: 1px solid var(--border);
  transform: translateX(100%);
  transition: transform .25s ease;
  z-index: 1001;
  overflow-y: auto;
  pointer-events: none;
}

.map-spot-panel.open {
  transform: translateX(0);
  pointer-events: auto;
}

.map-spot-panel-close {
  position: absolute;
  top: 10px;
  right: 10px;
  z-index: 2;
  width: 32px;
  height: 32px;
  border: 1px solid var(--border);
  background: rgba(255,255,255,.92);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.msp-emoji-header {
  height: 110px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  color: #fff;
}

.msp-name { font-weight: 700; color: var(--charcoal); }
.msp-meta { font-size: .85rem; color: var(--text-muted); }
.msp-desc { font-size: .85rem; color: var(--text-muted); line-height: 1.5; }

.msp-chips { display: flex; flex-wrap: wrap; gap: .4rem; }
.msp-chip {
  font-size: .75rem;
  padding: .25rem .55rem;
  border: 1px solid var(--border);
  border-radius: 999px;
  color: var(--charcoal);
  background: var(--sand, #f7f2ea);
}
</style>

<!-- ── JS ──────────────────────────────────────────────────── -->
<script>
document.addEventListener('DOMContentLoaded', function() {
/* ============================================================
   TRIP PLANNER — Main JS
   ============================================================ */

const API_BASE = '<?= APP_URL ?>/api/';

// Map colors (route lines, pins) can't use CSS var() directly — Leaflet
// takes literal color strings, not CSS custom properties. Reading the
// *computed* value at runtime instead of hardcoding a hex means these
// actually follow whatever color the admin sets in Site Settings,
// instead of being frozen at whatever the brand color happened to be
// when this file was last edited.
function themeColor(varName, fallback) {
  const v = getComputedStyle(document.body).getPropertyValue(varName).trim();
  return v || fallback;
}
const THEME_DARK  = themeColor('--maroon-dark',  '#B0281C');
const THEME_MID   = themeColor('--maroon-mid',   '#D9481F');
const THEME_LIGHT = themeColor('--maroon-light', '#FF7A45');

// ── Map init ────────────────────────────────────────────────
const map = L.map('trip-map', { zoomControl: true }).setView([14.17, 121.24], 10);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
  maxZoom: 18,
}).addTo(map);

// ── Optional TomTom live traffic layer ──────────────────────
// The API key is injected from includes/config.php. The layer is
// deliberately off by default so the existing map behaves exactly
// as before until the visitor chooses to show traffic.
const TOMTOM_API_KEY = <?= json_encode(defined('TOMTOM_API_KEY') ? TOMTOM_API_KEY : '') ?>;
let trafficLayer = null;
let trafficEnabled = false;

function setTrafficEnabled(enabled) {
  const btn = document.getElementById('traffic-toggle-btn');
  const legend = document.getElementById('traffic-map-legend');

  if (!TOMTOM_API_KEY) {
    IExploreApp.toast('Live traffic is not configured yet. Add your TomTom API key in includes/config.php.', 'info');
    if (btn) btn.setAttribute('aria-pressed', 'false');
    return;
  }

  trafficEnabled = !!enabled;

  if (trafficEnabled) {
    if (!trafficLayer) {
      // TomTom Raster Flow Tiles. Relative style highlights congestion
      // compared with normal/free-flow speed.
      trafficLayer = L.tileLayer(
        'https://api.tomtom.com/traffic/map/4/tile/flow/relative/{z}/{x}/{y}.png?key=' + encodeURIComponent(TOMTOM_API_KEY),
        {
          tileSize: 256,
          opacity: 0.72,
          maxZoom: 18,
          attribution: 'Traffic data © TomTom'
        }
      );
    }
    trafficLayer.addTo(map);
    if (btn) {
      btn.classList.add('active');
      btn.setAttribute('aria-pressed', 'true');
      btn.innerHTML = '<i class=\"bi bi-traffic-cone me-1\"></i>Hide Traffic';
    }
    if (legend) legend.hidden = false;
  } else {
    if (trafficLayer && map.hasLayer(trafficLayer)) map.removeLayer(trafficLayer);
    if (btn) {
      btn.classList.remove('active');
      btn.setAttribute('aria-pressed', 'false');
      btn.innerHTML = '<i class=\"bi bi-traffic-cone me-1\"></i>Traffic';
    }
    if (legend) legend.hidden = true;
  }

  // Live traffic changes the itinerary's estimated arrival times (see
  // getTrafficAdjustment), not just the map overlay — so if a schedule is
  // already on screen, recompute it right away rather than leaving stale
  // times up until the visitor happens to replan.
  if (lastItineraryRender) {
    const { routeData, spots, days, forceIncludeAll } = lastItineraryRender;
    renderItinerary(routeData, spots, days, forceIncludeAll);
  }
}

document.getElementById('traffic-toggle-btn')?.addEventListener('click', () => {
  setTrafficEnabled(!trafficEnabled);
});

setTimeout(() => { map.invalidateSize(); }, 100);
setTimeout(() => { map.invalidateSize(); }, 400);
setTimeout(() => { map.invalidateSize(); }, 800);
window.addEventListener('resize', () => map.invalidateSize());

// State
let markerLayer   = L.layerGroup().addTo(map);
let allSpots      = [];
let routeData     = null;
let selectedTransport = null;
let travelDate    = null;   // 'YYYY-MM-DD' or null — used to check spot closures
let includedSpotIds = [];   // spot IDs actually placed into the itinerary (excludes closed ones)

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

const iconStart = makeIcon(THEME_MID, '▶', 34);
const iconEnd   = makeIcon(THEME_DARK, '■', 34);
const iconSpot  = makeIcon('#c77c48', '★', 28);

// ── Distance / travel-time estimation ───────────────────────
// Straight-line (Haversine) distance between two coordinates, in km.
function haversineKm(lat1, lon1, lat2, lon2) {
  const R = 6371; // Earth radius in km
  const toRad = deg => deg * Math.PI / 180;
  const dLat = toRad(lat2 - lat1);
  const dLon = toRad(lon2 - lon1);
  const a = Math.sin(dLat / 2) ** 2 +
            Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
            Math.sin(dLon / 2) ** 2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

// Assumed average road speed (km/h) for local/provincial travel around
// Laguna, factoring in traffic, stops, and non-highway roads. Used to turn
// a straight-line distance into a rough "time to reach" estimate.
const AVG_SPEED_KMH = 30;

function estimateTravelMinutes(km) {
  return Math.max(1, Math.round((km / AVG_SPEED_KMH) * 60));
}

// Short "X km · ~Y min" label. isApprox adds a tilde on the distance too,
// since these are straight-line, not road, distances.
function distanceTimeLabel(km) {
  const mins = estimateTravelMinutes(km);
  return `${km < 10 ? km.toFixed(1) : Math.round(km)} km · ~${formatDuration(mins)}`;
}

// ── Live traffic-adjusted travel time ───────────────────────
// Reuses the same TomTom Flow Segment data that powers the map overlay
// (see setTrafficEnabled below) to nudge the itinerary's estimated leg
// times when the current is slower than usual — e.g. a "1:00 PM" arrival
// becomes "1:20 PM" when the road it's traveling on is showing heavy
// traffic right now, instead of always assuming the same average speed.

// Cached per lookup so re-rendering the itinerary, or two legs that pass
// near the same spot, don't each cost a separate API call. Keyed to ~1km
// precision — plenty coarse for "is this stretch of road congested".
const trafficCache = new Map();

// Turns a single point's live conditions into a multiplier applied to the
// leg's normally-estimated travel time (1 = no change, >1 = slower than
// usual right now). Falls back to "no adjustment" whenever live traffic
// isn't turned on, isn't configured, or the lookup fails for any reason —
// the itinerary must still work even if TomTom is unreachable.
async function getTrafficAdjustment(lat, lng) {
  if (!trafficEnabled || !TOMTOM_API_KEY) return { multiplier: 1, condition: null };

  const key = `${lat.toFixed(2)},${lng.toFixed(2)}`;
  if (trafficCache.has(key)) return trafficCache.get(key);

  let result = { multiplier: 1, condition: null };
  try {
    const res = await fetch(
      `<?= APP_URL ?>/api/routes.php?action=traffic&points=${encodeURIComponent(JSON.stringify([{ lat, lng }]))}`
    ).then(r => r.json());
    const point = res.success && Array.isArray(res.data) ? res.data[0] : null;
    if (point && point.relativeSpeed > 0) {
      // relativeSpeed is currentSpeed/freeFlowSpeed — under 1 means slower
      // than the road's normal free-flow speed right now. Floor it at 0.35
      // so one severely-congested (or misreported near-zero) data point
      // can't blow a leg's estimate up to several hours.
      const clampedRatio = Math.max(0.35, Math.min(1, point.relativeSpeed));
      result = { multiplier: 1 / clampedRatio, condition: point.condition };
    }
  } catch (err) {
    // Network hiccup or TomTom outage — quietly keep the static estimate.
  }

  trafficCache.set(key, result);
  return result;
}

// ── "Is this spot actually along the way?" corridor filter ──
// A bounding box between origin and destination (what the backend uses to
// fetch candidate spots) lets in anything inside that rectangle, including
// spots far off to the side that the route never actually passes near. This
// measures each spot's real distance to the drawn route line (the actual
// road-following polyline when available, otherwise the straight
// origin→destination line) and only keeps spots within a walkable/short
// detour distance of it.
const ROUTE_CORRIDOR_KM = 6;

// Perpendicular distance (km) from point P to the segment A→B, using a
// local flat-earth approximation. Laguna's whole span is under ~50km, so
// the curvature error this introduces is negligible for filtering purposes.
function pointToSegmentKm(lat, lng, aLat, aLng, bLat, bLng) {
  const latRef = (aLat + bLat) / 2;
  const kmPerDegLat = 110.574;
  const kmPerDegLng = 111.320 * Math.cos(latRef * Math.PI / 180);

  const toXY = (la, lo) => [(lo - aLng) * kmPerDegLng, (la - aLat) * kmPerDegLat];
  const [px, py] = toXY(lat, lng);
  const [bx, by] = toXY(bLat, bLng);

  const segLenSq = bx * bx + by * by;
  let t = segLenSq > 0 ? (px * bx + py * by) / segLenSq : 0;
  t = Math.max(0, Math.min(1, t));
  const dx = px - bx * t, dy = py - by * t;
  return Math.sqrt(dx * dx + dy * dy);
}

// Minimum distance (km) from a point to any segment of a multi-point route
// line — i.e. "how far off the actual road path is this spot".
function distanceToRouteKm(lat, lng, routeLine) {
  if (!routeLine || routeLine.length < 2) return Infinity;
  let min = Infinity;
  for (let i = 0; i < routeLine.length - 1; i++) {
    const [aLat, aLng] = routeLine[i];
    const [bLat, bLng] = routeLine[i + 1];
    const d = pointToSegmentKm(lat, lng, aLat, aLng, bLat, bLng);
    if (d < min) min = d;
  }
  return min;
}

// ── Shared transport icon/label lookup (used in both the top-level
// transport options list and per-leg fares inside the itinerary) ──
const TRANSPORT_ICONS = {
  jeepney: 'bi-truck-front', bus: 'bi-bus-front', tricycle: 'bi-bicycle',
  private_car: 'bi-car-front', fx_uv: 'bi-minecart',
};
const TRANSPORT_LABELS = {
  jeepney: 'Jeepney', bus: 'Bus', tricycle: 'Tricycle',
  private_car: 'Private Car', fx_uv: 'FX / UV Express',
};
function transportIcon(type) { return TRANSPORT_ICONS[type] || 'bi-bus-front'; }
function transportLabel(type) { return TRANSPORT_LABELS[type] || type; }

// One-line "🚍 Jeepney · ₱25.00" style label for a transport_options row.
function fareLabel(fare) {
  const label = transportLabel(fare.transport_type);
  const price = fare.fare_php > 0 ? `₱${parseFloat(fare.fare_php).toFixed(2)}` : 'Own vehicle';
  return `<i class="bi ${transportIcon(fare.transport_type)} me-1"></i>${label} · ${price}`;
}

// Once you've actually arrived in a city/town, the hop from there to the
// specific spot is a short local trip — walked or taken by tricycle, not
// another long-haul ride booked like the jeepney/bus legs between towns.
// This gives a first-time visitor a concrete, actionable suggestion
// instead of just a raw distance number.
function lastMileSuggestion(km) {
  const mins = estimateTravelMinutes(km);
  if (km <= 0.8) {
    return `<i class="bi bi-person-walking me-1"></i>Walk (~${formatDuration(Math.max(mins, 5))})`;
  }
  if (km <= 3) {
    return `<i class="bi bi-bicycle me-1"></i>Ride a tricycle (~₱15–20, ~${formatDuration(mins)})`;
  }
  return `<i class="bi bi-bicycle me-1"></i>Tricycle or multicab (~₱20–30, ~${formatDuration(mins)}, fare varies — confirm with the driver)`;
}

// Cache of city-pair → cheapest transport option, so repeated hops between
// the same two cities within one itinerary don't refetch. Cleared each
// time a fresh itinerary is built.
let fareCache = new Map();

async function getCityFare(originCityId, destCityId) {
  if (!originCityId || !destCityId || originCityId === destCityId) return null;
  // Cache key includes the currently preferred transport type, so
  // switching transport options in the sidebar doesn't return a stale
  // fare cached under a different preference.
  const preferredType = selectedTransport?.transport_type || '';
  const key = `${originCityId}-${destCityId}-${preferredType}`;
  if (fareCache.has(key)) return fareCache.get(key);

  let chosen = null;
  try {
    const res = await fetch(
      API_BASE + `routes.php?action=route&origin=${originCityId}&dest=${destCityId}`
    ).then(r => r.json());
    const options = res.success ? res.data.transport_options : [];
    if (options && options.length) {
      // Prefer whichever mode the traveler picked in Transport Options,
      // when this specific leg actually offers it — otherwise fall back
      // to the cheapest available (options are backend-sorted fare ASC).
      chosen = (preferredType && options.find(o => o.transport_type === preferredType)) || options[0];
    }
  } catch (err) {
    console.warn('Fare lookup failed for', key, err);
  }
  fareCache.set(key, chosen);
  return chosen;
}

// Cache of city → food shops, so multiple lunches landing in the same
// city (e.g. across different days) don't refetch.
let foodShopCache = new Map();

// Finds the closest real food shop (restaurant/cafe/street food/bakery/
// milk tea) to a specific point, so "Lunch Break" can name an actual
// place instead of a generic "try local specialties" line disconnected
// from where the traveler actually is.
async function getNearbyFood(cityId, lat, lng) {
  if (!cityId) return null;
  if (!foodShopCache.has(cityId)) {
    try {
      const res = await fetch(API_BASE + `shops.php?action=nearby_food&city=${cityId}`).then(r => r.json());
      foodShopCache.set(cityId, res.success ? res.data : []);
    } catch (err) {
      console.warn('Nearby food lookup failed for city', cityId, err);
      foodShopCache.set(cityId, []);
    }
  }
  const shops = foodShopCache.get(cityId);
  if (!shops || !shops.length) return null;

  let nearest = null, nearestKm = Infinity;
  for (const shop of shops) {
    const km = haversineKm(lat, lng, shop.latitude, shop.longitude);
    if (km < nearestKm) { nearestKm = km; nearest = shop; }
  }
  return nearest ? { ...nearest, distance_km: nearestKm } : null;
}

const SHOP_CATEGORY_LABELS = {
  restaurant: 'Restaurant', cafe: 'Café', street_food: 'Street food stall',
  bakery: 'Bakery', milktea: 'Milk tea shop',
};

// ── Spot closure / availability helpers ─────────────────────
function todayStr() {
  return new Date().toISOString().slice(0, 10);
}

// Returns null if the spot is open. Otherwise returns details about the
// closure, resolved against the tourist's chosen travel date (or today,
// if no date was picked).
function spotClosureInfo(spot) {
  if (!spot.is_closed) return null;
  const ref = travelDate || todayStr();
  const reopensBeforeTravel = !!(spot.closed_until && spot.closed_until < ref);
  return {
    reopensBeforeTravel,
    reason: spot.closure_reason || null,
    closedUntil: spot.closed_until || null,
  };
}

function formatDateNice(isoDate) {
  return new Date(isoDate + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

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

// When restoring a saved itinerary via "Re-plan", these exact spot IDs
// take priority over auto-selecting fresh top-rated spots. Cleared after
// first use so a later manual re-search doesn't keep restoring stale
// spots the person may have already moved past.
let restoreSpotIds = <?= json_encode(array_values($pre_spot_ids)) ?>;

// ── Main plan function ──────────────────────────────────────
async function planRoute() {
  const origin  = document.getElementById('origin-select').value;
  const dest    = document.getElementById('dest-select').value;
  const days    = parseInt(document.getElementById('days-select').value);
  const persons = parseInt(document.getElementById('persons-select').value);
  const budget  = document.getElementById('budget-select').value;
  travelDate    = document.getElementById('travel-date-input').value || null;

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

    const routeLine = await drawRoute(routeData);

    // Keep only spots genuinely along the way. The backend's bounding-box
    // query is deliberately loose (fast, simple SQL) and can include spots
    // that sit off to the side and are never actually passed — filter those
    // out here against the real drawn route line.
    const onRouteSpots = allSpots.filter(
      s => distanceToRouteKm(parseFloat(s.latitude), parseFloat(s.longitude), routeLine) <= ROUTE_CORRIDOR_KM
    );
    if (onRouteSpots.length > 0) {
      allSpots = onRouteSpots;
    } else {
      console.warn(`No spots found within ${ROUTE_CORRIDOR_KM}km of the route — showing all fetched spots instead.`);
    }

    fareCache = new Map(); // fresh fare lookups for this itinerary
    drawSpotMarkers(allSpots);

    renderTransportOptions(routeData.transport_options);
    renderRouteStats(routeData);
    renderSpotsGrid(allSpots);

    // Restoring a saved itinerary ("Re-plan")? Use the exact spots that
    // were actually saved, not a freshly auto-picked set — otherwise
    // "Re-plan" silently discards whatever the person specifically chose
    // and hand them a different trip instead.
    let itinerarySpots = allSpots;
    if (restoreSpotIds.length) {
      try {
        const res = await fetch(API_BASE + `spots.php?action=by_ids&ids=${restoreSpotIds.join(',')}`).then(r => r.json());
        if (res.success && res.data.length) {
          itinerarySpots = res.data;
        } else {
          IExploreApp.toast('Could not restore your saved spots — showing a fresh selection instead.', 'warning');
        }
      } catch (err) {
        console.warn('Failed to restore saved spot_ids', err);
        IExploreApp.toast('Could not restore your saved spots — showing a fresh selection instead.', 'warning');
      }
      restoreSpotIds = []; // one-time use — don't re-apply on a later manual search
    }

    // Itinerary first — it decides which spots actually get scheduled
    // (includedSpotIds), so the budget total below can match what's shown.
    const isRestoring = itinerarySpots !== allSpots; // true only when we just substituted in the saved spot list above
    await renderItinerary(routeData, itinerarySpots, days, isRestoring);
    renderBudget(routeData, itinerarySpots.filter(s => includedSpotIds.includes(s.id)), days, persons, budget);

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

// ── Draw road-following route via OpenRouteService ──────────
// Calls our own server-side proxy (api/routes.php?action=directions)
// so the ORS API key never reaches the browser, then draws the
// returned line as a plain Leaflet polyline — no external routing
// library needed.
let routeLayerGroup = null;

async function drawRoute(data) {
  const { origin, destination } = data;

  // Remove previous route layer
  if (routeLayerGroup) {
    map.removeLayer(routeLayerGroup);
    routeLayerGroup = null;
  }

  const startLatLng = L.latLng(parseFloat(origin.latitude),      parseFloat(origin.longitude));
  const endLatLng   = L.latLng(parseFloat(destination.latitude), parseFloat(destination.longitude));

  function addEndpointMarkers(group) {
    L.marker(startLatLng, { icon: iconStart })
      .addTo(group)
      .bindPopup(`<div class="popup-title">${origin.name}</div>
                  <div class="popup-meta"><i class="bi bi-geo-alt"></i> Starting Point</div>`);
    L.marker(endLatLng, { icon: iconEnd })
      .addTo(group)
      .bindPopup(`<div class="popup-title">${destination.name}</div>
                  <div class="popup-meta"><i class="bi bi-geo-alt"></i> Destination</div>`);
  }

  function drawFallback(reason) {
    routeLayerGroup = L.layerGroup().addTo(map);
    L.polyline([startLatLng, endLatLng], {
      color: THEME_MID, weight: 4, opacity: 0.6, dashArray: '10 6',
    }).addTo(routeLayerGroup);
    addEndpointMarkers(routeLayerGroup);
    map.fitBounds([startLatLng, endLatLng], { padding: [40, 40] });
    if (reason) console.warn('ORS routing unavailable, falling back to straight line:', reason);
    IExploreApp.toast('Using approximate route (road data unavailable).', 'info');
    // Straight two-point line — used as the "corridor" for filtering
    // nearby spots when we don't have a real road-following path.
    return [[startLatLng.lat, startLatLng.lng], [endLatLng.lat, endLatLng.lng]];
  }

  try {
    const res = await fetch(
      API_BASE + `routes.php?action=directions` +
      `&origin_lat=${origin.latitude}&origin_lng=${origin.longitude}` +
      `&dest_lat=${destination.latitude}&dest_lng=${destination.longitude}`
    ).then(r => r.json());

    if (!res.success || !res.data.coordinates || !res.data.coordinates.length) {
      return drawFallback(res.message || 'no coordinates returned');
    }

    routeLayerGroup = L.layerGroup().addTo(map);
    const latlngs = res.data.coordinates; // already [lat, lng] pairs
    L.polyline(latlngs, {
      color: THEME_MID, weight: 5, opacity: 0.85,
    }).addTo(routeLayerGroup);
    addEndpointMarkers(routeLayerGroup);
    map.fitBounds(L.latLngBounds(latlngs), { padding: [40, 40] });
    return latlngs; // real road-following path, for corridor filtering
  } catch (err) {
    return drawFallback(err);
  }
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
      <div class="popup-rich-header" style="background:var(--maroon-pale);padding:.75rem 1rem .5rem;margin:-.4rem -.4rem .5rem;border-radius:8px 8px 0 0;text-align:center;font-size:2rem;line-height:1">
        <i class="bi ${catIcon(spot.category)}" style="color:var(--maroon-mid)"></i>
      </div>
      <div style="padding:0 .25rem">
        <div class="popup-title" style="font-size:.95rem">${spot.name}</div>
        <div class="popup-meta mb-1">
          <i class="bi bi-geo-alt" style="color:var(--maroon-mid)"></i>
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
          style="display:block;width:100%;padding:.35rem;background:var(--maroon-dark);color:#fff;border:none;border-radius:6px;font-size:.8rem;cursor:pointer;font-family:'DM Sans',sans-serif;text-align:center;text-decoration:none">
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
        <i class="bi bi-signpost-2 d-block mb-2" style="font-size:1.6rem;color:var(--maroon-pale)"></i>
        <p class="text-muted small mb-1 fw-500">No scheduled transport data</p>
        <p class="text-muted" style="font-size:.75rem;line-height:1.5">
          Try a <strong>private car</strong> or <strong>tricycle</strong> for this route.
          Check local terminals for jeepney schedules.
        </p>
      </div>`;
    return;
  }

  // Options arrive sorted by fare ASC, which puts private_car (₱0.00,
  // "Own vehicle") first on almost every route. Defaulting to that made
  // the whole planner look like it ignored fares: Route Info showed
  // "Own vehicle", the itinerary's per-leg lines showed "Own vehicle",
  // and Budget priced transport at ₱0. Default to the cheapest option
  // that has an actual fare instead, and only fall back to index 0 when
  // own-vehicle is genuinely the only choice on file.
  const defaultIndex = Math.max(0, options.findIndex(t => parseFloat(t.fare_php) > 0));

  container.innerHTML = options.map((t, i) => `
    <div class="transport-option p-2 mb-2 rounded-2 ${i===defaultIndex?'selected':''}"
         data-index="${i}"
         style="border:1.5px solid ${i===defaultIndex?'var(--maroon-light)':'var(--border)'};
                background:${i===defaultIndex?'var(--maroon-pale)':'#fff'};
                cursor:pointer;transition:all .2s">
      <div class="d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <i class="bi ${transportIcon(t.transport_type)} fs-5 text-maroon"></i>
          <div>
            <div class="fw-bold small">${transportLabel(t.transport_type)}</div>
            <div style="font-size:.75rem;color:var(--text-muted)">${t.distance_km} km · ${formatDuration(t.duration_min)}</div>
          </div>
        </div>
        <div class="text-end">
          <div class="fw-bold text-maroon small">
            ${t.fare_php > 0 ? '₱ '+parseFloat(t.fare_php).toFixed(2) : 'Own vehicle'}
          </div>
          <div style="font-size:.7rem;color:var(--text-muted)">per person</div>
        </div>
      </div>
      ${t.notes ? `<div style="font-size:.72rem;color:var(--text-muted);margin-top:.35rem">
        <i class="bi bi-info-circle me-1"></i>${t.notes}</div>` : ''}
    </div>
  `).join('');

  selectedTransport = options[defaultIndex];

  container.querySelectorAll('.transport-option').forEach(el => {
    el.addEventListener('click', () => {
      container.querySelectorAll('.transport-option').forEach(e => {
        e.style.borderColor = 'var(--border)';
        e.style.background  = '#fff';
      });
      el.style.borderColor = 'var(--maroon-light)';
      el.style.background  = 'var(--maroon-pale)';
      selectedTransport = options[parseInt(el.dataset.index)];

      // Previously this selection only got used later, silently, when
      // saving the itinerary — clicking a card had no visible effect on
      // Route Info, Budget, or the itinerary's per-leg fares, which just
      // felt broken. Now all three re-render immediately with the chosen
      // mode's actual fare/time. Itinerary must run first since it
      // repopulates includedSpotIds, which Budget's totals depend on.
      if (routeData) {
        fareCache = new Map(); // stale fares were cached under the old preference
        const days    = parseInt(document.getElementById('days-select').value);
        const persons = parseInt(document.getElementById('persons-select').value);
        const budget  = document.getElementById('budget-select').value;
        renderItinerary(routeData, allSpots, days).then(() => {
          renderRouteStats(routeData);
          renderBudget(routeData, allSpots.filter(s => includedSpotIds.includes(s.id)), days, persons, budget);
        });
      }
    });
  });
}

// ── Render route stats ──────────────────────────────────────
// Reflects whichever transport option is currently selected (defaults to
// the cheapest, options[0], until the person clicks a different one).
function renderRouteStats(data) {
  const t = selectedTransport || data.transport_options[0] || {};
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
                   background:${cat===filterCat?'var(--maroon-mid)':'#fff'};
                   color:${cat===filterCat?'#fff':'var(--charcoal)'};
                   border:1.5px solid ${cat===filterCat?'var(--maroon-mid)':'var(--border)'}">
      <i class="bi ${cat==='all'?'bi-grid':catIcon(cat)} me-1"></i>${cat === 'all' ? 'All' : catLabel(cat)}
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

  const originPt = routeData ? {
    lat: parseFloat(routeData.origin.latitude),
    lng: parseFloat(routeData.origin.longitude),
  } : null;

  grid.innerHTML = filtered.map(spot => {
    const distKm = originPt ? haversineKm(originPt.lat, originPt.lng, spot.latitude, spot.longitude) : null;
    const distHtml = distKm !== null ? `
      <span>·</span>
      <span title="Straight-line distance & estimated drive time from ${routeData.origin.name}">
        <i class="bi bi-signpost-2"></i> ${distanceTimeLabel(distKm)}
      </span>` : '';

    const closure = spotClosureInfo(spot);
    const closureBanner = closure ? `
      <div style="background:${closure.reopensBeforeTravel ? '#fef3c7' : '#fee2e2'};
                  color:${closure.reopensBeforeTravel ? '#92400e' : '#a61c1c'};
                  font-size:.72rem;font-weight:700;padding:.3rem .6rem;border-radius:6px;margin-top:.45rem">
        <i class="bi bi-cone-striped me-1"></i>Temporarily Closed${closure.reason ? ' — ' + closure.reason : ''}
        ${closure.closedUntil
          ? (closure.reopensBeforeTravel
              ? ` · expected to reopen ${formatDateNice(closure.closedUntil)}, before your trip`
              : ` · expected back ${formatDateNice(closure.closedUntil)}`)
          : ' · no reopening date yet'}
      </div>` : '';

    return `
    <div class="col-12">
      <div class="spot-card-row d-flex gap-3 p-3 bg-white rounded-3 align-items-start"
           id="spot-card-${spot.id}"
           data-spot-id="${spot.id}"
           style="border:1.5px solid ${closure && !closure.reopensBeforeTravel ? '#fca5a5' : 'var(--border)'};
                  cursor:pointer;transition:all .22s;scroll-margin-top:90px;
                  opacity:${closure && !closure.reopensBeforeTravel ? '.75' : '1'}"
           onmouseenter="this.style.borderColor='var(--maroon-light)';this.style.background='var(--maroon-pale)'"
           onmouseleave="this.style.borderColor=this.classList.contains('spot-card-active')?'var(--maroon-mid)':'${closure && !closure.reopensBeforeTravel ? '#fca5a5' : 'var(--border)'}';this.style.background=this.classList.contains('spot-card-active')?'#fbe4e4':'#fff'"
           onclick="flyToSpot(${spot.id})">
        <div style="width:52px;height:52px;border-radius:10px;background:var(--maroon-pale);
                    display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0">
          <i class="bi ${catIcon(spot.category)}" style="color:var(--maroon-mid)"></i>
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
            ${distHtml}
          </div>
          ${closureBanner}
        </div>
        <div class="d-flex flex-column align-items-end gap-1">
          <i class="bi bi-map text-maroon small" title="Fly to on map"></i>
          <i class="bi bi-chevron-right text-muted small"></i>
        </div>
      </div>
    </div>
  `;
  }).join('');
}

// ── Render budget breakdown ─────────────────────────────────
async function renderBudget(routeData, spots, days, persons, budgetLevel) {
  const origin_id = routeData.origin.id;
  const dest_id   = routeData.destination.id;
  // Respect whichever transport option the person actually clicked in the
  // Transport Options list, instead of silently recomputing a different
  // "assumed" mode from the budget level alone.
  const transportParam = selectedTransport?.transport_type
    ? `&transport=${encodeURIComponent(selectedTransport.transport_type)}`
    : '';

  const res = await fetch(
    API_BASE + `budget.php?action=estimate&origin=${origin_id}&dest=${dest_id}&days=${days}&persons=${persons}&level=${budgetLevel}${transportParam}`
  ).then(r => r.json());

  if (!res.success) return;
  const b = res.data;

  const totalFees = spots.reduce((sum, s) => sum + parseFloat(s.entrance_fee), 0);

  document.getElementById('total-budget').textContent = formatPeso(b.grand_total + totalFees * persons);

  document.getElementById('budget-breakdown').innerHTML = `
    ${budgetRow('bi-bus-front',   'Transport',       b.transport * persons)}
    ${budgetRow('bi-house-door',  'Accommodation',   b.accommodation * persons * Math.max(0, days - 1))}
    ${budgetRow('bi-cup-hot',     'Food',            b.food * persons * days)}
    ${budgetRow('bi-signpost-2',  'Local Transport', b.local * persons * days)}
    ${budgetRow('bi-ticket',      'Entrance Fees',   totalFees * persons)}
    ${budgetRow('bi-three-dots',  'Miscellaneous',   b.misc * persons)}
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
// Touring window per day: how late we'll schedule a new stop, and how
// many stops of ~2 hours each realistically fit (minus a fixed lunch
// hour). Day 1 starts later since the morning is spent traveling.
const TOUR_END_MIN   = 18 * 60; // stop scheduling new visits after 6 PM
const HOURS_PER_STOP = 120;     // minutes budgeted per spot visit
const LUNCH_MIN      = 60;

function dayTouringStart(day) {
  return (day === 1 ? 10 : 8) * 60;
}

function dayCapacity(day) {
  const available = Math.max(0, TOUR_END_MIN - dayTouringStart(day) - LUNCH_MIN);
  return Math.max(1, Math.floor(available / HOURS_PER_STOP));
}

// Groups spots by city, then orders those city clusters by how far along
// the straight line from origin to destination they sit (vector
// projection). Within a cluster, spots are still ranked by rating. This
// keeps a day's stops in one "base" city before moving on, instead of the
// old rating-only order which could bounce origin → dest → origin → dest.
function orderSpotsAlongRoute(spots, routeData) {
  const oLat = parseFloat(routeData.origin.latitude);
  const oLng = parseFloat(routeData.origin.longitude);
  const dLat = parseFloat(routeData.destination.latitude);
  const dLng = parseFloat(routeData.destination.longitude);
  const dx = dLng - oLng, dy = dLat - oLat;
  const lenSq = (dx * dx + dy * dy) || 1;

  const groups = new Map();
  spots.forEach(s => {
    if (!groups.has(s.city_id)) groups.set(s.city_id, []);
    groups.get(s.city_id).push(s);
  });

  const clusters = [...groups.values()].map(list => {
    const avgLat = list.reduce((sum, s) => sum + s.latitude, 0) / list.length;
    const avgLng = list.reduce((sum, s) => sum + s.longitude, 0) / list.length;
    // Projection of the cluster's centroid onto the origin→destination
    // vector: ~0 means "near origin", ~1 means "near destination".
    const t = ((avgLng - oLng) * dx + (avgLat - oLat) * dy) / lenSq;
    return { t, list: list.slice().sort((a, b) => b.rating - a.rating) };
  });
  clusters.sort((a, b) => a.t - b.t);

  return clusters.flatMap(c => c.list);
}

// Fetch verified hotels in a spot's city (reuses the existing
// nearby_hotels endpoint, which groups by the spot's own city_id) and
// return the one closest to that spot — so travelers check in somewhere
// that doesn't cost them a detour the next morning.
async function findNearestHotel(anchorSpot) {
  try {
    const res = await fetch(API_BASE + `spots.php?action=nearby_hotels&id=${anchorSpot.id}`).then(r => r.json());
    if (!res.success || !res.data.length) return null;
    let best = null, bestKm = Infinity;
    res.data.forEach(h => {
      const km = haversineKm(anchorSpot.latitude, anchorSpot.longitude, parseFloat(h.latitude), parseFloat(h.longitude));
      if (km < bestKm) { bestKm = km; best = h; }
    });
    return best ? { ...best, distKm: bestKm } : null;
  } catch {
    return null;
  }
}

// Remembers the args of the last itinerary render so toggling live traffic
// on/off can recompute the schedule immediately, instead of only affecting
// the map overlay until the visitor happens to replan the trip.
let lastItineraryRender = null;

async function renderItinerary(routeData, spots, days, forceIncludeAll = false) {
  lastItineraryRender = { routeData, spots, days, forceIncludeAll };
  const container = document.getElementById('itinerary-days');

  // Closed spots (that won't reopen before travel) are never scheduled.
  const closedSpots = spots.filter(s => {
    const c = spotClosureInfo(s);
    return c && !c.reopensBeforeTravel;
  });
  const openSpots = spots.filter(s => !closedSpots.includes(s));

  // Order by city cluster along the route (not just rating) so a day's
  // stops stay in one base city before moving to the next.
  const ordered = orderSpotsAlongRoute(openSpots, routeData);

  // Slice into days up-front, so each day already knows what the *next*
  // day's first spot is — needed to pick a same-direction hotel for
  // tonight's check-in.
  //
  // Two modes:
  // - Normal (auto-planning): capacity-limited per day, since we're
  //   choosing from a large pool of candidates and shouldn't cram in
  //   more than a day can reasonably hold.
  // - forceIncludeAll (restoring a saved itinerary via "Re-plan"): the
  //   person already committed to this exact set of spots — silently
  //   dropping some because a capacity formula says "too many for one
  //   day" would contradict the whole point of restoring their saved
  //   trip. Spread everything evenly across the given day count instead.
  const daySlices = [];
  let cursor = 0;
  if (forceIncludeAll) {
    const perDay = Math.max(1, Math.ceil(ordered.length / days));
    for (let day = 1; day <= days; day++) {
      const daySpots = ordered.slice(cursor, cursor + perDay);
      cursor += daySpots.length;
      daySlices.push(daySpots);
    }
  } else {
    for (let day = 1; day <= days; day++) {
      const capacity = dayCapacity(day);
      const daySpots = ordered.slice(cursor, cursor + capacity);
      cursor += daySpots.length;
      daySlices.push(daySpots);
    }
  }
  const scheduledSpots = ordered.slice(0, cursor);
  const timeCutSpots = forceIncludeAll ? [] : ordered.slice(cursor);
  includedSpotIds = scheduledSpots.map(s => s.id);

  let html = '';

  // Running "current location" (and the city name that goes with it),
  // used to estimate distance/time to the next stop and to detect when a
  // day's plan has moved into a new city. Starts at the trip origin.
  let lastPoint = {
    latitude:  parseFloat(routeData.origin.latitude),
    longitude: parseFloat(routeData.origin.longitude),
    cityName:  routeData.origin.name,
    cityId:    routeData.origin.id,
  };

  for (let day = 1; day <= days; day++) {
    const daySpots = daySlices[day - 1];
    const cityName = daySpots.length ? daySpots[daySpots.length - 1].city_name : lastPoint.cityName;

    html += `
      <div class="itinerary-day">
        <div class="day-dot">${day}</div>
        <div class="day-label">
          <span>Day ${day} — ${cityName}</span>
          <span class="day-stop-count"><i class="bi bi-geo-alt-fill me-1"></i>${daySpots.length} stop${daySpots.length !== 1 ? 's' : ''}</span>
        </div>
    `;

    // Collect this day's items as {minutes-since-midnight, html} pairs so
    // we can sort them into real chronological order before rendering —
    // spot visit times are dynamic (depend on how many stops are that day),
    // so fixed-time items like Lunch/Check-in can't just be appended last.
    const events = [];

    if (day === 1) {
      events.push({ t: 7*60, html: itineraryItem('7:00 AM', 'bi-sun', 'Start your day',
        'Pack your bags and get ready to explore.') });
      events.push({ t: 7*60 + 15, html: itineraryItem('7:15 AM', 'bi-geo-alt', 'Depart from ' + routeData.origin.name,
        'Your trip begins here — first stop coming up.') });
    }

    let time = dayTouringStart(day); // minutes since midnight
    let lunchInserted = false;
    const LUNCH_START_MIN = 12 * 60; // don't schedule lunch before noon
    for (const spot of daySpots) {
      const crossingCity = spot.city_id !== lastPoint.cityId;

      // Where does the "last mile to the spot" measurement start from?
      // Normally the previous stop — but if we just crossed into a new
      // city, it should start from that city's town proper/terminal, not
      // from wherever we were in the last city (that distance was already
      // covered by the inter-city ride above).
      let lastMileLat = lastPoint.latitude, lastMileLng = lastPoint.longitude, lastMileFrom = 'the previous stop';

      if (crossingCity) {
        // Long-haul leg: board the actual jeepney/tricycle/bus that runs
        // between these two towns, using real fare data when we have it.
        const cityLat = parseFloat(spot.city_latitude), cityLng = parseFloat(spot.city_longitude);
        const fare = await getCityFare(lastPoint.cityId, spot.city_id);
        const interCityKm = fare && fare.distance_km
          ? parseFloat(fare.distance_km)
          : haversineKm(lastPoint.latitude, lastPoint.longitude, cityLat, cityLng);
        const baseTravelMins = fare && fare.duration_min ? fare.duration_min : estimateTravelMinutes(interCityKm);

        // Sample live traffic near the midpoint of this leg — a single
        // point can't capture an entire 15-20km ride, but it's a reasonable
        // proxy for "is this general corridor congested right now", and
        // matches what the traffic overlay itself is already showing.
        const midLat = (lastPoint.latitude + cityLat) / 2;
        const midLng = (lastPoint.longitude + cityLng) / 2;
        const traffic = await getTrafficAdjustment(midLat, midLng);
        const travelMins = Math.round(baseTravelMins * traffic.multiplier);
        const extraMins = travelMins - baseTravelMins;

        const heading = fare ? `Board a ${transportLabel(fare.transport_type)} to ${spot.city_name}` : `Travel to ${spot.city_name}`;
        const trafficNote = (traffic.condition && traffic.condition !== 'free' && extraMins > 0)
          ? ` · 🚦 ${traffic.condition} traffic right now (+${extraMins} min)`
          : '';
        const desc = (fare
          ? `${fare.fare_php > 0 ? '₱'+parseFloat(fare.fare_php).toFixed(2) : 'Own vehicle'} · ${fare.distance_km} km · ${formatDuration(travelMins)} from ${lastPoint.cityName}`
          : `${distanceTimeLabel(interCityKm)} from ${lastPoint.cityName} · no fixed fare on file — try tricycle/habal-habal and negotiate`) + trafficNote;

        events.push({
          t: time,
          html: itineraryItem(minutesToLabel(time), fare ? transportIcon(fare.transport_type) : 'bi-bus-front', heading, desc)
        });
        time += travelMins;

        // Now "arrived" at the new city's town proper — the remaining
        // distance to the spot itself is the short last-mile hop.
        if (!isNaN(cityLat) && !isNaN(cityLng)) {
          lastMileLat = cityLat;
          lastMileLng = cityLng;
        } else {
          lastMileLat = spot.latitude;
          lastMileLng = spot.longitude;
        }
        lastMileFrom = spot.city_name + ' town proper';
      }

      const timeStr = minutesToLabel(time);
      const legKm = haversineKm(lastMileLat, lastMileLng, spot.latitude, spot.longitude);
      const legLabel = `${lastMileSuggestion(legKm)} from ${lastMileFrom} (${legKm < 10 ? legKm.toFixed(1) : Math.round(legKm)} km)`;
      lastPoint = { latitude: spot.latitude, longitude: spot.longitude, cityName: spot.city_name, cityId: spot.city_id };

      events.push({
        t: time,
        html: itineraryItem(timeStr, 'bi-geo-alt-fill', spot.name,
          `${spot.city_name} · ${spot.entrance_fee > 0 ? '₱'+parseFloat(spot.entrance_fee).toFixed(0) : 'Free'} entrance · ${legLabel}`,
          true)
      });
      time += 120; // 2 hours per stop

      // Insert lunch right after whichever stop the traveler has actually
      // just finished once the clock crosses noon — anchored to that real
      // spot, not a fixed, disconnected 12:00 PM slot that could land
      // before, after, or on top of an unrelated stop. Named after an
      // actual nearby food shop when one exists on file, instead of a
      // generic "try local specialties" line.
      if (!lunchInserted && time >= LUNCH_START_MIN) {
        const nearbyFood = await getNearbyFood(spot.city_id, spot.latitude, spot.longitude);
        const lunchDesc = nearbyFood
          ? `${SHOP_CATEGORY_LABELS[nearbyFood.category] || 'Eatery'} <strong>${nearbyFood.name}</strong> is about ${nearbyFood.distance_km < 1 ? Math.round(nearbyFood.distance_km*1000)+'m' : nearbyFood.distance_km.toFixed(1)+' km'} from ${spot.name} — good spot for lunch.`
          : `No listed eateries near ${spot.name} yet — ask locally, or try Laguna specialties like buko pie, kesong puti, or fresh bangus.`;

        events.push({
          t: time,
          html: itineraryItem(minutesToLabel(time), 'bi-cup-hot', 'Lunch Break', lunchDesc)
        });
        time += LUNCH_MIN;
        lunchInserted = true;
      }
    }

    // Edge case: if the day ends before noon is ever reached (e.g. very
    // few stops), still surface a lunch suggestion tied to wherever the
    // day's last stop was, rather than skipping it entirely.
    if (!lunchInserted && daySpots.length > 0) {
      const nearbyFood = await getNearbyFood(lastPoint.cityId, lastPoint.latitude, lastPoint.longitude);
      const lunchDesc = nearbyFood
        ? `${SHOP_CATEGORY_LABELS[nearbyFood.category] || 'Eatery'} <strong>${nearbyFood.name}</strong> is about ${nearbyFood.distance_km < 1 ? Math.round(nearbyFood.distance_km*1000)+'m' : nearbyFood.distance_km.toFixed(1)+' km'} away — good spot for lunch.`
        : `No listed eateries nearby yet — ask locally, or try Laguna specialties like buko pie, kesong puti, or fresh bangus.`;
      events.push({ t: time, html: itineraryItem(minutesToLabel(time), 'bi-cup-hot', 'Lunch Break', lunchDesc) });
      time += LUNCH_MIN;
    }

    // Overnight stay — only when there's a next day to prep for. Picks
    // whichever verified hotel is closest to *tomorrow's* first stop, so
    // the traveler isn't backtracking across town the next morning.
    if (day < days) {
      const nextSpot = daySlices[day][0]; // daySlices[day] is tomorrow (0-indexed)
      if (nextSpot) {
        const hotel = await findNearestHotel(nextSpot);
        if (hotel) {
          const priceLabel = hotel.price_min
            ? `₱${Math.round(hotel.price_min).toLocaleString()}${hotel.price_max ? '–₱'+Math.round(hotel.price_max).toLocaleString() : ''}/night`
            : 'Price on request';
          events.push({
            t: TOUR_END_MIN,
            html: itineraryItem(minutesToLabel(TOUR_END_MIN), 'bi-house-check', 'Check-in at ' + hotel.name,
              `${hotel.address || nextSpot.city_name} · ${priceLabel} · ${distanceTimeLabel(hotel.distKm)} from tomorrow's first stop`)
          });
          // Sleep at the hotel, not at the last spot visited — tomorrow's
          // first "distance from previous stop" should measure from here.
          // Keep cityId — without it, tomorrow's first leg compares
          // spot.city_id against undefined, so every Day 2+ hop looked
          // like a city crossing but then failed its fare lookup and fell
          // back to the generic "no fixed fare on file" line.
          lastPoint = {
            latitude:  parseFloat(hotel.latitude),
            longitude: parseFloat(hotel.longitude),
            cityName:  nextSpot.city_name,
            cityId:    nextSpot.city_id,
          };
        } else {
          events.push({
            t: TOUR_END_MIN,
            html: itineraryItem(minutesToLabel(TOUR_END_MIN), 'bi-house-check', 'Check-in / Rest',
              `No listed accommodations in ${nextSpot.city_name} yet — settle in nearby and rest for the next day.`)
          });
        }
      }
    }

    // Render in actual chronological order. Array.sort is stable in all
    // current browsers, so same-time items (e.g. Lunch vs. a spot also at
    // noon) keep a sensible relative order.
    events.sort((a, b) => a.t - b.t);
    html += events.map(e => e.html).join('');

    html += '</div>';
  }

  // Notices about spots left out — shown above the days so they're the
  // first thing a tourist sees if their planned count looks short.
  let noticeHtml = '';
  if (closedSpots.length) {
    noticeHtml += `
      <div class="mb-3 p-3" style="background:#fee2e2;border:1.5px solid #fca5a5;border-radius:var(--radius-sm);font-size:.82rem">
        <div class="fw-bold mb-1" style="color:#a61c1c">
          <i class="bi bi-exclamation-triangle-fill me-1"></i>
          ${closedSpots.length} spot${closedSpots.length > 1 ? 's' : ''} left out — temporarily closed
        </div>
        <ul class="mb-0 ps-3" style="color:#7f1d1d">
          ${closedSpots.map(s => `
            <li>
              <strong>${s.name}</strong>${s.closure_reason ? ' — ' + s.closure_reason : ''}
              ${s.closed_until ? ` (expected back ${formatDateNice(s.closed_until)})` : ' (no reopening date yet)'}
            </li>`).join('')}
        </ul>
      </div>`;
  }
  if (timeCutSpots.length) {
    noticeHtml += `
      <div class="mb-3 p-3" style="background:#fef3c7;border:1.5px solid #fcd34d;border-radius:var(--radius-sm);font-size:.82rem">
        <div class="fw-bold mb-1" style="color:#92400e">
          <i class="bi bi-clock-history me-1"></i>
          ${timeCutSpots.length} spot${timeCutSpots.length > 1 ? 's' : ''} left out — not enough time for ${days} day${days > 1 ? 's' : ''}
        </div>
        <div style="color:#78350f">
          ${timeCutSpots.map(s => `<strong>${s.name}</strong>`).join(', ')}.
          Add more days to your trip to fit ${timeCutSpots.length > 1 ? 'them' : 'it'} in.
        </div>
      </div>`;
  }

  container.innerHTML = noticeHtml + html;
}

function itineraryItem(time, icon, name, desc, isSpot = false) {
  return `
    <div class="itinerary-item" style="display:flex;align-items:flex-start;gap:1rem;padding:1.1rem 1.3rem">
      <div class="item-time" style="min-width:68px;flex-shrink:0;white-space:nowrap">${time}</div>
      <div style="flex:1;min-width:0">
        <div style="display:flex;align-items:center;gap:.45rem;flex-wrap:nowrap;margin-bottom:.25rem">
          <i class="bi ${icon}" style="color:${isSpot?'var(--terracotta)':'var(--maroon-light)'};flex-shrink:0;font-size:1.05rem"></i>
          <span class="item-name">${name}</span>
        </div>
        <div class="item-desc">${desc}</div>
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
    : 'Free Entry';
  const fullStars = Math.round(spot.rating);
  const starsHtml = Array.from({length:5}, (_,i) =>
    `<i class="bi ${i < fullStars ? 'bi-star-fill' : 'bi-star'}"
        style="color:${i < fullStars ? 'var(--sand-dark)' : '#ccc'};font-size:.85rem"></i>`
  ).join('') + `<span style="font-size:.8rem;color:var(--text-muted);margin-left:.3rem">${parseFloat(spot.rating).toFixed(1)}</span>`;

  document.getElementById('msp-emoji-header').innerHTML = `<i class="bi ${catIcon(spot.category)}"></i>`;
  document.getElementById('msp-emoji-header').style.background = catBg(spot.category);
  document.getElementById('msp-badge').innerHTML =
    `<span class="badge-category badge-${spot.category}">${catLabel(spot.category)}</span>`;
  document.getElementById('msp-name').textContent  = spot.name;
  document.getElementById('msp-city').innerHTML    =
    `<i class="bi bi-geo-alt-fill me-1" style="color:var(--maroon-mid)"></i>${spot.city_name}`;
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
    card.style.borderColor = 'var(--maroon-mid)';
    card.style.background  = '#fbe4e4';
    // Always scroll to center of viewport, even if technically "close enough" already —
    // otherwise clicking a marker can feel like it did nothing.
    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
    card.animate(
      [
        { boxShadow: '0 0 0 0 rgba(45,106,79,.5)' },
        { boxShadow: '0 0 0 10px rgba(45,106,79,0)' },
      ],
      { duration: 700, easing: 'ease-out' }
    );
  }
}

// ── Legacy showSpotDetail kept for backward compat ──────────
window.showSpotDetail = window.flyToSpot;

// ── Category background color helper ───────────────────────
function catBg(cat) {
  const m = {
    nature:'#fbdede', heritage:'#fef3c7', waterfall:'#dbeafe',
    hotspring:'#ffe4e6', museum:'#f3e8ff', religious:'#fff7ed',
    beach_lake:'#e0f2fe', adventure:'#fef9c3', food:'#fce7f3'
  };
  return m[cat] || 'var(--maroon-pale)';
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
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN },
      body: JSON.stringify({
        origin_id:      routeData.origin.id,
        dest_id:        routeData.destination.id,
        days:           parseInt(document.getElementById('days-select').value),
        persons:        parseInt(document.getElementById('persons-select').value),
        budget_level:   document.getElementById('budget-select').value,
        transport_pref: selectedTransport?.transport_type || 'any',
        travel_date:    travelDate,
        spot_ids:       includedSpotIds,
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

// Minutes-since-midnight → "H:MM AM/PM" label. Wraps correctly past noon
// and past midnight (e.g. a heavily-packed day rolling past 12:00 AM),
// unlike a plain "hour > 12 ? hour-12 : hour" check.
function minutesToLabel(totalMinutes) {
  const h = Math.floor(totalMinutes / 60) % 24;
  const m = totalMinutes % 60;
  const period = h >= 12 ? 'PM' : 'AM';
  let h12 = h % 12;
  if (h12 === 0) h12 = 12;
  return `${h12}:${String(m).padStart(2, '0')} ${period}`;
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

function catIcon(cat) {
  const m = {
    nature:'bi-tree', heritage:'bi-bank', waterfall:'bi-droplet', hotspring:'bi-fire',
    museum:'bi-columns-gap', religious:'bi-building', beach_lake:'bi-water',
    adventure:'bi-compass', food:'bi-cup-hot'
  };
  return m[cat] || 'bi-geo-alt';
}

}); // end DOMContentLoaded
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
