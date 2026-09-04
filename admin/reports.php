<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';

function bind_dynamic_params(mysqli_stmt $statement, string $types, array $params): void
{
    if ($params === []) {
        return;
    }

    $references = [];
    foreach ($params as $key => $value) {
        $references[$key] = &$params[$key];
    }

    $callArgs = array_merge([$types], $references);
    call_user_func_array([$statement, 'bind_param'], $callArgs);
}

function format_currency(float $value): string
{
    return 'PKR ' . number_format($value, 2, '.', ',');
}

$adminName = htmlspecialchars((string) ($_SESSION['admin_name'] ?? 'Administrator'), ENT_QUOTES, 'UTF-8');
$adminEmail = htmlspecialchars((string) ($_SESSION['admin_email'] ?? ''), ENT_QUOTES, 'UTF-8');

$allowedStatuses = ['Pending', 'Confirmed', 'Preparing', 'Out for Delivery', 'Delivered', 'Cancelled'];

$selectedDateFrom = isset($_GET['date_from']) ? trim((string) $_GET['date_from']) : '';
$selectedDateTo = isset($_GET['date_to']) ? trim((string) $_GET['date_to']) : '';
$statusFilter = isset($_GET['status']) ? trim((string) $_GET['status']) : 'All';
$paymentFilter = isset($_GET['payment_method']) ? trim((string) $_GET['payment_method']) : 'All';

if ($statusFilter !== 'All' && ! in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'All';
}

$whereClauses = ['1=1'];
$queryParams = [];
$queryTypes = '';

if ($selectedDateFrom !== '') {
    $whereClauses[] = 'DATE(o.created_at) >= ?';
    $queryParams[] = $selectedDateFrom;
    $queryTypes .= 's';
}

if ($selectedDateTo !== '') {
    $whereClauses[] = 'DATE(o.created_at) <= ?';
    $queryParams[] = $selectedDateTo;
    $queryTypes .= 's';
}

if ($statusFilter !== 'All') {
    $whereClauses[] = 'o.status = ?';
    $queryParams[] = $statusFilter;
    $queryTypes .= 's';
}

if ($paymentFilter !== 'All') {
    $whereClauses[] = 'o.payment_method = ?';
    $queryParams[] = $paymentFilter;
    $queryTypes .= 's';
}

$baseOrderWhere = implode(' AND ', $whereClauses);

$summary = [
    'total_orders' => 0,
    'completed_orders' => 0,
    'pending_orders' => 0,
    'cancelled_orders' => 0,
    'total_revenue' => 0.0,
];

$salesSummary = [
    'subtotal' => 0.0,
    'delivery_charges' => 0.0,
    'discounts' => 0.0,
    'final_revenue' => 0.0,
];

$recentOrders = [];
$topSellingItems = [];
$paymentMethods = [];

try {
    $connection = get_db_connection();

    $summarySql = 'SELECT
        COUNT(*) AS total_orders,
        SUM(CASE WHEN o.status = "Delivered" THEN 1 ELSE 0 END) AS completed_orders,
        SUM(CASE WHEN o.status = "Pending" THEN 1 ELSE 0 END) AS pending_orders,
        SUM(CASE WHEN o.status = "Cancelled" THEN 1 ELSE 0 END) AS cancelled_orders,
        COALESCE(SUM(o.total_amount), 0) AS total_revenue
        FROM orders o
        WHERE ' . $baseOrderWhere;

    $summaryStmt = $connection->prepare($summarySql);
    if ($summaryStmt !== false) {
        bind_dynamic_params($summaryStmt, $queryTypes, $queryParams);
        $summaryStmt->execute();
        $summaryResult = $summaryStmt->get_result();
        if ($summaryResult && $summaryResult->num_rows > 0) {
            $summaryRow = $summaryResult->fetch_assoc();
            $summary['total_orders'] = (int) ($summaryRow['total_orders'] ?? 0);
            $summary['completed_orders'] = (int) ($summaryRow['completed_orders'] ?? 0);
            $summary['pending_orders'] = (int) ($summaryRow['pending_orders'] ?? 0);
            $summary['cancelled_orders'] = (int) ($summaryRow['cancelled_orders'] ?? 0);
            $summary['total_revenue'] = (float) ($summaryRow['total_revenue'] ?? 0.0);
        }
        $summaryStmt->close();
    }

    $salesSql = 'SELECT
        COALESCE(SUM(o.subtotal), 0) AS subtotal,
        COALESCE(SUM(o.delivery_charges), 0) AS delivery_charges,
        COALESCE(SUM(o.discount_amount), 0) AS discounts,
        COALESCE(SUM(o.total_amount), 0) AS final_revenue
        FROM orders o
        WHERE ' . $baseOrderWhere;

    $salesStmt = $connection->prepare($salesSql);
    if ($salesStmt !== false) {
        bind_dynamic_params($salesStmt, $queryTypes, $queryParams);
        $salesStmt->execute();
        $salesResult = $salesStmt->get_result();
        if ($salesResult && $salesResult->num_rows > 0) {
            $salesRow = $salesResult->fetch_assoc();
            $salesSummary['subtotal'] = (float) ($salesRow['subtotal'] ?? 0.0);
            $salesSummary['delivery_charges'] = (float) ($salesRow['delivery_charges'] ?? 0.0);
            $salesSummary['discounts'] = (float) ($salesRow['discounts'] ?? 0.0);
            $salesSummary['final_revenue'] = (float) ($salesRow['final_revenue'] ?? 0.0);
        }
        $salesStmt->close();
    }

    $paymentMethodsSql = 'SELECT DISTINCT payment_method FROM orders WHERE payment_method IS NOT NULL AND payment_method != "" ORDER BY payment_method ASC';
    $paymentMethodsResult = $connection->query($paymentMethodsSql);
    if ($paymentMethodsResult) {
        while ($row = $paymentMethodsResult->fetch_assoc()) {
            $paymentMethods[] = (string) ($row['payment_method'] ?? '');
        }
    }

    $topSellingSql = 'SELECT
        COALESCE(m.name, oi.item_name) AS item_name,
        SUM(oi.quantity) AS quantity_sold,
        COALESCE(SUM(oi.item_total), 0) AS revenue
        FROM order_items oi
        LEFT JOIN menu_items m ON m.menu_id = oi.food_id
        INNER JOIN orders o ON o.order_id = oi.order_id
        WHERE ' . $baseOrderWhere . '
        GROUP BY COALESCE(m.name, oi.item_name)
        ORDER BY quantity_sold DESC, revenue DESC
        LIMIT 5';

    $topSellingStmt = $connection->prepare($topSellingSql);
    if ($topSellingStmt !== false) {
        bind_dynamic_params($topSellingStmt, $queryTypes, $queryParams);
        $topSellingStmt->execute();
        $topResult = $topSellingStmt->get_result();
        while ($row = $topResult->fetch_assoc()) {
            $topSellingItems[] = [
                'item_name' => (string) ($row['item_name'] ?? 'Unknown Item'),
                'quantity_sold' => (int) ($row['quantity_sold'] ?? 0),
                'revenue' => (float) ($row['revenue'] ?? 0.0),
            ];
        }
        $topSellingStmt->close();
    }

    $recentOrdersSql = 'SELECT o.order_id, c.full_name AS customer_name, o.total_amount, o.payment_method, o.status, o.created_at
        FROM orders o
        LEFT JOIN customers c ON c.customer_id = o.customer_id
        WHERE ' . $baseOrderWhere . '
        ORDER BY o.created_at DESC
        LIMIT 6';

    $recentStmt = $connection->prepare($recentOrdersSql);
    if ($recentStmt !== false) {
        bind_dynamic_params($recentStmt, $queryTypes, $queryParams);
        $recentStmt->execute();
        $recentResult = $recentStmt->get_result();
        while ($row = $recentResult->fetch_assoc()) {
            $recentOrders[] = [
                'order_id' => (int) ($row['order_id'] ?? 0),
                'customer_name' => (string) ($row['customer_name'] ?? 'Unknown Customer'),
                'total_amount' => (float) ($row['total_amount'] ?? 0.0),
                'payment_method' => (string) ($row['payment_method'] ?? 'Cash on Delivery'),
                'status' => (string) ($row['status'] ?? 'Pending'),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }
        $recentStmt->close();
    }

    $connection->close();
} catch (Throwable $exception) {
    $summary = [
        'total_orders' => 0,
        'completed_orders' => 0,
        'pending_orders' => 0,
        'cancelled_orders' => 0,
        'total_revenue' => 0.0,
    ];
    $salesSummary = [
        'subtotal' => 0.0,
        'delivery_charges' => 0.0,
        'discounts' => 0.0,
        'final_revenue' => 0.0,
    ];
    $recentOrders = [];
    $topSellingItems = [];
    $paymentMethods = [];
}

$hasOrders = $summary['total_orders'] > 0;
$showNoOrdersMessage = ! $hasOrders;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | Fast Food Admin</title>
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
            --success: #34d399;
            --warning: #fbbf24;
            --danger: #f87171;
            --info: #60a5fa;
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
        .panel-title { margin: 0; font-size: 1.08rem; }
        .filters { display: flex; gap: 12px; align-items: end; padding: 14px 18px; flex-wrap: wrap; }
        .field { display: flex; flex-direction: column; gap: 8px; min-width: 0; }
        .field label { color: var(--gold-soft); text-transform: uppercase; letter-spacing: 0.08em; font-size: 0.7rem; font-weight: 700; }
        .field input, .field select { min-height: 42px; width: 100%; border-radius: 10px; border: 1px solid var(--border); background: rgba(255,255,255,0.02); color: var(--text); padding: 10px 12px; font: inherit; }
        .field input:focus, .field select:focus { outline: none; border-color: var(--gold-soft); box-shadow: 0 0 0 3px rgba(212,175,55,0.12); }
        .filters .field--date { flex: 1 1 160px; }
        .filters .field--select { flex: 1 1 180px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 42px; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--border); background: rgba(212,175,55,0.08); color: var(--gold-soft); font-weight: 700; cursor: pointer; }
        .btn.primary { background: linear-gradient(135deg, var(--gold), var(--gold-soft)); color: #111; border-color: rgba(212,175,55,0.8); }
        .btn.secondary { background: rgba(255,255,255,0.02); }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; padding: 18px; }
        .summary-card { background: linear-gradient(180deg, var(--panel), var(--panel-soft)); border: 1px solid var(--border); border-radius: 16px; padding: 18px 18px 14px; box-shadow: 0 8px 20px var(--shadow); }
        .summary-icon { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: rgba(212, 175, 55, 0.1); color: var(--gold-soft); margin-bottom: 12px; border: 1px solid var(--border); }
        .summary-label { display: block; color: var(--muted); text-transform: uppercase; letter-spacing: 0.12em; font-size: 0.7rem; margin-bottom: 8px; }
        .summary-value { font-size: clamp(1.6rem, 2.4vw, 2rem); font-weight: 700; line-height: 1.2; }
        .report-grid { display: grid; grid-template-columns: 1.2fr 1fr; gap: 18px; padding: 0 18px 18px; }
        .stats-panel { background: linear-gradient(180deg, var(--panel), var(--panel-soft)); border: 1px solid var(--border); border-radius: 16px; padding: 18px; }
        .stats-panel h3, .table-panel h3 { margin: 0 0 14px; font-size: 1.05rem; }
        .sales-list { display: grid; gap: 12px; }
        .sales-item { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid rgba(212,175,55,0.12); }
        .sales-item:last-child { border-bottom: none; }
        .sales-item .label { color: var(--muted); }
        .sales-item .value { font-weight: 700; color: var(--text); }
        .table-panel { background: linear-gradient(180deg, var(--panel), var(--panel-soft)); border: 1px solid var(--border); border-radius: 16px; padding: 18px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; min-width: 700px; border-collapse: collapse; }
        th, td { padding: 12px 14px; border-bottom: 1px solid rgba(212,175,55,0.12); text-align: left; vertical-align: middle; }
        th { color: var(--gold-soft); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.12em; }
        td { color: var(--text); }
        .status-badge { display: inline-flex; align-items: center; justify-content: center; padding: 6px 10px; border-radius: 999px; font-size: 0.68rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; border: 1px solid rgba(255,255,255,0.1); }
        .status-pending { background: rgba(251, 191, 36, 0.12); color: var(--warning); border-color: rgba(251, 191, 36, 0.25); }
        .status-confirmed { background: rgba(52, 211, 153, 0.12); color: var(--success); border-color: rgba(52, 211, 153, 0.25); }
        .status-preparing { background: rgba(96, 165, 250, 0.12); color: var(--info); border-color: rgba(96, 165, 250, 0.25); }
        .status-out { background: rgba(168, 85, 247, 0.12); color: #c084fc; border-color: rgba(168, 85, 247, 0.25); }
        .status-delivered { background: rgba(52, 211, 153, 0.12); color: var(--success); border-color: rgba(52, 211, 153, 0.25); }
        .status-cancelled { background: rgba(248, 113, 113, 0.12); color: var(--danger); border-color: rgba(248, 113, 113, 0.25); }
        .small-btn { display: inline-flex; align-items: center; justify-content: center; min-height: 34px; padding: 8px 12px; border-radius: 10px; border: 1px solid var(--border); background: rgba(212,175,55,0.09); color: var(--gold-soft); font-weight: 700; }
        .empty-state { padding: 22px 18px; text-align: center; color: var(--muted); }
        .filter-summary { padding: 0 18px 18px; color: var(--muted); font-size: 0.9rem; }
        .menu-toggle { display: none; }
        @media (max-width: 980px) {
            .report-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 820px) {
            .admin-layout { display: block; }
            .sidebar { position: fixed; left: 0; top: 0; transform: translateX(-100%); z-index: 20; height: 100vh; width: 260px; box-shadow: 0 18px 38px rgba(0,0,0,0.35); }
            .sidebar.open { transform: translateX(0); }
            .menu-toggle { display: inline-flex; width: 44px; height: 44px; border-radius: 10px; border: 1px solid var(--border); background: rgba(212,175,55,0.08); color: var(--gold-soft); align-items: center; justify-content: center; cursor: pointer; }
            .topbar { padding: 18px 20px; }
            .content { padding: 20px; }
            .filters { flex-direction: column; align-items: stretch; }
            .field, .field--date, .field--select { width: 100%; }
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
            <a class="nav-link" href="customers.php"><i class="fa-solid fa-users"></i> Customers</a>
            <a class="nav-link" href="orders.php"><i class="fa-solid fa-box-open"></i> Orders</a>
            <a class="nav-link" href="menu.php"><i class="fa-solid fa-burger"></i> Menu</a>
            <a class="nav-link active" href="reports.php"><i class="fa-solid fa-chart-column"></i> Reports</a>
            <a class="nav-link" href="profile.php"><i class="fa-solid fa-user-gear"></i> Profile</a>
            <a class="nav-link logout-link" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-panel">
        <header class="topbar">
            <div style="display:flex; align-items:center; gap:12px;">
                <button class="menu-toggle" type="button" aria-label="Toggle sidebar" id="menuToggle"><i class="fa-solid fa-bars"></i></button>
                <h1 class="page-title">Reports</h1>
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
                    <h2 class="panel-title">Sales Overview</h2>
                </div>

                <form method="GET" action="reports.php" class="filters" aria-label="Report filters">
                    <div class="field field--date">
                        <label for="dateFrom">From Date</label>
                        <input id="dateFrom" type="date" name="date_from" value="<?php echo htmlspecialchars($selectedDateFrom, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="field field--date">
                        <label for="dateTo">To Date</label>
                        <input id="dateTo" type="date" name="date_to" value="<?php echo htmlspecialchars($selectedDateTo, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="field field--select">
                        <label for="statusFilter">Status</label>
                        <select id="statusFilter" name="status">
                            <option value="All" <?php echo $statusFilter === 'All' ? 'selected' : ''; ?>>All Statuses</option>
                            <?php foreach ($allowedStatuses as $status): ?>
                                <option value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field field--select">
                        <label for="paymentFilter">Payment</label>
                        <select id="paymentFilter" name="payment_method">
                            <option value="All" <?php echo $paymentFilter === 'All' ? 'selected' : ''; ?>>All Payment Methods</option>
                            <?php foreach ($paymentMethods as $method): ?>
                                <option value="<?php echo htmlspecialchars($method, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $paymentFilter === $method ? 'selected' : ''; ?>><?php echo htmlspecialchars($method, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn primary" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
                    <a class="btn secondary" href="reports.php"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                </form>

                <?php if ($selectedDateFrom !== '' || $selectedDateTo !== '' || $statusFilter !== 'All' || $paymentFilter !== 'All'): ?>
                    <div class="filter-summary">
                        Showing report for the current filters.
                    </div>
                <?php endif; ?>

                <div class="summary-grid">
                    <article class="summary-card">
                        <div class="summary-icon"><i class="fa-solid fa-receipt"></i></div>
                        <span class="summary-label">Total Orders</span>
                        <div class="summary-value"><?php echo number_format($summary['total_orders']); ?></div>
                    </article>
                    <article class="summary-card">
                        <div class="summary-icon"><i class="fa-solid fa-circle-check"></i></div>
                        <span class="summary-label">Delivered</span>
                        <div class="summary-value"><?php echo number_format($summary['completed_orders']); ?></div>
                    </article>
                    <article class="summary-card">
                        <div class="summary-icon"><i class="fa-solid fa-clock"></i></div>
                        <span class="summary-label">Pending</span>
                        <div class="summary-value"><?php echo number_format($summary['pending_orders']); ?></div>
                    </article>
                    <article class="summary-card">
                        <div class="summary-icon"><i class="fa-solid fa-ban"></i></div>
                        <span class="summary-label">Cancelled</span>
                        <div class="summary-value"><?php echo number_format($summary['cancelled_orders']); ?></div>
                    </article>
                    <article class="summary-card">
                        <div class="summary-icon"><i class="fa-solid fa-money-bill-wave"></i></div>
                        <span class="summary-label">Total Revenue</span>
                        <div class="summary-value"><?php echo format_currency((float) $summary['total_revenue']); ?></div>
                    </article>
                </div>
            </article>

            <?php if ($showNoOrdersMessage): ?>
                <div class="empty-state" style="margin-top:18px; background: linear-gradient(180deg, var(--panel), var(--panel-soft)); border: 1px solid var(--border); border-radius: 16px; padding: 22px;">
                    No orders found for the selected filters.
                </div>
            <?php else: ?>
                <div class="report-grid" style="margin-top:18px;">
                    <section class="stats-panel">
                        <h3>Sales Summary</h3>
                        <div class="sales-list">
                            <div class="sales-item">
                                <span class="label">Gross/Subtotal Sales</span>
                                <span class="value"><?php echo format_currency((float) $salesSummary['subtotal']); ?></span>
                            </div>
                            <div class="sales-item">
                                <span class="label">Delivery Fees</span>
                                <span class="value"><?php echo format_currency((float) $salesSummary['delivery_charges']); ?></span>
                            </div>
                            <div class="sales-item">
                                <span class="label">Discounts</span>
                                <span class="value"><?php echo format_currency((float) $salesSummary['discounts']); ?></span>
                            </div>
                            <div class="sales-item">
                                <span class="label">Final Revenue</span>
                                <span class="value"><?php echo format_currency((float) $salesSummary['final_revenue']); ?></span>
                            </div>
                        </div>
                    </section>

                    <section class="stats-panel">
                        <h3>Top Selling Items</h3>
                        <?php if ($topSellingItems === []): ?>
                            <div class="empty-state">No item sales data available.</div>
                        <?php else: ?>
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Food Item</th>
                                            <th>Qty Sold</th>
                                            <th>Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topSellingItems as $item): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars((string) ($item['item_name'] ?? 'Unknown Item'), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo number_format((int) ($item['quantity_sold'] ?? 0)); ?></td>
                                                <td><?php echo format_currency((float) ($item['revenue'] ?? 0.0)); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>

                <div class="table-panel" style="margin:0 18px 18px;">
                    <h3>Recent Orders</h3>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Total</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                    <th>Order Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recentOrders === []): ?>
                                    <tr>
                                        <td colspan="7" class="empty-state">No recent orders available.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentOrders as $order): ?>
                                        <?php
                                            $statusClass = 'status-pending';
                                            $status = (string) ($order['status'] ?? 'Pending');
                                            if ($status === 'Confirmed') { $statusClass = 'status-confirmed'; }
                                            elseif ($status === 'Preparing') { $statusClass = 'status-preparing'; }
                                            elseif ($status === 'Out for Delivery') { $statusClass = 'status-out'; }
                                            elseif ($status === 'Delivered') { $statusClass = 'status-delivered'; }
                                            elseif ($status === 'Cancelled') { $statusClass = 'status-cancelled'; }
                                        ?>
                                        <tr>
                                            <td>#<?php echo (int) ($order['order_id'] ?? 0); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($order['customer_name'] ?? 'Unknown Customer'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo format_currency((float) ($order['total_amount'] ?? 0.0)); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($order['payment_method'] ?? 'Cash on Delivery'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                            <td><?php echo htmlspecialchars(date('M j, Y', strtotime((string) ($order['created_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><a class="small-btn" href="order-view.php?id=<?php echo (int) ($order['order_id'] ?? 0); ?>">View</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
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
