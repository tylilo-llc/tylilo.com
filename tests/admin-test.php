<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$dir = sys_get_temp_dir() . '/tylilo-admin-' . getmypid();
putenv('TYLILO_DATA_DIR=' . $dir);
putenv('TYLILO_ADMIN_STORE=json');
putenv('ADMIN_SETUP_CODE=correct-horse-battery');
putenv('TURNSTILE_SECRET=1x0000000000000000000000000000000AA');
$_SERVER['SCRIPT_FILENAME'] = $root . '/tests/admin-test.php';
require $root . '/includes/bootstrap.php';
require $root . '/includes/admin.php';
require $root . '/includes/admin-qr.php';

$failed = 0;
$checks = 0;
$token = tylilo_admin_csrf();
check(tylilo_admin_csrf_ok($token), 'the matching CSRF token is accepted');
check(!tylilo_admin_csrf_ok('not-the-token'), 'a wrong CSRF token is rejected');

function check(bool $ok, string $label): void
{
    global $failed, $checks;
    $checks++;
    echo ($ok ? 'ok  ' : 'not ok  ') . $label . "\n";
    if (!$ok) {
        $failed++;
    }
}

$secret = tylilo_admin_base32_decode('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');
check(tylilo_admin_hotp($secret, intdiv(59, 30)) === '287082', 'TOTP matches the RFC test vector');

$pass = tylilo_turnstile_verify('XXXX.DUMMY.TOKEN.XXXX', '127.0.0.1', 'login');
check($pass['ok'] === true, 'login Turnstile accepts the always-pass test key');
putenv('TURNSTILE_SECRET=2x0000000000000000000000000000000AA');
$fail = tylilo_turnstile_verify('XXXX.DUMMY.TOKEN.XXXX', '127.0.0.1', 'login');
check($fail['ok'] === false, 'login Turnstile rejects the always-fail test key');
putenv('TURNSTILE_SECRET=1x0000000000000000000000000000000AA');

check(str_contains(tylilo_admin_markdown("<script>alert(1)</script>\n\n**Bold**"), '&lt;script&gt;') && str_contains(tylilo_admin_markdown('**Bold**'), '<strong>Bold</strong>'), 'notes escape HTML and render bold text');

tylilo_admin_with(static function (array $data): array {
    $data['users'][] = [
        'id' => 1,
        'username' => 'ryan',
        'password_hash' => tylilo_admin_hash_password('correct-horse'),
        'role' => 'admin',
        'totp_secret' => '',
        'totp_enabled' => 0,
        'recovery_hashes' => '[]',
        'created_at' => time(),
    ];

    return $data;
});
check(tylilo_admin_has_admin(), 'an admin account can be stored');
check((tylilo_admin_user_by_name('ryan')['role'] ?? '') === 'admin', 'the user role column is admin');

$ip = tylilo_admin_ip_hash('203.0.113.8');
$now = time();
for ($i = 0; $i < 5; $i++) {
    tylilo_admin_note_attempt($ip, 'ryan', false, $now);
}
check(tylilo_admin_locked($ip, 'ryan', $now), 'five failed sign-ins lock the account');
check(!tylilo_admin_locked(tylilo_admin_ip_hash('203.0.113.9'), 'other', $now), 'a different person is not locked');

$sqliteDir = $dir . '-sqlite';
putenv('TYLILO_DATA_DIR=' . $sqliteDir);
putenv('TYLILO_ADMIN_STORE=sqlite');
tylilo_admin_with(static function (array $data): array {
    $data['users'][] = [
        'id' => 1,
        'username' => 'ryan',
        'password_hash' => 'hash',
        'role' => 'admin',
        'totp_secret' => '',
        'totp_enabled' => 0,
        'recovery_hashes' => '[]',
        'created_at' => time(),
    ];
    $data['users'][] = [
        'id' => 2,
        'username' => 'sam',
        'password_hash' => 'hash',
        'role' => 'member',
        'totp_secret' => '',
        'totp_enabled' => 0,
        'recovery_hashes' => '[]',
        'created_at' => time(),
    ];

    return $data;
});
$pdo = new PDO('sqlite:' . $sqliteDir . '/admin.sqlite');
$columns = array_column($pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC), 'name');
$roles = $pdo->query('SELECT role FROM users ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
check(in_array('role', $columns, true) && $roles === ['admin', 'member'], 'SQLite users table stores admin and member roles');

$setupDoc = $dir . '-doc/public_html';
$savedDataDir = getenv('TYLILO_DATA_DIR');
$savedHome = getenv('HOME');
putenv('ADMIN_SETUP_CODE');
putenv('TYLILO_DATA_DIR');
putenv('HOME=' . $dir . '-empty-home');
$_SERVER['HOME'] = $dir . '-empty-home';
$_SERVER['DOCUMENT_ROOT'] = $setupDoc;
if (!is_dir($setupDoc . '/data') && !mkdir($setupDoc . '/data', 0700, true) && !is_dir($setupDoc . '/data')) {
    throw new RuntimeException('Could not create the setup fixture.');
}
file_put_contents($setupDoc . '/data/admin-setup.php', "<?php\nreturn 'from-data';\n");
check(tylilo_admin_setup_code() === 'from-data', 'the setup code is read from data/ when that is the only copy');
if (!is_dir(dirname($setupDoc) . '/tylilo-data') && !mkdir(dirname($setupDoc) . '/tylilo-data', 0700, true) && !is_dir(dirname($setupDoc) . '/tylilo-data')) {
    throw new RuntimeException('Could not create the parent setup fixture.');
}
file_put_contents(dirname($setupDoc) . '/tylilo-data/admin-setup.php', "<?php\nreturn 'from-parent';\n");
check(tylilo_admin_setup_code() === 'from-parent', 'the setup code prefers the parent of the web root');
putenv('ADMIN_SETUP_CODE=correct-horse-battery');
if (is_string($savedDataDir) && $savedDataDir !== '') {
    putenv('TYLILO_DATA_DIR=' . $savedDataDir);
}
if (is_string($savedHome) && $savedHome !== '') {
    putenv('HOME=' . $savedHome);
    $_SERVER['HOME'] = $savedHome;
}

$svg = tylilo_admin_qr_svg('otpauth://totp/Tylilo:ryan?secret=GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ&issuer=Tylilo');
check(str_starts_with($svg, '<svg') && !str_contains($svg, 'chart.googleapis') && !str_contains($svg, 'api.qrserver'), 'the QR code is drawn locally');

echo $failed === 0 ? "\n{$checks} checks passed\n" : "\n{$failed} failed\n";
exit($failed === 0 ? 0 : 1);
