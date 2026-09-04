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

function render_login_page(string $message = '', string $type = 'info'): void
{
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $alertClass = $type === 'error' ? 'alert-error' : ($type === 'success' ? 'alert-success' : 'alert-info');
    $emailValue = htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8');
    $alertHtml = $message !== '' ? '<div class="alert ' . $alertClass . '">' . $safeMessage . '</div>' : '';

    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Fast Food</title>
    <style>
        :root {
            --bg: #0f0f10;
            --panel: #17171a;
            --panel-soft: #1d1d1f;
            --gold: #d4af37;
            --gold-soft: #f5d76e;
            --text: #f5f5f5;
            --muted: #b9b9b9;
            --danger: #ff6b6b;
            --success: #39d98a;
            --shadow: rgba(0, 0, 0, 0.35);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at top, #201d17, var(--bg) 45%);
            color: var(--text);
            font-family: Arial, sans-serif;
        }

        .auth-shell {
            width: min(100%, 480px);
            padding: 24px;
        }

        .auth-card {
            background: linear-gradient(180deg, var(--panel), var(--panel-soft));
            border: 1px solid rgba(212, 175, 55, 0.45);
            border-radius: 18px;
            box-shadow: 0 18px 45px var(--shadow);
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

        h1 {
            margin: 0 0 10px;
            font-size: clamp(2rem, 4vw, 2.5rem);
        }

        .subtext {
            margin: 0 0 22px;
            color: var(--muted);
            line-height: 1.6;
        }

        .alert {
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 18px;
            font-size: 0.95rem;
            border: 1px solid transparent;
        }

        .alert-info { background: rgba(212, 175, 55, 0.12); border-color: rgba(212, 175, 55, 0.4); color: var(--gold-soft); }
        .alert-error { background: rgba(255, 107, 107, 0.08); border-color: rgba(255, 107, 107, 0.5); color: #ffd7d7; }
        .alert-success { background: rgba(57, 217, 138, 0.08); border-color: rgba(57, 217, 138, 0.45); color: #d7ffe9; }

        form {
            display: grid;
            gap: 18px;
        }

        label {
            display: grid;
            gap: 8px;
            font-size: 0.95rem;
            color: var(--text);
        }

        input {
            width: 100%;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(212, 175, 55, 0.35);
            border-radius: 10px;
            color: var(--text);
            padding: 12px 14px;
            font-size: 1rem;
        }

        input::placeholder {
            color: #9b9b9b;
        }

        input:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.16);
        }

        .submit-btn {
            background: linear-gradient(135deg, var(--gold), var(--gold-soft));
            color: #111;
            border: none;
            font-weight: 700;
            border-radius: 10px;
            padding: 14px 18px;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .submit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(212, 175, 55, 0.22);
        }

        .switch-link {
            margin-top: 18px;
            text-align: center;
            color: var(--muted);
        }

        .switch-link a {
            color: var(--gold-soft);
            text-decoration: none;
            font-weight: 600;
        }

        .switch-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <main class="auth-shell">
        <div class="auth-card">
            <span class="brand">Fast Food</span>
            <h1>Login</h1>
            <p class="subtext">Welcome back! Sign in to continue your order.</p>
            ' . $alertHtml . '
            <form method="post" action="login.php" novalidate>
                <label>
                    <span>Email</span>
                    <input type="email" name="email" placeholder="Enter your email" value="' . $emailValue . '" required>
                </label>

                <label>
                    <span>Password</span>
                    <input type="password" name="password" placeholder="Enter your password" required>
                </label>

                <button class="submit-btn" type="submit">Login</button>
            </form>

            <p class="switch-link"><a href="forgot-password.php">Forgot password?</a></p>
            <p class="switch-link">New here? <a href="register.php">Create account</a></p>
            <p class="switch-link"><a href="index.php">&larr; Back to Account Type Selection</a></p>
        </div>
    </main>
</body>
</html>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        render_login_page('Please enter both email and password.', 'error');
        exit;
    }

    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        render_login_page('Invalid email address.', 'error');
        exit;
    }

    try {
        $connection = get_db_connection();
    } catch (RuntimeException $exception) {
        render_login_page('Database is currently unavailable. Please try again later.', 'error');
        exit;
    }

    $stmt = $connection->prepare('SELECT customer_id, full_name, email, password FROM customers WHERE email = ? LIMIT 1');
    if ($stmt === false) {
        $connection->close();
        render_login_page('Database is currently unavailable. Please try again later.', 'error');
        exit;
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();

    if (! $customer || ! password_verify($password, (string) $customer['password'])) {
        $stmt->close();
        $connection->close();
        render_login_page('Invalid login details. Please check your email and password.', 'error');
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['customer_id'] = (int) $customer['customer_id'];
    $_SESSION['customer_name'] = (string) $customer['full_name'];
    $_SESSION['customer_email'] = (string) $customer['email'];

    $stmt->close();
    $connection->close();

    header('Location: ../customer/dashboard.php');
    exit;
}

if (isset($_GET['success']) && $_GET['success'] === '1') {
    render_login_page('Registration successful. Please log in with your new account.', 'success');
    exit;
}

if (isset($_GET['reset']) && $_GET['reset'] === 'success') {
    render_login_page('Your password has been reset successfully. Please log in.', 'success');
    exit;
}

render_login_page();
