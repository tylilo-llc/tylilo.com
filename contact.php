<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/contact-lib.php';
require __DIR__ . '/includes/turnstile.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: /#contact', true, 303);
    exit;
}

tylilo_session_start();

$kept = [
    'name' => is_string($_POST['name'] ?? null) ? $_POST['name'] : '',
    'email' => is_string($_POST['email'] ?? null) ? $_POST['email'] : '',
    'message' => is_string($_POST['message'] ?? null) ? $_POST['message'] : '',
];
$turnstileToken = is_string($_POST['cf-turnstile-response'] ?? null) ? $_POST['cf-turnstile-response'] : '';
$remoteIp = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : '';
$turnstile = tylilo_turnstile_verify($turnstileToken, $remoteIp);
if (!$turnstile['ok']) {
    $_SESSION['contact_flash'] = [
        'success' => false,
        'errors' => ['form' => $turnstile['error']],
        'old' => $kept,
    ];
    header('Location: /#contact', true, 303);
    exit;
}

$expected = is_string($_SESSION['csrf'] ?? null) ? $_SESSION['csrf'] : '';
$result = tylilo_validate_contact($_POST, $expected);

$redirect = static function (array $flash): void {
    $_SESSION['contact_flash'] = $flash;
    header('Location: /#contact', true, 303);
    exit;
};

if ($result['spam']) {
    $redirect(['success' => true, 'errors' => [], 'old' => []]);
}

if ($result['errors'] !== []) {
    $redirect([
        'success' => false,
        'errors' => $result['errors'],
        'old' => $result['clean'],
    ]);
}

if (tylilo_too_many_messages()) {
    $redirect([
        'success' => false,
        'errors' => [
            'form' => 'Please wait a little while before sending another message.',
        ],
        'old' => $result['clean'],
    ]);
}

$sent = tylilo_send_contact(
    $result['clean']['name'],
    $result['clean']['email'],
    $result['clean']['message']
);

if (!$sent) {
    $redirect([
        'success' => false,
        'errors' => [
            'form' => 'The message could not be sent from the server. Please try again in a little while.',
        ],
        'old' => $result['clean'],
    ]);
}

tylilo_note_message_sent();
unset($_SESSION['csrf']);
$redirect(['success' => true, 'errors' => [], 'old' => []]);
