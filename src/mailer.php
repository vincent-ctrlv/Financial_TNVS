<?php
// PHPMailer is installed with Composer (`composer install`).
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Sends an email using PHPMailer and Gmail SMTP.
 * 
 * @param string $to Recipient email address
 * @param string $subject Subject of the email
 * @param string $body HTML body of the email
 * @return bool True on success, False on failure
 */
function send_mail($to, $subject, $body) {
    if (!class_exists(PHPMailer::class)) {
        error_log('Mailer Error: PHPMailer is not installed. Run `composer install` in the project folder.');
        return false;
    }
    if (SMTP_USER === '' || SMTP_PASS === '') {
        error_log('Mailer Error: SMTP_USER / SMTP_PASS are not set in .env.');
        return false;
    }
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        // Recipients
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $subject = '=?UTF-8?B?' . base64_encode($subject) . '?='; // Proper encoding for many clients
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
