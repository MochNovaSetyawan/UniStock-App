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

// ---- TAMBAH UNIT (restock) ----
if ($action === 'add_units') {
    $addQty     = max(1, (int)($_POST['add_qty'] ?? 0));
    $condition  = in_array($_POST['condition'] ?? '', ['good','fair','poor']) ? $_POST['condition'] : 'good';
    $locationId = (int)($_POST['location_id'] ?? 0) ?: null;
    $batchPrice = isset($_POST['purchase_price']) && $_POST['purchase_price'] !== ''
                  ? abs((float)$_POST['purchase_price']) : null;

    if (!$itemId) {
        flashMessage('error', 'Barang tidak ditemukan.');
        header("Location: $redirect"); exit;
    }

    // Ambil data item (kode & kategori) untuk prefix unit
    $itemRow = $db->prepare("SELECT code, category_id FROM items WHERE id = ?");
    $itemRow->execute([$itemId]);
    $itemData = $itemRow->fetch();
    if (!$itemData) {
        flashMessage('error', 'Barang tidak ditemukan.');
        header("Location: $redirect"); exit;
    }

    // Hitung total unit saat ini sebagai basis penomoran
    $maxStmt = $db->prepare("SELECT MAX(unit_number) FROM item_units WHERE item_id = ?");
    $maxStmt->execute([$itemId]);
    $currentMax = (int)$maxStmt->fetchColumn();
    $newTotal   = $currentMax + $addQty;

    // Buat unit baru
    generateMissingUnits($db, $itemId, $itemData['code'], $itemData['category_id'], $newTotal, $condition, $locationId);

    // Jika ada harga beli khusus batch ini, terapkan ke unit yang baru dibuat
    if ($batchPrice !== null) {
        $db->prepare("
            UPDATE item_units SET purchase_price = ?
            WHERE item_id = ? AND unit_number > ? AND unit_number <= ?
        ")->execute([$batchPrice, $itemId, $currentMax, $newTotal]);
    }

    // Perbarui items.quantity ke total baru & sinkron quantity_available
    $db->prepare("UPDATE items SET quantity = ?, updated_at = NOW() WHERE id = ?")
       ->execute([$newTotal, $itemId]);
    syncItemAvailability($db, $itemId);

    flashMessage('success', "{$addQty} unit baru berhasil ditambahkan. Total unit sekarang: {$newTotal}.");
    header("Location: $redirect"); exit;
}

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
$price      = $_POST['purchase_price'] !== '' ? abs((float)$_POST['purchase_price']) : null;

$stmt = $db->prepare("UPDATE item_units
    SET status = ?, `condition` = ?, serial_number = ?, location_id = ?,
        notes = ?, disposed_date = ?, purchase_price = ?, updated_at = NOW()
    WHERE id = ? AND item_id = ?");
$stmt->execute([$status, $condition, $serial ?: null, $locationId,
                $notes ?: null, $disposed, $price, $unitId, $itemId]);

// Sync quantity_available on the parent item
syncItemAvailability($db, $itemId);

flashMessage('success', 'Unit berhasil diperbarui.');
header("Location: $redirect"); exit;
