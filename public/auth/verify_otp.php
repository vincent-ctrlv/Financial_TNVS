<?php
// Second sign-in step: the user typed a correct password on login.php and
// was emailed a 6-digit code. They are signed in only once the code matches.
require_once __DIR__ . '/../../src/bootstrap.php';

if (is_logged_in()) {
    header('Location: ' . url('dashboard.php'));
    exit;
}
if (empty($_SESSION['pending_user'])) {
    header('Location: ' . url('auth/login.php'));
    exit;
}

$csrf_token = csrf_token();

$error  = null;
$notice = null;
$locked = false;

if (isset($_GET['sent'])) {
    $notice = 'A new code has been sent.';
} elseif (isset($_GET['failed'])) {
    $error = 'We could not send a new code. Please try again later.';
} elseif (isset($_GET['wait'])) {
    $error = 'Please wait ' . max(1, (int)$_GET['wait']) . ' second(s) before requesting another code.';
}

// Brute-force: max 5 wrong attempts, then 10-minute lockout
$OTP_MAX_ATTEMPTS    = 5;
$OTP_LOCKOUT_SECONDS = 600;

// Check active lockout
if (!empty($_SESSION['otp_locked_until']) && time() < $_SESSION['otp_locked_until']) {
    $locked    = true;
    $remaining = $_SESSION['otp_locked_until'] - time();
    $error     = "Too many failed attempts. Please wait " . ceil($remaining / 60) . " minute(s) before trying again.";
}

if (!$locked && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF verification
    $submitted_csrf = $_POST['_csrf'] ?? '';
    if (!hash_equals($csrf_token, $submitted_csrf)) {
        $error = "Invalid request token. Please reload the page and try again.";
    } else {
        if (!isset($_SESSION['otp_attempts'])) {
            $_SESSION['otp_attempts'] = 0;
        }

        $entered = trim($_POST['otp'] ?? '');
        $hash    = $_SESSION['otp_hash'] ?? null;
        $expiry  = $_SESSION['otp_expiry'] ?? 0;

        if (!$hash || time() > $expiry) {
            // Expired or missing OTP — clear everything
            unset($_SESSION['otp_hash'], $_SESSION['otp_expiry'], $_SESSION['otp_attempts']);
            $error = "OTP expired or not found. Please request a new one.";
        } elseif (preg_match('/^\d{6}$/', $entered) && password_verify($entered, $hash)) {
            // ✅ Correct — consume OTP, clear all counters, sign the user in
            $pendingUser = $_SESSION['pending_user'];
            unset(
                $_SESSION['otp_hash'],
                $_SESSION['otp_expiry'],
                $_SESSION['otp_sent_at'],
                $_SESSION['otp_attempts'],
                $_SESSION['otp_locked_until']
            );
            login_user($pendingUser);
            header('Location: ' . url('dashboard.php'));
            exit;
        } else {
            // ❌ Wrong code — increment attempt counter
            $_SESSION['otp_attempts']++;
            $remaining_attempts = $OTP_MAX_ATTEMPTS - $_SESSION['otp_attempts'];

            if ($_SESSION['otp_attempts'] >= $OTP_MAX_ATTEMPTS) {
                // Lock out and invalidate OTP
                $_SESSION['otp_locked_until'] = time() + $OTP_LOCKOUT_SECONDS;
                unset($_SESSION['otp_hash'], $_SESSION['otp_expiry'], $_SESSION['otp_attempts']);
                $locked = true;
                $error  = "Too many failed attempts. Your OTP has been invalidated. Please request a new code after 10 minutes.";
            } else {
                $error = "Invalid OTP code. You have " . $remaining_attempts . " attempt(s) remaining.";
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
    <title>Verify OTP - SakaySaya</title>
    <style>
        :root {
            --primary: #0984E3;
            --bg: #F5F6FA;
            --card-bg: #FFFFFF;
            --text: #2D3436;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .card {
            background: var(--card-bg);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        h2 { margin-bottom: 20px; color: var(--primary); }
        p { color: #636E72; font-size: 14px; margin-bottom: 30px; }
        input[type="text"] {
            width: 100%;
            padding: 12px;
            font-size: 24px;
            text-align: center;
            letter-spacing: 8px;
            border: 2px solid #DFE6E9;
            border-radius: 8px;
            margin-bottom: 20px;
            outline: none;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        input[type="text"]:focus { border-color: var(--primary); }
        button {
            width: 100%;
            padding: 12px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s, background 0.3s;
        }
        button:hover { background: #0773C5; transform: translateY(-2px); }
        button:disabled { background: #b2bec3; cursor: not-allowed; transform: none; }
        .error { color: #D63031; margin-bottom: 20px; font-size: 14px; }
        .notice { color: #00b894; margin-bottom: 20px; font-size: 14px; }
        .resend { margin-top: 20px; font-size: 14px; }
        .resend a { color: var(--primary); text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Enter Verification Code</h2>
        <p>A 6-digit code has been sent to your email address.</p>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($notice): ?>
            <div class="notice"><?php echo htmlspecialchars($notice); ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="text" name="otp" maxlength="6" placeholder="000000"
                   autocomplete="off" <?php echo $locked ? 'disabled' : 'required autofocus'; ?>
                   inputmode="numeric">
            <button type="submit" <?php echo $locked ? 'disabled' : ''; ?>>Verify Code</button>
        </form>

        <div class="resend">
            <?php if ($locked): ?>
                OTP invalidated. <a href="send_otp.php">Request a new code</a>
            <?php else: ?>
                Didn't receive code? <a href="send_otp.php">Resend</a>
            <?php endif; ?>
            <br><a href="logout.php" style="font-weight:normal;color:#636E72;">Use a different account</a>
        </div>
    </div>
</body>
</html>
