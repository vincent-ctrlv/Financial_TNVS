<?php
require_once __DIR__ . '/../includes/auth.php';

// ── CSRF setup ──
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

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
            if (attempt_login($mysqli, $email, $password)) {
                // Successful login — reset fail counter and regenerate session
                unset($_SESSION['login_fails'], $_SESSION['login_locked_until']);
                session_regenerate_id(true);
                header('Location: ../dashboard.php');
                exit;
            }
            // Failed login
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
	</style>
</head>
<body>
	<div id="login" class="login">
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
