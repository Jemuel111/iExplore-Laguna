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

// ── Security ──────────────────────────────────────────────────

/**
 * Sanitize a string for HTML output.
 */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
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
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => false,     // set true when using HTTPS
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

// ── CORS (for API endpoints) ──────────────────────────────────

function set_api_headers(): void {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: ' . APP_URL);
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
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
