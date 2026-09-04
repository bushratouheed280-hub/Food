<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';

function generate_admin_order_csrf_token(): string
{
    if (empty($_SESSION['admin_order_csrf_token'])) {
        $_SESSION['admin_order_csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['admin_order_csrf_token'];
}

function verify_admin_order_csrf_token(string $token): bool
{
    $expected = $_SESSION['admin_order_csrf_token'] ?? '';
    return is_string($expected) && hash_equals($expected, $token);
}

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
$validSorts = [
    'newest' => 'o.created_at DESC',
    'oldest' => 'o.created_at ASC',
    'highest_total' => 'o.total_amount DESC',
    'lowest_total' => 'o.total_amount ASC',
];

$search = trim((string) ($_GET['search'] ?? ''));
$statusFilter = isset($_GET['status']) ? trim((string) $_GET['status']) : 'All';
$paymentFilter = isset($_GET['payment_method']) ? trim((string) $_GET['payment_method']) : 'All';
$dateFrom = isset($_GET['date_from']) ? trim((string) $_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim((string) $_GET['date_to']) : '';
$sortKey = isset($_GET['sort']) && isset($validSorts[(string) $_GET['sort']]) ? (string) $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 10;

if (! in_array($statusFilter, $allowedStatuses, true) && $statusFilter !== 'All') {
    $statusFilter = 'All';
}

$flashMessage = $_SESSION['admin_order_message'] ?? '';
$flashType = $_SESSION['admin_order_message_type'] ?? 'success';
unset($_SESSION['admin_order_message'], $_SESSION['admin_order_message_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    if (! isset($_POST['csrf_token']) || ! verify_admin_order_csrf_token((string) $_POST['csrf_token'])) {
        $_SESSION['admin_order_message'] = 'Invalid security token. Please try again.';
        $_SESSION['admin_order_message_type'] = 'error';
        header('Location: orders.php');
        exit;
    }

    $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $newStatus = trim((string) ($_POST['status'] ?? ''));

    if ($orderId === false || $orderId === null || $orderId <= 0) {
        $_SESSION['admin_order_message'] = 'Invalid order ID.';
        $_SESSION['admin_order_message_type'] = 'error';
        header('Location: orders.php');
        exit;
    }

    if (! in_array($newStatus, $allowedStatuses, true)) {
        $_SESSION['admin_order_message'] = 'Invalid order status selected.';
        $_SESSION['admin_order_message_type'] = 'error';
        header('Location: order-view.php?id=' . $orderId);
        exit;
    }

    $updateConnection = get_db_connection();
    $currentStatusStmt = $updateConnection->prepare('SELECT status FROM orders WHERE order_id = ? LIMIT 1');
    if ($currentStatusStmt === false) {
        $updateConnection->close();
        $_SESSION['admin_order_message'] = 'Unable to load the selected order.';
        $_SESSION['admin_order_message_type'] = 'error';
        header('Location: orders.php');
        exit;
    }

    $currentStatusStmt->bind_param('i', $orderId);
    $currentStatusStmt->execute();
    $currentStatusResult = $currentStatusStmt->get_result();
    $currentOrder = $currentStatusResult->fetch_assoc();
    $currentStatusStmt->close();

    if (! $currentOrder) {
        $updateConnection->close();
        $_SESSION['admin_order_message'] = 'Order not found.';
        $_SESSION['admin_order_message_type'] = 'error';
        header('Location: orders.php');
        exit;
    }

    $currentStatus = (string) $currentOrder['status'];
    $statusRules = [
        'Pending' => ['Confirmed', 'Cancelled'],
        'Confirmed' => ['Preparing', 'Cancelled'],
        'Preparing' => ['Out for Delivery', 'Cancelled'],
        'Out for Delivery' => ['Delivered', 'Cancelled'],
        'Delivered' => [],
        'Cancelled' => [],
    ];

    if (! isset($statusRules[$currentStatus]) || ! in_array($newStatus, $statusRules[$currentStatus], true)) {
        $updateConnection->close();
        $_SESSION['admin_order_message'] = 'This status change is not allowed for the current order state.';
        $_SESSION['admin_order_message_type'] = 'error';
        header('Location: order-view.php?id=' . $orderId);
        exit;
    }

    $statusStmt = $updateConnection->prepare('UPDATE orders SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE order_id = ?');
    if ($statusStmt === false) {
        $updateConnection->close();
        $_SESSION['admin_order_message'] = 'Unable to update order status.';
        $_SESSION['admin_order_message_type'] = 'error';
        header('Location: order-view.php?id=' . $orderId);
        exit;
    }

    $statusStmt->bind_param('si', $newStatus, $orderId);
    $statusStmt->execute();
    $statusStmt->close();
    $updateConnection->close();

    $_SESSION['admin_order_message'] = 'Order status updated successfully.';
    $_SESSION['admin_order_message_type'] = 'success';
    header('Location: order-view.php?id=' . $orderId);
    exit;
}

$connection = get_db_connection();

$statsQuery = 'SELECT
    COUNT(*) AS total_orders,
    SUM(CASE WHEN status = "Pending" THEN 1 ELSE 0 END) AS pending_orders,
    SUM(CASE WHEN status = "Preparing" THEN 1 ELSE 0 END) AS preparing_orders,
    SUM(CASE WHEN status = "Delivered" THEN 1 ELSE 0 END) AS delivered_orders,
    SUM(CASE WHEN status = "Cancelled" THEN 1 ELSE 0 END) AS cancelled_orders,
    COALESCE(SUM(total_amount), 0) AS total_revenue
FROM orders';
$statsResult = $connection->query($statsQuery);
$stats = [
    'total_orders' => 0,
    'pending_orders' => 0,
    'preparing_orders' => 0,
    'delivered_orders' => 0,
    'cancelled_orders' => 0,
    'total_revenue' => 0.0,
];
if ($statsResult && $statsResult->num_rows > 0) {
    $statsRow = $statsResult->fetch_assoc();
    $stats['total_orders'] = (int) ($statsRow['total_orders'] ?? 0);
    $stats['pending_orders'] = (int) ($statsRow['pending_orders'] ?? 0);
    $stats['preparing_orders'] = (int) ($statsRow['preparing_orders'] ?? 0);
    $stats['delivered_orders'] = (int) ($statsRow['delivered_orders'] ?? 0);
    $stats['cancelled_orders'] = (int) ($statsRow['cancelled_orders'] ?? 0);
    $stats['total_revenue'] = (float) ($statsRow['total_revenue'] ?? 0.0);
}

$paymentMethods = [];
$paymentMethodStmt = $connection->query('SELECT DISTINCT payment_method FROM orders WHERE payment_method IS NOT NULL AND payment_method != "" ORDER BY payment_method ASC');
if ($paymentMethodStmt) {
    while ($row = $paymentMethodStmt->fetch_assoc()) {
        $paymentMethods[] = (string) ($row['payment_method'] ?? '');
    }
}

$whereClauses = ['1=1'];
$queryParams = [];
$queryTypes = '';

if ($search !== '') {
    $searchValue = trim((string) $search);
    $numericSearch = filter_var($searchValue, FILTER_VALIDATE_INT);
    if ($numericSearch !== false) {
        $whereClauses[] = 'o.order_id = ?';
        $queryParams[] = $numericSearch;
        $queryTypes .= 'i';
    } else {
        $whereClauses[] = '(c.full_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)';
        $like = '%' . $searchValue . '%';
        $queryParams[] = $like;
        $queryParams[] = $like;
        $queryParams[] = $like;
        $queryTypes .= 'sss';
    }
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

if ($dateFrom !== '') {
    $whereClauses[] = 'DATE(o.created_at) >= ?';
    $queryParams[] = $dateFrom;
    $queryTypes .= 's';
}

if ($dateTo !== '') {
    $whereClauses[] = 'DATE(o.created_at) <= ?';
    $queryParams[] = $dateTo;
    $queryTypes .= 's';
}

$countSql = 'SELECT COUNT(*) AS total_orders FROM orders o LEFT JOIN customers c ON c.customer_id = o.customer_id WHERE ' . implode(' AND ', $whereClauses);
$countStmt = $connection->prepare($countSql);
$totalOrders = 0;
if ($countStmt !== false) {
    if ($queryParams !== []) {
        bind_dynamic_params($countStmt, $queryTypes, $queryParams);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalOrders = (int) ($countResult->fetch_assoc()['total_orders'] ?? 0);
    $countStmt->close();
}
$totalPages = max(1, (int) ceil($totalOrders / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sortClause = $validSorts[$sortKey] ?? $validSorts['newest'];
$listSql = 'SELECT o.order_id, c.full_name AS customer_name, c.email AS customer_email, c.phone AS customer_phone, o.total_amount, o.payment_method, o.status, o.created_at
    FROM orders o
    LEFT JOIN customers c ON c.customer_id = o.customer_id
    WHERE ' . implode(' AND ', $whereClauses) . '
    ORDER BY ' . $sortClause . '
    LIMIT ? OFFSET ?';

$orderList = [];
$listStmt = $connection->prepare($listSql);
if ($listStmt !== false) {
    $listParams = $queryParams;
    $listParams[] = $perPage;
    $listParams[] = $offset;
    $listTypes = $queryTypes . 'ii';
    bind_dynamic_params($listStmt, $listTypes, $listParams);
    $listStmt->execute();
    $listResult = $listStmt->get_result();
    while ($row = $listResult->fetch_assoc()) {
        $orderList[] = $row;
    }
    $listStmt->close();
}

$connection->close();

$sortUrl = function (string $columnKey) use ($search, $statusFilter, $paymentFilter, $dateFrom, $dateTo) {
    $query = [
        'search' => $search,
        'status' => $statusFilter,
        'payment_method' => $paymentFilter,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'sort' => $columnKey,
        'page' => 1,
    ];

    return '?' . http_build_query($query);
};

$activeFilterCount = 0;
foreach (['search', 'status', 'payment_method', 'date_from', 'date_to'] as $filterKey) {
    $value = ${$filterKey} ?? '';
    if ($filterKey === 'status' && ($value === '' || $value === 'All')) {
        continue;
    }
    if ($filterKey === 'payment_method' && ($value === '' || $value === 'All')) {
        continue;
    }
    if ($value !== '') {
        $activeFilterCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders | Fast Food Admin</title>
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
            --danger: #f87171;
            --success: #34d399;
            --warning: #fbbf24;
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
        .flash { margin: 0 0 18px; padding: 12px 14px; border-radius: 12px; font-weight: 600; }
        .flash.success { background: rgba(52, 211, 153, 0.12); border: 1px solid rgba(52, 211, 153, 0.28); color: var(--success); }
        .flash.error { background: rgba(248, 113, 113, 0.12); border: 1px solid rgba(248, 113, 113, 0.28); color: var(--danger); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 18px; margin-bottom: 28px; }
        .stat-card { background: linear-gradient(180deg, var(--panel), var(--panel-soft)); border: 1px solid var(--border); border-radius: 16px; padding: 18px 18px 16px; box-shadow: 0 12px 28px var(--shadow); }
        .stat-icon { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: rgba(212, 175, 55, 0.1); color: var(--gold-soft); margin-bottom: 14px; border: 1px solid var(--border); }
        .stat-label { display: block; color: var(--muted); text-transform: uppercase; letter-spacing: 0.12em; font-size: 0.72rem; margin-bottom: 8px; }
        .stat-value { font-size: clamp(1.5rem, 2.5vw, 2.1rem); font-weight: 700; line-height: 1.2; }
        .panel-card { background: linear-gradient(180deg, var(--panel), var(--panel-soft)); border: 1px solid var(--border); border-radius: 18px; box-shadow: 0 12px 30px var(--shadow); overflow: hidden; }
        .panel-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 18px 20px; border-bottom: 1px solid var(--border); }
        .panel-title { margin: 0; font-size: 1.1rem; }
        .toolbar { padding: 18px 20px 8px; }
        .order-filter-form { display: flex; flex-direction: column; gap: 12px; }
        .filter-grid { display: grid; grid-template-columns: minmax(220px, 1.8fr) repeat(2, minmax(160px, 1fr)) minmax(150px, 0.9fr) minmax(150px, 0.9fr) auto auto; gap: 12px; align-items: end; }
        .field { display: flex; flex-direction: column; gap: 7px; }
        .field label { display: block; color: var(--gold-soft); font-size: 0.72rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; }
        .input-wrap { position: relative; }
        .input-wrap i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--gold-soft); font-size: 0.8rem; }
        .search-input, .filter-select, .date-input { width: 100%; height: 42px; padding: 10px 12px; border-radius: 10px; border: 1px solid var(--border); background: rgba(255,255,255,0.02); color: var(--text); outline: none; font: inherit; }
        .search-input { padding-left: 36px; }
        .search-input::placeholder, .date-input::placeholder { color: var(--muted); }
        .search-input:focus, .filter-select:focus, .date-input:focus { border-color: var(--gold-soft); box-shadow: 0 0 0 3px rgba(212,175,55,0.12); }
        .filter-actions { display: flex; align-items: end; gap: 10px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 42px; border-radius: 10px; border: 1px solid var(--border); background: rgba(212, 175, 55, 0.08); color: var(--gold-soft); padding: 10px 14px; font-weight: 700; cursor: pointer; transition: all 0.2s ease; }
        .btn.primary { background: linear-gradient(135deg, var(--gold), var(--gold-soft)); color: #111; border-color: rgba(212,175,55,0.8); }
        .btn.secondary { background: rgba(255,255,255,0.02); }
        .btn:hover { transform: translateY(-1px); }
        .filter-meta { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; padding: 0 2px 4px; }
        .filter-status { color: var(--muted); font-size: 0.82rem; }
        .filter-status strong { color: var(--gold-soft); font-weight: 700; }
        .filter-link { color: var(--gold-soft); text-decoration: none; font-weight: 600; font-size: 0.82rem; }
        .validation-message { display: none; color: var(--danger); background: rgba(248,113,113,0.08); border: 1px solid rgba(248,113,113,0.25); border-radius: 8px; padding: 8px 10px; font-size: 0.8rem; }
        .validation-message.visible { display: block; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 980px; }
        th, td { padding: 14px 18px; border-bottom: 1px solid rgba(212, 175, 55, 0.15); text-align: left; vertical-align: middle; }
        th { color: var(--gold-soft); font-size: 0.76rem; text-transform: uppercase; letter-spacing: 0.12em; background: rgba(212, 175, 55, 0.04); }
        td { color: var(--text); font-size: 0.96rem; }
        .status-badge { display: inline-flex; align-items: center; justify-content: center; padding: 6px 10px; border-radius: 999px; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; border: 1px solid rgba(255,255,255,0.1); }
        .status-pending { background: rgba(251, 191, 36, 0.12); color: var(--warning); border-color: rgba(251, 191, 36, 0.28); }
        .status-confirmed { background: rgba(52, 211, 153, 0.10); color: var(--success); border-color: rgba(52, 211, 153, 0.25); }
        .status-preparing { background: rgba(59, 130, 246, 0.12); color: #7dd3fc; border-color: rgba(59, 130, 246, 0.30); }
        .status-delivered { background: rgba(34, 197, 94, 0.12); color: var(--success); border-color: rgba(34, 197, 94, 0.25); }
        .status-cancelled { background: rgba(248, 113, 113, 0.12); color: var(--danger); border-color: rgba(248, 113, 113, 0.28); }
        .status-out { background: rgba(147, 51, 234, 0.12); color: #c084fc; border-color: rgba(147, 51, 234, 0.25); }
        .icon-link { color: var(--gold-soft); font-weight: 700; }
        .empty-state { padding: 30px 20px; color: var(--muted); text-align: center; }
        .pagination { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 18px 20px; flex-wrap: wrap; }
        .page-links { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .page-links a, .page-links span { min-width: 38px; min-height: 38px; display: inline-flex; align-items: center; justify-content: center; border-radius: 9px; border: 1px solid var(--border); background: rgba(255,255,255,0.02); color: var(--text); padding: 0 10px; font-weight: 700; }
        .page-links a.active, .page-links span.current { background: rgba(212,175,55,0.12); color: var(--gold-soft); }
        .menu-toggle { display: none; }
        @media (max-width: 820px) {
            .admin-layout { display: block; }
            .sidebar { position: fixed; left: 0; top: 0; transform: translateX(-100%); z-index: 20; height: 100vh; width: 260px; box-shadow: 0 18px 38px rgba(0, 0, 0, 0.35); }
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
                <a class="nav-link" href="customers.php"><i class="fa-solid fa-users"></i> Customers</a>
                <a class="nav-link active" href="orders.php"><i class="fa-solid fa-box-open"></i> Orders</a>
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
                    <h1 class="page-title">Orders</h1>
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
                <?php if ($flashMessage !== ''): ?>
                    <div class="flash <?php echo htmlspecialchars($flashType, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <section class="stats-grid" aria-label="Order statistics">
                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-box-open"></i></div>
                        <span class="stat-label">Total Orders</span>
                        <div class="stat-value"><?php echo number_format($stats['total_orders']); ?></div>
                    </article>
                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-clock"></i></div>
                        <span class="stat-label">Pending Orders</span>
                        <div class="stat-value"><?php echo number_format($stats['pending_orders']); ?></div>
                    </article>
                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-clipboard-list"></i></div>
                        <span class="stat-label">Preparing Orders</span>
                        <div class="stat-value"><?php echo number_format($stats['preparing_orders']); ?></div>
                    </article>
                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
                        <span class="stat-label">Delivered Orders</span>
                        <div class="stat-value"><?php echo number_format($stats['delivered_orders']); ?></div>
                    </article>
                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-ban"></i></div>
                        <span class="stat-label">Cancelled Orders</span>
                        <div class="stat-value"><?php echo number_format($stats['cancelled_orders']); ?></div>
                    </article>
                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-money-bill-wave"></i></div>
                        <span class="stat-label">Total Revenue</span>
                        <div class="stat-value"><?php echo format_currency((float) $stats['total_revenue']); ?></div>
                    </article>
                </section>

                <article class="panel-card">
                    <div class="panel-header">
                        <h2 class="panel-title">Order Management</h2>
                    </div>

                    <div class="toolbar">
                        <form class="order-filter-form" id="orderFilterForm" method="GET" action="orders.php" novalidate>
                            <div class="filter-grid">
                                <div class="field">
                                    <label for="searchOrders">Search Orders</label>
                                    <div class="input-wrap">
                                        <i class="fa-solid fa-magnifying-glass"></i>
                                        <input id="searchOrders" class="search-input" type="search" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search Order ID, customer name, email..." aria-label="Search orders">
                                    </div>
                                </div>

                                <div class="field">
                                    <label for="statusFilter">Order Status</label>
                                    <select id="statusFilter" class="filter-select" name="status">
                                        <option value="All" <?php echo $statusFilter === 'All' ? 'selected' : ''; ?>>All Statuses</option>
                                        <?php foreach ($allowedStatuses as $statusName): ?>
                                            <option value="<?php echo htmlspecialchars($statusName, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $statusFilter === $statusName ? 'selected' : ''; ?>><?php echo htmlspecialchars($statusName, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="field">
                                    <label for="paymentFilter">Payment Method</label>
                                    <select id="paymentFilter" class="filter-select" name="payment_method">
                                        <option value="All" <?php echo $paymentFilter === 'All' ? 'selected' : ''; ?>>All Payment Methods</option>
                                        <?php foreach ($paymentMethods as $method): ?>
                                            <option value="<?php echo htmlspecialchars((string) $method, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $paymentFilter === (string) $method ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $method, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="field">
                                    <label for="dateFrom">From Date</label>
                                    <input id="dateFrom" class="date-input" type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8'); ?>" placeholder="From date">
                                </div>

                                <div class="field">
                                    <label for="dateTo">To Date</label>
                                    <input id="dateTo" class="date-input" type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8'); ?>" placeholder="To date">
                                </div>

                                <div class="filter-actions">
                                    <button class="btn primary" type="submit"><i class="fa-solid fa-filter"></i> Apply Filters</button>
                                    <a class="btn secondary" href="orders.php"><i class="fa-solid fa-xmark"></i> Clear Filters</a>
                                </div>
                            </div>

                            <div class="filter-meta">
                                <?php if ($activeFilterCount > 0): ?>
                                    <div class="filter-status"><strong><?php echo $activeFilterCount; ?> filter<?php echo $activeFilterCount === 1 ? '' : 's'; ?> applied</strong></div>
                                    <a class="filter-link" href="orders.php">Clear All</a>
                                <?php else: ?>
                                    <div class="filter-status">Filters inactive</div>
                                <?php endif; ?>
                            </div>

                            <div id="dateRangeMessage" class="validation-message" role="alert" aria-live="polite">Please select a valid date range: From Date must be earlier than or equal to To Date.</div>
                        </form>
                    </div>

                    <?php if ($orderList === []): ?>
                        <div class="empty-state"><?php echo $search !== '' || $statusFilter !== 'All' || $paymentFilter !== 'All' || $dateFrom !== '' || $dateTo !== '' ? 'No orders match your search or filter.' : 'No orders found.'; ?></div>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th><a href="<?php echo $sortUrl('newest'); ?>" style="color: var(--gold-soft);">Order ID</a></th>
                                        <th>Customer</th>
                                        <th><a href="<?php echo $sortUrl('highest_total'); ?>" style="color: var(--gold-soft);">Total Amount</a></th>
                                        <th>Payment Method</th>
                                        <th>Status</th>
                                        <th><a href="<?php echo $sortUrl('newest'); ?>" style="color: var(--gold-soft);">Order Date</a></th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orderList as $order): ?>
                                        <?php
                                            $orderId = (int) ($order['order_id'] ?? 0);
                                            $customerName = htmlspecialchars((string) ($order['customer_name'] ?? 'Unknown Customer'), ENT_QUOTES, 'UTF-8');
                                            $customerEmail = htmlspecialchars((string) ($order['customer_email'] ?? ''), ENT_QUOTES, 'UTF-8');
                                            $totalAmount = (float) ($order['total_amount'] ?? 0.0);
                                            $paymentMethod = htmlspecialchars((string) ($order['payment_method'] ?? 'Cash on Delivery'), ENT_QUOTES, 'UTF-8');
                                            $status = htmlspecialchars((string) ($order['status'] ?? 'Pending'), ENT_QUOTES, 'UTF-8');
                                            $statusClass = strtolower(str_replace([' ', '-'], '', $status));
                                            $createdAt = htmlspecialchars((string) ($order['created_at'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        ?>
                                        <tr>
                                            <td>#<?php echo $orderId; ?></td>
                                            <td>
                                                <div style="font-weight:700; color: var(--text);"><?php echo $customerName; ?></div>
                                                <div style="color: var(--muted); font-size:0.8rem; margin-top:4px;"><?php echo $customerEmail; ?></div>
                                            </td>
                                            <td><?php echo format_currency($totalAmount); ?></td>
                                            <td><?php echo $paymentMethod; ?></td>
                                            <td><span class="status-badge status-<?php echo $statusClass; ?>"><?php echo $status; ?></span></td>
                                            <td><?php echo htmlspecialchars(date('M j, Y', strtotime($createdAt ?: 'now')), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><a class="icon-link" href="order-view.php?id=<?php echo $orderId; ?>">View</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php if ($totalOrders > 0): ?>
                        <div class="pagination">
                            <div>Page <?php echo $page; ?> of <?php echo $totalPages; ?></div>
                            <div class="page-links">
                                <?php if ($page > 1): ?>
                                    <a href="orders.php?<?php echo http_build_query(['search' => $search, 'status' => $statusFilter, 'payment_method' => $paymentFilter, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'sort' => $sortKey, 'page' => $page - 1]); ?>">Previous</a>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <?php if ($i === $page): ?>
                                        <span class="current"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a href="orders.php?<?php echo http_build_query(['search' => $search, 'status' => $statusFilter, 'payment_method' => $paymentFilter, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'sort' => $sortKey, 'page' => $i]); ?>"><?php echo $i; ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="orders.php?<?php echo http_build_query(['search' => $search, 'status' => $statusFilter, 'payment_method' => $paymentFilter, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'sort' => $sortKey, 'page' => $page + 1]); ?>">Next</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
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

        const dateRangeForm = document.getElementById('orderFilterForm');
        const dateFrom = document.getElementById('dateFrom');
        const dateTo = document.getElementById('dateTo');
        const dateRangeMessage = document.getElementById('dateRangeMessage');

        if (dateRangeForm && dateFrom && dateTo && dateRangeMessage) {
            dateRangeForm.addEventListener('submit', function (event) {
                const fromValue = dateFrom.value;
                const toValue = dateTo.value;

                if (fromValue && toValue && fromValue > toValue) {
                    event.preventDefault();
                    dateRangeMessage.classList.add('visible');
                    return false;
                }

                dateRangeMessage.classList.remove('visible');
            });
        }
    </script>
</body>
</html>
