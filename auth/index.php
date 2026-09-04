<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/security.php';
    start_secure_session('fastfood_customer');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Selection | Fast Food</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
        integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        :root {
            --bg: #0f0f10;
            --panel: #17171a;
            --panel-soft: #1d1d1f;
            --gold: #d4af37;
            --gold-soft: #f5d76e;
            --text: #f5f5f5;
            --muted: #b9b9b9;
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

        .page-shell {
            width: min(1100px, 100%);
            padding: 32px 20px;
        }

        .page-card {
            background: linear-gradient(180deg, var(--panel), var(--panel-soft));
            border: 1px solid rgba(212, 175, 55, 0.45);
            border-radius: 22px;
            box-shadow: 0 22px 60px var(--shadow);
            padding: clamp(24px, 4vw, 40px);
        }

        .eyebrow {
            display: inline-block;
            font-size: 12px;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--gold-soft);
            font-weight: 700;
            margin-bottom: 12px;
        }

        h1 {
            margin: 0 0 10px;
            font-size: clamp(2.2rem, 4vw, 3.2rem);
            line-height: 1.2;
        }

        .subheading {
            margin: 0 0 30px;
            color: var(--muted);
            font-size: 1.05rem;
        }

        .choice-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
        }

        .choice-card {
            display: flex;
            flex-direction: column;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(212, 175, 55, 0.35);
            border-radius: 18px;
            padding: 26px 22px;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
            min-height: 100%;
        }

        .choice-card:hover {
            transform: translateY(-3px);
            border-color: rgba(212, 175, 55, 0.7);
            box-shadow: 0 14px 30px rgba(212, 175, 55, 0.12);
        }

        .icon-wrap {
            width: 62px;
            height: 62px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(212, 175, 55, 0.18), rgba(245, 215, 110, 0.12));
            border: 1px solid rgba(212, 175, 55, 0.35);
            color: var(--gold-soft);
            font-size: 1.4rem;
            margin-bottom: 18px;
        }

        .choice-card h2 {
            margin: 0 0 10px;
            font-size: 1.8rem;
        }

        .choice-card p {
            margin: 0 0 22px;
            color: var(--muted);
            line-height: 1.7;
            flex-grow: 1;
        }

        .action-stack {
            display: grid;
            gap: 12px;
        }

        .primary-btn,
        .secondary-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            border-radius: 12px;
            padding: 12px 16px;
            font-weight: 700;
            text-decoration: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .primary-btn {
            background: linear-gradient(135deg, var(--gold), var(--gold-soft));
            color: #111;
            box-shadow: 0 10px 22px rgba(212, 175, 55, 0.22);
        }

        .secondary-btn {
            background: rgba(255, 255, 255, 0.03);
            color: var(--text);
            border: 1px solid rgba(212, 175, 55, 0.35);
        }

        .primary-btn:hover,
        .secondary-btn:hover {
            transform: translateY(-1px);
            text-decoration: none;
        }

        @media (max-width: 640px) {
            .choice-card {
                padding: 22px 18px;
            }
        }
    </style>
</head>
<body>
    <main class="page-shell">
        <div class="page-card">
            <span class="eyebrow">Fast Food</span>
            <h1>Welcome to Fast Food</h1>
            <p class="subheading">Choose how you want to continue</p>

            <div class="choice-grid">
                <article class="choice-card">
                    <div class="icon-wrap"><i class="fa-solid fa-user"></i></div>
                    <h2>Customer</h2>
                    <p>Order food, manage your cart and track your orders.</p>
                    <div class="action-stack">
                        <a class="primary-btn" href="login.php">Customer Login</a>
                        <a class="secondary-btn" href="register.php">Customer Register</a>
                    </div>
                </article>

                <article class="choice-card">
                    <div class="icon-wrap"><i class="fa-solid fa-shield-halved"></i></div>
                    <h2>Admin</h2>
                    <p>Manage orders, customers, menu items and website operations.</p>
                    <div class="action-stack">
                        <a class="primary-btn" href="../admin/login.php">Admin Login</a>
                    </div>
                </article>
            </div>
        </div>
    </main>
</body>
</html>
