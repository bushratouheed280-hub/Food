<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$customerId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($customerId === false || $customerId === null || $customerId <= 0) {
    $_SESSION['admin_customer_message'] = 'Invalid customer ID.';
    $_SESSION['admin_customer_message_type'] = 'error';
    header('Location: customers.php');
    exit;
}

$connection = get_db_connection();
$stmt = $connection->prepare('SELECT customer_id, full_name, email, phone, address, created_at, updated_at FROM customers WHERE customer_id = ?');
if ($stmt === false) {
    $connection->close();
    $_SESSION['admin_customer_message'] = 'Unable to load customer details.';
    $_SESSION['admin_customer_message_type'] = 'error';
    header('Location: customers.php');
    exit;
}

$stmt->bind_param('i', $customerId);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();
$stmt->close();
$connection->close();

if ($customer === null) {
    $_SESSION['admin_customer_message'] = 'Customer not found.';
    $_SESSION['admin_customer_message_type'] = 'error';
    header('Location: customers.php');
    exit;
}

$adminName = htmlspecialchars((string) ($_SESSION['admin_name'] ?? 'Administrator'), ENT_QUOTES, 'UTF-8');
$adminEmail = htmlspecialchars((string) ($_SESSION['admin_email'] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Customer | Fast Food Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        :root {
            --bg: #0d0d0e;
            --sidebar: #121214;
            --panel: #17171a;
            --panel-soft: #1d1d20;
            --gold: #d4af37;
            --gold-soft: #f5d76e;
            --text: #f6f6f6;
            --muted: #c8c8c8;
            --border: rgba(212, 175, 55, 0.32);
            --shadow: rgba(0, 0, 0, 0.28);
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: linear-gradient(180deg, #111214, #0d0d0e 35%, #17171a 100%); color: var(--text); font-family: Arial, sans-serif; }
        a { text-decoration: none; }
        .admin-layout { display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: var(--sidebar); border-right: 1px solid var(--border); position: sticky; top: 0; height: 100vh; padding: 22px 18px; }
        .brand-block { padding: 10px 10px 22px; border-bottom: 1px solid var(--border); margin-bottom: 18px; }
        .brand-block .logo { display: block; color: var(--gold-soft); font-size: 0.8rem; letter-spacing: 0.18em; text-transform: uppercase; font-weight: 700; margin-bottom: 6px; }
        .brand-block .admin-label { color: var(--muted); font-size: 0.8rem; letter-spacing: 0.14em; text-transform: uppercase; }
        .nav-menu { display: grid; gap: 8px; margin-top: 18px; }
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 12px 14px; color: var(--text); border-radius: 12px; border: 1px solid transparent; font-weight: 600; }
        .nav-link i { width: 18px; display: inline-flex; justify-content: center; color: var(--gold-soft); }
        .nav-link:hover, .nav-link.active { background: rgba(212, 175, 55, 0.1); border-color: var(--border); color: var(--gold-soft); }
        .nav-link.logout-link { margin-top: 18px; background: rgba(212, 175, 55, 0.08); border-color: var(--border); }
        .main-panel { flex: 1; min-width: 0; }
        .topbar { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 24px 28px 18px; border-bottom: 1px solid var(--border); background: rgba(17, 18, 20, 0.4); position: sticky; top: 0; backdrop-filter: blur(8px); z-index: 5; }
        .page-title { font-size: clamp(1.7rem, 2.5vw, 2.3rem); margin: 0; }
        .header-user { display: flex; align-items: center; gap: 16px; }
        .welcome-block { text-align: right; }
        .welcome-label { display: block; color: var(--muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.12em; }
        .welcome-name { font-weight: 700; color: var(--gold-soft); font-size: 1rem; }
        .welcome-email { color: var(--muted); font-size: 0.82rem; }
        .avatar { width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--gold), var(--gold-soft)); color: #111; font-weight: 700; border: 1px solid rgba(212, 175, 55, 0.5); }
        .logout-btn { display: inline-flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--gold), var(--gold-soft)); color: #111; border: none; padding: 10px 16px; border-radius: 10px; font-weight: 700; }
        .content { padding: 28px; }
        .panel-card { background: linear-gradient(180deg, var(--panel), var(--panel-soft)); border: 1px solid var(--border); border-radius: 18px; box-shadow: 0 12px 30px var(--shadow); overflow: hidden; }
        .panel-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 18px 20px; border-bottom: 1px solid var(--border); }
        .panel-title { margin: 0; font-size: 1.1rem; }
        .detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap: 18px; padding: 22px 20px 10px; }
        .detail-item { background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: 12px; padding: 16px; }
        .detail-label { display: block; color: var(--gold-soft); text-transform: uppercase; letter-spacing: 0.12em; font-size: 0.7rem; margin-bottom: 8px; }
        .detail-value { color: var(--text); font-size: 1rem; line-height: 1.5; word-break: break-word; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; padding: 20px; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px; border-radius: 10px; font-weight: 700; border: 1px solid var(--border); background: rgba(212,175,55,0.08); color: var(--gold-soft); padding: 10px 14px; text-decoration: none;
        }
        .menu-toggle { display: none; }
        @media (max-width: 820px) {
            .admin-layout { display: block; }
            .sidebar { position: fixed; left: 0; top: 0; transform: translateX(-100%); z-index: 20; height: 100vh; width: 260px; box-shadow: 0 18px 38px rgba(0,0,0,0.35); }
            .sidebar.open { transform: translateX(0); }
            .menu-toggle { display: inline-flex; width: 44px; height: 44px; border-radius: 10px; border: 1px solid var(--border); background: rgba(212,175,55,0.08); color: var(--gold-soft); align-items: center; justify-content: center; cursor: pointer; }
            .topbar { padding: 18px 20px; }
            .content { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <aside class="sidebar" id="sidebar">
            <div class="brand-block">
                <span class="logo">Fast Food</span>
                <div class="admin-label">Admin Panel</div>
            </div>
            <nav class="nav-menu" aria-label="Admin navigation">
                <a class="nav-link" href="dashboard.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
                <a class="nav-link active" href="customers.php"><i class="fa-solid fa-users"></i> Customers</a>
                <a class="nav-link" href="orders.php"><i class="fa-solid fa-box-open"></i> Orders</a>
                <a class="nav-link" href="menu.php"><i class="fa-solid fa-burger"></i> Menu</a>
                <a class="nav-link" href="reports.php"><i class="fa-solid fa-chart-column"></i> Reports</a>
                <a class="nav-link" href="profile.php"><i class="fa-solid fa-user-gear"></i> Profile</a>
                <a class="nav-link logout-link" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </nav>
        </aside>

        <main class="main-panel">
            <header class="topbar">
                <div style="display:flex;align-items:center;gap:12px;">
                    <button class="menu-toggle" type="button" aria-label="Toggle sidebar" id="menuToggle"><i class="fa-solid fa-bars"></i></button>
                    <h1 class="page-title">Customer Details</h1>
                </div>
                <div class="header-user">
                    <div class="welcome-block">
                        <span class="welcome-label">Welcome</span>
                        <div class="welcome-name"><?php echo $adminName; ?></div>
                        <div class="welcome-email"><?php echo $adminEmail; ?></div>
                    </div>
                    <div class="avatar"><?php echo strtoupper(substr($adminName, 0, 1)); ?></div>
                    <a class="logout-btn" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
                </div>
            </header>

            <div class="content">
                <article class="panel-card">
                    <div class="panel-header">
                        <h2 class="panel-title"><?php echo htmlspecialchars((string) $customer['full_name'], ENT_QUOTES, 'UTF-8'); ?></h2>
                    </div>

                    <div class="detail-grid">
                        <div class="detail-item"><span class="detail-label">Customer ID</span><div class="detail-value"><?php echo (int) $customer['customer_id']; ?></div></div>
                        <div class="detail-item"><span class="detail-label">Full Name</span><div class="detail-value"><?php echo htmlspecialchars((string) $customer['full_name'], ENT_QUOTES, 'UTF-8'); ?></div></div>
                        <div class="detail-item"><span class="detail-label">Email</span><div class="detail-value"><?php echo htmlspecialchars((string) $customer['email'], ENT_QUOTES, 'UTF-8'); ?></div></div>
                        <div class="detail-item"><span class="detail-label">Phone</span><div class="detail-value"><?php echo htmlspecialchars((string) ($customer['phone'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></div></div>
                        <div class="detail-item" style="grid-column: 1 / -1;"><span class="detail-label">Address</span><div class="detail-value"><?php echo htmlspecialchars((string) ($customer['address'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></div></div>
                        <div class="detail-item"><span class="detail-label">Created At</span><div class="detail-value"><?php echo htmlspecialchars((string) ($customer['created_at'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></div></div>
                        <div class="detail-item"><span class="detail-label">Updated At</span><div class="detail-value"><?php echo htmlspecialchars((string) ($customer['updated_at'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></div></div>
                    </div>

                    <div class="actions">
                        <a class="btn" href="customer-edit.php?id=<?php echo (int) $customer['customer_id']; ?>"><i class="fa-solid fa-pen-to-square"></i> Edit</a>
                        <a class="btn" href="customers.php"><i class="fa-solid fa-arrow-left"></i> Back to Customers</a>
                    </div>
                </article>
            </div>
        </main>
    </div>

    <script>
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', function () {
                sidebar.classList.toggle('open');
            });
        }
    </script>
</body>
</html>
