<?php

// ── Database ─────────────────────────────────────────────────
define('DB_HOST',   'localhost');
define('DB_NAME',   'iexplore_laguna');
define('DB_USER',   'root');        // Change in production
define('DB_PASS',   '');            // Change in production
define('DB_CHARSET','utf8mb4');

// ── App ───────────────────────────────────────────────────────
define('APP_NAME',  'IExplore Laguna');
define('APP_URL',   'http://localhost/iexplore-laguna');
define('APP_VERSION','1.0.0');

// ── Session ───────────────────────────────────────────────────
define('SESSION_NAME', 'iexplore_session');
define('SESSION_LIFETIME', 7200); // 2 hours

// ── Security ──────────────────────────────────────────────────
define('BCRYPT_COST', 12);

// ── External APIs ────────────────────────────────────────────
// OpenRouteService — used for live road-following route polylines
// on the trip planner map. Get a free API key at:
// https://openrouteservice.org/dev/#/signup  (free tier: 2,000
// requests/day, plenty for a capstone demo). Paste it below.
define('ORS_API_KEY', '');
date_default_timezone_set('Asia/Manila');

// ── Error display (set false in production) ───────────────────
define('DEBUG_MODE', false);

if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    // Never show raw errors/stack traces to visitors — log them instead
    // and show a friendly page. (Turning DEBUG_MODE off alone would just
    // leave a blank white screen on a fatal error, which looks broken
    // and isn't much better than leaking the trace.)
    ini_set('display_errors', 0);
    error_reporting(E_ALL); // still capture everything into the log

    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
    ini_set('log_errors', 1);
    ini_set('error_log', $logDir . '/php-error.log');

    set_exception_handler(function (Throwable $e) {
        error_log('[Uncaught] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        if (!headers_sent()) http_response_code(500);
        require __DIR__ . '/error-page.php';
        exit;
    });

    register_shutdown_function(function () {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            if (!headers_sent()) http_response_code(500);
            require __DIR__ . '/error-page.php';
        }
    });
}
