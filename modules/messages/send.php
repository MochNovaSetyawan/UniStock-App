<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;

header('Content-Type: application/json');

if (!Auth::check()) {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$toId    = (int)($_POST['to_user_id'] ?? 0);
$message = trim($_POST['message'] ?? '');
$me      = Auth::id();

if (!$toId || !$message) {
    echo json_encode(['ok' => false, 'error' => 'Data tidak lengkap']);
    exit;
}
if ($toId === $me) {
    echo json_encode(['ok' => false, 'error' => 'Tidak dapat mengirim pesan ke diri sendiri']);
    exit;
}

$pdo   = Database::getInstance();
$check = $pdo->prepare("SELECT id FROM users WHERE id = ? AND is_active = 1");
$check->execute([$toId]);
if (!$check->fetch()) {
    echo json_encode(['ok' => false, 'error' => 'Pengguna tidak ditemukan']);
    exit;
}

$pdo->prepare("INSERT INTO messages (from_user_id, to_user_id, message, created_at) VALUES (?, ?, ?, NOW())")
    ->execute([$me, $toId, $message]);
$msgId = (int)$pdo->lastInsertId();

echo json_encode([
    'ok'         => true,
    'id'         => $msgId,
    'message'    => htmlspecialchars($message, ENT_QUOTES, 'UTF-8'),
    'created_at' => date('d M Y H:i'),
    'from_me'    => true,
]);
