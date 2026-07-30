<?php
// ============================================================
// IEXPLORE LAGUNA — Route API
// api/routes.php
// GET  ?action=cities                  → all cities list
// GET  ?action=route&origin=1&dest=2   → route + transport options
// GET  ?action=spots&origin=1&dest=2   → tourist spots along route
// ============================================================
require_once __DIR__ . '/../includes/helpers.php';
set_api_headers();

$action = input('action', 'get', 'cities');

switch ($action) {

    // ── All cities for dropdowns ──────────────────────────
    case 'cities':
        $cities = db_fetch_all(
            "SELECT id, name, slug, latitude, longitude, description
             FROM cities ORDER BY name"
        );
        json_ok($cities);
        break;

    // ── Route between two cities ──────────────────────────
    case 'route':
        $origin = (int) input('origin', 'get');
        $dest   = (int) input('dest',   'get');

        if (!$origin || !$dest) {
            json_error('Origin and destination are required.', 400);
        }
        if ($origin === $dest) {
            json_error('Origin and destination must be different.', 400);
        }

        // Get origin and destination city details
        $origin_city = db_fetch_one(
            "SELECT id, name, slug, latitude, longitude FROM cities WHERE id = ?",
            [$origin]
        );
        $dest_city = db_fetch_one(
            "SELECT id, name, slug, latitude, longitude FROM cities WHERE id = ?",
            [$dest]
        );

        if (!$origin_city || !$dest_city) {
            json_error('City not found.', 404);
        }

        // Get all transport options for this route
        $transport_options = db_fetch_all(
            "SELECT r.*,
                    o.name AS origin_name, o.latitude AS origin_lat, o.longitude AS origin_lng,
                    d.name AS dest_name,   d.latitude AS dest_lat,   d.longitude AS dest_lng
             FROM routes r
             JOIN cities o ON r.origin_city_id = o.id
             JOIN cities d ON r.dest_city_id   = d.id
             WHERE r.origin_city_id = ? AND r.dest_city_id = ?
             ORDER BY r.fare_php ASC",
            [$origin, $dest]
        );

        // If no direct route, try reverse direction
        if (empty($transport_options)) {
            $transport_options = db_fetch_all(
                "SELECT r.*,
                        o.name AS origin_name, o.latitude AS origin_lat, o.longitude AS origin_lng,
                        d.name AS dest_name,   d.latitude AS dest_lat,   d.longitude AS dest_lng
                 FROM routes r
                 JOIN cities o ON r.origin_city_id = o.id
                 JOIN cities d ON r.dest_city_id   = d.id
                 WHERE r.origin_city_id = ? AND r.dest_city_id = ?
                 ORDER BY r.fare_php ASC",
                [$dest, $origin]
            );
            // Swap city references if reversed
            foreach ($transport_options as &$t) {
                [$t['origin_name'], $t['dest_name']] = [$t['dest_name'], $t['origin_name']];
                [$t['origin_lat'],  $t['dest_lat']]  = [$t['dest_lat'],  $t['origin_lat']];
                [$t['origin_lng'],  $t['dest_lng']]  = [$t['dest_lng'],  $t['origin_lng']];
            }
            unset($t);
        }

        // Straight-line origin/destination coords. Note: the frontend
        // doesn't actually use this field — it draws the real
        // road-following route via the 'directions' action below
        // (OpenRouteService), using origin/destination coords directly.
        // Kept here for API completeness / potential future use.
        $waypoints = [
            ['lat' => (float)$origin_city['latitude'],  'lng' => (float)$origin_city['longitude'],  'name' => $origin_city['name']],
            ['lat' => (float)$dest_city['latitude'],    'lng' => (float)$dest_city['longitude'],    'name' => $dest_city['name']],
        ];

        json_ok([
            'origin'            => $origin_city,
            'destination'       => $dest_city,
            'transport_options' => $transport_options,
            'waypoints'         => $waypoints,
            'has_route'         => !empty($transport_options),
        ]);
        break;

    // ── Tourist spots along/near the route ────────────────
    case 'spots':
        $origin = (int) input('origin', 'get');
        $dest   = (int) input('dest',   'get');

        if (!$origin || !$dest) {
            json_error('Origin and destination are required.', 400);
        }

        // Get spots in both origin and destination cities
        // plus any city that sits "between" them geographically
        // Strategy: get all spots, filter by city_id IN (origin, dest)
        // plus cities whose lat/lng falls between the two endpoints
        $origin_city = db_fetch_one("SELECT latitude, longitude FROM cities WHERE id = ?", [$origin]);
        $dest_city   = db_fetch_one("SELECT latitude, longitude FROM cities WHERE id = ?", [$dest]);

        if (!$origin_city || !$dest_city) {
            json_error('City not found.', 404);
        }

        // Bounding box with padding
        $min_lat = min($origin_city['latitude'], $dest_city['latitude']) - 0.15;
        $max_lat = max($origin_city['latitude'], $dest_city['latitude']) + 0.15;
        $min_lng = min($origin_city['longitude'], $dest_city['longitude']) - 0.15;
        $max_lng = max($origin_city['longitude'], $dest_city['longitude']) + 0.15;

        $spots = db_fetch_all(
            "SELECT s.id, s.name, s.slug, s.description, s.category,
                    s.latitude, s.longitude, s.entrance_fee,
                    s.operating_hours, s.rating, s.image_url,
                    s.is_closed, s.closure_reason, s.closed_until,
                    c.name AS city_name, c.id AS city_id
             FROM tourist_spots s
             JOIN cities c ON s.city_id = c.id
             WHERE s.is_active = 1
               AND s.latitude  BETWEEN ? AND ?
               AND s.longitude BETWEEN ? AND ?
             ORDER BY
               CASE WHEN s.city_id IN (?,?) THEN 0 ELSE 1 END,
               s.rating DESC",
            [$min_lat, $max_lat, $min_lng, $max_lng, $origin, $dest]
        );

        // Cast numeric fields, and resolve each spot's closure status
        // relative to today (auto-expires closures whose closed_until
        // date has already passed, even if the admin never flipped the
        // flag back).
        foreach ($spots as &$s) {
            $s['latitude']     = (float) $s['latitude'];
            $s['longitude']    = (float) $s['longitude'];
            $s['entrance_fee'] = (float) $s['entrance_fee'];
            $s['rating']       = (float) $s['rating'];

            $status = spot_closure_status($s);
            $s['is_closed']     = $status['closed'];
            $s['closure_reason']= $status['reason'];
            $s['closed_until']  = $status['closed_until'];
        }
        unset($s);

        json_ok($spots);
        break;

    // ── Live road-following route via OpenRouteService ─────
    // Server-side proxy so the ORS API key never reaches the browser.
    // Returns GeoJSON line coordinates for Leaflet to draw directly —
    // no client-side routing library needed.
    case 'directions':
        $oLat = (float) input('origin_lat', 'get');
        $oLng = (float) input('origin_lng', 'get');
        $dLat = (float) input('dest_lat',   'get');
        $dLng = (float) input('dest_lng',   'get');

        if (!$oLat || !$oLng || !$dLat || !$dLng) {
            json_error('Origin and destination coordinates are required.', 400);
        }
        if (!ORS_API_KEY) {
            json_error('ORS API key not configured.', 501);
        }

        $url = 'https://api.openrouteservice.org/v2/directions/driving-car'
             . '?api_key=' . urlencode(ORS_API_KEY)
             . '&start=' . $oLng . ',' . $oLat
             . '&end='   . $dLng . ',' . $dLat;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_HTTPHEADER     => ['Accept: application/json, application/geo+json'],
        ]);
        $raw     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $httpCode !== 200) {
            json_error('Could not reach routing service' . ($curlErr ? ": {$curlErr}" : '.'), 502);
        }

        $data = json_decode($raw, true);
        $coords = $data['features'][0]['geometry']['coordinates'] ?? null;
        $summary = $data['features'][0]['properties']['summary'] ?? null;

        if (!$coords) {
            json_error('No route found between these points.', 404);
        }

        // ORS returns [lng, lat] pairs — flip to [lat, lng] for Leaflet
        $latlngs = array_map(fn($c) => [(float)$c[1], (float)$c[0]], $coords);

        json_ok([
            'coordinates'  => $latlngs,
            'distance_km'  => isset($summary['distance']) ? round($summary['distance'] / 1000, 1) : null,
            'duration_min' => isset($summary['duration']) ? round($summary['duration'] / 60) : null,
        ]);
        break;

    default:
        json_error('Unknown action.', 400);
}