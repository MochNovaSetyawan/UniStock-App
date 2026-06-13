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
    Session::flash('error', 'Anda tidak dapat menghapus akun sendiri.');
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    Session::flash('error', 'User tidak ditemukan.');
    header('Location: index.php');
    exit;
}

$pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
AuditService::log('DELETE', 'users', $id, 'User deleted: ' . $user['username'], $user);
Session::flash('success', 'User berhasil dihapus.');
header('Location: index.php');
exit;
