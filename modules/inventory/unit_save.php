<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole('superadmin', 'admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php'); exit;
}

$db       = getDB();
$itemId   = (int)($_POST['item_id'] ?? 0);
$redirect = $_POST['_redirect'] ?? "units.php?item_id={$itemId}";
$action   = $_POST['action'] ?? 'edit_unit';

// ---- BULK STATUS ----
if ($action === 'bulk_status') {
    $ids    = array_filter(array_map('intval', explode(',', $_POST['bulk_ids'] ?? '')));
    $status = $_POST['bulk_status'] ?? '';
    $allowed = ['available','borrowed','maintenance','damaged','disposed','lost'];
    if (empty($ids) || !in_array($status, $allowed)) {
        flashMessage('error', 'Data tidak valid.'); header("Location: $redirect"); exit;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("UPDATE item_units SET status = ?, updated_at = NOW()
                          WHERE id IN ({$placeholders}) AND item_id = ?");
    $stmt->execute(array_merge([$status], $ids, [$itemId]));

    // Sync quantity_available
    syncItemAvailability($db, $itemId);
    $n = $stmt->rowCount();
    flashMessage('success', "{$n} unit berhasil diubah ke status: {$status}.");
    header("Location: $redirect"); exit;
}

// ---- SINGLE UNIT EDIT ----
$unitId  = (int)($_POST['unit_id'] ?? 0);
if (!$unitId) { flashMessage('error', 'Unit tidak ditemukan.'); header("Location: $redirect"); exit; }

$allowed = ['available','borrowed','maintenance','damaged','disposed','lost'];
$allowedCond = ['good','fair','poor','damaged'];

$status     = in_array($_POST['status'] ?? '', $allowed) ? $_POST['status'] : 'available';
$condition  = in_array($_POST['condition'] ?? '', $allowedCond) ? $_POST['condition'] : 'good';
$serial     = trim($_POST['serial_number'] ?? '');
$locationId = (int)($_POST['location_id'] ?? 0) ?: null;
$notes      = trim($_POST['notes'] ?? '');
$disposed   = ($status === 'disposed') ? date('Y-m-d') : null;

$stmt = $db->prepare("UPDATE item_units
    SET status = ?, `condition` = ?, serial_number = ?, location_id = ?,
        notes = ?, disposed_date = ?, updated_at = NOW()
    WHERE id = ? AND item_id = ?");
$stmt->execute([$status, $condition, $serial ?: null, $locationId,
                $notes ?: null, $disposed, $unitId, $itemId]);

// Sync quantity_available on the parent item
syncItemAvailability($db, $itemId);

flashMessage('success', 'Unit berhasil diperbarui.');
header("Location: $redirect"); exit;
