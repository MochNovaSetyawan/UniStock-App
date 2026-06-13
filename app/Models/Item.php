<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Item (inventory) model.
 */
class Item extends BaseModel
{
    protected string $table = 'items';

    // ── List / count ──────────────────────────────────────────

    public function getList(array $filters, int $perPage, int $offset): array
    {
        [$where, $params] = $this->filterWhere($filters);
        $stmt = $this->db->prepare("
            SELECT i.*, c.name AS category_name, c.code AS category_code, c.color AS category_color,
                   l.name AS location_name, l.building AS location_building,
                   (SELECT COUNT(*) FROM item_units u WHERE u.item_id = i.id AND u.status = 'available') AS units_available,
                   (SELECT COUNT(*) FROM item_units u WHERE u.item_id = i.id) AS units_total
            FROM items i
            LEFT JOIN categories c ON i.category_id = c.id
            LEFT JOIN locations  l ON i.location_id  = l.id
            {$where}
            ORDER BY i.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([...$params, $perPage, $offset]);
        return $stmt->fetchAll();
    }

    public function getCount(array $filters): int
    {
        [$where, $params] = $this->filterWhere($filters);
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM items i{$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getWithDetails(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT i.*, c.name AS category_name, c.code AS category_code, c.color AS category_color,
                   l.name AS location_name, l.building AS location_building
            FROM items i
            LEFT JOIN categories c ON i.category_id = c.id
            LEFT JOIN locations  l ON i.location_id  = l.id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    // ── Availability sync ──────────────────────────────────────

    public function syncAvailability(int $itemId): void
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM item_units WHERE item_id = ? AND status = 'available'"
        );
        $stmt->execute([$itemId]);
        $available = (int) $stmt->fetchColumn();
        $this->db->prepare(
            "UPDATE items SET quantity_available = ?, updated_at = NOW() WHERE id = ?"
        )->execute([$available, $itemId]);
    }

    // ── Unit management ───────────────────────────────────────

    /**
     * Generate item_units from (current max + 1) up to $qty.
     * Returns number of units created.
     */
    public function generateUnits(
        int    $itemId,
        string $itemCode,
        ?int   $catId,
        int    $qty,
        string $condition  = 'good',
        ?int   $locationId = null
    ): int {
        $prefix = $this->buildUnitPrefix($itemCode, $catId);

        $maxStmt = $this->db->prepare(
            'SELECT MAX(unit_number) FROM item_units WHERE item_id = ?'
        );
        $maxStmt->execute([$itemId]);
        $existing = (int) $maxStmt->fetchColumn();

        if ($existing >= $qty) {
            return 0;
        }

        $ins = $this->db->prepare("
            INSERT IGNORE INTO item_units
                (item_id, unit_number, unit_code, full_code, status, `condition`, location_id)
            VALUES (?, ?, ?, ?, 'available', ?, ?)
        ");

        $created = 0;
        for ($n = $existing + 1; $n <= $qty; $n++) {
            $unitCode = 'U' . str_pad((string) $n, 3, '0', STR_PAD_LEFT);
            $ins->execute([$itemId, $n, $unitCode, "{$prefix}-{$unitCode}", $condition, $locationId]);
            $created++;
        }
        return $created;
    }

    /**
     * Rebuild full_code for all existing units of an item.
     * Call when item code or category changes.
     */
    public function rebuildUnitCodes(int $itemId, string $itemCode, ?int $catId): void
    {
        $prefix = $this->buildUnitPrefix($itemCode, $catId);
        $units  = $this->db->prepare(
            'SELECT id, unit_code FROM item_units WHERE item_id = ?'
        );
        $units->execute([$itemId]);
        $upd = $this->db->prepare('UPDATE item_units SET full_code = ? WHERE id = ?');
        foreach ($units->fetchAll() as $u) {
            $upd->execute(["{$prefix}-{$u['unit_code']}", $u['id']]);
        }
    }

    // ── Dashboard helpers ─────────────────────────────────────

    public function getDashboardStats(): array
    {
        $db = $this->db;
        return [
            'total_items'         => (int) $db->query("SELECT COUNT(*) FROM items WHERE status = 'active'")->fetchColumn(),
            'total_categories'    => (int) $db->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
            'total_locations'     => (int) $db->query('SELECT COUNT(*) FROM locations')->fetchColumn(),
            'total_users'         => (int) $db->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn(),
            'active_borrows'      => (int) $db->query("SELECT COUNT(*) FROM transactions WHERE type='borrow' AND status='active'")->fetchColumn(),
            'pending_borrows'     => (int) $db->query("SELECT COUNT(*) FROM transactions WHERE type='borrow' AND status='pending'")->fetchColumn(),
            'overdue_borrows'     => (int) $db->query("SELECT COUNT(*) FROM transactions WHERE type='borrow' AND status='active' AND expected_return < NOW()")->fetchColumn(),
            'pending_maintenance' => (int) $db->query("SELECT COUNT(*) FROM maintenance WHERE status IN ('pending','in_progress')")->fetchColumn(),
            'low_stock_items'     => (int) $db->query("SELECT COUNT(*) FROM items WHERE quantity_available <= min_stock AND status='active'")->fetchColumn(),
        ];
    }

    public function getByCategory(): array
    {
        return $this->db->query("
            SELECT c.name, c.color, COUNT(i.id) AS total
            FROM categories c
            LEFT JOIN items i ON c.id = i.category_id AND i.status = 'active'
            GROUP BY c.id
            ORDER BY total DESC
        ")->fetchAll();
    }

    // ── Private helpers ───────────────────────────────────────

    private function buildUnitPrefix(string $itemCode, ?int $catId): string
    {
        $catCode = '';
        if ($catId) {
            $cs = $this->db->prepare('SELECT code FROM categories WHERE id = ?');
            $cs->execute([$catId]);
            $row     = $cs->fetch();
            $catCode = $row ? strtoupper($row['code']) : '';
        }
        return $catCode ? "{$catCode}-{$itemCode}" : $itemCode;
    }

    private function filterWhere(array $filters): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[]  = '(i.name LIKE ? OR i.code LIKE ? OR i.brand LIKE ? OR i.serial_number LIKE ?)';
            $s        = '%' . $filters['search'] . '%';
            $params   = array_merge($params, [$s, $s, $s, $s]);
        }
        if (!empty($filters['category'])) {
            $where[]  = 'i.category_id = ?';
            $params[] = (int) $filters['category'];
        }
        if (!empty($filters['location'])) {
            $where[]  = 'i.location_id = ?';
            $params[] = (int) $filters['location'];
        }
        if (!empty($filters['condition'])) {
            $where[]  = 'i.condition = ?';
            $params[] = $filters['condition'];
        }
        if (!empty($filters['status'])) {
            $where[]  = 'i.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['low_stock'])) {
            $where[] = 'i.quantity_available <= i.min_stock';
        }

        $sql = ' WHERE ' . implode(' AND ', $where);
        return [$sql, $params];
    }
}
