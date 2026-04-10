<?php
require_once __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';
$sessionUser = current_user();

function users_has_column(mysqli $mysqli, string $column): bool {
    if (!$res = $mysqli->query("SHOW COLUMNS FROM users LIKE '" . $mysqli->real_escape_string($column) . "'")) return false;
    $exists = (bool)$res->num_rows; $res->free(); return $exists;
}

$hasAddress = users_has_column($mysqli, 'address');
// Ensure avatar column exists
if (!users_has_column($mysqli, 'avatar')) { $mysqli->query("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL"); }

$success = '';$error = '';

// Load fresh user row from DB
$uid = (int)($sessionUser['id'] ?? 0);
$userRow = null;
if ($uid) {
    $sel = $hasAddress ? 'user_id, name, email, role, plate_no, reference_no, address, created_at' : 'user_id, name, email, role, plate_no, reference_no, created_at';
    if ($stmt = $mysqli->prepare("SELECT $sel FROM users WHERE user_id = ? LIMIT 1")) {
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        $userRow = $res->fetch_assoc();
        $stmt->close();
    }
}

if (!$userRow) {
    // If there's no DB row but a session user exists (for example the test 'Admin' login),
    // fall back to session values so the admin can still view and edit a profile-like page.
    if ($sessionUser) {
        $userRow = [
            'user_id' => (int)($sessionUser['id'] ?? 0),
            'name' => $sessionUser['name'] ?? 'User',
            'email' => $sessionUser['email'] ?? '',
            'role' => $sessionUser['role'] ?? '',
            'plate_no' => '',
            'reference_no' => '',
            'address' => '',
            'avatar' => '',
            'created_at' => date('Y-m-d'),
        ];
    } else {
        http_response_code(404);
        echo 'User not found';
        exit;
    }
}

// Handle update profile
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['do'] ?? '') === 'update_profile') {
    verify_csrf_or_die();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $plate = trim($_POST['plate_no'] ?? '');
    $ref = trim($_POST['reference_no'] ?? '');
    $address = $hasAddress ? trim($_POST['address'] ?? '') : '';
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide valid name and email.';
    } else {
        // Ensure email is unique except self
        if ($stmt = $mysqli->prepare('SELECT 1 FROM users WHERE email = ? AND user_id <> ? LIMIT 1')) {
            $stmt->bind_param('si', $email, $uid);
            $stmt->execute(); $stmt->store_result();
            if ($stmt->num_rows > 0) { $error = 'Email already in use by another account.'; }
            $stmt->close();
        }
        if ($error === '') {
            // Handle avatar upload if provided (no GD dependency)
            $avatarPath = $userRow['avatar'] ?? '';
            if (isset($_FILES['avatar']) && is_uploaded_file($_FILES['avatar']['tmp_name'])) {
                $dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'avatars';
                if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
                $tmp = $_FILES['avatar']['tmp_name'];
                $info = @getimagesize($tmp);
                $mime = $info['mime'] ?? '';
                $ext = ($mime === 'image/jpeg') ? '.jpg' : (($mime === 'image/png') ? '.png' : (($mime === 'image/gif') ? '.gif' : ''));
                if ($ext !== '') {
                    $target = $dir . DIRECTORY_SEPARATOR . $uid . $ext;
                    foreach (['.jpg','.png','.gif'] as $e) { $p = $dir . DIRECTORY_SEPARATOR . $uid . $e; if ($e !== $ext && file_exists($p)) @unlink($p); }
                    if (@move_uploaded_file($tmp, $target)) {
                        $avatarPath = 'uploads/avatars/' . $uid . $ext;
                    }
                }
            }
            if ($hasAddress) {
                if ($stmt = $mysqli->prepare('UPDATE users SET name=?, email=?, plate_no=?, reference_no=?, address=?, avatar=? WHERE user_id=?')) {
                    $stmt->bind_param('ssssssi', $name, $email, $plate, $ref, $address, $avatarPath, $uid);
                    $ok = $stmt->execute(); $stmt->close();
                    if ($ok) { $success = 'Profile updated.'; $_SESSION['user']['name'] = $name; $_SESSION['user']['email'] = $email; }
                    else { $error = 'Failed to update profile.'; }
                } else { $error = 'Internal error (prepare with address).'; }
            } else {
                if ($stmt = $mysqli->prepare('UPDATE users SET name=?, email=?, plate_no=?, reference_no=?, avatar=? WHERE user_id=?')) {
                    $stmt->bind_param('sssssi', $name, $email, $plate, $ref, $avatarPath, $uid);
                    $ok = $stmt->execute(); $stmt->close();
                    if ($ok) { $success = 'Profile updated.'; $_SESSION['user']['name'] = $name; $_SESSION['user']['email'] = $email; }
                    else { $error = 'Failed to update profile.'; }
                } else { $error = 'Internal error (prepare).'; }
            }
            // Reload fresh
            if ($error === '') {
                header('Location: profile.php?updated=1');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - TNVS</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div id="app" class="app">
        <header class="app-header">
            <button id="menuToggle" class="icon-btn" aria-label="Toggle menu"><span></span><span></span><span></span></button>
            <div class="brand" role="img" aria-label="TNVS Finance System">
                <svg viewBox="0 0 120 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <text x="10" y="16" font-size="16" font-weight="800" letter-spacing="0.5" font-family="Segoe UI, Roboto, Helvetica, Arial, sans-serif" fill="currentColor">SAKAYSAYA</text>
                </svg>
            </div>
            <div class="user">
                <span id="currentUser">Welcome, <?php echo htmlspecialchars($sessionUser['name']); ?></span>
                <a class="text-btn" href="logout.php">Logout</a>
            </div>
        </header>
        <nav id="sidebar" class="sidebar">
            <div class="group">
                <h4>Main</h4>
                <a class="nav" href="dashboard.php"><span>Dashboard</span></a>
            </div>
            <div class="group">
                <h4>Accounts Management</h4>
                <a class="nav" href="ledger.php"><span>General Ledger</span></a>
                <a class="nav" href="accounts_receivable.php"><span>Accounts Receivable</span></a>
                <a class="nav" href="accounts_payable.php"><span>Accounts Payable</span></a>
            </div>
            <div class="group">
                <h4>Financial Operations</h4>
                <a class="nav" href="disbursement.php"><span>Disbursement</span></a>
                <a class="nav" href="collection.php"><span>Collection</span></a>
            </div>
            <!-- Sidebar profile at bottom -->
            <div style="margin-top:auto;padding:16px;">
                <a href="profile.php" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit;">
                    <?php $nm = htmlspecialchars($sessionUser['name'] ?? 'User'); $initial = strtoupper(substr($nm,0,1)); $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128"><rect width="100%" height="100%" fill="#d9d9d9"/><text x="50%" y="55%" dominant-baseline="middle" text-anchor="middle" font-family="Segoe UI, Arial" font-size="64" fill="#555">'.$initial.'</text></svg>'; $src = 'data:image/svg+xml;charset=utf-8,' . rawurlencode($svg); ?>
                    <img src="<?php echo $src; ?>" alt="avatar" style="width:40px;height:40px;border-radius:50%;object-fit:cover;background:#ddd;flex:0 0 auto;" />
                    <div style="font-size:14px;line-height:1;">
                        <div style="font-weight:700"><?php echo $nm; ?></div>
                        <div style="font-size:12px;color:#666"><?php echo htmlspecialchars($sessionUser['email'] ?? ''); ?></div>
                    </div>
                </a>
            </div>
        </nav>
        <div id="overlay" class="overlay"></div>
        <main class="content" id="mainContent">
            <section class="section active">
                <h3>My Profile</h3>
                <?php if ($success): ?><div class="notification success show" style="position:static;margin-bottom:16px;"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
                <?php if ($error): ?><div class="notification danger show" style="position:static;margin-bottom:16px;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <div class="modal-card" style="display:block">
                    <div class="modal-header">
                        <h4 id="profileTitle">Profile of <?php echo htmlspecialchars($userRow['name']); ?></h4>
                    </div>
                    <div class="modal-body">
                        <form method="post" class="form-grid" enctype="multipart/form-data">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="do" value="update_profile" />
                            <label>Full name<input type="text" name="name" value="<?php echo htmlspecialchars($userRow['name']); ?>" required></label>
                            <label>Email<input type="email" name="email" value="<?php echo htmlspecialchars($userRow['email']); ?>" required></label>
                            <label>Avatar<input type="file" name="avatar" accept="image/*"></label>
                            <?php if ($hasAddress): ?>
                            <label>Address<input type="text" name="address" value="<?php echo htmlspecialchars($userRow['address'] ?? ''); ?>"></label>
                            <?php endif; ?>
                            <?php if (($userRow['role'] ?? '') !== 'admin'): ?>
                            <label>Plate No<input type="text" name="plate_no" value="<?php echo htmlspecialchars($userRow['plate_no'] ?? ''); ?>"></label>
                            <label>Reference No<input type="text" name="reference_no" value="<?php echo htmlspecialchars($userRow['reference_no'] ?? ''); ?>"></label>
                            <?php endif; ?>
                            <div style="grid-column: 1 / -1; display:flex; gap:10px;">
                                <button class="btn-small" type="submit">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        </main>
    </div>
    <script src="app.js"></script>
</body>
</html>

