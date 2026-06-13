<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Message model – private messaging.
 */
class Message extends BaseModel
{
    protected string $table = 'messages';

    public function getConversations(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                IF(m.from_user_id = :uid, m.to_user_id, m.from_user_id) AS partner_id,
                u.full_name, u.role,
                MAX(m.created_at) AS last_at,
                SUM(m.to_user_id = :uid AND m.is_read = 0) AS unread_count,
                (SELECT message FROM messages
                 WHERE (from_user_id = :uid AND to_user_id = partner_id)
                    OR (to_user_id   = :uid AND from_user_id = partner_id)
                 ORDER BY created_at DESC LIMIT 1) AS last_message
            FROM messages m
            JOIN users u ON u.id = IF(m.from_user_id = :uid, m.to_user_id, m.from_user_id)
            WHERE m.from_user_id = :uid OR m.to_user_id = :uid
            GROUP BY partner_id
            ORDER BY last_at DESC
        ");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function getHistory(int $userId, int $partnerId, int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT m.*, u.full_name AS sender_name
            FROM messages m
            JOIN users u ON u.id = m.from_user_id
            WHERE (m.from_user_id = ? AND m.to_user_id = ?)
               OR (m.from_user_id = ? AND m.to_user_id = ?)
            ORDER BY m.created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$userId, $partnerId, $partnerId, $userId, $limit]);
        return $stmt->fetchAll();
    }

    public function markReadFrom(int $toUserId, int $fromUserId): void
    {
        $this->db->prepare(
            "UPDATE messages SET is_read = 1, read_at = NOW()
             WHERE to_user_id = ? AND from_user_id = ? AND is_read = 0"
        )->execute([$toUserId, $fromUserId]);
    }

    public function countUnread(int $userId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM messages WHERE to_user_id = ? AND is_read = 0'
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    public function send(int $fromId, int $toId, string $message): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO messages (from_user_id, to_user_id, message, is_read) VALUES (?,?,?,0)'
        );
        $stmt->execute([$fromId, $toId, $message]);
        return (int) $this->db->lastInsertId();
    }

    public function getNewSince(int $userId, int $partnerId, int $afterId): array
    {
        $stmt = $this->db->prepare("
            SELECT m.*, u.full_name AS sender_name
            FROM messages m
            JOIN users u ON u.id = m.from_user_id
            WHERE m.id > ?
              AND ((m.from_user_id = ? AND m.to_user_id = ?)
                OR (m.from_user_id = ? AND m.to_user_id = ?))
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$afterId, $userId, $partnerId, $partnerId, $userId]);
        return $stmt->fetchAll();
    }
}
