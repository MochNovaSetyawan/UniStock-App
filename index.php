<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use App\Core\Auth;

if (Auth::check()) {
    header('Location: ' . APP_URL . '/dashboard.php');
} else {
    header('Location: ' . APP_URL . '/login.php');
}
exit;
