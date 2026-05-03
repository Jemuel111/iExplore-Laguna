<?php
// ============================================================
// IEXPLORE LAGUNA — Budget API
// api/budget.php
// GET ?action=estimate&origin=1&dest=2&days=2&persons=2&level=midrange
// ============================================================
require_once __DIR__ . '/../includes/helpers.php';
set_api_headers();

$action  = input('action', 'get', 'estimate');

switch ($action) {

    case 'estimate':
        $origin_id  = (int) input('origin',  'get');
        $dest_id    = (int) input('dest',    'get');
        $days       = max(1, (int) input('days',    'get', 1));
        $persons    = max(1, (int) input('persons', 'get', 1));
        $level      = input('level', 'get', 'midrange');

        if (!in_array($level, ['budget','midrange','upscale'])) {
            $level = 'midrange';
        }
        if (!$origin_id || !$dest_id) {
            json_error('Origin and destination required.', 400);
        }

        // Get transport fare (cheapest for level)
        $transport_map = [
            'budget'   => ['jeepney','bus','tricycle'],
            'midrange' => ['bus','fx_uv','jeepney'],
            'upscale'  => ['private_car','fx_uv','bus'],
        ];
        $preferred = $transport_map[$level];
        $placeholders = implode(',', array_fill(0, count($preferred), '?'));

        $route = db_fetch_one(
            "SELECT fare_php, distance_km, duration_min, transport_type
             FROM routes
             WHERE origin_city_id = ? AND dest_city_id = ?
               AND transport_type IN ($placeholders)
             ORDER BY fare_php ASC
             LIMIT 1",
            array_merge([$origin_id, $dest_id], $preferred)
        );

        // Try reverse if not found
        if (!$route) {
            $route = db_fetch_one(
                "SELECT fare_php, distance_km, duration_min, transport_type
                 FROM routes
                 WHERE origin_city_id = ? AND dest_city_id = ?
                   AND transport_type IN ($placeholders)
                 ORDER BY fare_php ASC
                 LIMIT 1",
                array_merge([$dest_id, $origin_id], $preferred)
            );
        }

        $transport_fare = $route ? (float)$route['fare_php'] : 80.00;

        // Get food estimate for destination city
        $food_cat = "food_{$level}";
        $food_row = db_fetch_one(
            "SELECT amount_php FROM budget_estimates
             WHERE city_id = ? AND category = ? LIMIT 1",
            [$dest_id, $food_cat]
        );
        // Fallback to origin city
        if (!$food_row) {
            $food_row = db_fetch_one(
                "SELECT amount_php FROM budget_estimates
                 WHERE city_id = ? AND category = ? LIMIT 1",
                [$origin_id, $food_cat]
            );
        }
        $food_daily = $food_row ? (float)$food_row['amount_php'] : 350.00;

        // Get accommodation for destination
        $acc_cat = "accommodation_{$level}";
        $acc_row = db_fetch_one(
            "SELECT amount_php FROM budget_estimates
             WHERE city_id = ? AND category = ? LIMIT 1",
            [$dest_id, $acc_cat]
        );
        if (!$acc_row) {
            $acc_row = db_fetch_one(
                "SELECT amount_php FROM budget_estimates
                 WHERE city_id = ? AND category = ? LIMIT 1",
                [$origin_id, $acc_cat]
            );
        }
        $acc_nightly = $acc_row ? (float)$acc_row['amount_php'] : 1500.00;

        // Get local transport daily
        $local_row = db_fetch_one(
            "SELECT amount_php FROM budget_estimates
             WHERE city_id = ? AND category = 'local_transport_daily' LIMIT 1",
            [$dest_id]
        );
        $local_daily = $local_row ? (float)$local_row['amount_php'] : 150.00;

        // Misc (10% of subtotal as buffer)
        $subtotal = ($transport_fare * 2) // round trip
                  + ($food_daily * $days)
                  + ($acc_nightly * max(0, $days - 1)) // no hotel if 1-day trip
                  + ($local_daily * $days);
        $misc = round($subtotal * 0.10, 2);

        $grand_total = ($subtotal + $misc) * $persons;

        json_ok([
            'transport'     => $transport_fare * 2,   // round trip per person
            'food'          => $food_daily,            // per person per day
            'accommodation' => $acc_nightly,           // per person per night
            'local'         => $local_daily,           // per person per day
            'misc'          => $misc,                  // per person total
            'grand_total'   => $grand_total,
            'days'          => $days,
            'persons'       => $persons,
            'level'         => $level,
            'transport_type'=> $route['transport_type'] ?? 'bus',
        ]);
        break;

    // ── Full breakdown per city ───────────────────────────
    case 'city':
        $city_id = (int) input('city_id', 'get');
        if (!$city_id) json_error('city_id required.', 400);

        $rows = db_fetch_all(
            "SELECT category, amount_php, notes
             FROM budget_estimates WHERE city_id = ? ORDER BY category",
            [$city_id]
        );
        json_ok($rows);
        break;

    default:
        json_error('Unknown action.', 400);
}