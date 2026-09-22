<?php
// ============================================================
// IEXPLORE LAGUNA — Mailer (PHPMailer, no Composer)
// includes/mailer.php
//
// PHPMailer is vendored directly under /vendor/phpmailer (just the
// three files PHPMailer actually needs: PHPMailer.php, SMTP.php,
// Exception.php) instead of pulled in through Composer, to match how
// the rest of this project has no build step — drop the folder in and
// it works.
//
// Requires SMTP_HOST / SMTP_USER / SMTP_PASS to be set in .env (see
// includes/config.php for the Gmail App Password instructions). If
// SMTP isn't configured yet, send_mail() returns false instead of
// throwing, so the rest of the app doesn't break before that's set up.
// ============================================================

require_once __DIR__ . '/../vendor/phpmailer/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Send an HTML email over SMTP.
 *
 * @param string $to_email
 * @param string $to_name
 * @param string $subject
 * @param string $html_body
 * @return bool  true if the message was accepted for delivery
 */
function send_mail(string $to_email, string $to_name, string $subject, string $html_body): bool {
    if (!SMTP_HOST || !SMTP_USER || !SMTP_PASS) {
        error_log('send_mail() skipped: SMTP_HOST/SMTP_USER/SMTP_PASS not set in .env');
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->Port       = SMTP_PORT;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_PORT === 465 ? 'ssl' : 'tls';
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to_email, $to_name);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_body;
        $mail->AltBody  = trim(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html_body)));

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        error_log('send_mail() failed: ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Build and send the "reset your password" email.
 */
function send_password_reset_email(string $to_email, string $to_name, string $reset_url): bool {
    $safe_name = e($to_name);
    $safe_url  = e($reset_url);

    $html = <<<HTML
    <div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;color:#2b2b2b">
      <h2 style="color:#a3202e;margin-bottom:4px;">{$safe_name},</h2>
      <p>We got a request to reset the password for your {$to_email} account on {APP_NAME}.</p>
      <p style="margin:24px 0;">
        <a href="{$safe_url}"
           style="background:#a3202e;color:#fff;text-decoration:none;
                  padding:12px 22px;border-radius:8px;display:inline-block;">
          Reset Password
        </a>
      </p>
      <p style="font-size:.85rem;color:#666;">
        This link expires in 30 minutes and can only be used once.
        If you didn't request this, you can safely ignore this email —
        your password won't change.
      </p>
      <p style="font-size:.75rem;color:#999;margin-top:24px;">
        Having trouble with the button? Paste this link into your browser:<br>
        {$safe_url}
      </p>
    </div>
    HTML;

    $html = str_replace('{APP_NAME}', APP_NAME, $html);

    return send_mail($to_email, $to_name, 'Reset your ' . APP_NAME . ' password', $html);
}
