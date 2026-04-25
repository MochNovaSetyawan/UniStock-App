<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole('superadmin', 'admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }

$db = getDB();
$id = (int)$_POST['id'];
$action = $_POST['action'] ?? '';

$stmt = $db->prepare("SELECT t.*, i.quantity_available FROM transactions t JOIN items i ON t.item_id=i.id WHERE t.id=?");
$stmt->execute([$id]);
$tx = $stmt->fetch();
if (!$tx || $tx['status'] !== 'pending') { flashMessage('error', 'Transaksi tidak dapat diproses.'); header('Location: index.php'); exit; }

// Get linked units (reserved) for this transaction
$unitStmt = $db->prepare("SELECT tu.unit_id FROM transaction_units tu WHERE tu.transaction_id = ?");
$unitStmt->execute([$id]);
$unitIds = $unitStmt->fetchAll(PDO::FETCH_COLUMN);

if ($action === 'approve') {
    // If using unit system: check reserved units exist
    if (!empty($unitIds)) {
        $db->beginTransaction();
        try {
            $db->prepare("UPDATE transactions SET status='active', approved_by=?, approved_at=NOW() WHERE id=?")
               ->execute([$_SESSION['user_id'], $id]);

            // Promote reserved → borrowed for each linked unit
            $upd = $db->prepare("UPDATE item_units SET status='borrowed' WHERE id=? AND status='reserved'");
            foreach ($unitIds as $uid) {
                $upd->execute([$uid]);
            }
            syncItemAvailability($db, $tx['item_id']);

            $db->commit();
            auditLog('UPDATE', 'transactions', $id, 'Transaction approved (units: ' . implode(',', $unitIds) . ')');
            flashMessage('success', 'Peminjaman berhasil disetujui.');
        } catch (Exception $e) {
            $db->rollBack();
            flashMessage('error', 'Gagal menyetujui: ' . $e->getMessage());
        }
    } else {
        // Legacy: quantity-based approval
        if ($tx['quantity_available'] < $tx['quantity']) {
            flashMessage('error', 'Stok tidak mencukupi untuk disetujui.');
        } else {
            $db->prepare("UPDATE transactions SET status='active', approved_by=?, approved_at=NOW() WHERE id=?")->execute([$_SESSION['user_id'], $id]);
            $db->prepare("UPDATE items SET quantity_available=quantity_available-? WHERE id=?")->execute([$tx['quantity'], $tx['item_id']]);
            auditLog('UPDATE', 'transactions', $id, 'Transaction approved');
            flashMessage('success', 'Peminjaman berhasil disetujui.');
        }
    }
} elseif ($action === 'reject') {
    $db->beginTransaction();
    try {
        $db->prepare("UPDATE transactions SET status='rejected', approved_by=?, approved_at=NOW() WHERE id=?")
           ->execute([$_SESSION['user_id'], $id]);

        if (!empty($unitIds)) {
            // Release reserved units back to available
            $upd = $db->prepare("UPDATE item_units SET status='available' WHERE id=? AND status='reserved'");
            foreach ($unitIds as $uid) {
                $upd->execute([$uid]);
            }
            syncItemAvailability($db, $tx['item_id']);
        }

        $db->commit();
        auditLog('UPDATE', 'transactions', $id, 'Transaction rejected');
        flashMessage('info', 'Peminjaman ditolak.');
    } catch (Exception $e) {
        $db->rollBack();
        flashMessage('error', 'Gagal menolak: ' . $e->getMessage());
    }
}
header('Location: index.php'); exit;
