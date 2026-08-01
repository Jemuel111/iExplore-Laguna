<?php
// ============================================================
// LAKBAY LAGUNA — Global Helper Functions
// includes/helpers.php
// ============================================================

require_once __DIR__ . '/db.php';

// ── Output ────────────────────────────────────────────────────

/**
 * Send a JSON response and exit.
 */
function json_response(bool $success, $data = null, string $message = '', int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Send a success JSON response.
 */
function json_ok($data = null, string $message = 'OK'): void {
    json_response(true, $data, $message, 200);
}

/**
 * Send an error JSON response.
 */
function json_error(string $message, int $code = 400, $data = null): void {
    json_response(false, $data, $message, $code);
}

/**
 * Single source of truth for tourist spot category metadata (icon,
 * label, badge colors). Every page previously re-declared its own copy
 * of this map — some with emoji, some with slightly different labels —
 * which is exactly how inconsistencies creep in. Use this everywhere
 * instead of a local $emojis/$catEmojis array.
 */
function spot_categories(): array {
    return [
        'nature'     => ['icon' => 'bi-tree',           'label' => 'Nature',      'bg' => '#fbdede', 'fg' => '#6b0f14'],
        'heritage'   => ['icon' => 'bi-bank',            'label' => 'Heritage',    'bg' => '#fef3c7', 'fg' => '#92400e'],
        'waterfall'  => ['icon' => 'bi-droplet',         'label' => 'Waterfall',   'bg' => '#dbeafe', 'fg' => '#1e40af'],
        'hotspring'  => ['icon' => 'bi-fire',            'label' => 'Hot Spring',  'bg' => '#ffe4e6', 'fg' => '#9f1239'],
        'museum'     => ['icon' => 'bi-columns-gap',     'label' => 'Museum',      'bg' => '#f3e8ff', 'fg' => '#6b21a8'],
        'religious'  => ['icon' => 'bi-building',        'label' => 'Religious',   'bg' => '#fff7ed', 'fg' => '#9a3412'],
        'beach_lake' => ['icon' => 'bi-water',            'label' => 'Lake/Beach',  'bg' => '#e0f2fe', 'fg' => '#075985'],
        'adventure'  => ['icon' => 'bi-compass',         'label' => 'Adventure',   'bg' => '#fef9c3', 'fg' => '#713f12'],
        'food'       => ['icon' => 'bi-cup-hot',         'label' => 'Food',        'bg' => '#fce7f3', 'fg' => '#9d174d'],
    ];
}

/** Icon class for a single category, with a safe fallback. */
function spot_category_icon(?string $category): string {
    return spot_categories()[$category]['icon'] ?? 'bi-geo-alt';
}

/** Display label for a single category, with a safe fallback. */
function spot_category_label(?string $category): string {
    return spot_categories()[$category]['label'] ?? ucfirst((string)$category);
}

/** Same idea as spot_categories() but for shop/stall types. */
function shop_categories(): array {
    return [
        'milktea'     => ['icon' => 'bi-cup-straw',  'label' => 'Milk Tea'],
        'cafe'        => ['icon' => 'bi-cup-hot',    'label' => 'Café'],
        'restaurant'  => ['icon' => 'bi-egg-fried',  'label' => 'Restaurant'],
        'bakery'      => ['icon' => 'bi-basket2',    'label' => 'Bakery'],
        'street_food' => ['icon' => 'bi-fire',       'label' => 'Street Food'],
        'souvenir'    => ['icon' => 'bi-gift',       'label' => 'Souvenir'],
        'pasalubong'  => ['icon' => 'bi-box-seam',   'label' => 'Pasalubong'],
        'grocery'     => ['icon' => 'bi-cart3',      'label' => 'Grocery'],
        'other'       => ['icon' => 'bi-shop',       'label' => 'Other'],
    ];
}

function shop_category_icon(?string $category): string {
    return shop_categories()[$category]['icon'] ?? 'bi-shop';
}

function shop_category_label(?string $category): string {
    return shop_categories()[$category]['label'] ?? ucfirst((string)$category);
}

/**
 * Add the tourist demographic columns to `users` if they don't already
 * exist yet. Nullable — existing accounts (and hotel/shop/admin roles)
 * won't have this data, only new tourist signups going forward.
 */
function ensure_user_demographic_columns(): void {
    $existing = array_column(
        db_fetch_all("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'"),
        'COLUMN_NAME'
    );
    $wanted = [
        'birthdate'    => "DATE NULL",
        'gender'       => "VARCHAR(20) NULL",
        'tourist_type' => "VARCHAR(20) NULL COMMENT 'local or international'",
        'nationality'  => "VARCHAR(100) NULL",
        'province'     => "VARCHAR(100) NULL",
        'city'         => "VARCHAR(100) NULL",
    ];
    foreach ($wanted as $col => $def) {
        if (!in_array($col, $existing, true)) {
            db()->exec("ALTER TABLE users ADD COLUMN {$col} {$def}");
        }
    }
}

/**
 * "Interest views" — a lightweight, automatic counter of how many times
 * a spot's page was opened. NOT the same as a confirmed visit (browsing
 * behavior alone can't prove someone physically went there) — kept as
 * a clearly separate, honestly-labeled metric from spot_checkins below.
 */
function ensure_spot_views_table(): void {
    db()->exec(
        "CREATE TABLE IF NOT EXISTS spot_views (
            id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            spot_id INT(10) UNSIGNED NOT NULL,
            user_id INT(10) UNSIGNED NULL,
            viewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_spot_views_spot (spot_id),
            INDEX idx_spot_views_date (viewed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

/**
 * "Confirmed visits" — GPS-verified check-ins. Only recorded when the
 * tourist's actual device location is within range of the spot's real
 * coordinates at the moment they check in. This is the metric that
 * should be trusted as "people who really went there."
 */
function ensure_spot_checkins_table(): void {
    db()->exec(
        "CREATE TABLE IF NOT EXISTS spot_checkins (
            id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            spot_id INT(10) UNSIGNED NOT NULL,
            user_id INT(10) UNSIGNED NOT NULL,
            latitude DECIMAL(10,7) NOT NULL,
            longitude DECIMAL(10,7) NOT NULL,
            distance_meters INT UNSIGNED NOT NULL,
            checked_in_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_spot_checkins_spot (spot_id),
            INDEX idx_spot_checkins_user (user_id),
            INDEX idx_spot_checkins_date (checked_in_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

/** Haversine distance between two coordinates, in meters. */
function haversine_meters(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $earthRadius = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2)**2;
    return $earthRadius * 2 * atan2(sqrt($a), sqrt(1-$a));
}

/**
 * Shop order status metadata — same idea as booking_status_meta() but
 * for pickup orders (different status set: preparing/ready/picked_up
 * instead of checked_in/checked_out).
 */
function order_status_meta(string $status): array {
    $map = [
        'pending'   => ['bg'=>'#fff3cd','fg'=>'#856404','icon'=>'bi-hourglass-split',  'label'=>'Pending'],
        'confirmed' => ['bg'=>'#d1ecf1','fg'=>'#0c5460','icon'=>'bi-check-circle-fill','label'=>'Confirmed'],
        'preparing' => ['bg'=>'#d4edda','fg'=>'#155724','icon'=>'bi-egg-fried',         'label'=>'Preparing'],
        'ready'     => ['bg'=>'#d4edda','fg'=>'#155724','icon'=>'bi-bag-check-fill',    'label'=>'Ready for Pickup!'],
        'picked_up' => ['bg'=>'#e2e3e5','fg'=>'#383d41','icon'=>'bi-check2-all',        'label'=>'Picked Up'],
        'cancelled' => ['bg'=>'#f8d7da','fg'=>'#721c24','icon'=>'bi-x-circle-fill',     'label'=>'Cancelled'],
    ];
    return $map[$status] ?? ['bg'=>'#f1f5f9','fg'=>'#334155','icon'=>'bi-question-circle','label'=>'Unknown'];
}
function booking_status_meta(string $status): array {
    $map = [
        'pending'     => ['bg'=>'#fff3cd','fg'=>'#856404','icon'=>'bi-hourglass-split', 'label'=>'Pending'],
        'confirmed'   => ['bg'=>'#d1ecf1','fg'=>'#0c5460','icon'=>'bi-check-circle-fill','label'=>'Confirmed'],
        'checked_in'  => ['bg'=>'#d4edda','fg'=>'#155724','icon'=>'bi-door-open-fill',   'label'=>'Checked In'],
        'checked_out' => ['bg'=>'#e2e3e5','fg'=>'#383d41','icon'=>'bi-check2-all',       'label'=>'Checked Out'],
        'cancelled'   => ['bg'=>'#f8d7da','fg'=>'#721c24','icon'=>'bi-x-circle-fill',    'label'=>'Cancelled'],
        'no_show'     => ['bg'=>'#f8d7da','fg'=>'#721c24','icon'=>'bi-slash-circle-fill','label'=>'No Show'],
    ];
    return $map[$status] ?? ['bg'=>'#f1f5f9','fg'=>'#334155','icon'=>'bi-question-circle','label'=>'Unknown'];
}

/**
 * Sanitize a string for HTML output.
 */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Return the current CSRF token, generating one for this session if it
 * doesn't have one yet. One token per session, reused across the whole
 * session lifetime (regenerated on login — see login_user()).
 */
function csrf_token(): string {
    session_start_safe();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Hidden <input> for a <form method="POST">. Drop this inside every
 * form that changes data so csrf_verify() can check it on submit.
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Verify the CSRF token on an incoming POST request. Call this as the
 * first thing on every POST handler (page or API). Uses a timing-safe
 * comparison and stops the request cold on mismatch — a missing/wrong
 * token means the request didn't originate from our own form.
 */
function csrf_verify(): void {
    session_start_safe();
    $submitted = $_POST['csrf_token'] ?? '';
    $expected  = $_SESSION['csrf_token'] ?? '';

    if ($expected === '' || $submitted === '' || !hash_equals($expected, $submitted)) {
        http_response_code(403);
        if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
            || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Your session expired or this request looks invalid. Please refresh the page and try again.']);
        } else {
            echo 'Your session expired or this request looks invalid. Please go back, refresh the page, and try again.';
        }
        exit;
    }
}

/**
 * Simple login rate-limiter, tracked per email+IP in the session (no
 * extra table needed). After MAX_ATTEMPTS failed logins, blocks further
 * tries for LOCKOUT_SECONDS. Call login_attempt_blocked() before
 * checking the password, and record_failed_login() / reset_login_attempts()
 * after checking it.
 */
function login_rate_limit_key(string $email): string {
    // Keyed by email only (not email+IP). On XAMPP, "localhost" can
    // inconsistently resolve to 127.0.0.1 or ::1 between requests,
    // which silently reset the counter every attempt. Locking by
    // account rather than account+IP is also the more standard
    // behavior anyway — it stops distributed attempts too, not just
    // ones from a single address.
    return 'login_attempts_' . md5(strtolower(trim($email)));
}

const LOGIN_MAX_ATTEMPTS    = 5;
const LOGIN_LOCKOUT_SECONDS = 300; // 5 minutes

function login_attempt_blocked(string $email): ?int {
    session_start_safe();
    $key = login_rate_limit_key($email);
    $data = $_SESSION[$key] ?? null;
    if (!$data) return null;

    if ($data['count'] >= LOGIN_MAX_ATTEMPTS) {
        $elapsed = time() - $data['last'];
        if ($elapsed < LOGIN_LOCKOUT_SECONDS) {
            return LOGIN_LOCKOUT_SECONDS - $elapsed; // seconds remaining
        }
        // Lockout expired — clear it so they can try again
        unset($_SESSION[$key]);
    }
    return null;
}

/**
 * Record a failed attempt and return how many the person has left
 * before they get locked out (0 means this attempt just triggered the
 * lockout). Used to show "N attempts remaining" on the login form.
 */
function record_failed_login(string $email): int {
    session_start_safe();
    $key = login_rate_limit_key($email);
    $data = $_SESSION[$key] ?? ['count' => 0, 'last' => time()];
    $data['count']++;
    $data['last'] = time();
    $_SESSION[$key] = $data;
    return max(0, LOGIN_MAX_ATTEMPTS - $data['count']);
}

function reset_login_attempts(string $email): void {
    session_start_safe();
    unset($_SESSION[login_rate_limit_key($email)]);
}

/**
 * Send a standard set of defensive HTTP security headers. Call once,
 * early, on every page/API response.
 */
function send_security_headers(): void {
    header('X-Content-Type-Options: nosniff');       // stop MIME-sniffing away from a declared content type
    header('X-Frame-Options: SAMEORIGIN');            // block this site from being framed elsewhere (clickjacking)
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(self), camera=(), microphone=()');
}

/**
 * Get a sanitized value from $_GET or $_POST.
 */
function input(string $key, string $source = 'request', $default = null) {
    if ($source === 'get') {
        $arr = $_GET;
    } elseif ($source === 'post') {
        $arr = $_POST;
    } else {
        $arr = $_REQUEST;
    }
    if (!isset($arr[$key])) return $default;
    $val = trim($arr[$key]);
    return $val === '' ? $default : $val;
}

/**
 * Validate that required POST fields are present.
 * Returns array of missing field names, empty array if all present.
 */
function require_fields(array $fields, string $source = 'post'): array {
    $missing = [];
    foreach ($fields as $f) {
        if (input($f, $source) === null) $missing[] = $f;
    }
    return $missing;
}

// ── Auth / Session ────────────────────────────────────────────

function session_start_safe(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => $isHttps,  // cookie only sent over HTTPS once you're on it
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function current_user(): ?array {
    session_start_safe();
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool {
    return current_user() !== null;
}

function login_user(array $user): void {
    session_start_safe();
    session_regenerate_id(true);
    unset($_SESSION['csrf_token']); // force a fresh token for the new session
    $_SESSION['user'] = [
        'id'    => $user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'role'  => $user['role']  ?? 'tourist',
        'phone' => $user['phone'] ?? null,
    ];
}

function logout_user(): void {
    session_start_safe();
    $_SESSION = [];
    session_destroy();
}

// ── Database Helpers ──────────────────────────────────────────

/**
 * Fetch all rows from a prepared statement.
 */
function db_fetch_all(string $sql, array $params = []): array {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Fetch a single row.
 */
function db_fetch_one(string $sql, array $params = []): ?array {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Execute an INSERT/UPDATE/DELETE and return affected rows.
 */
function db_execute(string $sql, array $params = []): int {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/**
 * Return last inserted ID.
 */
function db_last_id(): string {
    return db()->lastInsertId();
}

/**
 * Create the hotel_reviews table on first use. This app ships without a
 * bundled SQL schema file, so table creation happens lazily the same way
 * hotel_photos does. Column types/charset match hotels.id and the existing
 * spot_reviews table exactly to avoid FK type-mismatch errors (MySQL 150).
 */
function ensure_hotel_reviews_table(): void {
    db()->exec(
        "CREATE TABLE IF NOT EXISTS hotel_reviews (
            id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            hotel_id INT(10) UNSIGNED NOT NULL,
            user_id INT(10) UNSIGNED NOT NULL,
            rating TINYINT(4) NOT NULL,
            title VARCHAR(120) DEFAULT NULL,
            body TEXT DEFAULT NULL,
            stayed_on DATE DEFAULT NULL,
            is_approved TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_hotel_reviews_hotel (hotel_id),
            INDEX idx_hotel_reviews_user (user_id),
            CONSTRAINT fk_hotel_reviews_hotel FOREIGN KEY (hotel_id) REFERENCES hotels(id) ON DELETE CASCADE,
            CONSTRAINT fk_hotel_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

/**
 * Create the hotel_amenities table on first use — mirrors spot_amenities
 * (id, hotel_id, label, icon) so hotels can show a "What's Included"
 * grid just like tourist spots do.
 */
/**
 * Create the hotel_photos table on first use — same definition already
 * used in admin-hotel-photos.php, pulled out here so any page that
 * reads hotel photos (not just the admin uploader) can safely call it
 * first without duplicating the CREATE TABLE statement.
 */
function ensure_hotel_photos_table(): void {
    db()->exec(
        "CREATE TABLE IF NOT EXISTS hotel_photos (
            id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            hotel_id INT(10) UNSIGNED NOT NULL,
            url VARCHAR(255) NOT NULL,
            caption VARCHAR(200) DEFAULT NULL,
            photo_type ENUM('main','gallery','room','amenity','exterior') DEFAULT 'gallery',
            sort_order TINYINT(4) DEFAULT 0,
            INDEX idx_hotel_photos_hotel (hotel_id),
            CONSTRAINT fk_hotel_photos_hotel FOREIGN KEY (hotel_id) REFERENCES hotels(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function ensure_hotel_amenities_table(): void {
    db()->exec(
        "CREATE TABLE IF NOT EXISTS hotel_amenities (
            id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            hotel_id INT(10) UNSIGNED NOT NULL,
            label VARCHAR(80) NOT NULL,
            icon VARCHAR(50) DEFAULT 'bi-check-circle',
            INDEX idx_hotel_amenities_hotel (hotel_id),
            CONSTRAINT fk_hotel_amenities_hotel FOREIGN KEY (hotel_id) REFERENCES hotels(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

/**
 * Create the review_photos table on first use. Shared between spot
 * reviews and hotel reviews (review_type distinguishes them) since both
 * need the exact same "attach a few photos to my review" behavior —
 * one shared table avoids duplicating this twice.
 */
function ensure_review_photos_table(): void {
    db()->exec(
        "CREATE TABLE IF NOT EXISTS review_photos (
            id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            review_type ENUM('spot','hotel') NOT NULL,
            review_id INT(10) UNSIGNED NOT NULL,
            url VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_review_photos_review (review_type, review_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

/**
 * Batch-fetch photos for a list of reviews and attach them as a
 * 'photos' key on each row (array of URL strings). Used everywhere
 * reviews are displayed — avoids one query per review.
 */
function attach_review_photos(array $reviews, string $reviewType): array {
    if (empty($reviews)) return $reviews;

    $ids = array_column($reviews, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $rows = db_fetch_all(
        "SELECT review_id, url FROM review_photos
         WHERE review_type = ? AND review_id IN ($placeholders)
         ORDER BY id ASC",
        array_merge([$reviewType], $ids)
    );

    $byReview = [];
    foreach ($rows as $row) {
        $byReview[$row['review_id']][] = $row['url'];
    }
    foreach ($reviews as &$rv) {
        $rv['photos'] = $byReview[$rv['id']] ?? [];
    }
    unset($rv);

    return $reviews;
}
function save_review_photos(string $reviewType, int $reviewId, int $maxPhotos = 3): array {
    $errors = [];
    if (empty($_FILES['photos']) || !is_array($_FILES['photos']['name'])) {
        return $errors;
    }

    $count = min(count($_FILES['photos']['name']), $maxPhotos);
    for ($i = 0; $i < $count; $i++) {
        if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;

        $_FILES['__review_photo'] = [
            'name'     => $_FILES['photos']['name'][$i],
            'type'     => $_FILES['photos']['type'][$i],
            'tmp_name' => $_FILES['photos']['tmp_name'][$i],
            'error'    => $_FILES['photos']['error'][$i],
            'size'     => $_FILES['photos']['size'][$i],
        ];
        try {
            $url = handle_image_upload('__review_photo', 'reviews');
            if ($url) {
                db_execute(
                    "INSERT INTO review_photos (review_type, review_id, url) VALUES (?, ?, ?)",
                    [$reviewType, $reviewId, $url]
                );
            }
        } catch (RuntimeException $e) {
            $errors[] = $_FILES['photos']['name'][$i] . ': ' . $e->getMessage();
        }
    }
    return $errors;
}

// ── Formatting ────────────────────────────────────────────────

/**
 * Format PHP peso amount.
 */
function peso(float $amount): string {
    return '₱ ' . number_format($amount, 2);
}

/**
 * Format minutes into human-readable duration.
 */
function format_duration(int $minutes): string {
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    if ($h === 0)  return "{$m} min";
    if ($m === 0)  return "{$h} hr";
    return "{$h} hr {$m} min";
}

/**
 * Generate a URL slug from a string.
 */
function slugify(string $text): string {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', trim($text));
    return $text;
}

/**
 * Check if a value is a valid Philippine mobile number.
 */
function is_valid_phone(string $phone): bool {
    return (bool) preg_match('/^(\+639|09)\d{9}$/', $phone);
}

// ── Spot closures ─────────────────────────────────────────────

/**
 * Determines whether a tourist spot should be treated as closed for a
 * given reference date (defaults to today). A spot marked is_closed=1
 * with a closed_until date that has already passed is treated as open
 * again automatically — no separate admin action needed to "reopen" it.
 *
 * @param array       $spot           Row from tourist_spots (must include
 *                                    is_closed, closed_until, closure_reason,
 *                                    closure_updated_at).
 * @param string|null $referenceDate  'Y-m-d' date to check against
 *                                    (e.g. a tourist's planned travel date).
 *                                    Defaults to today.
 * @return array{closed:bool, reopens_before_reference:bool, reason:?string, closed_until:?string}
 */
function spot_closure_status(array $spot, ?string $referenceDate = null): array {
    $isClosed = !empty($spot['is_closed']);
    $closedUntil = $spot['closed_until'] ?? null;

    if (!$isClosed) {
        return ['closed' => false, 'reopens_before_reference' => true, 'reason' => null, 'closed_until' => null];
    }

    // Auto-expire: if a reopening date was given and it's in the past,
    // treat the spot as open even if the admin never flipped the flag back.
    $today = date('Y-m-d');
    if ($closedUntil && $closedUntil < $today) {
        return ['closed' => false, 'reopens_before_reference' => true, 'reason' => null, 'closed_until' => null];
    }

    $reference = $referenceDate ?: $today;
    // If we know a reopening date and it falls before (or on) the tourist's
    // planned visit, the spot should be back open by the time they arrive.
    $reopensBeforeReference = $closedUntil !== null && $closedUntil < $reference;

    return [
        'closed'                   => true,
        'reopens_before_reference' => $reopensBeforeReference,
        'reason'                   => $spot['closure_reason'] ?? null,
        'closed_until'             => $closedUntil,
    ];
}

/**
 * Parses the comma-delimited spot_ids column (format: ",12,45,7,")
 * back into an array of ints.
 */
function parse_spot_ids(?string $stored): array {
    if (!$stored) return [];
    return array_values(array_filter(array_map('intval', explode(',', trim($stored, ',')))));
}

/**
 * Encodes an array of spot IDs into the comma-delimited storage format
 * used by the itineraries.spot_ids column (easy & index-friendly to
 * search with LIKE '%,12,%').
 */
function encode_spot_ids(array $ids): string {
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (empty($ids)) return '';
    return ',' . implode(',', $ids) . ',';
}

// ── CORS (for API endpoints) ──────────────────────────────────

function set_api_headers(): void {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: ' . APP_URL);
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    send_security_headers();
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);
}

/**
 * Computes a real ₱min–₱max per-day-per-person range for each budget
 * level (budget/midrange/upscale) from actual data in budget_estimates
 * (food + accommodation combined), instead of showing vague labels.
 * Falls back to reasonable defaults if the table has no data yet.
 */
function get_budget_level_ranges(): array {
    $ranges = [
        'budget'   => ['min' => 0,    'max' => 1000],
        'midrange' => ['min' => 1000, 'max' => 3000],
        'upscale'  => ['min' => 3000, 'max' => 8000],
    ];

    $rows = db_fetch_all(
        "SELECT
            SUBSTRING_INDEX(category,'_',-1) AS level,
            MIN(amount_php) AS min_amt,
            MAX(amount_php) AS max_amt
         FROM budget_estimates
         WHERE category LIKE 'food_%' OR category LIKE 'accommodation_%'
         GROUP BY level, SUBSTRING_INDEX(category,'_',1)"
    );

    if (!empty($rows)) {
        $computed = ['budget' => ['min'=>0,'max'=>0], 'midrange' => ['min'=>0,'max'=>0], 'upscale' => ['min'=>0,'max'=>0]];
        foreach ($rows as $r) {
            $lvl = $r['level'];
            if (!isset($computed[$lvl])) continue;
            $computed[$lvl]['min'] += (float) $r['min_amt'];
            $computed[$lvl]['max'] += (float) $r['max_amt'];
        }
        // Only use computed values if they actually resolved to something real
        foreach ($computed as $lvl => $range) {
            if ($range['max'] > 0) $ranges[$lvl] = $range;
        }
    }

    return $ranges;
}

/**
 * Handles an uploaded image file: validates it, saves it to
 * /uploads/{subfolder}/, and returns the public URL to store in the DB.
 *
 * @param string $fieldName  The <input type="file" name="..."> field name
 * @param string $subfolder  'hotels', 'shops', or 'packages'
 * @return string|null       Public URL on success, null if no file was
 *                            uploaded (not an error — just means "keep
 *                            the existing photo"). Calls json_error() /
 *                            dies with a message on validation failure.
 */
function handle_image_upload(string $fieldName, string $subfolder): ?string {
    if (empty($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // no file chosen — not an error, just nothing to do
    }

    $file = $_FILES[$fieldName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed (error code ' . $file['error'] . '). Try a smaller file.');
    }

    $maxBytes = 3 * 1024 * 1024; // 3MB
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('Image is too large — please use a file under 3MB.');
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG, or WEBP images are allowed.');
    }

    $subfolder = preg_replace('/[^a-z]/', '', $subfolder); // sanitize
    $uploadDir = __DIR__ . '/../uploads/' . $subfolder . '/';
    $dirExisted = is_dir($uploadDir);
    if (!$dirExisted) {
        @mkdir($uploadDir, 0755, true);
    }
    if (!is_dir($uploadDir)) {
        throw new RuntimeException(
            "Could not create the uploads/{$subfolder} folder. Check that the 'uploads' folder exists at your project root and that your hosting account can create folders inside it (folder permissions, usually 755)."
        );
    }
    if (!is_writable($uploadDir)) {
        throw new RuntimeException(
            "The uploads/{$subfolder} folder exists but isn't writable by the server. In your hosting file manager, set its permissions to 755 (or 775 if that doesn't work), then try again."
        );
    }

    $filename = uniqid($subfolder . '_', true) . '.' . $allowed[$mime];
    $destPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new RuntimeException('Could not save the uploaded file. Please try again.');
    }

    return APP_URL . '/uploads/' . $subfolder . '/' . $filename;
}

// ── Content moderation ──────────────────────────────────────────

/**
 * Words/phrases we auto-censor in user-submitted review text.
 * Covers common English profanity plus common Filipino/Tagalog swear
 * words, since this app serves Laguna, Philippines. Not exhaustive —
 * meant as a reasonable first line of defense, not a perfect filter.
 */
function profanity_wordlist(): array {
    return [
        // English
        'fuck', 'fucking', 'fucker', 'shit', 'bullshit', 'bitch', 'asshole',
        'bastard', 'dick', 'cunt', 'piss', 'slut', 'whore', 'faggot', 'nigger',
        'nigga', 'retard', 'douche', 'cock', 'pussy', 'motherfucker',
        // Filipino / Tagalog
        'putangina', 'putang ina', 'puta', 'putanginamo', 'gago', 'gaga',
        'tangina', 'tang ina', 'tanginamo', 'ulol', 'bobo', 'tarantado',
        'leche', 'lintik', 'hayop', 'hayop ka', 'kupal', 'peste', 'pakyu',
        'punyeta', 'bwisit', 'yawa', 'inutil', 'buwisit', 'siraulo',
    ];
}

/**
 * Replace any censored words found in $text with asterisks (first and
 * last letter kept so the review stays readable), and report whether
 * anything was actually censored.
 *
 * Returns ['text' => string, 'was_censored' => bool]
 */
function censor_profanity(?string $text): array {
    $text = (string) ($text ?? '');
    if ($text === '') return ['text' => $text, 'was_censored' => false];

    $wasCensored = false;

    foreach (profanity_wordlist() as $word) {
        // Word can contain a space (multi-word phrases like "putang ina") —
        // build a boundary-safe pattern either way.
        $pattern = '/\b(' . preg_quote($word, '/') . ')\b/iu';
        $text = preg_replace_callback($pattern, function ($m) use (&$wasCensored) {
            $wasCensored = true;
            $matched = $m[1];
            $len = mb_strlen($matched);
            if ($len <= 2) return str_repeat('*', $len);
            return mb_substr($matched, 0, 1) . str_repeat('*', $len - 2) . mb_substr($matched, -1);
        }, $text);
    }

    return ['text' => $text, 'was_censored' => $wasCensored];
}
