<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$pageTitle = 'Pinjam Barang';
$db = getDB();

$preItemId = (int)($_GET['item_id'] ?? 0);
$preItem   = null;
if ($preItemId) {
    $stmt = $db->prepare("SELECT * FROM items WHERE id = ? AND status = 'active'");
    $stmt->execute([$preItemId]);
    $preItem = $stmt->fetch();
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemId        = (int)($_POST['item_id'] ?? 0);
    $unitIds       = array_filter(array_map('intval', $_POST['unit_ids'] ?? []));
    $borrowerName  = trim($_POST['borrower_name']   ?? '');
    $borrowerIdNum = trim($_POST['borrower_id_number'] ?? '');
    $borrowerDept  = trim($_POST['borrower_department'] ?? '');
    $borrowerPhone = trim($_POST['borrower_phone']  ?? '');
    $purpose       = trim($_POST['purpose']         ?? '');
    $borrowDate    = $_POST['borrow_date']           ?? date('Y-m-d H:i:s');
    $expectedReturn= $_POST['expected_return']       ?? '';
    $notes         = trim($_POST['notes']            ?? '');
    $quantity      = count($unitIds);

    if (!$itemId)          $errors[] = 'Pilih barang yang akan dipinjam.';
    if (empty($unitIds))   $errors[] = 'Pilih minimal satu unit yang akan dipinjam.';
    if (!$borrowerName)    $errors[] = 'Nama peminjam wajib diisi.';
    if (!$expectedReturn)  $errors[] = 'Tanggal pengembalian wajib diisi.';

    if (empty($errors)) {
        // Verify item exists
        $stmt = $db->prepare("SELECT * FROM items WHERE id = ? AND status = 'active'");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();
        if (!$item) $errors[] = 'Barang tidak ditemukan.';
    }

    if (empty($errors)) {
        // Verify all selected units are still available for this item
        $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
        $chk = $db->prepare("SELECT id FROM item_units
            WHERE id IN ({$placeholders}) AND item_id = ? AND status = 'available'");
        $chk->execute(array_merge($unitIds, [$itemId]));
        $validUnits = $chk->fetchAll(PDO::FETCH_COLUMN);

        if (count($validUnits) !== count($unitIds)) {
            $errors[] = 'Beberapa unit yang dipilih sudah tidak tersedia. Silakan pilih ulang.';
        }
    }

    if (empty($errors)) {
        $db->beginTransaction();
        try {
            $code        = generateCode('TRX', 'transactions');
            $needApproval= (int)getSetting('require_approval', 1);
            $unitStatus  = isAdmin() ? 'borrowed' : 'reserved';
            $txStatus    = isAdmin() ? 'active'   : 'pending';

            // Insert transaction
            $db->prepare("
                INSERT INTO transactions
                    (code,type,item_id,quantity,borrower_name,borrower_id_number,
                     borrower_department,borrower_phone,purpose,borrow_date,
                     expected_return,status,notes,requested_by,approved_by,approved_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ")->execute([
                $code, 'borrow', $itemId, $quantity,
                $borrowerName, $borrowerIdNum, $borrowerDept, $borrowerPhone,
                $purpose, $borrowDate, $expectedReturn, $txStatus, $notes,
                $_SESSION['user_id'],
                isAdmin() ? $_SESSION['user_id'] : null,
                isAdmin() ? date('Y-m-d H:i:s')  : null,
            ]);
            $txId = (int)$db->lastInsertId();

            // Link units to transaction + update unit status
            $insLink = $db->prepare("INSERT INTO transaction_units (transaction_id, unit_id) VALUES (?,?)");
            $updUnit = $db->prepare("UPDATE item_units SET status = ?, updated_at = NOW() WHERE id = ?");
            foreach ($unitIds as $uid) {
                $insLink->execute([$txId, $uid]);
                $updUnit->execute([$unitStatus, $uid]);
            }

            // Sync quantity_available on item
            syncItemAvailability($db, $itemId);

            $db->commit();
            auditLog('CREATE', 'transactions', $txId, "Borrow request: {$code} ({$quantity} unit)");

            $msg = isAdmin()
                ? "Peminjaman <strong>{$code}</strong> berhasil dicatat. {$quantity} unit dipinjam."
                : "Permohonan <strong>{$code}</strong> diajukan. {$quantity} unit direservasi, menunggu persetujuan admin.";
            flashMessage('success', $msg);
            header('Location: index.php'); exit;

        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}

$items   = $db->query("
    SELECT i.*, c.name as cat_name, c.code as cat_code
    FROM items i
    LEFT JOIN categories c ON i.category_id = c.id
    WHERE i.status = 'active'
      AND (SELECT COUNT(*) FROM item_units WHERE item_id = i.id AND status = 'available') > 0
    ORDER BY i.name
")->fetchAll();

$maxDays = (int)getSetting('borrow_max_days', 14);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title">
    <div class="breadcrumb">
      <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M9 18l6-6-6-6"/></svg>
      <a href="index.php">Transaksi</a>
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M9 18l6-6-6-6"/></svg>
      Pinjam
    </div>
    <h2>Form Peminjaman Barang</h2>
  </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger" style="margin-bottom:20px;">
  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
  <div><ul style="margin:4px 0 0 16px;"><?php foreach($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul></div>
</div>
<?php endif; ?>

<form method="POST" id="borrowForm">
<div class="grid-2" style="align-items:start;gap:20px;">

  <div style="display:flex;flex-direction:column;gap:20px;">

    <!-- Pilih Barang -->
    <div class="card">
      <div class="card-header"><div class="card-title">Pilih Barang &amp; Unit</div></div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:16px;">

        <div class="form-group">
          <label class="form-label">Barang <span class="required">*</span></label>
          <select name="item_id" id="itemSelect" class="form-control" required onchange="loadUnits(this.value)">
            <option value="">-- Pilih Barang --</option>
            <?php foreach ($items as $it): ?>
            <option value="<?= $it['id'] ?>"
                    data-code="<?= sanitize($it['code']) ?>"
                    data-cat="<?= sanitize($it['cat_name'] ?? '') ?>"
                    <?= $preItemId == $it['id'] ? 'selected' : '' ?>>
              <?= sanitize($it['name']) ?>
              [<?= sanitize($it['code']) ?>]
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Unit Picker -->
        <div id="unitPickerWrap" style="display:none;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <label class="form-label" style="margin:0;">Pilih Unit <span class="required">*</span>
              <span id="unitSelectedCount" style="font-weight:400;color:var(--text-muted);font-size:0.78rem;margin-left:6px;"></span>
            </label>
            <div style="display:flex;gap:6px;">
              <button type="button" class="btn btn-ghost btn-sm" onclick="selectAllUnits(true)">Pilih Semua</button>
              <button type="button" class="btn btn-ghost btn-sm" onclick="selectAllUnits(false)">Batalkan</button>
            </div>
          </div>

          <!-- Filter unit -->
          <div style="margin-bottom:8px;">
            <input type="text" id="unitSearch" class="form-control" placeholder="Cari kode unit / serial..." oninput="filterUnits(this.value)" style="font-size:0.82rem;">
          </div>

          <div id="unitList" style="max-height:260px;overflow-y:auto;background:var(--bg-elevated);border-radius:var(--radius-sm);border:1px solid var(--border);"></div>

          <div id="unitEmptyMsg" style="display:none;padding:20px;text-align:center;color:var(--text-muted);font-size:0.82rem;background:var(--bg-elevated);border-radius:var(--radius-sm);border:1px solid var(--border);">
            Tidak ada unit tersedia untuk barang ini.
          </div>
        </div>

        <div id="unitLoadingMsg" style="display:none;padding:14px;text-align:center;color:var(--text-muted);font-size:0.82rem;">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="animation:spin 1s linear infinite;vertical-align:middle;margin-right:6px;"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
          Memuat unit...
        </div>

      </div>
    </div>

    <!-- Data Peminjam -->
    <div class="card">
      <div class="card-header"><div class="card-title">Data Peminjam</div></div>
      <div class="card-body">
        <div class="form-grid">
          <div class="form-group full">
            <label class="form-label">Nama Peminjam <span class="required">*</span></label>
            <input type="text" name="borrower_name" class="form-control" required
                   value="<?= sanitize($_POST['borrower_name'] ?? $currentUser['full_name']) ?>"
                   placeholder="Nama lengkap peminjam">
          </div>
          <div class="form-group">
            <label class="form-label">NIM / NIP / ID</label>
            <input type="text" name="borrower_id_number" class="form-control"
                   value="<?= sanitize($_POST['borrower_id_number'] ?? '') ?>"
                   placeholder="NIM/NIP...">
          </div>
          <div class="form-group">
            <label class="form-label">Departemen / Fakultas</label>
            <input type="text" name="borrower_department" class="form-control"
                   value="<?= sanitize($_POST['borrower_department'] ?? $currentUser['department'] ?? '') ?>"
                   placeholder="Fakultas Teknik...">
          </div>
          <div class="form-group">
            <label class="form-label">No. Telepon</label>
            <input type="text" name="borrower_phone" class="form-control"
                   value="<?= sanitize($_POST['borrower_phone'] ?? '') ?>"
                   placeholder="08xx-xxxx-xxxx">
          </div>
        </div>
      </div>
    </div>

    <!-- Jadwal -->
    <div class="card">
      <div class="card-header"><div class="card-title">Jadwal Peminjaman</div></div>
      <div class="card-body">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Tanggal Pinjam</label>
            <input type="datetime-local" name="borrow_date" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Rencana Kembali <span class="required">*</span></label>
            <input type="datetime-local" name="expected_return" class="form-control" required
                   value="<?= date('Y-m-d\TH:i', strtotime("+{$maxDays} days")) ?>"
                   min="<?= date('Y-m-d\TH:i') ?>">
            <span class="form-hint">Maksimal <?= $maxDays ?> hari</span>
          </div>
          <div class="form-group full">
            <label class="form-label">Keperluan / Tujuan</label>
            <textarea name="purpose" class="form-control" rows="2" placeholder="Jelaskan keperluan peminjaman..."><?= sanitize($_POST['purpose'] ?? '') ?></textarea>
          </div>
          <div class="form-group full">
            <label class="form-label">Catatan Tambahan</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="Catatan opsional..."><?= sanitize($_POST['notes'] ?? '') ?></textarea>
          </div>
        </div>
      </div>
      <div class="card-footer" style="display:flex;gap:12px;justify-content:flex-end;">
        <a href="index.php" class="btn btn-outline">Batal</a>
        <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
          <span id="submitLabel"><?= isAdmin() ? 'Catat Peminjaman' : 'Ajukan Permohonan' ?></span>
        </button>
      </div>
    </div>
  </div>

  <!-- Sidebar info -->
  <div style="display:flex;flex-direction:column;gap:20px;">

    <!-- Summary card -->
    <div class="card" id="summaryCard" style="display:none;">
      <div class="card-header"><div class="card-title">Ringkasan Peminjaman</div></div>
      <div class="card-body" style="font-size:0.85rem;display:flex;flex-direction:column;gap:10px;">
        <div style="display:flex;justify-content:space-between;">
          <span style="color:var(--text-muted);">Barang</span>
          <strong id="sumItem" style="text-align:right;max-width:200px;"></strong>
        </div>
        <div style="display:flex;justify-content:space-between;">
          <span style="color:var(--text-muted);">Jumlah Unit</span>
          <strong id="sumQty" style="color:var(--accent-light);">0</strong>
        </div>
        <div id="sumUnitListWrap" style="border-top:1px solid var(--border);padding-top:10px;">
          <div style="color:var(--text-muted);margin-bottom:6px;font-size:0.78rem;">Unit yang dipilih:</div>
          <div id="sumUnitList" style="display:flex;flex-direction:column;gap:4px;"></div>
        </div>
      </div>
    </div>

    <!-- Panduan -->
    <div class="card">
      <div class="card-header"><div class="card-title">Panduan Peminjaman</div></div>
      <div class="card-body" style="font-size:0.84rem;color:var(--text-secondary);line-height:1.8;">
        <ol style="padding-left:16px;">
          <li>Pilih barang yang ingin dipinjam</li>
          <li>Centang unit-unit yang akan dipinjam</li>
          <li>Isi data peminjam dengan lengkap</li>
          <li>Tentukan jadwal pengembalian</li>
          <?php if (!isAdmin()): ?><li>Tunggu persetujuan admin</li><?php endif; ?>
        </ol>
        <div style="margin-top:14px;background:var(--bg-surface);border-radius:var(--radius-sm);padding:12px;border:1px solid var(--border);">
          <div style="font-weight:600;color:var(--warning);margin-bottom:6px;">⚠ Perhatian</div>
          <ul style="padding-left:16px;font-size:0.79rem;">
            <li>Kembalikan barang sesuai jadwal</li>
            <li>Kerusakan menjadi tanggung jawab peminjam</li>
            <li>Maksimal <?= $maxDays ?> hari peminjaman</li>
          </ul>
        </div>
      </div>
    </div>
  </div>

</div>
</form>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
.unit-item {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 12px; border-bottom: 1px solid var(--border);
  cursor: pointer; transition: background 0.12s;
  user-select: none;
}
.unit-item:last-child { border-bottom: none; }
.unit-item:hover { background: var(--bg-card); }
.unit-item.checked { background: rgba(99,102,241,0.07); }
.unit-item input[type=checkbox] { flex-shrink: 0; width: 15px; height: 15px; cursor: pointer; accent-color: var(--accent); }
.unit-code { font-family: monospace; font-size: 0.83rem; font-weight: 600; color: var(--accent-light); flex: 1; }
.unit-meta { font-size: 0.74rem; color: var(--text-muted); }
.unit-cond { font-size: 0.72rem; }
</style>

<script>
let allUnits = [];

function loadUnits(itemId) {
  const wrap     = document.getElementById('unitPickerWrap');
  const loading  = document.getElementById('unitLoadingMsg');
  const list     = document.getElementById('unitList');
  const emptyMsg = document.getElementById('unitEmptyMsg');
  const sumItem  = document.getElementById('sumItem');

  wrap.style.display    = 'none';
  emptyMsg.style.display= 'none';
  list.innerHTML        = '';
  allUnits              = [];
  updateSummary();

  if (!itemId) return;

  // Set item name in summary
  const sel = document.getElementById('itemSelect');
  sumItem.textContent = sel.options[sel.selectedIndex].text.replace(/\[.*\].*/, '').trim();

  loading.style.display = 'block';

  fetch('<?= APP_URL ?>/modules/transactions/get_units.php?item_id=' + itemId)
    .then(r => r.json())
    .then(units => {
      loading.style.display = 'none';
      allUnits = units;

      if (!units.length) {
        emptyMsg.style.display = 'block';
        return;
      }
      renderUnits(units);
      wrap.style.display = 'block';
    })
    .catch(() => { loading.style.display = 'none'; });
}

function renderUnits(units) {
  const list = document.getElementById('unitList');
  list.innerHTML = units.map(u => `
    <label class="unit-item" id="ui-${u.id}">
      <input type="checkbox" name="unit_ids[]" value="${u.id}" onchange="onUnitCheck(this)">
      <div style="flex:1;min-width:0;">
        <div class="unit-code">${u.full_code}</div>
        <div class="unit-meta">
          ${u.serial_number ? 'SN: ' + u.serial_number + ' &bull; ' : ''}
          ${condLabel(u.condition)}
          ${u.loc_name ? ' &bull; ' + u.loc_name : ''}
        </div>
      </div>
    </label>
  `).join('');
  updateCount();
}

function filterUnits(q) {
  const lq = q.toLowerCase();
  const filtered = q
    ? allUnits.filter(u =>
        u.full_code.toLowerCase().includes(lq) ||
        (u.serial_number || '').toLowerCase().includes(lq))
    : allUnits;

  // Preserve checked state
  const checkedIds = new Set(
    Array.from(document.querySelectorAll('input[name="unit_ids[]"]:checked'))
         .map(c => parseInt(c.value))
  );

  renderUnits(filtered);

  // Re-check previously checked that are still visible
  document.querySelectorAll('input[name="unit_ids[]"]').forEach(c => {
    if (checkedIds.has(parseInt(c.value))) {
      c.checked = true;
      c.closest('.unit-item').classList.add('checked');
    }
  });
  updateCount();
}

function onUnitCheck(cb) {
  cb.closest('.unit-item').classList.toggle('checked', cb.checked);
  updateCount();
  updateSummary();
}

function updateCount() {
  const n   = document.querySelectorAll('input[name="unit_ids[]"]:checked').length;
  const tot = document.querySelectorAll('input[name="unit_ids[]"]').length;
  document.getElementById('unitSelectedCount').textContent = `(${n} dipilih dari ${allUnits.length} tersedia)`;
  document.getElementById('submitBtn').disabled = n === 0;
  const lbl = document.getElementById('submitLabel');
  if (n > 0) lbl.textContent = `<?= isAdmin() ? 'Catat Peminjaman' : 'Ajukan Permohonan' ?> (${n} unit)`;
  else       lbl.textContent = `<?= isAdmin() ? 'Catat Peminjaman' : 'Ajukan Permohonan' ?>`;
}

function updateSummary() {
  const checked = Array.from(document.querySelectorAll('input[name="unit_ids[]"]:checked'));
  const card    = document.getElementById('summaryCard');
  const qty     = document.getElementById('sumQty');
  const ul      = document.getElementById('sumUnitList');

  qty.textContent = checked.length;
  card.style.display = checked.length ? 'block' : 'none';

  ul.innerHTML = checked.map(c => {
    const row = c.closest('.unit-item');
    const code = row.querySelector('.unit-code').textContent;
    return `<div style="font-family:monospace;font-size:0.8rem;color:var(--accent-light);background:var(--bg-elevated);padding:4px 8px;border-radius:4px;">${code}</div>`;
  }).join('');
}

function selectAllUnits(check) {
  document.querySelectorAll('input[name="unit_ids[]"]').forEach(c => {
    c.checked = check;
    c.closest('.unit-item').classList.toggle('checked', check);
  });
  updateCount();
  updateSummary();
}

function condLabel(c) {
  return { good:'Baik', fair:'Cukup Baik', poor:'Kurang Baik', damaged:'Rusak' }[c] || c;
}

// Init if pre-selected item
document.addEventListener('DOMContentLoaded', () => {
  const sel = document.getElementById('itemSelect');
  if (sel.value) loadUnits(sel.value);
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
