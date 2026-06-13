<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Setting – key-value application settings (cached per request).
 */
final class Setting
{
    private static array $cache  = [];
    private static bool  $loaded = false;

    private function __construct() {}

    private static function load(): void
    {
        if (self::$loaded) {
            return;
        }
        try {
            $rows = Database::getInstance()
                ->query("SELECT `key`, value FROM settings")
                ->fetchAll();
            foreach ($rows as $row) {
                self::$cache[$row['key']] = $row['value'];
            }
        } catch (\Throwable) {
            // DB might not be ready yet; silently ignore
        }
        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = ''): mixed
    {
        self::load();
        return self::$cache[$key] ?? $default;
    }

    public static function all(): array
    {
        self::load();
        return self::$cache;
    }

    public static function set(string $key, string $value, int $updatedBy): bool
    {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare(
            "INSERT INTO settings (`key`, value, updated_by)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE value = ?, updated_by = ?"
        );
        $ok = $stmt->execute([$key, $value, $updatedBy, $value, $updatedBy]);
        if ($ok) {
            self::$cache[$key] = $value;
        }
        return $ok;
    }
}
