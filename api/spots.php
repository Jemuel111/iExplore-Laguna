<?php
// ============================================================
// IEXPLORE LAGUNA — Spot Detail API
// api/spots.php
// GET ?action=detail&id=1          → full spot info
// GET ?action=photos&id=1          → all photos for a spot
// GET ?action=reviews&id=1         → approved reviews
// GET ?action=nearby_hotels&id=1   → nearest hotels by city
// GET ?action=amenities&id=1       → amenities/tags
// POST ?action=review              → submit a review (auth required)
// ============================================================
require_once __DIR__ . '/../includes/helpers.php';
set_api_headers();
ensure_review_photos_table();

$action  = input('action', 'get', 'detail');
$spot_id = (int) input('id', 'get', 0);

switch ($action) {

    // ── Full spot detail ──────────────────────────────────
    case 'detail':
        if (!$spot_id) json_error('Spot ID required.', 400);

        $spot = db_fetch_one(
            "SELECT s.*,
                    c.name AS city_name, c.slug AS city_slug,
                    c.latitude AS city_lat, c.longitude AS city_lng
             FROM tourist_spots s
             JOIN cities c ON s.city_id = c.id
             WHERE s.id = ? AND s.is_active = 1",
            [$spot_id]
        );

        if (!$spot) json_error('Spot not found.', 404);

        // Attach review summary
        $review_stats = db_fetch_one(
            "SELECT COUNT(*) AS total, AVG(rating) AS avg_rating
             FROM spot_reviews WHERE spot_id = ? AND is_approved = 1",
            [$spot_id]
        );
        $spot['review_count'] = (int)   ($review_stats['total']      ?? 0);
        $spot['review_avg']   = (float) ($review_stats['avg_rating'] ?? 0);

        $closure = spot_closure_status($spot);
        $spot['is_closed']      = $closure['closed'];
        $spot['closure_reason'] = $closure['reason'];
        $spot['closed_until']   = $closure['closed_until'];

        json_ok($spot);
        break;

    // ── Photos ────────────────────────────────────────────
    case 'photos':
        if (!$spot_id) json_error('Spot ID required.', 400);

        $photos = db_fetch_all(
            "SELECT id, url, caption, photo_type, sort_order
             FROM spot_photos
             WHERE spot_id = ?
             ORDER BY sort_order ASC, id ASC",
            [$spot_id]
        );

        json_ok($photos);
        break;

    // ── Amenities ─────────────────────────────────────────
    case 'amenities':
        if (!$spot_id) json_error('Spot ID required.', 400);

        $amenities = db_fetch_all(
            "SELECT label, icon FROM spot_amenities WHERE spot_id = ? ORDER BY id ASC",
            [$spot_id]
        );

        json_ok($amenities);
        break;

    // ── Reviews ───────────────────────────────────────────
    case 'reviews':
        if (!$spot_id) json_error('Spot ID required.', 400);

        $page     = max(1, (int) input('page', 'get', 1));
        $per_page = 5;
        $offset   = ($page - 1) * $per_page;

        $reviews = db_fetch_all(
            "SELECT r.id, r.user_id, r.rating, r.title, r.body, r.visited_on, r.created_at,
                    u.name AS user_name
             FROM spot_reviews r
             JOIN users u ON r.user_id = u.id
             WHERE r.spot_id = ? AND r.is_approved = 1
             ORDER BY r.created_at DESC
             LIMIT ? OFFSET ?",
            [$spot_id, $per_page, $offset]
        );

        $total = db_fetch_one(
            "SELECT COUNT(*) AS cnt FROM spot_reviews WHERE spot_id = ? AND is_approved = 1",
            [$spot_id]
        )['cnt'] ?? 0;

        $reviews = attach_review_photos($reviews, 'spot');

        session_start_safe();
        json_ok([
            'reviews'    => $reviews,
            'total'      => (int) $total,
            'page'       => $page,
            'per_page'   => $per_page,
            'has_more'   => ($offset + $per_page) < $total,
            'my_user_id' => (int) (current_user()['id'] ?? 0),
        ]);
        break;

    // ── Nearby hotels (same city) ─────────────────────────
    case 'nearby_hotels':
        if (!$spot_id) json_error('Spot ID required.', 400);

        // Get city_id for this spot
        $spot_city = db_fetch_one(
            "SELECT city_id FROM tourist_spots WHERE id = ?",
            [$spot_id]
        );
        if (!$spot_city) json_error('Spot not found.', 404);

        $hotels = db_fetch_all(
            "SELECT h.id, h.name, h.star_rating, h.address, h.phone,
                    h.price_min, h.price_max, h.latitude, h.longitude,
                    c.name AS city_name
             FROM hotels h
             JOIN cities c ON h.city_id = c.id
             WHERE h.city_id = ? AND h.is_active = 1 AND h.is_verified = 1
             ORDER BY h.star_rating DESC
             LIMIT 4",
            [$spot_city['city_id']]
        );

        json_ok($hotels);
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
        csrf_verify();

        $sid     = (int)   input('spot_id',    'post', 0);
        $rating  = (int)   input('rating',     'post', 0);
        $title   = trim((string) input('title',      'post', ''));
        $body    = trim((string) input('body',       'post', ''));
        $visited = trim((string) input('visited_on', 'post', ''));
        $user_id = (int) (current_user()['id'] ?? 0);

        if (!$sid || $rating < 1 || $rating > 5) {
            json_error('Invalid spot ID or rating (1–5 required).', 400);
        }

        // Check already reviewed
        $existing = db_fetch_one(
            "SELECT id FROM spot_reviews WHERE spot_id = ? AND user_id = ?",
            [$sid, $user_id]
        );
        if ($existing) {
            json_error('You have already reviewed this spot.', 409);
        }

        // Auto-censor bad words instead of rejecting the review outright
        $titleCensor = censor_profanity($title);
        $bodyCensor  = censor_profanity($body);
        $title = $titleCensor['text'];
        $body  = $bodyCensor['text'];
        $wasCensored = $titleCensor['was_censored'] || $bodyCensor['was_censored'];

        db_execute(
            "INSERT INTO spot_reviews (spot_id, user_id, rating, title, body, visited_on)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$sid, $user_id, $rating, $title ?: null, $body ?: null,
             ($visited ?: null)]
        );
        $newReviewId = (int) db_last_id();

        // Update tourist_spots average rating
        db_execute(
            "UPDATE tourist_spots
             SET rating = (SELECT AVG(rating) FROM spot_reviews WHERE spot_id = ? AND is_approved = 1)
             WHERE id = ?",
            [$sid, $sid]
        );

        $photoErrors = save_review_photos('spot', $newReviewId);

        json_ok(
            ['was_censored' => $wasCensored, 'photo_errors' => $photoErrors],
            $wasCensored
                ? 'Review submitted! Some words were censored for inappropriate language.'
                : 'Review submitted successfully!'
        );
        break;

    // ── Update your own review (POST, auth required) ──────
    case 'update_review':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_error('POST method required.', 405);
        }

        session_start_safe();
        if (!is_logged_in()) {
            json_error('You must be logged in to edit a review.', 401);
        }
        csrf_verify();

        $reviewId = (int)   input('review_id',   'post', 0);
        $rating   = (int)   input('rating',      'post', 0);
        $title    = trim((string) input('title',      'post', ''));
        $body     = trim((string) input('body',       'post', ''));
        $visited  = trim((string) input('visited_on', 'post', ''));
        $user_id  = (int) (current_user()['id'] ?? 0);

        if (!$reviewId || $rating < 1 || $rating > 5) {
            json_error('Invalid review ID or rating (1–5 required).', 400);
        }

        $existing = db_fetch_one("SELECT * FROM spot_reviews WHERE id = ?", [$reviewId]);
        if (!$existing) json_error('Review not found.', 404);
        if ((int)$existing['user_id'] !== $user_id) {
            json_error('You can only edit your own review.', 403);
        }

        $titleCensor = censor_profanity($title);
        $bodyCensor  = censor_profanity($body);
        $title = $titleCensor['text'];
        $body  = $bodyCensor['text'];
        $wasCensored = $titleCensor['was_censored'] || $bodyCensor['was_censored'];

        db_execute(
            "UPDATE spot_reviews SET rating=?, title=?, body=?, visited_on=? WHERE id=? AND user_id=?",
            [$rating, $title ?: null, $body ?: null, ($visited ?: null), $reviewId, $user_id]
        );

        db_execute(
            "UPDATE tourist_spots
             SET rating = (SELECT AVG(rating) FROM spot_reviews WHERE spot_id = ? AND is_approved = 1)
             WHERE id = ?",
            [$existing['spot_id'], $existing['spot_id']]
        );

        $photoErrors = save_review_photos('spot', $reviewId);

        json_ok(
            ['was_censored' => $wasCensored, 'photo_errors' => $photoErrors],
            $wasCensored
                ? 'Review updated! Some words were censored for inappropriate language.'
                : 'Review updated successfully!'
        );
        break;

    // ── Delete your own review (POST, auth required) ──────
    case 'delete_review':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_error('POST method required.', 405);
        }

        session_start_safe();
        if (!is_logged_in()) {
            json_error('You must be logged in to delete a review.', 401);
        }
        csrf_verify_header();

        $data     = json_decode(file_get_contents('php://input'), true);
        $reviewId = (int) ($data['review_id'] ?? 0);
        $user_id  = (int) (current_user()['id'] ?? 0);

        $existing = db_fetch_one("SELECT * FROM spot_reviews WHERE id = ?", [$reviewId]);
        if (!$existing) json_error('Review not found.', 404);
        if ((int)$existing['user_id'] !== $user_id) {
            json_error('You can only delete your own review.', 403);
        }

        db_execute("DELETE FROM spot_reviews WHERE id=? AND user_id=?", [$reviewId, $user_id]);
        db_execute("DELETE FROM review_photos WHERE review_type='spot' AND review_id=?", [$reviewId]);

        db_execute(
            "UPDATE tourist_spots
             SET rating = COALESCE((SELECT AVG(rating) FROM spot_reviews WHERE spot_id = ? AND is_approved = 1), 4.00)
             WHERE id = ?",
            [$existing['spot_id'], $existing['spot_id']]
        );

        json_ok(null, 'Review deleted.');
        break;

    default:
        json_error('Unknown action.', 400);
}
