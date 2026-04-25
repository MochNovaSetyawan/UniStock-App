<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole('superadmin', 'admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }

$db = getDB();
$id = (int)$_POST['id'];

$stmt = $db->prepare("SELECT m.code, m.unit_id, m.item_id, m.status FROM maintenance m WHERE m.id = ?");
$stmt->execute([$id]);
$m = $stmt->fetch();
if (!$m) { flashMessage('error', 'Tidak ditemukan.'); header('Location: index.php'); exit; }

// Restore unit status if it was locked in maintenance
if ($m['unit_id'] && in_array($m['status'], ['pending', 'in_progress'])) {
    $db->prepare("UPDATE item_units SET status='available', updated_at=NOW() WHERE id=?")
       ->execute([$m['unit_id']]);
    syncItemAvailability($db, $m['item_id']);
}

$db->prepare("DELETE FROM maintenance WHERE id=?")->execute([$id]);
auditLog('DELETE', 'maintenance', $id, 'Maintenance deleted: ' . $m['code']);
flashMessage('success', 'Catatan pemeliharaan berhasil dihapus.');
header('Location: index.php'); exit;
