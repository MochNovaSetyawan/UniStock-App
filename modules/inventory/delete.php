<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole('superadmin', 'admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }

$db = getDB();
$id = (int)($_POST['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM items WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) { flashMessage('error', 'Barang tidak ditemukan.'); header('Location: index.php'); exit; }

// Check active borrows
$active = $db->prepare("SELECT COUNT(*) FROM transactions WHERE item_id = ? AND type = 'borrow' AND status IN ('approved','active')");
$active->execute([$id]);
if ($active->fetchColumn() > 0) {
    flashMessage('error', 'Barang tidak dapat dihapus karena masih ada peminjaman aktif.');
    header('Location: index.php'); exit;
}

// Hapus berurutan mengikuti FK chain
$db->beginTransaction();
try {
    // 1. Hapus transaction_units yang merujuk unit barang ini
    $db->prepare("
        DELETE tu FROM transaction_units tu
        INNER JOIN item_units iu ON tu.unit_id = iu.id
        WHERE iu.item_id = ?
    ")->execute([$id]);

    // 2. Hapus semua unit barang
    $db->prepare("DELETE FROM item_units WHERE item_id = ?")->execute([$id]);

    // 3. Hapus barang
    $db->prepare("DELETE FROM items WHERE id = ?")->execute([$id]);

    $db->commit();
} catch (\Exception $e) {
    $db->rollBack();
    flashMessage('error', 'Gagal menghapus barang: ' . $e->getMessage());
    header('Location: index.php'); exit;
}

auditLog('DELETE', 'inventory', $id, 'Item deleted: ' . $item['name'], $item);
flashMessage('success', 'Barang "' . $item['name'] . '" berhasil dihapus.');
header('Location: index.php');
exit;
