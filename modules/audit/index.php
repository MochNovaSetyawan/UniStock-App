<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole('superadmin');

$pageTitle = 'Audit Log';
$db = getDB();

$search  = trim($_GET['search'] ?? '');
$module  = $_GET['module'] ?? '';
$userId  = (int)($_GET['user_id'] ?? 0);
$perPage = 25;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$where = ['1=1']; $params = [];
if ($search) { $where[] = '(al.action LIKE ? OR al.description LIKE ? OR al.module LIKE ?)'; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($module) { $where[] = 'al.module = ?'; $params[] = $module; }
if ($userId) { $where[] = 'al.user_id = ?'; $params[] = $userId; }
$whereStr = implode(' AND ', $where);

$cStmt = $db->prepare("SELECT COUNT(*) FROM audit_logs al WHERE $whereStr"); $cStmt->execute($params); $total = (int)$cStmt->fetchColumn();
$stmt = $db->prepare("
    SELECT al.*, u.full_name, u.username FROM audit_logs al
    LEFT JOIN users u ON al.user_id=u.id WHERE $whereStr
    ORDER BY al.created_at DESC LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [$perPage, $offset]));
$logs = $stmt->fetchAll();

$modules = $db->query("SELECT DISTINCT module FROM audit_logs ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);
$users   = $db->query("SELECT id, full_name, username FROM users ORDER BY full_name")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title">
    <div class="breadcrumb"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a> <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M9 18l6-6-6-6"/></svg> Audit Log</div>
    <h2>Audit Log</h2>
    <p>Rekam jejak seluruh aktivitas sistem</p>
  </div>
</div>

<div class="card">
  <div class="table-toolbar">
    <form method="GET" style="display:contents;">
      <div class="search-box">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Cari aksi, deskripsi...">
      </div>
      <select name="module" class="filter-select" onchange="this.form.submit()">
        <option value="">Semua Modul</option>
        <?php foreach ($modules as $mod): ?><option value="<?= $mod ?>" <?= $module===$mod?'selected':'' ?>><?= ucfirst($mod) ?></option><?php endforeach; ?>
      </select>
      <select name="user_id" class="filter-select" onchange="this.form.submit()">
        <option value="">Semua User</option>
        <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= $userId==$u['id']?'selected':'' ?>><?= sanitize($u['full_name']) ?></option><?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-outline btn-sm">Cari</button>
      <span style="margin-left:auto;font-size:0.78rem;color:var(--text-muted);"><?= number_format($total) ?> entri</span>
    </form>
  </div>

  <div class="table-container">
    <?php if (empty($logs)): ?>
    <div class="empty-state"><h3>Tidak ada log</h3></div>
    <?php else: ?>
    <table class="table">
      <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Modul</th><th>Deskripsi</th><th>IP</th></tr></thead>
      <tbody>
        <?php foreach ($logs as $log):
          $actionColors = ['CREATE'=>'green','UPDATE'=>'amber','DELETE'=>'red','LOGIN'=>'blue','LOGOUT'=>'secondary'];
          $ac = $actionColors[$log['action']] ?? 'secondary';
        ?>
        <tr>
          <td class="td-meta"><?= formatDateTime($log['created_at']) ?></td>
          <td>
            <div class="table-item-name"><?= sanitize($log['full_name'] ?? 'System') ?></div>
            <div class="table-item-code">@<?= sanitize($log['username'] ?? '-') ?></div>
          </td>
          <td><span class="badge badge-<?= $ac ?>"><?= sanitize($log['action']) ?></span></td>
          <td><span class="badge badge-secondary"><?= sanitize($log['module']) ?></span></td>
          <td style="max-width:300px;"><?= sanitize($log['description'] ?? '-') ?></td>
          <td class="td-meta"><?= sanitize($log['ip_address'] ?? '-') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  <?= paginate($total, $perPage, $page, '?'.http_build_query(array_filter(['search'=>$search,'module'=>$module,'user_id'=>$userId]))) ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
