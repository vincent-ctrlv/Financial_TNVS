<?php
// Resend the sign-in code. Only works for someone who has just passed the
// password step (auth/login.php), and only to that account's own email.
require_once __DIR__ . '/../../src/bootstrap.php';

$pending = $_SESSION['pending_user'] ?? null;
if (!$pending) {
    header('Location: ' . url('auth/login.php'));
    exit;
}

$wait = (int)($_SESSION['otp_sent_at'] ?? 0) + OTP_RESEND_COOLDOWN - time();
if ($wait > 0) {
    header('Location: ' . url('auth/verify_otp.php?wait=' . $wait));
    exit;
}

if (!empty($_SESSION['otp_locked_until']) && time() < $_SESSION['otp_locked_until']) {
    header('Location: ' . url('auth/verify_otp.php'));
    exit;
}

$sent = send_login_otp($pending);
header('Location: ' . url('auth/verify_otp.php?' . ($sent ? 'sent=1' : 'failed=1')));
exit;
