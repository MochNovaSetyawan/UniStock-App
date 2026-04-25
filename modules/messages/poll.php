<?php
// ============================================
// UNISTOCK - Message Polling (AJAX GET)
// ?with=USER_ID&after=MSG_ID  → pesan baru dalam percakapan
// ?counts_only=1              → hanya hitung unread
// ============================================
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['ok' => false]); exit; }

$db   = getDB();
$me   = (int)$_SESSION['user_id'];

// Mode: hanya unread count + notifikasi terbaru (untuk badge & dropdown di header)
if (!empty($_GET['counts_only'])) {
    $notifs = getUnreadNotifications(6);
    foreach ($notifs as &$n) { $n['id'] = (int)$n['id']; }
    unset($n);
    echo json_encode([
        'ok'            => true,
        'unread_msgs'   => countUnreadMessages(),
        'unread_notifs' => countUnreadNotifications(),
        'notifications' => $notifs,
    ]);
    exit;
}

$withId  = (int)($_GET['with']  ?? 0);
$afterId = (int)($_GET['after'] ?? 0);

if (!$withId) { echo json_encode(['ok' => false, 'error' => 'Missing with']); exit; }

// Tandai pesan yang diterima dalam percakapan ini sebagai terbaca
$db->prepare("
    UPDATE messages SET is_read = 1, read_at = NOW()
    WHERE from_user_id = ? AND to_user_id = ? AND is_read = 0
")->execute([$withId, $me]);

// Ambil pesan baru setelah after_id
$stmt = $db->prepare("
    SELECT m.id, m.from_user_id, m.message,
           DATE_FORMAT(m.created_at, '%d %b %Y %H:%i') AS created_at,
           (m.from_user_id = ?) AS from_me
    FROM messages m
    WHERE ((m.from_user_id = ? AND m.to_user_id = ?)
        OR (m.from_user_id = ? AND m.to_user_id = ?))
      AND m.id > ?
    ORDER BY m.id ASC
    LIMIT 50
");
$stmt->execute([$me, $me, $withId, $withId, $me, $afterId]);
$newMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Konversi tipe
foreach ($newMessages as &$msg) {
    $msg['id']       = (int)$msg['id'];
    $msg['from_me']  = (bool)$msg['from_me'];
    $msg['message']  = htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8');
}

echo json_encode([
    'ok'            => true,
    'messages'      => $newMessages,
    'unread_msgs'   => countUnreadMessages(),
    'unread_notifs' => countUnreadNotifications(),
]);
