<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pageTitle = 'Dashboard';
$stats = getDashboardStats();
$recentTransactions = getRecentTransactions(8);
$categoryData = getItemsByCategory();

// Recent maintenance
$db = getDB();
$recentMaintenance = $db->query("
    SELECT m.*, i.name as item_name, i.code as item_code
    FROM maintenance m
    JOIN items i ON m.item_id = i.id
    ORDER BY m.created_at DESC LIMIT 5
")->fetchAll();

// Low stock items
$lowStockItems = $db->query("
    SELECT i.*, c.name as category_name
    FROM items i
    LEFT JOIN categories c ON i.category_id = c.id
    WHERE i.quantity_available <= i.min_stock AND i.status = 'active'
    ORDER BY i.quantity_available ASC LIMIT 5
")->fetchAll();

// Overdue transactions
$overdueItems = $db->query("
    SELECT t.*, i.name as item_name, i.code as item_code
    FROM transactions t
    JOIN items i ON t.item_id = i.id
    WHERE t.type='borrow' AND t.status='active' AND t.expected_return < NOW()
    ORDER BY t.expected_return ASC LIMIT 5
")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
  <div class="page-title">
    <div class="breadcrumb">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      Dashboard
    </div>
    <h2>Selamat Datang, <?= sanitize(explode(' ', $currentUser['full_name'])[0]) ?> 👋</h2>
    <p>Berikut ringkasan inventaris universitas hari ini, <?= date('d F Y') ?></p>
  </div>
  <?php if (isAdmin()): ?>
  <div class="btn-group">
    <a href="<?= APP_URL ?>/modules/inventory/form.php" class="btn btn-primary">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M12 5v14M5 12h14"/></svg>
      Tambah Barang
    </a>
    <a href="<?= APP_URL ?>/modules/transactions/form.php" class="btn btn-outline">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
      Transaksi Baru
    </a>
  </div>
  <?php endif; ?>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon indigo">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
    </div>
    <div class="stat-info">
      <div class="stat-value"><?= number_format($stats['total_items']) ?></div>
      <div class="stat-label">Total Barang</div>
      <div class="stat-change"><?= number_format($stats['total_categories']) ?> kategori</div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon green">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
    </div>
    <div class="stat-info">
      <div class="stat-value"><?= number_format($stats['total_locations']) ?></div>
      <div class="stat-label">Lokasi</div>
      <div class="stat-change">Gedung & ruangan</div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon blue">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
    </div>
    <div class="stat-info">
      <div class="stat-value"><?= number_format($stats['active_borrows']) ?></div>
      <div class="stat-label">Sedang Dipinjam</div>
      <div class="stat-change <?= $stats['overdue_borrows'] > 0 ? 'down' : '' ?>">
        <?php if ($stats['overdue_borrows'] > 0): ?>
          &#9650; <?= $stats['overdue_borrows'] ?> terlambat
        <?php else: ?>
          Semua tepat waktu
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ($stats['pending_borrows'] > 0): ?>
  <div class="stat-card" style="border-color:rgba(245,158,11,0.3);">
    <div class="stat-icon amber">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </div>
    <div class="stat-info">
      <div class="stat-value" style="color:var(--warning);"><?= number_format($stats['pending_borrows']) ?></div>
      <div class="stat-label">Menunggu Persetujuan</div>
      <div class="stat-change down">Perlu ditindaklanjuti</div>
    </div>
  </div>
  <?php endif; ?>

  <div class="stat-card">
    <div class="stat-icon amber">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
    </div>
    <div class="stat-info">
      <div class="stat-value"><?= number_format($stats['pending_maintenance']) ?></div>
      <div class="stat-label">Pemeliharaan</div>
      <div class="stat-change">Pending & diproses</div>
    </div>
  </div>

  <?php if ($stats['low_stock_items'] > 0): ?>
  <div class="stat-card" style="border-color: rgba(239,68,68,0.3);">
    <div class="stat-icon red">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
    </div>
    <div class="stat-info">
      <div class="stat-value" style="color: var(--danger);"><?= number_format($stats['low_stock_items']) ?></div>
      <div class="stat-label">Stok Menipis</div>
      <div class="stat-change down">Perlu perhatian</div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (isAdmin()): ?>
  <div class="stat-card">
    <div class="stat-icon purple">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
    </div>
    <div class="stat-info">
      <div class="stat-value"><?= number_format($stats['total_users']) ?></div>
      <div class="stat-label">Pengguna Aktif</div>
      <div class="stat-change up">Sistem berjalan</div>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Alerts -->
<?php if (!empty($overdueItems)): ?>
<div class="alert alert-danger" style="margin-bottom: 20px;">
  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
  <div class="alert-content">
    <strong><?= count($overdueItems) ?> peminjaman terlambat!</strong> Segera tindak lanjuti.
    <a href="<?= APP_URL ?>/modules/transactions/index.php?status=overdue" style="margin-left: 8px; text-decoration: underline;">Lihat detail</a>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($lowStockItems)): ?>
<div class="alert alert-warning" style="margin-bottom: 20px;">
  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
  <div class="alert-content">
    <strong><?= count($lowStockItems) ?> barang dengan stok menipis.</strong> Pertimbangkan untuk melakukan pengadaan.
    <a href="<?= APP_URL ?>/modules/inventory/index.php?filter=low_stock" style="margin-left: 8px; text-decoration: underline;">Lihat barang</a>
  </div>
</div>
<?php endif; ?>

<!-- Main Content Grid -->
<div class="dashboard-grid">
  <!-- Left Column -->
  <div>
    <!-- Recent Transactions -->
    <div class="card mb-20">
      <div class="card-header">
        <div class="card-title">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
          Transaksi Terkini
        </div>
        <a href="<?= APP_URL ?>/modules/transactions/index.php" class="btn btn-ghost btn-sm">Lihat semua</a>
      </div>
      <?php if (empty($recentTransactions)): ?>
      <div class="empty-state">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
        <h3>Belum ada transaksi</h3>
        <p>Transaksi peminjaman akan muncul di sini</p>
      </div>
      <?php else: ?>
      <div class="table-container">
        <table class="table">
          <thead>
            <tr>
              <th>Kode / Barang</th>
              <th>Tipe</th>
              <th>Peminjam</th>
              <th>Status</th>
              <th>Tgl</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentTransactions as $t): ?>
            <tr data-href="<?= APP_URL ?>/modules/transactions/view.php?id=<?= $t['id'] ?>">
              <td>
                <div class="table-item-name"><?= sanitize($t['item_name']) ?></div>
                <div class="table-item-code"><?= sanitize($t['code']) ?></div>
              </td>
              <td><?php
                $typeLabels = ['borrow'=>'Pinjam','return'=>'Kembali','transfer'=>'Transfer','dispose'=>'Buang'];
                echo "<span class='badge badge-secondary'>" . ($typeLabels[$t['type']] ?? $t['type']) . "</span>";
              ?></td>
              <td style="font-size: 0.83rem;"><?= sanitize($t['borrower_name'] ?? $t['requested_by_name'] ?? '-') ?></td>
              <td><?= statusBadge($t['status']) ?></td>
              <td style="font-size: 0.75rem; color: var(--text-muted);"><?= formatDate($t['created_at'], 'd M') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- Overdue Borrows -->
    <?php if (!empty($overdueItems)): ?>
    <div class="card" style="border-color: rgba(239,68,68,0.3);">
      <div class="card-header">
        <div class="card-title" style="color: var(--danger);">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
          Peminjaman Terlambat
        </div>
      </div>
      <div class="table-container">
        <table class="table">
          <thead><tr><th>Barang</th><th>Peminjam</th><th>Batas Kembali</th><th>Telat</th></tr></thead>
          <tbody>
            <?php foreach ($overdueItems as $o): ?>
            <tr data-href="<?= APP_URL ?>/modules/transactions/view.php?id=<?= $o['id'] ?>">
              <td>
                <div class="table-item-name"><?= sanitize($o['item_name']) ?></div>
                <div class="table-item-code"><?= sanitize($o['item_code']) ?></div>
              </td>
              <td style="font-size:0.83rem;"><?= sanitize($o['borrower_name'] ?? '-') ?></td>
              <td style="font-size:0.83rem;color:var(--danger);"><?= formatDateTime($o['expected_return']) ?></td>
              <td>
                <?php
                  $days = floor((time() - strtotime($o['expected_return'])) / 86400);
                  echo "<span class='badge badge-danger'>{$days} hari</span>";
                ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Right Column -->
  <div>
    <!-- Category Distribution -->
    <div class="card mb-20">
      <div class="card-header">
        <div class="card-title">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>
          Barang per Kategori
        </div>
      </div>
      <div class="card-body">
        <?php if (empty($categoryData)): ?>
        <p class="text-muted text-center">Belum ada data</p>
        <?php else:
          $maxTotal = max(array_column($categoryData, 'total')) ?: 1;
          foreach ($categoryData as $cat):
            $pct = round(($cat['total'] / $maxTotal) * 100);
        ?>
        <div class="category-item" style="margin-bottom: 14px;">
          <div class="category-top">
            <span class="category-name"><?= sanitize($cat['name']) ?></span>
            <span class="category-count"><?= $cat['total'] ?> item</span>
          </div>
          <div class="category-bar">
            <div class="category-bar-fill" style="width: <?= $pct ?>%; background: <?= sanitize($cat['color']) ?>;"></div>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Recent Maintenance -->
    <div class="card mb-20">
      <div class="card-header">
        <div class="card-title">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
          Pemeliharaan
        </div>
        <a href="<?= APP_URL ?>/modules/maintenance/index.php" class="btn btn-ghost btn-sm">Semua</a>
      </div>
      <div class="activity-list" style="padding: 0 20px;">
        <?php if (empty($recentMaintenance)): ?>
        <p class="text-muted text-center" style="padding: 20px 0;">Tidak ada pemeliharaan</p>
        <?php else: foreach ($recentMaintenance as $m): ?>
        <div class="activity-item">
          <div class="activity-dot <?= $m['priority'] === 'critical' ? 'red' : ($m['priority'] === 'high' ? 'amber' : ($m['status'] === 'completed' ? 'green' : 'blue')) ?>"></div>
          <div class="activity-content">
            <div class="activity-title"><?= sanitize($m['title']) ?></div>
            <div class="activity-meta"><?= sanitize($m['item_name']) ?> &bull; <?= statusBadge($m['status']) ?></div>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Low Stock Alert -->
    <?php if (!empty($lowStockItems)): ?>
    <div class="card" style="border-color: rgba(245,158,11,0.3);">
      <div class="card-header">
        <div class="card-title" style="color: var(--warning);">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
          Stok Menipis
        </div>
      </div>
      <div class="activity-list" style="padding: 0 20px;">
        <?php foreach ($lowStockItems as $ls): ?>
        <div class="activity-item">
          <div class="activity-dot amber"></div>
          <div class="activity-content">
            <a href="<?= APP_URL ?>/modules/inventory/view.php?id=<?= $ls['id'] ?>" class="activity-title" style="color: var(--text-primary);">
              <?= sanitize($ls['name']) ?>
            </a>
            <div class="activity-meta">
              Tersedia: <strong style="color: var(--warning);"><?= $ls['quantity_available'] ?></strong> / <?= $ls['quantity'] ?> unit
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
