<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$adminName = htmlspecialchars((string) ($_SESSION['admin_name'] ?? 'Administrator'), ENT_QUOTES, 'UTF-8');
$adminEmail = htmlspecialchars((string) ($_SESSION['admin_email'] ?? ''), ENT_QUOTES, 'UTF-8');

$stats = [
    'customers' => 0,
    'orders' => 0,
    'pending' => 0,
    'completed' => 0,
    'revenue' => 0.0,
];

$recentOrders = [];
$recentCustomers = [];
$statusBreakdown = [
    'Pending' => 0,
    'Processing' => 0,
    'Completed' => 0,
    'Cancelled' => 0,
];

try {
    $connection = get_db_connection();

    $customersQuery = $connection->query('SELECT COUNT(*) AS total_customers FROM customers');
    if ($customersQuery && $customersQuery->num_rows > 0) {
        $stats['customers'] = (int) $customersQuery->fetch_assoc()['total_customers'];
    }

    $ordersQuery = $connection->query('SELECT COUNT(*) AS total_orders FROM orders');
    if ($ordersQuery && $ordersQuery->num_rows > 0) {
        $stats['orders'] = (int) $ordersQuery->fetch_assoc()['total_orders'];
    }

    $pendingQuery = $connection->query('SELECT COUNT(*) AS pending_orders FROM orders WHERE status = "Pending"');
    if ($pendingQuery && $pendingQuery->num_rows > 0) {
        $stats['pending'] = (int) $pendingQuery->fetch_assoc()['pending_orders'];
    }

    $completedQuery = $connection->query('SELECT COUNT(*) AS completed_orders FROM orders WHERE status = "Delivered"');
    if ($completedQuery && $completedQuery->num_rows > 0) {
        $stats['completed'] = (int) $completedQuery->fetch_assoc()['completed_orders'];
    }

    $revenueQuery = $connection->query('SELECT COALESCE(SUM(total_amount), 0) AS total_revenue FROM orders');
    if ($revenueQuery && $revenueQuery->num_rows > 0) {
        $stats['revenue'] = (float) $revenueQuery->fetch_assoc()['total_revenue'];
    }

    $recentOrdersQuery = $connection->query(
        'SELECT o.order_id, c.full_name AS customer_name, o.total_amount, o.status, o.created_at
         FROM orders o
         LEFT JOIN customers c ON c.customer_id = o.customer_id
         ORDER BY o.created_at DESC
         LIMIT 6'
    );
    if ($recentOrdersQuery) {
        while ($row = $recentOrdersQuery->fetch_assoc()) {
            $recentOrders[] = $row;
        }
    }

    $recentCustomersQuery = $connection->query(
        'SELECT full_name, email, phone, created_at
         FROM customers
         ORDER BY created_at DESC
         LIMIT 5'
    );
    if ($recentCustomersQuery) {
        while ($row = $recentCustomersQuery->fetch_assoc()) {
            $recentCustomers[] = $row;
        }
    }

    $statusQuery = $connection->query('SELECT status, COUNT(*) AS total FROM orders GROUP BY status');
    if ($statusQuery) {
        while ($statusRow = $statusQuery->fetch_assoc()) {
            $status = (string) $statusRow['status'];

            if ($status === 'Pending') {
                $statusBreakdown['Pending'] = (int) $statusRow['total'];
            } elseif (in_array($status, ['Confirmed', 'Preparing', 'Out for Delivery'], true)) {
                $statusBreakdown['Processing'] += (int) $statusRow['total'];
            } elseif ($status === 'Delivered') {
                $statusBreakdown['Completed'] = (int) $statusRow['total'];
            } elseif ($status === 'Cancelled') {
                $statusBreakdown['Cancelled'] = (int) $statusRow['total'];
            }
        }
    }

    $connection->close();
} catch (Throwable $exception) {
    $stats = [
        'customers' => 0,
        'orders' => 0,
        'pending' => 0,
        'completed' => 0,
        'revenue' => 0.0,
    ];
    $recentOrders = [];
    $recentCustomers = [];
    $statusBreakdown = [
        'Pending' => 0,
        'Processing' => 0,
        'Completed' => 0,
        'Cancelled' => 0,
    ];
}

function format_currency(float $value): string
{
    return 'Rs. ' . number_format($value, 2, '.', ',');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Fast Food</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
        integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        :root {
            --bg: #0d0d0e;
            --sidebar: #121214;
            --panel: #17171a;
            --panel-soft: #1d1d20;
            --panel-elevated: #212126;
            --gold: #d4af37;
            --gold-soft: #f5d76e;
            --text: #f6f6f6;
            --muted: #c8c8c8;
            --border: rgba(212, 175, 55, 0.32);
            --success: #34d399;
            --warning: #fbbf24;
            --danger: #f87171;
            --shadow: rgba(0, 0, 0, 0.28);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(180deg, #111214, #0d0d0e 35%, #17171a 100%);
            color: var(--text);
            font-family: Arial, sans-serif;
        }

        a { text-decoration: none; }

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 260px;
            background: var(--sidebar);
            border-right: 1px solid var(--border);
            position: sticky;
            top: 0;
            height: 100vh;
            padding: 22px 18px;
            transition: transform 0.3s ease;
        }

        .brand-block {
            padding: 10px 10px 22px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 18px;
        }

        .brand-block .logo {
            display: block;
            color: var(--gold-soft);
            font-size: 0.8rem;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .brand-block .admin-label {
            color: var(--muted);
            font-size: 0.8rem;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .nav-menu {
            display: grid;
            gap: 8px;
            margin-top: 18px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            color: var(--text);
            border-radius: 12px;
            border: 1px solid transparent;
            transition: all 0.2s ease;
            font-weight: 600;
        }

        .nav-link i {
            width: 18px;
            display: inline-flex;
            justify-content: center;
            color: var(--gold-soft);
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(212, 175, 55, 0.1);
            border-color: var(--border);
            color: var(--gold-soft);
        }

        .nav-link.logout-link {
            margin-top: 18px;
            background: rgba(212, 175, 55, 0.08);
            border-color: var(--border);
        }

        .main-panel {
            flex: 1;
            min-width: 0;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 24px 28px 18px;
            border-bottom: 1px solid var(--border);
            background: rgba(17, 18, 20, 0.4);
            position: sticky;
            top: 0;
            backdrop-filter: blur(8px);
            z-index: 5;
        }

        .page-title {
            font-size: clamp(1.7rem, 2.5vw, 2.3rem);
            margin: 0;
        }

        .header-user {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .welcome-block {
            text-align: right;
        }

        .welcome-label {
            display: block;
            color: var(--muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
        }

        .welcome-name {
            font-weight: 700;
            color: var(--gold-soft);
            font-size: 1rem;
        }

        .welcome-email {
            color: var(--muted);
            font-size: 0.82rem;
        }

        .avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--gold), var(--gold-soft));
            color: #111;
            font-weight: 700;
            border: 1px solid rgba(212, 175, 55, 0.5);
        }

        .logout-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--gold), var(--gold-soft));
            color: #111;
            border: none;
            padding: 10px 16px;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
        }

        .content {
            padding: 28px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: linear-gradient(180deg, var(--panel), var(--panel-soft));
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 18px 18px 16px;
            box-shadow: 0 12px 28px var(--shadow);
        }

        .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(212, 175, 55, 0.1);
            color: var(--gold-soft);
            margin-bottom: 14px;
            border: 1px solid var(--border);
        }

        .stat-label {
            display: block;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.12em;
            font-size: 0.72rem;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: clamp(1.5rem, 2.5vw, 2.1rem);
            font-weight: 700;
            line-height: 1.2;
        }

        .panel-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.6fr) minmax(300px, 0.9fr);
            gap: 22px;
            margin-bottom: 28px;
        }

        .panel-card {
            background: linear-gradient(180deg, var(--panel), var(--panel-soft));
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: 0 12px 30px var(--shadow);
            overflow: hidden;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 20px;
            border-bottom: 1px solid var(--border);
        }

        .panel-title {
            margin: 0;
            font-size: 1.1rem;
        }

        .panel-link {
            color: var(--gold-soft);
            font-weight: 700;
            font-size: 0.85rem;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 620px;
        }

        th, td {
            padding: 14px 18px;
            border-bottom: 1px solid rgba(212, 175, 55, 0.15);
            text-align: left;
            vertical-align: middle;
        }

        th {
            color: var(--gold-soft);
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            background: rgba(212, 175, 55, 0.04);
        }

        td {
            color: var(--text);
            font-size: 0.96rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .status-pending { background: rgba(251, 191, 36, 0.12); color: var(--warning); border-color: rgba(251, 191, 36, 0.28); }
        .status-confirmed { background: rgba(52, 211, 153, 0.1); color: var(--success); border-color: rgba(52, 211, 153, 0.25); }
        .status-preparing { background: rgba(59, 130, 246, 0.12); color: #7dd3fc; border-color: rgba(59, 130, 246, 0.3); }
        .status-delivered { background: rgba(34, 197, 94, 0.12); color: var(--success); border-color: rgba(34, 197, 94, 0.25); }
        .status-cancelled { background: rgba(248, 113, 113, 0.12); color: var(--danger); border-color: rgba(248, 113, 113, 0.28); }

        .btn-ghost {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: rgba(212, 175, 55, 0.08);
            color: var(--gold-soft);
            font-size: 0.8rem;
            font-weight: 700;
        }

        .quick-actions {
            display: grid;
            gap: 12px;
            padding: 18px 20px 20px;
        }

        .quick-btn {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border);
            color: var(--text);
            font-weight: 700;
            transition: transform 0.2s ease, border-color 0.2s ease;
        }

        .quick-btn:hover {
            transform: translateY(-1px);
            border-color: rgba(212, 175, 55, 0.7);
        }

        .customer-list {
            padding: 0 0 14px;
        }

        .customer-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 20px;
            border-bottom: 1px solid rgba(212, 175, 55, 0.13);
        }

        .customer-name {
            font-weight: 700;
        }

        .customer-meta {
            color: var(--muted);
            font-size: 0.85rem;
            margin-top: 2px;
            line-height: 1.4;
        }

        .status-overview {
            padding: 18px 20px 20px;
            display: grid;
            gap: 16px;
        }

        .status-row {
            display: grid;
            gap: 8px;
        }

        .status-head {
            display: flex;
            justify-content: space-between;
            color: var(--muted);
            font-size: 0.85rem;
        }

        .status-bar {
            height: 9px;
            width: 100%;
            background: rgba(255, 255, 255, 0.04);
            border-radius: 999px;
            overflow: hidden;
            border: 1px solid rgba(212, 175, 55, 0.18);
        }

        .status-bar span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, var(--gold), var(--gold-soft));
        }

        .status-row[data-status="Pending"] .status-bar span { background: linear-gradient(90deg, #fbbf24, #fcd34d); }
        .status-row[data-status="Processing"] .status-bar span { background: linear-gradient(90deg, #60a5fa, #7dd3fc); }
        .status-row[data-status="Completed"] .status-bar span { background: linear-gradient(90deg, #34d399, #6ee7b7); }
        .status-row[data-status="Cancelled"] .status-bar span { background: linear-gradient(90deg, #f87171, #fca5a5); }

        .empty-state {
            padding: 24px 20px;
            color: var(--muted);
        }

        .menu-toggle {
            display: none;
        }

        @media (max-width: 980px) {
            .panel-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 820px) {
            .admin-layout {
                display: block;
            }

            .sidebar {
                position: fixed;
                left: 0;
                top: 0;
                transform: translateX(-100%);
                z-index: 20;
                height: 100vh;
                width: 260px;
                box-shadow: 0 18px 38px rgba(0, 0, 0, 0.35);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .menu-toggle {
                display: inline-flex;
                width: 44px;
                height: 44px;
                border-radius: 10px;
                border: 1px solid var(--border);
                background: rgba(212, 175, 55, 0.08);
                color: var(--gold-soft);
                align-items: center;
                justify-content: center;
                cursor: pointer;
            }

            .topbar {
                padding: 18px 20px;
            }

            .content {
                padding: 20px;
            }
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
                <a class="nav-link active" href="dashboard.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
                <a class="nav-link" href="customers.php"><i class="fa-solid fa-users"></i> Customers</a>
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
                    <button class="menu-toggle" type="button" aria-label="Toggle sidebar" id="menuToggle">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <h1 class="page-title">Dashboard</h1>
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
                <section class="stats-grid" aria-label="Admin statistics">
                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
                        <span class="stat-label">Total Customers</span>
                        <div class="stat-value"><?php echo number_format($stats['customers']); ?></div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-bag-shopping"></i></div>
                        <span class="stat-label">Total Orders</span>
                        <div class="stat-value"><?php echo number_format($stats['orders']); ?></div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-clock"></i></div>
                        <span class="stat-label">Pending Orders</span>
                        <div class="stat-value"><?php echo number_format($stats['pending']); ?></div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
                        <span class="stat-label">Completed Orders</span>
                        <div class="stat-value"><?php echo number_format($stats['completed']); ?></div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-money-bill-wave"></i></div>
                        <span class="stat-label">Total Revenue</span>
                        <div class="stat-value"><?php echo format_currency((float) $stats['revenue']); ?></div>
                    </article>
                </section>

                <section class="panel-grid">
                    <article class="panel-card">
                        <div class="panel-header">
                            <h2 class="panel-title">Recent Orders</h2>
                            <a class="panel-link" href="orders.php">View All Orders</a>
                        </div>

                        <?php if ($recentOrders === []): ?>
                            <div class="empty-state">No orders have been placed yet.</div>
                        <?php else: ?>
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Customer</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentOrders as $order): ?>
                                            <?php
                                                $orderId = (int) ($order['order_id'] ?? 0);
                                                $customerName = htmlspecialchars((string) ($order['customer_name'] ?? 'Unknown Customer'), ENT_QUOTES, 'UTF-8');
                                                $totalAmount = (float) ($order['total_amount'] ?? 0.0);
                                                $status = (string) ($order['status'] ?? 'Pending');
                                                $statusClass = strtolower(str_replace([' ', '-'], '', $status));
                                                $createdAt = htmlspecialchars((string) ($order['created_at'] ?? ''), ENT_QUOTES, 'UTF-8');
                                            ?>
                                            <tr>
                                                <td>#<?php echo $orderId; ?></td>
                                                <td><?php echo $customerName; ?></td>
                                                <td><?php echo format_currency($totalAmount); ?></td>
                                                <td><span class="status-badge status-<?php echo $statusClass; ?>"><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                                <td><?php echo htmlspecialchars(date('M j, Y', strtotime($createdAt ?: 'now')), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><a class="btn-ghost" href="orders.php">View</a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </article>

                    <article class="panel-card">
                        <div class="panel-header">
                            <h2 class="panel-title">Order Status Overview</h2>
                        </div>
                        <div class="status-overview">
                            <?php foreach (['Pending', 'Processing', 'Completed', 'Cancelled'] as $statusName): ?>
                                <?php
                                    $displayValue = (int) ($statusBreakdown[$statusName] ?? 0);
                                    $totalStatusOrders = max((int) $stats['orders'], 1);
                                    $percent = $totalStatusOrders > 0 ? (int) round(($displayValue / $totalStatusOrders) * 100) : 0;
                                ?>
                                <div class="status-row" data-status="<?php echo htmlspecialchars($statusName, ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="status-head">
                                        <span><?php echo htmlspecialchars($statusName, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <strong><?php echo number_format($displayValue); ?></strong>
                                    </div>
                                    <div class="status-bar"><span style="width: <?php echo $percent; ?>%;"></span></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </article>
                </section>

                <section class="panel-grid">
                    <article class="panel-card">
                        <div class="panel-header">
                            <h2 class="panel-title">Recent Customers</h2>
                            <a class="panel-link" href="customers.php">View All Customers</a>
                        </div>

                        <?php if ($recentCustomers === []): ?>
                            <div class="empty-state">No customers are registered yet.</div>
                        <?php else: ?>
                            <div class="customer-list">
                                <?php foreach ($recentCustomers as $customer): ?>
                                    <?php
                                        $customerName = htmlspecialchars((string) ($customer['full_name'] ?? 'Unknown Customer'), ENT_QUOTES, 'UTF-8');
                                        $customerEmail = htmlspecialchars((string) ($customer['email'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        $customerPhone = htmlspecialchars((string) ($customer['phone'] ?? 'N/A'), ENT_QUOTES, 'UTF-8');
                                        $customerDate = htmlspecialchars(date('M j, Y', strtotime((string) ($customer['created_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <div class="customer-row">
                                        <div>
                                            <div class="customer-name"><?php echo $customerName; ?></div>
                                            <div class="customer-meta"><?php echo $customerEmail; ?><br><?php echo $customerPhone; ?></div>
                                        </div>
                                        <div class="customer-meta"><?php echo $customerDate; ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>

                    <article class="panel-card">
                        <div class="panel-header">
                            <h2 class="panel-title">Quick Actions</h2>
                        </div>
                        <div class="quick-actions">
                            <a class="quick-btn" href="customers.php"><span>Manage Customers</span><i class="fa-solid fa-arrow-right"></i></a>
                            <a class="quick-btn" href="orders.php"><span>Manage Orders</span><i class="fa-solid fa-arrow-right"></i></a>
                            <a class="quick-btn" href="menu.php"><span>Manage Menu</span><i class="fa-solid fa-arrow-right"></i></a>
                        </div>
                    </article>
                </section>
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
