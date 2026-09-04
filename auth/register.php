<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/security.php';
    start_secure_session('fastfood_customer');
}

require_once __DIR__ . '/../config/database.php';

function render_register_page(string $message = '', string $type = 'info'): void
{
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $alertClass = $type === 'error' ? 'alert-error' : ($type === 'success' ? 'alert-success' : 'alert-info');
    $fullNameValue = htmlspecialchars((string) ($_POST['full_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $emailValue = htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8');
    $phoneValue = htmlspecialchars((string) ($_POST['phone'] ?? ''), ENT_QUOTES, 'UTF-8');
    $addressValue = htmlspecialchars((string) ($_POST['address'] ?? ''), ENT_QUOTES, 'UTF-8');
    $alertHtml = $message !== '' ? '<div class="alert ' . $alertClass . '">' . $safeMessage . '</div>' : '';

    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Fast Food</title>
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
            width: min(100%, 520px);
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
            gap: 16px;
        }

        label {
            display: grid;
            gap: 8px;
            font-size: 0.95rem;
            color: var(--text);
        }

        input,
        textarea {
            width: 100%;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(212, 175, 55, 0.35);
            border-radius: 10px;
            color: var(--text);
            padding: 12px 14px;
            font-size: 1rem;
        }

        input::placeholder,
        textarea::placeholder {
            color: #9b9b9b;
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.16);
        }

        textarea {
            min-height: 110px;
            resize: vertical;
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
            <h1>Create Account</h1>
            <p class="subtext">Register to place orders faster and keep your details saved.</p>
            ' . $alertHtml . '
            <form method="post" action="register.php" novalidate>
                <label>
                    <span>Full Name</span>
                    <input type="text" name="full_name" placeholder="Enter your full name" value="' . $fullNameValue . '" required>
                </label>

                <label>
                    <span>Email</span>
                    <input type="email" name="email" placeholder="Enter your email" value="' . $emailValue . '" required>
                </label>

                <label>
                    <span>Phone</span>
                    <input type="tel" name="phone" placeholder="Enter your phone number" value="' . $phoneValue . '" required>
                </label>

                <label>
                    <span>Password</span>
                    <input type="password" name="password" placeholder="Create a password" required>
                </label>

                <label>
                    <span>Confirm Password</span>
                    <input type="password" name="confirm_password" placeholder="Confirm your password" required>
                </label>

                <label>
                    <span>Address</span>
                    <textarea name="address" placeholder="Enter your address" required>' . $addressValue . '</textarea>
                </label>

                <button class="submit-btn" type="submit">Register</button>
            </form>

            <p class="switch-link">Already have an account? <a href="login.php">Login</a></p>
            <p class="switch-link"><a href="index.php">&larr; Back to Account Type Selection</a></p>
        </div>
    </main>
</body>
</html>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    $address = trim((string) ($_POST['address'] ?? ''));

    $errors = [];

    if ($fullName === '' || $email === '' || $phone === '' || $password === '' || $confirmPassword === '' || $address === '') {
        $errors[] = 'Please fill in all required fields.';
    }

    if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address.';
    }

    if ($password !== '' && $confirmPassword !== '' && $password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if ($password !== '' && ! preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,}$/', $password)) {
        $errors[] = 'Weak password. Use at least 8 characters with letters and numbers.';
    }

    if (! empty($errors)) {
        render_register_page(implode(' ', $errors), 'error');
        exit;
    }

    try {
        $connection = get_db_connection();
    } catch (RuntimeException $exception) {
        render_register_page('Database is currently unavailable. Please try again later.', 'error');
        exit;
    }

    $checkStmt = $connection->prepare('SELECT customer_id FROM customers WHERE email = ? LIMIT 1');
    if ($checkStmt === false) {
        $connection->close();
        render_register_page('Database is currently unavailable. Please try again later.', 'error');
        exit;
    }

    $checkStmt->bind_param('s', $email);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult && $checkResult->num_rows > 0) {
        $checkStmt->close();
        $connection->close();
        render_register_page('This email is already registered. Please use a different email address.', 'error');
        exit;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $insertStmt = $connection->prepare('INSERT INTO customers (full_name, email, phone, password, address) VALUES (?, ?, ?, ?, ?)');
    if ($insertStmt === false) {
        $checkStmt->close();
        $connection->close();
        render_register_page('Database is currently unavailable. Please try again later.', 'error');
        exit;
    }

    $insertStmt->bind_param('sssss', $fullName, $email, $phone, $hashedPassword, $address);

    if (! $insertStmt->execute()) {
        $insertStmt->close();
        $checkStmt->close();
        $connection->close();
        render_register_page('We could not create your account right now. Please try again.', 'error');
        exit;
    }

    $insertStmt->close();
    $checkStmt->close();
    $connection->close();

    header('Location: login.php?success=1');
    exit;
}

render_register_page();
