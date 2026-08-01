<?php
// ============================================================
// IEXPLORE LAGUNA — Spot Check-In API
// api/spot-checkin.php
// POST only. Records a "confirmed visit" ONLY if the tourist's actual
// device GPS location is within range of the spot's real coordinates
// at the moment of check-in. This is intentionally a separate, stricter
// signal from the automatic page-view counter in spot_views — browsing
// a page proves nothing about physical presence, GPS proximity does
// (within normal consumer GPS accuracy).
// ============================================================
require_once __DIR__ . '/../includes/helpers.php';
set_api_headers();
ensure_spot_checkins_table();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('POST method required.', 405);
}

session_start_safe();
if (!is_logged_in()) {
    json_error('You must be logged in to check in.', 401);
}

// Maximum allowed distance between the tourist's device and the spot's
// real coordinates for a check-in to count as "confirmed." Consumer
// GPS is typically accurate to 5–50m outdoors, worse near buildings/
// trees — 300m gives real headroom for that without being meaningless.
const CHECKIN_MAX_METERS = 300;

$data      = json_decode(file_get_contents('php://input'), true);
$spot_id   = (int)   ($data['spot_id']   ?? 0);
$lat       = (float) ($data['latitude']  ?? 0);
$lng       = (float) ($data['longitude'] ?? 0);
$user_id   = (int) (current_user()['id'] ?? 0);

if (!$spot_id || !$lat || !$lng) {
    json_error('Missing spot or location data.', 400);
}

$spot = db_fetch_one(
    "SELECT id, name, latitude, longitude FROM tourist_spots WHERE id = ? AND is_active = 1",
    [$spot_id]
);
if (!$spot) {
    json_error('Spot not found.', 404);
}

$distance = haversine_meters(
    (float) $spot['latitude'], (float) $spot['longitude'],
    $lat, $lng
);

if ($distance > CHECKIN_MAX_METERS) {
    $km = round($distance / 1000, 1);
    json_error(
        "You're about {$km} km from {$spot['name']} — you need to be there in person to check in.",
        422
    );
}

db_execute(
    "INSERT INTO spot_checkins (spot_id, user_id, latitude, longitude, distance_meters)
     VALUES (?, ?, ?, ?, ?)",
    [$spot_id, $user_id, $lat, $lng, (int) round($distance)]
);

json_ok(
    ['distance_meters' => (int) round($distance)],
    "Checked in at {$spot['name']}! Thanks for confirming your visit."
);
