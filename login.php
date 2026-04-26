<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}

// ── Mode AJAX (fetch dari JS) ────────────────────────────────────────────────
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        echo json_encode(['ok' => false, 'error' => 'Username dan password wajib diisi.']);
        exit;
    }
    if (login($username, $password)) {
        echo json_encode(['ok' => true, 'name' => $_SESSION['user_name']]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Username atau password salah. Silakan coba lagi.']);
    }
    exit;
}

$appName = getSetting('app_name', 'Unistock');
$uniName = getSetting('university_name', 'Universitas Nusantara');
$appLogo = getSetting('app_logo', '');
$logoUrl = ($appLogo && file_exists(UPLOAD_PATH . $appLogo)) ? UPLOAD_URL . $appLogo : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login &mdash; <?= sanitize($appName) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
  <?php if ($logoUrl): ?>
  <link rel="icon" type="image/jpeg" href="<?= $logoUrl ?>">
  <?php else: ?>
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236366f1' stroke-width='2'><path d='M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10'/></svg>">
  <?php endif; ?>
  <style>
    /* ── Welcome overlay ──────────────────────────────────── */
    #welcomeOverlay {
      position: fixed; inset: 0; z-index: 9999;
      background: var(--bg-dark, #0a0a0f);
      display: flex; flex-direction: column;
      align-items: center; justify-content: center; gap: 16px;
      opacity: 0; pointer-events: none;
      transition: opacity 0.4s ease;
    }
    #welcomeOverlay.show { opacity: 1; pointer-events: all; }

    #welcomeOverlay .wl-greeting {
      font-size: 1rem; font-weight: 500; letter-spacing: 0.08em;
      text-transform: uppercase; color: var(--text-muted, #888);
      opacity: 0; transform: translateY(12px);
      transition: opacity 0.4s ease 0.2s, transform 0.4s ease 0.2s;
    }
    #welcomeOverlay.show .wl-greeting { opacity: 1; transform: translateY(0); }

    #welcomeOverlay .wl-name {
      font-size: 2.2rem; font-weight: 800;
      color: var(--text-primary, #fff);
      opacity: 0; transform: translateY(16px);
      transition: opacity 0.45s ease 0.35s, transform 0.45s ease 0.35s;
      text-align: center; padding: 0 24px;
    }
    #welcomeOverlay.show .wl-name { opacity: 1; transform: translateY(0); }

    #welcomeOverlay .wl-dots {
      display: flex; gap: 6px; margin-top: 8px;
      opacity: 0; transition: opacity 0.3s ease 0.6s;
    }
    #welcomeOverlay.show .wl-dots { opacity: 1; }
    #welcomeOverlay .wl-dots span {
      width: 7px; height: 7px; border-radius: 50%;
      background: var(--accent, #6366f1);
      animation: wlBounce 1.1s infinite ease-in-out;
    }
    #welcomeOverlay .wl-dots span:nth-child(2) { animation-delay: 0.18s; }
    #welcomeOverlay .wl-dots span:nth-child(3) { animation-delay: 0.36s; }

    @keyframes wlBounce {
      0%, 80%, 100% { transform: scale(0.7); opacity: 0.5; }
      40%            { transform: scale(1.2); opacity: 1; }
    }
  </style>
</head>
<body>

<!-- ── Welcome Overlay ──────────────────────────────────────── -->
<div id="welcomeOverlay" role="status" aria-live="polite">
  <p class="wl-greeting">Selamat Datang</p>
  <p class="wl-name" id="welcomeName"></p>
  <div class="wl-dots"><span></span><span></span><span></span></div>
</div>

<div class="login-page">
  <div class="login-bg"></div>

  <div class="login-card fade-in">
    <div class="login-logo">
      <div class="login-logo-icon" <?= $logoUrl ? 'style="background:transparent;box-shadow:none;padding:0;"' : '' ?>>
        <?php if ($logoUrl): ?>
          <img src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= sanitize($appName) ?>"
               style="width:100%;height:100%;object-fit:contain;border-radius:8px;">
        <?php else: ?>
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/>
          </svg>
        <?php endif; ?>
      </div>
      <h1><?= sanitize($appName) ?></h1>
      <p><?= sanitize($uniName) ?></p>
    </div>

    <h2 class="login-title">Masuk ke Akun Anda</h2>

    <div id="errorBox" style="display:none;margin-bottom:20px;" class="alert alert-danger">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
      <div id="errorMsg"></div>
    </div>

    <form id="loginForm" autocomplete="off">
      <div class="form-group" style="margin-bottom: 16px;">
        <label class="form-label" for="username">Username atau Email</label>
        <div style="position: relative;">
          <input type="text" id="username" name="username"
                 class="form-control"
                 placeholder="Masukkan username..."
                 required autofocus
                 style="padding-left: 40px;">
          <svg style="position:absolute;left:12px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:var(--text-muted);"
               xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
          </svg>
        </div>
      </div>

      <div class="form-group" style="margin-bottom: 24px;">
        <label class="form-label" for="password">Password</label>
        <div style="position: relative;">
          <input type="password" id="password" name="password"
                 class="form-control"
                 placeholder="Masukkan password..."
                 required
                 style="padding-left: 40px; padding-right: 40px;">
          <svg style="position:absolute;left:12px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:var(--text-muted);"
               xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
          </svg>
          <span id="togglePwd" onclick="togglePassword()"
                style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--text-muted);">
            <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
          </span>
        </div>
      </div>

      <button type="submit" id="submitBtn" class="btn btn-primary w-100 btn-lg">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
        </svg>
        Masuk
      </button>
    </form>

    <div style="margin-top: 24px; padding: 16px; background: var(--bg-surface); border-radius: var(--radius-sm); border: 1px solid var(--border);">
      <p style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Demo Akun</p>
      <div style="display: flex; flex-direction: column; gap: 4px; font-size: 0.78rem; color: var(--text-secondary);">
        <span><span class="badge badge-danger" style="margin-right:6px;">Super Admin</span> superadmin / password</span>
        <span><span class="badge badge-info" style="margin-right:6px;">Admin</span> admin / password</span>
        <span><span class="badge badge-success" style="margin-right:6px;">Pekerja</span> worker1 / password</span>
      </div>
    </div>
  </div>
</div>

<script>
function togglePassword() {
  const input = document.getElementById('password');
  input.type = input.type === 'password' ? 'text' : 'password';
}

document.getElementById('loginForm').addEventListener('submit', function(e) {
  e.preventDefault();

  const btn     = document.getElementById('submitBtn');
  const errBox  = document.getElementById('errorBox');
  const errMsg  = document.getElementById('errorMsg');
  const overlay = document.getElementById('welcomeOverlay');

  // Nonaktifkan tombol + tampilkan loading
  btn.disabled = true;
  btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="animation:spin 0.8s linear infinite"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Memuat...';
  errBox.style.display = 'none';

  const body = new URLSearchParams(new FormData(this));

  fetch('', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: body,
  })
  .then(r => r.json())
  .then(data => {
    if (!data.ok) {
      // Gagal login — tampilkan error
      errMsg.textContent = data.error || 'Login gagal.';
      errBox.style.display = 'flex';
      btn.disabled = false;
      btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg> Masuk';
      return;
    }

    // Berhasil — tampilkan animasi selamat datang
    document.getElementById('welcomeName').textContent = data.name;
    overlay.classList.add('show');

    // Redirect setelah animasi selesai (1.8 detik)
    setTimeout(function() {
      window.location.href = '<?= APP_URL ?>/dashboard.php';
    }, 1800);
  })
  .catch(function() {
    errMsg.textContent = 'Terjadi kesalahan. Coba lagi.';
    errBox.style.display = 'flex';
    btn.disabled = false;
    btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg> Masuk';
  });
});
</script>
<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>
</body>
</html>
