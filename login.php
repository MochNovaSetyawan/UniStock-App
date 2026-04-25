<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi.';
    } elseif (login($username, $password)) {
        header('Location: ' . APP_URL . '/dashboard.php');
        exit;
    } else {
        $error = 'Username atau password salah. Silakan coba lagi.';
    }
}

$appName = getSetting('app_name', 'Unistock');
$uniName = getSetting('university_name', 'Universitas Nusantara');
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
</head>
<body>
<div class="login-page">
  <div class="login-bg"></div>

  <div class="login-card fade-in">
    <div class="login-logo">
      <div class="login-logo-icon">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/>
        </svg>
      </div>
      <h1><?= sanitize($appName) ?></h1>
      <p><?= sanitize($uniName) ?></p>
    </div>

    <h2 class="login-title">Masuk ke Akun Anda</h2>

    <?php if ($error): ?>
    <div class="alert alert-danger" style="margin-bottom: 20px;">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
      <div><?= sanitize($error) ?></div>
    </div>
    <?php endif; ?>

    <form method="POST" action="" autocomplete="off">
      <div class="form-group" style="margin-bottom: 16px;">
        <label class="form-label" for="username">Username atau Email</label>
        <div style="position: relative;">
          <input type="text" id="username" name="username"
                 class="form-control"
                 placeholder="Masukkan username..."
                 value="<?= sanitize($_POST['username'] ?? '') ?>"
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

      <button type="submit" class="btn btn-primary w-100 btn-lg">
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
</script>
</body>
</html>
