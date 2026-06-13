<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Session;

/**
 * AuditService – writes to audit_logs table.
 */
final class AuditService
{
    private function __construct() {}

    public static function log(
        string $action,
        string $module,
        ?int   $recordId    = null,
        string $description = '',
        mixed  $oldData     = null,
        mixed  $newData     = null
    ): void {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs
                    (user_id, action, module, record_id, description, old_data, new_data, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                Session::get('user_id'),
                $action,
                $module,
                $recordId,
                $description,
                $oldData !== null ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
                $newData !== null ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null,
                $_SERVER['REMOTE_ADDR']     ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } catch (\Throwable) {
            // Silently fail – audit logging must never break the main flow
        }
    }
}
