<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole('superadmin', 'admin');

$pageTitle = 'Laporan';
$db = getDB();

$type     = $_GET['type'] ?? 'inventory';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo   = $_GET['date_to']   ?? date('Y-m-d');

$data = [];

switch ($type) {
    case 'inventory':
        $data = $db->query("
            SELECT i.code, i.name, c.name as cat, c.color as cat_color,
                   l.name as loc,
                   i.quantity, i.quantity_available,
                   i.`condition`, i.status,
                   i.purchase_date, i.purchase_price
            FROM items i
            LEFT JOIN categories c ON i.category_id = c.id
            LEFT JOIN locations l  ON i.location_id  = l.id
            ORDER BY c.name, i.name
        ")->fetchAll();
        break;

    case 'transactions':
        $stmt = $db->prepare("
            SELECT t.code, i.name as item_name, i.code as item_code,
                   t.type, t.borrower_name, t.quantity,
                   t.borrow_date, t.expected_return, t.status
            FROM transactions t
            JOIN items i ON t.item_id = i.id
            WHERE DATE(t.created_at) BETWEEN ? AND ?
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $data = $stmt->fetchAll();
        break;

    case 'maintenance':
        $stmt = $db->prepare("
            SELECT m.code, i.name as item_name, i.code as item_code,
                   m.title, m.type, m.priority,
                   m.technician, m.cost, m.status, m.created_at
            FROM maintenance m
            JOIN items i ON m.item_id = i.id
            WHERE DATE(m.created_at) BETWEEN ? AND ?
            ORDER BY m.created_at DESC
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $data = $stmt->fetchAll();
        break;
}

// Summary stats
$sumStats = [
    'items_total'      => $db->query("SELECT COUNT(*) FROM items WHERE status='active'")->fetchColumn(),
    'items_value'      => $db->query("SELECT SUM(purchase_price * quantity) FROM items WHERE status='active'")->fetchColumn(),
    'borrows_month'    => $db->query("SELECT COUNT(*) FROM transactions WHERE type='borrow' AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn(),
    'returns_month'    => $db->query("SELECT COUNT(*) FROM transactions WHERE type='borrow' AND status='returned' AND MONTH(created_at)=MONTH(NOW())")->fetchColumn(),
    'overdue_now'      => $db->query("SELECT COUNT(*) FROM transactions WHERE type='borrow' AND status='active' AND expected_return < NOW()")->fetchColumn(),
    'maintenance_cost' => $db->query("SELECT SUM(cost) FROM maintenance WHERE status='completed'")->fetchColumn(),
];

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title">
    <div class="breadcrumb">
      <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M9 18l6-6-6-6"/></svg>
      Laporan
    </div>
    <h2>Laporan Inventaris</h2>
    <p>Rekap dan analisis data inventaris universitas</p>
  </div>
  <button onclick="window.print()" class="btn btn-outline">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
    Cetak
  </button>
</div>

<?php
// Format angka kompak untuk stat card
function fmtCompact($val) {
    if (!$val) return '—';
    if ($val >= 1000000000) return 'Rp ' . number_format($val/1000000000, 1, ',', '.') . ' M';
    if ($val >= 1000000)    return 'Rp ' . number_format($val/1000000,    1, ',', '.') . ' Jt';
    if ($val >= 1000)       return 'Rp ' . number_format($val/1000,       0, ',', '.') . ' Rb';
    return 'Rp ' . number_format($val, 0, ',', '.');
}
$cards = [
    ['color'=>'rgba(99,102,241,0.15)',  'stroke'=>'var(--accent-light)', 'tag'=>'Inventaris',
     'icon'=>'<path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/>',
     'value'=> number_format($sumStats['items_total']),
     'label'=>'Barang Aktif', 'sub'=>''],

    ['color'=>'rgba(34,197,94,0.15)',   'stroke'=>'var(--success)',      'tag'=>'Nilai Aset',
     'icon'=>'<path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
     'value'=> fmtCompact($sumStats['items_value']),
     'label'=>'Total Nilai Aset', 'sub'=>''],

    ['color'=>'rgba(59,130,246,0.15)',  'stroke'=>'var(--info)',         'tag'=>'Bulan Ini',
     'icon'=>'<path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>',
     'value'=> $sumStats['borrows_month'],
     'label'=>'Dipinjam', 'sub'=>$sumStats['returns_month'].' kembali'],

    ['color'=>'rgba(239,68,68,0.15)',   'stroke'=>'var(--danger)',       'tag'=>'Overdue',
     'icon'=>'<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>',
     'value'=> $sumStats['overdue_now'],
     'label'=>'Terlambat Kembali', 'sub'=>'',
     'danger'=> $sumStats['overdue_now'] > 0],

    ['color'=>'rgba(245,158,11,0.15)',  'stroke'=>'var(--warning)',      'tag'=>'Maintenance',
     'icon'=>'<path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
     'value'=> fmtCompact($sumStats['maintenance_cost']),
     'label'=>'Biaya Pemeliharaan', 'sub'=>''],
];
?>
<!-- Summary Cards -->
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:24px;">
<?php foreach ($cards as $c): ?>
  <div class="card" style="margin:0;<?= !empty($c['danger']) ? 'border-color:rgba(239,68,68,0.3);' : '' ?>">
    <div class="card-body" style="padding:16px 18px;">
      <!-- Icon + tag -->
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
        <div style="width:34px;height:34px;border-radius:9px;background:<?= $c['color'] ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="<?= $c['stroke'] ?>"><?= $c['icon'] ?></svg>
        </div>
        <span style="font-size:0.7rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;"><?= $c['tag'] ?></span>
      </div>
      <!-- Value -->
      <div style="font-size:1.5rem;font-weight:800;line-height:1.1;color:<?= !empty($c['danger']) ? 'var(--danger)' : 'var(--text-primary)' ?>;">
        <?= $c['value'] ?>
      </div>
      <!-- Label + sub -->
      <div style="margin-top:5px;font-size:0.75rem;color:var(--text-muted);">
        <?= $c['label'] ?>
        <?php if ($c['sub']): ?>
        &nbsp;<span style="color:var(--success);font-weight:600;"><?= $c['sub'] ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>

<!-- Filter & Table -->
<div class="card">
  <!-- Toolbar -->
  <div class="table-toolbar" style="flex-wrap:wrap;gap:10px;">
    <form method="GET" style="display:contents;">

      <!-- Tab jenis laporan -->
      <div style="display:flex;background:var(--bg-elevated);border-radius:8px;padding:3px;gap:2px;">
        <?php
        $tabIcons = [
          'inventory'    => '<path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/>',
          'transactions' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>',
          'maintenance'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
        ];
        foreach (['inventory' => 'Inventaris', 'transactions' => 'Transaksi', 'maintenance' => 'Pemeliharaan'] as $t => $l):
          $active = $type === $t;
        ?>
        <a href="?type=<?= $t ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>"
           style="display:flex;align-items:center;gap:6px;padding:6px 14px;border-radius:6px;font-size:0.8rem;font-weight:600;text-decoration:none;transition:all 0.15s;
                  <?= $active ? 'background:var(--accent);color:#fff;' : 'color:var(--text-muted);' ?>">
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><?= $tabIcons[$t] ?></svg>
          <?= $l ?>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- Filter tanggal (transaksi & pemeliharaan) -->
      <?php if ($type !== 'inventory'): ?>
      <input type="hidden" name="type" value="<?= $type ?>">
      <div style="display:flex;align-items:center;gap:8px;background:var(--bg-elevated);border-radius:8px;padding:4px 10px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="color:var(--text-muted);flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        <input type="date" name="date_from" class="filter-select" value="<?= $dateFrom ?>"
               style="background:transparent;border:none;padding:0;font-size:0.8rem;color:var(--text-primary);outline:none;">
        <span style="color:var(--text-muted);font-size:0.78rem;">—</span>
        <input type="date" name="date_to" class="filter-select" value="<?= $dateTo ?>"
               style="background:transparent;border:none;padding:0;font-size:0.8rem;color:var(--text-primary);outline:none;">
        <button type="submit" class="btn btn-primary btn-sm" style="padding:4px 12px;font-size:0.78rem;">Terapkan</button>
      </div>
      <?php endif; ?>

      <!-- Jumlah data -->
      <div style="margin-left:auto;display:flex;align-items:center;gap:6px;">
        <span style="font-size:0.75rem;color:var(--text-muted);">
          <?= count($data) ?> baris
        </span>
      </div>

    </form>
  </div>

  <!-- Table -->
  <div class="table-container">
    <?php if (empty($data)): ?>
    <div class="empty-state">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      <h3>Tidak ada data</h3>
    </div>

    <?php elseif ($type === 'inventory'): ?>
    <table class="table">
      <thead>
        <tr>
          <th>Barang</th>
          <th>Kategori</th>
          <th>Lokasi</th>
          <th>Stok</th>
          <th>Kondisi</th>
          <th>Status</th>
          <th>Tgl Beli</th>
          <th>Harga Beli</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($data as $row): ?>
        <tr>
          <td>
            <div class="table-item-name"><?= sanitize($row['name']) ?></div>
            <div class="table-item-code"><?= sanitize($row['code']) ?></div>
          </td>
          <td>
            <?php if ($row['cat']): ?>
            <span style="display:inline-flex;align-items:center;gap:5px;">
              <span style="width:7px;height:7px;border-radius:50%;background:<?= sanitize($row['cat_color'] ?? '#6366f1') ?>;flex-shrink:0;"></span>
              <?= sanitize($row['cat']) ?>
            </span>
            <?php else: ?>
            <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td><?= sanitize($row['loc'] ?? '—') ?></td>
          <td>
            <span style="font-weight:500;<?= $row['quantity_available'] == 0 ? 'color:var(--danger)' : ($row['quantity_available'] < 3 ? 'color:var(--warning)' : '') ?>">
              <?= $row['quantity_available'] ?>
            </span>
            <span style="color:var(--text-muted);"> / <?= $row['quantity'] ?></span>
          </td>
          <td><?= conditionBadge($row['condition']) ?></td>
          <td><?= statusBadge($row['status']) ?></td>
          <td class="td-meta"><?= formatDate($row['purchase_date']) ?></td>
          <td><?= formatRupiah($row['purchase_price']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php elseif ($type === 'transactions'):
      $typeLabels = ['borrow' => 'Pinjam', 'return' => 'Kembali', 'transfer' => 'Transfer', 'dispose' => 'Buang'];
    ?>
    <table class="table">
      <thead>
        <tr>
          <th>Kode</th>
          <th>Barang</th>
          <th>Tipe</th>
          <th>Peminjam</th>
          <th>Jml</th>
          <th>Tgl Pinjam</th>
          <th>Batas Kembali</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($data as $row): ?>
        <tr>
          <td><span class="mono table-item-code" style="color:var(--accent-light);"><?= sanitize($row['code']) ?></span></td>
          <td>
            <div class="table-item-name"><?= sanitize($row['item_name']) ?></div>
            <div class="table-item-code"><?= sanitize($row['item_code']) ?></div>
          </td>
          <td><span class="badge badge-secondary"><?= $typeLabels[$row['type']] ?? sanitize($row['type']) ?></span></td>
          <td><?= sanitize($row['borrower_name'] ?? '—') ?></td>
          <td class="td-meta"><?= $row['quantity'] ?></td>
          <td class="td-meta"><?= formatDate($row['borrow_date']) ?></td>
          <td class="td-meta"><?= formatDate($row['expected_return']) ?></td>
          <td><?= statusBadge($row['status']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php elseif ($type === 'maintenance'):
      $typeLabels = ['preventive' => 'Preventif', 'corrective' => 'Korektif', 'inspection' => 'Inspeksi'];
    ?>
    <table class="table">
      <thead>
        <tr>
          <th>Kode</th>
          <th>Barang</th>
          <th>Judul</th>
          <th>Tipe</th>
          <th>Prioritas</th>
          <th>Teknisi</th>
          <th>Biaya</th>
          <th>Status</th>
          <th>Tgl</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($data as $row): ?>
        <tr>
          <td><span class="mono table-item-code" style="color:var(--accent-light);"><?= sanitize($row['code']) ?></span></td>
          <td>
            <div class="table-item-name"><?= sanitize($row['item_name']) ?></div>
            <div class="table-item-code"><?= sanitize($row['item_code']) ?></div>
          </td>
          <td><?= sanitize($row['title']) ?></td>
          <td><span class="badge badge-secondary"><?= $typeLabels[$row['type']] ?? sanitize($row['type']) ?></span></td>
          <td><?= priorityBadge($row['priority']) ?></td>
          <td><?= sanitize($row['technician'] ?? '—') ?></td>
          <td><?= formatRupiah($row['cost']) ?></td>
          <td><?= statusBadge($row['status']) ?></td>
          <td class="td-meta"><?= formatDate($row['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
