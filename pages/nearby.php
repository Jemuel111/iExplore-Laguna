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
    renderItinerary(d);
  }

  function renderItinerary(d) {
    itinLayer.clearLayers();
    const s = d.summary;
    let html = '<div class="form-panel"><h6 class="fw-bold mb-1" style="font-family:\'Playfair Display\',serif;color:var(--maroon-dark)">Your suggested itinerary</h6>' +
      '<div class="small text-muted mb-2">' + s.start + ' → ' + s.end + ' · ' + s.spots + ' spot' + (s.spots === 1 ? '' : 's') +
      ' · ~' + s.total_travel_km + ' km travel · entrance fees ' + peso(s.total_entrance) + '</div>' +
      '<div class="nb-step"><div class="nb-num" style="background:#1a73e8"><i class="bi bi-geo-fill"></i></div><div><div class="fw-semibold">Start: your location</div><div class="small text-muted">' + s.start + '</div></div></div>';

    let prev = [me.lat, me.lng], n = 0;
    const legs = [];
    d.stops.forEach(t => {
      const it = t.item, isLunch = t.kind === 'lunch';
      if (!isLunch) n++;
      const label = isLunch ? '<i class="bi bi-cup-hot"></i>' : n;
      const m = L.marker([it.lat, it.lng], {icon: pinIcon(isLunch ? '#c77c48' : '#B0281C', isLunch ? 'L' : n)}).bindPopup(popupHtml(it));
      m.addTo(itinLayer);
      legs.push({from: prev, to: [it.lat, it.lng]});
      prev = [it.lat, it.lng];

      html += '<div class="nb-step"><div class="nb-num ' + (isLunch ? 'lunch' : '') + '">' + label + '</div><div class="flex-grow-1">' +
        '<div class="small text-muted"><i class="bi ' + (MODE_ICON[t.leg.mode] || 'bi-arrow-right') + '"></i> ' + esc(t.leg.mode) + ' · ~' + t.leg.km + ' km · ~' + t.leg.minutes + ' min</div>' +
        '<div class="fw-semibold">' + (isLunch ? 'Lunch: ' : '') + '<a href="' + itemLink(it) + '" style="color:inherit">' + esc(it.name) + '</a></div>' +
        '<div class="small text-muted">' + esc(t.arrive) + ' – ' + esc(t.depart) + (isLunch ? '' : ' · ' + esc(it.category_label) + ' · ' + peso(it.entrance_fee)) + '</div>' +
        (t.note ? '<div class="small" style="color:var(--maroon-dark)"><i class="bi bi-info-circle"></i> ' + esc(t.note) + '</div>' : '') +
        '</div></div>';
    });

    if (d.skipped && d.skipped.length) {
      html += '<div class="small text-muted mt-2">Left out: ' + d.skipped.map(esc).join('; ') + '</div>';
    }
    html += '<div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> ' + esc(s.estimate_note) + '</div></div>';
    $('nb-itin-result').innerHTML = html;

    // Draw each leg: road-following line if the routing service answers, dashed straight line otherwise.
    const all = [[me.lat, me.lng]].concat(legs.map(l => l.to));
    map.fitBounds(L.latLngBounds(all), {padding: [40, 40]});
    legs.forEach(drawLeg);
    $('nb-itin-result').scrollIntoView({behavior: 'smooth', block: 'nearest'});
  }

  async function drawLeg(leg) {
    const straight = () => L.polyline([leg.from, leg.to], {color: '#B0281C', weight: 3, dashArray: '6 8', opacity: .8}).addTo(itinLayer);
    try {
      const p = new URLSearchParams({origin_lat: leg.from[0], origin_lng: leg.from[1], dest_lat: leg.to[0], dest_lng: leg.to[1]});
      const r = await fetch(API + 'routes.php?action=directions&' + p).then(x => x.json());
      const coords = r && r.success && r.data && r.data.coordinates;
      if (coords && coords.length > 1) { L.polyline(coords, {color: '#B0281C', weight: 4, opacity: .85}).addTo(itinLayer); return; }
    } catch (e) { /* fall through */ }
    straight();
  }

  // ── Wiring ────────────────────────────────────────────────
  $('nb-locate-btn').addEventListener('click', locate);
  $('nb-itin-btn').addEventListener('click', suggestItinerary);
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
