<?php

declare(strict_types=1);

namespace App\Models;

/**
 * ItemUnit model – individual unit tracking.
 */
class ItemUnit extends BaseModel
{
    protected string $table = 'item_units';

    public function getAvailableForItem(int $itemId): array
    {
        $stmt = $this->db->prepare("
            SELECT u.*, l.name AS loc_name
            FROM item_units u
            LEFT JOIN locations l ON u.location_id = l.id
            WHERE u.item_id = ? AND u.status = 'available'
            ORDER BY u.unit_number
        ");
        $stmt->execute([$itemId]);
        return $stmt->fetchAll();
    }

    public function getForItem(int $itemId): array
    {
        $stmt = $this->db->prepare("
            SELECT u.*, l.name AS loc_name
            FROM item_units u
            LEFT JOIN locations l ON u.location_id = l.id
            WHERE u.item_id = ?
            ORDER BY u.unit_number
        ");
        $stmt->execute([$itemId]);
        return $stmt->fetchAll();
    }

    public function setStatus(int $unitId, string $status): bool
    {
        return (bool) $this->db->prepare(
            "UPDATE item_units SET status = ?, updated_at = NOW() WHERE id = ?"
        )->execute([$status, $unitId]);
    }

    public function getStatusCounts(int $itemId): array
    {
        $defaults = ['available'=>0,'borrowed'=>0,'reserved'=>0,'maintenance'=>0,'damaged'=>0,'disposed'=>0,'lost'=>0];
        $stmt = $this->db->prepare(
            'SELECT status, COUNT(*) AS cnt FROM item_units WHERE item_id = ? GROUP BY status'
        );
        $stmt->execute([$itemId]);
        foreach ($stmt->fetchAll() as $row) {
            $defaults[$row['status']] = (int) $row['cnt'];
        }
        return $defaults;
    }
}
