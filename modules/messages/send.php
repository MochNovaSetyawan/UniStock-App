<?php
// ============================================
// UNISTOCK - Send Message (AJAX POST)
// ============================================
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['ok' => false, 'error' => 'Unauthorized']); exit; }

$toId    = (int)($_POST['to_user_id'] ?? 0);
$message = trim($_POST['message'] ?? '');
$me      = (int)$_SESSION['user_id'];

if (!$toId || !$message) {
    echo json_encode(['ok' => false, 'error' => 'Data tidak lengkap']); exit;
}
if ($toId === $me) {
    echo json_encode(['ok' => false, 'error' => 'Tidak dapat mengirim pesan ke diri sendiri']); exit;
}

$db = getDB();

// Pastikan penerima ada dan aktif
$check = $db->prepare("SELECT id, full_name FROM users WHERE id = ? AND is_active = 1");
$check->execute([$toId]);
$receiver = $check->fetch();
if (!$receiver) {
    echo json_encode(['ok' => false, 'error' => 'Pengguna tidak ditemukan']); exit;
}

// Simpan pesan
$stmt = $db->prepare("
    INSERT INTO messages (from_user_id, to_user_id, message, created_at)
    VALUES (?, ?, ?, NOW())
");
$stmt->execute([$me, $toId, $message]);
$msgId = $db->lastInsertId();

// Kembalikan data pesan baru
echo json_encode([
    'ok'         => true,
    'id'         => (int)$msgId,
    'message'    => htmlspecialchars($message, ENT_QUOTES, 'UTF-8'),
    'created_at' => date('d M Y H:i'),
    'from_me'    => true,
]);
