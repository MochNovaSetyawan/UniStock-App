<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Paginator – generates pagination HTML and helpers.
 */
final class Paginator
{
    private function __construct() {}

    /** Returns current page from $_GET['page'], minimum 1. */
    public static function currentPage(): int
    {
        return max(1, (int) ($_GET['page'] ?? 1));
    }

    /** Calculates the SQL OFFSET. */
    public static function offset(int $page, int $perPage): int
    {
        return ($page - 1) * $perPage;
    }

    /**
     * Renders pagination links.
     *
     * @param string $baseUrl  URL without ?page= (may already have other query params)
     */
    public static function render(int $total, int $perPage, int $currentPage, string $baseUrl): string
    {
        if ($perPage <= 0) {
            return '';
        }
        $totalPages = (int) ceil($total / $perPage);
        if ($totalPages <= 1) {
            return '';
        }

        $sep  = str_contains($baseUrl, '?') ? '&' : '?';
        $html = '<div class="pagination">';

        if ($currentPage > 1) {
            $html .= "<a href=\"{$baseUrl}{$sep}page=" . ($currentPage - 1) . "\" class=\"page-btn\">"
                . '<i data-feather="chevron-left"></i></a>';
        }

        $start = max(1, $currentPage - 2);
        $end   = min($totalPages, $currentPage + 2);
        for ($i = $start; $i <= $end; $i++) {
            $active = $i === $currentPage ? ' active' : '';
            $html  .= "<a href=\"{$baseUrl}{$sep}page={$i}\" class=\"page-btn{$active}\">{$i}</a>";
        }

        if ($currentPage < $totalPages) {
            $html .= "<a href=\"{$baseUrl}{$sep}page=" . ($currentPage + 1) . "\" class=\"page-btn\">"
                . '<i data-feather="chevron-right"></i></a>';
        }

        return $html . '</div>';
    }
}
