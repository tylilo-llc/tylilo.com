<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$dir = sys_get_temp_dir() . '/tylilo-session-' . getmypid();
putenv('TYLILO_DATA_DIR=' . $dir);
putenv('TYLILO_ADMIN_STORE=json');
$_SERVER['SCRIPT_FILENAME'] = $root . '/tests/admin-session-test.php';
$_SERVER['DOCUMENT_ROOT'] = $root;
require $root . '/includes/bootstrap.php';
require $root . '/includes/admin.php';
tylilo_session_start();

$failed = 0;
$checks = 0;

function check(bool $ok, string $label): void
{
    global $failed, $checks;
    $checks++;
    echo ($ok ? 'ok  ' : 'not ok  ') . $label . "\n";
    if (!$ok) {
        $failed++;
    }
}

tylilo_admin_with(static function (array $data): array {
    $data['users'][] = [
        'id' => 1,
        'username' => 'ryan',
        'password_hash' => 'hash',
        'role' => 'owner',
        'disabled' => 0,
        'permissions' => [],
        'totp_secret' => '',
        'totp_enabled' => 0,
        'recovery_hashes' => '[]',
        'created_at' => time(),
        'session_version' => 3,
    ];

    return $data;
});

$_SESSION['admin_user_id'] = 1;
$_SESSION['admin_last_seen'] = time();
$_SESSION['admin_login_at'] = time() - 100;
$_SESSION['admin_session_version'] = 3;
tylilo_admin_touch_session();
check(($_SESSION['admin_user_id'] ?? 0) === 1, 'a fresh sign-in stays signed in');

$_SESSION['admin_login_at'] = time() - 43201;
$_SESSION['admin_last_seen'] = time();
tylilo_admin_touch_session();
check(!isset($_SESSION['admin_user_id']) && ($_SESSION['admin_notice'] ?? '') === 'signed-out', 'a sign-in older than 12 hours is signed out');

$_SESSION['admin_user_id'] = 1;
$_SESSION['admin_last_seen'] = time() - 2000;
$_SESSION['admin_login_at'] = time() - 100;
$_SESSION['admin_session_version'] = 3;
unset($_SESSION['admin_notice']);
tylilo_admin_touch_session();
check(!isset($_SESSION['admin_user_id']), '30 idle minutes still ends the session');

$_SESSION['admin_user_id'] = 1;
$_SESSION['admin_last_seen'] = time();
$_SESSION['admin_login_at'] = time();
$_SESSION['admin_session_version'] = 2;
check(tylilo_admin_current() === null, 'an old session version is rejected');

$user = ['session_version' => 4];
tylilo_admin_bump_session($user);
check($user['session_version'] === 5, 'signing out everywhere moves the session version');

$_SESSION['admin_csrf'] = 'token-token-token-token-token-token';
check(tylilo_admin_csrf_ok('nope') === false, 'keepalive still requires the matching token');

echo $failed === 0 ? "\n{$checks} checks passed\n" : "\n{$failed} failed\n";
exit($failed === 0 ? 0 : 1);
