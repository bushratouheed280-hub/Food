<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    require_once __DIR__ . '/config/security.php';
    start_secure_session('fastfood_customer');
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/security.php';

if (! isset($_SESSION['customer_id'])) {
    header('Location: auth/login.php');
    exit;
}

function safe_text(?string $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function get_session_cart(): array
{
    if (! isset($_SESSION['cart']) || ! is_array($_SESSION['cart'])) {
        return [];
    }

    return $_SESSION['cart'];
}

function normalize_cart(): array
{
    $cart = get_session_cart();
    $normalized = [];

    foreach ($cart as $entry) {
        if (! is_array($entry)) {
            continue;
        }

        $menuId = isset($entry['menu_id']) ? filter_var($entry['menu_id'], FILTER_VALIDATE_INT) : false;
        $quantity = isset($entry['quantity']) ? filter_var($entry['quantity'], FILTER_VALIDATE_INT) : false;

        if ($menuId === false || $menuId <= 0) {
            continue;
        }

        if ($quantity === false || $quantity <= 0) {
            $quantity = 1;
        }

        $normalized[] = [
            'menu_id' => (int) $menuId,
            'quantity' => (int) $quantity,
        ];
    }

    return $normalized;
}

function get_customer_details(mysqli $connection, int $customerId): ?array
{
    $statement = $connection->prepare('SELECT full_name, email, phone, address FROM customers WHERE customer_id = ? LIMIT 1');
    if (! $statement) {
        return null;
    }

    $statement->bind_param('i', $customerId);
    if (! $statement->execute()) {
        $statement->close();
        return null;
    }

    $result = $statement->get_result();
    $customer = $result ? $result->fetch_assoc() : null;
    $statement->close();

    return $customer;
}

function get_menu_item(mysqli $connection, int $menuId): ?array
{
    $statement = $connection->prepare('SELECT menu_id, name, price, image_url, is_available FROM menu_items WHERE menu_id = ? AND is_available = 1 LIMIT 1');
    if (! $statement) {
        return null;
    }

    $statement->bind_param('i', $menuId);
    if (! $statement->execute()) {
        $statement->close();
        return null;
    }

    $result = $statement->get_result();
    $item = $result ? $result->fetch_assoc() : null;
    $statement->close();

    return $item;
}

function valid_coupon_discount(string $couponCode): float
{
    $normalized = strtoupper(trim($couponCode));
    $codes = [
        'SAVE10' => 0.10,
        'WELCOME5' => 0.05,
    ];

    return $codes[$normalized] ?? 0.0;
}

$customerId = (int) $_SESSION['customer_id'];
$checkoutError = '';
$successOrderNumber = '';
$successOrderId = 0;
$hadPlaceOrder = false;

try {
    $connection = get_db_connection();
} catch (RuntimeException $exception) {
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Checkout Error</title></head><body style="font-family:Arial,sans-serif;background:#111;color:#fff;display:grid;place-items:center;min-height:100vh;">';
    echo '<div style="max-width:560px;padding:40px;border:1px solid rgba(255,255,255,0.1);border-radius:20px;background:#171717;text-align:center;">';
    echo '<h1 style="color:#ffc107;">Checkout Unavailable</h1>';
    echo '<p>We could not connect to the database right now. Please try again.</p>';
    echo '</div></body></html>';
    exit;
}

$customer = get_customer_details($connection, $customerId);
if (! $customer) {
    $connection->close();
    session_destroy();
    header('Location: auth/login.php');
    exit;
}

$customerName = (string) ($customer['full_name'] ?? '');
$customerEmail = (string) ($customer['email'] ?? '');
$customerPhone = (string) ($customer['phone'] ?? '');
$customerAddress = (string) ($customer['address'] ?? '');
$cartItems = normalize_cart();
$checkoutRows = [];
$subtotal = 0.0;
$deliveryFee = 60.00;
$discountPercent = 0.0;
$discountAmount = 0.0;
$grandTotal = 0.0;

if (! empty($cartItems)) {
    foreach ($cartItems as $cartItem) {
        $menuId = (int) $cartItem['menu_id'];
        $quantity = (int) $cartItem['quantity'];

        $menuItem = get_menu_item($connection, $menuId);
        if (! $menuItem) {
            $checkoutError = 'One or more menu items are no longer available. Please review your cart.';
            break;
        }

        $unitPrice = (float) $menuItem['price'];
        $itemSubtotal = $unitPrice * $quantity;
        $subtotal += $itemSubtotal;

        $checkoutRows[] = [
            'menu_id' => $menuId,
            'name' => (string) $menuItem['name'],
            'image_url' => (string) $menuItem['image_url'],
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'item_subtotal' => $itemSubtotal,
        ];
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['place_order'])) {
    $hadPlaceOrder = true;

    if (! verify_customer_csrf_token(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null)) {
        http_response_code(403);
        exit('Invalid security token. Please refresh the page and try again.');
    }

    $deliveryName = trim((string) ($_POST['delivery_name'] ?? $customerName));
    $deliveryPhone = trim((string) ($_POST['delivery_phone'] ?? $customerPhone));
    $deliveryAddress = trim((string) ($_POST['delivery_address'] ?? $customerAddress));
    $deliveryInstructions = trim((string) ($_POST['delivery_instructions'] ?? ''));
    $paymentMethod = trim((string) ($_POST['payment_method'] ?? 'Cash on Delivery'));
    $couponCode = trim((string) ($_POST['coupon_code'] ?? ''));

    if ($paymentMethod === 'Card Payment') {
        $checkoutError = 'Card Payment is not available yet. Please choose Cash on Delivery.';
    }

    if ($deliveryName === '' || $deliveryPhone === '' || $deliveryAddress === '') {
        $checkoutError = 'Please fill in your full name, phone number, and delivery address.';
    }

    if ($checkoutError === '' && ! empty($couponCode)) {
        $discountPercent = valid_coupon_discount($couponCode);
        if ($discountPercent <= 0) {
            $checkoutError = 'The coupon code is invalid or unavailable.';
        }
    }

    if ($checkoutError === '' && empty($checkoutRows)) {
        $checkoutError = 'Your cart is empty. Please add an item before checkout.';
    }

    if ($checkoutError === '') {
        $discountAmount = round($subtotal * $discountPercent, 2);
        $grandTotal = round(($subtotal + $deliveryFee - $discountAmount), 2);

        $orderNumber = 'ORD-' . date('Ymd') . '-' . str_pad((string) $customerId, 4, '0', STR_PAD_LEFT) . '-' . str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);

        try {
            $connection->begin_transaction();

            $orderStatement = $connection->prepare('INSERT INTO orders (customer_id, order_number, subtotal, discount_amount, delivery_charges, total_amount, payment_method, delivery_name, delivery_phone, delivery_address, delivery_instructions, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "Pending")');
            if (! $orderStatement) {
                throw new RuntimeException('Unable to create order.');
            }

            $subtotalDecimal = number_format($subtotal, 2, '.', '');
            $discountDecimal = number_format($discountAmount, 2, '.', '');
            $deliveryDecimal = number_format($deliveryFee, 2, '.', '');
            $grandTotalDecimal = number_format($grandTotal, 2, '.', '');

            $orderStatement->bind_param(
                'issssssssss',
                $customerId,
                $orderNumber,
                $subtotalDecimal,
                $discountDecimal,
                $deliveryDecimal,
                $grandTotalDecimal,
                $paymentMethod,
                $deliveryName,
                $deliveryPhone,
                $deliveryAddress,
                $deliveryInstructions
            );

            if (! $orderStatement->execute()) {
                throw new RuntimeException('Unable to save order.');
            }

            $orderId = (int) $connection->insert_id;
            $orderStatement->close();

            $itemStatement = $connection->prepare('INSERT INTO order_items (order_id, food_id, item_name, quantity, unit_price, item_total) VALUES (?, ?, ?, ?, ?, ?)');
            if (! $itemStatement) {
                throw new RuntimeException('Unable to save order items.');
            }

            foreach ($checkoutRows as $checkoutRow) {
                $foodId = (int) $checkoutRow['menu_id'];
                $itemName = (string) $checkoutRow['name'];
                $quantity = (int) $checkoutRow['quantity'];
                $unitPrice = number_format((float) $checkoutRow['unit_price'], 2, '.', '');
                $itemTotal = number_format((float) $checkoutRow['item_subtotal'], 2, '.', '');

                $itemStatement->bind_param('iisiss', $orderId, $foodId, $itemName, $quantity, $unitPrice, $itemTotal);
                if (! $itemStatement->execute()) {
                    throw new RuntimeException('Unable to save order item.');
                }
            }

            $itemStatement->close();
            $connection->commit();

            unset($_SESSION['cart']);
            $successOrderNumber = $orderNumber;
            $successOrderId = $orderId;
            $checkoutRows = [];
            $subtotal = 0.0;
            $deliveryFee = 0.0;
            $discountAmount = 0.0;
            $grandTotal = 0.0;
            $checkoutError = '';
        } catch (Throwable $exception) {
            $connection->rollback();
            $checkoutError = 'Order could not be placed. No incomplete order was saved.';
        }
    }
}

if ($checkoutError === '' && $hadPlaceOrder && $successOrderNumber !== '') {
    $connection->close();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Order Placed | Fast Food</title>
        <style>
            body { margin:0; min-height:100vh; display:grid; place-items:center; background:#0b0b0b; color:#fff; font-family:Arial,sans-serif; }
            .success-box { width:min(700px,90vw); padding:42px 28px; background:linear-gradient(180deg,#141414,#101010); border:1px solid rgba(255,193,7,0.3); border-radius:22px; box-shadow:0 20px 45px rgba(0,0,0,0.3); text-align:center; }
            h1 { color:#ffd76a; margin-bottom:16px; }
            p { color:#d7d7d7; line-height:1.8; }
            .actions { display:flex; justify-content:center; gap:14px; margin-top:26px; flex-wrap:wrap; }
            a { text-decoration:none; display:inline-flex; align-items:center; justify-content:center; border-radius:999px; padding:0.9rem 1.2rem; font-weight:700; }
            .primary { background:linear-gradient(135deg,#ffd76a,#ffc107); color:#111; }
            .secondary { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.1); color:#fff; }
        </style>
    </head>
    <body>
        <div class="success-box">
            <h1>Order Placed Successfully!</h1>
            <p>Order Number: <strong>#<?= safe_text($successOrderNumber); ?></strong></p>
            <p>Thank you for your order. Your order is now pending confirmation.</p>
            <div class="actions">
                <a href="customer/my-orders.php" class="primary">View My Orders</a>
                <a href="index.php#Menu" class="secondary">Continue Shopping</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$amountDue = $subtotal + $deliveryFee - $discountAmount;
if ($amountDue < 0) {
    $amountDue = 0.0;
}

$defaultName = safe_text($customerName);
$defaultPhone = safe_text($customerPhone);
$defaultAddress = safe_text($customerAddress);
$defaultEmail = safe_text($customerEmail);
$defaultInstructions = '';

if ($checkoutError !== '') {
    $defaultInstructions = safe_text((string) ($_POST['delivery_instructions'] ?? ''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | Fast Food</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin:0;
            font-family:Arial,sans-serif;
            background:#0b0b0b;
            color:#fff;
        }
        .checkout-shell {
            max-width:1200px;
            margin:0 auto;
            padding:46px 20px 80px;
        }
        .checkout-header { margin-bottom:24px; }
        .checkout-header h1 { margin:0; font-size:clamp(2rem,4vw,3rem); }
        .checkout-grid { display:grid; grid-template-columns: 1.75fr 1fr; gap:24px; }
        .panel { background:linear-gradient(180deg,#141414,#0f0f0f); border:1px solid rgba(255,255,255,0.08); border-radius:20px; padding:22px; }
        .row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        label { display:block; font-size:0.95rem; color:#d7d7d7; margin-bottom:8px; }
        input, textarea, select {
            width:100%;
            border-radius:12px;
            border:1px solid rgba(255,255,255,0.1);
            background:rgba(255,255,255,0.02);
            color:#fff;
            padding:14px 15px;
            margin-bottom:18px;
            font:inherit;
        }
        textarea { min-height:100px; resize:vertical; }
        .radio-group { display:flex; flex-wrap:wrap; gap:18px; margin-bottom:18px; }
        .radio-option { display:flex; align-items:center; gap:8px; color:#d7d7d7; }
        .summary-list { display:flex; flex-direction:column; gap:16px; }
        .checkout-item { display:grid; grid-template-columns:70px 1fr auto; gap:12px; align-items:center; padding-bottom:14px; border-bottom:1px solid rgba(255,255,255,0.08); }
        .checkout-item img { width:70px; height:70px; object-fit:cover; border-radius:12px; border:1px solid rgba(255,255,255,0.08); }
        .checkout-item h4 { margin:0 0 4px; font-size:1rem; }
        .meta { color:#b5b5b5; font-size:0.9rem; }
        .summary-row { display:flex; justify-content:space-between; padding:8px 0; color:#d7d7d7; }
        .summary-row.total { font-size:1.15rem; font-weight:700; color:#fff; padding-top:12px; border-top:1px solid rgba(255,255,255,0.08); }
        .place-order-btn { display:inline-flex; justify-content:center; width:100%; border:0; background:linear-gradient(135deg,#ffd76a,#ffc107); color:#111; font-weight:800; border-radius:999px; padding:0.95rem 1.1rem; cursor:pointer; margin-top:20px; }
        .muted { color:#b5b5b5; }
        .error-box { margin-bottom:20px; background:rgba(255,107,107,0.08); border:1px solid rgba(255,107,107,0.3); color:#ffdede; border-radius:12px; padding:12px 14px; }
        .note { font-size:0.85rem; color:#d7d7d7; margin-top:8px; }
        @media (max-width: 900px) { .checkout-grid { grid-template-columns:1fr; } }
        @media (max-width: 600px) { .row { grid-template-columns:1fr; } }
    </style>
</head>
<body>
    <div class="checkout-shell">
        <div class="checkout-header">
            <h1>Checkout</h1>
        </div>

        <?php if ($checkoutError !== ''): ?>
            <div class="error-box"><?= safe_text($checkoutError); ?></div>
        <?php endif; ?>

        <div class="checkout-grid">
            <form method="post" action="checkout.php">
                <input type="hidden" name="csrf_token" value="<?= safe_text(customer_csrf_token()); ?>">
                <div class="panel">
                    <h2>Customer Information</h2>
                    <div class="row">
                        <div>
                            <label for="delivery_name">Full Name</label>
                            <input id="delivery_name" name="delivery_name" type="text" value="<?= $defaultName; ?>" required>
                        </div>
                        <div>
                            <label for="delivery_phone">Phone</label>
                            <input id="delivery_phone" name="delivery_phone" type="tel" value="<?= $defaultPhone; ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div>
                            <label for="delivery_email">Email</label>
                            <input id="delivery_email" type="email" value="<?= $defaultEmail; ?>" readonly>
                        </div>
                        <div>
                            <label for="delivery_address">Address</label>
                            <input id="delivery_address" name="delivery_address" type="text" value="<?= $defaultAddress; ?>" required>
                        </div>
                    </div>

                    <div>
                        <label for="delivery_instructions">Delivery Instructions</label>
                        <textarea id="delivery_instructions" name="delivery_instructions" placeholder="Optional note for the rider"><?= $defaultInstructions; ?></textarea>
                    </div>

                    <div>
                        <label>Payment Method</label>
                        <div class="radio-group">
                            <label class="radio-option"><input type="radio" name="payment_method" value="Cash on Delivery" checked> Cash on Delivery</label>
                            <label class="radio-option"><input type="radio" name="payment_method" value="Card Payment"> Card Payment</label>
                        </div>
                        <div class="note">Card payment is not integrated yet. Cash on Delivery is active.</div>
                    </div>

                    <div>
                        <label for="coupon_code">Coupon Code</label>
                        <input id="coupon_code" name="coupon_code" type="text" placeholder="Optional coupon code">
                        <div class="note">Available demo codes: SAVE10, WELCOME5</div>
                    </div>
                </div>

                <div class="panel" style="margin-top:24px;">
                    <button class="place-order-btn" type="submit" name="place_order" value="1">Place Order</button>
                </div>
            </form>

            <aside class="panel">
                <h2>Order Summary</h2>
                <div class="summary-list">
                    <?php if (empty($checkoutRows)): ?>
                        <p class="muted">Your cart is empty. Add items before checkout.</p>
                    <?php else: ?>
                        <?php foreach ($checkoutRows as $checkoutRow): ?>
                            <?php
                            $itemName = safe_text((string) $checkoutRow['name']);
                            $imageUrl = safe_text((string) $checkoutRow['image_url']);
                            $unitPrice = (float) $checkoutRow['unit_price'];
                            $quantity = (int) $checkoutRow['quantity'];
                            $itemSubtotal = (float) $checkoutRow['item_subtotal'];
                            ?>
                            <div class="checkout-item">
                                <img src="<?= $imageUrl; ?>" alt="<?= $itemName; ?>">
                                <div>
                                    <h4><?= $itemName; ?></h4>
                                    <div class="meta">Qty: <?= (int) $quantity; ?></div>
                                </div>
                                <div>
                                    <div>$<?= number_format($unitPrice, 2); ?></div>
                                    <div class="meta">$<?= number_format($itemSubtotal, 2); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span>$<?= number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Delivery Fee</span>
                        <span>$<?= number_format($deliveryFee, 2); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Discount</span>
                        <span>-$<?= number_format($discountAmount, 2); ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>Grand Total</span>
                        <span>$<?= number_format($amountDue, 2); ?></span>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</body>
</html>
