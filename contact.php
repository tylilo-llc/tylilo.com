<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/contact-lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: /#contact', true, 303);
    exit;
}

tylilo_session_start();
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
            'form' => 'Please wait a little while before sending another message. You can also use the email button above.',
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
            'form' => 'The message could not be sent from the server. Please use the email button above instead.',
        ],
        'old' => $result['clean'],
    ]);
}

tylilo_note_message_sent();
unset($_SESSION['csrf']);
$redirect(['success' => true, 'errors' => [], 'old' => []]);
