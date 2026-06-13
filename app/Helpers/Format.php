<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Format – output-formatting utilities.
 */
final class Format
{
    private function __construct() {}

    /** XSS-safe output (replaces sanitize()). */
    public static function escape(mixed $value): string
    {
        return htmlspecialchars(
            strip_tags(trim((string) $value)),
            ENT_QUOTES,
            'UTF-8'
        );
    }

    public static function date(mixed $date, string $format = 'd M Y'): string
    {
        if (!$date || in_array($date, ['0000-00-00', '0000-00-00 00:00:00'], true)) {
            return '-';
        }
        return date($format, strtotime((string) $date));
    }

    public static function datetime(mixed $date): string
    {
        return self::date($date, 'd M Y H:i');
    }

    public static function currency(mixed $amount): string
    {
        if ($amount === null || $amount === '') {
            return '-';
        }
        return 'Rp ' . number_format((float) $amount, 0, ',', '.');
    }
}
