<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

$token = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = tylilo_admin_field('token');
} elseif (isset($_GET['token']) && is_string($_GET['token'])) {
    $token = trim($_GET['token']);
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $again = is_string($_POST['password_again'] ?? null) ? $_POST['password_again'] : '';
    $ip = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : '';
    $result = tylilo_admin_reset_submit($token, $password, $again, tylilo_admin_field('csrf'), $ip);
    if ($result['ok']) {
        tylilo_admin_end_session('');
        header('Location: /admin/?reset=1', true, 303);
        exit;
    }
    $error = $result['error'];
}

$preview = tylilo_admin_reset_preview($token);
if ($preview === null && $error === '') {
    $error = tylilo_admin_reset_invalid_error();
}

$html = '<div class="card">';
if (is_array($preview)) {
    $name = (string) $preview['user']['username'];
    $html .= '<h2>Choose a new password</h2>';
    if ($error !== '') {
        $html .= '<div class="form-status error" id="reset-error" role="alert"><p>' . tylilo_e($error) . '</p></div>';
    }
    $described = $error !== '' ? 'reset-error password-rules' : 'password-rules';
    $invalid = $error !== '' ? ' aria-invalid="true"' : '';
    $html .= '<p id="password-rules">Set a new password for <strong>' . tylilo_e($name) . '</strong>. Use at least 12 characters. Saving this signs out existing sessions for this account.</p>';
    $html .= '<form method="post" action="/admin/reset.php">';
    $html .= '<input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '">';
    $html .= '<input type="hidden" name="token" value="' . tylilo_e($token) . '" autocomplete="off">';
    $html .= '<label for="password">New password</label>';
    $html .= '<input id="password" name="password" type="password" required autocomplete="new-password" minlength="12" aria-describedby="' . $described . '"' . $invalid . '>';
    $html .= '<label for="password_again">New password again</label>';
    $html .= '<input id="password_again" name="password_again" type="password" required autocomplete="new-password" minlength="12" aria-describedby="' . $described . '"' . $invalid . '>';
    $html .= '<button class="btn send" type="submit">Save new password</button></form>';
} else {
    $html .= '<h2>Reset password</h2>';
    if ($error !== '') {
        $html .= '<div class="form-status error" id="reset-error" role="alert"><p>' . tylilo_e($error) . '</p></div>';
    }
}
$html .= '<p><a href="/admin/">Back to sign in</a></p></div>';
tylilo_admin_layout('Reset password', $html);
