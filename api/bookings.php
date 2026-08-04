<?php
// ============================================================
// iEXPLORE LAGUNA — Bookings API
// api/bookings.php  POST → reserve a hotel room
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
    echo json_encode(['success'=>false,'message'=>'Admin accounts cannot make bookings']);
    exit;
}
csrf_verify_header();
$data = json_decode(file_get_contents('php://input'), true);

$hotel_id         = (int)   ($data['hotel_id']         ?? 0);
$room_id          = (int)   ($data['room_id']          ?? 0);
$check_in         =          $data['check_in']         ?? '';
$check_out        =          $data['check_out']        ?? '';
$guests           = (int)   ($data['guests']           ?? 1);
$guest_name       = trim(    $data['guest_name']       ?? '');
$guest_phone      = trim(    $data['guest_phone']      ?? '');
$guest_email      = trim(    $data['guest_email']      ?? '');
$special_requests =          $data['special_requests'] ?? '';
$payment_method   =          $data['payment_method']   ?? 'cash_on_checkin';

// ── Validate basics ─────────────────────────────────────────
if (!$hotel_id || !$room_id) {
    echo json_encode(['success'=>false,'message'=>'Missing hotel or room selection']);
    exit;
}
if (!$guest_name) {
    echo json_encode(['success'=>false,'message'=>'Guest name is required']);
    exit;
}

$ci = DateTime::createFromFormat('Y-m-d', $check_in);
$co = DateTime::createFromFormat('Y-m-d', $check_out);
if (!$ci || !$co) {
    echo json_encode(['success'=>false,'message'=>'Invalid check-in/check-out dates']);
    exit;
}
$today = new DateTime('today');
if ($ci < $today) {
    echo json_encode(['success'=>false,'message'=>'Check-in date cannot be in the past']);
    exit;
}
$nights = (int) $ci->diff($co)->days;
if ($nights < 1) {
    echo json_encode(['success'=>false,'message'=>'Check-out must be after check-in']);
    exit;
}
if ($guests < 1) $guests = 1;

// ── Validate hotel & room ───────────────────────────────────
$hotel = db_fetch_one("SELECT * FROM hotels WHERE id = ? AND is_active = 1 AND is_verified = 1", [$hotel_id]);
if (!$hotel) {
    echo json_encode(['success'=>false,'message'=>'Hotel not found']);
    exit;
}

$room = db_fetch_one(
    "SELECT * FROM hotel_rooms WHERE id = ? AND hotel_id = ? AND is_available = 1",
    [$room_id, $hotel_id]
);
if (!$room) {
    echo json_encode(['success'=>false,'message'=>'Room type not found']);
    exit;
}
if ($guests > $room['capacity']) {
    echo json_encode(['success'=>false,'message'=>"This room type sleeps up to {$room['capacity']} guest(s)."]);
    exit;
}

// ── Check availability: overlapping bookings for this room type ──
$overlap_count = db_fetch_one(
    "SELECT COUNT(*) AS n FROM bookings
     WHERE room_id = ?
       AND status NOT IN ('cancelled','no_show')
       AND check_in_date < ? AND check_out_date > ?",
    [$room_id, $check_out, $check_in]
)['n'] ?? 0;

if ($overlap_count >= $room['room_count']) {
    echo json_encode(['success'=>false,'message'=>'Sorry, this room type is fully booked for the selected dates.']);
    exit;
}

// ── Compute total ────────────────────────────────────────────
$price_per_night = (float) $room['price_per_night'];
$total_amount    = round($price_per_night * $nights, 2);

// Generate booking number
$booking_number = 'BKG-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

db_execute(
    "INSERT INTO bookings
       (booking_number, tourist_id, hotel_id, room_id, status,
        check_in_date, check_out_date, nights, guests_count,
        price_per_night, total_amount, special_requests,
        guest_name, guest_phone, guest_email)
     VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
    [
        $booking_number, $u['id'], $hotel_id, $room_id,
        $check_in, $check_out, $nights, $guests,
        $price_per_night, $total_amount, $special_requests ?: null,
        $guest_name, $guest_phone ?: null, $guest_email ?: null,
    ]
);

$booking_id = db_last_id();

// Record payment record (pending)
$allowed_methods = ['gcash','maya','credit_card','debit_card','bank_transfer','cash_on_pickup','cash_on_checkin'];
$method = in_array($payment_method, $allowed_methods) ? $payment_method : 'cash_on_checkin';

db_execute(
    "INSERT INTO payments (reference_type, reference_id, payer_id, method, amount, status)
     VALUES ('booking', ?, ?, ?, ?, 'pending')",
    [$booking_id, $u['id'], $method, $total_amount]
);

// Notify hotel owner
if ($hotel['owner_id']) {
    db_execute(
        "INSERT INTO notifications (user_id, type, title, message, link)
         VALUES (?, 'booking_placed', ?, ?, ?)",
        [
            $hotel['owner_id'],
            'New Booking: ' . $booking_number,
            "You have a new booking from {$guest_name} — ₱" . number_format($total_amount, 2),
            APP_URL . '/pages/hotel-dashboard.php#bookings'
        ]
    );
}

// Notify tourist
db_execute(
    "INSERT INTO notifications (user_id, type, title, message, link)
     VALUES (?, 'booking_placed', ?, ?, ?)",
    [
        $u['id'],
        'Reservation Placed! ' . $booking_number,
        "Your reservation at {$hotel['name']} has been placed. We'll notify you once it's confirmed.",
        APP_URL . '/pages/my-bookings.php'
    ]
);

echo json_encode([
    'success'        => true,
    'booking_number' => $booking_number,
    'total'          => $total_amount,
    'nights'         => $nights,
]);
