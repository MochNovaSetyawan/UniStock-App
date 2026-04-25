<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$pageTitle = 'Transaksi Peminjaman';
$db = getDB();

$search  = trim($_GET['search'] ?? '');
$status  = $_GET['status'] ?? '';
$type    = $_GET['type'] ?? '';
$itemId  = (int)($_GET['item_id'] ?? 0);
$perPage = (int)getSetting('items_per_page', 15);
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$where = ['1=1']; $params = [];

// Workers can only see their own transactions
if (hasRole('worker')) {
    $where[] = 't.requested_by = ?';
    $params[] = $_SESSION['user_id'];
}

if ($search) {
    $where[] = '(t.code LIKE ? OR t.borrower_name LIKE ? OR i.name LIKE ?)';
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
if ($status === 'overdue') {
    $where[] = "t.status = 'active' AND t.type = 'borrow' AND t.expected_return < NOW()";
} elseif ($status) {
    $where[] = 't.status = ?';
    $params[] = $status;
}
if ($type)    { $where[] = 't.type = ?';      $params[] = $type; }
if ($itemId)  { $where[] = 't.item_id = ?';   $params[] = $itemId; }

$whereStr = implode(' AND ', $where);

$cStmt = $db->prepare("SELECT COUNT(*) FROM transactions t JOIN items i ON t.item_id = i.id WHERE $whereStr");
$cStmt->execute($params); $total = (int)$cStmt->fetchColumn();

$stmt = $db->prepare("
    SELECT t.*, i.name as item_name, i.code as item_code,
           u.full_name as requested_by_name, a.full_name as approved_by_name
    FROM transactions t
    JOIN items i ON t.item_id = i.id
    LEFT JOIN users u ON t.requested_by = u.id
    LEFT JOIN users a ON t.approved_by = a.id
    WHERE $whereStr
    ORDER BY t.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [$perPage, $offset]));
$transactions = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title">
    <div class="breadcrumb"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a> <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M9 18l6-6-6-6"/></svg> Transaksi</div>
    <h2>Pinjam &amp; Kembali</h2>
    <p>Kelola peminjaman dan pengembalian barang inventaris</p>
  </div>
  <a href="form.php" class="btn btn-primary">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M12 5v14M5 12h14"/></svg>
    Pinjam Barang
  </a>
</div>

<!-- Status Tabs -->
<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
  <?php
  $tabs = [
    [''        ,'Semua'],
    ['pending' ,'Menunggu'],
    ['active'  ,'Aktif Dipinjam'],
    ['overdue' ,'Terlambat'],
    ['returned','Dikembalikan'],
    ['rejected','Ditolak'],
  ];
  foreach ($tabs as [$val, $lbl]):
    $isActive = $status === $val;
    $count = '';
    if ($val === 'pending') { $pStmt = $db->query("SELECT COUNT(*) FROM transactions WHERE status='pending'"); $count = ' (' . $pStmt->fetchColumn() . ')'; }
    if ($val === 'overdue') {
      $oStmt = $db->query("SELECT COUNT(*) FROM transactions WHERE type='borrow' AND status='active' AND expected_return < NOW()");
      $count = ' (' . $oStmt->fetchColumn() . ')';
    }
  ?>
  <a href="?status=<?= $val ?><?= $search ? '&search='.urlencode($search) : '' ?>"
     style="padding:7px 14px;border-radius:var(--radius-sm);border:1px solid <?= $isActive ? 'var(--accent)' : 'var(--border)' ?>;background:<?= $isActive ? 'var(--accent-glow)' : 'var(--bg-card)' ?>;color:<?= $isActive ? 'var(--accent-light)' : 'var(--text-secondary)' ?>;font-size:0.8rem;transition:all 0.2s;">
    <?= $lbl . $count ?>
  </a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="table-toolbar">
    <form method="GET" style="display:contents;">
      <input type="hidden" name="status" value="<?= sanitize($status) ?>">
      <div class="search-box">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Cari kode, barang, peminjam...">
      </div>
      <select name="type" class="filter-select" onchange="this.form.submit()">
        <option value="">Semua Tipe</option>
        <option value="borrow"   <?= $type==='borrow'?'selected':'' ?>>Pinjam</option>
        <option value="return"   <?= $type==='return'?'selected':'' ?>>Kembali</option>
        <option value="transfer" <?= $type==='transfer'?'selected':'' ?>>Transfer</option>
      </select>
      <button type="submit" class="btn btn-outline btn-sm">Cari</button>
      <span style="margin-left:auto;font-size:0.78rem;color:var(--text-muted);"><?= $total ?> transaksi</span>
    </form>
  </div>

  <div class="table-container">
    <?php if (empty($transactions)): ?>
    <div class="empty-state">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
      <h3>Tidak ada transaksi</h3>
    </div>
    <?php else: ?>
    <table class="table">
      <thead><tr><th>Kode / Barang</th><th>Tipe</th><th>Unit</th><th>Peminjam</th><th>Tgl Pinjam</th><th>Batas Kembali</th><th>Status</th><th style="text-align:right;">Aksi</th></tr></thead>
      <tbody>
        <?php foreach ($transactions as $tx):
          $isOverdue = $tx['type']==='borrow' && $tx['status']==='active' && $tx['expected_return'] && $tx['expected_return'] < date('Y-m-d H:i:s');
        ?>
        <tr data-href="view.php?id=<?= $tx['id'] ?>" style="<?= $isOverdue ? 'background: rgba(239,68,68,0.04)' : '' ?>">
          <td>
            <div class="table-item-name"><?= sanitize($tx['item_name']) ?></div>
            <div class="table-item-code"><?= sanitize($tx['code']) ?> &bull; <?= sanitize($tx['item_code']) ?></div>
          </td>
          <td><?php $tl=['borrow'=>'Pinjam','return'=>'Kembali','transfer'=>'Transfer','dispose'=>'Buang']; echo "<span class='badge badge-secondary'>".($tl[$tx['type']]??$tx['type'])."</span>"; ?></td>
          <td class="td-meta"><?= $tx['quantity'] ?> unit</td>
          <td>
            <div><?= sanitize($tx['borrower_name'] ?: ($tx['requested_by_name'] ?: '-')) ?></div>
            <?php if ($tx['borrower_department']): ?><div class="table-item-sub"><?= sanitize($tx['borrower_department']) ?></div><?php endif; ?>
          </td>
          <td class="td-meta"><?= formatDate($tx['borrow_date'] ?: $tx['created_at'], 'd M Y') ?></td>
          <td>
            <?php if ($tx['expected_return']): ?>
            <span style="<?= $isOverdue ? 'color:var(--danger);font-weight:600;' : 'color:var(--text-muted);' ?>">
              <?= formatDate($tx['expected_return'], 'd M Y') ?>
              <?php if ($isOverdue): ?><br><span class="table-item-code" style="color:var(--danger);">Terlambat <?= floor((time()-strtotime($tx['expected_return']))/86400) ?> hari</span><?php endif; ?>
            </span>
            <?php else: ?>-<?php endif; ?>
          </td>
          <td><?= statusBadge($isOverdue ? 'overdue' : $tx['status']) ?></td>
          <td style="text-align:right;" onclick="event.stopPropagation()">
            <div class="btn-group" style="justify-content:flex-end;">
              <a href="view.php?id=<?= $tx['id'] ?>" class="btn btn-ghost btn-icon btn-sm" title="Detail">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              </a>
              <?php if (isAdmin() && $tx['status'] === 'pending'): ?>
              <form method="POST" action="approve.php" style="display:inline;">
                <input type="hidden" name="id" value="<?= $tx['id'] ?>"><input type="hidden" name="action" value="approve">
                <button type="submit" class="btn btn-ghost btn-icon btn-sm" style="color:var(--success);" title="Setujui">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </button>
              </form>
              <form method="POST" action="approve.php" style="display:inline;">
                <input type="hidden" name="id" value="<?= $tx['id'] ?>"><input type="hidden" name="action" value="reject">
                <button type="submit" class="btn btn-ghost btn-icon btn-sm" style="color:var(--danger);" title="Tolak">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </button>
              </form>
              <?php endif; ?>
              <?php if (isAdmin() && ($tx['status'] === 'active' || $isOverdue)): ?>
              <a href="return.php?id=<?= $tx['id'] ?>" class="btn btn-ghost btn-icon btn-sm" style="color:var(--info);" title="Proses Pengembalian">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
              </a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  <?= paginate($total, $perPage, $page, '?'.http_build_query(array_filter(['search'=>$search,'status'=>$status,'type'=>$type]))) ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
