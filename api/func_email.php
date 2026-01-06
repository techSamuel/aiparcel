<?php
// api/func_email.php

require_once __DIR__ . '/src/Exception.php';
require_once __DIR__ . '/src/PHPMailer.php';
require_once __DIR__ . '/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Sends a system email using PHPMailer.
 */
function sendSystemEmail($to, $subject, $body_html)
{
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = (SMTP_SECURE === 'tls') ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = SMTP_PORT;

        // Sender
        $mail->setFrom(SMTP_FROM_EMAIL, defined('APP_NAME') ? APP_NAME : 'AiParcel');
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body_html;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed to $to: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Generates the full HTML email body by wrapping content in the standard template.
 */
function wrapInEmailTemplate($title, $message_content, $pdo)
{
    // Fetch Settings for Logo/Name
    $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('app_name', 'app_logo_url')");
    $settings = array_column($stmt_settings->fetchAll(), 'setting_value', 'setting_key');
    $appName = $settings['app_name'] ?? 'AiParcel';
    $logoUrl = ($settings['app_logo_url'] ?? '') ? APP_URL . '/' . $settings['app_logo_url'] : '';

    $template = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($title) . '</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f7f9; }
        .container { max-width: 600px; margin: 40px auto; background-color: #ffffff; border-radius: 12px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); overflow: hidden; }
        .header { background-color: #ffffff; padding: 20px; text-align: center; border-bottom: 1px solid #eee; }
        .header img { max-width: 150px; max-height: 80px; }
        .content { padding: 40px; color: #4a4a4a; line-height: 1.6; }
        .content h1 { font-size: 22px; color: #333; margin-top: 0; }
        .button { display: inline-block; background-color: #d72129; color: #ffffff !important; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 600; margin: 20px 0; }
        .footer { padding: 20px; font-size: 12px; color: #999; text-align: center; background-color: #f4f7f9; border-top: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            ' . ($logoUrl ? '<img src="' . $logoUrl . '" alt="' . $appName . '">' : '<h2>' . $appName . '</h2>') . '
        </div>
        <div class="content">
            <h1>' . $title . '</h1>
            ' . $message_content . '
            <p>Thank you,<br>The ' . $appName . ' Team</p>
        </div>
        <div class="footer">
            &copy; ' . date('Y') . ' ' . $appName . '. All rights reserved.
        </div>
    </div>
</body>
</html>';

    return $template;
}
