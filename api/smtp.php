<?php
/**
 * Shared SMTP sender — used by submit-form.php and auth.php
 */

require_once __DIR__ . '/config.php';

function smtpSend($to, $subject, $htmlBody, $fromName, $fromEmail, $replyTo) {
    $socket = stream_socket_client(
        'ssl://' . SMTP_HOST . ':' . SMTP_PORT,
        $errno, $errstr, 10,
        STREAM_CLIENT_CONNECT,
        stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]])
    );
    if (!$socket) return false;

    $read = function() use ($socket) {
        $r = '';
        while ($line = fgets($socket, 512)) {
            $r .= $line;
            if ($line[3] === ' ') break;
        }
        return $r;
    };

    $send = function($cmd) use ($socket, $read) {
        fwrite($socket, $cmd . "\r\n");
        return $read();
    };

    $read(); // greeting
    $send('EHLO jallous-webdesign.de');
    $send('AUTH LOGIN');
    $send(base64_encode(SMTP_USER));
    $authResult = $send(base64_encode(SMTP_PASS));

    if (strpos($authResult, '235') === false) {
        fclose($socket);
        return false;
    }

    $send('MAIL FROM:<' . SMTP_USER . '>');
    $send('RCPT TO:<' . $to . '>');
    $send('DATA');

    $msgId = '<' . uniqid('', true) . '@jallous-webdesign.de>';
    $headers  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$replyTo}\r\n";
    $headers .= "To: {$to}\r\n";
    $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $headers .= "Date: " . date('r') . "\r\n";
    $headers .= "Message-ID: {$msgId}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: base64\r\n";
    $headers .= "\r\n";
    $headers .= chunk_split(base64_encode($htmlBody));

    $result = $send($headers . "\r\n.");
    $send('QUIT');
    fclose($socket);

    return strpos($result, '250') !== false;
}
