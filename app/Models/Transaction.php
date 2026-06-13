<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Transaction model – borrow / return / transfer.
 */
class Transaction extends BaseModel
{
    protected string $table = 'transactions';

    public function getList(array $filters, int $perPage, int $offset): array
    {
        [$where, $params] = $this->filterWhere($filters);
        $stmt = $this->db->prepare("
            SELECT t.*, i.name AS item_name, i.code AS item_code,
                   u.full_name  AS requested_by_name,
                   a.full_name  AS approved_by_name
            FROM transactions t
            JOIN items i ON t.item_id = i.id
            LEFT JOIN users u ON t.requested_by = u.id
            LEFT JOIN users a ON t.approved_by  = a.id
            {$where}
            ORDER BY t.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([...$params, $perPage, $offset]);
        return $stmt->fetchAll();
    }

    public function getCount(array $filters): int
    {
        [$where, $params] = $this->filterWhere($filters);
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM transactions t JOIN items i ON t.item_id = i.id{$where}"
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getWithDetails(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT t.*, i.name AS item_name, i.code AS item_code, i.image AS item_image,
                   u.full_name  AS requested_by_name,
                   a.full_name  AS approved_by_name,
                   r.full_name  AS returned_by_name,
                   c.name       AS category_name, l.name AS location_name
            FROM transactions t
            JOIN items i ON t.item_id = i.id
            LEFT JOIN users u ON t.requested_by = u.id
            LEFT JOIN users a ON t.approved_by  = a.id
            LEFT JOIN users r ON t.returned_by  = r.id
            LEFT JOIN categories c ON i.category_id = c.id
            LEFT JOIN locations  l ON i.location_id  = l.id
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function getRecent(int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT t.*, i.name AS item_name, i.code AS item_code,
                   u.full_name AS requested_by_name
            FROM transactions t
            JOIN items i ON t.item_id = i.id
            LEFT JOIN users u ON t.requested_by = u.id
            ORDER BY t.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public function getLinkedUnits(int $transactionId): array
    {
        $stmt = $this->db->prepare("
            SELECT u.* FROM item_units u
            JOIN transaction_units tu ON u.id = tu.unit_id
            WHERE tu.transaction_id = ?
        ");
        $stmt->execute([$transactionId]);
        return $stmt->fetchAll();
    }

    public function isOverdue(array $tx): bool
    {
        return $tx['type'] === 'borrow'
            && $tx['status'] === 'active'
            && !empty($tx['expected_return'])
            && $tx['expected_return'] < date('Y-m-d H:i:s');
    }

    public function pendingCount(): int
    {
        return (int) $this->db->query(
            "SELECT COUNT(*) FROM transactions WHERE status='pending'"
        )->fetchColumn();
    }

    public function overdueCount(): int
    {
        return (int) $this->db->query(
            "SELECT COUNT(*) FROM transactions WHERE type='borrow' AND status='active' AND expected_return < NOW()"
        )->fetchColumn();
    }

    // ── Private ───────────────────────────────────────────────

    private function filterWhere(array $filters): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['worker_id'])) {
            $where[]  = 't.requested_by = ?';
            $params[] = (int) $filters['worker_id'];
        }
        if (!empty($filters['search'])) {
            $where[]  = '(t.code LIKE ? OR t.borrower_name LIKE ? OR i.name LIKE ?)';
            $s        = '%' . $filters['search'] . '%';
            $params   = array_merge($params, [$s, $s, $s]);
        }
        if (($filters['status'] ?? '') === 'overdue') {
            $where[] = "t.status = 'active' AND t.type = 'borrow' AND t.expected_return < NOW()";
        } elseif (!empty($filters['status'])) {
            $where[]  = 't.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['type'])) {
            $where[]  = 't.type = ?';
            $params[] = $filters['type'];
        }
        if (!empty($filters['item_id'])) {
            $where[]  = 't.item_id = ?';
            $params[] = (int) $filters['item_id'];
        }

        return [' WHERE ' . implode(' AND ', $where), $params];
    }
}
