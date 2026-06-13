<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * CodeGenerator – generates sequential codes per table.
 *
 * Format: PREFIX-YYYYMM0001
 */
final class CodeGenerator
{
    private function __construct() {}

    public static function generate(
        string $prefix,
        string $table,
        string $column = 'code'
    ): string {
        $pdo   = Database::getInstance();
        $year  = date('Y');
        $month = date('m');
        $like  = "{$prefix}-{$year}{$month}%";

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` LIKE ?"
        );
        $stmt->execute([$like]);
        $count = (int) $stmt->fetchColumn() + 1;

        return "{$prefix}-{$year}{$month}" . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
