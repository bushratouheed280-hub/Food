<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/security.php';
    start_secure_session('fastfood_customer');
}

if (isset($_SESSION['customer_id'])) {
    header('Location: ../customer/dashboard.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

function is_local_development(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));

    return $host === '' || str_contains($host, 'localhost') || str_contains($host, '127.0.0.1') || str_contains($host, '::1');
}

function build_reset_url(string $token): string
{
    $projectFolder = basename(dirname(__DIR__));
    $encodedFolder = rawurlencode($projectFolder);

    return 'http://localhost/' . $encodedFolder . '/auth/reset-password.php?token=' . rawurlencode($token);
}

function render_forgot_page(string $message = '', string $type = 'info', ?string $developmentResetUrl = null): void
{
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $alertClass = $type === 'error' ? 'alert-error' : ($type === 'success' ? 'alert-success' : 'alert-info');
    $emailValue = htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8');
    $alertHtml = $message !== '' ? '<div class="alert ' . $alertClass . '">' . $safeMessage . '</div>' : '';
    $developmentResetHtml = '';

    if ($developmentResetUrl !== null && is_local_development()) {
        $safeResetUrl = htmlspecialchars($developmentResetUrl, ENT_QUOTES, 'UTF-8');
        $developmentResetHtml = '
            <div class="dev-reset-box">
                <div class="dev-title">Development Reset Link</div>
                <a href="' . $safeResetUrl . '" target="_blank" rel="noopener noreferrer">' . $safeResetUrl . '</a>
            </div>
        ';
    }

    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Fast Food</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        :root {
            --bg: #000000;
            --card: #111111;
            --gold: #ffc107;
            --gold-soft: #ffd75a;
            --text: #ffffff;
            --muted: #bdbdbd;
            --danger: #ff6b6b;
            --success: #39d98a;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg);
            color: var(--text);
            font-family: Arial, sans-serif;
        }

        .auth-shell { width: min(100%, 480px); padding: 24px; }
        .auth-card {
            background: var(--card);
            border: 1px solid rgba(255, 193, 7, 0.4);
            border-radius: 18px;
            box-shadow: 0 18px 40px rgba(0,0,0,0.35);
            padding: 30px 28px;
        }

        .brand {
            color: var(--gold-soft);
            text-transform: uppercase;
            letter-spacing: 0.18em;
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 12px;
            display: inline-block;
        }

        h1 { margin: 0 0 10px; font-size: clamp(2rem, 4vw, 2.5rem); }
        .subtext { color: var(--muted); margin: 0 0 24px; line-height: 1.6; }

        .alert {
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 18px;
            font-size: 0.95rem;
            border: 1px solid transparent;
        }

        .alert-info { background: rgba(255, 193, 7, 0.08); border-color: rgba(255, 193, 7, 0.4); color: var(--gold-soft); }
        .alert-error { background: rgba(255, 107, 107, 0.08); border-color: rgba(255, 107, 107, 0.5); color: #ffd7d7; }
        .alert-success { background: rgba(57, 217, 138, 0.08); border-color: rgba(57, 217, 138, 0.45); color: #d7ffe9; }

        form { display: grid; gap: 18px; }
        label { display: grid; gap: 8px; color: var(--text); }
        input {
            width: 100%;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255, 193, 7, 0.35);
            border-radius: 10px;
            color: var(--text);
            padding: 12px 14px;
            font-size: 1rem;
        }
        input::placeholder { color: #a3a3a3; }
        input:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px rgba(255,193,7,0.12); }

        .submit-btn {
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--gold), var(--gold-soft));
            color: #111;
            font-weight: 700;
            padding: 14px 18px;
            cursor: pointer;
        }
        .submit-btn:hover { transform: translateY(-1px); }

        .secondary-link {
            text-align: center;
            margin-top: 18px;
            color: var(--muted);
        }
        .secondary-link a { color: var(--gold-soft); text-decoration: none; font-weight: 600; }
        .secondary-link a:hover { text-decoration: underline; }

        .dev-reset-box {
            margin-top: 18px;
            padding: 14px 16px;
            border: 1px solid rgba(255, 193, 7, 0.45);
            border-radius: 12px;
            background: rgba(255, 193, 7, 0.08);
        }

        .dev-title {
            color: var(--gold-soft);
            font-weight: 700;
            margin-bottom: 10px;
            letter-spacing: 0.04em;
        }

        .dev-reset-box a {
            color: var(--text);
            word-break: break-all;
        }
    </style>
</head>
<body>
    <main class="auth-shell">
        <div class="auth-card">
            <span class="brand">Fast Food</span>
            <h1>Forgot Password</h1>
            <p class="subtext">Enter your email address and we will send a reset link if an account exists.</p>
            ' . $alertHtml . '
            ' . $developmentResetHtml . '
            <form method="post" action="forgot-password.php" novalidate>
                <label>
                    <span>Email Address</span>
                    <input type="email" name="email" placeholder="Enter your email" value="' . $emailValue . '" required>
                </label>

                <button class="submit-btn" type="submit">Send Reset Link</button>
            </form>

            <p class="secondary-link"><a href="login.php"><i class="fa-solid fa-arrow-left"></i> Back to Login</a></p>
        </div>
    </main>
</body>
</html>';
}

function generate_secure_token(): string
{
    return bin2hex(random_bytes(32));
}

function send_reset_email(string $email, string $token): void
{
    if (! is_local_development()) {
        return;
    }

    $resetUrl = build_reset_url($token);
    error_log('Password reset requested for: ' . $email);
    error_log('Development reset link: ' . $resetUrl);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));

    if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        render_forgot_page('Please enter a valid email address.', 'error');
        exit;
    }

    try {
        $connection = get_db_connection();
    } catch (RuntimeException $exception) {
        render_forgot_page('We could not process your request right now. Please try again later.', 'error');
        exit;
    }

    $rateLimitKey = 'forgot_' . preg_replace('/[^a-z0-9]/i', '', $email);
    $rateLimitFile = __DIR__ . '/../storage/' . md5($rateLimitKey) . '.lock';
    if (file_exists($rateLimitFile)) {
        $lastRequest = (int) file_get_contents($rateLimitFile);
        if (($lastRequest + 60) > time()) {
            $connection->close();
            render_forgot_page('If an account exists for this email, a password reset link has been sent.', 'success');
            exit;
        }
    }

    $stmt = $connection->prepare('SELECT customer_id FROM customers WHERE email = ? LIMIT 1');
    if ($stmt === false) {
        $connection->close();
        render_forgot_page('We could not process your request right now. Please try again later.', 'error');
        exit;
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();
    $stmt->close();

    $developmentResetUrl = null;

    if ($customer) {
        $token = generate_secure_token();
        $tokenHash = hash('sha256', $token);
        $expiresAt = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');

        $updateStmt = $connection->prepare('UPDATE customers SET reset_token_hash = ?, reset_token_expires = ? WHERE customer_id = ?');
        if ($updateStmt !== false) {
            $updateStmt->bind_param('ssi', $tokenHash, $expiresAt, $customer['customer_id']);
            $updateStmt->execute();
            $updateStmt->close();
            send_reset_email($email, $token);
            if (is_local_development()) {
                $developmentResetUrl = build_reset_url($token);
            }
        }
    }

    $dir = dirname($rateLimitFile);
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($rateLimitFile, (string) time());

    $connection->close();

    render_forgot_page('If an account exists for this email, a password reset link has been sent.', 'success', $developmentResetUrl);
    exit;
}

render_forgot_page();
