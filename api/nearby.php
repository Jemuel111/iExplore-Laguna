<?php
// ============================================================
// IEXPLORE LAGUNA — "Near Me" API
// api/nearby.php
//
// Separate from the route-based Trip Planner (routes.php / planner.php).
// Everything here starts from the tourist's CURRENT position.
//
// GET ?action=search&lat=14.2&lng=121.16&radius=10&types=spots,hotels,shops
//     → items within <radius> km, nearest first, each with distance_km
// GET ?action=itinerary&lat=..&lng=..&radius=10&hours=8&start=08:00
//         &categories=nature,museum&lunch=1
//     → a suggested stop-by-stop plan that begins at the tourist's position
// ============================================================
require_once __DIR__ . '/../includes/helpers.php';
set_api_headers();

const NEARBY_MAX_RADIUS_KM = 50.0;
// Rough bounding box of Laguna province (with margin) — used only to warn
// the tourist when they are clearly outside the area we cover.
const LAGUNA_BBOX = ['minLat' => 13.85, 'maxLat' => 14.55, 'minLng' => 121.00, 'maxLng' => 121.65];

/** Reads and validates lat/lng. Exits with a JSON error if invalid. */
function nearby_read_position(): array {
    $lat = input('lat', 'get', null);
    $lng = input('lng', 'get', null);
    if (!is_numeric($lat) || !is_numeric($lng)) {
        json_error('Your location (lat, lng) is required.', 400);
    }
    $lat = (float) $lat;
    $lng = (float) $lng;
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        json_error('Invalid coordinates.', 400);
    }
    return [$lat, $lng];
}

function nearby_read_radius(): float {
    $r = (float) input('radius', 'get', 10);
    if ($r <= 0) $r = 10;
    return min($r, NEARBY_MAX_RADIUS_KM);
}

function nearby_in_laguna(float $lat, float $lng): bool {
    $b = LAGUNA_BBOX;
    return $lat >= $b['minLat'] && $lat <= $b['maxLat'] && $lng >= $b['minLng'] && $lng <= $b['maxLng'];
}

/** SQL expression for great-circle distance (km) — uses 6 positional params: lat, lng, lat. */
function nearby_distance_sql(string $latCol, string $lngCol): string {
    return "(6371 * ACOS(LEAST(1, GREATEST(-1,
              COS(RADIANS(?)) * COS(RADIANS($latCol)) * COS(RADIANS($lngCol) - RADIANS(?))
            + SIN(RADIANS(?)) * SIN(RADIANS($latCol))))))";
}

/** Nearby active, currently-open spots. */
function nearby_spots(float $lat, float $lng, float $radiusKm, ?array $categories = null, int $limit = 60): array {
    $dist = nearby_distance_sql('s.latitude', 's.longitude');
    $sql  = "SELECT s.id, s.name, s.slug, s.category, s.latitude, s.longitude, s.entrance_fee,
                    s.operating_hours, s.rating, s.is_closed, s.closure_reason, s.closed_until,
                    c.name AS city_name,
                    (SELECT url FROM spot_photos WHERE spot_id = s.id AND photo_type = 'main' LIMIT 1) AS main_photo_url,
                    $dist AS distance_km
             FROM tourist_spots s
             JOIN cities c ON s.city_id = c.id
             WHERE s.is_active = 1";
    $params = [$lat, $lng, $lat];

    if ($categories) {
        $sql .= ' AND s.category IN (' . implode(',', array_fill(0, count($categories), '?')) . ')';
        array_push($params, ...$categories);
    }
    $sql .= " HAVING distance_km <= ? ORDER BY distance_km ASC LIMIT " . (int) $limit;
    $params[] = $radiusKm;

    $rows = db_fetch_all($sql, $params);
    $out  = [];
    foreach ($rows as $r) {
        $closure = spot_closure_status($r);
        if ($closure['closed']) continue; // don't suggest places that are shut right now
        $out[] = [
            'type'           => 'spot',
            'id'             => (int) $r['id'],
            'name'           => $r['name'],
            'slug'           => $r['slug'],
            'category'       => $r['category'],
            'category_label' => spot_category_label($r['category']),
            'city_name'      => $r['city_name'],
            'lat'            => (float) $r['latitude'],
            'lng'            => (float) $r['longitude'],
            'entrance_fee'   => (float) $r['entrance_fee'],
            'operating_hours'=> $r['operating_hours'],
            'rating'         => (float) $r['rating'],
            'photo'          => $r['main_photo_url'],
            'distance_km'    => round((float) $r['distance_km'], 2),
        ];
    }
    return $out;
}

function nearby_hotels(float $lat, float $lng, float $radiusKm, int $limit = 30): array {
    $dist = nearby_distance_sql('h.latitude', 'h.longitude');
    $rows = db_fetch_all(
        "SELECT h.id, h.name, h.star_rating, h.price_min, h.price_max, h.address,
                h.latitude, h.longitude, c.name AS city_name, $dist AS distance_km
         FROM hotels h
         JOIN cities c ON h.city_id = c.id
         WHERE h.is_active = 1 AND h.is_verified = 1
           AND h.latitude IS NOT NULL AND h.longitude IS NOT NULL
         HAVING distance_km <= ? ORDER BY distance_km ASC LIMIT " . (int) $limit,
        [$lat, $lng, $lat, $radiusKm]
    );
    return array_map(fn($r) => [
        'type'        => 'hotel',
        'id'          => (int) $r['id'],
        'name'        => $r['name'],
        'city_name'   => $r['city_name'],
        'address'     => $r['address'],
        'star_rating' => (int) $r['star_rating'],
        'price_min'   => $r['price_min'] !== null ? (float) $r['price_min'] : null,
        'price_max'   => $r['price_max'] !== null ? (float) $r['price_max'] : null,
        'lat'         => (float) $r['latitude'],
        'lng'         => (float) $r['longitude'],
        'distance_km' => round((float) $r['distance_km'], 2),
    ], $rows);
}

function nearby_shops(float $lat, float $lng, float $radiusKm, ?array $categories = null, int $limit = 30): array {
    $dist   = nearby_distance_sql('s.latitude', 's.longitude');
    $params = [$lat, $lng, $lat];
    $sql    = "SELECT s.id, s.name, s.category, s.address, s.open_time, s.close_time,
                      s.latitude, s.longitude, c.name AS city_name, $dist AS distance_km
               FROM shops s
               JOIN cities c ON s.city_id = c.id
               WHERE s.is_active = 1 AND s.is_verified = 1
                 AND s.latitude IS NOT NULL AND s.longitude IS NOT NULL";
    if ($categories) {
        $sql .= ' AND s.category IN (' . implode(',', array_fill(0, count($categories), '?')) . ')';
        array_push($params, ...$categories);
    }
    $sql .= " HAVING distance_km <= ? ORDER BY distance_km ASC LIMIT " . (int) $limit;
    $params[] = $radiusKm;

    return array_map(fn($r) => [
        'type'           => 'shop',
        'id'             => (int) $r['id'],
        'name'           => $r['name'],
        'category'       => $r['category'],
        'category_label' => shop_category_label($r['category']),
        'city_name'      => $r['city_name'],
        'address'        => $r['address'],
        'open_time'      => $r['open_time'],
        'close_time'     => $r['close_time'],
        'lat'            => (float) $r['latitude'],
        'lng'            => (float) $r['longitude'],
        'distance_km'    => round((float) $r['distance_km'], 2),
    ], db_fetch_all($sql, $params));
}

// ── Itinerary helpers ───────────────────────────────────────

/** Typical time spent at a place, by category (minutes). */
function nearby_visit_minutes(string $category): int {
    return [
        'waterfall' => 120, 'hotspring' => 120, 'adventure' => 120,
        'nature'    => 90,  'beach_lake' => 90,
        'museum'    => 60,  'heritage'    => 45,
        'food'      => 45,  'religious'   => 30,
    ][$category] ?? 60;
}

/**
 * Estimated travel between two points. Straight-line distance is scaled by 1.4
 * to approximate road distance; short hops are walked, longer ones assume an
 * average 25 km/h by tricycle/jeepney. These are ESTIMATES, not live routing.
 */
function nearby_leg(float $lat1, float $lng1, float $lat2, float $lng2): array {
    $straightKm = haversine_meters($lat1, $lng1, $lat2, $lng2) / 1000;
    $roadKm     = $straightKm * 1.4;
    if ($roadKm <= 1.0) {
        return ['km' => round($roadKm, 1), 'minutes' => max(1, (int) round($roadKm / 4.5 * 60)), 'mode' => 'walk'];
    }
    $mode = $roadKm <= 5 ? 'tricycle' : 'jeepney / van';
    return ['km' => round($roadKm, 1), 'minutes' => max(3, (int) round($roadKm / 25 * 60)), 'mode' => $mode];
}

/** Parses "8:00 AM – 5:00 PM" → [openMin, closeMin] in minutes since midnight, or null if unknown. */
function nearby_parse_hours(?string $text): ?array {
    if (!$text) return null;
    if (!preg_match_all('/(\d{1,2})(?::(\d{2}))?\s*(AM|PM)/i', $text, $m, PREG_SET_ORDER) || count($m) < 2) {
        return null;
    }
    $toMin = function (array $x): int {
        $h = (int) $x[1] % 12;
        if (strtoupper($x[3]) === 'PM') $h += 12;
        return $h * 60 + (int) ($x[2] ?? 0);
    };
    return [$toMin($m[0]), $toMin($m[1])];
}

function nearby_fmt_time(int $min): string {
    $min = $min % (24 * 60);
    $h = intdiv($min, 60);
    return sprintf('%d:%02d %s', $h % 12 === 0 ? 12 : $h % 12, $min % 60, $h >= 12 ? 'PM' : 'AM');
}

/** Orders stops nearest-neighbour from the start, then improves with 2-opt. */
function nearby_order_stops(float $lat, float $lng, array $stops): array {
    $n = count($stops);
    if ($n < 2) return $stops;

    $d = fn($a, $b) => haversine_meters($a['lat'], $a['lng'], $b['lat'], $b['lng']);
    $start = ['lat' => $lat, 'lng' => $lng];

    // nearest neighbour
    $left = $stops; $route = []; $cur = $start;
    while ($left) {
        $bi = 0; $bd = INF;
        foreach ($left as $i => $s) {
            $dd = $d($cur, $s);
            if ($dd < $bd) { $bd = $dd; $bi = $i; }
        }
        $cur = $left[$bi];
        $route[] = $cur;
        array_splice($left, $bi, 1);
    }

    // 2-opt on the open path (start fixed, end free)
    $len = function (array $r) use ($d, $start): float {
        $t = 0; $prev = $start;
        foreach ($r as $s) { $t += $d($prev, $s); $prev = $s; }
        return $t;
    };
    $improved = true; $guard = 0;
    while ($improved && $guard++ < 50) {
        $improved = false;
        for ($i = 0; $i < $n - 1; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $cand = array_merge(
                    array_slice($route, 0, $i),
                    array_reverse(array_slice($route, $i, $j - $i + 1)),
                    array_slice($route, $j + 1)
                );
                if ($len($cand) + 1 < $len($route)) { $route = $cand; $improved = true; }
            }
        }
    }
    return $route;
}

switch (input('action', 'get', 'search')) {

    // ── Everything near the tourist ──────────────────────────
    case 'search':
        [$lat, $lng] = nearby_read_position();
        $radius = nearby_read_radius();

        $types = array_filter(array_map('trim', explode(',', (string) input('types', 'get', 'spots'))));
        $cats  = array_values(array_intersect(
            array_filter(array_map('trim', explode(',', (string) input('categories', 'get', '')))),
            array_keys(spot_categories())
        ));

        $data = ['spots' => [], 'hotels' => [], 'shops' => []];
        if (in_array('spots',  $types, true)) $data['spots']  = nearby_spots($lat, $lng, $radius, $cats ?: null);
        if (in_array('hotels', $types, true)) $data['hotels'] = nearby_hotels($lat, $lng, $radius);
        if (in_array('shops',  $types, true)) $data['shops']  = nearby_shops($lat, $lng, $radius);

        $meta = [
            'origin'      => ['lat' => $lat, 'lng' => $lng],
            'radius_km'   => $radius,
            'in_laguna'   => nearby_in_laguna($lat, $lng),
            'nearest_spot_km' => null,
        ];
        // If nothing is in range, tell the tourist how far the closest spot actually is.
        if (!$data['spots']) {
            $far = nearby_spots($lat, $lng, 500, $cats ?: null, 1);
            if ($far) $meta['nearest_spot_km'] = $far[0]['distance_km'];
        }
        json_ok(['meta' => $meta] + $data);
        break;

    // ── Suggested itinerary starting from the tourist's position ──
    case 'itinerary':
        [$lat, $lng] = nearby_read_position();
        $radius = nearby_read_radius();
        $hours  = (int) input('hours', 'get', 8);
        $hours  = max(2, min($hours, 12));
        $budgetMin = $hours * 60;

        $startRaw = (string) input('start', 'get', '08:00');
        if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $startRaw, $sm)) $startRaw = '08:00';
        $startMin = (int) $sm[1] * 60 + (int) $sm[2];

        $cats = array_values(array_intersect(
            array_filter(array_map('trim', explode(',', (string) input('categories', 'get', '')))),
            array_keys(spot_categories())
        ));
        $wantLunch = (string) input('lunch', 'get', '1') !== '0';

        $candidates = nearby_spots($lat, $lng, $radius, $cats ?: null, 40);
        if (!$candidates) {
            json_ok(['stops' => [], 'summary' => null, 'message' => 'No open tourist spots found within ' . $radius . ' km of your location.']);
            break;
        }

        // 1) Keep the best candidates: rating up, distance down.
        usort($candidates, function ($a, $b) use ($radius) {
            $sa = $a['rating'] - 2.0 * ($a['distance_km'] / max($radius, 1));
            $sb = $b['rating'] - 2.0 * ($b['distance_km'] / max($radius, 1));
            return $sb <=> $sa;
        });
        $maxStops = max(2, min(8, (int) floor($hours / 1.5)));
        $pool = array_slice($candidates, 0, $maxStops + 2);

        // 2) Fill the time budget, then order the stops geographically.
        //    Spots that share identical coordinates (e.g. a shrine and its museum)
        //    are naturally visited back-to-back with ~0 travel time.
        $chosen = [];
        $used   = 0; $cur = ['lat' => $lat, 'lng' => $lng];
        $remaining = $pool;
        while ($remaining && count($chosen) < $maxStops) {
            // pick the pool item with the best score relative to where we are now
            $bi = null; $bs = -INF;
            foreach ($remaining as $i => $s) {
                $leg = nearby_leg($cur['lat'], $cur['lng'], $s['lat'], $s['lng']);
                $cost = $leg['minutes'] + nearby_visit_minutes($s['category']);
                if ($used + $cost > $budgetMin) continue;
                $score = $s['rating'] - $leg['minutes'] / 30;
                if ($score > $bs) { $bs = $score; $bi = $i; }
            }
            if ($bi === null) break;
            $s = $remaining[$bi];
            $leg = nearby_leg($cur['lat'], $cur['lng'], $s['lat'], $s['lng']);
            $used += $leg['minutes'] + nearby_visit_minutes($s['category']);
            $chosen[] = $s;
            $cur = $s;
            array_splice($remaining, $bi, 1);
        }
        if (!$chosen) {
            // Nothing fit the time window — offer the single closest spot instead of an empty plan.
            $chosen = [$candidates[0]];
        }
        $chosen = nearby_order_stops($lat, $lng, $chosen);

        // 3) Build the timeline, honouring opening hours and adding a lunch break.
        $foodShops = $wantLunch ? array_values(array_filter(
            nearby_shops($lat, $lng, $radius, ['restaurant', 'cafe', 'street_food'], 40),
            fn($s) => true
        )) : [];

        $timeline = []; $clock = $startMin; $prev = ['lat' => $lat, 'lng' => $lng];
        $totalFee = 0.0; $totalKm = 0.0; $lunchDone = false; $skipped = [];
        $endLimit = $startMin + $budgetMin;

        foreach ($chosen as $s) {
            // lunch before this stop if we'd otherwise pass noon without eating
            if ($wantLunch && !$lunchDone && $clock >= 11 * 60 + 30 && $clock <= 14 * 60) {
                $nearestFood = null; $nd = INF;
                foreach ($foodShops as $f) {
                    $dd = haversine_meters($prev['lat'], $prev['lng'], $f['lat'], $f['lng']);
                    if ($dd < $nd) { $nd = $dd; $nearestFood = $f; }
                }
                if ($nearestFood && $nd < 5000) {
                    $leg = nearby_leg($prev['lat'], $prev['lng'], $nearestFood['lat'], $nearestFood['lng']);
                    $clock += $leg['minutes'];
                    $timeline[] = ['kind' => 'lunch', 'item' => $nearestFood, 'arrive' => nearby_fmt_time($clock),
                                   'depart' => nearby_fmt_time($clock + 45), 'leg' => $leg];
                    $clock += 45; $totalKm += $leg['km'];
                    $prev = $nearestFood; $lunchDone = true;
                }
            }

            $leg = nearby_leg($prev['lat'], $prev['lng'], $s['lat'], $s['lng']);
            $arrive = $clock + $leg['minutes'];
            $visit  = nearby_visit_minutes($s['category']);

            // opening hours: wait if early, skip if we'd arrive after closing
            $hoursRange = nearby_parse_hours($s['operating_hours']);
            $note = null;
            if ($hoursRange) {
                [$open, $close] = $hoursRange;
                if ($arrive >= $close - 20) { $skipped[] = $s['name'] . ' (closes ' . nearby_fmt_time($close) . ')'; continue; }
                if ($arrive < $open) { $note = 'Opens at ' . nearby_fmt_time($open) . ' — wait or visit later.'; $arrive = $open; }
                if ($arrive + $visit > $close) { $visit = max(20, $close - $arrive); $note = 'Closes at ' . nearby_fmt_time($close) . ' — visit time shortened.'; }
            }
            if ($arrive + $visit > $endLimit + 30) { $skipped[] = $s['name'] . ' (not enough time)'; continue; }

            $timeline[] = ['kind' => 'spot', 'item' => $s, 'arrive' => nearby_fmt_time($arrive),
                           'depart' => nearby_fmt_time($arrive + $visit), 'visit_minutes' => $visit,
                           'leg' => $leg, 'note' => $note];
            $clock = $arrive + $visit;
            $totalKm += $leg['km'];
            $totalFee += $s['entrance_fee'];
            $prev = $s;
        }

        $spotCount = count(array_filter($timeline, fn($t) => $t['kind'] === 'spot'));
        json_ok([
            'origin'   => ['lat' => $lat, 'lng' => $lng],
            'stops'    => $timeline,
            'skipped'  => $skipped,
            'summary'  => [
                'spots'           => $spotCount,
                'total_entrance'  => round($totalFee, 2),
                'total_travel_km' => round($totalKm, 1),
                'start'           => nearby_fmt_time($startMin),
                'end'             => nearby_fmt_time($clock),
                'estimate_note'   => 'Travel times and distances are estimates (not live routing); entrance fees exclude transport and food.',
            ],
        ]);
        break;

    default:
        json_error('Unknown action.', 400);
}
