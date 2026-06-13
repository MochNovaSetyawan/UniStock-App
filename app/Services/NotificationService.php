<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Models\Notification;

/**
 * NotificationService – creates and queries user notifications.
 */
final class NotificationService
{
    private function __construct() {}

    public static function create(
        int     $userId,
        string  $title,
        string  $message,
        string  $type  = 'info',
        ?string $link  = null
    ): bool {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->prepare(
                'INSERT INTO notifications (user_id, title, message, type, link, is_read)
                 VALUES (?, ?, ?, ?, ?, 0)'
            );
            return $stmt->execute([$userId, $title, $message, $type, $link]);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function countUnread(?int $userId = null): int
    {
        $uid = $userId ?? Auth::id();
        if ($uid === null) {
            return 0;
        }
        return (new Notification())->countUnread($uid);
    }

    public static function getUnread(int $limit = 5, ?int $userId = null): array
    {
        $uid = $userId ?? Auth::id();
        if ($uid === null) {
            return [];
        }
        return (new Notification())->getUnread($uid, $limit);
    }

    /**
     * Checks for overdue borrows and creates reminder notifications.
     * Called periodically from the polling AJAX endpoint.
     *
     * @return int number of notifications created
     */
    public static function checkOverdueReminders(): int
    {
        try {
            $pdo      = Database::getInstance();
            $overdue  = $pdo->query("
                SELECT t.id, t.code, t.expected_return, t.requested_by,
                       i.name AS item_name
                FROM transactions t
                JOIN items i ON t.item_id = i.id
                WHERE t.type = 'borrow' AND t.status = 'active' AND t.expected_return < NOW()
            ")->fetchAll();

            $adminIds = $pdo->query(
                "SELECT id FROM users WHERE role IN ('superadmin','admin') AND is_active = 1"
            )->fetchAll(\PDO::FETCH_COLUMN);

            $link   = APP_URL . '/modules/transactions/index.php?status=overdue';
            $count  = 0;

            foreach ($overdue as $tx) {
                $days  = (int) floor((time() - strtotime($tx['expected_return'])) / 86400);
                $title = 'Peminjaman Terlambat';
                $msg   = "Peminjaman {$tx['code']} ({$tx['item_name']}) terlambat {$days} hari.";

                // Notify borrower
                if ($tx['requested_by']) {
                    $already = $pdo->prepare(
                        "SELECT 1 FROM notifications WHERE user_id=? AND title=? AND link=? AND DATE(created_at)=CURDATE()"
                    );
                    $already->execute([$tx['requested_by'], $title, $link]);
                    if (!$already->fetchColumn()) {
                        self::create((int)$tx['requested_by'], $title, $msg, 'danger', $link);
                        $count++;
                    }
                }

                // Notify admins
                foreach ($adminIds as $adminId) {
                    $already = $pdo->prepare(
                        "SELECT 1 FROM notifications WHERE user_id=? AND title=? AND link=? AND DATE(created_at)=CURDATE()"
                    );
                    $already->execute([$adminId, $title, $link]);
                    if (!$already->fetchColumn()) {
                        self::create((int)$adminId, $title, $msg, 'danger', $link);
                        $count++;
                    }
                }
            }

            return $count;
        } catch (\Throwable) {
            return 0;
        }
    }
}
