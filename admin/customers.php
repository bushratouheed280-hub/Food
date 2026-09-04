<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';

function generate_admin_csrf_token(): string
{
    if (empty($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['admin_csrf_token'];
}

function verify_admin_csrf_token(string $token): bool
{
    $expected = $_SESSION['admin_csrf_token'] ?? '';
    return is_string($expected) && hash_equals($expected, $token);
}

$currentAdminName = htmlspecialchars((string) ($_SESSION['admin_name'] ?? 'Administrator'), ENT_QUOTES, 'UTF-8');
$currentAdminEmail = htmlspecialchars((string) ($_SESSION['admin_email'] ?? ''), ENT_QUOTES, 'UTF-8');

$search = trim((string) ($_GET['search'] ?? ''));
$sortBy = isset($_GET['sort']) ? (string) $_GET['sort'] : 'customer_id';
$sortDirection = isset($_GET['direction']) && strtoupper((string) $_GET['direction']) === 'ASC' ? 'ASC' : 'DESC';
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 10;
$sortableColumns = [
    'customer_id' => 'c.customer_id',
    'full_name' => 'c.full_name',
    'created_at' => 'c.created_at',
];
$sortColumn = $sortableColumns[$sortBy] ?? 'c.customer_id';

$searchClause = '';
$searchParams = [];
if ($search !== '') {
    $searchClause = ' AND (c.full_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)';
    $likeTerm = '%' . $search . '%';
    $searchParams = [$likeTerm, $likeTerm, $likeTerm];
}

$connection = get_db_connection();

$countSql = 'SELECT COUNT(*) AS total_customers FROM customers c WHERE 1=1' . $searchClause;
$countStmt = $connection->prepare($countSql);
if ($countStmt === false) {
    $totalCustomers = 0;
    $totalPages = 1;
} else {
    if ($searchParams !== []) {
        $countStmt->bind_param('sss', ...$searchParams);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalCustomers = (int) ($countResult->fetch_assoc()['total_customers'] ?? 0);
    $countStmt->close();
    $totalPages = max(1, (int) ceil($totalCustomers / $perPage));
    $page = min($page, $totalPages);
}

$offset = ($page - 1) * $perPage;

$statsSql = 'SELECT COUNT(*) AS total_customers,
    SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS new_customers,
    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS today_new_customers
    FROM customers';
$statsResult = $connection->query($statsSql);
$stats = [
    'total_customers' => 0,
    'active_customers' => 0,
    'new_customers' => 0,
    'today_new_customers' => 0,
];
if ($statsResult && $statsResult->num_rows > 0) {
    $statsRow = $statsResult->fetch_assoc();
    $stats['total_customers'] = (int) ($statsRow['total_customers'] ?? 0);
    $stats['active_customers'] = $stats['total_customers'];
    $stats['new_customers'] = (int) ($statsRow['new_customers'] ?? 0);
    $stats['today_new_customers'] = (int) ($statsRow['today_new_customers'] ?? 0);
}

$listSql = 'SELECT c.customer_id, c.full_name, c.email, c.phone, c.address, c.created_at, c.updated_at
    FROM customers c
    WHERE 1=1' . $searchClause . '
    ORDER BY ' . $sortColumn . ' ' . $sortDirection . '
    LIMIT ? OFFSET ?';

$listStmt = $connection->prepare($listSql);
$customers = [];
if ($listStmt !== false) {
    $bindTypes = '';
    $bindParams = [];
    if ($searchParams !== []) {
        $bindTypes = str_repeat('s', count($searchParams));
        $bindParams = array_merge($searchParams, ['i', 'i']);
    } else {
        $bindParams = ['i', 'i'];
    }

    $bindTypes .= 'ii';
    $bindValues = array_merge($searchParams, [ $perPage, $offset ]);

    if ($searchParams !== []) {
        $listStmt->bind_param($bindTypes, ...$bindValues);
    } else {
        $listStmt->bind_param('ii', $perPage, $offset);
    }

    $listStmt->execute();
    $listResult = $listStmt->get_result();
    while ($customer = $listResult->fetch_assoc()) {
        $customers[] = $customer;
    }
    $listStmt->close();
}

$connection->close();

$flashMessage = $_SESSION['admin_customer_message'] ?? '';
$flashType = $_SESSION['admin_customer_message_type'] ?? 'success';
unset($_SESSION['admin_customer_message'], $_SESSION['admin_customer_message_type']);

$sortQueryString = function (string $column) use ($search, $sortDirection) {
    $nextDirection = 'ASC';
    if (isset($_GET['sort']) && $_GET['sort'] === $column && strtoupper((string) ($_GET['direction'] ?? 'DESC')) === 'ASC') {
        $nextDirection = 'DESC';
    }

    $query = ['search' => $search, 'sort' => $column, 'direction' => $nextDirection, 'page' => 1];
    return '?' . http_build_query($query);
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers | Fast Food Admin</title>
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
        .admin-layout { display: flex; min-height: 100vh; }
        .sidebar {
            width: 260px;
            background: var(--sidebar);
            border-right: 1px solid var(--border);
            position: sticky;
            top: 0;
            height: 100vh;
            padding: 22px 18px;
        }
        .brand-block { padding: 10px 10px 22px; border-bottom: 1px solid var(--border); margin-bottom: 18px; }
        .brand-block .logo { display: block; color: var(--gold-soft); font-size: 0.8rem; letter-spacing: 0.18em; text-transform: uppercase; font-weight: 700; margin-bottom: 6px; }
        .brand-block .admin-label { color: var(--muted); font-size: 0.8rem; letter-spacing: 0.14em; text-transform: uppercase; }
        .nav-menu { display: grid; gap: 8px; margin-top: 18px; }
        .nav-link {
            display: flex; align-items: center; gap: 12px; padding: 12px 14px; color: var(--text);
            border-radius: 12px; border: 1px solid transparent; transition: all 0.2s ease; font-weight: 600;
        }
        .nav-link i { width: 18px; display: inline-flex; justify-content: center; color: var(--gold-soft); }
        .nav-link:hover, .nav-link.active {
            background: rgba(212, 175, 55, 0.1); border-color: var(--border); color: var(--gold-soft);
        }
        .nav-link.logout-link { margin-top: 18px; background: rgba(212, 175, 55, 0.08); border-color: var(--border); }
        .main-panel { flex: 1; min-width: 0; }
        .topbar {
            display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 24px 28px 18px;
            border-bottom: 1px solid var(--border); background: rgba(17, 18, 20, 0.4); position: sticky; top: 0; backdrop-filter: blur(8px); z-index: 5;
        }
        .page-title { font-size: clamp(1.7rem, 2.5vw, 2.3rem); margin: 0; }
        .header-user { display: flex; align-items: center; gap: 16px; }
        .welcome-block { text-align: right; }
        .welcome-label { display: block; color: var(--muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.12em; }
        .welcome-name { font-weight: 700; color: var(--gold-soft); font-size: 1rem; }
        .welcome-email { color: var(--muted); font-size: 0.82rem; }
        .avatar {
            width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, var(--gold), var(--gold-soft)); color: #111; font-weight: 700; border: 1px solid rgba(212, 175, 55, 0.5);
        }
        .logout-btn {
            display: inline-flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--gold), var(--gold-soft));
            color: #111; border: none; padding: 10px 16px; border-radius: 10px; font-weight: 700; cursor: pointer;
        }
        .content { padding: 28px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 18px; margin-bottom: 28px; }
        .stat-card {
            background: linear-gradient(180deg, var(--panel), var(--panel-soft)); border: 1px solid var(--border); border-radius: 16px;
            padding: 18px 18px 16px; box-shadow: 0 12px 28px var(--shadow);
        }
        .stat-icon {
            width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
            background: rgba(212, 175, 55, 0.1); color: var(--gold-soft); margin-bottom: 14px; border: 1px solid var(--border);
        }
        .stat-label { display: block; color: var(--muted); text-transform: uppercase; letter-spacing: 0.12em; font-size: 0.72rem; margin-bottom: 8px; }
        .stat-value { font-size: clamp(1.5rem, 2.5vw, 2.1rem); font-weight: 700; line-height: 1.2; }
        .panel-card {
            background: linear-gradient(180deg, var(--panel), var(--panel-soft)); border: 1px solid var(--border); border-radius: 18px;
            box-shadow: 0 12px 30px var(--shadow); overflow: hidden;
        }
        .panel-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 18px 20px; border-bottom: 1px solid var(--border); }
        .panel-title { margin: 0; font-size: 1.1rem; }
        .toolbar { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; padding: 18px 20px; }
        .search-form { flex: 1 1 260px; display: flex; gap: 10px; }
        .search-input {
            width: 100%; padding: 12px 14px; border-radius: 10px; border: 1px solid var(--border); background: rgba(255,255,255,0.02); color: var(--text);
            outline: none;
        }
        .search-input::placeholder { color: var(--muted); }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px; border-radius: 10px; font-weight: 700; border: 1px solid var(--border);
            background: rgba(212, 175, 55, 0.08); color: var(--gold-soft); padding: 10px 14px; cursor: pointer;
        }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 880px; }
        th, td { padding: 14px 18px; border-bottom: 1px solid rgba(212, 175, 55, 0.15); text-align: left; vertical-align: middle; }
        th { color: var(--gold-soft); font-size: 0.76rem; text-transform: uppercase; letter-spacing: 0.12em; background: rgba(212, 175, 55, 0.04); }
        td { color: var(--text); font-size: 0.96rem; }
        .customer-actions { display: flex; align-items: center; gap: 8px; }
        .icon-btn {
            width: 34px; height: 34px; border-radius: 9px; border: 1px solid var(--border); background: rgba(255,255,255,0.02); color: var(--gold-soft); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s ease;
        }
        .icon-btn:hover { border-color: rgba(212,175,55,0.7); transform: translateY(-1px); }
        .icon-btn.delete { color: var(--danger); }
        .empty-state { padding: 30px 20px; color: var(--muted); text-align: center; }
        .flash { margin: 0 0 18px; padding: 12px 14px; border-radius: 12px; font-weight: 600; }
        .flash.success { background: rgba(52, 211, 153, 0.12); border: 1px solid rgba(52, 211, 153, 0.28); color: var(--success); }
        .flash.error { background: rgba(248, 113, 113, 0.12); border: 1px solid rgba(248, 113, 113, 0.28); color: var(--danger); }
        .pagination { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 18px 20px; flex-wrap: wrap; }
        .page-links { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .page-links a, .page-links span {
            min-width: 38px; min-height: 38px; display: inline-flex; align-items: center; justify-content: center; border-radius: 9px;
            border: 1px solid var(--border); background: rgba(255,255,255,0.02); color: var(--text); padding: 0 10px; font-weight: 700;
        }
        .page-links a.active, .page-links span.current { background: rgba(212,175,55,0.12); color: var(--gold-soft); }
        .hidden-mobile { display: inline; }
        .menu-toggle { display: none; }
        @media (max-width: 820px) {
            .admin-layout { display: block; }
            .sidebar {
                position: fixed; left: 0; top: 0; transform: translateX(-100%); z-index: 20; height: 100vh; width: 260px; box-shadow: 0 18px 38px rgba(0,0,0,0.35);
            }
            .sidebar.open { transform: translateX(0); }
            .menu-toggle {
                display: inline-flex; width: 44px; height: 44px; border-radius: 10px; border: 1px solid var(--border); background: rgba(212,175,55,0.08); color: var(--gold-soft); align-items: center; justify-content: center; cursor: pointer;
            }
            .topbar { padding: 18px 20px; }
            .content { padding: 20px; }
            .hidden-mobile { display: none; }
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
                    <button class="menu-toggle" type="button" aria-label="Toggle sidebar" id="menuToggle">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <h1 class="page-title">Customers</h1>
                </div>
                <div class="header-user">
                    <div class="welcome-block">
                        <span class="welcome-label">Welcome</span>
                        <div class="welcome-name"><?php echo $currentAdminName; ?></div>
                        <div class="welcome-email"><?php echo $currentAdminEmail; ?></div>
                    </div>
                    <div class="avatar"><?php echo strtoupper(substr($currentAdminName, 0, 1)); ?></div>
                    <a class="logout-btn" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> <span class="hidden-mobile">Logout</span></a>
                </div>
            </header>

            <div class="content">
                <?php if ($flashMessage !== ''): ?>
                    <div class="flash <?php echo htmlspecialchars($flashType, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <section class="stats-grid" aria-label="Customer statistics">
                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
                        <span class="stat-label">Total Customers</span>
                        <div class="stat-value"><?php echo number_format($stats['total_customers']); ?></div>
                    </article>
                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-user-check"></i></div>
                        <span class="stat-label">Active / Registered</span>
                        <div class="stat-value"><?php echo number_format($stats['active_customers']); ?></div>
                    </article>
                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-user-plus"></i></div>
                        <span class="stat-label">New Customers</span>
                        <div class="stat-value"><?php echo number_format($stats['new_customers']); ?></div>
                    </article>
                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-calendar-day"></i></div>
                        <span class="stat-label">Today's New Customers</span>
                        <div class="stat-value"><?php echo number_format($stats['today_new_customers']); ?></div>
                    </article>
                </section>

                <article class="panel-card">
                    <div class="panel-header">
                        <h2 class="panel-title">Customer Directory</h2>
                        <span class="muted" style="color: var(--muted); font-size: 0.9rem;">Showing <?php echo number_format(count($customers)); ?> results</span>
                    </div>

                    <div class="toolbar">
                        <form class="search-form" method="GET" action="customers.php">
                            <input class="search-input" type="search" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search customers..." aria-label="Search customers">
                            <button type="submit" class="btn"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
                            <?php if ($search !== ''): ?>
                                <a class="btn" href="customers.php"><i class="fa-solid fa-xmark"></i> Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <?php if ($customers === []): ?>
                        <div class="empty-state"><?php echo $search !== '' ? 'No customers match your search.' : 'No customers found.'; ?></div>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th><a href="<?php echo $sortQueryString('customer_id'); ?>" style="color: var(--gold-soft);">Customer ID</a></th>
                                        <th><a href="<?php echo $sortQueryString('full_name'); ?>" style="color: var(--gold-soft);">Full Name</a></th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Address</th>
                                        <th><a href="<?php echo $sortQueryString('created_at'); ?>" style="color: var(--gold-soft);">Created At</a></th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($customers as $customer): ?>
                                        <?php
                                            $customerId = (int) ($customer['customer_id'] ?? 0);
                                            $fullName = htmlspecialchars((string) ($customer['full_name'] ?? 'Unknown Customer'), ENT_QUOTES, 'UTF-8');
                                            $email = htmlspecialchars((string) ($customer['email'] ?? ''), ENT_QUOTES, 'UTF-8');
                                            $phone = htmlspecialchars((string) ($customer['phone'] ?? 'N/A'), ENT_QUOTES, 'UTF-8');
                                            $address = htmlspecialchars((string) ($customer['address'] ?? 'N/A'), ENT_QUOTES, 'UTF-8');
                                            $createdAt = htmlspecialchars((string) ($customer['created_at'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        ?>
                                        <tr>
                                            <td>#<?php echo $customerId; ?></td>
                                            <td><?php echo $fullName; ?></td>
                                            <td><?php echo $email; ?></td>
                                            <td><?php echo $phone; ?></td>
                                            <td><?php echo strlen($address) > 50 ? substr($address, 0, 50) . '...' : $address; ?></td>
                                            <td><?php echo htmlspecialchars(date('M j, Y', strtotime($createdAt ?: 'now')), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <div class="customer-actions">
                                                    <a class="icon-btn" href="customer-view.php?id=<?php echo $customerId; ?>" title="View customer" aria-label="View customer"><i class="fa-solid fa-eye"></i></a>
                                                    <a class="icon-btn" href="customer-edit.php?id=<?php echo $customerId; ?>" title="Edit customer" aria-label="Edit customer"><i class="fa-solid fa-pen-to-square"></i></a>
                                                    <form method="POST" action="customers.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this customer? This action cannot be undone.');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_admin_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                                        <input type="hidden" name="delete_customer_id" value="<?php echo $customerId; ?>">
                                                        <button class="icon-btn delete" type="submit" title="Delete customer" aria-label="Delete customer"><i class="fa-solid fa-trash"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php if ($totalCustomers > 0): ?>
                        <div class="pagination">
                            <div>
                                Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                            </div>
                            <div class="page-links">
                                <?php if ($page > 1): ?>
                                    <a href="customers.php?<?php echo http_build_query(['search' => $search, 'sort' => $sortBy, 'direction' => $sortDirection, 'page' => $page - 1]); ?>">Previous</a>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <?php if ($i === $page): ?>
                                        <span class="current"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a href="customers.php?<?php echo http_build_query(['search' => $search, 'sort' => $sortBy, 'direction' => $sortDirection, 'page' => $i]); ?>"><?php echo $i; ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="customers.php?<?php echo http_build_query(['search' => $search, 'sort' => $sortBy, 'direction' => $sortDirection, 'page' => $page + 1]); ?>">Next</a>
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
    </script>
</body>
</html>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_customer_id'])) {
    if (! isset($_POST['csrf_token']) || ! verify_admin_csrf_token((string) $_POST['csrf_token'])) {
        $_SESSION['admin_customer_message'] = 'Invalid security token. Please try again.';
        $_SESSION['admin_customer_message_type'] = 'error';
        header('Location: customers.php');
        exit;
    }

    $customerId = filter_input(INPUT_POST, 'delete_customer_id', FILTER_VALIDATE_INT);
    if ($customerId === false || $customerId === null || $customerId <= 0) {
        $_SESSION['admin_customer_message'] = 'Invalid customer ID.';
        $_SESSION['admin_customer_message_type'] = 'error';
        header('Location: customers.php');
        exit;
    }

    $deleteConnection = get_db_connection();

    $customerCheckStmt = $deleteConnection->prepare('SELECT customer_id FROM customers WHERE customer_id = ? LIMIT 1');
    if ($customerCheckStmt === false) {
        $deleteConnection->close();
        $_SESSION['admin_customer_message'] = 'Unable to verify customer record.';
        $_SESSION['admin_customer_message_type'] = 'error';
        header('Location: customers.php');
        exit;
    }

    $customerCheckStmt->bind_param('i', $customerId);
    $customerCheckStmt->execute();
    $customerResult = $customerCheckStmt->get_result();
    $customerExists = $customerResult->fetch_assoc() !== null;
    $customerCheckStmt->close();

    if (! $customerExists) {
        $deleteConnection->close();
        $_SESSION['admin_customer_message'] = 'Customer not found.';
        $_SESSION['admin_customer_message_type'] = 'error';
        header('Location: customers.php');
        exit;
    }

    $orderCheckStmt = $deleteConnection->prepare('SELECT COUNT(*) AS existing_orders FROM orders WHERE customer_id = ?');
    if ($orderCheckStmt === false) {
        $deleteConnection->close();
        $_SESSION['admin_customer_message'] = 'Unable to verify customer order history.';
        $_SESSION['admin_customer_message_type'] = 'error';
        header('Location: customers.php');
        exit;
    }

    $orderCheckStmt->bind_param('i', $customerId);
    $orderCheckStmt->execute();
    $orderCheckResult = $orderCheckStmt->get_result();
    $hasOrders = ((int) ($orderCheckResult->fetch_assoc()['existing_orders'] ?? 0)) > 0;
    $orderCheckStmt->close();

    if ($hasOrders) {
        $deleteConnection->close();
        $_SESSION['admin_customer_message'] = 'Customer cannot be deleted because this customer has existing orders.';
        $_SESSION['admin_customer_message_type'] = 'error';
        header('Location: customers.php');
        exit;
    }

    $deleteStmt = $deleteConnection->prepare('DELETE FROM customers WHERE customer_id = ?');
    if ($deleteStmt === false) {
        $deleteConnection->close();
        $_SESSION['admin_customer_message'] = 'Unable to delete customer record.';
        $_SESSION['admin_customer_message_type'] = 'error';
        header('Location: customers.php');
        exit;
    }

    $deleteStmt->bind_param('i', $customerId);

    try {
        $deleteStmt->execute();
    } catch (mysqli_sql_exception $exception) {
        $deleteStmt->close();
        $deleteConnection->close();
        $_SESSION['admin_customer_message'] = 'Customer cannot be deleted because this customer has existing orders.';
        $_SESSION['admin_customer_message_type'] = 'error';
        header('Location: customers.php');
        exit;
    }

    $deleteStmt->close();
    $deleteConnection->close();

    $_SESSION['admin_customer_message'] = 'Customer deleted successfully.';
    $_SESSION['admin_customer_message_type'] = 'success';
    header('Location: customers.php');
    exit;
}
?>
