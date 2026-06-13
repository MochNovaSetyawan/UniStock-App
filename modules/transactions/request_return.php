<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Helpers\Format;
use App\Services\AuditService;

Auth::requireLogin();

$pdo = Database::getInstance();

// One-time migration: add return_requested to ENUM + new columns
try {
    $pdo->exec("ALTER TABLE transactions MODIFY COLUMN status ENUM('pending','approved','active','returned','overdue','rejected','cancelled','return_requested') NOT NULL DEFAULT 'pending'");
} catch (\PDOException $e) {}
foreach (['return_requested_at DATETIME NULL DEFAULT NULL', 'return_requested_by INT NULL DEFAULT NULL'] as $colDef) {
    try { $pdo->exec("ALTER TABLE transactions ADD COLUMN $colDef"); } catch (\PDOException $e) {}
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT t.*, i.name AS item_name, i.code AS item_code
    FROM transactions t
    JOIN items i ON t.item_id = i.id
    WHERE t.id = ?
");
$stmt->execute([$id]);
$tx = $stmt->fetch();

if (!$tx || $tx['status'] !== 'active') {
    Session::flash('error', 'Transaksi tidak dapat diajukan pengembalian.');
    header('Location: index.php'); exit;
}

// Worker hanya bisa mengajukan milik sendiri
if (Auth::hasRole('worker') && $tx['requested_by'] != Auth::id()) {
    Session::flash('error', 'Akses ditolak.');
    header('Location: index.php'); exit;
}

// Load linked units
$unitStmt = $pdo->prepare("
    SELECT tu.unit_id, iu.full_code, iu.unit_code, iu.serial_number,
           iu.condition AS current_condition, l.name AS loc_name
    FROM transaction_units tu
    JOIN item_units iu ON tu.unit_id = iu.id
    LEFT JOIN locations l ON iu.location_id = l.id
    WHERE tu.transaction_id = ?
    ORDER BY iu.unit_number ASC
");
$unitStmt->execute([$id]);
$txUnits = $unitStmt->fetchAll(\PDO::FETCH_ASSOC);
$hasUnits = !empty($txUnits);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notes = trim($_POST['return_notes'] ?? '');

    $pdo->beginTransaction();
    try {
        if ($hasUnits) {
            $unitConditions = $_POST['unit_condition'] ?? [];
            $unitNotes      = $_POST['unit_notes']      ?? [];

            $conditionRank    = ['good' => 1, 'fair' => 2, 'poor' => 3, 'damaged' => 4];
            $overallCondition = 'good';
            foreach ($txUnits as $u) {
                $cond = $unitConditions[$u['unit_id']] ?? 'good';
                if (($conditionRank[$cond] ?? 0) > ($conditionRank[$overallCondition] ?? 0)) {
                    $overallCondition = $cond;
                }
            }

            $pdo->prepare("
                UPDATE transactions
                SET status = 'return_requested',
                    return_condition = ?,
                    return_notes = ?,
                    return_requested_at = NOW(),
                    return_requested_by = ?
                WHERE id = ?
            ")->execute([$overallCondition, $notes ?: null, Auth::id(), $id]);

            $updTu = $pdo->prepare("
                UPDATE transaction_units
                SET return_condition = ?, return_notes = ?
                WHERE transaction_id = ? AND unit_id = ?
            ");
            foreach ($txUnits as $u) {
                $cond  = $unitConditions[$u['unit_id']] ?? 'good';
                $unote = trim($unitNotes[$u['unit_id']] ?? '');
                $updTu->execute([$cond, $unote ?: null, $id, $u['unit_id']]);
            }
        } else {
            $condition = $_POST['return_condition'] ?? 'good';
            $pdo->prepare("
                UPDATE transactions
                SET status = 'return_requested',
                    return_condition = ?,
                    return_notes = ?,
                    return_requested_at = NOW(),
                    return_requested_by = ?
                WHERE id = ?
            ")->execute([$condition, $notes ?: null, Auth::id(), $id]);
        }

        $pdo->commit();
        AuditService::log('UPDATE', 'transactions', $id, 'Return requested by worker');
        Session::flash('success', 'Pengajuan pengembalian berhasil dikirim. Menunggu persetujuan admin.');
        header('Location: index.php'); exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $errors[] = 'Gagal memproses pengajuan: ' . $e->getMessage();
    }
}

$pageTitle = 'Ajukan Pengembalian';
include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="page-header">
  <div class="page-title">
    <div class="breadcrumb">
      <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M9 18l6-6-6-6"/></svg>
      <a href="index.php">Transaksi</a>
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M9 18l6-6-6-6"/></svg>
      Ajukan Pengembalian
    </div>
    <h2>Form Pengajuan Pengembalian</h2>
    <p>Isi kondisi barang lalu kirim untuk disetujui admin</p>
  </div>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger mb-20"><?= implode('<br>', array_map([Format::class, 'escape'], $errors)) ?></div>
<?php endif; ?>

<div style="max-width:<?= $hasUnits ? '800px' : '600px' ?>;">

  <!-- Info Peminjaman -->
  <div class="card mb-20">
    <div class="card-header"><div class="card-title">Info Peminjaman</div></div>
    <div class="card-body">
      <div class="detail-grid">
        <div class="detail-field"><label>Kode Transaksi</label><span class="mono"><?= Format::escape($tx['code']) ?></span></div>
        <div class="detail-field"><label>Barang</label><span><?= Format::escape($tx['item_name']) ?></span></div>
        <div class="detail-field"><label>Jumlah</label><span><?= $tx['quantity'] ?> unit</span></div>
        <div class="detail-field"><label>Peminjam</label><span><?= Format::escape($tx['borrower_name']) ?></span></div>
        <div class="detail-field"><label>Tgl Pinjam</label><span><?= Format::datetime($tx['borrow_date']) ?></span></div>
        <div class="detail-field">
          <label>Batas Kembali</label>
          <span style="<?= $tx['expected_return'] < date('Y-m-d H:i:s') ? 'color:var(--danger);font-weight:600;' : '' ?>">
            <?= Format::datetime($tx['expected_return']) ?>
            <?php if ($tx['expected_return'] < date('Y-m-d H:i:s')): ?>
            <span class="badge badge-danger" style="margin-left:6px;">Terlambat</span>
            <?php endif; ?>
          </span>
        </div>
      </div>
    </div>
  </div>

  <form method="POST">
    <input type="hidden" name="id" value="<?= $tx['id'] ?>">

    <?php if ($hasUnits): ?>
    <!-- Kondisi Per Unit -->
    <div class="card mb-20">
      <div class="card-header">
        <div class="card-title">Kondisi Unit yang Dikembalikan</div>
        <span class="badge badge-info"><?= count($txUnits) ?> unit</span>
      </div>
      <div class="card-body" style="padding:0;">
        <div style="padding:12px 20px;border-bottom:1px solid var(--border);background:var(--bg-elevated);display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
          <span style="font-size:0.8rem;color:var(--text-muted);">Isi semua kondisi:</span>
          <?php foreach (['good' => 'Baik', 'fair' => 'Cukup Baik', 'poor' => 'Kurang Baik', 'damaged' => 'Rusak'] as $val => $lbl): ?>
          <button type="button" class="btn btn-outline btn-xs" onclick="fillAllConditions('<?= $val ?>')"><?= $lbl ?></button>
          <?php endforeach; ?>
        </div>
        <div style="display:flex;flex-direction:column;">
          <?php foreach ($txUnits as $u): ?>
          <div class="unit-return-row" style="padding:14px 20px;border-bottom:1px solid var(--border);display:grid;grid-template-columns:1fr 180px;gap:14px;align-items:start;">
            <div>
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                <span class="mono" style="font-size:0.88rem;color:var(--accent);font-weight:600;"><?= Format::escape($u['full_code']) ?></span>
                <?php if ($u['serial_number']): ?>
                <span style="font-size:0.75rem;color:var(--text-muted);">S/N: <?= Format::escape($u['serial_number']) ?></span>
                <?php endif; ?>
                <?php if ($u['loc_name']): ?>
                <span style="font-size:0.75rem;color:var(--text-muted);"><?= Format::escape($u['loc_name']) ?></span>
                <?php endif; ?>
              </div>
              <textarea name="unit_notes[<?= $u['unit_id'] ?>]" class="form-control" rows="1"
                style="font-size:0.8rem;resize:none;" placeholder="Catatan kondisi unit ini (opsional)..."></textarea>
            </div>
            <div>
              <label class="form-label" style="font-size:0.76rem;">Kondisi <span class="required">*</span></label>
              <select name="unit_condition[<?= $u['unit_id'] ?>]" class="form-control unit-condition-select" required>
                <option value="good">Baik</option>
                <option value="fair">Cukup Baik</option>
                <option value="poor">Kurang Baik</option>
                <option value="damaged">Rusak</option>
              </select>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php else: ?>
    <!-- Kondisi tanpa unit (legacy) -->
    <div class="card mb-20">
      <div class="card-header"><div class="card-title">Kondisi Pengembalian</div></div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Kondisi Saat Dikembalikan <span class="required">*</span></label>
          <select name="return_condition" class="form-control" required>
            <option value="good">Baik - Tidak ada kerusakan</option>
            <option value="fair">Cukup Baik - Sedikit keausan wajar</option>
            <option value="poor">Kurang Baik - Ada kerusakan ringan</option>
            <option value="damaged">Rusak - Kerusakan signifikan</option>
          </select>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Catatan -->
    <div class="card">
      <div class="card-header"><div class="card-title">Catatan Pengembalian</div></div>
      <div class="card-body">
        <textarea name="return_notes" class="form-control" rows="3"
          placeholder="Jelaskan kondisi barang saat dikembalikan (opsional)..."></textarea>
      </div>
      <div class="card-footer" style="display:flex;gap:12px;justify-content:flex-end;align-items:center;">
        <a href="index.php" class="btn btn-outline">Batal</a>
        <button type="submit" class="btn btn-primary">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
          Kirim Pengajuan Pengembalian
        </button>
      </div>
    </div>
  </form>
</div>

<script>
function fillAllConditions(val) {
  document.querySelectorAll('.unit-condition-select').forEach(function(sel) {
    sel.value = val;
    sel.dispatchEvent(new Event('change'));
  });
}
document.querySelectorAll('.unit-condition-select').forEach(function(sel) {
  sel.addEventListener('change', function() {
    var row = this.closest('.unit-return-row');
    if (row) row.style.background = this.value === 'damaged' ? 'rgba(239,68,68,0.05)' : '';
  });
});
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
