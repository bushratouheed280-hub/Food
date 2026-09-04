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

function format_member_date(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return 'N/A';
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);

    if ($date === false) {
        return 'N/A';
    }

    return $date->format('F j, Y');
}

$customerId = (int) $_SESSION['customer_id'];
$message = '';
$messageType = 'info';
$editing = isset($_GET['edit']) && $_GET['edit'] === '1';

try {
    $connection = get_db_connection();
} catch (RuntimeException $exception) {
    http_response_code(500);
    exit('Database is currently unavailable. Please try again later.');
}

$stmt = $connection->prepare('SELECT customer_id, full_name, email, phone, address, created_at FROM customers WHERE customer_id = ? LIMIT 1');
if ($stmt === false) {
    $connection->close();
    http_response_code(500);
    exit('Database is currently unavailable. Please try again later.');
}

$stmt->bind_param('i', $customerId);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();
$stmt->close();

if (! $customer) {
    $connection->close();
    session_destroy();
    header('Location: ../auth/login.php');
    exit;
}

$customerName = (string) ($customer['full_name'] ?? '');
$customerEmail = (string) ($customer['email'] ?? '');
$customerPhone = (string) ($customer['phone'] ?? '');
$customerAddress = (string) ($customer['address'] ?? '');
$createdAt = (string) ($customer['created_at'] ?? '');

$formFullName = $customerName;
$formEmail = $customerEmail;
$formPhone = $customerPhone;
$formAddress = $customerAddress;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $editing = true;

    if (! verify_customer_csrf_token(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null)) {
        http_response_code(403);
        exit('Invalid security token. Please refresh the page and try again.');
    }

    $formFullName = trim((string) ($_POST['full_name'] ?? ''));
    $formEmail = strtolower(trim((string) ($_POST['email'] ?? '')));
    $formPhone = trim((string) ($_POST['phone'] ?? ''));
    $formAddress = trim((string) ($_POST['address'] ?? ''));

    if ($formFullName === '') {
        $message = 'Please enter a valid name.';
        $messageType = 'error';
    } elseif ($formEmail === '' || ! filter_var($formEmail, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'error';
    } else {
        $duplicateStmt = $connection->prepare('SELECT customer_id FROM customers WHERE email = ? AND customer_id != ? LIMIT 1');
        if ($duplicateStmt === false) {
            $message = 'Unable to update your profile. Please try again.';
            $messageType = 'error';
        } else {
            $duplicateStmt->bind_param('si', $formEmail, $customerId);
            $duplicateStmt->execute();
            $duplicateResult = $duplicateStmt->get_result();
            $duplicateCustomer = $duplicateResult->fetch_assoc();
            $duplicateStmt->close();

            if ($duplicateCustomer) {
                $message = 'This email address is already in use.';
                $messageType = 'error';
            } else {
                $normalizedPhone = $formPhone !== '' ? $formPhone : null;
                $normalizedAddress = $formAddress !== '' ? $formAddress : null;

                $updateStmt = $connection->prepare('UPDATE customers SET full_name = ?, email = ?, phone = ?, address = ?, updated_at = CURRENT_TIMESTAMP WHERE customer_id = ?');
                if ($updateStmt === false) {
                    $message = 'Unable to update your profile. Please try again.';
                    $messageType = 'error';
                } else {
                    $updateStmt->bind_param('ssssi', $formFullName, $formEmail, $normalizedPhone, $normalizedAddress, $customerId);
                    if ($updateStmt->execute()) {
                        $message = 'Profile updated successfully.';
                        $messageType = 'success';
                        $customerName = $formFullName;
                        $customerEmail = $formEmail;
                        $customerPhone = $formPhone;
                        $customerAddress = $formAddress;
                        $formFullName = $customerName;
                        $formEmail = $customerEmail;
                        $formPhone = $customerPhone;
                        $formAddress = $customerAddress;
                        $editing = false;
                    } else {
                        $message = 'Unable to update your profile. Please try again.';
                        $messageType = 'error';
                    }
                    $updateStmt->close();
                }
            }
        }
    }
}

$connection->close();

$displayName = safe_text($customerName);
$displayEmail = safe_text($customerEmail);
$displayPhone = $customerPhone !== '' ? safe_text($customerPhone) : 'Not provided';
$displayAddress = $customerAddress !== '' ? nl2br(safe_text($customerAddress), false) : 'Not provided';
$memberSince = format_member_date($createdAt);

$formFullNameValue = safe_text($formFullName);
$formEmailValue = safe_text($formEmail);
$formPhoneValue = safe_text($formPhone);
$formAddressValue = safe_text($formAddress);

if ($message !== '') {
    $alertClass = $messageType === 'error' ? 'alert-error' : 'alert-success';
    $alertHtml = '<div class="alert ' . $alertClass . '">' . safe_text($message) . '</div>';
} else {
    $alertHtml = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Fast Food</title>
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

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--gold-soft);
            font-weight: 700;
            margin-bottom: 18px;
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

        .panel-badge {
            background: rgba(255, 193, 7, 0.12);
            border: 1px solid var(--border);
            color: var(--gold-soft);
            padding: 7px 12px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .profile-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr);
            gap: 22px;
        }

        .profile-summary {
            display: flex;
            flex-direction: column;
            gap: 12px;
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

        .primary-btn,
        .secondary-btn,
        .submit-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 12px;
            padding: 12px 18px;
            font-weight: 800;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: none;
            cursor: pointer;
            text-decoration: none;
        }

        .primary-btn,
        .submit-btn {
            background: linear-gradient(135deg, var(--gold), var(--gold-soft));
            color: #111;
            box-shadow: 0 12px 24px rgba(255, 193, 7, 0.18);
        }

        .secondary-btn {
            background: transparent;
            color: var(--text);
            border: 1px solid var(--border);
        }

        .primary-btn:hover,
        .secondary-btn:hover,
        .submit-btn:hover {
            transform: translateY(-1px);
        }

        .action-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 12px;
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

        .form-card {
            background: rgba(255,255,255,0.01);
            border: 1px solid rgba(255, 193, 7, 0.12);
            border-radius: 16px;
            padding: 18px;
        }

        .form-grid {
            display: grid;
            gap: 18px;
        }

        .field-group {
            display: grid;
            gap: 8px;
        }

        .field-group label {
            color: var(--gold-soft);
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .field-group input,
        .field-group textarea {
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255, 193, 7, 0.18);
            border-radius: 12px;
            color: var(--text);
            padding: 12px 14px;
            width: 100%;
            resize: vertical;
        }

        .field-group input::placeholder,
        .field-group textarea::placeholder {
            color: #8d8d8d;
        }

        .field-group textarea {
            min-height: 120px;
        }

        .field-group input:focus,
        .field-group textarea:focus {
            outline: none;
            border-color: rgba(255, 193, 7, 0.55);
            box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.1);
        }

        @media (max-width: 1024px) {
            .profile-layout {
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
                <a class="nav-link" href="dashboard.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
                <a class="nav-link active" href="profile.php"><i class="fa-solid fa-user"></i> My Profile</a>
                <a class="nav-link" href="#"><i class="fa-solid fa-box-open"></i> My Orders</a>
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
                    <h1 class="welcome-title">My Profile</h1>
                    <p class="welcome-email"><?php echo $displayEmail; ?></p>
                </div>

                <div class="topbar-actions">
                    <span class="status-pill"><span class="status-dot"></span> Active customer</span>
                </div>
            </header>

            <section class="content-area">
                <a class="back-link" href="dashboard.php"><i class="fa-solid fa-arrow-left"></i> Back / Dashboard</a>

                <?php echo $alertHtml; ?>

                <?php if (! $editing): ?>
                    <div class="panel-card">
                        <div class="panel-header">
                            <h2 class="panel-title">Profile Summary</h2>
                            <span class="panel-badge">Member</span>
                        </div>

                        <div class="profile-layout">
                            <div class="profile-summary">
                                <div class="profile-row">
                                    <span class="profile-label">Full Name</span>
                                    <span class="profile-value"><?php echo $displayName; ?></span>
                                </div>

                                <div class="profile-row">
                                    <span class="profile-label">Email</span>
                                    <span class="profile-value"><?php echo $displayEmail; ?></span>
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

                            <div class="profile-summary">
                                <div class="profile-row">
                                    <span class="profile-label">Account Created Date</span>
                                    <span class="profile-value"><?php echo $memberSince; ?></span>
                                </div>

                                <div class="action-row">
                                    <a class="primary-btn" href="profile.php?edit=1"><i class="fa-solid fa-pen-to-square"></i> Edit Profile</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="panel-card">
                        <div class="panel-header">
                            <h2 class="panel-title">Edit Profile</h2>
                            <span class="panel-badge">Update</span>
                        </div>

                        <form method="post" action="profile.php" class="form-card">
                            <input type="hidden" name="csrf_token" value="<?php echo safe_text(customer_csrf_token()); ?>">
                            <div class="form-grid">
                                <div class="field-group">
                                    <label for="full_name">Full Name</label>
                                    <input id="full_name" name="full_name" type="text" value="<?php echo $formFullNameValue; ?>" placeholder="Enter your full name" required>
                                </div>

                                <div class="field-group">
                                    <label for="email">Email</label>
                                    <input id="email" name="email" type="email" value="<?php echo $formEmailValue; ?>" placeholder="Enter your email" required>
                                </div>

                                <div class="field-group">
                                    <label for="phone">Phone</label>
                                    <input id="phone" name="phone" type="tel" value="<?php echo $formPhoneValue; ?>" placeholder="Enter your phone number">
                                </div>

                                <div class="field-group">
                                    <label for="address">Address</label>
                                    <textarea id="address" name="address" placeholder="Enter your address"><?php echo $formAddressValue; ?></textarea>
                                </div>

                                <div class="action-row">
                                    <button type="submit" class="submit-btn"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                                    <a href="profile.php" class="secondary-btn"><i class="fa-solid fa-xmark"></i> Cancel</a>
                                </div>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</body>
</html>
