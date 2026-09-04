<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/security.php';
    start_secure_session('fastfood_customer');
}

require_once __DIR__ . '/../config/database.php';

function render_reset_page(string $message = '', string $type = 'info'): void
{
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $alertClass = $type === 'error' ? 'alert-error' : ($type === 'success' ? 'alert-success' : 'alert-info');
    $alertHtml = $message !== '' ? '<div class="alert ' . $alertClass . '">' . $safeMessage . '</div>' : '';

    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Fast Food</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
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
    </style>
</head>
<body>
    <main class="auth-shell">
        <div class="auth-card">
            <span class="brand">Fast Food</span>
            <h1>Reset Password</h1>
            <p class="subtext">Create a new password for your account.</p>
            ' . $alertHtml . '
            <form method="post" action="reset-password.php?token=' . rawurlencode((string) ($_GET['token'] ?? '')) . '" novalidate>
                <label>
                    <span>New Password</span>
                    <input type="password" name="new_password" placeholder="Enter new password" required>
                </label>
                <label>
                    <span>Confirm New Password</span>
                    <input type="password" name="confirm_password" placeholder="Confirm new password" required>
                </label>
                <button class="submit-btn" type="submit">Reset Password</button>
            </form>
            <p class="secondary-link"><a href="login.php">Back to Login</a></p>
        </div>
    </main>
</body>
</html>';
}

if (! isset($_GET['token']) || trim((string) $_GET['token']) === '') {
    render_reset_page('Invalid or expired reset link.', 'error');
    exit;
}

$token = (string) $_GET['token'];
$tokenHash = hash('sha256', $token);

try {
    $connection = get_db_connection();
} catch (RuntimeException $exception) {
    render_reset_page('This reset link is no longer valid. Please request a new one.', 'error');
    exit;
}

$stmt = $connection->prepare('SELECT customer_id, reset_token_hash, reset_token_expires FROM customers WHERE reset_token_hash = ? LIMIT 1');
if ($stmt === false) {
    $connection->close();
    render_reset_page('Invalid or expired reset link.', 'error');
    exit;
}

$stmt->bind_param('s', $tokenHash);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();
$stmt->close();

if (! $customer || ! isset($customer['reset_token_expires']) || empty($customer['reset_token_expires'])) {
    $connection->close();
    render_reset_page('Invalid or expired reset link.', 'error');
    exit;
}

$currentTime = new DateTimeImmutable('now');
$expiresAt = new DateTimeImmutable($customer['reset_token_expires']);

if ($expiresAt <= $currentTime) {
    $connection->close();
    render_reset_page('Invalid or expired reset link.', 'error');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($newPassword === '' || $confirmPassword === '') {
        render_reset_page('Please enter and confirm your new password.', 'error');
        exit;
    }

    if ($newPassword !== $confirmPassword) {
        render_reset_page('Passwords do not match.', 'error');
        exit;
    }

    if (strlen($newPassword) < 8) {
        render_reset_page('Password must be at least 8 characters long.', 'error');
        exit;
    }

    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $nullToken = null;
    $nullExpires = null;
    $customerId = (int) $customer['customer_id'];

    $updateStmt = $connection->prepare('UPDATE customers SET password = ?, reset_token_hash = ?, reset_token_expires = ?, updated_at = CURRENT_TIMESTAMP WHERE customer_id = ?');
    if ($updateStmt === false) {
        $connection->close();
        render_reset_page('We could not reset your password right now. Please try again later.', 'error');
        exit;
    }

    $updateStmt->bind_param('sssi', $hashedPassword, $nullToken, $nullExpires, $customerId);
    if (! $updateStmt->execute()) {
        $updateStmt->close();
        $connection->close();
        render_reset_page('We could not reset your password right now. Please try again later.', 'error');
        exit;
    }

    $updateStmt->close();
    $connection->close();

    header('Location: login.php?reset=success');
    exit;
}

render_reset_page();
