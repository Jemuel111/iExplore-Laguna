<?php
// ============================================================
// LAKBAY LAGUNA — Database Connection (PDO Singleton)
// includes/db.php
// ============================================================

require_once __DIR__ . '/config.php';

class Database {

    private static ?PDO $instance = null;

    /**
     * Returns the single PDO instance.
     * Creates connection on first call.
     */
    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST, DB_NAME, DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // In production, log this rather than exposing details
                if (DEBUG_MODE) {
                    die(json_encode([
                        'success' => false,
                        'error'   => 'Database connection failed: ' . $e->getMessage()
                    ]));
                } else {
                    die(json_encode([
                        'success' => false,
                        'error'   => 'Service temporarily unavailable.'
                    ]));
                }
            }
        }

        return self::$instance;
    }

    // Prevent instantiation and cloning
    private function __construct() {}
    private function __clone() {}
}

/**
 * Shortcut helper — returns PDO instance.
 * Usage:  $pdo = db();
 */
function db(): PDO {
    return Database::getInstance();
}
