<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Category model.
 */
class Category extends BaseModel
{
    protected string $table = 'categories';

    public function getAllOrdered(): array
    {
        return $this->db->query('SELECT * FROM categories ORDER BY name')->fetchAll();
    }

    public function hasItems(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM items WHERE category_id = ?');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
