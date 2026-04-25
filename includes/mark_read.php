<?php
// ============================================
// UNISTOCK - Mark Notifications as Read (AJAX)
// POST id=X  → tandai satu notif
// POST id=0  → tandai semua notif
// ============================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['ok' => false]); exit; }

$db  = getDB();
$me  = (int)$_SESSION['user_id'];
$id  = (int)($_POST['id'] ?? 0);

if ($id > 0) {
    $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")
       ->execute([$id, $me]);
} else {
    $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")
       ->execute([$me]);
}

echo json_encode(['ok' => true, 'unread' => countUnreadNotifications()]);
