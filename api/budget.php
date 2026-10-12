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

        // Get transport fare.
        // If the planner passed an explicit transport mode (the option the
        // traveler actually clicked in Transport Options), price THAT mode.
        // Otherwise fall back to the modes implied by the budget level.
        $transport_map = [
            'budget'   => ['jeepney','bus','tricycle'],
            'midrange' => ['bus','fx_uv','jeepney'],
            'upscale'  => ['private_car','fx_uv','bus'],
        ];
        $valid_types = ['jeepney','bus','tricycle','private_car','fx_uv'];
        $chosen_type = input('transport', 'get', '');

        // Try the explicitly chosen mode first, then the level's modes.
        $attempts = [];
        if ($chosen_type && in_array($chosen_type, $valid_types, true)) {
            $attempts[] = [$chosen_type];
        }
        $attempts[] = $transport_map[$level];

        $route = null;
        foreach ($attempts as $preferred) {
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

            if ($route) break;
        }

        // ── Tunable assumptions ───────────────────────────────
        $PERSONS_PER_ROOM    = 2;     // hotel rooms are priced per room, not per person
        $PERSONS_PER_CAR     = 4;     // seats in a private car
        $CAR_COST_PER_KM     = 8.00;  // fuel + toll + parking, PHP per km, one way
        $CAR_MIN_ONE_WAY     = 100.00;
        $estimated           = [];    // which figures are fallback guesses

        // Level-aware fallbacks, used only when a city has no budget_estimates row
        $fallback_food  = ['budget' => 150,  'midrange' => 300,  'upscale' => 750];
        $fallback_acc   = ['budget' => 600,  'midrange' => 1800, 'upscale' => 5000];
        $fallback_local = ['budget' => 80,   'midrange' => 120,  'upscale' => 250];

        // ── Transport ─────────────────────────────────────────
        $rooms    = (int) ceil($persons / $PERSONS_PER_ROOM);
        $vehicles = 1;
        $route_type = $route['transport_type'] ?? 'bus';

        if (!$route) {
            $transport_pp = 80.00 * 2;           // per person, round trip
            $estimated[]  = 'transport';
        } elseif ($route_type === 'private_car') {
            // A private car is priced per vehicle, not per passenger.
            $vehicles = (int) ceil($persons / $PERSONS_PER_CAR);
            $fare     = (float) $route['fare_php'];
            if ($fare <= 0) {
                $km   = (float) ($route['distance_km'] ?? 0);
                $fare = $km > 0 ? max($CAR_MIN_ONE_WAY, $km * $CAR_COST_PER_KM) : 200.00;
            }
            $transport_pp = ($fare * 2 * $vehicles) / $persons;   // round trip, shared
        } else {
            $transport_pp = (float) $route['fare_php'] * 2;       // per person, round trip
        }

        // ── Food ──────────────────────────────────────────────
        $food_cat = "food_{$level}";
        $food_row = db_fetch_one(
            "SELECT amount_php FROM budget_estimates WHERE city_id = ? AND category = ? LIMIT 1",
            [$dest_id, $food_cat]
        );
        if (!$food_row) {
            $food_row = db_fetch_one(
                "SELECT amount_php FROM budget_estimates WHERE city_id = ? AND category = ? LIMIT 1",
                [$origin_id, $food_cat]
            );
        }
        if (!$food_row) $estimated[] = 'food';
        $food_daily = $food_row ? (float)$food_row['amount_php'] : (float)$fallback_food[$level];

        // ── Accommodation (room price per night) ──────────────
        $acc_cat = "accommodation_{$level}";
        $acc_row = db_fetch_one(
            "SELECT amount_php FROM budget_estimates WHERE city_id = ? AND category = ? LIMIT 1",
            [$dest_id, $acc_cat]
        );
        if (!$acc_row) {
            $acc_row = db_fetch_one(
                "SELECT amount_php FROM budget_estimates WHERE city_id = ? AND category = ? LIMIT 1",
                [$origin_id, $acc_cat]
            );
        }
        if (!$acc_row) $estimated[] = 'accommodation';
        $room_rate = $acc_row ? (float)$acc_row['amount_php'] : (float)$fallback_acc[$level];
        // Per-person share of the rooms, so callers that multiply by persons stay correct.
        $acc_nightly_pp = ($room_rate * $rooms) / $persons;

        // ── Local transport ───────────────────────────────────
        $local_row = db_fetch_one(
            "SELECT amount_php FROM budget_estimates
             WHERE city_id = ? AND category = 'local_transport_daily' LIMIT 1",
            [$dest_id]
        );
        if (!$local_row) $estimated[] = 'local';
        $local_daily = $local_row ? (float)$local_row['amount_php'] : (float)$fallback_local[$level];

        // ── Miscellaneous: 10% buffer on the per-person subtotal ──
        $nights   = max(0, $days - 1);   // no hotel for a 1-day trip
        $subtotal = $transport_pp
                  + ($food_daily * $days)
                  + ($acc_nightly_pp * $nights)
                  + ($local_daily * $days);
        $misc = round($subtotal * 0.10, 2);

        $grand_total = ($subtotal + $misc) * $persons;

        json_ok([
            'transport'      => round($transport_pp, 2),     // per person, round trip
            'food'           => $food_daily,                  // per person per day
            'accommodation'  => round($acc_nightly_pp, 2),    // per person per night (room cost shared)
            'local'          => $local_daily,                 // per person per day
            'misc'           => $misc,                        // per person total
            'grand_total'    => round($grand_total, 2),
            'days'           => $days,
            'persons'        => $persons,
            'level'          => $level,
            'transport_type' => $route_type,
            'rooms'          => $rooms,
            'room_rate'      => $room_rate,
            'vehicles'       => $vehicles,
            'estimated'      => $estimated,                   // e.g. ['food','accommodation']
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