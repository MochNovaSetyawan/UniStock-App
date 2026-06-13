<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Services\AuditService;

Auth::requireRole('superadmin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$pdo = Database::getInstance();
$id  = (int)$_POST['id'];

if ($id === Auth::id()) {
    Session::flash('error', 'Anda tidak dapat menonaktifkan akun sendiri.');
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT is_active, username FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    Session::flash('error', 'User tidak ditemukan.');
    header('Location: index.php');
    exit;
}

$newStatus = $user['is_active'] ? 0 : 1;
$pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$newStatus, $id]);
AuditService::log('UPDATE', 'users', $id, 'User status toggled: ' . $user['username'] . ' -> ' . ($newStatus ? 'active' : 'inactive'));
Session::flash('success', 'Status user berhasil diubah.');
header('Location: index.php');
exit;
