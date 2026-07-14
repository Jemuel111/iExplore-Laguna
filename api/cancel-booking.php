<?php
// ============================================================
// iEXPLORE LAGUNA — Cancel Booking
// api/cancel-booking.php  POST → tourist cancels their own booking
// Plain form POST + redirect (matches pages/my-bookings.php form)
// ============================================================
require_once __DIR__ . '/../includes/helpers.php';

if (!is_logged_in()) { header('Location: ' . APP_URL . '/pages/login.php'); exit; }
$u = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/pages/my-bookings.php'); exit;
}

$booking_id = (int) input('booking_id', 'post', 0);

$booking = db_fetch_one(
    "SELECT * FROM bookings WHERE id = ? AND tourist_id = ?",
    [$booking_id, $u['id']]
);

if ($booking && in_array($booking['status'], ['pending', 'confirmed'])) {
    db_execute(
        "UPDATE bookings SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?",
        [$booking_id]
    );

    // Notify hotel owner
    $hotel = db_fetch_one("SELECT * FROM hotels WHERE id = ?", [$booking['hotel_id']]);
    if ($hotel && $hotel['owner_id']) {
        db_execute(
            "INSERT INTO notifications (user_id, type, title, message, link)
             VALUES (?, 'booking_cancelled', ?, ?, ?)",
            [
                $hotel['owner_id'],
                '❌ Booking Cancelled: ' . $booking['booking_number'],
                "{$u['name']} cancelled booking #{$booking['booking_number']}.",
                APP_URL . '/pages/hotel-dashboard.php#bookings'
            ]
        );
    }

    $_SESSION['flash']['success'] = 'Reservation cancelled.';
} else {
    $_SESSION['flash']['danger'] = 'This reservation can no longer be cancelled.';
}

header('Location: ' . APP_URL . '/pages/my-bookings.php'); exit;
