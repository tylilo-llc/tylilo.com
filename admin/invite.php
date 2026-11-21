<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

$code = isset($_GET['code']) && is_string($_GET['code']) ? $_GET['code'] : tylilo_admin_field('code');
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!tylilo_admin_csrf_ok(tylilo_admin_field('csrf'))) {
        $error = 'That request was not accepted. Please try again.';
    } else {
        $username = tylilo_admin_field('username');
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $again = is_string($_POST['password_again'] ?? null) ? $_POST['password_again'] : '';
        $hash = hash('sha256', $code);
        $invite = null;
        $data = tylilo_admin_with(static fn (array $stored): array => $stored);
        foreach ($data['invites'] as $row) {
            if (hash_equals((string) $row['token_hash'], $hash)) {
                $invite = $row;
            }
        }
        $taken = false;
        foreach ($data['users'] as $user) {
            if (hash_equals((string) $user['username'], $username)) {
                $taken = true;
            }
        }
        if (!is_array($invite) || (int) $invite['revoked'] === 1 || (int) $invite['used_at'] > 0 || (int) $invite['expires_at'] < time()) {
            $error = 'This invite link is no longer valid.';
        } elseif (preg_match('/^[A-Za-z0-9-]{3,32}$/', $username) !== 1) {
            $error = 'Use 3–32 letters, numbers, or hyphens for the username.';
        } elseif ($taken) {
            $error = 'That username is already taken.';
        } elseif (strlen($password) < 12 || !hash_equals($password, $again)) {
            $error = 'Use a password of at least 12 characters and repeat it.';
        } else {
            $permissions = is_array($invite['permissions'] ?? null) ? $invite['permissions'] : tylilo_admin_default_permissions();
            $newId = 0;
            $created = false;
            tylilo_admin_with(static function (array $stored) use ($hash, $username, $password, $permissions, &$newId, &$created): array {
                $open = false;
                foreach ($stored['invites'] as &$row) {
                    if (hash_equals((string) $row['token_hash'], $hash) && (int) $row['used_at'] === 0 && (int) $row['revoked'] === 0 && (int) $row['expires_at'] >= time()) {
                        $row['used_at'] = time();
                        $open = true;
                    }
                }
                unset($row);
                if (!$open) {
                    return $stored;
                }
                $newId = tylilo_admin_next_id($stored['users']);
                $stored['users'][] = [
                    'id' => $newId,
                    'username' => $username,
                    'password_hash' => tylilo_admin_hash_password($password),
                    'role' => 'member',
                    'disabled' => 0,
                    'permissions' => $permissions,
                    'totp_secret' => '',
                    'totp_enabled' => 0,
                    'recovery_hashes' => '[]',
                    'created_at' => time(),
                ];
                $created = true;

                return $stored;
            });
            if (!$created) {
                $error = 'This invite link is no longer valid.';
            } else {
            tylilo_admin_log($newId, $username, 'join', 'Accepted an invite');
            session_regenerate_id(true);
            $_SESSION['admin_user_id'] = $newId;
            $_SESSION['admin_last_seen'] = time();
            $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
            header('Location: /admin/', true, 303);
            exit;
            }
        }
    }
}

$html = '<div class="card"><h2>Join the team</h2><p>Choose a username and a password of at least 12 characters. You can add an authenticator app later in Settings.</p>';
if ($error !== '') {
    $html .= '<div class="form-status error" role="alert"><p>' . tylilo_e($error) . '</p></div>';
}
$html .= '<form method="post" action="/admin/invite.php"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="code" value="' . tylilo_e($code) . '">';
$html .= '<label for="username">Username</label><input id="username" name="username" required autocomplete="username">';
$html .= '<label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="new-password">';
$html .= '<label for="password_again">Password again</label><input id="password_again" name="password_again" type="password" required autocomplete="new-password">';
$html .= '<button class="btn send" type="submit">Create account</button></form></div>';
tylilo_admin_layout('Join', $html);
