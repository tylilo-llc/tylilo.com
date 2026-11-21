<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

if (tylilo_admin_has_admin()) {
    tylilo_admin_layout('Setup', '<div class="card"><h2>Setup is closed</h2><p>An admin account already exists. <a href="/admin/">Sign in</a>.</p></div>');
    exit;
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!tylilo_admin_csrf_ok(tylilo_admin_field('csrf'))) {
        $error = 'That request was not accepted. Please try again.';
    }
    $code = tylilo_admin_setup_code();
    $given = tylilo_admin_field('setup_code');
    $username = tylilo_admin_field('username');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $again = is_string($_POST['password_again'] ?? null) ? $_POST['password_again'] : '';
    $token = is_string($_POST['cf-turnstile-response'] ?? null) ? $_POST['cf-turnstile-response'] : '';
    $ip = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : '';
    $check = tylilo_turnstile_verify($token, $ip, 'login');
    if ($error !== '') {
        // Keep the message from the check above.
    } elseif ($code === '') {
        error_log('Admin setup code is not configured.');
        $error = 'Setup is not available right now.';
    } elseif (!$check['ok']) {
        $error = $check['error'];
    } elseif (!hash_equals(hash('sha256', $code), hash('sha256', $given))) {
        $error = 'That setup code was not accepted.';
    } elseif (preg_match('/^[A-Za-z0-9-]{3,32}$/', $username) !== 1) {
        $error = 'Use 3–32 letters, numbers, or hyphens for the username.';
    } elseif (strlen($password) < 12 || !hash_equals($password, $again)) {
        $error = 'Use a password of at least 12 characters and repeat it.';
    } else {
        tylilo_admin_with(static function (array $data) use ($username, $password): array {
            $data['users'][] = [
                'id' => tylilo_admin_next_id($data['users']),
                'username' => $username,
                'password_hash' => tylilo_admin_hash_password($password),
                'role' => 'admin',
                'totp_secret' => '',
                'totp_enabled' => 0,
                'recovery_hashes' => '[]',
                'created_at' => time(),
            ];

            return $data;
        });
        header('Location: /admin/?ready=1', true, 303);
        exit;
    }
}

$sitekey = tylilo_turnstile_sitekey();
$html = '<div class="card"><h2>Create the admin account</h2>';
$html .= '<p>This page works only until the first admin exists.</p>';
if ($error !== '') {
    $html .= '<div class="form-status error" role="alert" aria-live="assertive"><p>' . tylilo_e($error) . '</p></div>';
}
$html .= '<form method="post" action="/admin/setup.php">';
$html .= '<input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '">';
$html .= '<label for="setup_code">Setup code</label><input id="setup_code" name="setup_code" type="password" autocomplete="off" required>';
$html .= '<label for="username">Username</label><input id="username" name="username" type="text" autocomplete="username" required>';
$html .= '<label for="password">Password</label><input id="password" name="password" type="password" autocomplete="new-password" required>';
$html .= '<label for="password_again">Password again</label><input id="password_again" name="password_again" type="password" autocomplete="new-password" required>';
$html .= '<div class="turnstile-slot"><div class="cf-turnstile" data-sitekey="' . tylilo_e($sitekey) . '" data-action="login" data-theme="auto"></div></div>';
$html .= '<button class="btn send" type="submit">Create admin</button></form></div>';
$html .= '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
tylilo_admin_layout('Setup', $html);
