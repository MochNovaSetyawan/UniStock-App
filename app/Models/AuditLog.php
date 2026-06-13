<?php

declare(strict_types=1);

namespace App\Models;

/**
 * AuditLog model.
 */
class AuditLog extends BaseModel
{
    protected string $table = 'audit_logs';

    public function getList(array $filters, int $perPage, int $offset): array
    {
        [$where, $params] = $this->filterWhere($filters);
        $stmt = $this->db->prepare("
            SELECT al.*, u.full_name, u.username
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            {$where}
            ORDER BY al.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([...$params, $perPage, $offset]);
        return $stmt->fetchAll();
    }

    public function getCount(array $filters): int
    {
        [$where, $params] = $this->filterWhere($filters);
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM audit_logs al{$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function distinctModules(): array
    {
        return $this->db->query(
            'SELECT DISTINCT module FROM audit_logs ORDER BY module'
        )->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function filterWhere(array $filters): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[]  = '(al.action LIKE ? OR al.description LIKE ? OR al.module LIKE ?)';
            $s        = '%' . $filters['search'] . '%';
            $params   = array_merge($params, [$s, $s, $s]);
        }
        if (!empty($filters['module'])) {
            $where[]  = 'al.module = ?';
            $params[] = $filters['module'];
        }
        if (!empty($filters['user_id'])) {
            $where[]  = 'al.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }

        return [' WHERE ' . implode(' AND ', $where), $params];
    }
}
