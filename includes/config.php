<?php

require_once __DIR__ . '/env.php';
load_env(__DIR__ . '/../.env');

// ── Database ─────────────────────────────────────────────────
define('DB_HOST',   env('DB_HOST', 'localhost'));
define('DB_NAME',   env('DB_NAME', 'iexplore_laguna'));
define('DB_USER',   env('DB_USER', 'root'));
define('DB_PASS',   env('DB_PASS', ''));
define('DB_CHARSET','utf8mb4');

// ── App ───────────────────────────────────────────────────────
define('APP_NAME',  'IExplore Laguna');
define('APP_URL', 'https://iexplorelaguna.online');
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
// requests/day, plenty for a capstone demo).
//
// TomTom Traffic API — optional live traffic overlay for the trip planner.
// Get a key from the TomTom Developer Portal.
//
// Both keys now live in a .env file (gitignored, never committed) instead
// of being hardcoded here. See .env.example for the format. Leave
// TOMTOM_API_KEY empty in .env to keep the Traffic button disabled.
define('ORS_API_KEY',    env('ORS_API_KEY', ''));
define('TOMTOM_API_KEY', env('TOMTOM_API_KEY', ''));

// SMTP — used by includes/mailer.php to send "reset your password"
// emails. For Gmail: enable 2-Step Verification on the sending
// account, then create an App Password at
// https://myaccount.google.com/apppasswords and use that (not the
// normal account password) as SMTP_PASS. Any other SMTP provider
// (Mailtrap, SendGrid SMTP, your host's mail server, etc.) works too —
// just fill in its host/port/user/pass here instead.
define('SMTP_HOST',       env('SMTP_HOST', ''));
define('SMTP_PORT',       (int) env('SMTP_PORT', 587));
define('SMTP_USER',       env('SMTP_USER', ''));
define('SMTP_PASS',       env('SMTP_PASS', ''));
define('SMTP_FROM_EMAIL', env('SMTP_FROM_EMAIL', SMTP_USER));
define('SMTP_FROM_NAME',  env('SMTP_FROM_NAME', APP_NAME));

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
