<?php
require_once __DIR__ . '/../includes/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$email = trim($_POST['email'] ?? '');
	$password = (string)($_POST['password'] ?? '');
	if ($email && $password) {
        // Use the proper authentication function from auth.php
		if (attempt_login($mysqli, $email, $password)) {
			header('Location: ../dashboard.php');
			exit;
		}
		$error = 'Invalid email or password';
	} else {
		$error = 'Please enter email and password';
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
				<label>Email
					<input type="email" name="email" placeholder="you@example.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" />
				</label>
				<label>Password
					<input type="password" name="password" placeholder="••••••••" required />
				</label>
				<div class="error-message"><?php echo htmlspecialchars($error); ?></div>
				<button type="submit">Sign in</button>
			</form>
		</div>
	</div>
</body>
</html>
