<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';

function generate_admin_order_view_csrf_token(): string
{
    if (empty($_SESSION['admin_order_view_csrf_token'])) {
        $_SESSION['admin_order_view_csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['admin_order_view_csrf_token'];
}

function verify_admin_order_view_csrf_token(string $token): bool
{
    $expected = $_SESSION['admin_order_view_csrf_token'] ?? '';
    return is_string($expected) && hash_equals($expected, $token);
}

function format_currency(float $value): string
{
    return 'PKR ' . number_format($value, 2, '.', ',');
}

$allowedStatuses = ['Pending', 'Confirmed', 'Preparing', 'Out for Delivery', 'Delivered', 'Cancelled'];
$statusRules = [
    'Pending' => ['Confirmed', 'Cancelled'],
    'Confirmed' => ['Preparing', 'Cancelled'],
    'Preparing' => ['Out for Delivery', 'Cancelled'],
    'Out for Delivery' => ['Delivered', 'Cancelled'],
    'Delivered' => [],
    'Cancelled' => [],
];
$adminName = htmlspecialchars((string) ($_SESSION['admin_name'] ?? 'Administrator'), ENT_QUOTES, 'UTF-8');
$adminEmail = htmlspecialchars((string) ($_SESSION['admin_email'] ?? ''), ENT_QUOTES, 'UTF-8');

$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($orderId === false || $orderId === null || $orderId <= 0) {
    $_SESSION['admin_order_message'] = 'Invalid order ID.';
    $_SESSION['admin_order_message_type'] = 'error';
    header('Location: orders.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    if (! isset($_POST['csrf_token']) || ! verify_admin_order_view_csrf_token((string) $_POST['csrf_token'])) {
        $_SESSION['admin_order_message'] = 'Invalid security token. Please try again.';
        $_SESSION['admin_order_message_type'] = 'error';
        header('Location: order-view.php?id=' . $orderId);
        exit;
    }

    $newStatus = trim((string) ($_POST['status'] ?? ''));
    if (! in_array($newStatus, $allowedStatuses, true)) {
        $_SESSION['admin_order_message'] = 'Invalid order status.';
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
    $current = $currentStatusStmt->get_result()->fetch_assoc();
    $currentStatusStmt->close();

    if (! $current) {
        $updateConnection->close();
        $_SESSION['admin_order_message'] = 'Order not found.';
        $_SESSION['admin_order_message_type'] = 'error';
        header('Location: orders.php');
        exit;
    }

    $currentStatus = (string) $current['status'];

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
$orderStmt = $connection->prepare(
    'SELECT o.order_id, o.order_number, o.subtotal, o.discount_amount, o.delivery_charges, o.total_amount, o.payment_method, o.delivery_name, o.delivery_phone, o.delivery_address, o.delivery_instructions, o.status, o.created_at, c.customer_id, c.full_name, c.email, c.phone, c.address
     FROM orders o
     LEFT JOIN customers c ON c.customer_id = o.customer_id
     WHERE o.order_id = ?
     LIMIT 1'
);
if ($orderStmt === false) {
    $connection->close();
    $_SESSION['admin_order_message'] = 'Unable to load order details.';
    $_SESSION['admin_order_message_type'] = 'error';
    header('Location: orders.php');
    exit;
}

$orderStmt->bind_param('i', $orderId);
$orderStmt->execute();
$order = $orderStmt->get_result()->fetch_assoc();
$orderStmt->close();

if (! $order) {
    $connection->close();
    $_SESSION['admin_order_message'] = 'Order not found.';
    $_SESSION['admin_order_message_type'] = 'error';
    header('Location: orders.php');
    exit;
}

$itemStmt = $connection->prepare('SELECT item_name, quantity, unit_price, item_total FROM order_items WHERE order_id = ?');
$orderItems = [];
if ($itemStmt !== false) {
    $itemStmt->bind_param('i', $orderId);
    $itemStmt->execute();
    $itemResult = $itemStmt->get_result();
    while ($row = $itemResult->fetch_assoc()) {
        $orderItems[] = $row;
    }
    $itemStmt->close();
}

$connection->close();
$currentOrderStatus = (string) ($order['status'] ?? 'Pending');
$statusUpdateDisabled = isset($statusRules[$currentOrderStatus]) && $statusRules[$currentOrderStatus] === [];
$statusOptions = array_values(array_unique(array_merge(
    [$currentOrderStatus],
    $statusRules[$currentOrderStatus] ?? []
)));
$flashMessage = $_SESSION['admin_order_message'] ?? '';
$flashType = $_SESSION['admin_order_message_type'] ?? 'success';
unset($_SESSION['admin_order_message'], $_SESSION['admin_order_message_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details | Fast Food Admin</title>
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
        .panel-card { background: linear-gradient(180deg, var(--panel), var(--panel-soft)); border: 1px solid var(--border); border-radius: 18px; box-shadow: 0 12px 30px var(--shadow); overflow: hidden; }
        .panel-header { padding: 18px 20px; border-bottom: 1px solid var(--border); }
        .panel-title { margin: 0; font-size: 1.1rem; }
        .detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px; padding: 22px 20px 10px; }
        .detail-item { background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: 12px; padding: 16px; }
        .detail-label { display: block; color: var(--gold-soft); text-transform: uppercase; letter-spacing: 0.12em; font-size: 0.7rem; margin-bottom: 8px; }
        .detail-value { color: var(--text); font-size: 1rem; line-height: 1.5; word-break: break-word; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; padding: 0 20px 20px; }
        .summary-box { background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: 12px; padding: 14px; }
        .status-badge { display: inline-flex; align-items: center; justify-content: center; padding: 6px 10px; border-radius: 999px; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; border: 1px solid rgba(255,255,255,0.1); }
        .status-pending { background: rgba(251, 191, 36, 0.12); color: var(--warning); border-color: rgba(251, 191, 36, 0.28); }
        .status-confirmed { background: rgba(52, 211, 153, 0.10); color: var(--success); border-color: rgba(52, 211, 153, 0.25); }
        .status-preparing { background: rgba(59, 130, 246, 0.12); color: #7dd3fc; border-color: rgba(59, 130, 246, 0.30); }
        .status-delivered { background: rgba(34, 197, 94, 0.12); color: var(--success); border-color: rgba(34, 197, 94, 0.25); }
        .status-cancelled { background: rgba(248, 113, 113, 0.12); color: var(--danger); border-color: rgba(248, 113, 113, 0.28); }
        .status-out { background: rgba(147, 51, 234, 0.12); color: #c084fc; border-color: rgba(147, 51, 234, 0.25); }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 720px; }
        th, td { padding: 14px 18px; border-bottom: 1px solid rgba(212, 175, 55, 0.15); text-align: left; vertical-align: middle; }
        th { color: var(--gold-soft); font-size: 0.76rem; text-transform: uppercase; letter-spacing: 0.12em; background: rgba(212, 175, 55, 0.04); }
        td { color: var(--text); font-size: 0.96rem; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; padding: 20px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; border-radius: 10px; font-weight: 700; border: 1px solid var(--border); background: rgba(212,175,55,0.08); color: var(--gold-soft); padding: 10px 14px; cursor: pointer; }
        .btn.primary { background: linear-gradient(135deg, var(--gold), var(--gold-soft)); color: #111; }
        .field { display: flex; flex-direction: column; gap: 8px; }
        .field select { width: 220px; padding: 10px 12px; border-radius: 10px; border: 1px solid var(--border); background: rgba(255,255,255,0.02); color: var(--text); cursor: pointer; pointer-events: auto; position: relative; z-index: 1; }
        .status-final-message { color: var(--muted); font-size: 0.82rem; line-height: 1.4; max-width: 280px; }
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
                    <h1 class="page-title">Order Details</h1>
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

                <article class="panel-card">
                    <div class="panel-header">
                        <h2 class="panel-title">Order #<?php echo (int) $order['order_id']; ?> · <?php echo htmlspecialchars((string) $order['order_number'], ENT_QUOTES, 'UTF-8'); ?></h2>
                    </div>

                    <div class="detail-grid">
                        <div class="detail-item"><span class="detail-label">Order ID</span><div class="detail-value"><?php echo (int) $order['order_id']; ?></div></div>
                        <div class="detail-item"><span class="detail-label">Order Date</span><div class="detail-value"><?php echo htmlspecialchars((string) $order['created_at'], ENT_QUOTES, 'UTF-8'); ?></div></div>
                        <div class="detail-item"><span class="detail-label">Customer Name</span><div class="detail-value"><?php echo htmlspecialchars((string) ($order['full_name'] ?? 'Unknown Customer'), ENT_QUOTES, 'UTF-8'); ?></div></div>
                        <div class="detail-item"><span class="detail-label">Customer Email</span><div class="detail-value"><?php echo htmlspecialchars((string) ($order['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></div>
                        <div class="detail-item"><span class="detail-label">Customer Phone</span><div class="detail-value"><?php echo htmlspecialchars((string) ($order['phone'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></div></div>
                        <div class="detail-item"><span class="detail-label">Payment Method</span><div class="detail-value"><?php echo htmlspecialchars((string) ($order['payment_method'] ?? 'Cash on Delivery'), ENT_QUOTES, 'UTF-8'); ?></div></div>
                        <div class="detail-item"><span class="detail-label">Delivery Address</span><div class="detail-value"><?php echo nl2br(htmlspecialchars((string) ($order['delivery_address'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'), false); ?></div></div>
                        <div class="detail-item"><span class="detail-label">Delivery Instructions</span><div class="detail-value"><?php echo nl2br(htmlspecialchars((string) ($order['delivery_instructions'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'), false); ?></div></div>
                        <div class="detail-item"><span class="detail-label">Status</span><div class="detail-value"><span class="status-badge status-<?php echo strtolower(str_replace([' ', '-'], '', (string) ($order['status'] ?? 'Pending'))); ?>"><?php echo htmlspecialchars((string) ($order['status'] ?? 'Pending'), ENT_QUOTES, 'UTF-8'); ?></span></div></div>
                    </div>

                    <div class="summary-grid">
                        <div class="summary-box"><div style="color: var(--muted); text-transform: uppercase; letter-spacing: 0.12em; font-size: 0.7rem; margin-bottom: 8px;">Subtotal</div><div style="font-weight:700; font-size:1.05rem;"><?php echo format_currency((float) ($order['subtotal'] ?? 0.0)); ?></div></div>
                        <div class="summary-box"><div style="color: var(--muted); text-transform: uppercase; letter-spacing: 0.12em; font-size: 0.7rem; margin-bottom: 8px;">Delivery Fee</div><div style="font-weight:700; font-size:1.05rem;"><?php echo format_currency((float) ($order['delivery_charges'] ?? 0.0)); ?></div></div>
                        <div class="summary-box"><div style="color: var(--muted); text-transform: uppercase; letter-spacing: 0.12em; font-size: 0.7rem; margin-bottom: 8px;">Discount</div><div style="font-weight:700; font-size:1.05rem;"><?php echo format_currency((float) ($order['discount_amount'] ?? 0.0)); ?></div></div>
                        <div class="summary-box"><div style="color: var(--muted); text-transform: uppercase; letter-spacing: 0.12em; font-size: 0.7rem; margin-bottom: 8px;">Grand Total</div><div style="font-weight:700; font-size:1.05rem; color: var(--gold-soft);"><?php echo format_currency((float) ($order['total_amount'] ?? 0.0)); ?></div></div>
                    </div>

                    <div class="actions">
                        <form method="POST" action="order-view.php?id=<?php echo (int) $order['order_id']; ?>" style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                            <input type="hidden" name="update_order_status" value="1">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_admin_order_view_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="order_id" value="<?php echo (int) $order['order_id']; ?>">
                            <div class="field">
                                <label for="status" style="color: var(--gold-soft); font-weight:700;">Update Order Status</label>
                                <select id="status" name="status" <?php echo $statusUpdateDisabled ? 'disabled' : ''; ?>>
                                    <?php foreach ($statusOptions as $statusName): ?>
                                        <option value="<?php echo htmlspecialchars($statusName, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $currentOrderStatus === $statusName ? 'selected' : ''; ?>><?php echo htmlspecialchars($statusName, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button class="btn primary" type="submit" <?php echo $statusUpdateDisabled ? 'disabled' : ''; ?>><i class="fa-solid fa-floppy-disk"></i> Save Status</button>
                            <?php if ($statusUpdateDisabled): ?>
                                <div class="status-final-message">This order has reached a final status and cannot be changed.</div>
                            <?php endif; ?>
                        </form>
                        <a class="btn" href="orders.php"><i class="fa-solid fa-arrow-left"></i> Back to Orders</a>
                    </div>
                </article>

                <article class="panel-card" style="margin-top:22px;">
                    <div class="panel-header">
                        <h2 class="panel-title">Order Items</h2>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Food Item</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                    <th>Item Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($orderItems === []): ?>
                                    <tr>
                                        <td colspan="4" style="text-align:center; color: var(--muted);">No items found for this order.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($orderItems as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars((string) ($item['item_name'] ?? 'Unknown Item'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo (int) ($item['quantity'] ?? 0); ?></td>
                                            <td><?php echo format_currency((float) ($item['unit_price'] ?? 0.0)); ?></td>
                                            <td><?php echo format_currency((float) ($item['item_total'] ?? 0.0)); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
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
