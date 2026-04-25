<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole('superadmin', 'admin');

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT m.*, i.name as item_name, i.id as item_id,
           iu.full_code as unit_full_code,
           iu.status as unit_current_status
    FROM maintenance m
    JOIN items i ON m.item_id = i.id
    LEFT JOIN item_units iu ON m.unit_id = iu.id
    WHERE m.id = ?
");
$stmt->execute([$id]);
$m = $stmt->fetch();
if (!$m) { flashMessage('error', 'Tidak ditemukan.'); header('Location: index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $technician = trim($_POST['technician'] ?? '');
    $cost       = ($_POST['cost'] ?? '') !== '' ? (float)$_POST['cost'] : null;
    $resolution = trim($_POST['resolution'] ?? '');
    $schedDate  = $_POST['scheduled_date'] ?: null;
    $assignedTo = (int)($_POST['assigned_to'] ?? 0) ?: null;

    $db->prepare("
        UPDATE maintenance
        SET technician=?, cost=?, resolution=?, scheduled_date=?, assigned_to=?, updated_at=NOW()
        WHERE id=?
    ")->execute([$technician, $cost, $resolution, $schedDate, $assignedTo, $id]);

    auditLog('UPDATE', 'maintenance', $id, 'Maintenance details updated: ' . $m['code']);
    flashMessage('success', 'Detail pemeliharaan berhasil diperbarui.');
    header('Location: index.php'); exit;
}

$admins    = $db->query("SELECT id, full_name FROM users WHERE role IN ('superadmin','admin') AND is_active=1 ORDER BY full_name")->fetchAll();
$pageTitle = 'Edit Pemeliharaan';
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title">
    <div class="breadcrumb">
      <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M9 18l6-6-6-6"/></svg>
      <a href="index.php">Pemeliharaan</a>
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M9 18l6-6-6-6"/></svg>
      Edit
    </div>
    <h2>Edit Detail Pemeliharaan</h2>
    <p><?= sanitize($m['title']) ?> &mdash; <?= sanitize($m['item_name']) ?></p>
  </div>
</div>

<form method="POST" id="updateForm">
  <div class="card" style="max-width:660px;">

    <!-- Header: badges (read-only status) -->
    <div class="card-header">
      <div class="card-title">Detail Pemeliharaan</div>
      <div style="display:flex;gap:6px;align-items:center;">
        <?= priorityBadge($m['priority']) ?>
        <?= statusBadge($m['status']) ?>
      </div>
    </div>

    <?php if ($m['unit_full_code']): ?>
    <div style="padding:11px 24px;border-bottom:1px solid var(--border);background:var(--accent-glow);display:flex;align-items:center;gap:10px;">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="color:var(--accent-light);flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/></svg>
      <span style="font-size:0.82rem;color:var(--text-secondary);">Unit yang dipelihara:</span>
      <span class="mono" style="font-weight:600;color:var(--accent-light);"><?= sanitize($m['unit_full_code']) ?></span>
      <span style="margin-left:4px;"><?= unitStatusBadge($m['unit_current_status']) ?></span>
    </div>
    <?php endif; ?>

    <div class="card-body">
      <div class="form-grid">

        <!-- Assigned to -->
        <div class="form-group">
          <label class="form-label">Ditugaskan ke</label>
          <select name="assigned_to" class="form-control">
            <option value="">-- Pilih Teknisi Internal --</option>
            <?php foreach ($admins as $a): ?>
            <option value="<?= $a['id'] ?>" <?= $m['assigned_to']==$a['id']?'selected':'' ?>><?= sanitize($a['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Technician -->
        <div class="form-group">
          <label class="form-label">Teknisi / Vendor</label>
          <input type="text" name="technician" class="form-control" value="<?= sanitize($m['technician']??'') ?>" placeholder="Nama teknisi atau vendor...">
        </div>

        <!-- Scheduled date -->
        <div class="form-group">
          <label class="form-label">Jadwal Perbaikan</label>
          <input type="date" name="scheduled_date" class="form-control" value="<?= $m['scheduled_date']??'' ?>">
        </div>

        <!-- Cost -->
        <div class="form-group">
          <label class="form-label">Biaya Aktual (Rp)</label>
          <input type="number" name="cost" class="form-control" value="<?= $m['cost']??'' ?>" placeholder="0" min="0">
        </div>

        <!-- Resolution -->
        <div class="form-group full">
          <label class="form-label">Resolusi / Tindakan yang Dilakukan</label>
          <textarea name="resolution" class="form-control" rows="3" placeholder="Jelaskan tindakan yang sudah dilakukan..."><?= sanitize($m['resolution']??'') ?></textarea>
        </div>

      </div>
    </div>

    <div class="card-footer" style="display:flex;gap:12px;justify-content:flex-end;">
      <a href="index.php" class="btn btn-outline">Batal</a>
      <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
    </div>
  </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
