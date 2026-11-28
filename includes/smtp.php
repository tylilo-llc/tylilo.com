<?php

declare(strict_types=1);

if (!defined('TYLILO_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

/**
 * Minimal authenticated SMTP sender for the consulting inquiry emails.
 * The password is never in this repository. It comes from
 * CONSULTING_SMTP_PASSWORD in the environment (tests) or from
 * consulting-smtp.php in the private data directory, written by deploy.
 */

const TYLILO_SMTP_HOST = 'mail.tylilo.com';
const TYLILO_SMTP_USER = 'consulting@tylilo.com';
const TYLILO_SMTP_FROM = 'consulting@tylilo.com';
const TYLILO_SMTP_FROM_NAME = 'Ryan Thorpe, Tylilo, LLC';
const TYLILO_SMTP_REPLY_TO = 'ryan@tylilo.com';

function tylilo_smtp_password(): string
{
    $env = getenv('CONSULTING_SMTP_PASSWORD');
    if (is_string($env) && $env !== '') {
        return $env;
    }

    return tylilo_private_php_value('consulting-smtp.php');
}

function tylilo_smtp_header_text(string $value): string
{
    $value = str_replace(["\r", "\n", "\0"], ' ', $value);
    if (preg_match('/[^\x20-\x7E]/', $value) === 1) {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    return $value;
}

/**
 * Build the full RFC 5322 message: multipart text and HTML.
 */
function tylilo_smtp_address(string $name, string $email): string
{
    $name = trim(str_replace(["\r", "\n", "\0", '"', '\\', '<', '>'], ' ', $name));
    $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
    if ($name === '') {
        return '<' . $email . '>';
    }
    if (preg_match('/[^\x20-\x7E]/', $name) === 1) {
        return tylilo_smtp_header_text($name) . ' <' . $email . '>';
    }

    return '"' . $name . '" <' . $email . '>';
}

function tylilo_smtp_message(string $to, string $subject, string $text, string $html, ?string $replyTo = null): string
{
    $boundary = 'tyl-' . bin2hex(random_bytes(12));
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $html = str_replace(["\r\n", "\r"], "\n", $html);
    $headers = [
        'Date: ' . date(DATE_RFC2822),
        'From: "' . TYLILO_SMTP_FROM_NAME . '" <' . TYLILO_SMTP_FROM . '>',
        'Reply-To: ' . ($replyTo !== null && $replyTo !== '' ? $replyTo : TYLILO_SMTP_REPLY_TO),
        'To: <' . $to . '>',
        'Subject: ' . tylilo_smtp_header_text($subject),
        'Message-ID: <' . bin2hex(random_bytes(16)) . '@tylilo.com>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];
    $body = '--' . $boundary . "\n"
        . "Content-Type: text/plain; charset=UTF-8\n"
        . "Content-Transfer-Encoding: quoted-printable\n\n"
        . quoted_printable_encode($text) . "\n"
        . '--' . $boundary . "\n"
        . "Content-Type: text/html; charset=UTF-8\n"
        . "Content-Transfer-Encoding: quoted-printable\n\n"
        . quoted_printable_encode($html) . "\n"
        . '--' . $boundary . "--\n";
    $message = implode("\n", $headers) . "\n\n" . $body;

    return str_replace("\n", "\r\n", str_replace("\r\n", "\n", $message));
}

/**
 * @param resource $socket
 */
function tylilo_smtp_read($socket): array
{
    $lines = '';
    $code = 0;
    while (($line = fgets($socket, 1024)) !== false) {
        $lines .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') {
            $code = (int) substr($line, 0, 3);
            break;
        }
    }

    return [$code, $lines];
}

/**
 * @param resource $socket
 */
function tylilo_smtp_cmd($socket, string $command, array $expect): bool
{
    if ($command !== '' && fwrite($socket, $command . "\r\n") === false) {
        return false;
    }
    [$code] = tylilo_smtp_read($socket);

    return in_array($code, $expect, true);
}

/**
 * Send one message. Returns false on any failure and logs a short reason
 * (never the password or the message body).
 */
function tylilo_smtp_send(string $to, string $subject, string $text, string $html, ?string $replyTo = null): bool
{
    $to = trim($to);
    if ($to === '' || preg_match('/[\r\n<>]/', $to) === 1 || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
        return false;
    }
    $message = tylilo_smtp_message($to, $subject, $text, $html, $replyTo);

    if (defined('TYLILO_SMTP_TEST_SINK')) {
        return file_put_contents((string) constant('TYLILO_SMTP_TEST_SINK'), "RCPT " . $to . "\r\n" . $message . "\r\n.\r\n", FILE_APPEND) !== false;
    }

    $password = tylilo_smtp_password();
    if ($password === '') {
        error_log('Inquiry mail: SMTP password is not configured.');

        return false;
    }

    $session = tylilo_smtp_open($password);
    if ($session === null) {
        return false;
    }
    [$socket, $address] = $session;
    $ok = tylilo_smtp_cmd($socket, 'MAIL FROM:<' . TYLILO_SMTP_FROM . '>', [250])
        && tylilo_smtp_cmd($socket, 'RCPT TO:<' . $to . '>', [250, 251])
        && tylilo_smtp_cmd($socket, 'DATA', [354]);
    if ($ok) {
        $data = preg_replace('/^\./m', '..', $message) ?? $message;
        $ok = fwrite($socket, $data . "\r\n.\r\n") !== false && tylilo_smtp_cmd($socket, '', [250]);
    }
    @fwrite($socket, "QUIT\r\n");
    fclose($socket);
    if (!$ok) {
        error_log('Inquiry mail: the SMTP conversation failed at ' . $address . '.');
    }

    return $ok;
}

/**
 * Connect and log in. Returns [socket, address] or null.
 * A connection that is refused moves on to the next port; a login that
 * fails does not, so a wrong password is never tried twice.
 *
 * @return array{0: resource, 1: string}|null
 */
function tylilo_smtp_open(string $password): ?array
{
    $attempts = [
        ['ssl://' . TYLILO_SMTP_HOST . ':465', false],
        ['tcp://' . TYLILO_SMTP_HOST . ':587', true],
    ];
    foreach ($attempts as [$address, $starttls]) {
        $context = stream_context_create(['ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => TYLILO_SMTP_HOST,
        ]]);
        $socket = @stream_socket_client($address, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) {
            error_log('Inquiry mail: could not connect to ' . $address . '.');
            continue;
        }
        stream_set_timeout($socket, 20);
        $ok = tylilo_smtp_cmd($socket, '', [220])
            && tylilo_smtp_cmd($socket, 'EHLO tylilo.com', [250]);
        if ($ok && $starttls) {
            $ok = tylilo_smtp_cmd($socket, 'STARTTLS', [220])
                && @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT) === true
                && tylilo_smtp_cmd($socket, 'EHLO tylilo.com', [250]);
        }
        $ok = $ok && tylilo_smtp_cmd($socket, 'AUTH PLAIN ' . base64_encode("\0" . TYLILO_SMTP_USER . "\0" . $password), [235]);
        if ($ok) {
            return [$socket, $address];
        }
        @fwrite($socket, "QUIT\r\n");
        fclose($socket);
        error_log('Inquiry mail: the SMTP login failed at ' . $address . '.');

        return null;
    }

    return null;
}

/**
 * Dry run for deploy checks: connect and log in, then quit without sending.
 */
function tylilo_smtp_check(): bool
{
    if (defined('TYLILO_SMTP_TEST_SINK')) {
        return true;
    }
    $password = tylilo_smtp_password();
    if ($password === '') {
        return false;
    }
    $session = tylilo_smtp_open($password);
    if ($session === null) {
        return false;
    }
    [$socket] = $session;
    $ok = tylilo_smtp_cmd($socket, 'NOOP', [250]);
    @fwrite($socket, "QUIT\r\n");
    fclose($socket);

    return $ok;
}
