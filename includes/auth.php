<?php
require_once __DIR__ . '/config.php';

// ============================================
// Authentication Functions
// ============================================

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

function requireRole(...$roles) {
    requireLogin();
    if (!in_array($_SESSION['user_role'], $roles)) {
        $_SESSION['flash_error'] = 'Akses ditolak. Anda tidak memiliki hak akses ke halaman ini.';
        header('Location: ' . APP_URL . '/dashboard.php');
        exit;
    }
}

function currentUser() {
    if (!isLoggedIn()) return null;
    static $user = null;
    if ($user === null) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    }
    return $user;
}

function hasRole(...$roles) {
    return isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], $roles);
}

function isSuperAdmin() { return hasRole('superadmin'); }
function isAdmin()      { return hasRole('superadmin', 'admin'); }
function isWorker()     { return hasRole('superadmin', 'admin', 'worker'); }

function login($username, $password) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_dept'] = $user['department'];

        // Update last login
        $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

        // Audit log
        auditLog('LOGIN', 'auth', $user['id'], 'User logged in');

        return true;
    }
    return false;
}

function logout() {
    if (isLoggedIn()) {
        auditLog('LOGOUT', 'auth', $_SESSION['user_id'], 'User logged out');
    }
    session_destroy();
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

function auditLog($action, $module, $recordId = null, $description = '', $oldData = null, $newData = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO audit_logs (user_id, action, module, record_id, description, old_data, new_data, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $action,
            $module,
            $recordId,
            $description,
            $oldData ? json_encode($oldData) : null,
            $newData ? json_encode($newData) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    } catch (Exception $e) {
        // Silently fail audit logging
    }
}

function flashMessage($type, $msg) {
    $_SESSION["flash_{$type}"] = $msg;
}

function getFlash($type) {
    $msg = $_SESSION["flash_{$type}"] ?? null;
    unset($_SESSION["flash_{$type}"]);
    return $msg;
}

function getUnreadNotifications($limit = 5) {
    if (!isLoggedIn()) return [];
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$_SESSION['user_id'], $limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function countUnreadNotifications() {
    if (!isLoggedIn()) return 0;
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}
