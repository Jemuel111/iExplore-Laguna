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

// ── Timezone ──────────────────────────────────────────────────
date_default_timezone_set('Asia/Manila');

// ── Error display (set false in production) ───────────────────
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
