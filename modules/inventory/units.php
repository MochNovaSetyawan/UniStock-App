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
if ($search)       { $where[] = '(iu.full_code LIKE ? OR iu.supplier LIKE ? OR iu.notes LIKE ?)';
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
          <th>Kode Unit</th>
          <th>Status</th>
          <th>Kondisi</th>
          <th>Tgl Beli</th>
          <th>Supplier</th>
          <th>Harga Beli</th>
          <th>Lokasi</th>
          <th>Catatan</th>
          <?php if (isAdmin()): ?><th style="text-align:right;">Aksi</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($units as $u): ?>
        <tr>
          <td>
            <span class="mono" style="font-size:0.85rem;font-weight:600;color:var(--accent-light);">
              <?= sanitize($u['full_code']) ?>
            </span>
          </td>
          <td><?= unitStatusBadge($u['status']) ?></td>
          <td><?= conditionBadge($u['condition']) ?></td>
          <td style="color:var(--text-muted);font-size:0.8rem;white-space:nowrap;"><?= $u['acquired_date'] ? formatDate($u['acquired_date']) : '—' ?></td>
          <td style="color:var(--text-secondary);font-size:0.82rem;"><?= sanitize($u['supplier'] ?: '—') ?></td>
          <td style="color:var(--text-secondary);white-space:nowrap;">
            <?php if ($u['purchase_price'] !== null): ?>
              <span style="color:var(--text-primary);font-weight:600;">Rp <?= number_format($u['purchase_price'], 0, ',', '.') ?></span>
            <?php else: ?>
              <span style="color:var(--text-muted);font-size:0.78rem;">—</span>
            <?php endif; ?>
          </td>
          <td style="color:var(--text-secondary);"><?= sanitize($u['loc_name'] ?: ($item['loc_name'] ?? '—')) ?></td>
          <td style="color:var(--text-muted);max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= sanitize($u['notes']) ?>"><?= sanitize($u['notes'] ?: '—') ?></td>
          <?php if (isAdmin()): ?>
          <td style="text-align:right;">
            <div class="btn-group" style="justify-content:flex-end;">
              <?php if (in_array($u['status'], ['available','damaged'])): ?>
              <button type="button" class="btn btn-ghost btn-icon btn-sm" title="Lapor Pemeliharaan"
                style="color:var(--warning);"
                onclick="openMaintModal(<?= htmlspecialchars(json_encode(['id'=>$u['id'],'full_code'=>$u['full_code'],'item_id'=>$itemId])) ?>)">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
              </button>
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

</div>

<?php if (isAdmin()): ?>
<!-- Edit Unit Modal -->
<div class="modal-overlay" id="editUnitModal">
  <div class="modal" style="max-width:480px;">
    <div class="modal-header">
      <div class="modal-title">Edit Unit</div>
      <button class="modal-close" onclick="closeModal('editUnitModal')">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <form method="POST" action="unit_save.php" style="display:flex;flex-direction:column;flex:1;min-height:0;">
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

        <div class="form-grid" style="grid-template-columns:1fr 1fr;">
          <div class="form-group">
            <label class="form-label">Tanggal Beli</label>
            <input type="date" name="acquired_date" id="editUnitAcquired" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Harga Beli / Unit (Rp)</label>
            <input type="number" name="purchase_price" id="editUnitPrice" class="form-control"
                   min="0" step="1" placeholder="0">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Supplier</label>
          <input type="text" name="supplier" id="editUnitSupplier" class="form-control" placeholder="Nama supplier...">
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

<!-- Lapor Pemeliharaan Modal -->
<div class="modal-overlay" id="maintModal">
  <div class="modal" style="max-width:520px;">
    <div class="modal-header">
      <div class="modal-title">Lapor Pemeliharaan</div>
      <button class="modal-close" onclick="closeModal('maintModal')">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <form method="POST" action="<?= APP_URL ?>/modules/maintenance/form.php" style="display:flex;flex-direction:column;flex:1;min-height:0;">
      <div class="modal-body" style="display:flex;flex-direction:column;gap:14px;">
        <input type="hidden" name="item_id"   id="maintItemId">
        <input type="hidden" name="unit_id"   id="maintUnitId">
        <input type="hidden" name="_redirect" value="<?= APP_URL ?>/modules/inventory/units.php?item_id=<?= $item['id'] ?>">

        <div style="background:var(--bg-elevated);border-radius:var(--radius-sm);padding:10px 14px;font-family:monospace;font-size:0.85rem;color:var(--warning);" id="maintUnitCode"></div>

        <div class="form-group" style="margin:0;">
          <label class="form-label">Judul Laporan <span class="required">*</span></label>
          <input type="text" name="title" id="maintTitle" class="form-control" required
                 placeholder="Deskripsi singkat masalah atau tindakan...">
        </div>

        <div class="form-grid" style="grid-template-columns:1fr 1fr;">
          <div class="form-group" style="margin:0;">
            <label class="form-label">Tipe</label>
            <select name="type" class="form-control">
              <option value="corrective">Korektif (Perbaikan)</option>
              <option value="preventive">Preventif (Rutin)</option>
              <option value="inspection">Inspeksi</option>
            </select>
          </div>
          <div class="form-group" style="margin:0;">
            <label class="form-label">Prioritas</label>
            <select name="priority" class="form-control">
              <option value="low">Rendah</option>
              <option value="medium" selected>Sedang</option>
              <option value="high">Tinggi</option>
              <option value="critical">Kritis</option>
            </select>
          </div>
        </div>

        <div class="form-group" style="margin:0;">
          <label class="form-label">Deskripsi Masalah</label>
          <textarea name="description" class="form-control" rows="3"
                    placeholder="Jelaskan masalah secara detail..."></textarea>
        </div>

        <?php if (isAdmin()): ?>
        <div style="height:1px;background:var(--border);"></div>
        <div class="form-grid" style="grid-template-columns:1fr 1fr;">
          <div class="form-group" style="margin:0;">
            <label class="form-label">Jadwal Perbaikan</label>
            <input type="date" name="scheduled_date" class="form-control">
          </div>
          <div class="form-group" style="margin:0;">
            <label class="form-label">Estimasi Biaya (Rp)</label>
            <input type="number" name="cost" class="form-control" min="0" placeholder="0">
          </div>
          <div class="form-group full" style="margin:0;">
            <label class="form-label">Teknisi / Vendor</label>
            <input type="text" name="technician" class="form-control" placeholder="Nama teknisi...">
          </div>
        </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('maintModal')">Batal</button>
        <button type="submit" class="btn btn-primary">
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
          Kirim Laporan
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
function openMaintModal(u) {
  document.getElementById('maintItemId').value    = u.item_id;
  document.getElementById('maintUnitId').value    = u.id;
  document.getElementById('maintUnitCode').textContent = u.full_code;
  document.getElementById('maintTitle').value     = '';
  openModal('maintModal');
}

function openEditModal(unit) {
  document.getElementById('editUnitId').value             = unit.id;
  document.getElementById('editUnitFullCode').textContent = unit.full_code;
  document.getElementById('editUnitStatus').value         = unit.status;
  document.getElementById('editUnitCondition').value      = unit.condition;
  document.getElementById('editUnitAcquired').value       = unit.acquired_date || '';
  document.getElementById('editUnitPrice').value          = unit.purchase_price || '';
  document.getElementById('editUnitSupplier').value       = unit.supplier || '';
  document.getElementById('editUnitLocation').value       = unit.location_id || '';
  document.getElementById('editUnitNotes').value          = unit.notes || '';
  openModal('editUnitModal');
}

</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
