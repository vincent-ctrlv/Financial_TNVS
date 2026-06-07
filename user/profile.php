<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_access('profile');
require __DIR__ . '/../includes/db.php';
$sessionUser = current_user();

$success = '';$error = '';
$uid = (int)($sessionUser['id'] ?? 0);
$userRow = null;

if ($uid > 0) {
    if ($stmt = $mysqli->prepare("SELECT user_id, name, email, role, plate_no, reference_no, address, avatar, created_at FROM users WHERE user_id = ? LIMIT 1")) {
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        $userRow = $res->fetch_assoc();
        $stmt->close();
    }
}

// Fallback to session if not in DB or query failed
if (!$userRow) {
    if ($sessionUser) {
        $userRow = [
            'user_id' => $uid,
            'name' => $sessionUser['name'] ?? 'User',
            'email' => $sessionUser['email'] ?? '',
            'role' => $sessionUser['role'] ?? '',
            'plate_no' => '',
            'reference_no' => '',
            'address' => '',
            'avatar' => $sessionUser['avatar'] ?? '',
            'created_at' => date('Y-m-d'),
        ];
    } else {
        http_response_code(404);
        echo 'User not found';
        exit;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['do'] ?? '') === 'update_profile') {
    verify_csrf_or_die();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $plate = trim($_POST['plate_no'] ?? '');
    $ref = trim($_POST['reference_no'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($uid <= 0) {
        $error = 'Unable to update profile: User record not found in database. Please log in again.';
    } elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide valid name and email.';
    } else {
        if ($stmt = $mysqli->prepare('SELECT 1 FROM users WHERE email = ? AND user_id <> ? LIMIT 1')) {
            $stmt->bind_param('si', $email, $uid);
            $stmt->execute(); $stmt->store_result();
            if ($stmt->num_rows > 0) { $error = 'Email already in use by another account.'; }
            $stmt->close();
        }
        if ($error === '') {
            $avatarPath = $userRow['avatar'] ?? '';
            if (isset($_FILES['avatar']) && is_uploaded_file($_FILES['avatar']['tmp_name'])) {
                $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'avatars';
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
            if ($stmt = $mysqli->prepare('UPDATE users SET name=?, email=?, plate_no=?, reference_no=?, address=?, avatar=? WHERE user_id=?')) {
                $stmt->bind_param('ssssssi', $name, $email, $plate, $ref, $address, $avatarPath, $uid);
                $ok = $stmt->execute(); $stmt->close();
                if ($ok) {
                    $success = 'Profile updated.';
                    $_SESSION['user']['name'] = $name;
                    $_SESSION['user']['email'] = $email;
                    $_SESSION['user']['avatar'] = $avatarPath;
                    log_audit_action($mysqli, 'update_profile', 'users');
                }
                else { $error = 'Failed to update profile.'; }
            } else { $error = 'Internal error (prepare).'; }
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
    <link rel="stylesheet" href="../assets/css/style.css?v=1.0.5">
    <style>
        .profile-container { max-width: 900px; margin: 0 auto; }
        .profile-card { background: white; border-radius: 24px; padding: 40px; box-shadow: var(--shadow-md); border: 1px solid rgba(40,167,69,0.1); }
        .profile-header-alt { display: flex; align-items: center; gap: 32px; margin-bottom: 40px; padding-bottom: 32px; border-bottom: 1px solid var(--panel); }
        .avatar-preview { position: relative; width: 120px; height: 120px; }
        .avatar-preview img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 4px solid var(--panel); box-shadow: var(--shadow-sm); }
        .form-section-title { font-size: 14px; font-weight: 800; color: var(--primary); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; }
        .form-section-title::after { content: ''; flex: 1; height: 1px; background: rgba(40,167,69,0.1); }
        .profile-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        @media (max-width: 640px) {
            .profile-form-grid { grid-template-columns: 1fr; }
            .profile-header-alt { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
    <div id="app" class="app">
        <?php include '../includes/header.php'; ?>
        <?php include '../includes/sidebar.php'; ?>
        <div id="overlay" class="overlay"></div>
        <main class="content" id="mainContent">
            <div class="profile-container">
                <section class="section active">
                    <h2 style="font-size: 32px; font-weight: 800; color: var(--primary); margin-bottom: 32px; letter-spacing: -1px;">My Profile</h2>
                    <?php if ($success || isset($_GET['updated'])): ?><div class="notification success show" style="position:static; margin-bottom:24px; transform:none; max-width:none; width:100%;">Profile updated successfully.</div><?php endif; ?>
                    <?php if ($error): ?><div class="notification danger show" style="position:static; margin-bottom:24px; transform:none; max-width:none; width:100%;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                    <div class="profile-card">
                        <form method="post" enctype="multipart/form-data">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="do" value="update_profile" />
                            <div class="profile-header-alt">
                                <div class="avatar-preview">
                                    <?php
                                    $u_avatar = $userRow['avatar'] ?? '';
                                    $found = false;
                                    if ($u_avatar && file_exists(dirname(__DIR__) . '/' . $u_avatar)) {
                                        $u_src = $_hWebRoot . '/' . $u_avatar;
                                        $found = true;
                                    } else {
                                        $u_initial = strtoupper(substr((string)$userRow['name'], 0, 1));
                                        $u_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128"><rect width="100%" height="100%" fill="#28a745" fill-opacity="0.1"/><text x="50%" y="55%" dominant-baseline="middle" text-anchor="middle" font-family="Segoe UI, Arial" font-size="64" fill="#28a745" font-weight="800">'.($u_initial?:'U').'</text></svg>';
                                        $u_src = 'data:image/svg+xml;charset=utf-8,' . rawurlencode($u_svg);
                                    }
                                    ?>
                                    <img src="<?php echo $u_src; ?>" alt="Profile avatar">
                                </div>
                                <div style="flex:1;">
                                    <h3 style="font-size:24px; font-weight:800; color:var(--text); margin-bottom:8px;">Account Details</h3>
                                    <p style="color:var(--muted); font-size:14px; margin-bottom:16px;">Update your photo and personal information here.</p>
                                    <div style="display:flex; gap:10px; align-items:center;">
                                        <input type="file" name="avatar" accept="image/*" style="font-size:13px;">
                                        <?php if ($found): ?><span style="font-size:12px; color:var(--primary);">✓ Photo active</span><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="form-section-title">Personal Information</div>
                            <div class="profile-form-grid">
                                <label style="display:grid; gap:8px; font-size:12px; font-weight:600; color:var(--muted); text-transform:uppercase;">Full Name
                                    <input type="text" name="name" value="<?php echo htmlspecialchars($userRow['name'] ?? ''); ?>" required style="width:100%; background:#fff; border:1px solid #ddd; border-radius:12px; padding:12px 16px; font-size:14px; color:var(--text);"></label>
                                <label style="display:grid; gap:8px; font-size:12px; font-weight:600; color:var(--muted); text-transform:uppercase;">Email Address
                                    <input type="email" name="email" value="<?php echo htmlspecialchars($userRow['email'] ?? ''); ?>" required style="width:100%; background:#fff; border:1px solid #ddd; border-radius:12px; padding:12px 16px; font-size:14px; color:var(--text);"></label>
                                <label style="display:grid; gap:8px; font-size:12px; font-weight:600; color:var(--muted); text-transform:uppercase; grid-column:1/-1;">Physical Address
                                    <input type="text" name="address" value="<?php echo htmlspecialchars($userRow['address'] ?? ''); ?>" style="width:100%; background:#fff; border:1px solid #ddd; border-radius:12px; padding:12px 16px; font-size:14px; color:var(--text);"></label>
                            </div>
                            <?php if (($userRow['role'] ?? '') !== 'admin'): ?>
                            <div class="form-section-title" style="margin-top:40px;">Professional Details</div>
                            <div class="profile-form-grid">
                                <label style="display:grid; gap:8px; font-size:12px; font-weight:600; color:var(--muted); text-transform:uppercase;">Plate Number
                                    <input type="text" name="plate_no" value="<?php echo htmlspecialchars($userRow['plate_no'] ?? ''); ?>" style="width:100%; background:#fff; border:1px solid #ddd; border-radius:12px; padding:12px 16px; font-size:14px; color:var(--text);"></label>
                                <label style="display:grid; gap:8px; font-size:12px; font-weight:600; color:var(--muted); text-transform:uppercase;">Reference Number
                                    <input type="text" name="reference_no" value="<?php echo htmlspecialchars($userRow['reference_no'] ?? ''); ?>" style="width:100%; background:#fff; border:1px solid #ddd; border-radius:12px; padding:12px 16px; font-size:14px; color:var(--text);"></label>
                            </div>
                            <?php endif; ?>
                            <div style="margin-top:48px; display:flex; justify-content:flex-end;">
                                <button type="submit" class="btn-small" style="padding:16px 48px; font-size:16px; background:var(--gradient-primary); color:white; border-radius:16px; box-shadow:var(--shadow-glow);">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </section>
            </div>
        </main>
    </div>
    <script src="../assets/js/app.js"></script>
</body>
</html>
