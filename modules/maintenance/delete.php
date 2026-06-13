<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Services\AuditService;

Auth::requireRole('superadmin', 'admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }

$pdo = Database::getInstance();
$id  = (int)$_POST['id'];

$stmt = $pdo->prepare("SELECT m.code, m.unit_id, m.item_id, m.status FROM maintenance m WHERE m.id = ?");
$stmt->execute([$id]);
$m = $stmt->fetch();
if (!$m) { Session::flash('error', 'Tidak ditemukan.'); header('Location: index.php'); exit; }

// Restore unit status if it was locked in maintenance
if ($m['unit_id'] && in_array($m['status'], ['pending', 'in_progress'])) {
    $pdo->prepare("UPDATE item_units SET status='available', updated_at=NOW() WHERE id=?")
        ->execute([$m['unit_id']]);

    $s = $pdo->prepare("SELECT COUNT(*) FROM item_units WHERE item_id = ? AND status = 'available'");
    $s->execute([$m['item_id']]);
    $pdo->prepare("UPDATE items SET quantity_available = ?, updated_at = NOW() WHERE id = ?")
        ->execute([(int)$s->fetchColumn(), $m['item_id']]);
}

$pdo->prepare("DELETE FROM maintenance WHERE id=?")->execute([$id]);
AuditService::log('DELETE', 'maintenance', $id, 'Maintenance deleted: ' . $m['code']);
Session::flash('success', 'Catatan pemeliharaan berhasil dihapus.');
header('Location: index.php'); exit;
