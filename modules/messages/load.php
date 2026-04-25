<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['ok'=>false]); exit; }

$me     = (int)$_SESSION['user_id'];
$withId = (int)($_GET['with'] ?? 0);
if (!$withId) { echo json_encode(['ok'=>false]); exit; }

$db = getDB();

// Tandai sebagai terbaca
$db->prepare("UPDATE messages SET is_read=1, read_at=NOW()
              WHERE from_user_id=? AND to_user_id=? AND is_read=0")
   ->execute([$withId, $me]);

$stmt = $db->prepare("
    SELECT m.id,
           m.from_user_id,
           m.message,
           DATE_FORMAT(m.created_at,'%d %b %Y %H:%i') AS created_at,
           (m.from_user_id = :me) AS from_me
    FROM messages m
    WHERE (m.from_user_id=:me2 AND m.to_user_id=:peer)
       OR (m.from_user_id=:peer2 AND m.to_user_id=:me3)
    ORDER BY m.id DESC LIMIT 60
");
$stmt->execute([':me'=>$me,':me2'=>$me,':me3'=>$me,':peer'=>$withId,':peer2'=>$withId]);
$msgs = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

$lastId = 0;
foreach ($msgs as &$m) {
    $m['id']      = (int)$m['id'];
    $m['from_me'] = (bool)$m['from_me'];
    $m['message'] = htmlspecialchars($m['message'], ENT_QUOTES, 'UTF-8');
    if ($m['id'] > $lastId) $lastId = $m['id'];
}

echo json_encode(['ok'=>true,'messages'=>$msgs,'last_id'=>$lastId]);
