<?php
// App configuration. Values come from .env (see .env.example); never put
// real credentials in this file.

// SMTP (PHPMailer)
define('SMTP_HOST',      getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT',      (int)(getenv('SMTP_PORT') ?: 587));
define('SMTP_USER',      getenv('SMTP_USER') ?: '');
define('SMTP_PASS',      getenv('SMTP_PASS') ?: '');
define('SMTP_FROM',      getenv('SMTP_FROM') ?: SMTP_USER);
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'SakaySaya Finance');

// Sign-in: require an emailed one-time code after the password.
// Set LOGIN_OTP=false in .env for local development without SMTP.
define('LOGIN_OTP', filter_var(getenv('LOGIN_OTP') === false ? 'true' : getenv('LOGIN_OTP'), FILTER_VALIDATE_BOOLEAN));
define('OTP_EXPIRY_SECONDS', 300);  // 5 minutes
define('OTP_RESEND_COOLDOWN', 60);  // seconds between resends
