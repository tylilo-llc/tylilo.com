<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

$error = '';
$user = tylilo_admin_current();
$pending = $_SESSION['admin_pending_id'] ?? 0;
$pending = is_int($pending) ? $pending : 0;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!tylilo_admin_csrf_ok(tylilo_admin_field('csrf'))) {
        $error = 'That request was not accepted. Please try again.';
    } elseif (tylilo_admin_field('step') === 'totp') {
        $account = tylilo_admin_user_by_id($pending);
        $code = tylilo_admin_field('code');
        $secret = is_array($account) ? tylilo_admin_base32_decode((string) ($account['totp_secret'] ?? '')) : '';
        $accepted = is_array($account) && $secret !== '' && tylilo_admin_totp_ok($secret, $code, time());
        if (!$accepted && is_array($account)) {
            $hashes = json_decode((string) ($account['recovery_hashes'] ?? '[]'), true);
            $hashes = is_array($hashes) ? $hashes : [];
            $left = [];
            foreach ($hashes as $hash) {
                if (!$accepted && is_string($hash) && password_verify($code, $hash)) {
                    $accepted = true;
                    continue;
                }
                $left[] = $hash;
            }
            if ($accepted) {
                tylilo_admin_with(static function (array $data) use ($account, $left): array {
                    foreach ($data['users'] as &$row) {
                        if ((int) $row['id'] === (int) $account['id']) {
                            $row['recovery_hashes'] = json_encode(array_values($left));
                        }
                    }

                    return $data;
                });
            }
        }
            if ($accepted && is_array($account)) {
            tylilo_admin_begin_session($account);
            tylilo_admin_log((int) $account['id'], (string) $account['username'], 'sign-in', 'Completed the second step');
            header('Location: /admin/', true, 303);
            exit;
        }
        $error = 'That code was not accepted.';
    } else {
        $username = tylilo_admin_field('username');
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $ip = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : '';
        $ipHash = tylilo_admin_ip_hash($ip);
        $token = is_string($_POST['cf-turnstile-response'] ?? null) ? $_POST['cf-turnstile-response'] : '';
        $check = tylilo_turnstile_verify($token, $ip, 'login');
        if (tylilo_admin_locked($ipHash, $username, time())) {
            $error = 'Too many sign-in attempts. Please wait 15 minutes and try again.';
        } elseif (!$check['ok']) {
            $error = $check['error'];
        } else {
            $account = tylilo_admin_user_by_name($username);
            $ok = is_array($account) && password_verify($password, (string) $account['password_hash']);
            tylilo_admin_note_attempt($ipHash, $username, $ok, time());
            if (!$ok || !is_array($account)) {
                $error = 'That username or password was not accepted.';
            } elseif ((int) ($account['disabled'] ?? 0) === 1) {
                $error = 'This account is turned off.';
            } elseif ((int) $account['totp_enabled'] === 1) {
                session_regenerate_id(true);
                $_SESSION['admin_pending_id'] = (int) $account['id'];
                $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
                header('Location: /admin/', true, 303);
                exit;
            } else {
                tylilo_admin_begin_session($account);
                tylilo_admin_log((int) $account['id'], (string) $account['username'], 'sign-in', 'Signed in');
                header('Location: /admin/', true, 303);
                exit;
            }
        }
    }
}

$user = tylilo_admin_current();
if ($user !== null) {
    $html = '<div class="card"><h2>Hello, ' . tylilo_e((string) $user['username']) . '</h2>';
    $html .= '<p>Your role is ' . tylilo_e((string) $user['role']) . '.</p>';
    if (tylilo_admin_is_owner($user)) {
        $html .= '<p>Notes, the to-do list, users, and game versions are in the sections above.</p>';
    } else {
        $html .= '<p>Your notes, to-dos, feedback, and ideas are in the sections above.</p>';
    }
    $html .= '</div>';
    tylilo_admin_layout('Admin', $html);
    exit;
}

$sitekey = tylilo_turnstile_sitekey();
$html = '<div class="card">';
if (isset($_GET['out']) || (($_SESSION['admin_notice'] ?? '') === 'signed-out')) {
    unset($_SESSION['admin_notice']);
    $html .= '<div class="form-status" role="status"><p>You were signed out.</p></div>';
}
if (isset($_GET['ready'])) {
    $html .= '<div class="form-status success" role="status"><p>The admin account is ready. Sign in with the password you just chose.</p></div>';
}
if ($error !== '') {
    $html .= '<div class="form-status error" id="form-status" role="alert" aria-live="assertive"><p>' . tylilo_e($error) . '</p></div>';
}
if ($pending > 0) {
    $html .= '<h2>Two-step check</h2><form method="post" action="/admin/">';
    $html .= '<input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '">';
    $html .= '<input type="hidden" name="step" value="totp">';
    $html .= '<label for="code">Authentication or recovery code</label>';
    $html .= '<input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required>';
    $html .= '<button class="btn send" type="submit">Continue</button></form>';
} else {
    $html .= '<h2>Sign in</h2><form method="post" action="/admin/">';
    $html .= '<input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '">';
    $html .= '<label for="username">Username</label><input id="username" name="username" type="text" autocomplete="username" required>';
    $html .= '<label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required>';
    $html .= '<div class="turnstile-slot"><div class="cf-turnstile" data-sitekey="' . tylilo_e($sitekey) . '" data-action="login" data-theme="auto"></div></div>';
    $html .= '<button class="btn send" type="submit">Sign in</button></form>';
    if (!tylilo_admin_has_admin()) {
        $html .= '<p><a href="/admin/setup.php">First-time setup</a></p>';
    }
    $html .= '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
}
$html .= '</div>';
tylilo_admin_layout('Sign in', $html);
