<?php
// Handle maintenance status transitions: start, complete, cancel
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole('superadmin', 'admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }

$db     = getDB();
$id     = (int)($_POST['id'] ?? 0);
$act    = $_POST['action'] ?? '';

$stmt = $db->prepare("
    SELECT m.*, i.id as item_id,
           iu.id as unit_id_val, iu.full_code as unit_full_code
    FROM maintenance m
    JOIN items i ON m.item_id = i.id
    LEFT JOIN item_units iu ON m.unit_id = iu.id
    WHERE m.id = ?
");
$stmt->execute([$id]);
$m = $stmt->fetch();
if (!$m) { flashMessage('error', 'Data tidak ditemukan.'); header('Location: index.php'); exit; }

switch ($act) {

    // ── pending → in_progress ─────────────────────────────────────────────
    case 'start':
        if ($m['status'] !== 'pending') { flashMessage('error', 'Status tidak valid.'); break; }

        $db->prepare("UPDATE maintenance SET status='in_progress', updated_at=NOW() WHERE id=?")
           ->execute([$id]);

        if ($m['unit_id']) {
            $db->prepare("UPDATE item_units SET status='maintenance', updated_at=NOW() WHERE id=?")
               ->execute([$m['unit_id']]);
            syncItemAvailability($db, $m['item_id']);
        }

        auditLog('UPDATE', 'maintenance', $id, 'Maintenance started: ' . $m['code']);
        flashMessage('success', 'Pemeliharaan dimulai &mdash; status diperbarui ke <strong>Diproses</strong>.');
        break;

    // ── in_progress → completed (with unit result) ────────────────────────
    case 'complete':
        if ($m['status'] !== 'in_progress') { flashMessage('error', 'Status tidak valid.'); break; }

        $resolution = trim($_POST['resolution'] ?? '');
        $compDate   = $_POST['completed_date'] ?: date('Y-m-d');
        $unitResult = $_POST['unit_result']    ?? 'repaired';   // repaired | still_damaged | disposed
        $resultCond = $_POST['result_condition'] ?? 'good';     // good | fair | poor

        $db->prepare("
            UPDATE maintenance
            SET status='completed', resolution=?, completed_date=?, updated_at=NOW()
            WHERE id=?
        ")->execute([$resolution, $compDate, $id]);

        if ($m['unit_id']) {
            switch ($unitResult) {
                case 'disposed':
                    $db->prepare("
                        UPDATE item_units
                        SET status='disposed', `condition`='damaged', disposed_date=CURDATE(), updated_at=NOW()
                        WHERE id=?
                    ")->execute([$m['unit_id']]);
                    break;
                case 'still_damaged':
                    $db->prepare("
                        UPDATE item_units
                        SET status='damaged', `condition`='damaged', updated_at=NOW()
                        WHERE id=?
                    ")->execute([$m['unit_id']]);
                    break;
                case 'repaired':
                default:
                    $cond = in_array($resultCond, ['good','fair','poor']) ? $resultCond : 'good';
                    $db->prepare("
                        UPDATE item_units
                        SET status='available', `condition`=?, updated_at=NOW()
                        WHERE id=?
                    ")->execute([$cond, $m['unit_id']]);
                    break;
            }
            syncItemAvailability($db, $m['item_id']);
        }

        auditLog('UPDATE', 'maintenance', $id, 'Maintenance completed: ' . $m['code']);
        flashMessage('success', 'Pemeliharaan selesai &mdash; unit telah diperbarui.');
        break;

    // ── any active → cancelled ────────────────────────────────────────────
    case 'cancel':
        if (!in_array($m['status'], ['pending','in_progress'])) {
            flashMessage('error', 'Status tidak dapat dibatalkan.'); break;
        }

        $db->prepare("UPDATE maintenance SET status='cancelled', updated_at=NOW() WHERE id=?")
           ->execute([$id]);

        if ($m['unit_id']) {
            $prev = $m['unit_prev_status'] ?: 'available';
            if (!in_array($prev, ['available','damaged'])) $prev = 'available';
            $db->prepare("UPDATE item_units SET status=?, updated_at=NOW() WHERE id=?")
               ->execute([$prev, $m['unit_id']]);
            syncItemAvailability($db, $m['item_id']);
        }

        auditLog('UPDATE', 'maintenance', $id, 'Maintenance cancelled: ' . $m['code']);
        flashMessage('success', 'Pemeliharaan dibatalkan.');
        break;

    default:
        flashMessage('error', 'Aksi tidak dikenali.');
}

header('Location: index.php'); exit;
