<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole('superadmin');

$pageTitle = 'Manajemen User';
$db = getDB();

$search = trim($_GET['search'] ?? '');
$role   = $_GET['role'] ?? '';
$perPage = 15;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$where = ['1=1']; $params = [];
if ($search) { $where[] = '(full_name LIKE ? OR username LIKE ? OR email LIKE ?)'; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($role)   { $where[] = 'role = ?'; $params[] = $role; }
$whereStr = implode(' AND ', $where);

$total = (int)$db->prepare("SELECT COUNT(*) FROM users WHERE $whereStr")->execute($params) ? $db->prepare("SELECT COUNT(*) FROM users WHERE $whereStr")->execute($params) : 0;
$cStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE $whereStr"); $cStmt->execute($params); $total = (int)$cStmt->fetchColumn();

$stmt = $db->prepare("SELECT * FROM users WHERE $whereStr ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$perPage, $offset]));
$users = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title">
    <div class="breadcrumb">
      <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M9 18l6-6-6-6"/></svg>
      Manajemen User
    </div>
    <h2>Pengguna Sistem</h2>
    <p>Kelola akun dan hak akses pengguna</p>
  </div>
  <a href="form.php" class="btn btn-primary">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M12 5v14M5 12h14"/></svg>
    Tambah User
  </a>
</div>

<div class="card">
  <div class="table-toolbar">
    <form method="GET" style="display: contents;">
      <div class="search-box">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Cari nama, username, email...">
      </div>
      <select name="role" class="filter-select" onchange="this.form.submit()">
        <option value="">Semua Role</option>
        <option value="superadmin" <?= $role==='superadmin'?'selected':'' ?>>Super Admin</option>
        <option value="admin"      <?= $role==='admin'?'selected':'' ?>>Admin</option>
        <option value="worker"     <?= $role==='worker'?'selected':'' ?>>Pekerja</option>
      </select>
      <button type="submit" class="btn btn-outline btn-sm">Cari</button>
      <?php if ($search || $role): ?><a href="?" class="btn btn-ghost btn-sm">Reset</a><?php endif; ?>
      <span style="margin-left:auto;font-size:0.78rem;color:var(--text-muted);"><?= $total ?> pengguna</span>
    </form>
  </div>

  <div class="table-container">
    <?php if (empty($users)): ?>
    <div class="empty-state">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
      <h3>Tidak ada pengguna ditemukan</h3>
    </div>
    <?php else: ?>
    <table class="table">
      <thead><tr><th>Pengguna</th><th>Role</th><th>Departemen</th><th>Status</th><th>Login Terakhir</th><th style="text-align:right;">Aksi</th></tr></thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td>
            <div style="display: flex; align-items: center; gap: 12px;">
              <div class="user-avatar" style="flex-shrink:0;"><?= strtoupper(substr($u['full_name'], 0, 1)) ?></div>
              <div>
                <div class="table-item-name"><?= sanitize($u['full_name']) ?></div>
                <div class="table-item-code">@<?= sanitize($u['username']) ?> &bull; <?= sanitize($u['email']) ?></div>
              </div>
            </div>
          </td>
          <td><?= roleBadge($u['role']) ?></td>
          <td><?= sanitize($u['department'] ?: '-') ?></td>
          <td>
            <span class="badge <?= $u['is_active'] ? 'badge-success' : 'badge-secondary' ?>">
              <?= $u['is_active'] ? 'Aktif' : 'Nonaktif' ?>
            </span>
          </td>
          <td class="td-meta"><?= $u['last_login'] ? formatDateTime($u['last_login']) : 'Belum pernah' ?></td>
          <td style="text-align:right;">
            <div class="btn-group" style="justify-content:flex-end;">
              <a href="form.php?id=<?= $u['id'] ?>" class="btn btn-ghost btn-icon btn-sm" title="Edit">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
              </a>
              <?php if ($u['id'] != $_SESSION['user_id']): ?>
              <form method="POST" action="toggle.php" style="display:inline;">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <button type="submit" class="btn btn-ghost btn-icon btn-sm" title="<?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>" style="color: <?= $u['is_active'] ? 'var(--warning)' : 'var(--success)' ?>;">
                  <?php if ($u['is_active']): ?>
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                  <?php else: ?>
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  <?php endif; ?>
                </button>
              </form>
              <form method="POST" action="delete.php" style="display:inline;">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <button type="button" class="btn btn-ghost btn-icon btn-sm" style="color:var(--danger);" title="Hapus"
                  onclick="confirmDelete('Hapus user <?= sanitize(addslashes($u['full_name'])) ?>?', this.closest('form'))">
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
  <?= paginate($total, $perPage, $page, '?' . http_build_query(array_filter(['search'=>$search,'role'=>$role]))) ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
