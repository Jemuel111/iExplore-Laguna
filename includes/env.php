<?php
// ============================================================
// IEXPLORE LAGUNA — Minimal .env loader
// includes/env.php
//
// No Composer/phpdotenv dependency needed — reads a simple
// KEY=VALUE file so real secrets never have to be hardcoded
// (and committed to git) in config.php.
//
// .env lines look like:
//   ORS_API_KEY=your-real-key-here
//   TOMTOM_API_KEY=your-real-key-here
//
// Blank lines and lines starting with # are ignored.
// Values can optionally be wrapped in quotes: KEY="some value"
// ============================================================

function load_env(string $path): void {
    if (!is_readable($path)) {
        return; // .env is optional — env('X', 'fallback') below still works
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;

        $pos = strpos($line, '=');
        if ($pos === false) continue;

        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));

        // Strip matching surrounding quotes, if present
        if (strlen($val) >= 2) {
            $first = $val[0];
            $last  = $val[strlen($val) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $val = substr($val, 1, -1);
            }
        }

        // Don't overwrite real environment variables set at the server level
        if (getenv($key) === false) {
            putenv("{$key}={$val}");
            $_ENV[$key] = $val;
        }
    }
}

/**
 * Reads an environment variable with an optional fallback.
 * Use this in config.php instead of hardcoding secret values.
 */
function env(string $key, $default = null) {
    $val = getenv($key);
    return $val !== false ? $val : $default;
}
