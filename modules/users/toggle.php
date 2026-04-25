<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole('superadmin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }
$db = getDB();
$id = (int)$_POST['id'];
if ($id == $_SESSION['user_id']) { flashMessage('error', 'Anda tidak dapat menonaktifkan akun sendiri.'); header('Location: index.php'); exit; }
$stmt = $db->prepare("SELECT is_active, username FROM users WHERE id = ?"); $stmt->execute([$id]); $user = $stmt->fetch();
if (!$user) { flashMessage('error', 'User tidak ditemukan.'); header('Location: index.php'); exit; }
$newStatus = $user['is_active'] ? 0 : 1;
$db->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$newStatus, $id]);
auditLog('UPDATE', 'users', $id, 'User status toggled: ' . $user['username'] . ' -> ' . ($newStatus ? 'active' : 'inactive'));
flashMessage('success', 'Status user berhasil diubah.');
header('Location: index.php'); exit;
