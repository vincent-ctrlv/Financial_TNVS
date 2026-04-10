<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';
require __DIR__ . '/lib/PHPMailer/Exception.php';
require __DIR__ . '/lib/PHPMailer/PHPMailer.php';
require __DIR__ . '/lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "--- VERBOSE SMTP DEBUG ---\n";

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;
    $mail->SMTPDebug  = 3; // Enable verbose debug output
    $mail->Debugoutput = 'echo';

    $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
    $mail->addAddress(SMTP_USER);

    $mail->isHTML(true);
    $mail->Subject = 'SMTP Test';
    $mail->Body    = 'Testing connectivity...';

    echo "Attempting to send...\n";
    $mail->send();
    echo "\nSUCCESS: Email sent!\n";
} catch (Exception $e) {
    echo "\nEXECPTION CAUGHT: " . $e->getMessage() . "\n";
    echo "PHPMailer Error Info: " . $mail->ErrorInfo . "\n";
} catch (\Throwable $t) {
    echo "\nFATAL ERROR: " . $t->getMessage() . "\n";
}
?>
