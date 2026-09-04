<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/security.php';
    start_secure_session('fastfood_customer');
}

if (! isset($_SESSION['customer_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    $connection = get_db_connection();
} catch (RuntimeException $exception) {
    http_response_code(500);
    exit('Database is currently unavailable. Please try again later.');
}

$stmt = $connection->prepare('SELECT customer_id, full_name, email, phone, address FROM customers WHERE customer_id = ? LIMIT 1');
if ($stmt === false) {
    $connection->close();
    http_response_code(500);
    exit('Database is currently unavailable. Please try again later.');
}

$customerId = (int) $_SESSION['customer_id'];
$stmt->bind_param('i', $customerId);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();
$stmt->close();
$connection->close();

if (! $customer) {
    session_destroy();
    header('Location: ../auth/login.php');
    exit;
}

$customerName = htmlspecialchars((string) ($customer['full_name'] ?? ''), ENT_QUOTES, 'UTF-8');
$customerEmail = htmlspecialchars((string) ($customer['email'] ?? ''), ENT_QUOTES, 'UTF-8');
$customerPhone = htmlspecialchars((string) ($customer['phone'] ?? ''), ENT_QUOTES, 'UTF-8');
$customerAddress = htmlspecialchars((string) ($customer['address'] ?? ''), ENT_QUOTES, 'UTF-8');

$displayPhone = $customerPhone !== '' ? $customerPhone : 'Not provided';
$displayAddress = $customerAddress !== '' ? $customerAddress : 'Not provided';

try {
    $statsConnection = get_db_connection();
} catch (RuntimeException $exception) {
    $statsConnection = null;
}

$totalOrders = 0;
$pendingOrders = 0;
$completedOrders = 0;
$totalSpent = 0;
$recentOrders = [];

if ($statsConnection !== null) {
    $summaryStmt = $statsConnection->prepare('SELECT COUNT(*) AS total_orders, SUM(CASE WHEN status = "Pending" THEN 1 ELSE 0 END) AS pending_orders, SUM(CASE WHEN status = "Delivered" THEN 1 ELSE 0 END) AS completed_orders, COALESCE(SUM(total_amount), 0) AS total_spent FROM orders WHERE customer_id = ?');
    if ($summaryStmt !== false) {
        $summaryStmt->bind_param('i', $customerId);
        $summaryStmt->execute();
        $summaryResult = $summaryStmt->get_result();
        $summaryRow = $summaryResult ? $summaryResult->fetch_assoc() : null;
        $summaryStmt->close();

        if ($summaryRow !== null) {
            $totalOrders = (int) ($summaryRow['total_orders'] ?? 0);
            $pendingOrders = (int) ($summaryRow['pending_orders'] ?? 0);
            $completedOrders = (int) ($summaryRow['completed_orders'] ?? 0);
            $totalSpent = (float) ($summaryRow['total_spent'] ?? 0.0);
        }
    }

    $recentStmt = $statsConnection->prepare('SELECT order_id, order_number, total_amount, status, created_at FROM orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT 3');
    if ($recentStmt !== false) {
        $recentStmt->bind_param('i', $customerId);
        $recentStmt->execute();
        $recentResult = $recentStmt->get_result();
        $recentOrders = $recentResult ? $recentResult->fetch_all(MYSQLI_ASSOC) : [];
        $recentStmt->close();
    }

    $statsConnection->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard | Fast Food</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <style>
        :root {
            --bg: #000000;
            --surface: #111111;
            --surface-soft: #171717;
            --surface-alt: #1d1d1d;
            --gold: #FFC107;
            --gold-soft: #ffd75f;
            --text: #FFFFFF;
            --muted: #BDBDBD;
            --border: rgba(255, 193, 7, 0.18);
            --shadow: rgba(0, 0, 0, 0.42);
            --success: #27ae60;
            --warning: #f39c12;
        }

        * { box-sizing: border-box; }

        html { scroll-behavior: smooth; }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
        }

        a { text-decoration: none; }

        .dashboard-shell {
            display: grid;
            grid-template-columns: 260px minmax(0, 1fr);
            min-height: 100vh;
            background: linear-gradient(180deg, #070707 0%, #0b0b0b 100%);
        }

        .sidebar {
            background: linear-gradient(180deg, #0d0d0d 0%, #121212 100%);
            border-right: 1px solid var(--border);
            padding: 24px 18px;
            position: relative;
        }

        .brand-block {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 8px 22px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 22px;
        }

        .brand-icon {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--gold), var(--gold-soft));
            color: #111;
            font-weight: 900;
        }

        .brand-name {
            font-size: 1.05rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px 14px;
            border-radius: 12px;
            color: var(--muted);
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .nav-link i {
            width: 18px;
            text-align: center;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.02);
            color: var(--text);
        }

        .nav-link.active {
            background: rgba(255, 193, 7, 0.12);
            border: 1px solid var(--border);
            color: var(--gold-soft);
            box-shadow: inset 0 0 0 1px rgba(255, 193, 7, 0.08);
        }

        .sidebar-footer {
            margin-top: 28px;
            padding-top: 18px;
            border-top: 1px solid var(--border);
        }

        .main-panel {
            padding: 32px 28px 40px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 18px 22px;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: rgba(17, 17, 17, 0.92);
            box-shadow: 0 14px 30px var(--shadow);
        }

        .welcome-block {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .mini-label {
            color: var(--gold-soft);
            font-size: 0.73rem;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            font-weight: 700;
        }

        .welcome-title {
            margin: 0;
            font-size: clamp(1.5rem, 2vw, 2.1rem);
            font-weight: 800;
            color: var(--text);
        }

        .welcome-email {
            color: var(--muted);
            margin: 0;
            font-size: 0.95rem;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(39, 174, 96, 0.1);
            border: 1px solid rgba(39, 174, 96, 0.28);
            color: #d9ffe9;
            padding: 10px 14px;
            border-radius: 999px;
            font-size: 0.88rem;
            font-weight: 600;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--success);
            box-shadow: 0 0 10px rgba(39, 174, 96, 0.9);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(180px, 1fr));
            gap: 18px;
            margin-top: 26px;
        }

        .stat-card {
            background: linear-gradient(180deg, #111111 0%, #191919 100%);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 20px 18px;
            box-shadow: 0 14px 30px rgba(0, 0, 0, 0.18);
            transition: transform 0.2s ease, border-color 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            border-color: rgba(255, 193, 7, 0.4);
        }

        .stat-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }

        .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            background: rgba(255, 193, 7, 0.1);
            color: var(--gold-soft);
            font-size: 1.1rem;
        }

        .stat-label {
            color: var(--muted);
            font-size: 0.85rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin: 0;
        }

        .stat-value {
            margin: 0;
            font-size: clamp(1.8rem, 2vw, 2.2rem);
            font-weight: 800;
            color: var(--text);
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(270px, 0.8fr);
            gap: 20px;
            margin-top: 24px;
        }

        .panel-card {
            background: linear-gradient(180deg, rgba(17,17,17,0.98) 0%, rgba(10,10,10,0.96) 100%);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 22px;
            box-shadow: 0 14px 30px rgba(0, 0, 0, 0.15);
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 18px;
        }

        .panel-title {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 800;
        }

        .panel-badge {
            background: rgba(255, 193, 7, 0.12);
            border: 1px solid var(--border);
            color: var(--gold-soft);
            padding: 7px 12px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            min-height: 260px;
            border: 1px dashed rgba(255, 193, 7, 0.36);
            background: rgba(255, 193, 7, 0.025);
            border-radius: 18px;
            padding: 28px 20px;
        }

        .empty-state i {
            font-size: 2.7rem;
            color: var(--gold-soft);
            margin-bottom: 18px;
        }

        .empty-state h3 {
            margin: 0 0 10px;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .empty-state p {
            margin: 0 0 18px;
            color: var(--muted);
            max-width: 300px;
            line-height: 1.6;
        }

        .primary-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: linear-gradient(135deg, var(--gold), var(--gold-soft));
            color: #111;
            border: none;
            border-radius: 12px;
            padding: 12px 18px;
            font-weight: 800;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .primary-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 28px rgba(255, 193, 7, 0.18);
        }

        .profile-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 10px;
        }

        .profile-row {
            display: flex;
            flex-direction: column;
            gap: 4px;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 193, 7, 0.12);
            border-radius: 12px;
            padding: 12px 14px;
        }

        .profile-label {
            color: var(--gold-soft);
            font-size: 0.75rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            font-weight: 700;
        }

        .profile-value {
            color: var(--text);
            font-size: 0.98rem;
            line-height: 1.5;
            word-break: break-word;
        }

        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(180px, 1fr));
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 860px) {
            .dashboard-shell {
                grid-template-columns: 1fr;
            }

            .sidebar {
                border-right: none;
                border-bottom: 1px solid var(--border);
                padding-bottom: 16px;
            }

            .sidebar-nav {
                flex-direction: row;
                flex-wrap: wrap;
            }

            .nav-link {
                width: fit-content;
            }
        }

        @media (max-width: 640px) {
            .main-panel {
                padding: 18px 16px 26px;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .topbar-actions {
                width: 100%;
                justify-content: flex-start;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .panel-card,
            .topbar {
                border-radius: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-shell">
        <aside class="sidebar">
            <div class="brand-block">
                <div class="brand-icon"><i class="fa-solid fa-burger"></i></div>
                <div class="brand-name">Fast Food</div>
            </div>

            <nav class="sidebar-nav" aria-label="Customer navigation">
                <a class="nav-link active" href="dashboard.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
                <a class="nav-link" href="profile.php"><i class="fa-solid fa-user"></i> My Profile</a>
                <a class="nav-link" href="my-orders.php"><i class="fa-solid fa-box-open"></i> My Orders</a>
                <a class="nav-link" href="../auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </nav>

            <div class="sidebar-footer">
                <div class="status-pill"><span class="status-dot"></span> Logged in</div>
            </div>
        </aside>

        <main class="main-panel">
            <header class="topbar">
                <div class="welcome-block">
                    <span class="mini-label">Customer Panel</span>
                    <h1 class="welcome-title">Welcome, <?php echo $customerName; ?></h1>
                    <p class="welcome-email"><?php echo $customerEmail; ?></p>
                </div>

                <div class="topbar-actions">
                    <span class="status-pill"><span class="status-dot"></span> Active customer</span>
                </div>
            </header>

            <section class="stats-grid" aria-label="Customer statistics">
                <article class="stat-card">
                    <div class="stat-head">
                        <p class="stat-label">Total Orders</p>
                        <div class="stat-icon"><i class="fa-solid fa-bag-shopping"></i></div>
                    </div>
                    <p class="stat-value"><?php echo $totalOrders; ?></p>
                </article>

                <article class="stat-card">
                    <div class="stat-head">
                        <p class="stat-label">Pending Orders</p>
                        <div class="stat-icon"><i class="fa-solid fa-clock"></i></div>
                    </div>
                    <p class="stat-value"><?php echo $pendingOrders; ?></p>
                </article>

                <article class="stat-card">
                    <div class="stat-head">
                        <p class="stat-label">Completed Orders</p>
                        <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
                    </div>
                    <p class="stat-value"><?php echo $completedOrders; ?></p>
                </article>

                <article class="stat-card">
                    <div class="stat-head">
                        <p class="stat-label">Total Spent</p>
                        <div class="stat-icon"><i class="fa-solid fa-wallet"></i></div>
                    </div>
                    <p class="stat-value">Rs. <?php echo number_format($totalSpent, 2); ?></p>
                </article>
            </section>

            <section class="dashboard-grid">
                <article class="panel-card">
                    <div class="panel-header">
                        <h2 class="panel-title">Recent Orders</h2>
                        <span class="panel-badge">Status</span>
                    </div>

                    <?php if (empty($recentOrders)): ?>
                        <div class="empty-state">
                            <i class="fa-solid fa-bowl-food"></i>
                            <h3>No orders yet.</h3>
                            <p>Your recent order history will appear here once you place your first order.</p>
                            <a class="primary-btn" href="../index.php#Menu"><i class="fa-solid fa-utensils"></i> Start Ordering</a>
                        </div>
                    <?php else: ?>
                        <div class="recent-orders-list" style="display:flex; flex-direction:column; gap:12px;">
                            <?php foreach ($recentOrders as $recentOrder): ?>
                                <?php
                                $recentOrderId = (int) ($recentOrder['order_id'] ?? 0);
                                $recentOrderNumber = htmlspecialchars((string) ($recentOrder['order_number'] ?? ''), ENT_QUOTES, 'UTF-8');
                                $recentOrderTotal = number_format((float) ($recentOrder['total_amount'] ?? 0.0), 2);
                                $recentOrderStatus = htmlspecialchars((string) ($recentOrder['status'] ?? 'Pending'), ENT_QUOTES, 'UTF-8');
                                $recentOrderDate = htmlspecialchars((string) date('M j, Y', strtotime((string) ($recentOrder['created_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8');
                                ?>
                                <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; padding:12px 14px; border:1px solid rgba(255,193,7,0.12); border-radius:12px; background:rgba(255,255,255,0.02);">
                                    <div>
                                        <div style="font-weight:700; color:#fff;">#<?php echo $recentOrderNumber; ?></div>
                                        <div style="font-size:0.82rem; color:#bdbdbd; margin-top:4px;"><?php echo $recentOrderDate; ?> • Rs. <?php echo $recentOrderTotal; ?></div>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <span class="status-badge status-pending" style="font-size:0.7rem; padding:6px 9px;"><?php echo $recentOrderStatus; ?></span>
                                        <a href="orders.php" class="secondary-btn" style="padding:8px 12px; font-size:0.83rem;">View</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>

                <aside class="panel-card">
                    <div class="panel-header">
                        <h2 class="panel-title">Customer Profile</h2>
                        <span class="panel-badge">Profile</span>
                    </div>

                    <div class="profile-list">
                        <div class="profile-row">
                            <span class="profile-label">Full Name</span>
                            <span class="profile-value"><?php echo $customerName; ?></span>
                        </div>

                        <div class="profile-row">
                            <span class="profile-label">Email</span>
                            <span class="profile-value"><?php echo $customerEmail; ?></span>
                        </div>

                        <div class="profile-row">
                            <span class="profile-label">Phone</span>
                            <span class="profile-value"><?php echo $displayPhone; ?></span>
                        </div>

                        <div class="profile-row">
                            <span class="profile-label">Address</span>
                            <span class="profile-value"><?php echo $displayAddress; ?></span>
                        </div>
                    </div>
                </aside>
            </section>
        </main>
    </div>
</body>
</html>
