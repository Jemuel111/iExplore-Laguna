<?php
// ============================================================
// IEXPLORE LAGUNA — Hotel Reviews API
// api/hotel-reviews.php
// GET  ?action=reviews&id=1   → approved reviews (paginated)
// POST ?action=review         → submit a review (auth required)
// ============================================================
require_once __DIR__ . '/../includes/helpers.php';
set_api_headers();
ensure_hotel_reviews_table();

$action   = input('action', 'get', 'reviews');
$hotel_id = (int) input('id', 'get', 0);

switch ($action) {

    // ── Reviews (paginated) ────────────────────────────────
    case 'reviews':
        if (!$hotel_id) json_error('Hotel ID required.', 400);

        $page     = max(1, (int) input('page', 'get', 1));
        $per_page = 5;
        $offset   = ($page - 1) * $per_page;

        $reviews = db_fetch_all(
            "SELECT r.id, r.rating, r.title, r.body, r.stayed_on, r.created_at,
                    u.name AS user_name
             FROM hotel_reviews r
             JOIN users u ON r.user_id = u.id
             WHERE r.hotel_id = ? AND r.is_approved = 1
             ORDER BY r.created_at DESC
             LIMIT ? OFFSET ?",
            [$hotel_id, $per_page, $offset]
        );

        $total = db_fetch_one(
            "SELECT COUNT(*) AS cnt FROM hotel_reviews WHERE hotel_id = ? AND is_approved = 1",
            [$hotel_id]
        )['cnt'] ?? 0;

        $stats = db_fetch_one(
            "SELECT COUNT(*) AS total, AVG(rating) AS avg_rating
             FROM hotel_reviews WHERE hotel_id = ? AND is_approved = 1",
            [$hotel_id]
        );

        json_ok([
            'reviews'    => $reviews,
            'total'      => (int) $total,
            'page'       => $page,
            'per_page'   => $per_page,
            'has_more'   => ($offset + $per_page) < $total,
            'avg_rating' => (float) ($stats['avg_rating'] ?? 0),
        ]);
        break;

    // ── Submit a review (POST, auth required) ─────────────
    case 'review':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_error('POST method required.', 405);
        }

        session_start_safe();
        if (!is_logged_in()) {
            json_error('You must be logged in to submit a review.', 401);
        }

        $data     = json_decode(file_get_contents('php://input'), true);
        $hid      = (int)  ($data['hotel_id']  ?? 0);
        $rating   = (int)  ($data['rating']    ?? 0);
        $title    = trim(  ($data['title']     ?? ''));
        $body     = trim(  ($data['body']      ?? ''));
        $stayed   = trim(  ($data['stayed_on'] ?? ''));
        $user_id  = (int) (current_user()['id'] ?? 0);

        if (!$hid || $rating < 1 || $rating > 5) {
            json_error('Invalid hotel ID or rating (1–5 required).', 400);
        }

        $hotelExists = db_fetch_one("SELECT id FROM hotels WHERE id = ?", [$hid]);
        if (!$hotelExists) json_error('Hotel not found.', 404);

        // One review per hotel per user
        $existing = db_fetch_one(
            "SELECT id FROM hotel_reviews WHERE hotel_id = ? AND user_id = ?",
            [$hid, $user_id]
        );
        if ($existing) {
            json_error('You have already reviewed this hotel.', 409);
        }

        // Auto-censor bad words instead of rejecting the review outright
        $titleCensor = censor_profanity($title);
        $bodyCensor  = censor_profanity($body);
        $title = $titleCensor['text'];
        $body  = $bodyCensor['text'];
        $wasCensored = $titleCensor['was_censored'] || $bodyCensor['was_censored'];

        db_execute(
            "INSERT INTO hotel_reviews (hotel_id, user_id, rating, title, body, stayed_on)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$hid, $user_id, $rating, $title ?: null, $body ?: null, ($stayed ?: null)]
        );

        json_ok(
            ['was_censored' => $wasCensored],
            $wasCensored
                ? 'Review submitted! Some words were censored for inappropriate language.'
                : 'Review submitted successfully!'
        );
        break;

    default:
        json_error('Unknown action.', 400);
}
