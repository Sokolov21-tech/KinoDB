<?php
 
 
 
 

require_once __DIR__ . '/config.php';

function sendMail(string $to, string $subject, string $textBody, string $htmlBody = ''): bool {
    if (MAIL_DEBUG) {
        return mailLog($to, $subject, $textBody);
    }
    return mailSmtp($to, $subject, $textBody, $htmlBody);
}

 
 
 

function mailLog(string $to, string $subject, string $body): bool {
    $dir  = LOG_PATH;
    if (!is_dir($dir)) mkdir($dir, 0750, true);

    $entry = implode("\n", [
        str_repeat('=', 60),
        'To:      ' . $to,
        'Subject: ' . $subject,
        'Date:    ' . date('Y-m-d H:i:s'),
        '',
        $body,
        '',
    ]);
    file_put_contents($dir . 'mail.log', $entry, FILE_APPEND | LOCK_EX);
    return true;
}

 
 
 

function mailSmtp(string $to, string $subject, string $text, string $html = ''): bool {
    $fp = @fsockopen(
        (MAIL_USE_TLS ? 'ssl://' : '') . MAIL_HOST,
        MAIL_PORT,
        $errno, $errstr, 10
    );
    if (!$fp) {
        error_log("[Mailer] Connect failed: $errstr ($errno)");
        return mailLog($to, $subject, $text);
    }

    $boundary = bin2hex(random_bytes(8));
    $lines    = [];

    $smtpRead  = fn() => fgets($fp, 515);
    $smtpWrite = function(string $cmd) use ($fp) { fwrite($fp, $cmd . "\r\n"); };

    $smtpRead();  
    $smtpWrite('EHLO ' . gethostname());
    while (($line = $smtpRead()) && substr($line, 3, 1) !== ' ') {}

    if (MAIL_USE_TLS && MAIL_PORT === 587) {
        $smtpWrite('STARTTLS');
        $smtpRead();
        stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $smtpWrite('EHLO ' . gethostname());
        while (($line = $smtpRead()) && substr($line, 3, 1) !== ' ') {}
    }

    $smtpWrite('AUTH LOGIN');
    $smtpRead();
    $smtpWrite(base64_encode(MAIL_USERNAME));
    $smtpRead();
    $smtpWrite(base64_encode(MAIL_PASSWORD));
    $resp = $smtpRead();

    if (substr($resp, 0, 3) !== '235') {
        fclose($fp);
        error_log('[Mailer] AUTH failed: ' . $resp);
        return false;
    }

    $smtpWrite('MAIL FROM:<' . MAIL_FROM_EMAIL . '>');
    $smtpRead();
    $smtpWrite("RCPT TO:<$to>");
    $smtpRead();
    $smtpWrite('DATA');
    $smtpRead();

    $headers  = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_EMAIL . ">\r\n";
    $headers .= "To: $to\r\n";
    $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $headers .= "MIME-Version: 1.0\r\n";

    if ($html) {
        $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
        $body     = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n$text\r\n";
        $body    .= "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n$html\r\n";
        $body    .= "--$boundary--\r\n";
    } else {
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body     = $text . "\r\n";
    }

    $smtpWrite($headers . "\r\n" . $body . "\r\n.");
    $resp = $smtpRead();
    $smtpWrite('QUIT');
    fclose($fp);

    return substr($resp, 0, 3) === '250';
}
