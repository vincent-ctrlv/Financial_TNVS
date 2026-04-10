<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered = $_POST['otp'] ?? '';
    $correct = $_SESSION['otp_code'] ?? null;
    $expiry = $_SESSION['otp_expiry'] ?? 0;

    if (!$correct || time() > $expiry) {
        $error = "OTP expired or not found. Please request a new one.";
    } elseif ($entered === $correct) {
        $success = "OTP Verified Successfully!";
        // In a real app, logic to proceed with login or reset goes here.
        // For now, let's redirect to dashboard if they are already logged in (via seed_admin).
        unset($_SESSION['otp_code']);
        unset($_SESSION['otp_expiry']);
        header('Location: dashboard.php?msg=otp_verified');
        exit;
    } else {
        $error = "Invalid OTP code. Please try again.";
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
        .error { color: #D63031; margin-bottom: 20px; font-size: 14px; }
        .resend { margin-top: 20px; font-size: 14px; }
        .resend a { color: var(--primary); text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Enter Verification Code</h2>
        <p>A 6-digit code has been sent to your Gmail.</p>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="otp" maxlength="6" placeholder="000000" autocomplete="off" required autofocus>
            <button type="submit">Verify Code</button>
        </form>

        <div class="resend">
            Didn't receive code? <a href="send_otp.php">Resend</a>
        </div>
    </div>
</body>
</html>
