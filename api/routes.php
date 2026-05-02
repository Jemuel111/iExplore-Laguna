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

        // Build waypoints for map (straight line via coordinates)
        // In production you'd call OSRM; for now we return city coords for Leaflet polyline
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

        // Cast numeric fields
        foreach ($spots as &$s) {
            $s['latitude']     = (float) $s['latitude'];
            $s['longitude']    = (float) $s['longitude'];
            $s['entrance_fee'] = (float) $s['entrance_fee'];
            $s['rating']       = (float) $s['rating'];
        }
        unset($s);

        json_ok($spots);
        break;

    default:
        json_error('Unknown action.', 400);
}