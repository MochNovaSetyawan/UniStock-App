<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * BaseModel – lightweight active-record base class.
 *
 * Subclasses must define:
 *   protected string $table;
 *
 * Optionally override:
 *   protected string $primaryKey = 'id';
 */
abstract class BaseModel
{
    protected PDO    $db;
    protected string $table      = '';
    protected string $primaryKey = 'id';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── Single-row finders ────────────────────────────────────

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findBy(string $column, mixed $value): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM `{$this->table}` WHERE `{$column}` = ? LIMIT 1"
        );
        $stmt->execute([$value]);
        return $stmt->fetch() ?: null;
    }

    // ── Collection queries ────────────────────────────────────

    /**
     * @param array<string, mixed> $conditions  column => value pairs (all ANDed, exact match)
     */
    public function all(
        array  $conditions = [],
        string $orderBy    = '',
        int    $limit      = 0,
        int    $offset     = 0
    ): array {
        [$where, $params] = $this->buildWhere($conditions);

        $sql = "SELECT * FROM `{$this->table}`{$where}";
        if ($orderBy !== '')        $sql .= " ORDER BY {$orderBy}";
        if ($limit   >  0)         $sql .= " LIMIT {$limit}";
        if ($offset  >  0)         $sql .= " OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @param array<string, mixed> $conditions */
    public function count(array $conditions = []): int
    {
        [$where, $params] = $this->buildWhere($conditions);
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM `{$this->table}`{$where}"
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    // ── Mutations ─────────────────────────────────────────────

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $columns      = implode(', ', array_map(fn($k) => "`{$k}`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $stmt = $this->db->prepare(
            "INSERT INTO `{$this->table}` ({$columns}) VALUES ({$placeholders})"
        );
        $stmt->execute(array_values($data));
        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        $set  = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($data)));
        $stmt = $this->db->prepare(
            "UPDATE `{$this->table}` SET {$set} WHERE `{$this->primaryKey}` = ?"
        );
        return $stmt->execute([...array_values($data), $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?"
        );
        return $stmt->execute([$id]);
    }

    // ── Helpers ───────────────────────────────────────────────

    /**
     * Build " WHERE col1 = ? AND col2 = ?" from an associative array.
     *
     * @return array{0: string, 1: list<mixed>}
     */
    protected function buildWhere(array $conditions): array
    {
        if (empty($conditions)) {
            return ['', []];
        }
        $clauses = array_map(fn($k) => "`{$k}` = ?", array_keys($conditions));
        return [' WHERE ' . implode(' AND ', $clauses), array_values($conditions)];
    }

    public function getDb(): PDO
    {
        return $this->db;
    }
}
