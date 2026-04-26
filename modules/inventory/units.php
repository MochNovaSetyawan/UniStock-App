<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$db     = getDB();
$itemId = (int)($_GET['item_id'] ?? 0);

// Load item
$stmt = $db->prepare("
    SELECT i.*, c.name as cat_name, c.code as cat_code, c.color as cat_color,
           l.name as loc_name
    FROM items i
    LEFT JOIN categories c ON i.category_id = c.id
    LEFT JOIN locations l  ON i.location_id  = l.id
    WHERE i.id = ?
");
$stmt->execute([$itemId]);
$item = $stmt->fetch();
if (!$item) { flashMessage('error', 'Barang tidak ditemukan.'); header('Location: index.php'); exit; }

$catCode = $item['cat_code'] ?? '';
$prefix  = $catCode ? "{$catCode}-{$item['code']}" : $item['code'];

// Filters
$filterStatus = $_GET['status'] ?? '';
$filterCond   = $_GET['condition'] ?? '';
$search       = trim($_GET['search'] ?? '');

$where  = ['iu.item_id = ?'];
$params = [$itemId];
if ($filterStatus) { $where[] = 'iu.status = ?'; $params[] = $filterStatus; }
if ($filterCond)   { $where[] = 'iu.condition = ?'; $params[] = $filterCond; }
if ($search)       { $where[] = '(iu.full_code LIKE ? OR iu.serial_number LIKE ? OR iu.notes LIKE ?)';
                     $params  = array_merge($params, ["%$search%", "%$search%", "%$search%"]); }

$whereStr = implode(' AND ', $where);

// Stats
$statsRows = $db->prepare("
    SELECT status, COUNT(*) as cnt FROM item_units WHERE item_id = ? GROUP BY status
");
$statsRows->execute([$itemId]);
$stats = ['available'=>0,'reserved'=>0,'borrowed'=>0,'maintenance'=>0,'damaged'=>0,'disposed'=>0,'lost'=>0];
foreach ($statsRows->fetchAll() as $r) { $stats[$r['status']] = (int)$r['cnt']; }
$totalUnits = array_sum($stats);

// Units list
$unitStmt = $db->prepare("
    SELECT iu.*, l.name as loc_name
    FROM item_units iu
    LEFT JOIN locations l ON iu.location_id = l.id
    WHERE {$whereStr}
    ORDER BY iu.unit_number ASC
");
$unitStmt->execute($params);
$units = $unitStmt->fetchAll();

$locations = $db->query("SELECT id, name FROM locations ORDER BY name")->fetchAll();

$pageTitle = 'Kode Unit — ' . $item['name'];
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title">
    <div class="breadcrumb">
      <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M9 18l6-6-6-6"/></svg>
      <a href="index.php">Inventaris</a>
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M9 18l6-6-6-6"/></svg>
      <a href="view.php?id=<?= $item['id'] ?>"><?= sanitize($item['name']) ?></a>
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M9 18l6-6-6-6"/></svg>
      Kode Unit
    </div>
    <h2>Kode Unit — <?= sanitize($item['name']) ?></h2>
    <p class="mono" style="color:var(--accent-light);"><?= sanitize($prefix) ?>-U001 &mdash; <?= sanitize($prefix) ?>-U<?= str_pad($totalUnits, 3, '0', STR_PAD_LEFT) ?></p>
  </div>
  <?php if (isAdmin()): ?>
  <a href="form.php?id=<?= $item['id'] ?>" class="btn btn-outline">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
    Edit Barang
  </a>
  <?php endif; ?>
</div>

<!-- Stats Row -->
<div style="display:grid;grid-template-columns:repeat(7,1fr);gap:10px;margin-bottom:20px;">
  <?php
  $statDef = [
    'available'   => ['Tersedia',     'var(--success)',      '?item_id='.$itemId.'&status=available'],
    'reserved'    => ['Direservasi',  'var(--warning)',      '?item_id='.$itemId.'&status=reserved'],
    'borrowed'    => ['Dipinjam',     'var(--info)',         '?item_id='.$itemId.'&status=borrowed'],
    'maintenance' => ['Maintenance',  '#f59e0b',             '?item_id='.$itemId.'&status=maintenance'],
    'damaged'     => ['Rusak',        'var(--danger)',       '?item_id='.$itemId.'&status=damaged'],
    'disposed'    => ['Dibuang',      'var(--text-muted)',   '?item_id='.$itemId.'&status=disposed'],
    'lost'        => ['Hilang',       'var(--text-muted)',   '?item_id='.$itemId.'&status=lost'],
  ];
  foreach ($statDef as $key => [$label, $color, $link]):
    $active = $filterStatus === $key;
  ?>
  <a href="<?= $link ?>" style="background:var(--bg-card);border:1px solid <?= $active ? $color : 'var(--border)' ?>;border-radius:var(--radius);padding:12px;text-align:center;transition:all 0.2s;text-decoration:none;">
    <div style="font-size:1.5rem;font-weight:800;color:<?= $color ?>;"><?= $stats[$key] ?></div>
    <div style="font-size:0.72rem;color:var(--text-muted);margin-top:2px;"><?= $label ?></div>
  </a>
  <?php endforeach; ?>
</div>

<!-- Unit List Card -->
<div class="card">
  <div class="table-toolbar">
    <form method="GET" style="display:contents;">
      <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
      <div class="search-box">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Cari kode, serial, catatan...">
      </div>
      <select name="status" class="filter-select" onchange="this.form.submit()">
        <option value="">Semua Status</option>
        <option value="available"   <?= $filterStatus==='available'  ?'selected':'' ?>>Tersedia</option>
        <option value="reserved"    <?= $filterStatus==='reserved'   ?'selected':'' ?>>Direservasi</option>
        <option value="borrowed"    <?= $filterStatus==='borrowed'   ?'selected':'' ?>>Dipinjam</option>
        <option value="maintenance" <?= $filterStatus==='maintenance'?'selected':'' ?>>Maintenance</option>
        <option value="damaged"     <?= $filterStatus==='damaged'    ?'selected':'' ?>>Rusak</option>
        <option value="disposed"    <?= $filterStatus==='disposed'   ?'selected':'' ?>>Dibuang</option>
        <option value="lost"        <?= $filterStatus==='lost'       ?'selected':'' ?>>Hilang</option>
      </select>
      <select name="condition" class="filter-select" onchange="this.form.submit()">
        <option value="">Semua Kondisi</option>
        <option value="good"    <?= $filterCond==='good'   ?'selected':'' ?>>Baik</option>
        <option value="fair"    <?= $filterCond==='fair'   ?'selected':'' ?>>Cukup Baik</option>
        <option value="poor"    <?= $filterCond==='poor'   ?'selected':'' ?>>Kurang Baik</option>
        <option value="damaged" <?= $filterCond==='damaged'?'selected':'' ?>>Rusak</option>
      </select>
      <button type="submit" class="btn btn-outline btn-sm">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        Cari
      </button>
      <?php if ($search || $filterStatus || $filterCond): ?>
      <a href="?item_id=<?= $item['id'] ?>" class="btn btn-ghost btn-sm">Reset</a>
      <?php endif; ?>
    </form>
    <div style="margin-left:auto;font-size:0.78rem;color:var(--text-muted);">
      <?= count($units) ?> / <?= $totalUnits ?> unit
    </div>
  </div>

  <div class="table-container">
    <?php if (empty($units)): ?>
    <div class="empty-state" style="padding:40px;">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 00-1.883 2.542l.857 6a2.25 2.25 0 002.227 1.932H19.05a2.25 2.25 0 002.227-1.932l.857-6a2.25 2.25 0 00-1.883-2.542m-16.5 0V6A2.25 2.25 0 016 3.75h3.879a1.5 1.5 0 011.06.44l2.122 2.12a1.5 1.5 0 001.06.44H18A2.25 2.25 0 0120.25 9v.776"/></svg>
      <h3>Tidak ada unit ditemukan</h3>
      <p>Coba ubah filter atau tambah jumlah barang di halaman edit</p>
    </div>
    <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th style="width:32px;"><input type="checkbox" id="checkAll" onchange="toggleAll(this)"></th>
          <th>Kode Unit</th>
          <th>Status</th>
          <th>Kondisi</th>
          <th>Harga Beli</th>
          <th>Serial Number</th>
          <th>Lokasi</th>
          <th>Catatan</th>
          <?php if (isAdmin()): ?><th style="text-align:right;">Aksi</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($units as $u): ?>
        <tr>
          <td><input type="checkbox" class="row-check" value="<?= $u['id'] ?>"></td>
          <td>
            <span class="mono" style="font-size:0.85rem;font-weight:600;color:var(--accent-light);">
              <?= sanitize($u['full_code']) ?>
            </span>
          </td>
          <td><?= unitStatusBadge($u['status']) ?></td>
          <td><?= conditionBadge($u['condition']) ?></td>
          <td style="color:var(--text-secondary);white-space:nowrap;">
            <?php if ($u['purchase_price'] !== null): ?>
              <span style="color:var(--text-primary);font-weight:600;">Rp <?= number_format($u['purchase_price'], 0, ',', '.') ?></span>
            <?php else: ?>
              <span style="color:var(--text-muted);font-size:0.78rem;">— (ikut barang)</span>
            <?php endif; ?>
          </td>
          <td style="color:var(--text-secondary);"><?= sanitize($u['serial_number'] ?: '—') ?></td>
          <td style="color:var(--text-secondary);"><?= sanitize($u['loc_name'] ?: ($item['loc_name'] ?? '—')) ?></td>
          <td style="color:var(--text-muted);max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= sanitize($u['notes']) ?>"><?= sanitize($u['notes'] ?: '—') ?></td>
          <?php if (isAdmin()): ?>
          <td style="text-align:right;">
            <div class="btn-group" style="justify-content:flex-end;">
              <?php if (in_array($u['status'], ['available','damaged'])): ?>
              <a href="<?= APP_URL ?>/modules/maintenance/form.php?item_id=<?= $itemId ?>&unit_id=<?= $u['id'] ?>"
                 class="btn btn-ghost btn-icon btn-sm" title="Lapor Pemeliharaan" style="color:var(--warning);">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
              </a>
              <?php endif; ?>
              <button type="button" class="btn btn-ghost btn-icon btn-sm" title="Edit Unit"
                onclick="openEditModal(<?= htmlspecialchars(json_encode($u)) ?>)">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
              </button>
            </div>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <?php if (isAdmin() && !empty($units)): ?>
  <!-- Bulk action bar -->
  <div id="bulkBar" style="display:none;padding:12px 20px;border-top:1px solid var(--border);background:var(--bg-elevated);align-items:center;gap:12px;flex-wrap:wrap;">
    <span id="bulkCount" style="font-size:0.82rem;color:var(--text-secondary);"></span>
    <span style="color:var(--border);">|</span>
    <span style="font-size:0.82rem;color:var(--text-muted);">Ubah status:</span>
    <?php foreach (['available'=>'Tersedia','borrowed'=>'Dipinjam','maintenance'=>'Maintenance','damaged'=>'Rusak','disposed'=>'Dibuang'] as $sv => $sl): ?>
    <button type="button" class="btn btn-ghost btn-sm" onclick="bulkChangeStatus('<?= $sv ?>')"><?= $sl ?></button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php if (isAdmin()): ?>
<!-- Edit Unit Modal -->
<div class="modal-overlay" id="editUnitModal">
  <div class="modal" style="max-width:480px;width:100%;">
    <div class="modal-header">
      <div class="modal-title">Edit Unit</div>
      <button class="modal-close" onclick="closeModal('editUnitModal')">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <form method="POST" action="unit_save.php">
      <div class="modal-body" style="display:flex;flex-direction:column;gap:14px;">
        <input type="hidden" name="unit_id" id="editUnitId">
        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
        <input type="hidden" name="_redirect" value="units.php?item_id=<?= $item['id'] ?>">

        <div style="background:var(--bg-elevated);border-radius:var(--radius-sm);padding:10px 14px;font-family:monospace;font-size:0.85rem;color:var(--accent-light);" id="editUnitFullCode"></div>

        <div class="form-grid" style="grid-template-columns:1fr 1fr;">
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" id="editUnitStatus" class="form-control">
              <option value="available">Tersedia</option>
              <option value="borrowed">Dipinjam</option>
              <option value="maintenance">Maintenance</option>
              <option value="damaged">Rusak</option>
              <option value="disposed">Dibuang</option>
              <option value="lost">Hilang</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Kondisi</label>
            <select name="condition" id="editUnitCondition" class="form-control">
              <option value="good">Baik</option>
              <option value="fair">Cukup Baik</option>
              <option value="poor">Kurang Baik</option>
              <option value="damaged">Rusak</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Harga Beli Unit</label>
          <input type="number" name="purchase_price" id="editUnitPrice" class="form-control"
                 min="0" step="1" placeholder="Kosongkan jika sama dengan harga barang">
          <span class="form-hint">Harga barang: Rp <?= number_format($item['purchase_price'] ?? 0, 0, ',', '.') ?></span>
        </div>

        <div class="form-group">
          <label class="form-label">Serial Number</label>
          <input type="text" name="serial_number" id="editUnitSerial" class="form-control" placeholder="SN-XXXXXXXX">
        </div>

        <div class="form-group">
          <label class="form-label">Lokasi Unit</label>
          <select name="location_id" id="editUnitLocation" class="form-control">
            <option value="">— Ikut lokasi barang —</option>
            <?php foreach ($locations as $loc): ?>
            <option value="<?= $loc['id'] ?>"><?= sanitize($loc['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Catatan</label>
          <textarea name="notes" id="editUnitNotes" class="form-control" rows="2" placeholder="Catatan khusus unit ini..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('editUnitModal')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- Bulk status form (hidden) -->
<form method="POST" action="unit_save.php" id="bulkForm">
  <input type="hidden" name="bulk_ids" id="bulkIds">
  <input type="hidden" name="bulk_status" id="bulkStatus">
  <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
  <input type="hidden" name="_redirect" value="units.php?item_id=<?= $item['id'] ?>">
  <input type="hidden" name="action" value="bulk_status">
</form>

<?php endif; ?>

<script>
function openEditModal(unit) {
  document.getElementById('editUnitId').value             = unit.id;
  document.getElementById('editUnitFullCode').textContent = unit.full_code;
  document.getElementById('editUnitStatus').value         = unit.status;
  document.getElementById('editUnitCondition').value      = unit.condition;
  document.getElementById('editUnitPrice').value          = unit.purchase_price || '';
  document.getElementById('editUnitSerial').value         = unit.serial_number || '';
  document.getElementById('editUnitLocation').value       = unit.location_id || '';
  document.getElementById('editUnitNotes').value          = unit.notes || '';
  openModal('editUnitModal');
}

// Checkbox select-all
function toggleAll(cb) {
  document.querySelectorAll('.row-check').forEach(c => c.checked = cb.checked);
  updateBulkBar();
}

document.querySelectorAll('.row-check').forEach(c => c.addEventListener('change', updateBulkBar));

function updateBulkBar() {
  const checked = document.querySelectorAll('.row-check:checked');
  const bar = document.getElementById('bulkBar');
  if (!bar) return;
  if (checked.length > 0) {
    bar.style.display = 'flex';
    document.getElementById('bulkCount').textContent = checked.length + ' unit dipilih';
  } else {
    bar.style.display = 'none';
  }
}

function bulkChangeStatus(status) {
  const checked = document.querySelectorAll('.row-check:checked');
  if (!checked.length) return;
  const ids = Array.from(checked).map(c => c.value).join(',');
  if (!confirm(`Ubah ${checked.length} unit ke status "${status}"?`)) return;
  document.getElementById('bulkIds').value   = ids;
  document.getElementById('bulkStatus').value = status;
  document.getElementById('bulkForm').submit();
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
