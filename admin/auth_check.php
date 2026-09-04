<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/security.php';
    start_secure_session('fastfood_admin');
}

if (! isset($_SESSION['admin_id']) || ! is_numeric($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
