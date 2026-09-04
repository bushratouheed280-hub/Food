<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';

function generate_admin_profile_csrf_token(): string
{
    if (empty($_SESSION['admin_profile_csrf_token'])) {
        $_SESSION['admin_profile_csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['admin_profile_csrf_token'];
}

function verify_admin_profile_csrf_token(string $token): bool
{
    $expected = $_SESSION['admin_profile_csrf_token'] ?? '';
    return is_string($expected) && hash_equals($expected, $token);
}

function safe_text(?string $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$adminId = (int) ($_SESSION['admin_id'] ?? 0);
$adminName = safe_text((string) ($_SESSION['admin_name'] ?? 'Administrator'));
$adminEmail = safe_text((string) ($_SESSION['admin_email'] ?? ''));

$profileMessage = '';
$profileMessageType = 'info';
$passwordMessage = '';
$passwordMessageType = 'info';
$profileFormName = '';
$profileFormEmail = '';
$profileCreatedAt = 'N/A';
$adminPhoneAvailable = false;

try {
    $connection = get_db_connection();
    $profileStmt = $connection->prepare('SELECT admin_id, full_name, email, password, created_at, updated_at FROM admins WHERE admin_id = ? LIMIT 1');
    if ($profileStmt === false) {
        throw new RuntimeException('Unable to load admin profile.');
    }

    $profileStmt->bind_param('i', $adminId);
    $profileStmt->execute();
    $profileResult = $profileStmt->get_result();
    $adminData = $profileResult->fetch_assoc();
    $profileStmt->close();

    if ($adminData === null) {
        $connection->close();
        session_destroy();
        header('Location: login.php');
        exit;
    }

    $profileFormName = (string) ($adminData['full_name'] ?? '');
    $profileFormEmail = (string) ($adminData['email'] ?? '');
    $profileCreatedAt = (string) ($adminData['created_at'] ?? '');

    $adminPhoneAvailable = false;
    $connection->close();
} catch (Throwable $exception) {
    $profileFormName = (string) ($_SESSION['admin_name'] ?? '');
    $profileFormEmail = (string) ($_SESSION['admin_email'] ?? '');
    $profileMessage = 'Unable to load your profile. Please try again later.';
    $profileMessageType = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['profile_submit'])) {
        if (! isset($_POST['csrf_token']) || ! verify_admin_profile_csrf_token((string) $_POST['csrf_token'])) {
            $profileMessage = 'Invalid security token. Please refresh the page and try again.';
            $profileMessageType = 'error';
        } else {
            $fullName = trim((string) ($_POST['full_name'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));

            if ($fullName === '') {
                $profileMessage = 'Please enter a valid name.';
                $profileMessageType = 'error';
            } elseif (strlen($fullName) > 100) {
                $profileMessage = 'Name must be 100 characters or fewer.';
                $profileMessageType = 'error';
            } elseif ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $profileMessage = 'Please enter a valid email address.';
                $profileMessageType = 'error';
            } else {
                try {
                    $connection = get_db_connection();
                    $duplicateStmt = $connection->prepare('SELECT admin_id FROM admins WHERE email = ? AND admin_id != ? LIMIT 1');
                    if ($duplicateStmt === false) {
                        throw new RuntimeException('Unable to validate email uniqueness.');
                    }

                    $duplicateStmt->bind_param('si', $email, $adminId);
                    $duplicateStmt->execute();
                    $duplicateResult = $duplicateStmt->get_result();
                    $duplicateAdmin = $duplicateResult->fetch_assoc();
                    $duplicateStmt->close();

                    if ($duplicateAdmin) {
                        $profileMessage = 'Email address is already in use.';
                        $profileMessageType = 'error';
                    } else {
                        $updateStmt = $connection->prepare('UPDATE admins SET full_name = ?, email = ?, updated_at = CURRENT_TIMESTAMP WHERE admin_id = ?');
                        if ($updateStmt === false) {
                            throw new RuntimeException('Unable to update profile.');
                        }

                        $updateStmt->bind_param('ssi', $fullName, $email, $adminId);
                        if (! $updateStmt->execute()) {
                            throw new RuntimeException('Unable to update profile.');
                        }

                        $updateStmt->close();
                        $_SESSION['admin_name'] = $fullName;
                        $_SESSION['admin_email'] = $email;
                        $profileFormName = $fullName;
                        $profileFormEmail = $email;
                        $profileMessage = 'Profile updated successfully.';
                        $profileMessageType = 'success';
                        $adminName = safe_text($fullName);
                        $adminEmail = safe_text($email);
                    }
                    $connection->close();
                } catch (Throwable $exception) {
                    $profileMessage = 'Unable to update profile. Please try again.';
                    $profileMessageType = 'error';
                }
            }
        }
    }

    if (isset($_POST['password_submit'])) {
        if (! isset($_POST['csrf_token']) || ! verify_admin_profile_csrf_token((string) $_POST['csrf_token'])) {
            $passwordMessage = 'Invalid security token. Please refresh the page and try again.';
            $passwordMessageType = 'error';
        } else {
            $currentPassword = (string) ($_POST['current_password'] ?? '');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

            if ($currentPassword === '') {
                $passwordMessage = 'Current password is required.';
                $passwordMessageType = 'error';
            } elseif ($newPassword === '') {
                $passwordMessage = 'New password is required.';
                $passwordMessageType = 'error';
            } elseif (strlen($newPassword) < 8) {
                $passwordMessage = 'Password must be at least 8 characters long.';
                $passwordMessageType = 'error';
            } elseif ($newPassword !== $confirmPassword) {
                $passwordMessage = 'New passwords do not match.';
                $passwordMessageType = 'error';
            } else {
                try {
                    $connection = get_db_connection();
                    $passwordStmt = $connection->prepare('SELECT password FROM admins WHERE admin_id = ? LIMIT 1');
                    if ($passwordStmt === false) {
                        throw new RuntimeException('Unable to verify current password.');
                    }

                    $passwordStmt->bind_param('i', $adminId);
                    $passwordStmt->execute();
                    $passwordResult = $passwordStmt->get_result();
                    $adminPasswordRow = $passwordResult->fetch_assoc();
                    $passwordStmt->close();

                    if ($adminPasswordRow === null || ! password_verify($currentPassword, (string) ($adminPasswordRow['password'] ?? ''))) {
                        $passwordMessage = 'Current password is incorrect.';
                        $passwordMessageType = 'error';
                    } else {
                        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                        $updatePasswordStmt = $connection->prepare('UPDATE admins SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE admin_id = ?');
                        if ($updatePasswordStmt === false) {
                            throw new RuntimeException('Unable to update password.');
                        }

                        $updatePasswordStmt->bind_param('si', $hashedPassword, $adminId);
                        if (! $updatePasswordStmt->execute()) {
                            throw new RuntimeException('Unable to update password.');
                        }

                        $updatePasswordStmt->close();
                        $passwordMessage = 'Password changed successfully.';
                        $passwordMessageType = 'success';
                    }

                    $connection->close();
                } catch (Throwable $exception) {
                    $passwordMessage = 'Unable to update password. Please try again.';
                    $passwordMessageType = 'error';
                }
            }
        }
    }
}

$profileAlert = $profileMessage !== '' ? '<div class="alert ' . ($profileMessageType === 'error' ? 'alert-error' : 'alert-success') . '">' . safe_text($profileMessage) . '</div>' : '';
$passwordAlert = $passwordMessage !== '' ? '<div class="alert ' . ($passwordMessageType === 'error' ? 'alert-error' : 'alert-success') . '">' . safe_text($passwordMessage) . '</div>' : '';

$createdDate = trim($profileCreatedAt) !== '' ? date('F j, Y', strtotime($profileCreatedAt)) : 'N/A';
$profileNameValue = safe_text($profileFormName);
$profileEmailValue = safe_text($profileFormEmail);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | Fast Food Admin</title>
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
            --shadow: rgba(0, 0, 0, 0.28);
            --success: #34d399;
            --danger: #f87171;
            --warning: #fbbf24;
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
        .profile-grid { display: grid; grid-template-columns: repeat(2, minmax(280px, 1fr)); gap: 18px; }
        .panel-card { background: linear-gradient(180deg, var(--panel), var(--panel-soft)); border: 1px solid var(--border); border-radius: 18px; box-shadow: 0 12px 30px var(--shadow); overflow: hidden; }
        .panel-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 18px 20px; border-bottom: 1px solid var(--border); }
        .panel-title { margin: 0; font-size: 1.08rem; }
        .profile-body { padding: 18px 20px 20px; }
        .info-list { display: grid; gap: 12px; margin-bottom: 18px; }
        .info-row { display: flex; justify-content: space-between; gap: 16px; padding-bottom: 10px; border-bottom: 1px solid rgba(212,175,55,0.12); }
        .info-label { color: var(--muted); }
        .info-value { font-weight: 700; color: var(--text); }
        .form-grid { display: grid; gap: 16px; }
        .field { display: grid; gap: 8px; }
        .field label { color: var(--gold-soft); text-transform: uppercase; letter-spacing: 0.08em; font-size: 0.7rem; font-weight: 700; }
        .field input { min-height: 42px; border-radius: 10px; border: 1px solid var(--border); background: rgba(255,255,255,0.02); color: var(--text); padding: 10px 12px; font: inherit; }
        .field input:focus { outline: none; border-color: var(--gold-soft); box-shadow: 0 0 0 3px rgba(212,175,55,0.12); }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 42px; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--border); background: rgba(212,175,55,0.08); color: var(--gold-soft); font-weight: 700; cursor: pointer; }
        .btn.primary { background: linear-gradient(135deg, var(--gold), var(--gold-soft)); color: #111; border-color: rgba(212,175,55,0.8); }
        .alert { border: 1px solid transparent; border-radius: 10px; padding: 12px 14px; margin-bottom: 18px; font-size: 0.95rem; }
        .alert-success { background: rgba(52, 211, 153, 0.08); border-color: rgba(52, 211, 153, 0.4); color: #d9ffef; }
        .alert-error { background: rgba(248, 113, 113, 0.08); border-color: rgba(248, 113, 113, 0.38); color: #ffe2e2; }
        .note { color: var(--muted); font-size: 0.88rem; }
        .menu-toggle { display: none; }
        @media (max-width: 920px) {
            .profile-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 820px) {
            .admin-layout { display: block; }
            .sidebar { position: fixed; left: 0; top: 0; transform: translateX(-100%); z-index: 20; height: 100vh; width: 260px; box-shadow: 0 18px 38px rgba(0,0,0,0.35); }
            .sidebar.open { transform: translateX(0); }
            .menu-toggle { display: inline-flex; width: 44px; height: 44px; border-radius: 10px; border: 1px solid var(--border); background: rgba(212,175,55,0.08); color: var(--gold-soft); align-items: center; justify-content: center; cursor: pointer; }
            .topbar { padding: 18px 20px; }
            .content { padding: 20px; }
            .info-row { flex-direction: column; }
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
            <a class="nav-link" href="reports.php"><i class="fa-solid fa-chart-column"></i> Reports</a>
            <a class="nav-link active" href="profile.php"><i class="fa-solid fa-user-gear"></i> Profile</a>
            <a class="nav-link logout-link" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-panel">
        <header class="topbar">
            <div style="display:flex; align-items:center; gap:12px;">
                <button class="menu-toggle" type="button" aria-label="Toggle sidebar" id="menuToggle"><i class="fa-solid fa-bars"></i></button>
                <h1 class="page-title">Profile</h1>
            </div>
            <div class="header-user">
                <div class="welcome-block">
                    <span class="welcome-label">Welcome</span>
                    <div class="welcome-name"><?php echo $adminName; ?></div>
                    <div class="welcome-email"><?php echo $adminEmail; ?></div>
                </div>
                <div class="avatar"><?php echo strtoupper(substr((string) ($_SESSION['admin_name'] ?? 'A'), 0, 1)); ?></div>
                <a class="logout-btn" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </header>

        <div class="content">
            <div class="profile-grid">
                <section class="panel-card">
                    <div class="panel-header">
                        <h2 class="panel-title">Profile Information</h2>
                    </div>
                    <div class="profile-body">
                        <?php echo $profileAlert; ?>
                        <div class="info-list">
                            <div class="info-row">
                                <span class="info-label">Name</span>
                                <span class="info-value"><?php echo safe_text($profileFormName); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Email</span>
                                <span class="info-value"><?php echo safe_text($profileFormEmail); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Admin ID</span>
                                <span class="info-value"><?php echo (int) $adminId; ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Phone</span>
                                <span class="info-value"><?php echo $adminPhoneAvailable ? 'Available in the current table' : 'Not available'; ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Created</span>
                                <span class="info-value"><?php echo safe_text($createdDate); ?></span>
                            </div>
                        </div>

                        <form method="POST" action="profile.php">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_admin_profile_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="profile_submit" value="1">
                            <div class="form-grid">
                                <div class="field">
                                    <label for="full_name">Name</label>
                                    <input id="full_name" name="full_name" type="text" value="<?php echo $profileNameValue; ?>" required>
                                </div>
                                <div class="field">
                                    <label for="email">Email</label>
                                    <input id="email" name="email" type="email" value="<?php echo $profileEmailValue; ?>" required>
                                </div>
                                <button class="btn primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="panel-card">
                    <div class="panel-header">
                        <h2 class="panel-title">Change Password</h2>
                    </div>
                    <div class="profile-body">
                        <?php echo $passwordAlert; ?>
                        <form method="POST" action="profile.php">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_admin_profile_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="password_submit" value="1">
                            <div class="form-grid">
                                <div class="field">
                                    <label for="current_password">Current Password</label>
                                    <input id="current_password" name="current_password" type="password" placeholder="Enter current password" required>
                                </div>
                                <div class="field">
                                    <label for="new_password">New Password</label>
                                    <input id="new_password" name="new_password" type="password" placeholder="Enter new password" required>
                                </div>
                                <div class="field">
                                    <label for="confirm_password">Confirm New Password</label>
                                    <input id="confirm_password" name="confirm_password" type="password" placeholder="Confirm new password" required>
                                </div>
                                <p class="note">Password must be at least 8 characters long.</p>
                                <button class="btn primary" type="submit"><i class="fa-solid fa-key"></i> Change Password</button>
                            </div>
                        </form>
                    </div>
                </section>
            </div>
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
