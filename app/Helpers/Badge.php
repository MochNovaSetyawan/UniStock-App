<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Badge – renders HTML badge spans used throughout the UI.
 */
final class Badge
{
    private function __construct() {}

    public static function condition(string $condition): string
    {
        $map = [
            'good'    => ['badge-success', 'Baik'],
            'fair'    => ['badge-warning', 'Cukup Baik'],
            'poor'    => ['badge-danger',  'Kurang Baik'],
            'damaged' => ['badge-danger',  'Rusak'],
            'lost'    => ['badge-dark',    'Hilang'],
        ];
        [$class, $label] = $map[$condition] ?? ['badge-secondary', ucfirst($condition)];
        return "<span class=\"badge {$class}\">{$label}</span>";
    }

    public static function status(string $status): string
    {
        $map = [
            'active'      => ['badge-success',   'Aktif'],
            'inactive'    => ['badge-secondary', 'Tidak Aktif'],
            'disposed'    => ['badge-dark',      'Dibuang'],
            'pending'          => ['badge-warning',   'Menunggu'],
            'return_requested' => ['badge-info',      'Pengajuan Kembali'],
            'approved'    => ['badge-info',      'Disetujui'],
            'returned'    => ['badge-success',   'Dikembalikan'],
            'overdue'     => ['badge-danger',    'Terlambat'],
            'rejected'    => ['badge-danger',    'Ditolak'],
            'cancelled'   => ['badge-secondary', 'Dibatalkan'],
            'in_progress' => ['badge-info',      'Diproses'],
            'completed'   => ['badge-success',   'Selesai'],
            'good'        => ['badge-success',   'Baik'],
        ];
        [$class, $label] = $map[$status] ?? ['badge-secondary', ucfirst($status)];
        return "<span class=\"badge {$class}\">{$label}</span>";
    }

    public static function unitStatus(string $status): string
    {
        $map = [
            'available'   => ['badge-success',   'Tersedia'],
            'reserved'    => ['badge-warning',   'Direservasi'],
            'borrowed'    => ['badge-info',      'Dipinjam'],
            'maintenance' => ['badge-warning',   'Maintenance'],
            'damaged'     => ['badge-danger',    'Rusak'],
            'disposed'    => ['badge-secondary', 'Dibuang'],
            'lost'        => ['badge-warning',   'Hilang'],
        ];
        [$class, $label] = $map[$status] ?? ['badge-secondary', ucfirst($status)];
        return "<span class=\"badge {$class}\">{$label}</span>";
    }

    public static function role(string $role): string
    {
        $map = [
            'superadmin' => ['badge-danger',  'Super Admin'],
            'admin'      => ['badge-info',    'Admin'],
            'worker'     => ['badge-success', 'Pekerja'],
        ];
        [$class, $label] = $map[$role] ?? ['badge-secondary', ucfirst($role)];
        return "<span class=\"badge {$class}\">{$label}</span>";
    }

    public static function priority(string $priority): string
    {
        $map = [
            'low'      => ['badge-secondary', 'Rendah'],
            'medium'   => ['badge-warning',   'Sedang'],
            'high'     => ['badge-danger',    'Tinggi'],
            'critical' => ['badge-dark',      'Kritis'],
        ];
        [$class, $label] = $map[$priority] ?? ['badge-secondary', ucfirst($priority)];
        return "<span class=\"badge {$class}\">{$label}</span>";
    }
}
