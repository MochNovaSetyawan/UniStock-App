<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Maintenance model.
 */
class Maintenance extends BaseModel
{
    protected string $table = 'maintenance';

    public function getList(array $filters, int $perPage, int $offset): array
    {
        [$where, $params] = $this->filterWhere($filters);
        $stmt = $this->db->prepare("
            SELECT m.*, i.name AS item_name, i.code AS item_code,
                   u1.full_name AS requested_by_name, u2.full_name AS assigned_to_name,
                   iu.full_code AS unit_full_code
            FROM maintenance m
            JOIN items i ON m.item_id = i.id
            LEFT JOIN users u1      ON m.requested_by = u1.id
            LEFT JOIN users u2      ON m.assigned_to  = u2.id
            LEFT JOIN item_units iu ON m.unit_id      = iu.id
            {$where}
            ORDER BY
                CASE m.status WHEN 'in_progress' THEN 1 WHEN 'pending' THEN 2 ELSE 3 END,
                CASE m.priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END,
                m.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([...$params, $perPage, $offset]);
        return $stmt->fetchAll();
    }

    public function getCount(array $filters): int
    {
        [$where, $params] = $this->filterWhere($filters);
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM maintenance m JOIN items i ON m.item_id=i.id{$where}"
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getWithDetails(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT m.*, i.name AS item_name, i.code AS item_code,
                   u1.full_name AS requested_by_name, u2.full_name AS assigned_to_name,
                   iu.full_code AS unit_full_code, iu.id AS unit_id_val
            FROM maintenance m
            JOIN items i ON m.item_id = i.id
            LEFT JOIN users u1      ON m.requested_by = u1.id
            LEFT JOIN users u2      ON m.assigned_to  = u2.id
            LEFT JOIN item_units iu ON m.unit_id      = iu.id
            WHERE m.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    private function filterWhere(array $filters): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[]  = '(m.title LIKE ? OR i.name LIKE ? OR m.code LIKE ?)';
            $s        = '%' . $filters['search'] . '%';
            $params   = array_merge($params, [$s, $s, $s]);
        }
        if (!empty($filters['status'])) {
            $where[]  = 'm.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['priority'])) {
            $where[]  = 'm.priority = ?';
            $params[] = $filters['priority'];
        }
        if (!empty($filters['item_id'])) {
            $where[]  = 'm.item_id = ?';
            $params[] = (int) $filters['item_id'];
        }
        if (!empty($filters['worker_id'])) {
            $where[]  = 'm.requested_by = ?';
            $params[] = (int) $filters['worker_id'];
        }

        return [' WHERE ' . implode(' AND ', $where), $params];
    }
}
