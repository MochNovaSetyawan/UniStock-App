<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole('superadmin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }
$db = getDB();
$id = (int)$_POST['id'];
if ($id == $_SESSION['user_id']) { flashMessage('error', 'Anda tidak dapat menghapus akun sendiri.'); header('Location: index.php'); exit; }
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?"); $stmt->execute([$id]); $user = $stmt->fetch();
if (!$user) { flashMessage('error', 'User tidak ditemukan.'); header('Location: index.php'); exit; }
$db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
auditLog('DELETE', 'users', $id, 'User deleted: ' . $user['username'], $user);
flashMessage('success', 'User berhasil dihapus.');
header('Location: index.php'); exit;
