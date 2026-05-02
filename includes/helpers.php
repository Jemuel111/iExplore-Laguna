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
function json_response(bool $success, mixed $data = null, string $message = '', int $code = 200): void {
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
function json_ok(mixed $data = null, string $message = 'OK'): void {
    json_response(true, $data, $message, 200);
}

/**
 * Send an error JSON response.
 */
function json_error(string $message, int $code = 400, mixed $data = null): void {
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
function input(string $key, string $source = 'request', mixed $default = null): mixed {
    $arr = match($source) {
        'get'   => $_GET,
        'post'  => $_POST,
        default => $_REQUEST,
    };
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
