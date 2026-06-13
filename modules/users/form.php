<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Helpers\Format;
use App\Services\AuditService;

Auth::requireRole('superadmin');

$pdo    = Database::getInstance();
$id     = (int)($_GET['id'] ?? 0);
$user   = null;
$isEdit = false;

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) {
        Session::flash('error', 'User tidak ditemukan.');
        header('Location: index.php');
        exit;
    }
    $isEdit = true;
}

$pageTitle = $isEdit ? 'Edit User' : 'Tambah User';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'full_name'  => trim($_POST['full_name'] ?? ''),
        'username'   => strtolower(trim($_POST['username'] ?? '')),
        'email'      => strtolower(trim($_POST['email'] ?? '')),
        'phone'      => trim($_POST['phone'] ?? ''),
        'role'       => $_POST['role'] ?? 'worker',
        'department' => trim($_POST['department'] ?? ''),
        'is_active'  => isset($_POST['is_active']) ? 1 : 0,
    ];
    $password    = $_POST['password'] ?? '';
    $passConfirm = $_POST['password_confirm'] ?? '';

    if (!$data['full_name'])  $errors[] = 'Nama lengkap wajib diisi.';
    if (!$data['username'])   $errors[] = 'Username wajib diisi.';
    if (!$data['email'])      $errors[] = 'Email wajib diisi.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email tidak valid.';
    if (!$isEdit && !$password)  $errors[] = 'Password wajib diisi untuk user baru.';
    if ($password && $password !== $passConfirm) $errors[] = 'Konfirmasi password tidak cocok.';
    if ($password && strlen($password) < 6)      $errors[] = 'Password minimal 6 karakter.';

    $uCheck = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $uCheck->execute([$data['username'], $id]);
    if ($uCheck->fetch()) $errors[] = 'Username sudah digunakan.';

    $eCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $eCheck->execute([$data['email'], $id]);
    if ($eCheck->fetch()) $errors[] = 'Email sudah digunakan.';

    if (empty($errors)) {
        if ($password) {
            $data['password'] = password_hash($password, PASSWORD_BCRYPT);
        }

        if ($isEdit) {
            $sql = "UPDATE users SET " . implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data))) . " WHERE id = ?";
            $pdo->prepare($sql)->execute(array_merge(array_values($data), [$id]));
            AuditService::log('UPDATE', 'users', $id, 'User updated: ' . $data['username']);
            Session::flash('success', 'User berhasil diperbarui.');
        } else {
            $sql = "INSERT INTO users (" . implode(', ', array_map(fn($k) => "`$k`", array_keys($data))) . ") VALUES (" . implode(', ', array_fill(0, count($data), '?')) . ")";
            $pdo->prepare($sql)->execute(array_values($data));
            AuditService::log('CREATE', 'users', (int)$pdo->lastInsertId(), 'User created: ' . $data['username']);
            Session::flash('success', 'User berhasil ditambahkan.');
        }
        header('Location: index.php');
        exit;
    }
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="page-header">
  <div class="page-title">
    <div class="breadcrumb">
      <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M9 18l6-6-6-6"/></svg>
      <a href="index.php">User</a>
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M9 18l6-6-6-6"/></svg>
      <?= $isEdit ? 'Edit' : 'Tambah' ?>
    </div>
    <h2><?= $isEdit ? 'Edit User' : 'Tambah User Baru' ?></h2>
  </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-20">
  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
  <div><ul style="margin:4px 0 0 16px;"><?php foreach ($errors as $e): ?><li><?= Format::escape($e) ?></li><?php endforeach; ?></ul></div>
</div>
<?php endif; ?>

<form method="POST">
  <div class="card" style="max-width: 720px;">
    <div class="card-header"><div class="card-title">Informasi Pengguna</div></div>
    <div class="card-body">
      <div class="form-grid">
        <div class="form-group full">
          <label class="form-label">Nama Lengkap <span class="required">*</span></label>
          <input type="text" name="full_name" class="form-control" required value="<?= Format::escape($user['full_name'] ?? ($_POST['full_name'] ?? '')) ?>" placeholder="Nama lengkap pengguna">
        </div>
        <div class="form-group">
          <label class="form-label">Username <span class="required">*</span></label>
          <input type="text" name="username" class="form-control" required value="<?= Format::escape($user['username'] ?? ($_POST['username'] ?? '')) ?>" placeholder="username_unik" style="text-transform:lowercase;">
        </div>
        <div class="form-group">
          <label class="form-label">Email <span class="required">*</span></label>
          <input type="email" name="email" class="form-control" required value="<?= Format::escape($user['email'] ?? ($_POST['email'] ?? '')) ?>" placeholder="email@universitas.ac.id">
        </div>
        <div class="form-group">
          <label class="form-label">No. Telepon</label>
          <input type="text" name="phone" class="form-control" value="<?= Format::escape($user['phone'] ?? '') ?>" placeholder="08xx-xxxx-xxxx">
        </div>
        <div class="form-group">
          <label class="form-label">Role <span class="required">*</span></label>
          <select name="role" class="form-control">
            <option value="worker"     <?= ($user['role'] ?? 'worker') === 'worker' ? 'selected' : '' ?>>Pekerja</option>
            <option value="admin"      <?= ($user['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
            <option value="superadmin" <?= ($user['role'] ?? '') === 'superadmin' ? 'selected' : '' ?>>Super Admin</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Departemen / Fakultas</label>
          <input type="text" name="department" class="form-control" value="<?= Format::escape($user['department'] ?? '') ?>" placeholder="Fakultas Teknik, IT Department...">
        </div>
        <div class="form-group">
          <label class="form-label">Password <?= $isEdit ? '' : '<span class="required">*</span>' ?></label>
          <input type="password" name="password" class="form-control" placeholder="<?= $isEdit ? 'Kosongkan jika tidak ingin ubah' : 'Min. 6 karakter' ?>" minlength="6">
        </div>
        <div class="form-group">
          <label class="form-label">Konfirmasi Password</label>
          <input type="password" name="password_confirm" class="form-control" placeholder="Ulangi password">
        </div>
        <div class="form-group full">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="is_active" value="1" <?= ($user['is_active'] ?? 1) ? 'checked' : '' ?> style="width:16px;height:16px;accent-color:var(--accent);">
            <span class="form-label" style="margin:0;">Akun Aktif</span>
          </label>
        </div>
      </div>
    </div>
    <div class="card-footer" style="display:flex;gap:12px;justify-content:flex-end;">
      <a href="index.php" class="btn btn-outline">Batal</a>
      <button type="submit" class="btn btn-primary">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        <?= $isEdit ? 'Simpan Perubahan' : 'Tambah User' ?>
      </button>
    </div>
  </div>
</form>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
