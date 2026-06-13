<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Location model.
 */
class Location extends BaseModel
{
    protected string $table = 'locations';

    public function getAllOrdered(): array
    {
        return $this->db->query('SELECT * FROM locations ORDER BY name')->fetchAll();
    }

    public function hasItems(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM items WHERE location_id = ?');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
