<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['conversations' => []]); exit; }

$me = (int)$_SESSION['user_id'];
$db = getDB();

$stmt = $db->prepare("
    SELECT
        u.id, u.full_name, u.role, u.department,
        lm.message    AS last_message,
        lm.from_user_id AS last_from,
        DATE_FORMAT(lm.created_at, '%d %b %H:%i') AS last_time,
        lm.created_at AS raw_time,
        (SELECT COUNT(*) FROM messages
         WHERE from_user_id = u.id AND to_user_id = :me AND is_read = 0) AS unread
    FROM (
        SELECT DISTINCT
            CASE WHEN from_user_id = :me2 THEN to_user_id ELSE from_user_id END AS partner_id
        FROM messages
        WHERE from_user_id = :me3 OR to_user_id = :me4
    ) AS pairs
    JOIN users u ON u.id = pairs.partner_id
    JOIN messages lm ON lm.id = (
        SELECT id FROM messages
        WHERE (from_user_id = :me5 AND to_user_id = u.id)
           OR (from_user_id = u.id AND to_user_id = :me6)
        ORDER BY id DESC LIMIT 1
    )
    ORDER BY raw_time DESC
");
$stmt->execute([
    ':me' => $me,':me2' => $me,':me3' => $me,
    ':me4' => $me,':me5' => $me,':me6' => $me,
]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as &$r) {
    $r['id']      = (int)$r['id'];
    $r['unread']  = (int)$r['unread'];
    $r['from_me'] = ((int)$r['last_from'] === $me);
    $r['initials'] = strtoupper(mb_substr($r['full_name'], 0, 1));
    unset($r['last_from'], $r['raw_time']);
}

echo json_encode(['conversations' => $rows]);
