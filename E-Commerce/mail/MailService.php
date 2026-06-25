<?php

function mail_load_phpmailer(): bool
{
    static $loaded = false;
    static $available = false;
    if ($loaded) {
        return $available;
    }
    $loaded = true;
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!is_readable($autoload)) {
        return false;
    }
    require_once $autoload;
    $available = class_exists(\PHPMailer\PHPMailer\PHPMailer::class);
    return $available;
}

function mail_is_enabled(): bool
{
    $flag = strtolower(trim((string) (getenv('MAIL_ENABLED') ?: 'false')));
    return in_array($flag, ['1', 'true', 'yes', 'on'], true);
}

function mail_from_address(): string
{
    return trim((string) (getenv('MAIL_FROM_ADDRESS') ?: 'noreply@localhost'));
}

function mail_from_name(): string
{
    return trim((string) (getenv('MAIL_FROM_NAME') ?: 'ZERA'));
}

function mail_site_name(): string
{
    return trim((string) (getenv('MAIL_SITE_NAME') ?: 'ZERA'));
}

/**
 * @return array{host: string, port: int, user: string, pass: string, encryption: string}
 */
function mail_smtp_config(): array
{
    return [
        'host' => trim((string) (getenv('SMTP_HOST') ?: '')),
        'port' => (int) (getenv('SMTP_PORT') ?: 587),
        'user' => trim((string) (getenv('SMTP_USER') ?: '')),
        'pass' => (string) (getenv('SMTP_PASS') ?: ''),
        'encryption' => strtolower(trim((string) (getenv('SMTP_ENCRYPTION') ?: 'tls'))),
    ];
}

function send_html_mail(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool
{
    if (!mail_is_enabled()) {
        return false;
    }

    if (!mail_load_phpmailer()) {
        error_log('Mail skipped: PHPMailer not installed (run composer install in E-Commerce).');
        return false;
    }

    $toEmail = trim($toEmail);
    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $smtp = mail_smtp_config();
        if ($smtp['host'] !== '') {
            $mail->isSMTP();
            $mail->Host = $smtp['host'];
            $mail->Port = $smtp['port'] > 0 ? $smtp['port'] : 587;
            $mail->SMTPAuth = $smtp['user'] !== '';
            if ($smtp['user'] !== '') {
                $mail->Username = $smtp['user'];
                $mail->Password = $smtp['pass'];
            }
            if ($smtp['encryption'] === 'ssl') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($smtp['encryption'] === 'tls') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = false;
                $mail->SMTPAutoTLS = false;
            }
        } else {
            $mail->isMail();
        }

        $mail->CharSet = 'UTF-8';
        $mail->setFrom(mail_from_address(), mail_from_name());
        $mail->addAddress($toEmail, $toName !== '' ? $toName : $toEmail);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody !== '' ? $textBody : strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));

        $mail->send();
        return true;
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log('Mail send failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * @return array{subject: string, html: string, text: string}
 */
function build_welcome_email_content(string $name, string $lang = 'tr'): array
{
    $site = mail_site_name();
    $firstName = trim($name) !== '' ? trim(explode(' ', trim($name), 2)[0]) : ($lang === 'tr' ? 'Merhaba' : 'there');

    if ($lang === 'tr') {
        $subject = "{$site}'ya hoş geldiniz!";
        $text = "Merhaba {$firstName},\n\n"
            . "{$site} ailesine katıldığınız için teşekkürler. Hesabınız hazır.\n\n"
            . "Alışverişe başlayabilir, siparişlerinizi takip edebilir ve favorilerinizi kaydedebilirsiniz.\n\n"
            . "İyi alışverişler,\n{$site}";
        $html = '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#1f2937;max-width:560px;">'
            . '<h2 style="color:#ff6f00;margin:0 0 12px;">ZERA\'ya hoş geldiniz!</h2>'
            . '<p>Merhaba <strong>' . htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') . '</strong>,</p>'
            . '<p>' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8') . ' ailesine katıldığınız için teşekkürler. Hesabınız başarıyla oluşturuldu.</p>'
            . '<ul style="padding-left:18px;">'
            . '<li>Ürünleri keşfedin ve sepete ekleyin</li>'
            . '<li>Siparişlerinizi <strong>Siparişlerim</strong> sayfasından takip edin</li>'
            . '<li>Favorilerinizi kaydedin</li>'
            . '</ul>'
            . '<p style="margin-top:24px;">İyi alışverişler,<br><strong>' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8') . '</strong></p>'
            . '</div>';
    } else {
        $subject = "Welcome to {$site}!";
        $text = "Hi {$firstName},\n\n"
            . "Thanks for joining {$site}. Your account is ready.\n\n"
            . "You can start shopping, track your orders, and save your favorites.\n\n"
            . "Happy shopping,\n{$site}";
        $html = '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#1f2937;max-width:560px;">'
            . '<h2 style="color:#ff6f00;margin:0 0 12px;">Welcome to ZERA!</h2>'
            . '<p>Hi <strong>' . htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') . '</strong>,</p>'
            . '<p>Thanks for joining ' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8') . '. Your account has been created successfully.</p>'
            . '<ul style="padding-left:18px;">'
            . '<li>Browse products and add them to your cart</li>'
            . '<li>Track orders on the <strong>Orders</strong> page</li>'
            . '<li>Save items to your wishlist</li>'
            . '</ul>'
            . '<p style="margin-top:24px;">Happy shopping,<br><strong>' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8') . '</strong></p>'
            . '</div>';
    }

    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}

function send_welcome_email(string $name, string $email, ?string $lang = null): bool
{
    $lang = $lang ?: (function_exists('get_current_lang') ? get_current_lang() : 'tr');
    if (!in_array($lang, ['tr', 'en'], true)) {
        $lang = 'tr';
    }

    $content = build_welcome_email_content($name, $lang);
    return send_html_mail($email, $name, $content['subject'], $content['html'], $content['text']);
}
