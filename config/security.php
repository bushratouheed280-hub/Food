<?php

declare(strict_types=1);

function start_secure_session(string $sessionName): void
{
    session_name($sessionName);
    session_set_cookie_params([
        'httponly' => true,
        'secure' => ! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

function customer_csrf_token(): string
{
    if (empty($_SESSION['customer_csrf_token'])) {
        $_SESSION['customer_csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['customer_csrf_token'];
}

function verify_customer_csrf_token(?string $token): bool
{
    $expected = $_SESSION['customer_csrf_token'] ?? '';

    return is_string($expected)
        && is_string($token)
        && $token !== ''
        && hash_equals($expected, $token);
}
