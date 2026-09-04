<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/security.php';
    start_secure_session('fastfood_admin');
}

if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

function render_admin_login_page(string $message = '', string $type = 'info', string $emailValue = ''): void
{
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $safeEmail = htmlspecialchars($emailValue, ENT_QUOTES, 'UTF-8');
    $alertClass = $type === 'error' ? 'alert-error' : ($type === 'success' ? 'alert-success' : 'alert-info');
    $alertHtml = $message !== '' ? '<div class="alert ' . $alertClass . '">' . $safeMessage . '</div>' : '';

    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Fast Food</title>
    <style>
        :root {
            --bg: #0d0d0e;
            --panel: #141416;
            --panel-soft: #1b1a1d;
            --gold: #d4af37;
            --gold-soft: #f4d777;
            --gold-deep: #b78c1d;
            --text: #f2f2f2;
            --muted: #c5c5c5;
            --danger: #ff6b6b;
            --success: #34d399;
            --shadow: rgba(0, 0, 0, 0.35);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at top, #221d17, var(--bg) 42%);
            color: var(--text);
            font-family: Arial, sans-serif;
        }

        .auth-shell {
            width: min(100%, 500px);
            padding: 24px;
        }

        .auth-card {
            background: linear-gradient(180deg, var(--panel), var(--panel-soft));
            border: 1px solid rgba(212, 175, 55, 0.42);
            border-radius: 18px;
            box-shadow: 0 22px 52px var(--shadow);
            padding: 30px 28px;
        }

        .brand {
            display: inline-block;
            color: var(--gold-soft);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            margin-bottom: 12px;
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
            border: 1px solid transparent;
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 18px;
            font-size: 0.95rem;
        }

        .alert-info { background: rgba(212, 175, 55, 0.12); border-color: rgba(212, 175, 55, 0.36); color: var(--gold-soft); }
        .alert-success { background: rgba(52, 211, 153, 0.08); border-color: rgba(52, 211, 153, 0.4); color: #d9ffef; }
        .alert-error { background: rgba(255, 107, 107, 0.08); border-color: rgba(255, 107, 107, 0.45); color: #ffe2e2; }

        form {
            display: grid;
            gap: 18px;
        }

        label {
            display: grid;
            gap: 8px;
            font-size: 0.95rem;
        }

        input {
            width: 100%;
            border: 1px solid rgba(212, 175, 55, 0.4);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.04);
            color: var(--text);
            padding: 12px 14px;
            font-size: 1rem;
        }

        input::placeholder {
            color: #a1a1a1;
        }

        input:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.14);
        }

        .password-wrap {
            position: relative;
        }

        .password-wrap input {
            padding-right: 54px;
        }

        .toggle-password {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: var(--gold-soft);
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .submit-btn {
            background: linear-gradient(135deg, var(--gold), var(--gold-soft));
            color: #111;
            border: none;
            border-radius: 10px;
            padding: 14px 18px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .submit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(212, 175, 55, 0.22);
        }
    </style>
</head>
<body>
    <main class="auth-shell">
        <section class="auth-card">
            <span class="brand">Fast Food</span>
            <h1>Admin Login</h1>
            <p class="subtext">Secure access to the admin dashboard.</p>
            {$alertHtml}
            <form method="post" action="login.php" novalidate>
                <label>
                    <span>Email</span>
                    <input type="email" name="email" placeholder="admin@fastfood.com" value="{$safeEmail}" required>
                </label>

                <label>
                    <span>Password</span>
                    <div class="password-wrap">
                        <input type="password" id="adminPassword" name="password" placeholder="Enter your password" required>
                        <button type="button" class="toggle-password" data-toggle-password="adminPassword">Show</button>
                    </div>
                </label>

                <button class="submit-btn" type="submit">Login</button>
            </form>

            <p class="switch-link" style="margin-top: 18px; text-align: center; color: #c5c5c5;">
                <a href="../auth/index.php" style="color: #f4d777; text-decoration: none; font-weight: 600;">&larr; Back to Account Type Selection</a>
            </p>
        </section>
    </main>

    <script>
        document.addEventListener('click', function (event) {
            const toggle = event.target.closest('[data-toggle-password]');
            if (! toggle) {
                return;
            }

            const input = document.getElementById(toggle.dataset.togglePassword);
            if (! input) {
                return;
            }

            const willShow = input.type === 'password';
            input.type = willShow ? 'text' : 'password';
            toggle.textContent = willShow ? 'Hide' : 'Show';
        });
    </script>
</body>
</html>
HTML;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        render_admin_login_page('Please enter both email and password.', 'error', $email);
        exit;
    }

    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        render_admin_login_page('Invalid email address.', 'error', $email);
        exit;
    }

    try {
        $connection = get_db_connection();
    } catch (RuntimeException $exception) {
        render_admin_login_page('Database is currently unavailable. Please try again later.', 'error', $email);
        exit;
    }

    $stmt = $connection->prepare('SELECT admin_id, full_name, email, password FROM admins WHERE email = ? LIMIT 1');
    if ($stmt === false) {
        $connection->close();
        render_admin_login_page('Database is currently unavailable. Please try again later.', 'error', $email);
        exit;
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();

    if (! $admin || ! password_verify($password, (string) $admin['password'])) {
        $stmt->close();
        $connection->close();
        render_admin_login_page('Invalid login details. Please check your email and password.', 'error', $email);
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $admin['admin_id'];
    $_SESSION['admin_name'] = (string) $admin['full_name'];
    $_SESSION['admin_email'] = (string) $admin['email'];

    $stmt->close();
    $connection->close();

    header('Location: dashboard.php');
    exit;
}

render_admin_login_page();
