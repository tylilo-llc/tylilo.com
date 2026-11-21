<?php

declare(strict_types=1);

if (!defined('TYLILO_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

function tylilo_post_string(array $post, string $key): string
{
    $value = $post[$key] ?? '';
    return is_string($value) ? $value : '';
}

function tylilo_strlen(string $value): int
{
    $count = preg_match_all('/./u', $value);
    return $count === false ? strlen($value) : $count;
}

/**
 * @return array{spam: bool, errors: array<string, string>, clean: array{name: string, email: string, message: string}}
 */
function tylilo_validate_contact(array $post, string $expectedToken): array
{
    $name = str_replace(["\r", "\n", "\0"], '', trim(tylilo_post_string($post, 'name')));
    $email = str_replace(["\r", "\n", "\0"], '', trim(tylilo_post_string($post, 'email')));
    $message = str_replace("\0", '', tylilo_post_string($post, 'message'));
    $message = preg_replace("/\r\n?/", "\n", $message) ?? '';
    $message = preg_replace("/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F\\x7F]/", '', $message) ?? '';
    $message = trim($message);
    $honeypot = trim(tylilo_post_string($post, 'ty_extra'));
    $token = tylilo_post_string($post, 'csrf');

    $clean = [
        'name' => $name,
        'email' => $email,
        'message' => $message,
    ];

    if ($honeypot !== '') {
        return ['spam' => true, 'errors' => [], 'clean' => $clean];
    }

    $errors = [];

    if ($expectedToken === '' || !hash_equals($expectedToken, $token)) {
        $errors['form'] = 'We could not confirm that submission. Please try again, or use the email button above.';
    }

    if ($name === '') {
        $errors['name'] = 'Please enter your name.';
    } elseif (tylilo_strlen($name) > 100) {
        $errors['name'] = 'Name must be 100 characters or fewer.';
    }

    if ($email === '') {
        $errors['email'] = 'Please enter your email address.';
    } elseif (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'That email address does not look valid.';
    }

    $messageLength = tylilo_strlen($message);
    if ($message === '') {
        $errors['message'] = 'Please enter a message.';
    } elseif ($messageLength > 5000) {
        $errors['message'] = 'Message must be 5,000 characters or fewer.';
    }

    return ['spam' => false, 'errors' => $errors, 'clean' => $clean];
}

function tylilo_too_many_messages(): bool
{
    tylilo_session_start();
    $now = time();
    $hits = [];
    foreach ($_SESSION['contact_hits'] ?? [] as $hit) {
        if (is_int($hit) && ($now - $hit) < 1800) {
            $hits[] = $hit;
        }
    }
    $_SESSION['contact_hits'] = $hits;

    return count($hits) >= 5;
}

function tylilo_note_message_sent(): void
{
    tylilo_session_start();
    $hits = $_SESSION['contact_hits'] ?? [];
    $hits[] = time();
    $_SESSION['contact_hits'] = $hits;
}

function tylilo_send_contact(string $name, string $email, string $message): bool
{
    $to = str_replace(["\r", "\n", "\0"], '', TYLILO_CONTACT_EMAIL);
    if (filter_var($to, FILTER_VALIDATE_EMAIL) === false || !function_exists('mail')) {
        return false;
    }

    $headerName = trim(str_replace(['"', '<', '>', '\\'], '', $name));
    $headerName = preg_replace('/[^\x20-\x7E]/', '', $headerName) ?? '';
    $headerName = trim($headerName);
    if ($headerName === '') {
        $headerName = 'Website visitor';
    }

    $bodyMessage = str_replace("\n", "\r\n", $message);
    $body = "Name: {$name}\r\nEmail: {$email}\r\n\r\n{$bodyMessage}\r\n";
    $subject = 'New message from tylilo.com';
    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'From: Tylilo website <' . $to . '>',
        'Reply-To: "' . $headerName . '" <' . $email . '>',
    ]);

    $sent = @mail($to, $subject, $body, $headers, '-f' . $to);
    if ($sent) {
        return true;
    }

    return (bool) @mail($to, $subject, $body, $headers);
}
