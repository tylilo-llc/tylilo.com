<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';
require dirname(__DIR__) . '/includes/admin-qr.php';

$user = tylilo_admin_guard();
$error = '';
$recovery = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!tylilo_admin_csrf_ok(tylilo_admin_field('csrf'))) {
        $error = 'That request was not accepted. Please try again.';
    } elseif (tylilo_admin_field('action') === 'start') {
        $_SESSION['admin_totp_pending'] = tylilo_admin_base32(random_bytes(20));
    } elseif (tylilo_admin_field('action') === 'confirm') {
        $secret = is_string($_SESSION['admin_totp_pending'] ?? null) ? $_SESSION['admin_totp_pending'] : '';
        $binary = $secret !== '' ? tylilo_admin_base32_decode($secret) : '';
        if ($binary === '' || !tylilo_admin_totp_ok($binary, tylilo_admin_field('code'), time())) {
            $error = 'That code did not match. Check the clock and try again.';
        } else {
            $codes = [];
            $hashes = [];
            for ($i = 0; $i < 8; $i++) {
                $code = bin2hex(random_bytes(5));
                $codes[] = $code;
                $hashes[] = password_hash($code, PASSWORD_DEFAULT);
            }
            tylilo_admin_with(static function (array $data) use ($user, $secret, $hashes): array {
                foreach ($data['users'] as &$row) {
                    if ((int) $row['id'] === (int) $user['id']) {
                        $row['totp_secret'] = $secret;
                        $row['totp_enabled'] = 1;
                        $row['recovery_hashes'] = json_encode($hashes);
                    }
                }

                return $data;
            });
            unset($_SESSION['admin_totp_pending']);
            $_SESSION['admin_recovery_once'] = $codes;
            header('Location: /admin/settings.php', true, 303);
            exit;
        }
    }
}

if (isset($_SESSION['admin_recovery_once']) && is_array($_SESSION['admin_recovery_once'])) {
    $recovery = $_SESSION['admin_recovery_once'];
    unset($_SESSION['admin_recovery_once']);
}
$user = tylilo_admin_user_by_id((int) $user['id']) ?? $user;
$pending = is_string($_SESSION['admin_totp_pending'] ?? null) ? $_SESSION['admin_totp_pending'] : '';
$html = '<div class="card"><h2>Two-step sign-in</h2>';
if ($error !== '') {
    $html .= '<div class="form-status error" role="alert"><p>' . tylilo_e($error) . '</p></div>';
}
if ($recovery !== []) {
    $html .= '<div class="form-status success" role="status"><p>Save these recovery codes now. Each one works once.</p><ul>';
    foreach ($recovery as $code) {
        $html .= '<li><code>' . tylilo_e((string) $code) . '</code></li>';
    }
    $html .= '</ul></div>';
}
if ((int) ($user['totp_enabled'] ?? 0) === 1) {
    $html .= '<p>Two-step sign-in is on.</p>';
} elseif ($pending !== '') {
    $uri = 'otpauth://totp/Tylilo:' . rawurlencode((string) $user['username']) . '?secret=' . rawurlencode($pending) . '&issuer=Tylilo&digits=6&period=30';
    $html .= '<p>Scan this code in an authenticator app, or enter the key by hand.</p>';
    $html .= '<div class="admin-qr">' . tylilo_admin_qr_svg($uri) . '</div>';
    $html .= '<p><code>' . tylilo_e($pending) . '</code></p>';
    $html .= '<form method="post" action="/admin/settings.php"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="confirm">';
    $html .= '<label for="code">6-digit code</label><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required>';
    $html .= '<button class="btn send" type="submit">Turn on two-step sign-in</button></form>';
} else {
    $html .= '<form method="post" action="/admin/settings.php"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="start">';
    $html .= '<button class="btn send" type="submit">Set up two-step sign-in</button></form>';
}
$html .= '</div>';
tylilo_admin_layout('Settings', $html);
