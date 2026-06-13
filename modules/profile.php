<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Helpers\Badge;
use App\Helpers\Format;
use App\Services\AuditService;

Auth::requireLogin();

$pageTitle = 'Profil Saya';
$pdo    = Database::getInstance();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $dept     = trim($_POST['department'] ?? '');
    $oldPass  = $_POST['old_password'] ?? '';
    $newPass  = $_POST['new_password'] ?? '';
    $confPass = $_POST['confirm_password'] ?? '';

    if (!$fullName) $errors[] = 'Nama lengkap wajib diisi.';

    $user = Auth::user();

    if ($newPass) {
        if (!password_verify($oldPass, $user['password'])) $errors[] = 'Password lama tidak benar.';
        if ($newPass !== $confPass) $errors[] = 'Konfirmasi password tidak cocok.';
        if (strlen($newPass) < 6)   $errors[] = 'Password baru minimal 6 karakter.';
    }

    if (empty($errors)) {
        $me = Auth::id();
        if ($newPass) {
            $pdo->prepare("UPDATE users SET full_name=?,phone=?,department=?,password=? WHERE id=?")
                ->execute([$fullName, $phone, $dept, password_hash($newPass, PASSWORD_BCRYPT), $me]);
        } else {
            $pdo->prepare("UPDATE users SET full_name=?,phone=?,department=? WHERE id=?")
                ->execute([$fullName, $phone, $dept, $me]);
        }
        Session::set('user_name', $fullName);
        AuditService::log('UPDATE', 'users', $me, 'Profile updated');
        Session::flash('success', 'Profil berhasil diperbarui.');
        header('Location: profile.php');
        exit;
    }
}

$user = Auth::user();
$me   = Auth::id();

$stBorrows = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE requested_by=? AND type='borrow'");
$stBorrows->execute([$me]);
$cntBorrows = $stBorrows->fetchColumn();

$stActive = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE requested_by=? AND status='active'");
$stActive->execute([$me]);
$cntActive = $stActive->fetchColumn();

$stMaint = $pdo->prepare("SELECT COUNT(*) FROM maintenance WHERE requested_by=?");
$stMaint->execute([$me]);
$cntMaint = $stMaint->fetchColumn();

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
  <div class="page-title">
    <div class="breadcrumb">
      <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M9 18l6-6-6-6"/></svg>
      Profil
    </div>
    <h2>Profil Saya</h2>
  </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-20">
  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
  <div><ul style="margin:4px 0 0 16px;"><?php foreach ($errors as $e): ?><li><?= Format::escape($e) ?></li><?php endforeach; ?></ul></div>
</div>
<?php endif; ?>

<div class="grid-2" style="align-items:start;gap:20px;">
  <div>
    <div class="card mb-20">
      <div class="card-body" style="text-align:center;padding:32px;">
        <div class="user-avatar" style="width:72px;height:72px;font-size:1.6rem;margin:0 auto 16px;">
          <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
        </div>
        <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:4px;"><?= Format::escape($user['full_name']) ?></h3>
        <p style="font-size:0.83rem;color:var(--text-muted);">@<?= Format::escape($user['username']) ?></p>
        <div style="margin-top:10px;"><?= Badge::role($user['role']) ?></div>
        <?php if ($user['department']): ?>
        <p style="margin-top:10px;font-size:0.83rem;color:var(--text-secondary);"><?= Format::escape($user['department']) ?></p>
        <?php endif; ?>
        <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);display:grid;grid-template-columns:repeat(3,1fr);gap:12px;text-align:center;">
          <div><div style="font-size:1.4rem;font-weight:700;"><?= $cntBorrows ?></div><div style="font-size:0.72rem;color:var(--text-muted);text-transform:uppercase;">Total Pinjam</div></div>
          <div><div style="font-size:1.4rem;font-weight:700;color:var(--info);"><?= $cntActive ?></div><div style="font-size:0.72rem;color:var(--text-muted);text-transform:uppercase;">Aktif</div></div>
          <div><div style="font-size:1.4rem;font-weight:700;color:var(--warning);"><?= $cntMaint ?></div><div style="font-size:0.72rem;color:var(--text-muted);text-transform:uppercase;">Laporan</div></div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><div class="card-title">Info Akun</div></div>
      <div class="card-body">
        <div style="display:flex;flex-direction:column;gap:14px;font-size:0.85rem;">
          <div style="display:flex;justify-content:space-between;"><span style="color:var(--text-muted);">Email</span><span><?= Format::escape($user['email']) ?></span></div>
          <div style="display:flex;justify-content:space-between;"><span style="color:var(--text-muted);">Telepon</span><span><?= Format::escape($user['phone'] ?: '-') ?></span></div>
          <div style="display:flex;justify-content:space-between;"><span style="color:var(--text-muted);">Bergabung</span><span><?= Format::date($user['created_at']) ?></span></div>
          <div style="display:flex;justify-content:space-between;"><span style="color:var(--text-muted);">Login Terakhir</span><span><?= $user['last_login'] ? Format::datetime($user['last_login']) : '-' ?></span></div>
        </div>
      </div>
    </div>
  </div>

  <form method="POST">
    <div class="card mb-20">
      <div class="card-header"><div class="card-title">Edit Profil</div></div>
      <div class="card-body">
        <div class="form-grid">
          <div class="form-group full"><label class="form-label">Nama Lengkap <span class="required">*</span></label><input type="text" name="full_name" class="form-control" required value="<?= Format::escape($user['full_name']) ?>"></div>
          <div class="form-group"><label class="form-label">No. Telepon</label><input type="text" name="phone" class="form-control" value="<?= Format::escape($user['phone'] ?? '') ?>" placeholder="08xx-xxxx-xxxx"></div>
          <div class="form-group"><label class="form-label">Departemen</label><input type="text" name="department" class="form-control" value="<?= Format::escape($user['department'] ?? '') ?>"></div>
        </div>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><div class="card-title">Ganti Password</div></div>
      <div class="card-body">
        <div class="form-grid">
          <div class="form-group full"><label class="form-label">Password Lama</label><input type="password" name="old_password" class="form-control" placeholder="Masukkan password saat ini"></div>
          <div class="form-group"><label class="form-label">Password Baru</label><input type="password" name="new_password" class="form-control" placeholder="Min. 6 karakter"></div>
          <div class="form-group"><label class="form-label">Konfirmasi Password</label><input type="password" name="confirm_password" class="form-control" placeholder="Ulangi password baru"></div>
        </div>
      </div>
      <div class="card-footer" style="display:flex;gap:12px;justify-content:flex-end;">
        <button type="submit" class="btn btn-primary">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
          Simpan Perubahan
        </button>
      </div>
    </div>
  </form>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
