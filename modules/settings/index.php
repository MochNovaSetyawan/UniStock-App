<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole('superadmin');

$pageTitle = 'Pengaturan Sistem';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Hapus logo
    if (isset($_POST['remove_logo'])) {
        $oldLogo = getSetting('app_logo', '');
        if ($oldLogo && file_exists(UPLOAD_PATH . $oldLogo)) {
            unlink(UPLOAD_PATH . $oldLogo);
        }
        $db->prepare("INSERT INTO settings (`key`,value,updated_by) VALUES ('app_logo','',?) ON DUPLICATE KEY UPDATE value='', updated_by=?")
           ->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
        auditLog('UPDATE', 'settings', null, 'Logo aplikasi dihapus');
        flashMessage('success', 'Logo berhasil dihapus.');
        header('Location: index.php'); exit;
    }

    // Upload logo baru
    if (!empty($_FILES['app_logo']['name'])) {
        $upload = uploadImage($_FILES['app_logo'], 'logo');
        if (isset($upload['error'])) {
            flashMessage('error', $upload['error']);
            header('Location: index.php'); exit;
        }
        // Hapus logo lama
        $oldLogo = getSetting('app_logo', '');
        if ($oldLogo && file_exists(UPLOAD_PATH . $oldLogo)) {
            unlink(UPLOAD_PATH . $oldLogo);
        }
        $db->prepare("INSERT INTO settings (`key`,value,updated_by) VALUES ('app_logo',?,?) ON DUPLICATE KEY UPDATE value=?, updated_by=?")
           ->execute([$upload['path'], $_SESSION['user_id'], $upload['path'], $_SESSION['user_id']]);
        auditLog('UPDATE', 'settings', null, 'Logo aplikasi diperbarui');
    }

    // Update pengaturan teks
    $keys = [
        'app_name', 'university_name', 'items_per_page',
        'borrow_max_days', 'low_stock_threshold',
        'allow_worker_borrow', 'require_approval', 'timezone'
    ];
    foreach ($keys as $key) {
        if (isset($_POST[$key])) {
            $val = trim($_POST[$key]);
            $db->prepare("UPDATE settings SET value=?, updated_by=? WHERE `key`=?")->execute([$val, $_SESSION['user_id'], $key]);
        }
    }
    auditLog('UPDATE', 'settings', null, 'System settings updated');
    flashMessage('success', 'Pengaturan berhasil disimpan.');
    header('Location: index.php'); exit;
}

// Load all settings
$allSettings = $db->query("SELECT `key`, value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$currentLogo = $allSettings['app_logo'] ?? '';

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title">
    <div class="breadcrumb"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a> <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M9 18l6-6-6-6"/></svg> Pengaturan</div>
    <h2>Pengaturan Sistem</h2>
    <p>Konfigurasi aplikasi inventaris</p>
  </div>
</div>

<form method="POST" enctype="multipart/form-data">
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">

    <div>
      <!-- CARD: Logo Aplikasi -->
      <div class="card mb-20">
        <div class="card-header"><div class="card-title">Logo Aplikasi</div></div>
        <div class="card-body">
          <div style="display:flex;align-items:center;gap:20px;">

            <!-- Preview -->
            <div id="logoWrap" style="width:80px;height:80px;flex-shrink:0;border-radius:12px;background:var(--bg-elevated);border:2px dashed var(--border);display:flex;align-items:center;justify-content:center;overflow:hidden;">
              <?php if ($currentLogo && file_exists(UPLOAD_PATH . $currentLogo)): ?>
                <img id="logoPreview" src="<?= UPLOAD_URL . htmlspecialchars($currentLogo) ?>" style="width:100%;height:100%;object-fit:contain;padding:8px;">
              <?php else: ?>
                <span id="logoPlaceholder" style="display:flex;flex-direction:column;align-items:center;gap:4px;">
                  <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="color:var(--text-muted)"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3 21h18M3.75 3h16.5A.75.75 0 0121 3.75v16.5a.75.75 0 01-.75.75H3.75a.75.75 0 01-.75-.75V3.75A.75.75 0 013.75 3z"/></svg>
                  <span style="font-size:0.65rem;color:var(--text-muted);">No logo</span>
                </span>
                <img id="logoPreview" src="" style="display:none;width:100%;height:100%;object-fit:contain;padding:8px;">
              <?php endif; ?>
            </div>

            <!-- Actions -->
            <div>
              <p style="font-size:0.78rem;color:var(--text-muted);margin-bottom:10px;line-height:1.5;">
                Format: JPG, PNG, WebP, GIF<br>
                Maks. 5 MB &bull; Rasio 1:1 direkomendasikan
              </p>
              <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <label class="btn btn-sm btn-secondary" style="cursor:pointer;margin:0;">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                  Pilih File
                  <input type="file" name="app_logo" accept="image/*" style="display:none;" onchange="previewLogo(this)">
                </label>
                <?php if ($currentLogo && file_exists(UPLOAD_PATH . $currentLogo)): ?>
                <button type="submit" name="remove_logo" value="1"
                  onclick="return confirm('Hapus logo aplikasi?')"
                  class="btn btn-sm btn-danger" style="margin:0;">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                  Hapus Logo
                </button>
                <?php endif; ?>
              </div>
            </div>

          </div>
        </div>
      </div>

      <div class="card mb-20">
        <div class="card-header"><div class="card-title">Informasi Aplikasi</div></div>
        <div class="card-body">
          <div class="form-grid">
            <div class="form-group full">
              <label class="form-label">Nama Aplikasi</label>
              <input type="text" name="app_name" class="form-control" value="<?= sanitize($allSettings['app_name'] ?? 'Unistock') ?>">
            </div>
            <div class="form-group full">
              <label class="form-label">Nama Universitas</label>
              <input type="text" name="university_name" class="form-control" value="<?= sanitize($allSettings['university_name'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Timezone</label>
              <select name="timezone" class="form-control">
                <?php foreach (['Asia/Jakarta'=>'WIB (Asia/Jakarta)','Asia/Makassar'=>'WITA (Asia/Makassar)','Asia/Jayapura'=>'WIT (Asia/Jayapura)'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= ($allSettings['timezone']??'Asia/Jakarta')===$v?'selected':'' ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><div class="card-title">Konfigurasi Tampilan</div></div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Item per Halaman</label>
            <select name="items_per_page" class="form-control">
              <?php foreach ([10,15,20,25,50] as $n): ?>
              <option value="<?= $n ?>" <?= ($allSettings['items_per_page']??15)==$n?'selected':'' ?>><?= $n ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div>
      <div class="card mb-20">
        <div class="card-header"><div class="card-title">Pengaturan Peminjaman</div></div>
        <div class="card-body">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Maksimal Hari Pinjam</label>
              <input type="number" name="borrow_max_days" class="form-control" value="<?= (int)($allSettings['borrow_max_days'] ?? 14) ?>" min="1" max="365">
              <span class="form-hint">Hari maksimal peminjaman barang</span>
            </div>
            <div class="form-group">
              <label class="form-label">Batas Stok Minimum</label>
              <input type="number" name="low_stock_threshold" class="form-control" value="<?= (int)($allSettings['low_stock_threshold'] ?? 5) ?>" min="0">
              <span class="form-hint">Alert ketika stok di bawah nilai ini</span>
            </div>
            <div class="form-group full">
              <label style="display:flex;align-items:center;gap:10px;cursor:pointer;margin-bottom:12px;">
                <input type="hidden" name="require_approval" value="0">
                <input type="checkbox" name="require_approval" value="1" <?= ($allSettings['require_approval']??1)?'checked':'' ?> style="width:16px;height:16px;accent-color:var(--accent);">
                <span>
                  <strong style="font-size:0.85rem;">Wajib Persetujuan Admin</strong>
                  <span class="form-hint" style="display:block;">Peminjaman oleh worker perlu disetujui admin</span>
                </span>
              </label>
              <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                <input type="hidden" name="allow_worker_borrow" value="0">
                <input type="checkbox" name="allow_worker_borrow" value="1" <?= ($allSettings['allow_worker_borrow']??1)?'checked':'' ?> style="width:16px;height:16px;accent-color:var(--accent);">
                <span>
                  <strong style="font-size:0.85rem;">Worker Dapat Mengajukan Pinjam</strong>
                  <span class="form-hint" style="display:block;">Izinkan pekerja mengajukan permohonan peminjaman</span>
                </span>
              </label>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><div class="card-title">Info Sistem</div></div>
        <div class="card-body" style="font-size:0.83rem;color:var(--text-secondary);">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div><span style="color:var(--text-muted);">Versi:</span><br><strong><?= sanitize($allSettings['app_version'] ?? '1.0.0') ?></strong></div>
            <div><span style="color:var(--text-muted);">PHP:</span><br><strong><?= PHP_VERSION ?></strong></div>
            <div><span style="color:var(--text-muted);">Database:</span><br><strong><?= DB_NAME ?></strong></div>
            <div><span style="color:var(--text-muted);">Server:</span><br><strong><?= $_SERVER['SERVER_NAME'] ?? 'localhost' ?></strong></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div style="display:flex;justify-content:flex-end;margin-top:20px;">
    <button type="submit" class="btn btn-primary btn-lg">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
      Simpan Pengaturan
    </button>
  </div>
</form>

<script>
function previewLogo(input) {
  if (!input.files || !input.files[0]) return;
  var reader = new FileReader();
  reader.onload = function(e) {
    var img = document.getElementById('logoPreview');
    var placeholder = document.getElementById('logoPlaceholder');
    img.src = e.target.result;
    img.style.display = 'block';
    if (placeholder) placeholder.style.display = 'none';
    document.getElementById('logoWrap').style.borderStyle = 'solid';
    document.getElementById('logoWrap').style.borderColor = 'var(--accent)';
  };
  reader.readAsDataURL(input.files[0]);
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
