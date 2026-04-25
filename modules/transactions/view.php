<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("
    SELECT t.*, i.name as item_name, i.code as item_code, i.image as item_image,
           u1.full_name as requested_by_name, u2.full_name as approved_by_name, u3.full_name as returned_by_name,
           l1.name as from_location, l2.name as to_location
    FROM transactions t
    JOIN items i ON t.item_id = i.id
    LEFT JOIN users u1 ON t.requested_by = u1.id
    LEFT JOIN users u2 ON t.approved_by = u2.id
    LEFT JOIN users u3 ON t.returned_by = u3.id
    LEFT JOIN locations l1 ON t.from_location_id = l1.id
    LEFT JOIN locations l2 ON t.to_location_id = l2.id
    WHERE t.id = ?
");
$stmt->execute([$id]);
$tx = $stmt->fetch();
if (!$tx) { flashMessage('error', 'Transaksi tidak ditemukan.'); header('Location: index.php'); exit; }

// Workers can only see their own
if (hasRole('worker') && $tx['requested_by'] != $_SESSION['user_id']) {
    flashMessage('error', 'Akses ditolak.'); header('Location: index.php'); exit;
}

// Load linked units
$txUnitStmt = $db->prepare("
    SELECT tu.unit_id, tu.return_condition, tu.return_notes,
           iu.full_code, iu.unit_code, iu.serial_number,
           iu.status as unit_status, iu.condition as unit_condition,
           l.name as loc_name
    FROM transaction_units tu
    JOIN item_units iu ON tu.unit_id = iu.id
    LEFT JOIN locations l ON iu.location_id = l.id
    WHERE tu.transaction_id = ?
    ORDER BY iu.unit_number ASC
");
$txUnitStmt->execute([$id]);
$txUnits = $txUnitStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Detail Transaksi';
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title">
    <div class="breadcrumb"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a> <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M9 18l6-6-6-6"/></svg> <a href="index.php">Transaksi</a> <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M9 18l6-6-6-6"/></svg> Detail</div>
    <h2>Detail Transaksi</h2>
    <p class="mono"><?= sanitize($tx['code']) ?></p>
  </div>
  <div class="btn-group">
    <?= statusBadge($tx['status']) ?>
    <?php if (isAdmin() && $tx['status'] === 'pending'): ?>
    <form method="POST" action="approve.php" style="display:inline;">
      <input type="hidden" name="id" value="<?= $tx['id'] ?>"><input type="hidden" name="action" value="approve">
      <button type="submit" class="btn btn-success btn-sm">Setujui</button>
    </form>
    <form method="POST" action="approve.php" style="display:inline;">
      <input type="hidden" name="id" value="<?= $tx['id'] ?>"><input type="hidden" name="action" value="reject">
      <button type="submit" class="btn btn-danger btn-sm">Tolak</button>
    </form>
    <?php endif; ?>
    <?php if (isAdmin() && $tx['status'] === 'active'): ?>
    <a href="return.php?id=<?= $tx['id'] ?>" class="btn btn-primary btn-sm">Proses Pengembalian</a>
    <?php endif; ?>
  </div>
</div>

<div class="grid-2" style="gap:20px;align-items:start;">
  <div>
    <div class="card mb-20">
      <div class="card-header"><div class="card-title">Informasi Barang</div></div>
      <div class="card-body">
        <div style="display:flex;gap:14px;align-items:center;">
          <div style="width:56px;height:56px;background:var(--bg-elevated);border-radius:var(--radius-sm);overflow:hidden;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <?php if ($tx['item_image']): ?>
            <img src="<?= UPLOAD_URL . sanitize($tx['item_image']) ?>" style="width:100%;height:100%;object-fit:cover;">
            <?php else: ?>
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="color:var(--text-disabled);"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
            <?php endif; ?>
          </div>
          <div>
            <a href="<?= APP_URL ?>/modules/inventory/view.php?id=<?= $tx['item_id'] ?>" style="font-size:1rem;font-weight:600;color:var(--text-primary);"><?= sanitize($tx['item_name']) ?></a>
            <div class="mono" style="font-size:0.8rem;color:var(--text-muted);"><?= sanitize($tx['item_code']) ?></div>
          </div>
        </div>
        <div class="divider"></div>
        <div class="detail-grid">
          <div class="detail-field"><label>Jumlah</label><span><?= $tx['quantity'] ?></span></div>
          <div class="detail-field"><label>Tipe</label><span><?php $tl=['borrow'=>'Pinjam','return'=>'Kembali','transfer'=>'Transfer']; echo $tl[$tx['type']]??$tx['type']; ?></span></div>
          <?php if ($tx['from_location']): ?><div class="detail-field"><label>Dari</label><span><?= sanitize($tx['from_location']) ?></span></div><?php endif; ?>
          <?php if ($tx['to_location']): ?><div class="detail-field"><label>Ke</label><span><?= sanitize($tx['to_location']) ?></span></div><?php endif; ?>
        </div>
      </div>
    </div>

    <div class="card mb-20">
      <div class="card-header"><div class="card-title">Data Peminjam</div></div>
      <div class="card-body">
        <div class="detail-grid">
          <div class="detail-field"><label>Nama</label><span><?= sanitize($tx['borrower_name'] ?: '-') ?></span></div>
          <div class="detail-field"><label>ID / NIM / NIP</label><span><?= sanitize($tx['borrower_id_number'] ?: '-') ?></span></div>
          <div class="detail-field"><label>Departemen</label><span><?= sanitize($tx['borrower_department'] ?: '-') ?></span></div>
          <div class="detail-field"><label>No. Telepon</label><span><?= sanitize($tx['borrower_phone'] ?: '-') ?></span></div>
        </div>
        <?php if ($tx['purpose']): ?>
        <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border);">
          <label class="form-label" style="display:block;margin-bottom:6px;">Keperluan</label>
          <p style="font-size:0.85rem;color:var(--text-secondary);"><?= nl2br(sanitize($tx['purpose'])) ?></p>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!empty($txUnits)): ?>
    <div class="card mb-20">
      <div class="card-header">
        <div class="card-title">Unit yang Dipinjam</div>
        <span class="badge badge-info"><?= count($txUnits) ?> unit</span>
      </div>
      <div style="overflow:hidden;border-radius:0 0 var(--radius) var(--radius);">
        <table class="table" style="margin:0;">
          <thead>
            <tr>
              <th>Kode Unit</th>
              <th>S/N</th>
              <th>Lokasi</th>
              <th>Status</th>
              <?php if ($tx['status'] === 'returned'): ?><th>Kondisi Kembali</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($txUnits as $u): ?>
            <tr>
              <td><span class="mono" style="color:var(--accent);font-weight:600;"><?= sanitize($u['full_code']) ?></span></td>
              <td style="color:var(--text-muted);"><?= $u['serial_number'] ? sanitize($u['serial_number']) : '-' ?></td>
              <td><?= $u['loc_name'] ? sanitize($u['loc_name']) : '-' ?></td>
              <td><?= unitStatusBadge($u['unit_status']) ?></td>
              <?php if ($tx['status'] === 'returned'): ?>
              <td>
                <?php if ($u['return_condition']): ?>
                  <?= conditionBadge($u['return_condition']) ?>
                  <?php if ($u['return_notes']): ?>
                  <div class="table-item-code" style="margin-top:3px;"><?= sanitize($u['return_notes']) ?></div>
                  <?php endif; ?>
                <?php else: ?>-<?php endif; ?>
              </td>
              <?php endif; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($tx['status'] === 'returned'): ?>
    <div class="card" style="border-color:rgba(16,185,129,0.3);">
      <div class="card-header"><div class="card-title" style="color:var(--success);">Info Pengembalian</div></div>
      <div class="card-body">
        <div class="detail-grid">
          <div class="detail-field"><label>Dikembalikan</label><span><?= formatDateTime($tx['actual_return']) ?></span></div>
          <div class="detail-field"><label>Kondisi</label><span><?= conditionBadge($tx['return_condition'] ?? 'good') ?></span></div>
          <div class="detail-field"><label>Diterima oleh</label><span><?= sanitize($tx['returned_by_name'] ?? '-') ?></span></div>
        </div>
        <?php if ($tx['return_notes']): ?>
        <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border);">
          <label class="form-label" style="display:block;margin-bottom:6px;">Catatan Pengembalian</label>
          <p style="font-size:0.85rem;color:var(--text-secondary);"><?= nl2br(sanitize($tx['return_notes'])) ?></p>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <div>
    <div class="card">
      <div class="card-header"><div class="card-title">Timeline</div></div>
      <div class="activity-list" style="padding:0 20px;">
        <div class="activity-item">
          <div class="activity-dot blue"></div>
          <div class="activity-content">
            <div class="activity-title">Permintaan dibuat</div>
            <div class="activity-meta"><?= sanitize($tx['requested_by_name'] ?? '-') ?> &bull; <?= formatDateTime($tx['created_at']) ?></div>
          </div>
        </div>
        <?php if ($tx['approved_at']): ?>
        <div class="activity-item">
          <div class="activity-dot <?= $tx['status']==='rejected'?'red':'green' ?>"></div>
          <div class="activity-content">
            <div class="activity-title"><?= $tx['status']==='rejected'?'Ditolak':'Disetujui' ?></div>
            <div class="activity-meta"><?= sanitize($tx['approved_by_name'] ?? '-') ?> &bull; <?= formatDateTime($tx['approved_at']) ?></div>
          </div>
        </div>
        <?php endif; ?>
        <?php if ($tx['borrow_date'] && $tx['status'] !== 'pending' && $tx['status'] !== 'rejected'): ?>
        <div class="activity-item">
          <div class="activity-dot amber"></div>
          <div class="activity-content">
            <div class="activity-title">Batas pengembalian</div>
            <div class="activity-meta"><?= formatDateTime($tx['expected_return']) ?></div>
          </div>
        </div>
        <?php endif; ?>
        <?php if ($tx['actual_return']): ?>
        <div class="activity-item">
          <div class="activity-dot green"></div>
          <div class="activity-content">
            <div class="activity-title">Barang dikembalikan</div>
            <div class="activity-meta"><?= sanitize($tx['returned_by_name'] ?? '-') ?> &bull; <?= formatDateTime($tx['actual_return']) ?></div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($tx['notes']): ?>
    <div class="card mt-20">
      <div class="card-header"><div class="card-title">Catatan</div></div>
      <div class="card-body" style="font-size:0.85rem;color:var(--text-secondary);"><?= nl2br(sanitize($tx['notes'])) ?></div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
