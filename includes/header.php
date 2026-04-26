<?php
// header.php - Call requireLogin() before including this
$appName     = getSetting('app_name', 'Unistock');
$uniName     = getSetting('university_name', 'Universitas Nusantara');
$appLogo     = getSetting('app_logo', '');
$currentUser = currentUser();
$notifCount  = countUnreadNotifications();
$msgCount    = countUnreadMessages();

$flashSuccess = getFlash('success');
$flashError   = getFlash('error');
$flashInfo    = getFlash('info');
$flashWarning = getFlash('warning');

$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($pageTitle) ? sanitize($pageTitle) . ' — ' : '' ?><?= $appName ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
  <?php if ($appLogo && file_exists(UPLOAD_PATH . $appLogo)): ?>
  <link rel="icon" type="image/jpeg" href="<?= UPLOAD_URL . htmlspecialchars($appLogo) ?>">
  <?php else: ?>
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236366f1' stroke-width='2'><path d='M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10'/></svg>">
  <?php endif; ?>
</head>
<body>
<div class="app-layout">

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-scroll">
  <div class="sidebar-logo">
    <div class="logo-icon" <?= ($appLogo && file_exists(UPLOAD_PATH . $appLogo)) ? 'style="background:transparent;box-shadow:none;padding:2px;"' : '' ?>>
      <?php if ($appLogo && file_exists(UPLOAD_PATH . $appLogo)): ?>
        <img src="<?= UPLOAD_URL . htmlspecialchars($appLogo) ?>" alt="<?= sanitize($appName) ?>" style="width:100%;height:100%;object-fit:contain;border-radius:6px;">
      <?php else: ?>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="20" height="20">
          <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10" />
        </svg>
      <?php endif; ?>
    </div>
    <div class="logo-text">
      <h1><?= $appName ?></h1>
      <span>Inventory System</span>
    </div>
  </div>

  <nav class="sidebar-nav">

    <!-- MAIN -->
    <div class="nav-section">
      <div class="nav-section-label">Main</div>

      <a href="<?= APP_URL ?>/dashboard.php" class="nav-item <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        Dashboard
      </a>
    </div>

    <!-- INVENTORY -->
    <div class="nav-section">
      <div class="nav-section-label">Inventaris</div>

      <a href="<?= APP_URL ?>/modules/inventory/index.php" class="nav-item <?= $currentDir === 'inventory' ? 'active' : '' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
        Barang / Aset
      </a>

      <a href="<?= APP_URL ?>/modules/categories/index.php" class="nav-item <?= $currentDir === 'categories' ? 'active' : '' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>
        Kategori
      </a>

      <a href="<?= APP_URL ?>/modules/locations/index.php" class="nav-item <?= $currentDir === 'locations' ? 'active' : '' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        Lokasi
      </a>
    </div>

    <!-- OPERATIONS -->
    <div class="nav-section">
      <div class="nav-section-label">Operasional</div>

      <a href="<?= APP_URL ?>/modules/transactions/index.php" class="nav-item <?= $currentDir === 'transactions' ? 'active' : '' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
        Pinjam / Kembali
        <?php if ($stats_active_borrow = 0): ?><?php endif; ?>
      </a>

      <a href="<?= APP_URL ?>/modules/maintenance/index.php" class="nav-item <?= $currentDir === 'maintenance' ? 'active' : '' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        Pemeliharaan
      </a>
    </div>

    <!-- REPORTS (Admin+) -->
    <?php if (isAdmin()): ?>
    <div class="nav-section">
      <div class="nav-section-label">Laporan</div>

      <a href="<?= APP_URL ?>/modules/reports/index.php" class="nav-item <?= $currentDir === 'reports' ? 'active' : '' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Laporan
      </a>
    </div>
    <?php endif; ?>

    <!-- ADMIN ONLY -->
    <?php if (isSuperAdmin()): ?>
    <div class="nav-section">
      <div class="nav-section-label">Administrasi</div>

      <a href="<?= APP_URL ?>/modules/users/index.php" class="nav-item <?= $currentDir === 'users' ? 'active' : '' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        Manajemen User
      </a>

      <a href="<?= APP_URL ?>/modules/audit/index.php" class="nav-item <?= $currentDir === 'audit' ? 'active' : '' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
        Audit Log
      </a>

      <a href="<?= APP_URL ?>/modules/settings/index.php" class="nav-item <?= $currentDir === 'settings' ? 'active' : '' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
        Pengaturan
      </a>
    </div>
    <?php endif; ?>

  </nav>

  <!-- Sidebar User -->
  <div class="sidebar-user">
    <div class="user-avatar">
      <?= strtoupper(substr($currentUser['full_name'] ?? 'U', 0, 1)) ?>
    </div>
    <div class="user-info">
      <strong><?= sanitize($currentUser['full_name'] ?? '') ?></strong>
      <span><?= roleBadge($currentUser['role'] ?? 'worker') ?></span>
    </div>
    <a href="<?= APP_URL ?>/logout.php" class="user-logout" title="Keluar">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
    </a>
  </div>
  </div><!-- end .sidebar-scroll -->
  <button class="sidebar-pin-btn" id="sidebarPinBtn" onclick="toggleSidebarPin()" aria-label="Pin atau lepas sidebar">
    <svg class="pin-chevron" xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
      <path d="M15 18l-6-6 6-6"/>
    </svg>
  </button>
</aside>

<!-- Spacer: hanya aktif saat pinned (tidak berubah saat hover) -->
<div class="sidebar-spacer"></div>
<!-- Overlay: muncul saat sidebar hover overlay, klik untuk dismiss -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ===== MAIN CONTENT ===== -->
<div class="main-content">

  <!-- TOPBAR -->
  <header class="topbar">

    <div class="topbar-left"></div>

    <div class="topbar-search">
      <svg class="topbar-search-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <input type="text" id="globalSearch" placeholder="Cari barang, kode, lokasi..." autocomplete="off" onkeyup="globalSearchHandler(this.value)">
    </div>

    <div class="topbar-right">

      <!-- ── Chat Toggle (LinkedIn-style widget) ─────────────────── -->
      <div class="topbar-btn" id="cwTopbarBtn" title="Pesan" style="position:relative;cursor:pointer;">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>
        <span class="notif-dot" id="msgDot" <?= $msgCount > 0 ? '' : 'style="display:none"' ?>></span>
      </div>

      <!-- ── Notification Bell ─────────────────────────────────────── -->
      <div class="dropdown" id="notifDropdown">
        <div class="topbar-btn" onclick="toggleDropdown('notifDropdown')" title="Notifikasi" style="position:relative;">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
          <span class="notif-dot" id="notifDot" <?= $notifCount > 0 ? '' : 'style="display:none"' ?>></span>
        </div>
        <div class="dropdown-menu notif-dropdown-menu" style="min-width:320px; right:0; left:auto;">
          <div style="padding:12px 16px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between;">
            <span style="font-size:0.83rem; font-weight:600; color:var(--text-primary);">
              Notifikasi
              <span class="badge badge-danger" id="notifBadge" style="margin-left:6px; <?= $notifCount > 0 ? '' : 'display:none' ?>"><?= $notifCount ?></span>
            </span>
            <?php if ($notifCount > 0): ?>
            <button onclick="markAllRead()" style="font-size:0.75rem; color:var(--accent-light); background:none; border:none; cursor:pointer; padding:0;">Tandai semua terbaca</button>
            <?php endif; ?>
          </div>
          <div id="notifList">
          <?php
          $notifs = getUnreadNotifications(6);
          if (empty($notifs)):
          ?>
          <div style="padding:20px; text-align:center; color:var(--text-muted); font-size:0.8rem;">Tidak ada notifikasi baru</div>
          <?php else: foreach ($notifs as $n):
            $typeColors = ['warning'=>'var(--warning)','danger'=>'var(--danger)','success'=>'var(--success)','info'=>'var(--info)'];
            $dotColor   = $typeColors[$n['type']] ?? 'var(--accent)';
            $href       = $n['link'] ?: '#';
          ?>
          <a href="<?= htmlspecialchars($href) ?>" onclick="markOneRead(<?= $n['id'] ?>, event)"
             class="dropdown-item notif-item" style="flex-direction:column; align-items:flex-start; gap:3px;">
            <div style="display:flex; align-items:center; gap:8px; width:100%;">
              <span style="width:7px;height:7px;border-radius:50%;background:<?= $dotColor ?>;flex-shrink:0;"></span>
              <span style="font-size:0.83rem; font-weight:500; color:var(--text-primary); flex:1;"><?= sanitize($n['title']) ?></span>
            </div>
            <span style="font-size:0.75rem; color:var(--text-muted); padding-left:15px;"><?= sanitize($n['message']) ?></span>
          </a>
          <?php endforeach; endif; ?>
          </div>
        </div>
      </div>

      <div class="dropdown" id="userDropdown">
        <div class="topbar-btn" onclick="toggleDropdown('userDropdown')" style="width: auto; padding: 0 10px; gap: 8px;">
          <div class="user-avatar" style="width: 28px; height: 28px; font-size: 0.75rem;">
            <?= strtoupper(substr($currentUser['full_name'] ?? 'U', 0, 1)) ?>
          </div>
          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
        </div>
        <div class="dropdown-menu">
          <div style="padding: 12px 16px; border-bottom: 1px solid var(--border);">
            <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-primary);"><?= sanitize($currentUser['full_name'] ?? '') ?></div>
            <div style="font-size: 0.75rem; color: var(--text-muted);"><?= sanitize($currentUser['email'] ?? '') ?></div>
          </div>
          <a href="<?= APP_URL ?>/modules/profile.php" class="dropdown-item">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            Profil Saya
          </a>
          <div class="dropdown-divider"></div>
          <a href="<?= APP_URL ?>/logout.php" class="dropdown-item danger">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            Keluar
          </a>
        </div>
      </div>
    </div>
  </header>

  <!-- FLASH MESSAGES -->
  <div style="padding: 0 28px;">
    <?php
    // Allow limited safe HTML in flash messages (strong, em, code, br)
    $flashAllowedTags = '<strong><em><code><br><span>';
    ?>
    <?php if ($flashSuccess): ?>
    <div class="alert alert-success fade-in" style="margin-top: 20px;">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <div class="alert-content"><?= strip_tags($flashSuccess, $flashAllowedTags) ?></div>
      <span class="alert-close" onclick="this.parentElement.remove()"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M18 6L6 18M6 6l12 12"/></svg></span>
    </div>
    <?php endif; ?>
    <?php if ($flashError): ?>
    <div class="alert alert-danger fade-in" style="margin-top: 20px;">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
      <div class="alert-content"><?= strip_tags($flashError, $flashAllowedTags) ?></div>
      <span class="alert-close" onclick="this.parentElement.remove()"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M18 6L6 18M6 6l12 12"/></svg></span>
    </div>
    <?php endif; ?>
    <?php if ($flashInfo): ?>
    <div class="alert alert-info fade-in" style="margin-top: 20px;">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <div class="alert-content"><?= strip_tags($flashInfo, $flashAllowedTags) ?></div>
      <span class="alert-close" onclick="this.parentElement.remove()"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M18 6L6 18M6 6l12 12"/></svg></span>
    </div>
    <?php endif; ?>
    <?php if ($flashWarning): ?>
    <div class="alert alert-warning fade-in" style="margin-top: 20px;">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
      <div class="alert-content"><?= strip_tags($flashWarning, $flashAllowedTags) ?></div>
      <span class="alert-close" onclick="this.parentElement.remove()"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M18 6L6 18M6 6l12 12"/></svg></span>
    </div>
    <?php endif; ?>
  </div>

  <!-- PAGE CONTENT STARTS -->
  <main class="page-content">
