<?php
// ============================================================
// IEXPLORE LAGUNA — Near Me
// pages/nearby.php
//
// A second, independent way to plan: instead of choosing a start and a
// destination (Trip Planner), the tourist shares their CURRENT location,
// sees the spots around them on the map, and gets a suggested itinerary
// that starts right where they're standing.
// Data comes from api/nearby.php. planner.php / explore.php are untouched.
// ============================================================
$page_title  = 'Near Me';
$active_page = 'nearby';
require_once __DIR__ . '/../includes/header.php';

ensure_all_laguna_cities();
$cities = db_fetch_all("SELECT id, name, latitude, longitude FROM cities ORDER BY name");
$cats   = spot_categories();
?>

<section class="py-3" style="background:linear-gradient(135deg,var(--maroon-dark),var(--maroon-mid));color:#fff">
  <div class="container d-flex align-items-center gap-3">
    <i class="bi bi-geo-fill fs-2" style="color:var(--sand-dark)"></i>
    <div>
      <h1 class="mb-0 fs-3" style="font-family:'Playfair Display',serif">Near Me</h1>
      <p class="mb-0 small opacity-75">See tourist spots around where you are right now, and get an itinerary that starts from your location</p>
    </div>
  </div>
</section>

<section class="py-3" style="background:var(--sand)">
  <div class="container">
    <div class="row g-3">

      <!-- ═════ Left: controls + results ═════ -->
      <div class="col-lg-5 order-2 order-lg-1">

        <div class="form-panel mb-3">
          <button class="btn btn-primary-app w-100 mb-2" id="nb-locate-btn">
            <i class="bi bi-crosshair me-2"></i>Use my current location
          </button>
          <div id="nb-status" class="small text-muted mb-2" role="status" aria-live="polite">
            Allow location access when your browser asks. You can also tap anywhere on the map to set your position.
          </div>

          <div class="row g-2 align-items-end mb-2">
            <div class="col-7">
              <label class="form-label small mb-1" for="nb-city">No GPS? Start from a town</label>
              <select class="form-select form-select-sm" id="nb-city">
                <option value="">— Select —</option>
                <?php foreach ($cities as $c): ?>
                  <option value="<?= (float) $c['latitude'] ?>,<?= (float) $c['longitude'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-5">
              <label class="form-label small mb-1" for="nb-radius">Radius: <span id="nb-radius-val">10</span> km</label>
              <input type="range" class="form-range" id="nb-radius" min="2" max="40" step="1" value="10">
            </div>
          </div>

          <div class="d-flex flex-wrap gap-3 small mb-2">
            <label><input type="checkbox" id="nb-hotels"> <i class="bi bi-building"></i> Hotels</label>
            <label><input type="checkbox" id="nb-shops"> <i class="bi bi-shop"></i> Food &amp; shops</label>
          </div>

          <select class="form-select form-select-sm" id="nb-category" aria-label="Spot category">
            <option value="">All spot types</option>
            <?php foreach ($cats as $key => $meta): ?>
              <option value="<?= e($key) ?>"><?= e($meta['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Itinerary options -->
        <div class="form-panel mb-3" id="nb-itin-panel">
          <h6 class="fw-bold mb-2" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">
            <i class="bi bi-signpost-split me-2" style="color:var(--maroon-light)"></i>Suggest an itinerary from here
          </h6>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label small mb-1" for="nb-hours">Time available</label>
              <select class="form-select form-select-sm" id="nb-hours">
                <option value="3">About 3 hours</option>
                <option value="5">Half day (5 hrs)</option>
                <option value="8" selected>Full day (8 hrs)</option>
                <option value="10">Long day (10 hrs)</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small mb-1" for="nb-start">Start time</label>
              <input type="time" class="form-control form-control-sm" id="nb-start" value="08:00">
            </div>
          </div>
          <label class="small d-block mb-2"><input type="checkbox" id="nb-lunch" checked> Include a lunch stop at a nearby food shop</label>
          <button class="btn btn-primary-app w-100" id="nb-itin-btn" disabled>
            <i class="bi bi-magic me-2"></i>Suggest itinerary
          </button>
        </div>

        <div id="nb-itin-result" class="mb-3"></div>

        <div class="d-flex align-items-center justify-content-between mb-2">
          <h6 class="fw-bold mb-0" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">Nearby spots</h6>
          <span class="small text-muted" id="nb-count"></span>
        </div>
        <div id="nb-list"></div>
      </div>

      <!-- ═════ Right: map ═════ -->
      <div class="col-lg-7 order-1 order-lg-2">
        <div id="nb-map" style="height:70vh;min-height:380px;border-radius:var(--radius-md,12px);box-shadow:var(--shadow-md);position:sticky;top:80px"></div>
      </div>

    </div>
  </div>
</section>

<style>
  .nb-card{background:#fff;border-radius:12px;padding:.7rem .9rem;margin-bottom:.6rem;box-shadow:var(--shadow-sm);cursor:pointer;border:2px solid transparent}
  .nb-card:hover,.nb-card.active{border-color:var(--maroon-light)}
  .nb-dist{background:var(--maroon-pale);color:var(--maroon-dark);border-radius:999px;padding:.1rem .6rem;font-size:.75rem;font-weight:700;white-space:nowrap}
  .nb-pin{width:30px;height:30px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);display:flex;align-items:center;justify-content:center;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.35)}
  .nb-pin>span{transform:rotate(45deg);color:#fff;font-weight:700;font-size:.8rem;line-height:1}
  .nb-me{width:18px;height:18px;border-radius:50%;background:#1a73e8;border:3px solid #fff;box-shadow:0 0 0 0 rgba(26,115,232,.6);animation:nbpulse 2s infinite}
  @keyframes nbpulse{70%{box-shadow:0 0 0 16px rgba(26,115,232,0)}100%{box-shadow:0 0 0 0 rgba(26,115,232,0)}}
  .nb-step{display:flex;gap:.7rem;padding:.6rem 0;border-bottom:1px dashed var(--border)}
  .nb-step:last-child{border-bottom:0}
  .nb-num{flex:0 0 28px;height:28px;border-radius:50%;background:var(--maroon-mid);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem}
  .nb-num.lunch{background:var(--terracotta)}
</style>

<script>
// Leaflet is loaded by the footer (after this tag), so wait for the page to finish loading.
document.addEventListener('DOMContentLoaded', function () {
  const API = '<?= APP_URL ?>/api/';
  const PAGE = '<?= APP_URL ?>/pages/';
  const LAGUNA_CENTER = [14.2, 121.3];
  const CITIES = <?= json_encode(array_map(fn($c) => ['id' => (int) $c['id'], 'name' => $c['name'], 'lat' => (float) $c['latitude'], 'lng' => (float) $c['longitude']], $cities), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  const LOGGED_IN = <?= is_logged_in() ? 'true' : 'false' ?>;
  let plan = null, savedId = null;   // current itinerary + whether it was saved
  const CAT_COLORS = {nature:'#2e7d32',heritage:'#b45309',waterfall:'#1d4ed8',hotspring:'#be123c',museum:'#7e22ce',religious:'#c2410c',beach_lake:'#0284c7',adventure:'#a16207',food:'#be185d'};

  const $ = id => document.getElementById(id);
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const peso = n => n > 0 ? '₱' + Number(n).toLocaleString() : 'Free';

  let me = null;                 // {lat, lng}
  let meMarker = null, radiusCircle = null;
  const markers = {};            // key -> Leaflet marker (results)
  let resultLayer, itinLayer;
  let searchTimer = null, searchSeq = 0;

  const map = L.map('nb-map').setView(LAGUNA_CENTER, 10);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'}).addTo(map);
  resultLayer = L.layerGroup().addTo(map);
  itinLayer   = L.layerGroup().addTo(map);

  function setStatus(msg, isError) {
    const el = $('nb-status');
    el.textContent = msg;
    el.className = 'small mb-2 ' + (isError ? 'text-danger' : 'text-muted');
  }

  // ── Position handling ─────────────────────────────────────
  function setPosition(lat, lng, label, fly) {
    me = {lat, lng};
    if (!meMarker) {
      meMarker = L.marker([lat, lng], {
        draggable: true, zIndexOffset: 1000, title: 'You are here',
        icon: L.divIcon({className: '', html: '<div class="nb-me"></div>', iconSize: [18, 18], iconAnchor: [9, 9]})
      }).addTo(map).bindTooltip('You are here', {permanent: false});
      meMarker.on('dragend', () => { const p = meMarker.getLatLng(); setPosition(p.lat, p.lng, 'Position moved — results updated.', false); });
    } else {
      meMarker.setLatLng([lat, lng]);
    }
    $('nb-itin-btn').disabled = false;
    if (label) setStatus(label);
    itinLayer.clearLayers();
    $('nb-itin-result').innerHTML = '';
    plan = null; savedId = null;
    updateRadiusCircle();
    if (fly) map.setView([lat, lng], 12);
    search();
  }

  function updateRadiusCircle() {
    if (!me) return;
    const km = +$('nb-radius').value;
    if (radiusCircle) map.removeLayer(radiusCircle);
    radiusCircle = L.circle([me.lat, me.lng], {radius: km * 1000, color: '#1a73e8', weight: 1, fillOpacity: 0.05, interactive: false}).addTo(map);
  }

  function locate() {
    if (!navigator.geolocation) { setStatus('Your browser does not support location. Pick a town or tap the map instead.', true); return; }
    if (!window.isSecureContext) { setStatus('Location only works on a secure (https) connection. Pick a town or tap the map instead.', true); return; }
    setStatus('Finding your location…');
    $('nb-locate-btn').disabled = true;
    navigator.geolocation.getCurrentPosition(
      pos => {
        $('nb-locate-btn').disabled = false;
        const {latitude, longitude, accuracy} = pos.coords;
        setPosition(latitude, longitude, 'Location found (accurate to about ' + Math.round(accuracy) + ' m). Drag the blue dot to adjust.', true);
      },
      err => {
        $('nb-locate-btn').disabled = false;
        const msg = {1: 'Location permission was denied.', 2: 'Your location is unavailable right now.', 3: 'Finding your location timed out.'}[err.code] || 'Could not get your location.';
        setStatus(msg + ' Pick a town, or tap the map to set your position.', true);
      },
      {enableHighAccuracy: true, timeout: 15000, maximumAge: 30000}
    );
  }

  // ── Search ────────────────────────────────────────────────
  function search() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(doSearch, 250);
  }

  async function doSearch() {
    if (!me) return;
    const seq = ++searchSeq;
    const types = ['spots'];
    if ($('nb-hotels').checked) types.push('hotels');
    if ($('nb-shops').checked) types.push('shops');
    const q = new URLSearchParams({action: 'search', lat: me.lat, lng: me.lng, radius: $('nb-radius').value, types: types.join(',')});
    if ($('nb-category').value) q.set('categories', $('nb-category').value);

    $('nb-list').innerHTML = '<div class="text-muted small">Searching…</div>';
    let res;
    try { res = await fetch(API + 'nearby.php?' + q).then(r => r.json()); }
    catch (e) { $('nb-list').innerHTML = '<div class="text-danger small">Could not load nearby places. Please try again.</div>'; return; }
    if (seq !== searchSeq) return; // a newer search superseded this one
    if (!res.success) { $('nb-list').innerHTML = '<div class="text-danger small">' + esc(res.error || res.message || 'Something went wrong.') + '</div>'; return; }

    renderResults(res.data);
  }

  function pinIcon(color, inner) {
    return L.divIcon({className: '', iconSize: [30, 30], iconAnchor: [15, 30], popupAnchor: [0, -28],
      html: '<div class="nb-pin" style="background:' + color + '"><span>' + inner + '</span></div>'});
  }

  function itemLink(it) {
    if (it.type === 'spot')  return PAGE + 'spot-detail.php?id=' + it.id;
    if (it.type === 'hotel') return PAGE + 'hotel.php?id=' + it.id;
    return PAGE + 'shop.php?id=' + it.id;
  }

  function popupHtml(it) {
    let extra = '';
    if (it.type === 'spot')  extra = esc(it.category_label) + ' · ' + peso(it.entrance_fee) + ' · ★ ' + it.rating.toFixed(1);
    if (it.type === 'hotel') extra = '★'.repeat(it.star_rating) + (it.price_min ? ' · from ' + peso(it.price_min) : '');
    if (it.type === 'shop')  extra = esc(it.category_label);
    return '<strong>' + esc(it.name) + '</strong><br><span class="small">' + extra + '<br>' + it.distance_km + ' km away · ' + esc(it.city_name) + '</span>' +
           '<br><a href="' + itemLink(it) + '">View details</a>';
  }

  function renderResults(data) {
    resultLayer.clearLayers();
    Object.keys(markers).forEach(k => delete markers[k]);
    const list = $('nb-list');
    list.innerHTML = '';

    const all = [...data.spots, ...data.hotels, ...data.shops];
    $('nb-count').textContent = data.spots.length + ' spot' + (data.spots.length === 1 ? '' : 's') + ' within ' + data.meta.radius_km + ' km';

    if (!data.spots.length) {
      let msg = 'No tourist spots within ' + data.meta.radius_km + ' km.';
      if (data.meta.nearest_spot_km) msg += ' The nearest one is about ' + data.meta.nearest_spot_km + ' km away — try a bigger radius.';
      if (!data.meta.in_laguna) msg += ' Your position looks to be outside Laguna.';
      list.innerHTML = '<div class="form-panel small text-muted">' + esc(msg) + '</div>';
    }

    all.forEach(it => {
      const key = it.type + '-' + it.id;
      const color = it.type === 'spot' ? (CAT_COLORS[it.category] || '#B0281C') : it.type === 'hotel' ? '#0f766e' : '#92400e';
      const glyph = it.type === 'spot' ? '●' : it.type === 'hotel' ? 'H' : 'S';
      const m = L.marker([it.lat, it.lng], {icon: pinIcon(color, glyph), title: it.name}).bindPopup(popupHtml(it));
      m.addTo(resultLayer);
      markers[key] = m;

      const icon = it.type === 'spot' ? 'bi-geo-alt' : it.type === 'hotel' ? 'bi-building' : 'bi-shop';
      const card = document.createElement('div');
      card.className = 'nb-card';
      card.dataset.key = key;
      card.innerHTML =
        '<div class="d-flex justify-content-between align-items-start gap-2">' +
          '<div><div class="fw-semibold"><i class="bi ' + icon + ' me-1" style="color:' + color + '"></i>' + esc(it.name) + '</div>' +
          '<div class="small text-muted">' + esc(it.type === 'hotel' ? it.city_name : (it.category_label + ' · ' + it.city_name)) +
          (it.type === 'spot' ? ' · ' + peso(it.entrance_fee) : '') + '</div></div>' +
          '<span class="nb-dist">' + it.distance_km + ' km</span></div>';
      card.addEventListener('click', () => {
        document.querySelectorAll('.nb-card.active').forEach(c => c.classList.remove('active'));
        card.classList.add('active');
        map.setView([it.lat, it.lng], Math.max(map.getZoom(), 14));
        m.openPopup();
      });
      list.appendChild(card);
    });

    if (all.length && me) {
      const b = L.latLngBounds([[me.lat, me.lng]]);
      all.forEach(it => b.extend([it.lat, it.lng]));
      map.fitBounds(b, {padding: [40, 40], maxZoom: 14});
    }
  }

  // ── Itinerary ─────────────────────────────────────────────
  const MODE_ICON = {walk: 'bi-person-walking', tricycle: 'bi-bicycle', 'jeepney / van': 'bi-bus-front'};
  const fmtTime = m => { m = ((Math.round(m) % 1440) + 1440) % 1440; const h = Math.floor(m / 60), mm = m % 60;
    return (h % 12 || 12) + ':' + String(mm).padStart(2, '0') + ' ' + (h >= 12 ? 'PM' : 'AM'); };
  const modeFor = km => km <= 1 ? 'walk' : km <= 5 ? 'tricycle' : 'jeepney / van';

  async function suggestItinerary() {
    if (!me) return;
    const btn = $('nb-itin-btn');
    btn.disabled = true;
    const out = $('nb-itin-result');
    out.innerHTML = '<div class="form-panel small text-muted">Building your itinerary…</div>';

    const q = new URLSearchParams({
      action: 'itinerary', lat: me.lat, lng: me.lng, radius: $('nb-radius').value,
      hours: $('nb-hours').value, start: $('nb-start').value || '08:00', lunch: $('nb-lunch').checked ? 1 : 0
    });
    if ($('nb-category').value) q.set('categories', $('nb-category').value);

    let res;
    try { res = await fetch(API + 'nearby.php?' + q).then(r => r.json()); }
    catch (e) { out.innerHTML = '<div class="form-panel small text-danger">Could not build an itinerary. Please try again.</div>'; btn.disabled = false; return; }
    btn.disabled = false;

    if (!res.success) { out.innerHTML = '<div class="form-panel small text-danger">' + esc(res.error || res.message) + '</div>'; return; }
    const d = res.data;
    if (!d.stops || !d.stops.length) {
      out.innerHTML = '<div class="form-panel small text-muted">' + esc(d.message || 'No itinerary could be built from here. Try a larger radius.') + '</div>';
      return;
    }
    showPlan(d);
  }

  // Re-times the plan from the numbers in each stop. `road[i]` (if present) holds the real
  // road distance/time for the leg that leads to stop i, replacing the server's estimate.
  function computeTimeline(d, road) {
    let clock = d.summary.start_min, km = 0, allRoad = true;
    const rows = d.stops.map((t, i) => {
      let leg = t.leg ? Object.assign({}, t.leg) : null, isRoad = false;
      if (leg && road[i]) {
        leg.km = road[i].km;
        leg.mode = modeFor(leg.km);
        leg.minutes = leg.mode === 'walk' ? Math.max(1, Math.round(leg.km / 4.5 * 60)) : Math.max(1, road[i].minutes);
        isRoad = true;
      }
      if (leg && !isRoad) allRoad = false;
      let arrive = clock + (leg ? leg.minutes : 0), visit = t.visit_minutes, note = null;
      if (t.kind === 'spot' && t.open_min != null && t.close_min != null) {
        if (arrive < t.open_min) { note = 'Opens at ' + fmtTime(t.open_min) + ', so wait or visit later.'; arrive = t.open_min; }
        if (arrive >= t.close_min - 20) note = 'May already be closed when you arrive (closes ' + fmtTime(t.close_min) + ').';
        else if (arrive + visit > t.close_min) { visit = Math.max(20, t.close_min - arrive); note = 'Closes at ' + fmtTime(t.close_min) + ', so visit time is shortened.'; }
      }
      if (leg) km += leg.km;
      const row = {t, leg, isRoad, arrive, depart: arrive + visit, note};
      clock = arrive + visit;
      return row;
    });
    return {rows, km, end: clock, allRoad};
  }

  function showPlan(d) {
    plan = d;
    savedId = null;
    itinLayer.clearLayers();
    const road = {};        // stop index -> {km, minutes} once TomTom/ORS answers
    paintPlan(computeTimeline(d, road));

    // Markers + lines. Each leg asks the routing service once: that answer both draws the
    // road-following line AND gives the real distance/time, so there is no extra request.
    let prev = [me.lat, me.lng], n = 0;
    const jobs = [];
    d.stops.forEach((t, i) => {
      if (!t.item) return;                       // generic lunch break: nothing to map
      const it = t.item, isLunch = t.kind === 'lunch';
      if (!isLunch) n++;
      L.marker([it.lat, it.lng], {icon: pinIcon(isLunch ? '#c77c48' : '#B0281C', isLunch ? 'L' : n)}).bindPopup(popupHtml(it)).addTo(itinLayer);
      jobs.push(drawLeg(prev, [it.lat, it.lng]).then(r => { if (r) road[i] = r; }));
      prev = [it.lat, it.lng];
    });
    const pts = [[me.lat, me.lng]].concat(d.stops.filter(t => t.item).map(t => [t.item.lat, t.item.lng]));
    map.fitBounds(L.latLngBounds(pts), {padding: [40, 40]});

    // Once every leg has answered (or timed out), refresh the times once with road figures.
    Promise.all(jobs).then(() => {
      if (plan !== d || !Object.keys(road).length) return;
      paintPlan(computeTimeline(d, road));
    });
    $('nb-itin-result').scrollIntoView({behavior: 'smooth', block: 'nearest'});
  }

  function paintPlan(tl) {
    const s = plan.summary, spots = tl.rows.filter(r => r.t.kind === 'spot').length;
    const km = Math.round(tl.km * 10) / 10;
    let html = '<div class="form-panel" style="margin-bottom:4.5rem">' +
      '<div class="d-flex justify-content-between align-items-start gap-2">' +
        '<h6 class="fw-bold mb-1" style="font-family:\'Playfair Display\',serif;color:var(--maroon-dark)">Your suggested itinerary</h6>' +
        '<button class="btn btn-sm btn-outline-secondary rounded-pill flex-shrink-0" id="nb-save-btn"' + (savedId ? ' disabled' : '') + '>' +
          (savedId ? '<i class="bi bi-check2"></i> Saved' : '<i class="bi bi-bookmark-plus"></i> Save') + '</button></div>' +
      '<div class="small text-muted mb-2">' + fmtTime(s.start_min) + ' → ' + fmtTime(tl.end) + ' · ' + spots + ' spot' + (spots === 1 ? '' : 's') +
      ' · ~' + km + ' km ' + (tl.allRoad ? 'by road' : 'travel (estimated)') + ' · entrance fees ' + peso(s.total_entrance) + '</div>' +
      (savedId ? '<div class="small mb-2"><a href="' + PAGE + 'itineraries.php">View it in My Itineraries →</a></div>' : '') +
      '<div class="nb-step"><div class="nb-num" style="background:#1a73e8"><i class="bi bi-geo-fill"></i></div><div><div class="fw-semibold">Start: your location</div><div class="small text-muted">' + fmtTime(s.start_min) + '</div></div></div>';

    let n = 0;
    tl.rows.forEach(r => {
      const t = r.t, it = t.item, isLunch = t.kind !== 'spot';
      if (!isLunch) n++;
      const label = isLunch ? '<i class="bi bi-cup-hot"></i>' : n;
      const legLine = r.leg
        ? '<div class="small text-muted"><i class="bi ' + (MODE_ICON[r.leg.mode] || 'bi-arrow-right') + '"></i> ' + esc(r.leg.mode) + ' · ~' + r.leg.km + ' km · ~' + r.leg.minutes + ' min' + (r.isRoad ? '' : ' <span title="Estimated">(est.)</span>') + '</div>'
        : '';
      const name = isLunch
        ? 'Lunch' + (it ? ': <a href="' + itemLink(it) + '" style="color:inherit">' + esc(it.name) + '</a>' : ' break')
        : '<a href="' + itemLink(it) + '" style="color:inherit">' + esc(it.name) + '</a>';
      html += '<div class="nb-step"><div class="nb-num ' + (isLunch ? 'lunch' : '') + '">' + label + '</div><div class="flex-grow-1">' + legLine +
        '<div class="fw-semibold">' + name + '</div>' +
        '<div class="small text-muted">' + fmtTime(r.arrive) + ' – ' + fmtTime(r.depart) + (isLunch ? '' : ' · ' + esc(it.category_label) + ' · ' + peso(it.entrance_fee)) + '</div>' +
        (t.why ? '<div class="small" style="color:var(--terracotta)"><i class="bi bi-lightbulb"></i> ' + esc(t.why) + '</div>' : '') +
        (r.note ? '<div class="small" style="color:var(--maroon-dark)"><i class="bi bi-info-circle"></i> ' + esc(r.note) + '</div>' : '') +
        '</div></div>';
    });

    if (plan.skipped && plan.skipped.length) html += '<div class="small text-muted mt-2">Left out: ' + plan.skipped.map(esc).join('; ') + '</div>';
    html += '<div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> ' +
      (tl.allRoad ? 'Distances and drive times are road figures from our routing service; actual commute time depends on traffic and transport.' : esc(s.estimate_note)) +
      ' Entrance fees exclude transport and food.</div></div>';
    $('nb-itin-result').innerHTML = html;
  }

  // Asks the routing service for one leg. Resolves to {km, minutes} or null; never rejects,
  // and gives up after 6 s so a slow provider can't hold the page up.
  async function drawLeg(from, to) {
    const ctl = new AbortController(), timer = setTimeout(() => ctl.abort(), 6000);
    try {
      const p = new URLSearchParams({origin_lat: from[0], origin_lng: from[1], dest_lat: to[0], dest_lng: to[1]});
      const r = await fetch(API + 'routes.php?action=directions&' + p, {signal: ctl.signal}).then(x => x.json());
      const coords = r && r.success && r.data && r.data.coordinates;
      if (coords && coords.length > 1) {
        L.polyline(coords, {color: '#B0281C', weight: 4, opacity: .85}).addTo(itinLayer);
        const km = parseFloat(r.data.distance_km), min = parseFloat(r.data.duration_min);
        return (isFinite(km) && isFinite(min) && km >= 0 && min >= 0) ? {km: Math.round(km * 10) / 10, minutes: Math.round(min)} : null;
      }
    } catch (e) { /* fall through to straight line */ }
    finally { clearTimeout(timer); }
    L.polyline([from, to], {color: '#B0281C', weight: 3, dashArray: '6 8', opacity: .8}).addTo(itinLayer);
    return null;
  }

  // ── Save to My Itineraries (uses the existing itineraries API) ──
  function nearestCity(lat, lng) {
    let best = CITIES[0], bd = Infinity;
    CITIES.forEach(c => { const d = (c.lat - lat) ** 2 + ((c.lng - lng) * Math.cos(lat * Math.PI / 180)) ** 2; if (d < bd) { bd = d; best = c; } });
    return best;
  }

  async function savePlan() {
    if (!plan) return;
    if (!LOGGED_IN) {
      IExploreApp.toast('Please log in to save your itinerary.', 'warning');
      setTimeout(() => { window.location.href = PAGE + 'login.php'; }, 1500);
      return;
    }
    const spots = plan.stops.filter(t => t.kind === 'spot').map(t => t.item);
    if (!spots.length) return;
    const origin = nearestCity(me.lat, me.lng), last = spots[spots.length - 1];
    const btn = $('nb-save-btn'); btn.disabled = true;
    try {
      const res = await fetch(API + 'itineraries.php?action=save', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN},
        body: JSON.stringify({
          origin_id: origin.id, dest_id: last.city_id, days: 1, persons: 1,
          budget_level: 'midrange', transport_pref: 'any', travel_date: null,
          title: 'Near Me plan from ' + origin.name,
          spot_ids: spots.map(x => x.id),
          total_budget: plan.summary.total_entrance,
          itinerary_json: [{day: 1, city: origin.name, spots: spots.map(x => ({name: x.name, city: x.city_name, price: x.entrance_fee})), shops: []}]
        })
      }).then(r => r.json());
      if (res.success) {
        savedId = (res.data && res.data.id) || true;
        IExploreApp.toast('Itinerary saved! View it in My Itineraries.', 'success');
        const keep = $('nb-itin-result').firstElementChild;   // repaint with the "Saved" state
        if (keep) { const b = $('nb-save-btn'); b.disabled = true; b.innerHTML = '<i class="bi bi-check2"></i> Saved'; }
      } else {
        btn.disabled = false;
        IExploreApp.toast(res.message || res.error || 'Could not save itinerary.', 'error');
      }
    } catch (e) {
      btn.disabled = false;
      IExploreApp.toast('Could not save itinerary. Please try again.', 'error');
    }
  }

  // ── Wiring ────────────────────────────────────────────────
  $('nb-locate-btn').addEventListener('click', locate);
  $('nb-itin-btn').addEventListener('click', suggestItinerary);
  $('nb-itin-result').addEventListener('click', e => { if (e.target.closest('#nb-save-btn')) savePlan(); });
  $('nb-radius').addEventListener('input', () => { $('nb-radius-val').textContent = $('nb-radius').value; updateRadiusCircle(); search(); });
  ['nb-hotels', 'nb-shops', 'nb-category'].forEach(id => $(id).addEventListener('change', search));
  $('nb-city').addEventListener('change', e => {
    if (!e.target.value) return;
    const [lat, lng] = e.target.value.split(',').map(Number);
    setPosition(lat, lng, 'Starting from ' + e.target.selectedOptions[0].text + '. Drag the blue dot to refine.', true);
  });
  map.on('click', e => setPosition(e.latlng.lat, e.latlng.lng, 'Position set from the map. Drag the blue dot to adjust.', false));
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
