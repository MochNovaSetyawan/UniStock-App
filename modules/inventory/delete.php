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
$id = (int)($_POST['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) { Session::flash('error', 'Barang tidak ditemukan.'); header('Location: index.php'); exit; }

// Check active borrows
$active = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE item_id = ? AND type = 'borrow' AND status IN ('approved','active')");
$active->execute([$id]);
if ($active->fetchColumn() > 0) {
    Session::flash('error', 'Barang tidak dapat dihapus karena masih ada peminjaman aktif.');
    header('Location: index.php'); exit;
}

// Hapus berurutan mengikuti FK chain
$pdo->beginTransaction();
try {
    // 1. Hapus transaction_units yang merujuk unit barang ini
    $pdo->prepare("
        DELETE tu FROM transaction_units tu
        INNER JOIN item_units iu ON tu.unit_id = iu.id
        WHERE iu.item_id = ?
    ")->execute([$id]);

    // 2. Hapus semua unit barang
    $pdo->prepare("DELETE FROM item_units WHERE item_id = ?")->execute([$id]);

    // 3. Hapus barang
    $pdo->prepare("DELETE FROM items WHERE id = ?")->execute([$id]);

    $pdo->commit();
} catch (\Exception $e) {
    $pdo->rollBack();
    Session::flash('error', 'Gagal menghapus barang: ' . $e->getMessage());
    header('Location: index.php'); exit;
}

AuditService::log('DELETE', 'inventory', $id, 'Item deleted: ' . $item['name'], $item);
Session::flash('success', 'Barang "' . $item['name'] . '" berhasil dihapus.');
header('Location: index.php');
exit;
