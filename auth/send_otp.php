<?php
require_once __DIR__ . '/../includes/auth.php'; // For session handling
require_once __DIR__ . '/../includes/mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Resolve recipient email: prefer logged-in user's email, then fallback to a
// POST/GET 'email' param (for pre-login OTP flow). Never use a hardcoded address.
$email = '';
if (is_logged_in()) {
    $email = current_user()['email'] ?? '';
} elseif (!empty($_POST['email'])) {
    $email = trim($_POST['email']);
} elseif (!empty($_GET['email'])) {
    $email = trim($_GET['email']);
}

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die('A valid email address is required to send an OTP. Please <a href="login.php">log in</a> first.');
}

// Generate 6-digit OTP
$otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expiry = time() + OTP_EXPIRY_SECONDS;

$_SESSION['otp_code'] = $otp;
$_SESSION['otp_expiry'] = $expiry;
$_SESSION['otp_email'] = $email;

$subject = "Your SakaySaya OTP Code";
$body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
        <h2 style='color: #2D3436;'>SakaySaya Finance System</h2>
        <p>Hello,</p>
        <p>Your One-Time Password (OTP) for account verification is:</p>
        <div style='font-size: 32px; font-weight: bold; color: #0984E3; padding: 20px; text-align: center; background: #f9f9f9; border-radius: 5px; margin: 20px 0;'>
            $otp
        </div>
        <p>This code will expire in 5 minutes.</p>
        <p style='font-size: 12px; color: #636E72; margin-top: 30px;'>If you did not request this code, please ignore this email.</p>
    </div>
";

if (send_mail($email, $subject, $body)) {
    header('Location: verify_otp.php?sent=1');
} else {
    // If it fails, likely due to SMTP config (App Password)
    die("Failed to send OTP. Please check your SMTP settings in config.php. Also ensure you are using a Google App Password.");
}
exit;
?>
