<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Services\NotificationService;

header('Content-Type: application/json');

if (!Auth::check()) {
    echo json_encode(['ok' => false]);
    exit;
}

$pdo = Database::getInstance();
$me  = Auth::id();

// Counts-only mode: for badge & notification dropdown in header
if (!empty($_GET['counts_only'])) {
    $notifs = NotificationService::getUnread(6);
    foreach ($notifs as &$n) { $n['id'] = (int)$n['id']; }
    unset($n);

    $unreadMsgs = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE to_user_id=? AND is_read=0");
    $unreadMsgs->execute([$me]);

    echo json_encode([
        'ok'            => true,
        'unread_msgs'   => (int)$unreadMsgs->fetchColumn(),
        'unread_notifs' => NotificationService::countUnread(),
        'notifications' => $notifs,
    ]);
    exit;
}

$withId  = (int)($_GET['with']  ?? 0);
$afterId = (int)($_GET['after'] ?? 0);

if (!$withId) {
    echo json_encode(['ok' => false, 'error' => 'Missing with']);
    exit;
}

$pdo->prepare("UPDATE messages SET is_read=1, read_at=NOW() WHERE from_user_id=? AND to_user_id=? AND is_read=0")
    ->execute([$withId, $me]);

$stmt = $pdo->prepare("
    SELECT m.id, m.from_user_id, m.message,
           DATE_FORMAT(m.created_at, '%d %b %Y %H:%i') AS created_at,
           (m.from_user_id = ?) AS from_me
    FROM messages m
    WHERE ((m.from_user_id=? AND m.to_user_id=?)
        OR (m.from_user_id=? AND m.to_user_id=?))
      AND m.id > ?
    ORDER BY m.id ASC
    LIMIT 50
");
$stmt->execute([$me, $me, $withId, $withId, $me, $afterId]);
$newMessages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

foreach ($newMessages as &$msg) {
    $msg['id']      = (int)$msg['id'];
    $msg['from_me'] = (bool)$msg['from_me'];
    $msg['message'] = htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8');
}

$unreadMsgs = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE to_user_id=? AND is_read=0");
$unreadMsgs->execute([$me]);

echo json_encode([
    'ok'            => true,
    'messages'      => $newMessages,
    'unread_msgs'   => (int)$unreadMsgs->fetchColumn(),
    'unread_notifs' => NotificationService::countUnread(),
]);
