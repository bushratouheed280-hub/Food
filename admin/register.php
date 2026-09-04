<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/security.php';
start_secure_session('fastfood_admin');

if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

header('Location: login.php');
exit;

require_once __DIR__ . '/../config/database.php';

function render_admin_register_page(string $message = '', string $type = 'info', array $data = []): void
{
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $alertClass = $type === 'error' ? 'alert-error' : ($type === 'success' ? 'alert-success' : 'alert-info');
    $alertHtml = $message !== '' ? '<div class="alert ' . $alertClass . '">' . $safeMessage . '</div>' : '';

    $fullNameValue = htmlspecialchars((string) ($data['full_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $emailValue = htmlspecialchars((string) ($data['email'] ?? ''), ENT_QUOTES, 'UTF-8');

    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Register | Fast Food</title>
    <style>
        :root {
            --bg: #0d0d0e;
            --panel: #141416;
            --panel-soft: #1b1a1d;
            --gold: #d4af37;
            --gold-soft: #f4d777;
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
    </style>
</head>
<body>
    <main class="auth-shell">
        <section class="auth-card">
            <span class="brand">Fast Food</span>
            <h1>Create Admin Account</h1>
            <p class="subtext">Secure admin access for website operations.</p>
            {$alertHtml}
            <form method="post" action="register.php" novalidate>
                <label>
                    <span>Full Name</span>
                    <input type="text" name="full_name" placeholder="Enter full name" value="{$fullNameValue}" required>
                </label>

                <label>
                    <span>Email</span>
                    <input type="email" name="email" placeholder="admin@fastfood.com" value="{$emailValue}" required>
                </label>

                <label>
                    <span>Password</span>
                    <input type="password" name="password" placeholder="Create a password" required>
                </label>

                <label>
                    <span>Confirm Password</span>
                    <input type="password" name="confirm_password" placeholder="Confirm your password" required>
                </label>

                <button class="submit-btn" type="submit">Create Admin Account</button>
            </form>

            <p class="switch-link"><a href="../auth/index.php">&larr; Back to Account Selection</a></p>
        </section>
    </main>
</body>
</html>
HTML;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    $data = [
        'full_name' => $fullName,
        'email' => $email,
    ];

    if ($fullName === '') {
        render_admin_register_page('Full name is required.', 'error', $data);
        exit;
    }

    if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        render_admin_register_page('Please enter a valid email address.', 'error', $data);
        exit;
    }

    if ($password === '' || $confirmPassword === '') {
        render_admin_register_page('Please enter and confirm the password.', 'error', $data);
        exit;
    }

    if ($password !== $confirmPassword) {
        render_admin_register_page('Passwords do not match.', 'error', $data);
        exit;
    }

    if (strlen($password) < 8) {
        render_admin_register_page('Password must be at least 8 characters long.', 'error', $data);
        exit;
    }

    try {
        $connection = get_db_connection();
    } catch (RuntimeException $exception) {
        render_admin_register_page('Database is currently unavailable. Please try again later.', 'error', $data);
        exit;
    }

    $checkStmt = $connection->prepare('SELECT admin_id FROM admins WHERE email = ? LIMIT 1');
    if ($checkStmt === false) {
        $connection->close();
        render_admin_register_page('Database is currently unavailable. Please try again later.', 'error', $data);
        exit;
    }

    $checkStmt->bind_param('s', $email);
    $checkStmt->execute();
    $existing = $checkStmt->get_result();
    $alreadyExists = $existing && $existing->num_rows > 0;
    $checkStmt->close();

    if ($alreadyExists) {
        $connection->close();
        render_admin_register_page('An admin account with this email already exists.', 'error', $data);
        exit;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $insertStmt = $connection->prepare('INSERT INTO admins (full_name, email, password) VALUES (?, ?, ?)');
    if ($insertStmt === false) {
        $connection->close();
        render_admin_register_page('Database is currently unavailable. Please try again later.', 'error', $data);
        exit;
    }

    $insertStmt->bind_param('sss', $fullName, $email, $hashedPassword);
    if (! $insertStmt->execute()) {
        $insertStmt->close();
        $connection->close();
        render_admin_register_page('Unable to create the admin account right now. Please try again.', 'error', $data);
        exit;
    }

    $insertStmt->close();
    $connection->close();

    header('Location: login.php?success=1');
    exit;
}

render_admin_register_page();
