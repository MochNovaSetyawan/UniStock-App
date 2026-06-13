<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/**
 * Database – PDO singleton.
 *
 * Usage:
 *   $pdo = Database::getInstance();
 */
final class Database
{
    private static ?PDO $pdo = null;

    private function __construct() {}
    private function __clone()   {}

    public static function getInstance(): PDO
    {
        if (self::$pdo === null) {
            try {
                self::$pdo = new PDO(
                    'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                    DB_USER,
                    DB_PASS,
                    [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ]
                );
            } catch (PDOException $e) {
                // Fail fast – do not expose credentials in the message
                http_response_code(500);
                exit('Database connection failed.');
            }
        }

        return self::$pdo;
    }
}
