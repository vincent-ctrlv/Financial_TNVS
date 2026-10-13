<?php
require_once __DIR__ . '/../../src/bootstrap.php';

if (is_logged_in()) {
    header('Location: ' . url('dashboard.php'));
    exit;
}

$csrf_token = csrf_token();

// ── Brute-force lockout ──
$LOGIN_MAX_FAILS    = 5;
$LOGIN_LOCKOUT_SECS = 300; // 5 minutes

$locked = false;
if (!empty($_SESSION['login_locked_until']) && time() < $_SESSION['login_locked_until']) {
    $locked = true;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    $submitted_csrf = $_POST['_csrf'] ?? '';
    if (!hash_equals($csrf_token, $submitted_csrf)) {
        $error = 'Invalid request. Please reload and try again.';
    } elseif ($locked) {
        $remaining = $_SESSION['login_locked_until'] - time();
        $error = 'Too many failed attempts. Please wait ' . ceil($remaining / 60) . ' minute(s).';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        if ($email && $password) {
            $found = check_credentials($mysqli, $email, $password);
            if ($found) {
                unset($_SESSION['login_fails'], $_SESSION['login_locked_until']);
                if (!LOGIN_OTP) {
                    login_user($found);
                    header('Location: ' . url('dashboard.php'));
                    exit;
                }
                // Password OK: email a one-time code and finish sign-in on verify_otp.php
                session_regenerate_id(true);
                if (send_login_otp($found)) {
                    header('Location: ' . url('auth/verify_otp.php'));
                    exit;
                }
                unset($_SESSION['pending_user']);
                $error = 'We could not send your verification code. Please try again later or contact an administrator.';
            }
            if (!$found) {
                $_SESSION['login_fails'] = ($_SESSION['login_fails'] ?? 0) + 1;
                if ($_SESSION['login_fails'] >= $LOGIN_MAX_FAILS) {
                    $_SESSION['login_locked_until'] = time() + $LOGIN_LOCKOUT_SECS;
                    unset($_SESSION['login_fails']);
                    $locked = true;
                    $error  = 'Too many failed attempts. Your account is temporarily locked for 5 minutes.';
                } else {
                    $remaining_attempts = $LOGIN_MAX_FAILS - $_SESSION['login_fails'];
                    $error = 'Invalid email or password. ' . $remaining_attempts . ' attempt(s) remaining.';
                }
            }
        } else {
            $error = 'Please enter email and password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Login - SakaySaya</title>
	<link rel="stylesheet" href="../assets/css/style.css?v=1.0.5">
	<style>
		html, body {
			overflow: hidden !important;
			scrollbar-gutter: auto !important;
		}
		.back-link {
			position: absolute;
			top: 24px;
			left: 24px;
			display: flex;
			align-items: center;
			gap: 6px;
			color: rgba(255, 255, 255, 0.6);
			text-decoration: none;
			font-size: 13px;
			font-weight: 600;
			transition: all var(--transition-normal);
			padding: 6px 12px;
			border-radius: 10px;
			background: rgba(255, 255, 255, 0.05);
			border: 1px solid rgba(255, 255, 255, 0.08);
			z-index: 10;
		}
		.back-link:hover {
			color: #ffffff;
			background: rgba(255, 255, 255, 0.1);
			border-color: rgba(255, 255, 255, 0.15);
			transform: translateX(-2px);
		}
	</style>
</head>
<body>
	<div id="login" class="login">
		<a href="../" class="back-link">
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
				<line x1="19" y1="12" x2="5" y2="12"></line>
				<polyline points="12 19 5 12 12 5"></polyline>
			</svg>
			Back to Home
		</a>
		<div class="login-card">
			<div class="logo">
				<h1>SAKAYSAYA</h1>
				<p>Finance System Login</p>
			</div>
			<form class="login-form" method="post" action="">
				<input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
				<label>Email
					<input type="email" name="email" placeholder="you@example.com" required
					       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
					       <?php echo $locked ? 'disabled' : ''; ?> />
				</label>
				<label>Password
					<input type="password" name="password" placeholder="••••••••" required
					       <?php echo $locked ? 'disabled' : ''; ?> />
				</label>
				<div class="error-message"><?php echo htmlspecialchars($error); ?></div>
				<button type="submit" <?php echo $locked ? 'disabled' : ''; ?>>Sign in</button>
			</form>
		</div>
	</div>
</body>
</html>
