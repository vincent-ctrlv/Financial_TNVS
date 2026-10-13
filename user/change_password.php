<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_access('change_password');
require __DIR__ . '/../includes/db.php';
$sessionUser = current_user();
$uid = (int)($sessionUser['id'] ?? 0);

$success = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['do'] ?? '') === 'change_password') {
    verify_csrf_or_die();
    $current  = $_POST['current_password'] ?? '';
    $new      = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($current === '' || $new === '' || $confirm === '') {
        $error = 'All fields are required.';
    } elseif (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New password and confirmation do not match.';
    } else {
        // Fetch current hash
        $hash = '';
        if ($stmt = $mysqli->prepare('SELECT password_hash FROM users WHERE user_id = ? LIMIT 1')) {
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) { $hash = $row['password_hash']; }
            $stmt->close();
        }
        if (!password_verify($current, $hash)) {
            $error = 'Current password is incorrect.';
        } else {
            $newHash = password_hash($new, PASSWORD_DEFAULT);
            if ($stmt = $mysqli->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')) {
                $stmt->bind_param('si', $newHash, $uid);
                $ok = $stmt->execute();
                $stmt->close();
                if ($ok) {
                    log_audit_action($mysqli, 'change_password', 'users');
                    $success = 'Password changed successfully!';
                } else {
                    $error = 'Failed to update password. Please try again.';
                }
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
    <title>Change Password</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=1.0.5">
    <style>
        .cp-container { max-width: 560px; margin: 0 auto; }
        .cp-card {
            background: white;
            border-radius: 24px;
            padding: 48px;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(40,167,69,0.1);
        }
        .cp-icon-wrap {
            width: 72px; height: 72px;
            background: linear-gradient(135deg, rgba(40,167,69,0.15), rgba(40,167,69,0.05));
            border-radius: 20px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 24px;
        }
        .strength-bar-wrap { margin-top: 8px; }
        .strength-bar-track {
            height: 6px; border-radius: 99px;
            background: #eee; overflow: hidden;
        }
        .strength-bar-fill {
            height: 100%; border-radius: 99px;
            transition: width 0.3s ease, background 0.3s ease;
            width: 0%;
        }
        .strength-label {
            font-size: 11px; font-weight: 700;
            margin-top: 5px; letter-spacing: .5px;
            text-transform: uppercase;
        }
        .pw-field-wrap { position: relative; }
        .pw-field-wrap input { padding-right: 44px !important; }
        .pw-toggle {
            position: absolute; right: 14px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none; cursor: pointer;
            color: var(--muted); padding: 0; line-height: 1;
        }
        .pw-toggle:hover { color: var(--primary); }
        .form-label {
            display: grid; gap: 8px;
            font-size: 12px; font-weight: 700;
            color: var(--muted); text-transform: uppercase;
            letter-spacing: .5px;
        }
        .form-label input {
            width: 100%; border: 1.5px solid #e5e7eb;
            border-radius: 12px; padding: 12px 16px;
            font-size: 14px; color: var(--text);
            transition: border-color .2s;
            box-sizing: border-box;
        }
        .form-label input:focus {
            outline: none; border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(40,167,69,0.12);
        }
        .req-list { list-style: none; padding: 0; margin: 12px 0 0; display: flex; flex-direction: column; gap: 6px; }
        .req-list li { font-size: 12px; color: var(--muted); display: flex; align-items: center; gap: 8px; transition: color .2s; }
        .req-list li.met { color: var(--primary); }
        .req-list li::before { content: '○'; font-size: 10px; }
        .req-list li.met::before { content: '✓'; }
    </style>
</head>
<body>
<div id="app" class="app">
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/sidebar.php'; ?>
    <div id="overlay" class="overlay"></div>
    <main class="content" id="mainContent">
        <div class="cp-container">
            <section class="section active">
                <h2 style="font-size:32px; font-weight:800; color:var(--primary); margin-bottom:32px; letter-spacing:-1px;">Change Password</h2>

                <?php if ($success): ?>
                <div class="notification success show" style="position:static;margin-bottom:24px;transform:none;max-width:none;width:100%;">
                    <?php echo htmlspecialchars($success); ?>
                </div>
                <?php endif; ?>
                <?php if ($error): ?>
                <div class="notification danger show" style="position:static;margin-bottom:24px;transform:none;max-width:none;width:100%;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <div class="cp-card">
                    <div class="cp-icon-wrap">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#28a745" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </div>
                    <h3 style="font-size:20px;font-weight:800;color:var(--text);margin-bottom:6px;">Update Your Password</h3>
                    <p style="color:var(--muted);font-size:14px;margin-bottom:32px;">Choose a strong password to keep your account secure.</p>

                    <form method="post" id="cpForm" style="display:flex;flex-direction:column;gap:22px;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="do" value="change_password">

                        <!-- Current Password -->
                        <label class="form-label">Current Password
                            <div class="pw-field-wrap">
                                <input type="password" name="current_password" id="currentPw" required placeholder="Enter your current password">
                                <button type="button" class="pw-toggle" onclick="togglePw('currentPw',this)" title="Show/hide">
                                    <svg id="eyeI1" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                </button>
                            </div>
                        </label>

                        <hr style="border:none;border-top:1px solid #f0f0f0;">

                        <!-- New Password -->
                        <label class="form-label">New Password
                            <div class="pw-field-wrap">
                                <input type="password" name="new_password" id="newPw" required placeholder="Min. 8 characters" oninput="checkStrength(this.value)">
                                <button type="button" class="pw-toggle" onclick="togglePw('newPw',this)" title="Show/hide">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                </button>
                            </div>
                            <div class="strength-bar-wrap">
                                <div class="strength-bar-track"><div class="strength-bar-fill" id="strengthBar"></div></div>
                                <div class="strength-label" id="strengthLabel" style="color:#bbb;">— Enter a password</div>
                            </div>
                            <ul class="req-list" id="reqList">
                                <li id="req-len">At least 8 characters</li>
                                <li id="req-upper">At least one uppercase letter</li>
                                <li id="req-num">At least one number</li>
                                <li id="req-special">At least one special character</li>
                            </ul>
                        </label>

                        <!-- Confirm Password -->
                        <label class="form-label">Confirm New Password
                            <div class="pw-field-wrap">
                                <input type="password" name="confirm_password" id="confirmPw" required placeholder="Re-enter new password" oninput="checkMatch()">
                                <button type="button" class="pw-toggle" onclick="togglePw('confirmPw',this)" title="Show/hide">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                </button>
                            </div>
                            <div id="matchMsg" style="font-size:12px;margin-top:5px;min-height:16px;"></div>
                        </label>

                        <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:8px;">
                            <a href="profile.php" style="display:inline-flex;align-items:center;padding:12px 24px;border-radius:12px;font-size:14px;font-weight:600;color:var(--muted);text-decoration:none;">Cancel</a>
                            <button type="submit" id="submitBtn" class="btn-small" style="padding:14px 40px;font-size:15px;background:var(--gradient-primary);color:white;border-radius:14px;box-shadow:var(--shadow-glow);">
                                Update Password
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </main>
</div>
<script src="../assets/js/app.js"></script>
<script>
function togglePw(id, btn) {
    const inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
}
function checkStrength(val) {
    const bar  = document.getElementById('strengthBar');
    const lbl  = document.getElementById('strengthLabel');
    const reqs = {
        'req-len':     val.length >= 8,
        'req-upper':   /[A-Z]/.test(val),
        'req-num':     /[0-9]/.test(val),
        'req-special': /[^A-Za-z0-9]/.test(val),
    };
    Object.entries(reqs).forEach(([id, met]) => {
        document.getElementById(id).classList.toggle('met', met);
    });
    const score = Object.values(reqs).filter(Boolean).length;
    const configs = [
        {w:'0%',  c:'#bbb',     t:'— Enter a password'},
        {w:'25%', c:'#ef4444',  t:'Weak'},
        {w:'50%', c:'#f59e0b',  t:'Fair'},
        {w:'75%', c:'#3b82f6',  t:'Good'},
        {w:'100%',c:'#28a745',  t:'Strong'},
    ];
    const cfg = configs[score];
    bar.style.width      = cfg.w;
    bar.style.background = cfg.c;
    lbl.style.color      = cfg.c;
    lbl.textContent      = cfg.t;
}
function checkMatch() {
    const nv = document.getElementById('newPw').value;
    const cv = document.getElementById('confirmPw').value;
    const msg = document.getElementById('matchMsg');
    if (cv === '') { msg.textContent = ''; return; }
    if (nv === cv) {
        msg.style.color = '#28a745'; msg.textContent = '✓ Passwords match';
    } else {
        msg.style.color = '#ef4444'; msg.textContent = '✗ Passwords do not match';
    }
}
</script>
</body>
</html>
