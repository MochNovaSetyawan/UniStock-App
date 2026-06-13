<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Models\Item;
use App\Services\AuditService;

Auth::requireRole('superadmin', 'admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }

$pdo    = Database::getInstance();
$id     = (int)$_POST['id'];
$action = $_POST['action'] ?? '';

// ── Borrow approval / rejection ───────────────────────────────────────────────
if (in_array($action, ['approve', 'reject'])) {

    $stmt = $pdo->prepare("SELECT t.*, i.quantity_available FROM transactions t JOIN items i ON t.item_id=i.id WHERE t.id=?");
    $stmt->execute([$id]);
    $tx = $stmt->fetch();
    if (!$tx || $tx['status'] !== 'pending') {
        Session::flash('error', 'Transaksi tidak dapat diproses.');
        header('Location: index.php'); exit;
    }

    $unitStmt = $pdo->prepare("SELECT tu.unit_id FROM transaction_units tu WHERE tu.transaction_id = ?");
    $unitStmt->execute([$id]);
    $unitIds = $unitStmt->fetchAll(\PDO::FETCH_COLUMN);

    if ($action === 'approve') {
        if (!empty($unitIds)) {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE transactions SET status='active', approved_by=?, approved_at=NOW() WHERE id=?")
                   ->execute([Auth::id(), $id]);
                $upd = $pdo->prepare("UPDATE item_units SET status='borrowed' WHERE id=? AND status='reserved'");
                foreach ($unitIds as $uid) { $upd->execute([$uid]); }
                (new Item())->syncAvailability($tx['item_id']);
                $pdo->commit();
                AuditService::log('UPDATE', 'transactions', $id, 'Transaction approved (units: ' . implode(',', $unitIds) . ')');
                Session::flash('success', 'Peminjaman berhasil disetujui.');
            } catch (Exception $e) {
                $pdo->rollBack();
                Session::flash('error', 'Gagal menyetujui: ' . $e->getMessage());
            }
        } else {
            if ($tx['quantity_available'] < $tx['quantity']) {
                Session::flash('error', 'Stok tidak mencukupi untuk disetujui.');
            } else {
                $pdo->prepare("UPDATE transactions SET status='active', approved_by=?, approved_at=NOW() WHERE id=?")->execute([Auth::id(), $id]);
                $pdo->prepare("UPDATE items SET quantity_available=quantity_available-? WHERE id=?")->execute([$tx['quantity'], $tx['item_id']]);
                AuditService::log('UPDATE', 'transactions', $id, 'Transaction approved');
                Session::flash('success', 'Peminjaman berhasil disetujui.');
            }
        }
    } elseif ($action === 'reject') {
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE transactions SET status='rejected', approved_by=?, approved_at=NOW() WHERE id=?")
               ->execute([Auth::id(), $id]);
            if (!empty($unitIds)) {
                $upd = $pdo->prepare("UPDATE item_units SET status='available' WHERE id=? AND status='reserved'");
                foreach ($unitIds as $uid) { $upd->execute([$uid]); }
                (new Item())->syncAvailability($tx['item_id']);
            }
            $pdo->commit();
            AuditService::log('UPDATE', 'transactions', $id, 'Transaction rejected');
            Session::flash('info', 'Peminjaman ditolak.');
        } catch (Exception $e) {
            $pdo->rollBack();
            Session::flash('error', 'Gagal menolak: ' . $e->getMessage());
        }
    }

// ── Return request approval / rejection ───────────────────────────────────────
} elseif (in_array($action, ['return_approve', 'return_reject'])) {

    $stmt = $pdo->prepare("SELECT t.*, i.id AS item_db_id FROM transactions t JOIN items i ON t.item_id=i.id WHERE t.id=?");
    $stmt->execute([$id]);
    $tx = $stmt->fetch();
    if (!$tx || $tx['status'] !== 'return_requested') {
        Session::flash('error', 'Pengajuan pengembalian tidak valid.');
        header('Location: index.php'); exit;
    }

    $unitStmt = $pdo->prepare("SELECT tu.unit_id, tu.return_condition FROM transaction_units tu WHERE tu.transaction_id = ?");
    $unitStmt->execute([$id]);
    $txUnits = $unitStmt->fetchAll(\PDO::FETCH_ASSOC);

    if ($action === 'return_approve') {
        $pdo->beginTransaction();
        try {
            $pdo->prepare("
                UPDATE transactions
                SET status = 'returned', actual_return = NOW(), returned_by = ?
                WHERE id = ?
            ")->execute([Auth::id(), $id]);

            if (!empty($txUnits)) {
                $updUnit = $pdo->prepare("UPDATE item_units SET status = ?, `condition` = ?, updated_at = NOW() WHERE id = ?");
                foreach ($txUnits as $u) {
                    $cond      = $u['return_condition'] ?? 'good';
                    $newStatus = ($cond === 'damaged') ? 'damaged' : 'available';
                    $updUnit->execute([$newStatus, $cond, $u['unit_id']]);
                }
                (new Item())->syncAvailability($tx['item_id']);
            } else {
                $pdo->prepare("UPDATE items SET quantity_available = quantity_available + ? WHERE id = ?")
                   ->execute([$tx['quantity'], $tx['item_id']]);
            }

            $pdo->commit();
            AuditService::log('UPDATE', 'transactions', $id, 'Return request approved by admin');
            Session::flash('success', 'Pengembalian disetujui. Barang telah dikembalikan ke inventaris.');
        } catch (Exception $e) {
            $pdo->rollBack();
            Session::flash('error', 'Gagal menyetujui pengembalian: ' . $e->getMessage());
        }
    } elseif ($action === 'return_reject') {
        $pdo->beginTransaction();
        try {
            $pdo->prepare("
                UPDATE transactions
                SET status = 'active',
                    return_condition = NULL,
                    return_notes = NULL,
                    return_requested_at = NULL,
                    return_requested_by = NULL
                WHERE id = ?
            ")->execute([$id]);

            // Hapus kondisi unit yang sudah diisi pekerja
            $pdo->prepare("UPDATE transaction_units SET return_condition = NULL, return_notes = NULL WHERE transaction_id = ?")
               ->execute([$id]);

            $pdo->commit();
            AuditService::log('UPDATE', 'transactions', $id, 'Return request rejected by admin');
            Session::flash('info', 'Pengajuan pengembalian ditolak. Transaksi kembali ke status aktif.');
        } catch (Exception $e) {
            $pdo->rollBack();
            Session::flash('error', 'Gagal menolak pengajuan: ' . $e->getMessage());
        }
    }
}

header('Location: index.php'); exit;
