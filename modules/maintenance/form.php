<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$pageTitle = 'Lapor Kerusakan / Pemeliharaan';
$db = getDB();

$preItemId = (int)($_GET['item_id'] ?? 0);
$preUnitId = (int)($_GET['unit_id'] ?? 0);
$errors    = [];

// If unit_id given but no item_id, resolve item from unit
if ($preUnitId && !$preItemId) {
    $st = $db->prepare("SELECT item_id FROM item_units WHERE id = ?");
    $st->execute([$preUnitId]);
    $preItemId = (int)($st->fetchColumn() ?: 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $unitId = (int)($_POST['unit_id'] ?? 0) ?: null;
    $data = [
        'item_id'        => (int)$_POST['item_id'],
        'unit_id'        => $unitId,
        'type'           => $_POST['type'] ?? 'corrective',
        'priority'       => $_POST['priority'] ?? 'medium',
        'title'          => trim($_POST['title'] ?? ''),
        'description'    => trim($_POST['description'] ?? ''),
        'scheduled_date' => $_POST['scheduled_date'] ?: null,
        'technician'     => trim($_POST['technician'] ?? ''),
        'cost'           => $_POST['cost'] !== '' ? (float)$_POST['cost'] : null,
        'requested_by'   => $_SESSION['user_id'],
    ];

    if (!$data['item_id']) $errors[] = 'Pilih barang.';
    if (!$data['title'])   $errors[] = 'Judul wajib diisi.';

    if (empty($errors)) {
        $data['code'] = generateCode('MNT', 'maintenance');

        // Capture unit's current status before locking it
        if ($unitId) {
            $stPrev = $db->prepare("SELECT status FROM item_units WHERE id = ?");
            $stPrev->execute([$unitId]);
            $data['unit_prev_status'] = $stPrev->fetchColumn() ?: 'available';
        }

        $cols = implode(',', array_map(fn($k) => "`$k`", array_keys($data)));
        $vals = implode(',', array_fill(0, count($data), '?'));
        $db->prepare("INSERT INTO maintenance ($cols) VALUES ($vals)")->execute(array_values($data));
        $newId = $db->lastInsertId();

        // Lock the unit into maintenance
        if ($unitId) {
            $db->prepare("UPDATE item_units SET status='maintenance', updated_at=NOW() WHERE id=?")
               ->execute([$unitId]);
            syncItemAvailability($db, $data['item_id']);
        }

        auditLog('CREATE', 'maintenance', $newId, 'Maintenance created: ' . $data['title']);
        flashMessage('success', 'Laporan pemeliharaan berhasil dikirim.');
        $redirect = trim($_POST['_redirect'] ?? '');
        header('Location: ' . ($redirect ?: 'index.php')); exit;
    }
}

$items = $db->query("SELECT id, code, name FROM items WHERE status='active' ORDER BY name")->fetchAll();

// Pre-load units for the pre-selected item (for JS init)
$preUnits = [];
if ($preItemId) {
    $st = $db->prepare("
        SELECT iu.id, iu.full_code, iu.unit_code, iu.status, iu.`condition`, iu.serial_number, l.name as loc_name
        FROM item_units iu
        LEFT JOIN locations l ON iu.location_id = l.id
        WHERE iu.item_id = ? AND iu.status IN ('available','damaged')
        ORDER BY iu.unit_number ASC
    ");
    $st->execute([$preItemId]);
    $preUnits = $st->fetchAll(PDO::FETCH_ASSOC);
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title">
    <div class="breadcrumb">
      <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M9 18l6-6-6-6"/></svg>
      <a href="index.php">Pemeliharaan</a>
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M9 18l6-6-6-6"/></svg>
      Lapor
    </div>
    <h2>Laporan Kerusakan / Pemeliharaan</h2>
  </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-20">
  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
  <div><ul style="margin:4px 0 0 16px;"><?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul></div>
</div>
<?php endif; ?>

<form method="POST" id="maintForm">
  <div class="card" style="max-width: 700px;">
    <div class="card-header"><div class="card-title">Detail Laporan</div></div>
    <div class="card-body">
      <div class="form-grid">

        <!-- Barang -->
        <div class="form-group full">
          <label class="form-label">Barang <span class="required">*</span></label>
          <select name="item_id" id="itemSelect" class="form-control" required onchange="loadUnits(this.value)">
            <option value="">-- Pilih Barang --</option>
            <?php foreach ($items as $it): ?>
            <option value="<?= $it['id'] ?>" <?= ($preItemId == $it['id'] || (isset($_POST['item_id']) && $_POST['item_id'] == $it['id'])) ? 'selected' : '' ?>>
              <?= sanitize($it['name']) ?> [<?= sanitize($it['code']) ?>]
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Unit (hanya tampil jika item punya unit) -->
        <div class="form-group full" id="unitRow" style="display:none;">
          <label class="form-label">Unit Spesifik</label>
          <select name="unit_id" id="unitSelect" class="form-control">
            <option value="">-- Pilih Unit (opsional) --</option>
          </select>
          <span class="form-hint">Pilih unit fisik yang akan dipelihara. Kosongkan jika berlaku untuk semua unit.</span>
        </div>

        <!-- Judul -->
        <div class="form-group full">
          <label class="form-label">Judul Laporan <span class="required">*</span></label>
          <input type="text" name="title" class="form-control" required
                 value="<?= sanitize($_POST['title'] ?? '') ?>"
                 placeholder="Deskripsi singkat masalah atau tindakan...">
        </div>

        <!-- Tipe & Prioritas -->
        <div class="form-group">
          <label class="form-label">Tipe</label>
          <select name="type" class="form-control">
            <option value="corrective" <?= ($_POST['type']??'corrective')==='corrective'?'selected':'' ?>>Korektif (Perbaikan Kerusakan)</option>
            <option value="preventive" <?= ($_POST['type']??'')==='preventive'?'selected':'' ?>>Preventif (Pemeliharaan Rutin)</option>
            <option value="inspection" <?= ($_POST['type']??'')==='inspection'?'selected':'' ?>>Inspeksi</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Prioritas</label>
          <select name="priority" class="form-control">
            <option value="low"      <?= ($_POST['priority']??'')==='low'?'selected':'' ?>>Rendah</option>
            <option value="medium"   <?= ($_POST['priority']??'medium')==='medium'?'selected':'' ?>>Sedang</option>
            <option value="high"     <?= ($_POST['priority']??'')==='high'?'selected':'' ?>>Tinggi</option>
            <option value="critical" <?= ($_POST['priority']??'')==='critical'?'selected':'' ?>>Kritis (Tidak dapat digunakan)</option>
          </select>
        </div>

        <!-- Deskripsi -->
        <div class="form-group full">
          <label class="form-label">Deskripsi Masalah</label>
          <textarea name="description" class="form-control" rows="3"
                    placeholder="Jelaskan masalah secara detail..."><?= sanitize($_POST['description'] ?? '') ?></textarea>
        </div>

        <!-- Admin-only fields -->
        <?php if (isAdmin()): ?>
        <div class="form-group">
          <label class="form-label">Jadwal Perbaikan</label>
          <input type="date" name="scheduled_date" class="form-control" value="<?= sanitize($_POST['scheduled_date'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Teknisi / Vendor</label>
          <input type="text" name="technician" class="form-control" value="<?= sanitize($_POST['technician'] ?? '') ?>" placeholder="Nama teknisi...">
        </div>
        <div class="form-group">
          <label class="form-label">Estimasi Biaya (Rp)</label>
          <input type="number" name="cost" class="form-control" value="<?= sanitize($_POST['cost'] ?? '') ?>" placeholder="0" min="0">
        </div>
        <?php endif; ?>

      </div>
    </div>
    <div class="card-footer" style="display:flex;gap:12px;justify-content:flex-end;">
      <a href="index.php" class="btn btn-outline">Batal</a>
      <button type="submit" class="btn btn-primary">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        Kirim Laporan
      </button>
    </div>
  </div>
</form>

<script>
const AJAX_URL = '<?= APP_URL ?>/modules/maintenance/get_units.php';
const preUnits = <?= json_encode($preUnits) ?>;
const preUnitId = <?= $preUnitId ?>;
const preItemId = <?= $preItemId ?>;

function condLabel(c) {
  return {good:'Baik', fair:'Cukup Baik', poor:'Kurang Baik', damaged:'Rusak'}[c] || c;
}
function statusLabel(s) {
  return {available:'Tersedia', damaged:'Rusak'}[s] || s;
}

function renderUnits(units, selectedId) {
  const row  = document.getElementById('unitRow');
  const sel  = document.getElementById('unitSelect');
  sel.innerHTML = '<option value="">-- Pilih Unit (opsional) --</option>';

  if (!units || !units.length) {
    row.style.display = 'none';
    return;
  }

  units.forEach(u => {
    const opt = document.createElement('option');
    opt.value = u.id;
    opt.selected = (parseInt(u.id) === selectedId);
    const serial = u.serial_number ? ` · SN: ${u.serial_number}` : '';
    const loc    = u.loc_name      ? ` · ${u.loc_name}` : '';
    opt.textContent = `${u.full_code}  [${statusLabel(u.status)} · ${condLabel(u.condition)}${serial}${loc}]`;
    sel.appendChild(opt);
  });

  row.style.display = '';
}

function loadUnits(itemId) {
  const row = document.getElementById('unitRow');
  if (!itemId) { row.style.display = 'none'; return; }

  fetch(AJAX_URL + '?item_id=' + itemId)
    .then(r => r.json())
    .then(units => renderUnits(units, 0))
    .catch(() => { row.style.display = 'none'; });
}

// Init on page load if item already selected
if (preItemId && preUnits.length) {
  renderUnits(preUnits, preUnitId);
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
