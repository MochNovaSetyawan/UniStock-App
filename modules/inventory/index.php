<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$pageTitle = 'Inventaris Barang';
$db = getDB();

// Filters
$search   = trim($_GET['search'] ?? '');
$catId    = (int)($_GET['category'] ?? 0);
$locId    = (int)($_GET['location'] ?? 0);
$cond     = $_GET['condition'] ?? '';
$status   = $_GET['status'] ?? 'active';
$filter   = $_GET['filter'] ?? '';
$perPage  = (int)getSetting('items_per_page', 15);
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $perPage;

// Build query
$where = ['1=1'];
$params = [];

if ($search) {
    $where[] = '(i.name LIKE ? OR i.code LIKE ? OR i.brand LIKE ? OR i.serial_number LIKE ?)';
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
}
if ($catId) { $where[] = 'i.category_id = ?'; $params[] = $catId; }
if ($locId) { $where[] = 'i.location_id = ?'; $params[] = $locId; }
if ($cond)  { $where[] = 'i.condition = ?';   $params[] = $cond; }
if ($status){ $where[] = 'i.status = ?';      $params[] = $status; }
if ($filter === 'low_stock') { $where[] = 'i.quantity_available <= i.min_stock'; }

$whereStr = implode(' AND ', $where);

// Count
$countStmt = $db->prepare("SELECT COUNT(*) FROM items i WHERE $whereStr");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

// Fetch
$stmt = $db->prepare("
    SELECT i.*, c.name as category_name, c.code as category_code, c.color as category_color,
           l.name as location_name, l.building as location_building,
           (SELECT COUNT(*) FROM item_units u WHERE u.item_id = i.id AND u.status = 'available') as units_available,
           (SELECT COUNT(*) FROM item_units u WHERE u.item_id = i.id) as units_total
    FROM items i
    LEFT JOIN categories c ON i.category_id = c.id
    LEFT JOIN locations l ON i.location_id = l.id
    WHERE $whereStr
    ORDER BY i.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [$perPage, $offset]));
$items = $stmt->fetchAll();

// Dropdown data
$categories = $db->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$locations  = $db->query("SELECT id, name, building FROM locations ORDER BY name")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title">
    <div class="breadcrumb">
      <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M9 18l6-6-6-6"/></svg>
      Inventaris
    </div>
    <h2>Barang &amp; Aset</h2>
    <p>Kelola seluruh barang dan aset universitas</p>
  </div>
  <?php if (isAdmin()): ?>
  <div class="btn-group">
    <button type="button" class="btn btn-primary" onclick="openModal('choiceModal')">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M12 5v14M5 12h14"/></svg>
      Tambah Barang
    </button>
    <a href="<?= APP_URL ?>/modules/reports/index.php?type=inventory" class="btn btn-outline">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
      Export
    </a>
  </div>
  <?php endif; ?>
</div>

<!-- Quick Stats Row -->
<div style="display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap;">
  <?php
  $qStats = [
    ['label' => 'Semua Aktif', 'val' => $db->query("SELECT COUNT(*) FROM items WHERE status='active'")->fetchColumn(), 'filter' => '?status=active', 'active' => $status === 'active'],
    ['label' => 'Stok Menipis', 'val' => $db->query("SELECT COUNT(*) FROM items WHERE quantity_available <= min_stock AND status='active'")->fetchColumn(), 'filter' => '?filter=low_stock&status=active', 'active' => $filter === 'low_stock'],
    ['label' => 'Nonaktif', 'val' => $db->query("SELECT COUNT(*) FROM items WHERE status='inactive'")->fetchColumn(), 'filter' => '?status=inactive', 'active' => $status === 'inactive'],
    ['label' => 'Dibuang', 'val' => $db->query("SELECT COUNT(*) FROM items WHERE status='disposed'")->fetchColumn(), 'filter' => '?status=disposed', 'active' => $status === 'disposed'],
  ];
  foreach ($qStats as $qs):
  ?>
  <a href="<?= $qs['filter'] ?>" style="padding: 8px 16px; border-radius: var(--radius-sm); border: 1px solid <?= $qs['active'] ? 'var(--accent)' : 'var(--border)' ?>; background: <?= $qs['active'] ? 'var(--accent-glow)' : 'var(--bg-card)' ?>; color: <?= $qs['active'] ? 'var(--accent-light)' : 'var(--text-secondary)' ?>; font-size: 0.82rem; display: flex; align-items: center; gap: 8px; transition: all 0.2s;">
    <?= sanitize($qs['label']) ?> <strong><?= $qs['val'] ?></strong>
  </a>
  <?php endforeach; ?>
</div>

<div class="card">
  <!-- Toolbar -->
  <div class="table-toolbar">
    <form method="GET" style="display: contents;">
      <div class="search-box">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Cari barang, kode, brand...">
      </div>
      <select name="category" class="filter-select" onchange="this.form.submit()">
        <option value="">Semua Kategori</option>
        <?php foreach ($categories as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $catId == $c['id'] ? 'selected' : '' ?>><?= sanitize($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="location" class="filter-select" onchange="this.form.submit()">
        <option value="">Semua Lokasi</option>
        <?php foreach ($locations as $l): ?>
        <option value="<?= $l['id'] ?>" <?= $locId == $l['id'] ? 'selected' : '' ?>><?= sanitize($l['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="condition" class="filter-select" onchange="this.form.submit()">
        <option value="">Semua Kondisi</option>
        <option value="good" <?= $cond==='good'?'selected':'' ?>>Baik</option>
        <option value="fair" <?= $cond==='fair'?'selected':'' ?>>Cukup Baik</option>
        <option value="poor" <?= $cond==='poor'?'selected':'' ?>>Kurang Baik</option>
        <option value="damaged" <?= $cond==='damaged'?'selected':'' ?>>Rusak</option>
        <option value="lost" <?= $cond==='lost'?'selected':'' ?>>Hilang</option>
      </select>
      <input type="hidden" name="status" value="<?= sanitize($status) ?>">
      <button type="submit" class="btn btn-outline btn-sm">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        Cari
      </button>
      <?php if ($search || $catId || $locId || $cond): ?>
      <a href="?" class="btn btn-ghost btn-sm">Reset</a>
      <?php endif; ?>
    </form>
    <div style="margin-left: auto; font-size: 0.78rem; color: var(--text-muted);">
      <?= number_format($total) ?> barang ditemukan
    </div>
  </div>

  <!-- Table -->
  <div class="table-container">
    <?php if (empty($items)): ?>
    <div class="empty-state">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
      <h3>Tidak ada barang ditemukan</h3>
      <p>Coba ubah filter pencarian atau tambah barang baru</p>
      <?php if (isAdmin()): ?>
      <a href="form.php" class="btn btn-primary">Tambah Barang Pertama</a>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Barang</th>
          <th>Kategori</th>
          <th>Lokasi</th>
          <th>Stok</th>
          <th>Kondisi</th>
          <th>Status</th>
          <th style="text-align:right;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item): ?>
        <tr data-href="view.php?id=<?= $item['id'] ?>">
          <td>
            <div style="display: flex; align-items: center; gap: 12px;">
              <div style="width: 40px; height: 40px; background: var(--bg-elevated); border-radius: var(--radius-sm); overflow: hidden; flex-shrink: 0; display: flex; align-items: center; justify-content: center;">
                <?php if ($item['image']): ?>
                <img src="<?= UPLOAD_URL . sanitize($item['image']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="color:var(--text-disabled);"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
                <?php endif; ?>
              </div>
              <div>
                <div class="table-item-name"><?= sanitize($item['name']) ?></div>
                <div class="table-item-code"><?= sanitize($item['code']) ?><?= $item['brand'] ? ' &bull; ' . sanitize($item['brand']) : '' ?></div>
              </div>
            </div>
          </td>
          <td>
            <?php if ($item['category_name']): ?>
            <span style="display:inline-flex;align-items:center;gap:4px;">
              <span style="width:6px;height:6px;border-radius:50%;background:<?= sanitize($item['category_color']) ?>;flex-shrink:0;"></span>
              <?= sanitize($item['category_name']) ?>
            </span>
            <?php else: ?>
            <span class="text-muted">-</span>
            <?php endif; ?>
          </td>
          <td><?= sanitize($item['location_name'] ?? '-') ?></td>
          <td>
            <div style="font-weight: 500; <?= $item['quantity_available'] <= $item['min_stock'] ? 'color: var(--danger)' : '' ?>">
              <?= $item['quantity_available'] ?> / <?= $item['quantity'] ?>
            </div>
            <?php if ($item['units_total'] > 0): ?>
            <a href="units.php?item_id=<?= $item['id'] ?>" onclick="event.stopPropagation()"
               style="font-size:0.7rem;color:var(--accent-light);text-decoration:none;display:inline-flex;align-items:center;gap:3px;margin-top:2px;">
              <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
              <?= $item['units_available'] ?>/<?= $item['units_total'] ?> unit
            </a>
            <?php else: ?>
            <div style="font-size: 0.72rem; color: var(--text-muted);"><?= sanitize($item['unit']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= conditionBadge($item['condition']) ?></td>
          <td><?= statusBadge($item['status']) ?></td>
          <td style="text-align: right;">
            <div class="btn-group" style="justify-content: flex-end;">
              <a href="view.php?id=<?= $item['id'] ?>" class="btn btn-ghost btn-icon btn-sm" title="Detail" onclick="event.stopPropagation()">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              </a>
              <?php if (isAdmin()): ?>
              <a href="form.php?id=<?= $item['id'] ?>" class="btn btn-ghost btn-icon btn-sm" title="Edit" onclick="event.stopPropagation()">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
              </a>
              <form method="POST" action="delete.php" style="display:inline;" onclick="event.stopPropagation()">
                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                <button type="button" class="btn btn-ghost btn-icon btn-sm" title="Hapus" style="color: var(--danger);"
                  onclick="confirmDelete('Hapus barang <?= sanitize(addslashes($item['name'])) ?>?', this.closest('form'))">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <!-- Pagination -->
  <?php echo paginate($total, $perPage, $page, '?' . http_build_query(array_filter(['search'=>$search,'category'=>$catId,'location'=>$locId,'condition'=>$cond,'status'=>$status]))); ?>
</div>

<?php if (isAdmin()): ?>
<!-- Modal Pilihan Tambah -->
<div class="modal-overlay" id="choiceModal" onclick="if(event.target===this)closeModal('choiceModal')">
  <div class="modal" style="max-width:480px;width:100%;">
    <div class="modal-header">
      <div class="modal-title">Tambah ke Inventaris</div>
      <button class="modal-close" onclick="closeModal('choiceModal')">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="modal-body" style="display:flex;flex-direction:column;gap:12px;padding:24px;">
      <a href="form.php" style="display:flex;align-items:center;gap:16px;padding:18px 20px;background:var(--bg-elevated);border:1px solid var(--border);border-radius:var(--radius);text-decoration:none;transition:border-color 0.2s;" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
        <div style="width:44px;height:44px;background:rgba(99,102,241,0.15);border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="var(--accent-light)"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
        </div>
        <div>
          <div style="font-weight:600;color:var(--text-primary);margin-bottom:3px;">Tambah Barang Baru</div>
          <div style="font-size:0.8rem;color:var(--text-muted);">Daftarkan barang atau aset baru ke inventaris</div>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="var(--text-muted)" style="margin-left:auto;flex-shrink:0;"><path d="M9 18l6-6-6-6"/></svg>
      </a>

      <a href="restock.php" style="display:flex;align-items:center;gap:16px;padding:18px 20px;background:var(--bg-elevated);border:1px solid var(--border);border-radius:var(--radius);text-decoration:none;transition:border-color 0.2s;" onmouseover="this.style.borderColor='var(--success)'" onmouseout="this.style.borderColor='var(--border)'">
        <div style="width:44px;height:44px;background:rgba(34,197,94,0.12);border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="var(--success)"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>
        </div>
        <div>
          <div style="font-weight:600;color:var(--text-primary);margin-bottom:3px;">Tambah Unit</div>
          <div style="font-size:0.8rem;color:var(--text-muted);">Tambah unit baru ke barang yang sudah terdaftar</div>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="var(--text-muted)" style="margin-left:auto;flex-shrink:0;"><path d="M9 18l6-6-6-6"/></svg>
      </a>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
