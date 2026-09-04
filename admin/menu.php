<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';

function generate_admin_menu_csrf_token(): string
{
    if (empty($_SESSION['admin_menu_csrf_token'])) {
        $_SESSION['admin_menu_csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['admin_menu_csrf_token'];
}

function verify_admin_menu_csrf_token(string $token): bool
{
    $expected = $_SESSION['admin_menu_csrf_token'] ?? '';
    return is_string($expected) && hash_equals($expected, $token);
}

function format_currency(float $value): string
{
    return 'PKR ' . number_format($value, 2, '.', ',');
}

$adminName = htmlspecialchars((string) ($_SESSION['admin_name'] ?? 'Administrator'), ENT_QUOTES, 'UTF-8');
$adminEmail = htmlspecialchars((string) ($_SESSION['admin_email'] ?? ''), ENT_QUOTES, 'UTF-8');

$flashMessage = $_SESSION['admin_menu_message'] ?? '';
$flashType = $_SESSION['admin_menu_message_type'] ?? 'success';
unset($_SESSION['admin_menu_message'], $_SESSION['admin_menu_message_type']);

$search = trim((string) ($_GET['search'] ?? ''));
$categoryFilter = isset($_GET['category']) ? trim((string) $_GET['category']) : 'all';
$availabilityFilter = isset($_GET['availability']) ? trim((string) $_GET['availability']) : 'all';
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 10;

$allowedAvailabilityFilters = ['all', 'available', 'unavailable'];
if (! in_array($availabilityFilter, $allowedAvailabilityFilters, true)) {
    $availabilityFilter = 'all';
}

$menuFormMode = 'add';
$menuFormItem = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isset($_POST['menu_action'])) {
        $_SESSION['admin_menu_message'] = 'Invalid request.';
        $_SESSION['admin_menu_message_type'] = 'error';
        header('Location: menu.php');
        exit;
    }

    if (! isset($_POST['csrf_token']) || ! verify_admin_menu_csrf_token((string) $_POST['csrf_token'])) {
        $_SESSION['admin_menu_message'] = 'Invalid security token. Please try again.';
        $_SESSION['admin_menu_message_type'] = 'error';
        header('Location: menu.php');
        exit;
    }

    $menuAction = (string) $_POST['menu_action'];

    if ($menuAction === 'toggle_availability') {
        $itemId = filter_input(INPUT_POST, 'menu_id', FILTER_VALIDATE_INT);
        $newAvailability = filter_input(INPUT_POST, 'target_availability', FILTER_VALIDATE_INT);

        if ($itemId === false || $itemId === null || $itemId <= 0) {
            $_SESSION['admin_menu_message'] = 'Invalid menu item ID.';
            $_SESSION['admin_menu_message_type'] = 'error';
            header('Location: menu.php');
            exit;
        }

        if ($newAvailability === false || $newAvailability === null || ! in_array($newAvailability, [0, 1], true)) {
            $_SESSION['admin_menu_message'] = 'Invalid availability value.';
            $_SESSION['admin_menu_message_type'] = 'error';
            header('Location: menu.php');
            exit;
        }

        $connection = get_db_connection();
        $stmt = $connection->prepare('UPDATE menu_items SET is_available = ?, updated_at = CURRENT_TIMESTAMP WHERE menu_id = ?');
        if ($stmt === false) {
            $connection->close();
            $_SESSION['admin_menu_message'] = 'Unable to update menu availability right now.';
            $_SESSION['admin_menu_message_type'] = 'error';
            header('Location: menu.php');
            exit;
        }

        $stmt->bind_param('ii', $newAvailability, $itemId);
        if (! $stmt->execute()) {
            $stmt->close();
            $connection->close();
            $_SESSION['admin_menu_message'] = 'Unable to update menu availability right now.';
            $_SESSION['admin_menu_message_type'] = 'error';
            header('Location: menu.php');
            exit;
        }

        $stmt->close();
        $connection->close();

        $_SESSION['admin_menu_message'] = $newAvailability ? 'Menu item marked as available.' : 'Menu item marked as unavailable.';
        $_SESSION['admin_menu_message_type'] = 'success';
        header('Location: menu.php');
        exit;
    }

    if ($menuAction === 'delete_item') {
        $itemId = filter_input(INPUT_POST, 'menu_id', FILTER_VALIDATE_INT);
        if ($itemId === false || $itemId === null || $itemId <= 0) {
            $_SESSION['admin_menu_message'] = 'Invalid menu item ID.';
            $_SESSION['admin_menu_message_type'] = 'error';
            header('Location: menu.php');
            exit;
        }

        $connection = get_db_connection();
        $usageStmt = $connection->prepare('SELECT COUNT(*) AS total FROM order_items WHERE food_id = ?');
        if ($usageStmt === false) {
            $connection->close();
            $_SESSION['admin_menu_message'] = 'Unable to check item usage.';
            $_SESSION['admin_menu_message_type'] = 'error';
            header('Location: menu.php');
            exit;
        }

        $usageStmt->bind_param('i', $itemId);
        $usageStmt->execute();
        $usage = $usageStmt->get_result()->fetch_assoc();
        $usageStmt->close();

        if ((int) ($usage['total'] ?? 0) > 0) {
            $updateStmt = $connection->prepare('UPDATE menu_items SET is_available = 0, updated_at = CURRENT_TIMESTAMP WHERE menu_id = ?');
            if ($updateStmt === false) {
                $connection->close();
                $_SESSION['admin_menu_message'] = 'Unable to update the menu item safely.';
                $_SESSION['admin_menu_message_type'] = 'error';
                header('Location: menu.php');
                exit;
            }
            $updateStmt->bind_param('i', $itemId);
            $updateStmt->execute();
            $updateStmt->close();
            $connection->close();

            $_SESSION['admin_menu_message'] = 'This item has order history, so it was marked unavailable instead of being deleted.';
            $_SESSION['admin_menu_message_type'] = 'success';
            header('Location: menu.php');
            exit;
        }

        $deleteStmt = $connection->prepare('DELETE FROM menu_items WHERE menu_id = ?');
        if ($deleteStmt === false) {
            $connection->close();
            $_SESSION['admin_menu_message'] = 'Unable to delete the menu item right now.';
            $_SESSION['admin_menu_message_type'] = 'error';
            header('Location: menu.php');
            exit;
        }

        $deleteStmt->bind_param('i', $itemId);
        $deleteStmt->execute();
        $deleteStmt->close();
        $connection->close();

        $_SESSION['admin_menu_message'] = 'Menu item deleted successfully.';
        $_SESSION['admin_menu_message_type'] = 'success';
        header('Location: menu.php');
        exit;
    }

    if ($menuAction === 'add_item' || $menuAction === 'update_item') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $price = trim((string) ($_POST['price'] ?? ''));
        $imageUrl = trim((string) ($_POST['image_url'] ?? ''));
        $isAvailable = isset($_POST['is_available']) ? 1 : 0;

        if ($name === '' || $category === '' || $price === '' || $imageUrl === '') {
            $_SESSION['admin_menu_message'] = 'Item name, category, price, and image URL are required.';
            $_SESSION['admin_menu_message_type'] = 'error';
            header('Location: menu.php?action=' . ($menuAction === 'add_item' ? 'add' : 'edit') . '&id=' . (isset($_POST['menu_id']) ? (int) $_POST['menu_id'] : ''));
            exit;
        }

        if (! filter_var($price, FILTER_VALIDATE_FLOAT)) {
            $_SESSION['admin_menu_message'] = 'Please enter a valid price.';
            $_SESSION['admin_menu_message_type'] = 'error';
            header('Location: menu.php?action=' . ($menuAction === 'add_item' ? 'add' : 'edit') . '&id=' . (isset($_POST['menu_id']) ? (int) $_POST['menu_id'] : ''));
            exit;
        }

        if (! filter_var($imageUrl, FILTER_VALIDATE_URL) || (! preg_match('/^https?:\/\//i', $imageUrl))) {
            $_SESSION['admin_menu_message'] = 'Please provide a valid image URL.';
            $_SESSION['admin_menu_message_type'] = 'error';
            header('Location: menu.php?action=' . ($menuAction === 'add_item' ? 'add' : 'edit') . '&id=' . (isset($_POST['menu_id']) ? (int) $_POST['menu_id'] : ''));
            exit;
        }

        $amount = (float) $price;
        $connection = get_db_connection();

        if ($menuAction === 'add_item') {
            $stmt = $connection->prepare('INSERT INTO menu_items (name, description, category, price, image_url, is_available, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
            if ($stmt === false) {
                $connection->close();
                $_SESSION['admin_menu_message'] = 'Unable to add the menu item right now.';
                $_SESSION['admin_menu_message_type'] = 'error';
                header('Location: menu.php');
                exit;
            }

            $stmt->bind_param('sssdsi', $name, $description, $category, $amount, $imageUrl, $isAvailable);
            $stmt->execute();
            $stmt->close();
            $connection->close();

            $_SESSION['admin_menu_message'] = 'Menu item added successfully.';
            $_SESSION['admin_menu_message_type'] = 'success';
            header('Location: menu.php');
            exit;
        }

        $itemId = filter_input(INPUT_POST, 'menu_id', FILTER_VALIDATE_INT);
        if ($itemId === false || $itemId === null || $itemId <= 0) {
            $connection->close();
            $_SESSION['admin_menu_message'] = 'Invalid menu item ID.';
            $_SESSION['admin_menu_message_type'] = 'error';
            header('Location: menu.php');
            exit;
        }

        $stmt = $connection->prepare('UPDATE menu_items SET name = ?, description = ?, category = ?, price = ?, image_url = ?, is_available = ?, updated_at = CURRENT_TIMESTAMP WHERE menu_id = ?');
        if ($stmt === false) {
            $connection->close();
            $_SESSION['admin_menu_message'] = 'Unable to update the menu item right now.';
            $_SESSION['admin_menu_message_type'] = 'error';
            header('Location: menu.php');
            exit;
        }

        $stmt->bind_param('sssdsii', $name, $description, $category, $amount, $imageUrl, $isAvailable, $itemId);
        $stmt->execute();
        $stmt->close();
        $connection->close();

        $_SESSION['admin_menu_message'] = 'Menu item updated successfully.';
        $_SESSION['admin_menu_message_type'] = 'success';
        header('Location: menu.php');
        exit;
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'edit') {
    $itemId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($itemId !== false && $itemId !== null && $itemId > 0) {
        $connection = get_db_connection();
        $stmt = $connection->prepare('SELECT menu_id, name, description, category, price, image_url, is_available, created_at FROM menu_items WHERE menu_id = ? LIMIT 1');
        if ($stmt !== false) {
            $stmt->bind_param('i', $itemId);
            $stmt->execute();
            $menuFormItem = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
        $connection->close();

        if ($menuFormItem) {
            $menuFormMode = 'edit';
        }
    }
}

$connection = get_db_connection();

$categoriesRow = $connection->query('SELECT DISTINCT category FROM menu_items WHERE category IS NOT NULL AND category != "" ORDER BY category ASC');
$categories = [];
if ($categoriesRow) {
    while ($row = $categoriesRow->fetch_assoc()) {
        $categories[] = (string) ($row['category'] ?? '');
    }
}

$whereClauses = ['1=1'];
$queryParams = [];
$queryTypes = '';

if ($search !== '') {
    $like = '%' . $search . '%';
    $whereClauses[] = '(name LIKE ? OR category LIKE ?)';
    $queryParams[] = $like;
    $queryParams[] = $like;
    $queryTypes .= 'ss';
}

if ($categoryFilter !== 'all' && $categoryFilter !== '') {
    $whereClauses[] = 'category = ?';
    $queryParams[] = $categoryFilter;
    $queryTypes .= 's';
}

if ($availabilityFilter === 'available') {
    $whereClauses[] = 'is_available = 1';
} elseif ($availabilityFilter === 'unavailable') {
    $whereClauses[] = 'is_available = 0';
}

$countSql = 'SELECT COUNT(*) AS total_items FROM menu_items WHERE ' . implode(' AND ', $whereClauses);
$countStmt = $connection->prepare($countSql);
$totalItems = 0;
if ($countStmt !== false) {
    if ($queryParams !== []) {
        $references = [];
        foreach ($queryParams as $key => $value) {
            $references[$key] = &$queryParams[$key];
        }
        $countArgs = array_merge([$queryTypes], $references);
        call_user_func_array([$countStmt, 'bind_param'], $countArgs);
    }
    $countStmt->execute();
    $countRow = $countStmt->get_result()->fetch_assoc();
    $totalItems = (int) ($countRow['total_items'] ?? 0);
    $countStmt->close();
}

$totalPages = max(1, (int) ceil($totalItems / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listSql = 'SELECT menu_id, name, description, category, price, image_url, is_available, created_at
    FROM menu_items
    WHERE ' . implode(' AND ', $whereClauses) . '
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?';

$menuItems = [];
$listStmt = $connection->prepare($listSql);
if ($listStmt !== false) {
    $listParams = $queryParams;
    $listParams[] = $perPage;
    $listParams[] = $offset;
    $listTypes = $queryTypes . 'ii';
    $listReferences = [];
    foreach ($listParams as $key => $value) {
        $listReferences[$key] = &$listParams[$key];
    }
    $listArgs = array_merge([$listTypes], $listReferences);
    call_user_func_array([$listStmt, 'bind_param'], $listArgs);
    $listStmt->execute();
    $listResult = $listStmt->get_result();
    while ($row = $listResult->fetch_assoc()) {
        $menuItems[] = $row;
    }
    $listStmt->close();
}

$stats = [
    'total_items' => 0,
    'available_items' => 0,
    'unavailable_items' => 0,
    'categories' => 0,
];

$statsStmt = $connection->query('SELECT COUNT(*) AS total_items, SUM(CASE WHEN is_available = 1 THEN 1 ELSE 0 END) AS available_items, SUM(CASE WHEN is_available = 0 THEN 1 ELSE 0 END) AS unavailable_items, COUNT(DISTINCT category) AS categories FROM menu_items');
if ($statsStmt && $statsStmt->num_rows > 0) {
    $statsRow = $statsStmt->fetch_assoc();
    $stats['total_items'] = (int) ($statsRow['total_items'] ?? 0);
    $stats['available_items'] = (int) ($statsRow['available_items'] ?? 0);
    $stats['unavailable_items'] = (int) ($statsRow['unavailable_items'] ?? 0);
    $stats['categories'] = (int) ($statsRow['categories'] ?? 0);
}

$connection->close();

$activeFilters = 0;
foreach (['search', 'category', 'availability'] as $filterKey) {
    $value = ${$filterKey} ?? '';
    if ($filterKey === 'category' && ($value === '' || $value === 'all')) {
        continue;
    }
    if ($filterKey === 'availability' && ($value === '' || $value === 'all')) {
        continue;
    }
    if ($value !== '') {
        $activeFilters++;
    }
}

$menuFormAction = $menuFormMode === 'edit' ? 'update_item' : 'add_item';
$headingText = $menuFormMode === 'edit' ? 'Edit Menu Item' : 'Add Menu Item';
$cancelUrl = 'menu.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu | Fast Food Admin</title>
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
        .content { padding: 22px 24px 26px; }
        .flash { margin: 0 0 18px; padding: 12px 14px; border-radius: 12px; font-weight: 600; }
        .flash.success { background: rgba(52, 211, 153, 0.12); border: 1px solid rgba(52, 211, 153, 0.28); color: var(--success); }
        .flash.error { background: rgba(248, 113, 113, 0.12); border: 1px solid rgba(248, 113, 113, 0.28); color: var(--danger); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 18px; margin-bottom: 20px; }
        .stat-card { background: linear-gradient(180deg, var(--panel), var(--panel-soft)); border: 1px solid var(--border); border-radius: 16px; padding: 18px 18px 16px; box-shadow: 0 12px 28px var(--shadow); }
        .stat-icon { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: rgba(212, 175, 55, 0.1); color: var(--gold-soft); margin-bottom: 14px; border: 1px solid var(--border); }
        .stat-label { display: block; color: var(--muted); text-transform: uppercase; letter-spacing: 0.12em; font-size: 0.72rem; margin-bottom: 8px; }
        .stat-value { font-size: clamp(1.5rem, 2.5vw, 2.1rem); font-weight: 700; line-height: 1.2; }
        .panel-card { background: linear-gradient(180deg, var(--panel), var(--panel-soft)); border: 1px solid var(--border); border-radius: 18px; box-shadow: 0 12px 30px var(--shadow); overflow: hidden; }
        .panel-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 14px 18px; border-bottom: 1px solid var(--border); }
        .panel-title { margin: 0; font-size: 1.08rem; }
        .toolbar { display: flex; align-items: stretch; gap: 12px; padding: 12px 18px; min-height: 96px; }
        .toolbar form { display: flex; align-items: flex-end; gap: 10px; width: 100%; }
        .toolbar .field { display: flex; flex: 1; min-width: 0; }
        .toolbar .field label { display: none; }
        .search-wrap { position: relative; width: 100%; }
        .search-wrap i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--gold-soft); }
        .search-input, .filter-select, .text-input, .price-input, .date-input, .textarea-input { width: 100%; min-height: 42px; padding: 10px 12px; border-radius: 10px; border: 1px solid var(--border); background: rgba(255,255,255,0.02); color: var(--text); outline: none; font: inherit; }
        .search-input { flex: 0 0 40%; min-width: 180px; padding-left: 36px; }
        .filter-select { flex: 0 0 20%; min-width: 140px; }
        .toolbar .btn { min-width: 110px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 42px; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--border); color: var(--gold-soft); font-weight: 700; background: rgba(212,175,55,0.08); cursor: pointer; }
        .btn.primary { background: linear-gradient(135deg, var(--gold), var(--gold-soft)); color: #111; border-color: rgba(212,175,55,0.8); }
        .btn.secondary { background: rgba(255,255,255,0.02); }
        .btn.danger { background: rgba(248,113,113,0.08); border-color: rgba(248,113,113,0.3); color: var(--danger); }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; table-layout: fixed; }
        th, td { padding: 12px 14px; border-bottom: 1px solid rgba(212, 175, 55, 0.15); text-align: left; vertical-align: middle; }
        th { color: var(--gold-soft); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.12em; background: rgba(212, 175, 55, 0.04); }
        td { color: var(--text); font-size: 0.94rem; }
        th:nth-child(1), td:nth-child(1) { width: 60px; }
        th:nth-child(2), td:nth-child(2) { width: 80px; }
        th:nth-child(3), td:nth-child(3) { width: 150px; }
        th:nth-child(4), td:nth-child(4) { width: 120px; }
        th:nth-child(5), td:nth-child(5) { width: 230px; }
        th:nth-child(6), td:nth-child(6) { width: 120px; }
        th:nth-child(7), td:nth-child(7) { width: 120px; }
        th:nth-child(8), td:nth-child(8) { width: 120px; }
        th:nth-child(9), td:nth-child(9) { width: 200px; }
        .menu-image { width: 55px; height: 55px; border-radius: 12px; object-fit: cover; border: 1px solid var(--border); background: rgba(255,255,255,0.02); display: block; }
        .status-pill { display: inline-flex; align-items: center; justify-content: center; min-width: 108px; padding: 6px 10px; border-radius: 999px; font-size: 0.68rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; border: 1px solid rgba(255,255,255,0.08); }
        .status-available { background: rgba(52,211,153,0.12); color: var(--success); border-color: rgba(52,211,153,0.28); }
        .status-unavailable { background: rgba(248,113,113,0.12); color: var(--danger); border-color: rgba(248,113,113,0.28); }
        .action-buttons { display: flex; flex-direction: column; align-items: flex-start; gap: 8px; }
        .action-main { display: flex; gap: 8px; flex-wrap: wrap; }
        .action-main .btn, .action-secondary .btn { min-height: 34px; padding: 8px 12px; font-size: 0.8rem; }
        .action-secondary { display: flex; width: 100%; }
        .inline-form { display: inline; }
        .toggle-label { display: inline-flex; align-items: center; gap: 8px; color: var(--text); }
        .checkbox { accent-color: var(--gold-soft); }
        .empty-state { padding: 30px 20px; color: var(--muted); text-align: center; }
        .pagination { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 18px 20px; flex-wrap: wrap; }
        .page-links { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .page-links a, .page-links span { min-width: 38px; min-height: 38px; display: inline-flex; align-items: center; justify-content: center; border-radius: 9px; border: 1px solid var(--border); background: rgba(255,255,255,0.02); color: var(--text); padding: 0 10px; font-weight: 700; }
        .page-links a.active, .page-links span.current { background: rgba(212,175,55,0.12); color: var(--gold-soft); }
        .form-card { background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: 16px; margin: 0 0 22px; }
        .form-card .panel-header { border-bottom: 1px solid var(--border); }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; padding: 20px; }
        .form-actions { display: flex; gap: 12px; flex-wrap: wrap; padding: 0 20px 20px; }
        .filter-note { color: var(--muted); font-size: 0.8rem; }
        .filter-note strong { color: var(--gold-soft); }
        .menu-row-title { font-weight: 700; color: var(--text); }
        .menu-row-meta { color: var(--muted); font-size: 0.78rem; }
        .menu-toggle { display: none; }
        @media (max-width: 980px) {
            .toolbar { flex-wrap: wrap; }
            .toolbar form { flex-wrap: wrap; }
            .search-input { flex-basis: 100%; }
            .filter-select { flex-basis: calc(50% - 6px); }
            .toolbar .btn { flex: 0 0 auto; }
        }
        @media (max-width: 820px) {
            .admin-layout { display: block; }
            .sidebar { position: fixed; left: 0; top: 0; transform: translateX(-100%); z-index: 20; height: 100vh; width: 260px; box-shadow: 0 18px 38px rgba(0, 0, 0, 0.35); }
            .sidebar.open { transform: translateX(0); }
            .menu-toggle { display: inline-flex; width: 44px; height: 44px; border-radius: 10px; border: 1px solid var(--border); background: rgba(212,175,55,0.08); color: var(--gold-soft); align-items: center; justify-content: center; cursor: pointer; }
            .topbar { padding: 18px 20px; }
            .content { padding: 20px; }
            .toolbar { min-height: auto; }
            .toolbar form { display: grid; grid-template-columns: 1fr; }
            .search-input, .filter-select, .toolbar .btn { flex-basis: auto; width: 100%; }
            .form-grid { grid-template-columns: 1fr; }
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
                <a class="nav-link active" href="menu.php"><i class="fa-solid fa-burger"></i> Menu</a>
                <a class="nav-link" href="reports.php"><i class="fa-solid fa-chart-column"></i> Reports</a>
                <a class="nav-link" href="profile.php"><i class="fa-solid fa-user-gear"></i> Profile</a>
                <a class="nav-link logout-link" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </nav>
        </aside>

        <main class="main-panel">
            <header class="topbar">
                <div style="display:flex;align-items:center;gap:12px;">
                    <button class="menu-toggle" type="button" aria-label="Toggle sidebar" id="menuToggle"><i class="fa-solid fa-bars"></i></button>
                    <h1 class="page-title">Menu Management</h1>
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

                <section class="stats-grid" aria-label="Menu statistics">
                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-burger"></i></div>
                        <span class="stat-label">Total Menu Items</span>
                        <div class="stat-value"><?php echo number_format($stats['total_items']); ?></div>
                    </article>
                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
                        <span class="stat-label">Available Items</span>
                        <div class="stat-value"><?php echo number_format($stats['available_items']); ?></div>
                    </article>
                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-ban"></i></div>
                        <span class="stat-label">Unavailable Items</span>
                        <div class="stat-value"><?php echo number_format($stats['unavailable_items']); ?></div>
                    </article>
                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-tags"></i></div>
                        <span class="stat-label">Categories</span>
                        <div class="stat-value"><?php echo number_format($stats['categories']); ?></div>
                    </article>
                </section>

                <article class="panel-card">
                    <div class="panel-header" style="padding:12px 18px;">
                        <h2 class="panel-title" style="font-size:1.05rem;">Menu Items</h2>
                        <a class="btn primary" href="menu.php?action=add"><i class="fa-solid fa-plus"></i> + Add Menu Item</a>
                    </div>

                    <?php if (isset($_GET['action']) && $_GET['action'] === 'add' || (isset($_GET['action']) && $_GET['action'] === 'edit' && $menuFormItem)): ?>
                        <div class="form-card">
                            <div class="panel-header">
                                <h3 class="panel-title"><?php echo $headingText; ?></h3>
                            </div>
                            <form method="POST" action="menu.php" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_admin_menu_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                <?php if ($menuFormMode === 'edit' && $menuFormItem): ?>
                                    <input type="hidden" name="menu_id" value="<?php echo (int) ($menuFormItem['menu_id'] ?? 0); ?>">
                                <?php endif; ?>
                                <input type="hidden" name="menu_action" value="<?php echo htmlspecialchars($menuFormAction, ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="form-grid">
                                    <div class="field">
                                        <label for="menuName">Item Name</label>
                                        <input id="menuName" class="text-input" type="text" name="name" value="<?php echo htmlspecialchars((string) (($menuFormItem['name'] ?? '') ?: ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                                    </div>
                                    <div class="field">
                                        <label for="menuCategory">Category</label>
                                        <input id="menuCategory" class="text-input" type="text" name="category" value="<?php echo htmlspecialchars((string) (($menuFormItem['category'] ?? '') ?: ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                                    </div>
                                    <div class="field">
                                        <label for="menuPrice">Price</label>
                                        <input id="menuPrice" class="price-input" type="number" step="0.01" min="0" name="price" value="<?php echo htmlspecialchars((string) (($menuFormItem['price'] ?? '') ?: ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                                    </div>
                                    <div class="field">
                                        <label for="menuImage">Image URL</label>
                                        <input id="menuImage" class="text-input" type="url" name="image_url" value="<?php echo htmlspecialchars((string) (($menuFormItem['image_url'] ?? '') ?: ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                                    </div>
                                    <div class="field" style="grid-column: 1 / -1;">
                                        <label for="menuDescription">Description</label>
                                        <textarea id="menuDescription" class="textarea-input" name="description" required><?php echo htmlspecialchars((string) (($menuFormItem['description'] ?? '') ?: ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                    </div>
                                    <div class="field">
                                        <label>Availability</label>
                                        <label class="toggle-label"><input class="checkbox" type="checkbox" name="is_available" value="1" <?php echo ((int) (($menuFormItem['is_available'] ?? 1) ?: 0) === 1) ? 'checked' : ''; ?>> Available</label>
                                    </div>
                                </div>
                                <div class="form-actions">
                                    <button class="btn primary" type="submit"><?php echo $menuFormMode === 'edit' ? 'Save Changes' : 'Add Item'; ?></button>
                                    <a class="btn secondary" href="menu.php">Cancel</a>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>

                    <div class="toolbar">
                        <form method="GET" action="menu.php">
                            <div class="field" style="flex: 0 0 40%;">
                                <div class="search-wrap">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                    <input id="searchMenu" class="search-input" type="search" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search Menu">
                                </div>
                            </div>
                            <div class="field" style="flex: 0 0 20%;">
                                <select id="menuCategoryFilter" class="filter-select" name="category">
                                    <option value="all" <?php echo $categoryFilter === 'all' ? 'selected' : ''; ?>>Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars((string) $category, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $categoryFilter === (string) $category ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $category, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field" style="flex: 0 0 20%;">
                                <select id="menuAvailabilityFilter" class="filter-select" name="availability">
                                    <option value="all" <?php echo $availabilityFilter === 'all' ? 'selected' : ''; ?>>Availability</option>
                                    <option value="available" <?php echo $availabilityFilter === 'available' ? 'selected' : ''; ?>>Available</option>
                                    <option value="unavailable" <?php echo $availabilityFilter === 'unavailable' ? 'selected' : ''; ?>>Unavailable</option>
                                </select>
                            </div>
                            <button class="btn primary" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
                            <a class="btn secondary" href="menu.php"><i class="fa-solid fa-xmark"></i> Reset</a>
                        </form>
                    </div>

                    <?php if ($activeFilters > 0): ?>
                        <div class="panel-header" style="padding-top:0; padding-bottom:12px; border-bottom:none;">
                            <div class="filter-note"><strong><?php echo $activeFilters; ?> filter<?php echo $activeFilters === 1 ? '' : 's'; ?> applied</strong> · <a href="menu.php" style="color:var(--gold-soft); text-decoration:none;">Clear All</a></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($menuItems === []): ?>
                        <div class="empty-state"><?php echo $search !== '' || $categoryFilter !== 'all' || $availabilityFilter !== 'all' ? 'No menu items match your current search or filters.' : 'No menu items found.'; ?></div>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Item ID</th>
                                        <th>Image</th>
                                        <th>Item Name</th>
                                        <th>Category</th>
                                        <th>Description</th>
                                        <th>Price</th>
                                        <th>Availability</th>
                                        <th>Created Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($menuItems as $item): ?>
                                        <?php
                                            $itemId = (int) ($item['menu_id'] ?? 0);
                                            $itemName = htmlspecialchars((string) ($item['name'] ?? 'Unnamed Item'), ENT_QUOTES, 'UTF-8');
                                            $itemCategory = htmlspecialchars((string) ($item['category'] ?? 'General'), ENT_QUOTES, 'UTF-8');
                                            $itemDescription = htmlspecialchars((string) ($item['description'] ?? ''), ENT_QUOTES, 'UTF-8');
                                            $itemPrice = (float) ($item['price'] ?? 0.0);
                                            $imageUrl = (string) ($item['image_url'] ?? '');
                                            $itemAvailable = (int) ($item['is_available'] ?? 0);
                                            $createdAt = htmlspecialchars((string) ($item['created_at'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        ?>
                                        <tr>
                                            <td>#<?php echo $itemId; ?></td>
                                            <td>
                                                <?php if ($imageUrl !== ''): ?>
                                                    <img class="menu-image" src="<?php echo htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $itemName; ?>">
                                                <?php else: ?>
                                                    <div class="menu-image" style="display:flex; align-items:center; justify-content:center; color:var(--muted); font-size:0.7rem;">No Image</div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="menu-row-title"><?php echo $itemName; ?></div>
                                            </td>
                                            <td><?php echo $itemCategory; ?></td>
                                            <td style="max-width: 240px; color: var(--muted);"><?php echo $itemDescription !== '' ? $itemDescription : 'No description provided.'; ?></td>
                                            <td><?php echo format_currency($itemPrice); ?></td>
                                            <td>
                                                <span class="status-pill <?php echo $itemAvailable ? 'status-available' : 'status-unavailable'; ?>"><?php echo $itemAvailable ? 'Available' : 'Unavailable'; ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars(date('M j, Y', strtotime($createdAt ?: 'now')), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <div class="action-main">
                                                        <a class="btn secondary" href="menu.php?action=edit&id=<?php echo $itemId; ?>"><i class="fa-solid fa-pen-to-square"></i> Edit</a>
                                                        <form class="inline-form" method="POST" action="menu.php" onsubmit="return confirm('Are you sure you want to delete this menu item?');">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_admin_menu_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                                            <input type="hidden" name="menu_action" value="delete_item">
                                                            <input type="hidden" name="menu_id" value="<?php echo $itemId; ?>">
                                                            <button class="btn danger" type="submit"><i class="fa-solid fa-trash"></i> Delete</button>
                                                        </form>
                                                    </div>
                                                    <div class="action-secondary">
                                                        <form class="inline-form" method="POST" action="menu.php">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_admin_menu_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                                            <input type="hidden" name="menu_action" value="toggle_availability">
                                                            <input type="hidden" name="menu_id" value="<?php echo $itemId; ?>">
                                                            <button class="btn secondary" type="submit" name="target_availability" value="<?php echo $itemAvailable ? '0' : '1'; ?>" <?php echo $itemAvailable ? '' : 'style="background: rgba(52,211,153,0.08); color:var(--success);"'; ?>><?php echo $itemAvailable ? 'Mark Unavailable' : 'Mark Available'; ?></button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php if ($totalItems > 0): ?>
                        <div class="pagination">
                            <div>Page <?php echo $page; ?> of <?php echo $totalPages; ?></div>
                            <div class="page-links">
                                <?php if ($page > 1): ?>
                                    <a href="menu.php?<?php echo http_build_query(['search' => $search, 'category' => $categoryFilter, 'availability' => $availabilityFilter, 'page' => $page - 1]); ?>">Previous</a>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <?php if ($i === $page): ?>
                                        <span class="current"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a href="menu.php?<?php echo http_build_query(['search' => $search, 'category' => $categoryFilter, 'availability' => $availabilityFilter, 'page' => $i]); ?>"><?php echo $i; ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="menu.php?<?php echo http_build_query(['search' => $search, 'category' => $categoryFilter, 'availability' => $availabilityFilter, 'page' => $page + 1]); ?>">Next</a>
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
