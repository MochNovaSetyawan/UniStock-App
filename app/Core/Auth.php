<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\AuditService;

/**
 * Auth – stateless authentication layer built on Session.
 *
 * Every method reads from / writes to the active session.
 * No constructor – all methods are static.
 */
final class Auth
{
    private static ?array $cachedUser = null;

    private function __construct() {}

    // ── Identity helpers ──────────────────────────────────────

    public static function check(): bool
    {
        return Session::has('user_id') && (int) Session::get('user_id') > 0;
    }

    public static function id(): ?int
    {
        return self::check() ? (int) Session::get('user_id') : null;
    }

    public static function role(): ?string
    {
        return Session::get('user_role');
    }

    /**
     * Returns the full user row from the database (cached per request).
     */
    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        if (self::$cachedUser !== null) {
            return self::$cachedUser;
        }
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1');
        $stmt->execute([self::id()]);
        self::$cachedUser = $stmt->fetch() ?: null;
        return self::$cachedUser;
    }

    // ── Role checks ───────────────────────────────────────────

    public static function hasRole(string ...$roles): bool
    {
        return in_array(self::role(), $roles, true);
    }

    public static function isSuperAdmin(): bool
    {
        return self::hasRole('superadmin');
    }

    /** Returns true for superadmin AND admin. */
    public static function isAdmin(): bool
    {
        return self::hasRole('superadmin', 'admin');
    }

    /** Returns true for every authenticated role (superadmin / admin / worker). */
    public static function isWorker(): bool
    {
        return self::hasRole('superadmin', 'admin', 'worker');
    }

    // ── Guards ────────────────────────────────────────────────

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: ' . APP_URL . '/login.php');
            exit;
        }
    }

    public static function requireRole(string ...$roles): void
    {
        self::requireLogin();
        if (!self::hasRole(...$roles)) {
            Session::flash('error', 'Akses ditolak. Anda tidak memiliki hak akses ke halaman ini.');
            header('Location: ' . APP_URL . '/dashboard.php');
            exit;
        }
    }

    // ── Login / Logout ────────────────────────────────────────

    public static function login(string $credential, string $password): bool
    {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1'
        );
        $stmt->execute([$credential, $credential]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        // Persist identity
        Session::set('user_id',   $user['id']);
        Session::set('user_name', $user['full_name']);
        Session::set('user_role', $user['role']);
        Session::set('user_dept', $user['department']);

        // Update last login
        $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);

        AuditService::log('LOGIN', 'auth', $user['id'], 'User logged in');

        return true;
    }

    public static function logout(): never
    {
        if (self::check()) {
            AuditService::log('LOGOUT', 'auth', self::id(), 'User logged out');
        }
        Session::destroy();
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}
