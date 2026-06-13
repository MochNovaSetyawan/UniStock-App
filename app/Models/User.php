<?php

declare(strict_types=1);

namespace App\Models;

/**
 * User model.
 */
class User extends BaseModel
{
    protected string $table = 'users';

    public function findByCredential(string $credential): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1'
        );
        $stmt->execute([$credential, $credential]);
        return $stmt->fetch() ?: null;
    }

    public function updateLastLogin(int $id): void
    {
        $this->db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$id]);
    }

    public function setActive(int $id, bool $active): bool
    {
        return $this->update($id, ['is_active' => (int) $active]);
    }

    /**
     * List users with optional filters.
     *
     * @param array{search?: string, role?: string} $filters
     */
    public function getList(array $filters, int $perPage, int $offset): array
    {
        [$where, $params] = $this->filterWhere($filters);
        $stmt = $this->db->prepare(
            "SELECT * FROM users{$where} ORDER BY created_at DESC LIMIT ? OFFSET ?"
        );
        $stmt->execute([...$params, $perPage, $offset]);
        return $stmt->fetchAll();
    }

    public function getCount(array $filters): int
    {
        [$where, $params] = $this->filterWhere($filters);
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users{$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function filterWhere(array $filters): array
    {
        $where  = ['1=1'];
        $params = [];
        if (!empty($filters['search'])) {
            $where[]  = '(full_name LIKE ? OR username LIKE ? OR email LIKE ?)';
            $s        = '%' . $filters['search'] . '%';
            $params   = array_merge($params, [$s, $s, $s]);
        }
        if (!empty($filters['role'])) {
            $where[]  = 'role = ?';
            $params[] = $filters['role'];
        }
        $sql = ' WHERE ' . implode(' AND ', $where);
        return [$sql, $params];
    }
}
