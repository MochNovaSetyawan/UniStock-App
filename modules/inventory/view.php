<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("
    SELECT i.*, c.name as category_name, c.code as category_code, c.color as category_color,
           l.name as location_name, l.building, l.floor, l.room,
           u1.full_name as created_by_name, u2.full_name as updated_by_name
    FROM items i
    LEFT JOIN categories c ON i.category_id = c.id
    LEFT JOIN locations l ON i.location_id = l.id
    LEFT JOIN users u1 ON i.created_by = u1.id
    LEFT JOIN users u2 ON i.updated_by = u2.id
    WHERE i.id = ?
");
$stmt->execute([$id]);
$item = $stmt->fetch();
if (!$item) { flashMessage('error', 'Barang tidak ditemukan.'); header('Location: index.php'); exit; }

// Unit stats
$unitStats = [];
$usRows = $db->prepare("SELECT status, COUNT(*) as cnt FROM item_units WHERE item_id = ? GROUP BY status");
$usRows->execute([$id]);
foreach ($usRows->fetchAll() as $r) { $unitStats[$r['status']] = (int)$r['cnt']; }
$totalUnits  = array_sum($unitStats);
$catCode     = $item['category_code'] ?? '';

// Transaction history
$txHistory = $db->prepare("
    SELECT t.*, u.full_name as user_name
    FROM transactions t
    LEFT JOIN users u ON t.requested_by = u.id
    WHERE t.item_id = ?
    ORDER BY t.created_at DESC LIMIT 10
");
$txHistory->execute([$id]);
$transactions = $txHistory->fetchAll();

// Maintenance history
$maintHistory = $db->prepare("
    SELECT * FROM maintenance WHERE item_id = ? ORDER BY created_at DESC LIMIT 5
");
$maintHistory->execute([$id]);
$maintenance = $maintHistory->fetchAll();

$pageTitle = 'Detail Barang';
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title">
    <div class="breadcrumb">
      <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M9 18l6-6-6-6"/></svg>
      <a href="index.php">Inventaris</a>
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M9 18l6-6-6-6"/></svg>
      Detail
    </div>
    <h2><?= sanitize($item['name']) ?></h2>
    <p class="mono"><?= sanitize($item['code']) ?></p>
  </div>
  <div class="btn-group">
    <?php if (isAdmin()): ?>
    <a href="form.php?id=<?= $item['id'] ?>" class="btn btn-outline">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
      Edit
    </a>
    <a href="units.php?item_id=<?= $item['id'] ?>" class="btn btn-outline">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
      Kode Unit <?php if ($totalUnits): ?><span class="badge badge-secondary" style="margin-left:4px;"><?= $totalUnits ?></span><?php endif; ?>
    </a>
    <?php endif; ?>
    <a href="<?= APP_URL ?>/modules/transactions/form.php?item_id=<?= $item['id'] ?>" class="btn btn-primary">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
      Pinjam
    </a>
    <a href="<?= APP_URL ?>/modules/maintenance/form.php?item_id=<?= $item['id'] ?>" class="btn btn-outline">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37"/></svg>
      Lapor Kerusakan
    </a>
  </div>
</div>

<div class="grid-2" style="gap: 20px; align-items: start;">
  <div style="display: flex; flex-direction: column; gap: 20px;">

    <!-- Item Info -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">Informasi Barang</div>
        <div class="btn-group">
          <?= conditionBadge($item['condition']) ?>
          <?= statusBadge($item['status']) ?>
        </div>
      </div>
      <div class="card-body">
        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
          <!-- Image -->
          <div style="width: 180px; flex-shrink: 0;">
            <div class="item-image-box">
              <?php if ($item['image']): ?>
              <img src="<?= UPLOAD_URL . sanitize($item['image']) ?>" alt="<?= sanitize($item['name']) ?>">
              <?php else: ?>
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
              <?php endif; ?>
            </div>
          </div>
          <!-- Details -->
          <div style="flex: 1; min-width: 0;">
            <div class="detail-grid">
              <?php $fields = [
                ['Kode', $item['code']],
                ['Merek', $item['brand'] ?: '-'],
                ['Model', $item['model'] ?: '-'],
                ['Nomor Seri', $item['serial_number'] ?: '-'],
                ['Kategori', $item['category_name'] ?: '-'],
                ['Lokasi', ($item['location_name'] ?? '-') . ($item['building'] ? " ({$item['building']})" : '')],
                ['Satuan', $item['unit']],
              ];
              foreach ($fields as [$label, $val]): ?>
              <div class="detail-field">
                <label><?= $label ?></label>
                <span><?= sanitize($val) ?></span>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <?php if ($item['description']): ?>
        <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border);">
          <label class="form-label" style="display:block; margin-bottom: 6px;">Deskripsi</label>
          <p style="font-size: 0.85rem; color: var(--text-secondary); line-height: 1.6;"><?= nl2br(sanitize($item['description'])) ?></p>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Stock Info -->
    <div class="card">
      <div class="card-header"><div class="card-title">Status Stok</div></div>
      <div class="card-body">
        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
          <div style="text-align: center; flex: 1;">
            <div style="font-size: 2.5rem; font-weight: 800; color: <?= $item['quantity_available'] <= $item['min_stock'] ? 'var(--danger)' : 'var(--success)' ?>;">
              <?= $item['quantity_available'] ?>
            </div>
            <div style="font-size: 0.78rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.06em;">Tersedia</div>
          </div>
          <div style="text-align: center; flex: 1;">
            <div style="font-size: 2.5rem; font-weight: 800; color: var(--text-primary);"><?= $item['quantity'] ?></div>
            <div style="font-size: 0.78rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.06em;">Total</div>
          </div>
          <div style="text-align: center; flex: 1;">
            <div style="font-size: 2.5rem; font-weight: 800; color: var(--info);"><?= $item['quantity'] - $item['quantity_available'] ?></div>
            <div style="font-size: 0.78rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.06em;">Dipinjam</div>
          </div>
          <div style="text-align: center; flex: 1;">
            <div style="font-size: 2.5rem; font-weight: 800; color: var(--warning);"><?= $item['min_stock'] ?></div>
            <div style="font-size: 0.78rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.06em;">Min. Stok</div>
          </div>
        </div>
        <?php
        $pct = $item['quantity'] > 0 ? round(($item['quantity_available'] / $item['quantity']) * 100) : 0;
        $color = $pct > 50 ? 'var(--success)' : ($pct > 20 ? 'var(--warning)' : 'var(--danger)');
        ?>
        <div style="margin-top: 16px;">
          <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 0.78rem; color: var(--text-muted);">
            <span>Ketersediaan</span>
            <span><?= $pct ?>%</span>
          </div>
          <div style="height: 8px; background: var(--bg-elevated); border-radius: 4px; overflow: hidden;">
            <div style="width: <?= $pct ?>%; height: 100%; background: <?= $color ?>; border-radius: 4px; transition: width 0.8s;"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Unit Status Summary -->
    <?php if ($totalUnits > 0):
      $pfx = ($item['category_code'] ?? '') ? $item['category_code'].'-'.$item['code'] : $item['code'];
    ?>
    <div class="card">
      <div class="card-header">
        <div class="card-title">Status Unit Fisik</div>
        <a href="units.php?item_id=<?= $item['id'] ?>" class="btn btn-ghost btn-sm">Kelola Semua</a>
      </div>
      <div class="card-body">
        <?php
        $uDef = [
          'available'   =>['Tersedia',    'var(--success)'],
          'reserved'    =>['Direservasi', 'var(--warning)'],
          'borrowed'    =>['Dipinjam',    'var(--info)'],
          'maintenance' =>['Maintenance', '#f59e0b'],
          'damaged'     =>['Rusak',       'var(--danger)'],
          'disposed'    =>['Dibuang',     'var(--text-muted)'],
          'lost'        =>['Hilang',      'var(--text-muted)'],
        ];
        ?>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:14px;">
          <?php foreach ($uDef as $sk => [$sl, $sc]): if (!($unitStats[$sk] ?? 0)) continue; ?>
          <a href="units.php?item_id=<?= $item['id'] ?>&status=<?= $sk ?>"
             style="text-align:center;padding:10px 6px;background:var(--bg-elevated);border-radius:var(--radius-sm);text-decoration:none;transition:background 0.15s;">
            <div style="font-size:1.4rem;font-weight:800;color:<?= $sc ?>;"><?= $unitStats[$sk] ?></div>
            <div style="font-size:0.72rem;color:var(--text-muted);margin-top:2px;"><?= $sl ?></div>
          </a>
          <?php endforeach; ?>
        </div>
        <div style="font-size:0.78rem;color:var(--text-muted);">
          Format: <code style="color:var(--accent-light);"><?= sanitize($pfx) ?>-U001</code> &mdash;
          <code style="color:var(--accent-light);"><?= sanitize($pfx) ?>-U<?= str_pad($totalUnits,3,'0',STR_PAD_LEFT) ?></code>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Purchase Info -->
    <div class="card">
      <div class="card-header"><div class="card-title">Info Pengadaan</div></div>
      <div class="card-body">
        <div class="detail-grid">
          <div class="detail-field"><label>Tanggal Beli</label><span><?= formatDate($item['purchase_date']) ?></span></div>
          <div class="detail-field"><label>Harga Beli</label><span><?= formatRupiah($item['purchase_price']) ?></span></div>
          <div class="detail-field"><label>Supplier</label><span><?= sanitize($item['supplier'] ?: '-') ?></span></div>
          <div class="detail-field"><label>Garansi Hingga</label>
            <span <?= $item['warranty_expiry'] && $item['warranty_expiry'] < date('Y-m-d') ? "style='color:var(--danger)'" : '' ?>>
              <?= formatDate($item['warranty_expiry']) ?>
              <?php if ($item['warranty_expiry'] && $item['warranty_expiry'] < date('Y-m-d')): ?><span class="badge badge-danger" style="margin-left:6px;">Expired</span><?php endif; ?>
            </span>
          </div>
          <div class="detail-field"><label>Ditambah oleh</label><span><?= sanitize($item['created_by_name'] ?? '-') ?></span></div>
          <div class="detail-field"><label>Terakhir diupdate</label><span><?= formatDateTime($item['updated_at']) ?></span></div>
        </div>
        <?php if ($item['notes']): ?>
        <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border);">
          <label class="form-label" style="display: block; margin-bottom: 6px;">Catatan</label>
          <p style="font-size: 0.85rem; color: var(--text-secondary);"><?= nl2br(sanitize($item['notes'])) ?></p>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div style="display: flex; flex-direction: column; gap: 20px;">
    <!-- Transaction History -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">Riwayat Transaksi</div>
        <a href="<?= APP_URL ?>/modules/transactions/index.php?item_id=<?= $item['id'] ?>" class="btn btn-ghost btn-sm">Semua</a>
      </div>
      <?php if (empty($transactions)): ?>
      <div class="empty-state" style="padding: 30px;">
        <p>Belum ada transaksi</p>
      </div>
      <?php else: ?>
      <div class="activity-list" style="padding: 0 20px;">
        <?php foreach ($transactions as $tx):
          $typeColors = ['borrow'=>'blue','return'=>'green','transfer'=>'amber','dispose'=>'red'];
          $typeLabels = ['borrow'=>'Dipinjam','return'=>'Dikembalikan','transfer'=>'Transfer','dispose'=>'Dibuang'];
        ?>
        <div class="activity-item">
          <div class="activity-dot <?= $typeColors[$tx['type']] ?? 'blue' ?>"></div>
          <div class="activity-content">
            <div class="activity-title"><?= $typeLabels[$tx['type']] ?? $tx['type'] ?> &bull; <?= sanitize($tx['borrower_name'] ?? $tx['user_name'] ?? '-') ?></div>
            <div class="activity-meta"><?= formatDateTime($tx['created_at']) ?> &bull; <?= statusBadge($tx['status']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Maintenance History -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">Riwayat Pemeliharaan</div>
        <a href="<?= APP_URL ?>/modules/maintenance/index.php?item_id=<?= $item['id'] ?>" class="btn btn-ghost btn-sm">Semua</a>
      </div>
      <?php if (empty($maintenance)): ?>
      <div class="empty-state" style="padding: 30px;">
        <p>Belum ada pemeliharaan</p>
      </div>
      <?php else: ?>
      <div class="activity-list" style="padding: 0 20px;">
        <?php foreach ($maintenance as $m): ?>
        <div class="activity-item">
          <div class="activity-dot <?= $m['status'] === 'completed' ? 'green' : ($m['priority'] === 'critical' ? 'red' : 'amber') ?>"></div>
          <div class="activity-content">
            <div class="activity-title"><?= sanitize($m['title']) ?></div>
            <div class="activity-meta"><?= formatDate($m['created_at']) ?> &bull; <?= statusBadge($m['status']) ?> &bull; <?= priorityBadge($m['priority']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
