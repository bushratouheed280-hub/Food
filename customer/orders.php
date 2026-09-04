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
require_once __DIR__ . '/../config/security.php';

function safe_text(?string $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function format_order_date(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return 'N/A';
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);

    if ($date === false) {
        return 'N/A';
    }

    return $date->format('M j, Y');
}

function format_currency(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return 'PKR 0.00';
    }

    $numeric = (float) $value;
    return 'PKR ' . number_format($numeric, 2);
}

function status_badge_class(string $status): string
{
    return match ($status) {
        'Pending' => 'status-pending',
        'Confirmed' => 'status-confirmed',
        'Preparing' => 'status-preparing',
        'Out for Delivery' => 'status-out',
        'Delivered' => 'status-delivered',
        'Cancelled' => 'status-cancelled',
        default => 'status-pending',
    };
}

$customerId = (int) $_SESSION['customer_id'];
$flashMessage = '';
$flashType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order_id'])) {
    if (! verify_customer_csrf_token(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null)) {
        http_response_code(403);
        exit('Invalid security token. Please refresh the page and try again.');
    }

    $cancelOrderId = filter_input(INPUT_POST, 'cancel_order_id', FILTER_VALIDATE_INT);

    if ($cancelOrderId === false || $cancelOrderId === null) {
        $flashMessage = 'Order not found.';
        $flashType = 'error';
    } else {
        try {
            $connection = get_db_connection();
        } catch (RuntimeException $exception) {
            $flashMessage = 'Unable to cancel this order.';
            $flashType = 'error';
        }

        if (isset($connection)) {
            $checkStmt = $connection->prepare('SELECT status FROM orders WHERE order_id = ? AND customer_id = ? LIMIT 1');
            if ($checkStmt === false) {
                $flashMessage = 'Unable to cancel this order.';
                $flashType = 'error';
            } else {
                $checkStmt->bind_param('ii', $cancelOrderId, $customerId);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                $order = $result->fetch_assoc();
                $checkStmt->close();

                if (! $order) {
                    $flashMessage = 'You are not authorized to cancel this order.';
                    $flashType = 'error';
                } elseif (! in_array($order['status'], ['Pending', 'Confirmed'], true)) {
                    $flashMessage = 'This order cannot be cancelled.';
                    $flashType = 'error';
                } else {
                    $updateStmt = $connection->prepare('UPDATE orders SET status = "Cancelled", updated_at = CURRENT_TIMESTAMP WHERE order_id = ? AND customer_id = ?');
                    if ($updateStmt === false) {
                        $flashMessage = 'Unable to cancel this order.';
                        $flashType = 'error';
                    } else {
                        $updateStmt->bind_param('ii', $cancelOrderId, $customerId);
                        if ($updateStmt->execute()) {
                            $flashMessage = 'Order cancelled successfully.';
                            $flashType = 'success';
                        } else {
                            $flashMessage = 'Unable to cancel this order.';
                            $flashType = 'error';
                        }
                        $updateStmt->close();
                    }
                }
            }

            $connection->close();
        }
    }
}

try {
    $connection = get_db_connection();
} catch (RuntimeException $exception) {
    http_response_code(500);
    exit('Database is currently unavailable. Please try again later.');
}

$customerStmt = $connection->prepare('SELECT full_name, email FROM customers WHERE customer_id = ? LIMIT 1');
if ($customerStmt === false) {
    $connection->close();
    http_response_code(500);
    exit('Database is currently unavailable. Please try again later.');
}

$customerStmt->bind_param('i', $customerId);
$customerStmt->execute();
$customerResult = $customerStmt->get_result();
$customer = $customerResult->fetch_assoc();
$customerStmt->close();

if (! $customer) {
    $connection->close();
    session_destroy();
    header('Location: ../auth/login.php');
    exit;
}

$customerName = (string) ($customer['full_name'] ?? '');

$orderStmt = $connection->prepare('SELECT o.order_id, o.order_number, o.total_amount, o.payment_method, o.status, o.created_at, COUNT(oi.order_item_id) AS item_count FROM orders o LEFT JOIN order_items oi ON oi.order_id = o.order_id WHERE o.customer_id = ? GROUP BY o.order_id, o.order_number, o.total_amount, o.payment_method, o.status, o.created_at ORDER BY o.created_at DESC');
if ($orderStmt === false) {
    $connection->close();
    http_response_code(500);
    exit('Unable to load your orders. Please try again.');
}

$orderStmt->bind_param('i', $customerId);
$orderStmt->execute();
$orderResult = $orderStmt->get_result();
$orders = $orderResult->fetch_all(MYSQLI_ASSOC);
$orderStmt->close();

$detailLookup = [];
foreach ($orders as $order) {
    $orderId = (int) $order['order_id'];

    $detailStmt = $connection->prepare('SELECT order_id, order_number, subtotal, discount_amount, delivery_charges, total_amount, payment_method, delivery_name, delivery_phone, delivery_address, delivery_instructions, status, created_at FROM orders WHERE order_id = ? AND customer_id = ? LIMIT 1');
    if ($detailStmt === false) {
        $detailLookup[$orderId] = ['order' => null, 'items' => []];
        continue;
    }

    $detailStmt->bind_param('ii', $orderId, $customerId);
    $detailStmt->execute();
    $detailRes = $detailStmt->get_result();
    $detailOrder = $detailRes->fetch_assoc();
    $detailStmt->close();

    $items = [];
    if ($detailOrder) {
        $itemsStmt = $connection->prepare('SELECT item_name, quantity, unit_price, item_total FROM order_items WHERE order_id = ?');
        if ($itemsStmt !== false) {
            $itemsStmt->bind_param('i', $orderId);
            $itemsStmt->execute();
            $itemsResult = $itemsStmt->get_result();
            $items = $itemsResult->fetch_all(MYSQLI_ASSOC);
            $itemsStmt->close();
        }
    }

    $detailLookup[$orderId] = ['order' => $detailOrder, 'items' => $items];
}

$connection->close();

$alertHtml = '';
if ($flashMessage !== '') {
    $alertClass = $flashType === 'error' ? 'alert-error' : 'alert-success';
    $alertHtml = '<div class="alert ' . $alertClass . '">' . safe_text($flashMessage) . '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders | Fast Food</title>
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
            --danger: #ff6b6b;
            --blue: #4da3ff;
            --purple: #9b7bff;
            --red: #ff6b6b;
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

        .content-area {
            margin-top: 26px;
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
            font-size: 1.6rem;
            font-weight: 800;
        }

        .panel-subtitle {
            color: var(--muted);
            margin: 0;
            font-size: 0.96rem;
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

        .alert {
            margin-bottom: 18px;
            border-radius: 12px;
            padding: 12px 14px;
            border: 1px solid transparent;
            font-weight: 600;
        }

        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            border-color: rgba(39, 174, 96, 0.35);
            color: #dfffe9;
        }

        .alert-error {
            background: rgba(255, 107, 107, 0.07);
            border-color: rgba(255, 107, 107, 0.35);
            color: #ffd7d7;
        }

        .table-responsive {
            border: 1px solid rgba(255, 193, 7, 0.12);
            border-radius: 16px;
            overflow: hidden;
            background: rgba(255,255,255,0.01);
        }

        .orders-table {
            width: 100%;
            margin: 0;
            color: var(--text);
            border-collapse: collapse;
        }

        .orders-table thead th {
            background: rgba(255, 193, 7, 0.08);
            color: var(--gold-soft);
            border-bottom: 1px solid var(--border);
            padding: 14px 16px;
            font-size: 0.8rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            font-weight: 700;
        }

        .orders-table tbody td {
            padding: 16px;
            border-bottom: 1px solid rgba(255, 193, 7, 0.08);
            vertical-align: middle;
            font-size: 0.95rem;
            color: var(--text);
        }

        .orders-table tbody tr:hover {
            background: rgba(255,255,255,0.015);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 7px 10px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.12);
            color: #ffd970;
            border: 1px solid rgba(255, 193, 7, 0.32);
        }

        .status-confirmed {
            background: rgba(77, 163, 255, 0.12);
            color: #bfe0ff;
            border: 1px solid rgba(77, 163, 255, 0.28);
        }

        .status-preparing {
            background: rgba(155, 123, 255, 0.12);
            color: #dccdff;
            border: 1px solid rgba(155, 123, 255, 0.25);
        }

        .status-out {
            background: rgba(255, 107, 107, 0.10);
            color: #ffc5c5;
            border: 1px solid rgba(255, 107, 107, 0.24);
        }

        .status-delivered {
            background: rgba(39, 174, 96, 0.12);
            color: #d5ffe9;
            border: 1px solid rgba(39, 174, 96, 0.28);
        }

        .status-cancelled {
            background: rgba(255, 107, 107, 0.12);
            color: #ffd7d7;
            border: 1px solid rgba(255, 107, 107, 0.25);
        }

        .action-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .primary-btn,
        .secondary-btn,
        .danger-btn,
        .ghost-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 12px;
            padding: 10px 14px;
            font-weight: 700;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: none;
            cursor: pointer;
            text-decoration: none;
        }

        .primary-btn {
            background: linear-gradient(135deg, var(--gold), var(--gold-soft));
            color: #111;
            box-shadow: 0 12px 24px rgba(255, 193, 7, 0.18);
        }

        .secondary-btn {
            background: transparent;
            color: var(--text);
            border: 1px solid var(--border);
        }

        .danger-btn {
            background: rgba(255, 107, 107, 0.1);
            color: #ffd7d7;
            border: 1px solid rgba(255, 107, 107, 0.3);
        }

        .ghost-btn {
            background: transparent;
            color: var(--muted);
            border: 1px solid rgba(255, 193, 7, 0.15);
        }

        .primary-btn:hover,
        .secondary-btn:hover,
        .danger-btn:hover,
        .ghost-btn:hover {
            transform: translateY(-1px);
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
            max-width: 440px;
            line-height: 1.6;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-top: 16px;
        }

        .detail-box {
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255, 193, 7, 0.12);
            border-radius: 12px;
            padding: 12px 14px;
        }

        .detail-label {
            color: var(--gold-soft);
            font-size: 0.72rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            font-weight: 700;
            display: block;
            margin-bottom: 4px;
        }

        .detail-value {
            color: var(--text);
            font-size: 0.96rem;
            line-height: 1.6;
            word-break: break-word;
        }

        .items-list {
            margin-top: 20px;
            border-top: 1px solid rgba(255, 193, 7, 0.14);
            padding-top: 16px;
        }

        .items-head {
            color: var(--gold-soft);
            font-size: 0.8rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            color: var(--text);
        }

        .items-table th,
        .items-table td {
            padding: 10px 8px;
            border-bottom: 1px solid rgba(255, 193, 7, 0.08);
            text-align: left;
            font-size: 0.92rem;
        }

        .items-table thead th {
            color: var(--gold-soft);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-size: 0.72rem;
        }

        .summary-box {
            margin-top: 18px;
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255, 193, 7, 0.12);
            border-radius: 12px;
            padding: 14px 16px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            color: var(--muted);
            padding: 6px 0;
            border-bottom: 1px solid rgba(255, 193, 7, 0.08);
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-total {
            color: var(--text);
            font-weight: 800;
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

            .panel-card,
            .topbar {
                border-radius: 16px;
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }

            .orders-table thead {
                display: none;
            }

            .orders-table,
            .orders-table tbody,
            .orders-table tr,
            .orders-table td {
                display: block;
                width: 100%;
            }

            .orders-table tbody tr {
                border-bottom: 1px solid rgba(255, 193, 7, 0.12);
                padding: 12px 0;
            }

            .orders-table tbody td {
                border-bottom: none;
                padding: 8px 16px;
            }

            .orders-table tbody td::before {
                content: attr(data-label);
                display: block;
                color: var(--gold-soft);
                font-size: 0.72rem;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                font-weight: 700;
                margin-bottom: 4px;
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
                <a class="nav-link" href="dashboard.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
                <a class="nav-link" href="profile.php"><i class="fa-solid fa-user"></i> My Profile</a>
                <a class="nav-link active" href="my-orders.php"><i class="fa-solid fa-box-open"></i> My Orders</a>
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
                    <h1 class="welcome-title">My Orders</h1>
                    <p class="welcome-email">View and manage your food orders.</p>
                </div>

                <div class="topbar-actions">
                    <span class="status-pill"><span class="status-dot"></span> <?php echo safe_text($customerName); ?></span>
                </div>
            </header>

            <section class="content-area">
                <?php echo $alertHtml; ?>

                <?php if (empty($orders)): ?>
                    <div class="panel-card">
                        <div class="empty-state">
                            <i class="fa-solid fa-bag-shopping"></i>
                            <h3>No orders yet.</h3>
                            <p>Your order history will appear here once you place your first order.</p>
                            <a href="../index.php#Menu" class="primary-btn"><i class="fa-solid fa-utensils"></i> Start Ordering</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="panel-card">
                        <div class="panel-header">
                            <div>
                                <h2 class="panel-title">Order History</h2>
                                <p class="panel-subtitle">Recent orders for <?php echo safe_text($customerName); ?></p>
                            </div>
                            <span class="panel-badge"><?php echo count($orders); ?> Orders</span>
                        </div>

                        <div class="table-responsive">
                            <table class="orders-table">
                                <thead>
                                    <tr>
                                        <th>Order Number</th>
                                        <th>Order Date</th>
                                        <th>Total Amount</th>
                                        <th>Payment Method</th>
                                        <th>Items</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <?php
                                        $orderId = (int) $order['order_id'];
                                        $status = (string) ($order['status'] ?? 'Pending');
                                        $showCancelButton = in_array($status, ['Pending', 'Confirmed'], true);
                                        ?>
                                        <tr>
                                            <td data-label="Order Number"><?php echo safe_text((string) ($order['order_number'] ?? '')); ?></td>
                                            <td data-label="Order Date"><?php echo safe_text(format_order_date((string) ($order['created_at'] ?? ''))); ?></td>
                                            <td data-label="Total Amount"><?php echo safe_text(format_currency((string) ($order['total_amount'] ?? '0.00'))); ?></td>
                                            <td data-label="Payment Method"><?php echo safe_text((string) ($order['payment_method'] ?? 'Cash on Delivery')); ?></td>
                                            <td data-label="Items"><?php echo (int) ($order['item_count'] ?? 0); ?></td>
                                            <td data-label="Status"><span class="status-badge <?php echo status_badge_class($status); ?>"><?php echo safe_text($status); ?></span></td>
                                            <td data-label="Actions">
                                                <div class="action-group">
                                                    <button type="button" class="secondary-btn" data-bs-toggle="modal" data-bs-target="#orderDetailsModal-<?php echo $orderId; ?>">
                                                        <i class="fa-solid fa-eye"></i> View Details
                                                    </button>
                                                    <?php if ($showCancelButton): ?>
                                                        <form method="post" action="orders.php" onsubmit="return confirm('Are you sure you want to cancel this order?');" style="display:inline;">
                                                            <input type="hidden" name="csrf_token" value="<?php echo safe_text(customer_csrf_token()); ?>">
                                                            <input type="hidden" name="cancel_order_id" value="<?php echo $orderId; ?>">
                                                            <button type="submit" class="danger-btn"><i class="fa-solid fa-ban"></i> Cancel Order</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <?php foreach ($orders as $order): ?>
        <?php
        $orderId = (int) $order['order_id'];
        $detail = $detailLookup[$orderId]['order'] ?? null;
        $items = $detailLookup[$orderId]['items'] ?? [];
        if (! $detail) {
            continue;
        }
        ?>
        <div class="modal fade" id="orderDetailsModal-<?php echo $orderId; ?>" tabindex="-1" aria-labelledby="orderDetailsTitle-<?php echo $orderId; ?>" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content" style="background: #111111; color: #FFFFFF; border: 1px solid rgba(255, 193, 7, 0.25); border-radius: 18px;">
                    <div class="modal-header" style="border-bottom: 1px solid rgba(255, 193, 7, 0.18);">
                        <h5 class="modal-title" id="orderDetailsTitle-<?php echo $orderId; ?>" style="font-weight: 800; color: #FFFFFF;">Order Details</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" style="padding: 20px;">
                        <div class="detail-grid">
                            <div class="detail-box">
                                <span class="detail-label">Order Number</span>
                                <span class="detail-value"><?php echo safe_text((string) ($detail['order_number'] ?? '')); ?></span>
                            </div>
                            <div class="detail-box">
                                <span class="detail-label">Order Date</span>
                                <span class="detail-value"><?php echo safe_text(format_order_date((string) ($detail['created_at'] ?? ''))); ?></span>
                            </div>
                            <div class="detail-box">
                                <span class="detail-label">Status</span>
                                <span class="detail-value"><span class="status-badge <?php echo status_badge_class((string) ($detail['status'] ?? 'Pending')); ?>"><?php echo safe_text((string) ($detail['status'] ?? 'Pending')); ?></span></span>
                            </div>
                            <div class="detail-box">
                                <span class="detail-label">Payment Method</span>
                                <span class="detail-value"><?php echo safe_text((string) ($detail['payment_method'] ?? 'Cash on Delivery')); ?></span>
                            </div>
                            <div class="detail-box">
                                <span class="detail-label">Delivery Name</span>
                                <span class="detail-value"><?php echo safe_text((string) ($detail['delivery_name'] ?? '')); ?></span>
                            </div>
                            <div class="detail-box">
                                <span class="detail-label">Delivery Phone</span>
                                <span class="detail-value"><?php echo safe_text((string) ($detail['delivery_phone'] ?? '')); ?></span>
                            </div>
                            <div class="detail-box" style="grid-column: 1 / -1;">
                                <span class="detail-label">Delivery Address</span>
                                <span class="detail-value"><?php echo nl2br(safe_text((string) ($detail['delivery_address'] ?? '')), false); ?></span>
                            </div>
                            <div class="detail-box" style="grid-column: 1 / -1;">
                                <span class="detail-label">Delivery Instructions</span>
                                <span class="detail-value"><?php echo $detail['delivery_instructions'] !== null && trim((string) $detail['delivery_instructions']) !== '' ? nl2br(safe_text((string) $detail['delivery_instructions']), false) : 'No special instructions'; ?></span>
                            </div>
                        </div>

                        <div class="items-list">
                            <div class="items-head">Order Items</div>
                            <?php if (empty($items)): ?>
                                <div class="detail-value">No items available for this order.</div>
                            <?php else: ?>
                                <table class="items-table">
                                    <thead>
                                        <tr>
                                            <th>Item Name</th>
                                            <th>Quantity</th>
                                            <th>Unit Price</th>
                                            <th>Item Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): ?>
                                            <tr>
                                                <td><?php echo safe_text((string) ($item['item_name'] ?? '')); ?></td>
                                                <td><?php echo (int) ($item['quantity'] ?? 0); ?></td>
                                                <td><?php echo safe_text(format_currency((string) ($item['unit_price'] ?? '0.00'))); ?></td>
                                                <td><?php echo safe_text(format_currency((string) ($item['item_total'] ?? '0.00'))); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>

                        <div class="summary-box">
                            <div class="summary-row">
                                <span>Subtotal</span>
                                <span><?php echo safe_text(format_currency((string) ($detail['subtotal'] ?? '0.00'))); ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Discount</span>
                                <span><?php echo safe_text(format_currency((string) ($detail['discount_amount'] ?? '0.00'))); ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Delivery Charges</span>
                                <span><?php echo safe_text(format_currency((string) ($detail['delivery_charges'] ?? '0.00'))); ?></span>
                            </div>
                            <div class="summary-row summary-total">
                                <span>Total Amount</span>
                                <span><?php echo safe_text(format_currency((string) ($detail['total_amount'] ?? '0.00'))); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid rgba(255, 193, 7, 0.18); justify-content: flex-end; padding: 16px 20px;">
                        <button type="button" class="ghost-btn" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-4Qn7e0oxhKJ5gdg0w5vtz0Q4VwM4T0h2U3GqkVY6K4b3g0d9Qe9CkNq6VvWlO8m" crossorigin="anonymous"></script>
</body>
</html>
