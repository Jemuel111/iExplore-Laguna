<?php
// ============================================================
// iEXPLORE LAGUNA — Package Booking API
// api/packages.php  POST → book a package (hotel + itinerary, one click)
// ============================================================
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Method not allowed']);
    exit;
}
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Login required']);
    exit;
}

$u    = current_user();
if (($u['role'] ?? '') === 'admin') {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Admin accounts cannot book packages']);
    exit;
}
$data = json_decode(file_get_contents('php://input'), true);

$package_id      = (int)   ($data['package_id']     ?? 0);
$start_date      =          $data['start_date']     ?? '';
$guests          = (int)   ($data['guests']         ?? 1);
$guest_name      = trim(    $data['guest_name']     ?? '');
$guest_phone     = trim(    $data['guest_phone']    ?? '');
$payment_method  =          $data['payment_method'] ?? 'cash_on_checkin';

if (!$package_id || !$start_date) {
    echo json_encode(['success'=>false,'message'=>'Missing package or start date']);
    exit;
}
if (!$guest_name) {
    echo json_encode(['success'=>false,'message'=>'Guest name is required']);
    exit;
}

$start = DateTime::createFromFormat('Y-m-d', $start_date);
if (!$start) {
    echo json_encode(['success'=>false,'message'=>'Invalid start date']);
    exit;
}
$today = new DateTime('today');
if ($start < $today) {
    echo json_encode(['success'=>false,'message'=>'Start date cannot be in the past']);
    exit;
}

// ── Load the package ────────────────────────────────────────
$pkg = db_fetch_one(
    "SELECT p.*, r.price_per_night, r.capacity, r.room_count, r.room_type
     FROM packages p
     LEFT JOIN hotel_rooms r ON p.room_id = r.id
     WHERE p.id = ? AND p.is_active = 1",
    [$package_id]
);
if (!$pkg) {
    echo json_encode(['success'=>false,'message'=>'Package not found']);
    exit;
}
if (!$pkg['hotel_id'] || !$pkg['room_id']) {
    echo json_encode(['success'=>false,'message'=>'This package has no hotel configured']);
    exit;
}
if ($guests > (int) $pkg['capacity']) {
    echo json_encode(['success'=>false,'message'=>"This package's room sleeps up to {$pkg['capacity']} guest(s)."]);
    exit;
}

$nights = max(1, (int) $pkg['days'] - 1);
$end = clone $start;
$end->modify("+{$nights} days");
$check_in  = $start->format('Y-m-d');
$check_out = $end->format('Y-m-d');

// ── Check room availability (same overlap rule as api/bookings.php) ──
$overlap_count = db_fetch_one(
    "SELECT COUNT(*) AS n FROM bookings
     WHERE room_id = ?
       AND status NOT IN ('cancelled','no_show')
       AND check_in_date < ? AND check_out_date > ?",
    [$pkg['room_id'], $check_out, $check_in]
)['n'] ?? 0;

if ($overlap_count >= (int) $pkg['room_count']) {
    echo json_encode(['success'=>false,'message'=>'Sorry, the hotel for this package is fully booked for the selected dates. Try a different start date.']);
    exit;
}

// ── Create the hotel booking ────────────────────────────────
$price_per_night = (float) $pkg['price_per_night'];
$hotel_total      = round($price_per_night * $nights, 2);
$booking_number   = 'BKG-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

db_execute(
    "INSERT INTO bookings
       (booking_number, tourist_id, hotel_id, room_id, status,
        check_in_date, check_out_date, nights, guests_count,
        price_per_night, total_amount, special_requests,
        guest_name, guest_phone, guest_email)
     VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)",
    [
        $booking_number, $u['id'], $pkg['hotel_id'], $pkg['room_id'],
        $check_in, $check_out, $nights, $guests,
        $price_per_night, $hotel_total,
        'Part of package: ' . $pkg['title'],
        $guest_name, $guest_phone ?: null,
    ]
);
$booking_id = db_last_id();

$allowed_methods = ['gcash','maya','credit_card','debit_card','bank_transfer','cash_on_pickup','cash_on_checkin'];
$method = in_array($payment_method, $allowed_methods) ? $payment_method : 'cash_on_checkin';
db_execute(
    "INSERT INTO payments (reference_type, reference_id, payer_id, method, amount, status)
     VALUES ('booking', ?, ?, ?, ?, 'pending')",
    [$booking_id, $u['id'], $method, $hotel_total]
);

// ── Build & save the itinerary from the package's spots ─────
$spot_rows = db_fetch_all(
    "SELECT ps.day_number, s.id, s.name, s.entrance_fee, s.city_id, c.name AS city_name
     FROM package_spots ps
     JOIN tourist_spots s ON ps.spot_id = s.id
     JOIN cities c ON s.city_id = c.id
     WHERE ps.package_id = ?
     ORDER BY ps.day_number, ps.sort_order",
    [$package_id]
);

$byDay = [];
foreach ($spot_rows as $sp) {
    $byDay[$sp['day_number']][] = [
        'name' => $sp['name'], 'city' => $sp['city_name'], 'price' => (float) $sp['entrance_fee'],
    ];
}
ksort($byDay);
$itinerary_days = [];
foreach ($byDay as $dayNum => $spots) {
    $itinerary_days[] = ['day' => $dayNum, 'city' => $spots[0]['city'] ?? '', 'spots' => $spots, 'shops' => []];
}

// origin/dest city for the itineraries table — use the package's city if
// single-city, otherwise fall back to the first day's city
$city_id = $pkg['city_id'];
if (!$city_id && !empty($spot_rows)) { $city_id = $spot_rows[0]['city_id']; }

$itinerary_id = null;
if ($city_id) {
    db_execute(
        "INSERT INTO itineraries
           (user_id, title, origin_city_id, dest_city_id, travel_date, num_days,
            num_persons, budget_level, transport_pref, itinerary_json, total_budget)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'midrange', 'any', ?, ?)",
        [
            $u['id'], $pkg['title'], $city_id, $city_id, $check_in, $pkg['days'],
            $guests, json_encode($itinerary_days), $pkg['estimated_price'],
        ]
    );
    $itinerary_id = db_last_id();
}

// ── Track the package booking ────────────────────────────────
db_execute(
    "INSERT INTO package_bookings (package_id, tourist_id, booking_id, itinerary_id, start_date)
     VALUES (?, ?, ?, ?, ?)",
    [$package_id, $u['id'], $booking_id, $itinerary_id, $check_in]
);

// ── Notifications ────────────────────────────────────────────
$hotel = db_fetch_one("SELECT owner_id, name FROM hotels WHERE id = ?", [$pkg['hotel_id']]);
if ($hotel && $hotel['owner_id']) {
    db_execute(
        "INSERT INTO notifications (user_id, type, title, message, link)
         VALUES (?, 'booking_placed', ?, ?, ?)",
        [
            $hotel['owner_id'],
            '📦 New Package Booking: ' . $booking_number,
            "{$guest_name} booked the \"{$pkg['title']}\" package — check-in {$check_in}, check-out {$check_out}.",
            APP_URL . '/pages/hotel-dashboard.php#bookings'
        ]
    );
}
db_execute(
    "INSERT INTO notifications (user_id, type, title, message, link)
     VALUES (?, 'package_booked', ?, ?, ?)",
    [
        $u['id'],
        '🎉 Package Booked! ' . $pkg['title'],
        "Your hotel is reserved ({$check_in} to {$check_out}) and your itinerary is ready.",
        APP_URL . '/pages/my-bookings.php'
    ]
);

echo json_encode([
    'success'        => true,
    'booking_number' => $booking_number,
    'itinerary_id'   => $itinerary_id,
    'check_in'       => $check_in,
    'check_out'      => $check_out,
]);
