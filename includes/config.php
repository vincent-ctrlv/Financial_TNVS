<?php
require_once __DIR__ . '/env.php';

// SMTP configuration for PHPMailer
// ---------------------------------------------------------------
// SECURITY: Never hardcode credentials in this file.
// Set these as environment variables on your server instead:
//   SMTP_USER=you@gmail.com
//   SMTP_PASS=your-app-password
//   SMTP_FROM=you@gmail.com
// ---------------------------------------------------------------
define('SMTP_HOST',      getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT',      (int)(getenv('SMTP_PORT') ?: 587));
define('SMTP_USER',      getenv('SMTP_USER') ?: 'vincentsasi98@gmail.com');
define('SMTP_PASS',      getenv('SMTP_PASS') ?: '');          // <-- set env var, do NOT put password here
define('SMTP_FROM',      getenv('SMTP_FROM') ?: 'vincentsasi98@gmail.com');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'SakaySaya Finance');

// OTP Settings
define('OTP_EXPIRY_SECONDS', 300); // 5 minutes
?>