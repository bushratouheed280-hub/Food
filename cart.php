<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    require_once __DIR__ . '/config/security.php';
    start_secure_session('fastfood_customer');
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/security.php';

function get_session_cart(): array
{
    if (! isset($_SESSION['cart']) || ! is_array($_SESSION['cart'])) {
        return [];
    }

    return $_SESSION['cart'];
}

function get_cart_quantity_total(): int
{
    $total = 0;
    foreach (get_session_cart() as $item) {
        $quantity = isset($item['quantity']) && is_numeric($item['quantity']) ? (int) $item['quantity'] : 0;
        $total += max(0, $quantity);
    }

    return $total;
}

function get_menu_item_by_id(int $menuId): ?array
{
    try {
        $connection = get_db_connection();
    } catch (Throwable $exception) {
        return null;
    }

    $statement = $connection->prepare('SELECT menu_id, name, price, image_url, is_available FROM menu_items WHERE menu_id = ? LIMIT 1');
    if (! $statement) {
        $connection->close();
        return null;
    }

    $statement->bind_param('i', $menuId);
    if (! $statement->execute()) {
        $statement->close();
        $connection->close();
        return null;
    }

    $result = $statement->get_result();
    $item = $result ? $result->fetch_assoc() : null;
    $statement->close();
    $connection->close();

    return $item;
}

function rebuild_cart_from_session(): array
{
    $currentCart = get_session_cart();
    $sanitized = [];
    $items = [];

    foreach ($currentCart as $key => $entry) {
        $menuId = isset($entry['menu_id']) && filter_var($entry['menu_id'], FILTER_VALIDATE_INT) !== false
            ? (int) $entry['menu_id']
            : 0;

        if ($menuId <= 0) {
            continue;
        }

        $menuItem = get_menu_item_by_id($menuId);
        if (! $menuItem || (int) $menuItem['is_available'] !== 1) {
            continue;
        }

        $quantity = isset($entry['quantity']) && is_numeric($entry['quantity']) ? (int) $entry['quantity'] : 1;
        $quantity = max(1, $quantity);

        $sanitized[$menuId] = [
            'menu_id' => $menuId,
            'name' => (string) $menuItem['name'],
            'price' => (float) $menuItem['price'],
            'image_url' => (string) $menuItem['image_url'],
            'quantity' => $quantity,
        ];

        $items[] = [
            'menu_id' => $menuId,
            'name' => (string) $menuItem['name'],
            'image_url' => (string) $menuItem['image_url'],
            'price' => (float) $menuItem['price'],
            'quantity' => $quantity,
            'subtotal' => (float) $menuItem['price'] * $quantity,
        ];
    }

    $_SESSION['cart'] = $sanitized;

    $total = 0.0;
    foreach ($items as $item) {
        $total += (float) $item['subtotal'];
    }

    return [
        'items' => $items,
        'total' => $total,
    ];
}

function add_item_to_cart(int $menuId): array
{
    $menuItem = get_menu_item_by_id($menuId);
    if (! $menuItem || (int) $menuItem['is_available'] !== 1) {
        return ['success' => false, 'message' => 'This menu item is not available.'];
    }

    $cart = get_session_cart();
    $key = (string) $menuId;

    if (isset($cart[$key])) {
        $currentQuantity = isset($cart[$key]['quantity']) && is_numeric($cart[$key]['quantity']) ? (int) $cart[$key]['quantity'] : 0;
        $cart[$key]['quantity'] = max(1, $currentQuantity + 1);
    } else {
        $cart[$key] = [
            'menu_id' => $menuId,
            'name' => (string) $menuItem['name'],
            'price' => (float) $menuItem['price'],
            'image_url' => (string) $menuItem['image_url'],
            'quantity' => 1,
        ];
    }

    $_SESSION['cart'] = $cart;

    $cartCount = get_cart_quantity_total();

    return [
        'success' => true,
        'quantity' => $cartCount,
        'cart_count' => $cartCount,
        'message' => 'Item added to cart',
    ];
}

function update_item_in_cart(int $menuId, int $delta): array
{
    $cart = get_session_cart();
    $key = (string) $menuId;

    if (! isset($cart[$key])) {
        return ['success' => false, 'message' => 'Item not found in cart.'];
    }

    $currentQuantity = isset($cart[$key]['quantity']) && is_numeric($cart[$key]['quantity']) ? (int) $cart[$key]['quantity'] : 0;
    $newQuantity = $currentQuantity + $delta;

    if ($newQuantity <= 0) {
        unset($cart[$key]);
        $_SESSION['cart'] = $cart;
        return ['success' => true, 'removed' => true, 'message' => 'Item removed from cart.'];
    }

    $cart[$key]['quantity'] = $newQuantity;
    $_SESSION['cart'] = $cart;

    return ['success' => true, 'removed' => false, 'message' => 'Cart updated.'];
}

function remove_item_from_cart(int $menuId): array
{
    $cart = get_session_cart();
    $key = (string) $menuId;

    if (! isset($cart[$key])) {
        return ['success' => false, 'message' => 'Item not found in cart.'];
    }

    unset($cart[$key]);
    $_SESSION['cart'] = $cart;

    return ['success' => true, 'message' => 'Item removed from cart.'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if (! verify_customer_csrf_token(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
        exit;
    }

    if ($action === 'add') {
        $menuId = isset($_POST['menu_id'])
            ? filter_var($_POST['menu_id'], FILTER_VALIDATE_INT)
            : (isset($_GET['menu_id']) ? filter_var($_GET['menu_id'], FILTER_VALIDATE_INT) : false);

        if ($menuId === false || $menuId <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid menu item.']);
            exit;
        }

        $result = add_item_to_cart($menuId);
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }

    if ($action === 'update') {
        $menuId = isset($_POST['menu_id']) ? filter_var($_POST['menu_id'], FILTER_VALIDATE_INT) : false;
        $delta = isset($_POST['delta']) ? filter_var($_POST['delta'], FILTER_VALIDATE_INT) : false;

        if ($menuId === false || $menuId <= 0 || $delta === false) {
            header('Location: cart.php');
            exit;
        }

        update_item_in_cart($menuId, $delta);
        header('Location: cart.php');
        exit;
    }

    if ($action === 'remove') {
        $menuId = isset($_POST['menu_id']) ? filter_var($_POST['menu_id'], FILTER_VALIDATE_INT) : false;
        if ($menuId === false || $menuId <= 0) {
            header('Location: cart.php');
            exit;
        }

        remove_item_from_cart($menuId);
        header('Location: cart.php');
        exit;
    }
}

$cartData = rebuild_cart_from_session();
$cartItems = $cartData['items'];
$cartTotal = $cartData['total'];
$emptyCart = empty($cartItems);
$cartQuantity = get_cart_quantity_total();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Cart | Fast Food</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        :root {
            --bg: #0b0b0b;
            --panel: #111111;
            --panel-soft: #171717;
            --gold: #ffc107;
            --gold-soft: #ffd66b;
            --text: #f4f4f4;
            --muted: #bdbdbd;
            --line: rgba(255,255,255,0.09);
            --danger: #ff5c5c;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: linear-gradient(180deg, #050505 0%, #0d0d0d 100%);
            color: var(--text);
            font-family: Arial, sans-serif;
        }
        .cart-shell {
            max-width: 1200px;
            margin: 0 auto;
            padding: 54px 20px 80px;
        }
        .cart-header {
            margin-bottom: 26px;
        }
        .cart-header h1 {
            margin: 0 0 8px;
            font-size: clamp(2.1rem, 4vw, 3rem);
            color: #fff;
        }
        .cart-header p {
            margin: 0;
            color: var(--muted);
        }
        .cart-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }
        .cart-panel, .summary-panel {
            background: linear-gradient(180deg, #151515, #0f0f0f);
            border: 1px solid var(--line);
            border-radius: 22px;
            box-shadow: 0 18px 38px rgba(0,0,0,0.28);
        }
        .cart-panel {
            overflow: hidden;
        }
        .cart-table {
            width: 100%;
            border-collapse: collapse;
        }
        .cart-table th,
        .cart-table td {
            padding: 18px 16px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: middle;
        }
        .cart-table th {
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--gold-soft);
            background: rgba(255,255,255,0.02);
        }
        .cart-product {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .cart-product img {
            width: 72px;
            height: 72px;
            object-fit: cover;
            border-radius: 14px;
            border: 1px solid var(--line);
        }
        .cart-product-name {
            font-weight: 700;
            color: #fff;
        }
        .qty-box {
            display: inline-flex;
            align-items: center;
            border: 1px solid rgba(255,255,255,0.09);
            border-radius: 999px;
            overflow: hidden;
            background: rgba(255,255,255,0.02);
        }
        .qty-box button {
            width: 34px;
            height: 34px;
            border: 0;
            background: rgba(255,255,255,0.04);
            color: var(--text);
            font-size: 1.2rem;
            cursor: pointer;
        }
        .qty-box span {
            width: 42px;
            text-align: center;
            font-weight: 700;
            color: var(--gold-soft);
        }
        .remove-btn, .checkout-btn, .browse-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 999px;
            cursor: pointer;
            text-decoration: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .remove-btn {
            background: rgba(255,92,92,0.12);
            color: #ffd2d2;
            padding: 0.6rem 0.9rem;
        }
        .remove-btn:hover, .checkout-btn:hover, .browse-btn:hover {
            transform: translateY(-2px);
        }
        .summary-panel {
            padding: 22px;
            height: fit-content;
        }
        .summary-panel h2 {
            margin-top: 0;
            font-size: 1.4rem;
            color: #fff;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 12px 0;
            border-bottom: 1px solid var(--line);
            color: var(--muted);
        }
        .summary-row.total {
            color: #fff;
            font-size: 1.1rem;
            font-weight: 800;
            border-bottom: 0;
            padding-top: 18px;
        }
        .checkout-btn {
            width: 100%;
            background: linear-gradient(135deg, var(--gold-soft), var(--gold));
            color: #111;
            font-weight: 800;
            padding: 0.95rem 1.15rem;
            margin-top: 18px;
            box-shadow: 0 10px 20px rgba(255,193,7,0.18);
        }
        .browse-btn {
            background: rgba(255,255,255,0.04);
            color: #fff;
            border: 1px solid var(--line);
            padding: 0.85rem 1.2rem;
            margin-top: 18px;
        }
        .empty-state {
            padding: 42px 18px;
            text-align: center;
            color: var(--muted);
        }
        .empty-state h2 {
            color: #fff;
            margin-bottom: 12px;
        }
        @media (max-width: 900px) {
            .cart-layout { grid-template-columns: 1fr; }
        }
        @media (max-width: 640px) {
            .cart-table th:nth-child(2),
            .cart-table td:nth-child(2),
            .cart-table th:nth-child(4),
            .cart-table td:nth-child(4) {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="cart-shell">
        <div class="cart-header">
            <h1>Cart</h1>
            <p><?= $emptyCart ? 'Your cart is empty.' : 'Review your items and continue to checkout.'; ?></p>
        </div>

        <?php if ($emptyCart): ?>
            <div class="cart-panel empty-state">
                <h2>Your Cart is Empty</h2>
                <p>Browse our menu and add your favorite food.</p>
                <a href="index.php#Menu" class="browse-btn">Browse Menu</a>
            </div>
        <?php else: ?>
            <div class="cart-layout">
                <div class="cart-panel">
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>Food Item</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Subtotal</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cartItems as $item): ?>
                                <?php
                                $menuId = (int) $item['menu_id'];
                                $itemName = htmlspecialchars((string) $item['name'], ENT_QUOTES, 'UTF-8');
                                $itemImage = htmlspecialchars((string) $item['image_url'], ENT_QUOTES, 'UTF-8');
                                $itemPrice = (float) $item['price'];
                                $itemQuantity = (int) $item['quantity'];
                                $itemSubtotal = (float) $item['subtotal'];
                                ?>
                                <tr>
                                    <td>
                                        <div class="cart-product">
                                            <img src="<?= $itemImage; ?>" alt="<?= $itemName; ?>">
                                            <div class="cart-product-name"><?= $itemName; ?></div>
                                        </div>
                                    </td>
                                    <td>$<?= number_format($itemPrice, 2); ?></td>
                                    <td>
                                        <div class="qty-box">
                                            <form method="post" action="cart.php" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(customer_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="action" value="update">
                                                <input type="hidden" name="menu_id" value="<?= htmlspecialchars((string) $menuId, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="delta" value="-1">
                                                <button type="submit" aria-label="Decrease quantity">−</button>
                                            </form>
                                            <span><?= htmlspecialchars((string) $itemQuantity, ENT_QUOTES, 'UTF-8'); ?></span>
                                            <form method="post" action="cart.php" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(customer_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="action" value="update">
                                                <input type="hidden" name="menu_id" value="<?= htmlspecialchars((string) $menuId, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="delta" value="1">
                                                <button type="submit" aria-label="Increase quantity">+</button>
                                            </form>
                                        </div>
                                    </td>
                                    <td>$<?= number_format($itemSubtotal, 2); ?></td>
                                    <td>
                                        <form method="post" action="cart.php">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(customer_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="menu_id" value="<?= htmlspecialchars((string) $menuId, ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="submit" class="remove-btn">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <aside class="summary-panel">
                    <h2>Order Summary</h2>
                    <div class="summary-row">
                        <span>Items</span>
                        <span><?= htmlspecialchars((string) $cartQuantity, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>Total</span>
                        <span>$<?= number_format($cartTotal, 2); ?></span>
                    </div>
                    <a href="checkout.php" class="checkout-btn">Proceed to Checkout</a>
                </aside>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
