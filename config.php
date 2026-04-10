<?php
// SMTP configuration for PHPMailer
// IMPORTANT: Use a Google App Password (not your account password)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'vincentsasi9@gmail.com');
define('SMTP_PASS', 'your-16-character-app-password'); // Replace this!
define('SMTP_FROM', 'vincentsasi9@gmail.com');
define('SMTP_FROM_NAME', 'SakaySaya Finance');

// OTP Settings
define('OTP_EXPIRY_SECONDS', 300); // 5 minutes
?>
