<?php
declare(strict_types=1);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo "Method not allowed.";
    exit;
}

// Load Composer autoloader if present (for PHPMailer)
$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
}

// Config via environment with sensible defaults
$to       = getenv('MAIL_TO') ?: 'tullejohn62@gmail.com';
$from     = getenv('MAIL_FROM') ?: 'info@tjsasphaltandconcreterepair.com';
$fromName = getenv('MAIL_FROM_NAME') ?: "TJ's Asphalt & Concrete Repair";
$subject  = getenv('MAIL_SUBJECT') ?: "New Contact Form Submission - TJ's Asphalt & Concrete Repair";

// Helper sanitize
$sanitize = static function (?string $v, int $max = 5000): string {
    $v = trim((string)$v);
    $v = strip_tags($v);
    // collapse CR/LF to spaces to mitigate header injection when echoed anywhere
    $v = str_replace(["\r", "\n"], ' ', $v);
    return mb_substr($v, 0, $max);
};

// Inputs
$name    = $sanitize(filter_input(INPUT_POST, 'name', FILTER_UNSAFE_RAW), 200);
$email   = $sanitize(filter_input(INPUT_POST, 'email', FILTER_UNSAFE_RAW), 200);
$address = $sanitize(filter_input(INPUT_POST, 'address', FILTER_UNSAFE_RAW), 500);
$phone   = $sanitize(filter_input(INPUT_POST, 'phone', FILTER_UNSAFE_RAW), 100);
$message = trim((string)filter_input(INPUT_POST, 'message', FILTER_UNSAFE_RAW)); // keep newlines for body

// Basic validations
if ($name === '' || $email === '' || $message === '') {
    http_response_code(400);
    echo "Please provide your name, a valid email, and a message.";
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo "Please provide a valid email address.";
    exit;
}
// Prevent header injection in Reply-To
$safeReplyTo = str_replace(["\r", "\n"], ' ', $email);

// Build email body with CRLF line endings
$body = "You have received a new message from your website contact form:\r\n\r\n"
      . "Name: " . $name . "\r\n"
      . "Email: " . $safeReplyTo . "\r\n"
      . "Address: " . $address . "\r\n"
      . "Phone: " . $phone . "\r\n"
      . "Message:\r\n" . str_replace(["\r\n", "\r", "\n"], "\r\n", trim($message)) . "\r\n";
$body = wordwrap($body, 70, "\r\n");

// Try PHPMailer SMTP first if available
$sent = false;
if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        $smtpHost = getenv('SMTP_HOST') ?: '';
        if ($smtpHost !== '') {
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->Port = (int)(getenv('SMTP_PORT') ?: 587);
            $secure = strtolower((string)(getenv('SMTP_SECURE') ?: 'tls'));
            if ($secure === 'ssl') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }
            $mail->SMTPAuth = true;
            $mail->Username = getenv('SMTP_USER') ?: '';
            $mail->Password = getenv('SMTP_PASS') ?: '';
        } else {
            // Use PHP's mail() transport through PHPMailer
            $mail->isMail();
        }

        $mail->CharSet = 'UTF-8';
        $mail->setFrom($from, $fromName);
        $mail->addAddress($to);
        $mail->addReplyTo($safeReplyTo, ($name !== '' ? $name : 'Contact Form'));
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = $body;

        $mail->send();
        $sent = true;
    } catch (Throwable $e) {
        error_log('PHPMailer send failed: ' . $e->getMessage());
        $sent = false;
    }
}

// Fallback to native mail()
if (!$sent) {
    $headers  = "From: {$from}\r\n";
    $headers .= "Reply-To: {$safeReplyTo}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    $sent = @mail($to, $subject, $body, $headers);
}

if ($sent) {
    echo "Thank you! Your message has been sent.";
} else {
    http_response_code(500);
    echo "Sorry, there was a problem sending your message. Please try again later.";
}