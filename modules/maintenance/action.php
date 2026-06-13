<?php
declare(strict_types=1);
// Handle maintenance status transitions: start, complete, cancel
require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Services\AuditService;

Auth::requireRole('superadmin', 'admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }

$pdo = Database::getInstance();
$id  = (int)($_POST['id'] ?? 0);
$act = $_POST['action'] ?? '';

$stmt = $pdo->prepare("
    SELECT m.*, i.id as item_id,
           iu.id as unit_id_val, iu.full_code as unit_full_code
    FROM maintenance m
    JOIN items i ON m.item_id = i.id
    LEFT JOIN item_units iu ON m.unit_id = iu.id
    WHERE m.id = ?
");
$stmt->execute([$id]);
$m = $stmt->fetch();
if (!$m) { Session::flash('error', 'Data tidak ditemukan.'); header('Location: index.php'); exit; }

$syncAvailability = static function (int $itemId) use ($pdo): void {
    $s = $pdo->prepare("SELECT COUNT(*) FROM item_units WHERE item_id = ? AND status = 'available'");
    $s->execute([$itemId]);
    $pdo->prepare("UPDATE items SET quantity_available = ?, updated_at = NOW() WHERE id = ?")
        ->execute([(int)$s->fetchColumn(), $itemId]);
};

switch ($act) {

    // ── pending → in_progress ─────────────────────────────────────────────
    case 'start':
        if ($m['status'] !== 'pending') { Session::flash('error', 'Status tidak valid.'); break; }

        $pdo->prepare("UPDATE maintenance SET status='in_progress', updated_at=NOW() WHERE id=?")
            ->execute([$id]);

        if ($m['unit_id']) {
            $pdo->prepare("UPDATE item_units SET status='maintenance', updated_at=NOW() WHERE id=?")
                ->execute([$m['unit_id']]);
            $syncAvailability((int)$m['item_id']);
        }

        AuditService::log('UPDATE', 'maintenance', $id, 'Maintenance started: ' . $m['code']);
        Session::flash('success', 'Pemeliharaan dimulai &mdash; status diperbarui ke <strong>Diproses</strong>.');
        break;

    // ── in_progress → completed (with unit result) ────────────────────────
    case 'complete':
        if ($m['status'] !== 'in_progress') { Session::flash('error', 'Status tidak valid.'); break; }

        $resolution = trim($_POST['resolution'] ?? '');
        $compDate   = $_POST['completed_date'] ?: date('Y-m-d');
        $unitResult = $_POST['unit_result']      ?? 'repaired';   // repaired | still_damaged | disposed
        $resultCond = $_POST['result_condition'] ?? 'good';       // good | fair | poor

        $pdo->prepare("
            UPDATE maintenance
            SET status='completed', resolution=?, completed_date=?, updated_at=NOW()
            WHERE id=?
        ")->execute([$resolution, $compDate, $id]);

        if ($m['unit_id']) {
            switch ($unitResult) {
                case 'disposed':
                    $pdo->prepare("
                        UPDATE item_units
                        SET status='disposed', `condition`='damaged', disposed_date=CURDATE(), updated_at=NOW()
                        WHERE id=?
                    ")->execute([$m['unit_id']]);
                    break;
                case 'still_damaged':
                    $pdo->prepare("
                        UPDATE item_units
                        SET status='damaged', `condition`='damaged', updated_at=NOW()
                        WHERE id=?
                    ")->execute([$m['unit_id']]);
                    break;
                case 'repaired':
                default:
                    $cond = in_array($resultCond, ['good', 'fair', 'poor']) ? $resultCond : 'good';
                    $pdo->prepare("
                        UPDATE item_units
                        SET status='available', `condition`=?, updated_at=NOW()
                        WHERE id=?
                    ")->execute([$cond, $m['unit_id']]);
                    break;
            }
            $syncAvailability((int)$m['item_id']);
        }

        AuditService::log('UPDATE', 'maintenance', $id, 'Maintenance completed: ' . $m['code']);
        Session::flash('success', 'Pemeliharaan selesai &mdash; unit telah diperbarui.');
        break;

    // ── any active → cancelled ────────────────────────────────────────────
    case 'cancel':
        if (!in_array($m['status'], ['pending', 'in_progress'])) {
            Session::flash('error', 'Status tidak dapat dibatalkan.'); break;
        }

        $pdo->prepare("UPDATE maintenance SET status='cancelled', updated_at=NOW() WHERE id=?")
            ->execute([$id]);

        if ($m['unit_id']) {
            $prev = $m['unit_prev_status'] ?: 'available';
            if (!in_array($prev, ['available', 'damaged'])) $prev = 'available';
            $pdo->prepare("UPDATE item_units SET status=?, updated_at=NOW() WHERE id=?")
                ->execute([$prev, $m['unit_id']]);
            $syncAvailability((int)$m['item_id']);
        }

        AuditService::log('UPDATE', 'maintenance', $id, 'Maintenance cancelled: ' . $m['code']);
        Session::flash('success', 'Pemeliharaan dibatalkan.');
        break;

    default:
        Session::flash('error', 'Aksi tidak dikenali.');
}

header('Location: index.php'); exit;
