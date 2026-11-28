<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$dir = sys_get_temp_dir() . '/tylilo-reset-' . getmypid();
putenv('TYLILO_DATA_DIR=' . $dir);
putenv('TYLILO_ADMIN_STORE=json');
$_SERVER['SCRIPT_FILENAME'] = $root . '/tests/admin-reset-test.php';
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

function reset_count(): int
{
    $data = tylilo_admin_with(static fn (array $stored): array => $stored);

    return count(tylilo_admin_reset_rows($data));
}

$login = (string) file_get_contents($root . '/admin/index.php');
$forgot = (string) file_get_contents($root . '/admin/forgot.php');
$resetPage = (string) file_get_contents($root . '/admin/reset.php');
$usersPage = (string) file_get_contents($root . '/admin/users.php');
$privacy = (string) file_get_contents($root . '/privacy.php');
$linkAt = strpos($login, 'Forgot password?');
$fieldAt = strpos($login, 'id="password"');
check($linkAt !== false && $fieldAt !== false && $linkAt < $fieldAt, 'the sign-in page links Forgot password? beside the password field');
check(str_contains($login, '/admin/forgot.php') && str_contains($login, "isset(\$_GET['reset'])"), 'sign-in offers the help page and a password-changed message');
check(str_contains($forgot, 'Ask the site owner for a password reset link.'), 'the help page tells the person to ask the owner');
check(!str_contains($forgot, 'tylilo_admin_user_by_name') && !str_contains($forgot, 'name="username"'), 'the help page does not look up or ask for a username');
check(substr_count($resetPage, 'autocomplete="new-password"') === 2, 'the reset form uses autocomplete new-password on both fields');
check(str_contains($resetPage, 'role="alert"') && str_contains($resetPage, '<label for="password">'), 'reset errors are announced and the password fields are labelled');
check(str_contains($resetPage, 'tylilo_admin_reset_invalid_error'), 'invalid reset links share one error');
$guardAt = strpos($usersPage, 'tylilo_admin_is_owner');
$actionAt = strpos($usersPage, "\$action === 'reset'");
check($guardAt !== false && $actionAt !== false && $guardAt < $actionAt, 'the users page checks the owner before it can issue a reset');
check(str_contains($usersPage, 'Reset password<span class="visually-hidden"> for '), 'each reset button names the account');
check(str_contains($privacy, 'November 28, 2026') && str_contains($privacy, 'a hash of that link') && str_contains($privacy, '24 hours'), 'privacy describes hashed reset links that expire after 24 hours');

$samHash = tylilo_admin_hash_password('old-sam-password');
$totp = 'JBSWY3DPEHPK3PXP';
$recovery = '["recovery-hash"]';
tylilo_admin_with(static function (array $data) use ($samHash, $totp, $recovery): array {
    $data['users'][] = [
        'id' => 1,
        'username' => 'ryan',
        'password_hash' => 'hash-ryan',
        'role' => 'owner',
        'disabled' => 0,
        'permissions' => tylilo_admin_default_permissions(true),
        'totp_secret' => '',
        'totp_enabled' => 0,
        'recovery_hashes' => '[]',
        'created_at' => time(),
        'session_version' => 1,
    ];
    $data['users'][] = [
        'id' => 2,
        'username' => 'sam',
        'password_hash' => $samHash,
        'role' => 'member',
        'disabled' => 0,
        'permissions' => tylilo_admin_default_permissions(),
        'totp_secret' => $totp,
        'totp_enabled' => 1,
        'recovery_hashes' => $recovery,
        'created_at' => time(),
        'session_version' => 1,
    ];

    return $data;
});

$before = reset_count();
check(tylilo_admin_issue_reset(2, 1) === null, 'a team member cannot issue a reset link for the owner');
check(tylilo_admin_issue_reset(2, 2) === null, 'a team member cannot issue a reset link for themselves');
check(tylilo_admin_issue_reset(9, 2) === null, 'an unknown account cannot issue a reset link');
check(reset_count() === $before, 'a rejected reset does not store a link');

$token = tylilo_admin_issue_reset(1, 2);
check(is_string($token) && strlen($token) === 64 && ctype_xdigit($token), 'the owner gets a 32-byte token');
$stored = tylilo_admin_with(static fn (array $data): array => $data);
$hashes = array_map(static fn (array $row): string => (string) ($row['token_hash'] ?? ''), tylilo_admin_reset_rows($stored));
check($token !== null && in_array(hash('sha256', $token), $hashes, true), 'the store keeps a hash of the token');
$raw = (string) file_get_contents($dir . '/admin.json');
check($token !== null && !str_contains($raw, $token), 'the raw token is not written to the JSON store');
$logged = false;
foreach ($stored['activity'] as $row) {
    if (($row['action'] ?? '') === 'password-reset' && str_contains((string) ($row['detail'] ?? ''), 'sam') && !str_contains((string) ($row['detail'] ?? ''), (string) $token)) {
        $logged = true;
    }
}
check($logged, 'creating a link is written to the activity log without the token');

$saved = tylilo_admin_apply_reset((string) $token, 'brand-new-secret', 'brand-new-secret');
check($saved['ok'] === true, 'a valid reset sets the new password');
$sam = tylilo_admin_user_by_name('sam');
check(is_array($sam) && password_verify('brand-new-secret', (string) $sam['password_hash']), 'the new password matches the stored hash');
check(is_array($sam) && !password_verify('old-sam-password', (string) $sam['password_hash']), 'the old password no longer matches');
check(is_array($sam) && $sam['totp_secret'] === $totp && (int) $sam['totp_enabled'] === 1 && $sam['recovery_hashes'] === $recovery, 'TOTP and recovery codes stay as they were');
check(is_array($sam) && (int) $sam['session_version'] === 2, 'a reset moves the session version');
$usedRow = null;
foreach (tylilo_admin_reset_rows(tylilo_admin_with(static fn (array $data): array => $data)) as $row) {
    if (hash_equals((string) $row['token_hash'], hash('sha256', (string) $token))) {
        $usedRow = $row;
    }
}
check(is_array($usedRow) && (int) $usedRow['used_at'] > 0, 'a used token is marked used');

$_SESSION['admin_user_id'] = 2;
$_SESSION['admin_session_version'] = 1;
$_SESSION['admin_last_seen'] = time();
$_SESSION['admin_login_at'] = time();
unset($_SESSION['admin_notice']);
check(tylilo_admin_current() === null, 'old sessions are invalidated');
$fresh = tylilo_admin_user_by_name('sam');
$_SESSION['admin_user_id'] = 2;
$_SESSION['admin_session_version'] = (int) ($fresh['session_version'] ?? 0);
$_SESSION['admin_last_seen'] = time();
$_SESSION['admin_login_at'] = time();
unset($_SESSION['admin_notice']);
$signedIn = tylilo_admin_current();
check(is_array($signedIn) && (int) $signedIn['id'] === 2, 'a session at the new version is still accepted');

$usedAgain = tylilo_admin_apply_reset((string) $token, 'brand-new-secret-2', 'brand-new-secret-2');
check($usedAgain['ok'] === false && $usedAgain['error'] === tylilo_admin_reset_invalid_error(), 'a used token fails');
$sam = tylilo_admin_user_by_name('sam');
check(is_array($sam) && password_verify('brand-new-secret', (string) $sam['password_hash']), 'a used token does not change the password again');

$expiredToken = tylilo_admin_issue_reset(1, 2);
tylilo_admin_with(static function (array $data) use ($expiredToken): array {
    foreach ($data['password_resets'] as &$row) {
        if (is_string($expiredToken) && hash_equals((string) ($row['token_hash'] ?? ''), hash('sha256', $expiredToken))) {
            $row['expires_at'] = time() - 10;
        }
    }
    unset($row);

    return $data;
});
$expired = tylilo_admin_apply_reset((string) $expiredToken, 'brand-new-secret-3', 'brand-new-secret-3');
check($expired['ok'] === false && $expired['error'] === tylilo_admin_reset_invalid_error(), 'an expired token fails');

$wrong = tylilo_admin_apply_reset(bin2hex(random_bytes(32)), 'brand-new-secret-3', 'brand-new-secret-3');
check($wrong['ok'] === false && $wrong['error'] === tylilo_admin_reset_invalid_error(), 'a wrong token fails');
check($expired['error'] === $usedAgain['error'] && $wrong['error'] === $usedAgain['error'], 'expired, used, and wrong tokens share one error');

$ownerToken = tylilo_admin_issue_reset(1, 1);
$first = tylilo_admin_issue_reset(1, 2);
$second = tylilo_admin_issue_reset(1, 2);
$old = tylilo_admin_apply_reset((string) $first, 'replaced-link-pass', 'replaced-link-pass');
check($old['ok'] === false && $old['error'] === tylilo_admin_reset_invalid_error(), 'issuing a new link invalidates the old one');
$ownerStill = tylilo_admin_apply_reset((string) $ownerToken, 'owner-new-password', 'owner-new-password');
check($ownerStill['ok'] === true, 'a new link for one person leaves another person\'s link working');
$ryan = tylilo_admin_user_by_name('ryan');
check(is_array($ryan) && password_verify('owner-new-password', (string) $ryan['password_hash']) && (int) $ryan['session_version'] === 2, 'the owner can reset their own account');
$newest = tylilo_admin_apply_reset((string) $second, 'sam-after-replace', 'sam-after-replace');
check($newest['ok'] === true, 'the newest link still works');

$kept = tylilo_admin_issue_reset(1, 2);
$weak = tylilo_admin_apply_reset((string) $kept, 'short', 'short');
$mismatch = tylilo_admin_apply_reset((string) $kept, 'long-enough-password', 'different-password');
check($weak['status'] === 'weak' && $mismatch['status'] === 'weak', 'a short or mismatched password is rejected');
$still = tylilo_admin_apply_reset((string) $kept, 'long-enough-password', 'long-enough-password');
check($still['ok'] === true, 'a rejected password does not use up the link');

$disabledToken = tylilo_admin_issue_reset(1, 2);
tylilo_admin_with(static function (array $data): array {
    foreach ($data['users'] as &$user) {
        if ((int) $user['id'] === 2) {
            $user['disabled'] = 1;
        }
    }
    unset($user);

    return $data;
});
$disabled = tylilo_admin_apply_reset((string) $disabledToken, 'should-not-save-ok', 'should-not-save-ok');
check($disabled['ok'] === false && $disabled['error'] === tylilo_admin_reset_invalid_error(), 'a disabled account cannot use a reset link');
tylilo_admin_with(static function (array $data): array {
    foreach ($data['users'] as &$user) {
        if ((int) $user['id'] === 2) {
            $user['disabled'] = 0;
        }
    }
    unset($user);

    return $data;
});
$sam = tylilo_admin_user_by_name('sam');
check(is_array($sam) && password_verify('long-enough-password', (string) $sam['password_hash']), 'a disabled account keeps its current password');

$csrf = tylilo_admin_csrf();
$csrfToken = tylilo_admin_issue_reset(1, 2);
$badCsrf = tylilo_admin_reset_submit((string) $csrfToken, 'csrf-safe-password', 'csrf-safe-password', 'not-the-token', '203.0.113.40');
check($badCsrf['status'] === 'csrf' && $badCsrf['ok'] === false, 'a reset without the matching CSRF token is rejected');
$goodCsrf = tylilo_admin_reset_submit((string) $csrfToken, 'csrf-safe-password', 'csrf-safe-password', $csrf, '203.0.113.41');
check($goodCsrf['ok'] === true, 'a reset with the matching CSRF token is saved');

$limitToken = tylilo_admin_issue_reset(1, 2);
$now = time();
$limitIp = '203.0.113.80';
$locked = false;
for ($i = 0; $i < 5; $i++) {
    $attempt = tylilo_admin_reset_submit((string) $limitToken, 'short', 'short', $csrf, $limitIp, $now);
    if ($attempt['status'] !== 'weak') {
        $locked = true;
    }
}
check(!$locked, 'the first five bad passwords are still the password error');
$blocked = tylilo_admin_reset_submit((string) $limitToken, 'after-lock-password', 'after-lock-password', $csrf, $limitIp, $now);
check($blocked['status'] === 'locked', 'the reset form locks after repeated attempts');
$sam = tylilo_admin_user_by_name('sam');
check(is_array($sam) && password_verify('csrf-safe-password', (string) $sam['password_hash']), 'a locked reset does not change the password');

$sqliteDir = $dir . '-sqlite';
putenv('TYLILO_DATA_DIR=' . $sqliteDir);
putenv('TYLILO_ADMIN_STORE=sqlite');
tylilo_admin_with(static function (array $data) use ($samHash, $totp, $recovery): array {
    $data['users'][] = [
        'id' => 1,
        'username' => 'ryan',
        'password_hash' => 'hash-ryan',
        'role' => 'owner',
        'disabled' => 0,
        'permissions' => tylilo_admin_default_permissions(true),
        'totp_secret' => '',
        'totp_enabled' => 0,
        'recovery_hashes' => '[]',
        'created_at' => time(),
        'session_version' => 1,
    ];
    $data['users'][] = [
        'id' => 2,
        'username' => 'sam',
        'password_hash' => $samHash,
        'role' => 'member',
        'disabled' => 0,
        'permissions' => tylilo_admin_default_permissions(),
        'totp_secret' => $totp,
        'totp_enabled' => 1,
        'recovery_hashes' => $recovery,
        'created_at' => time(),
        'session_version' => 1,
    ];

    return $data;
});
$pdo = new PDO('sqlite:' . $sqliteDir . '/admin.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$legacy = json_decode((string) $pdo->query('SELECT json FROM admin_state WHERE id = 1')->fetchColumn(), true);
unset($legacy['password_resets']);
$update = $pdo->prepare('UPDATE admin_state SET json = ? WHERE id = 1');
$update->execute([json_encode($legacy)]);
$sqliteToken = tylilo_admin_issue_reset(1, 2);
$sqliteSaved = tylilo_admin_apply_reset((string) $sqliteToken, 'sqlite-password-ok', 'sqlite-password-ok');
check($sqliteSaved['ok'] === true, 'a reset works when the SQLite document has no reset list yet');
$columns = array_column($pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC), 'name');
$rows = $pdo->query('SELECT id, username, password_hash, totp_secret, totp_enabled, recovery_hashes, session_version FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
check($columns === ['id', 'username', 'password_hash', 'role', 'disabled', 'permissions', 'totp_secret', 'totp_enabled', 'recovery_hashes', 'created_at', 'session_version'], 'the users table keeps its columns');
check(count($rows) === 2 && $rows[0]['username'] === 'ryan' && $rows[0]['password_hash'] === 'hash-ryan', 'an existing SQLite user is left in place');
check($rows[1]['username'] === 'sam' && password_verify('sqlite-password-ok', (string) $rows[1]['password_hash']), 'the SQLite user gets the new password hash');
check((int) $rows[1]['session_version'] === 2 && $rows[1]['totp_secret'] === $totp && (int) $rows[1]['totp_enabled'] === 1 && $rows[1]['recovery_hashes'] === $recovery, 'SQLite keeps TOTP data and bumps the session');
$blob = (string) file_get_contents($sqliteDir . '/admin.sqlite');
check($sqliteToken !== null && !str_contains($blob, $sqliteToken), 'the raw token is not written to the SQLite file');
$state = json_decode((string) $pdo->query('SELECT json FROM admin_state WHERE id = 1')->fetchColumn(), true);
$stateHashes = array_map(static fn (array $row): string => (string) ($row['token_hash'] ?? ''), tylilo_admin_reset_rows(is_array($state) ? $state : []));
check($sqliteToken !== null && in_array(hash('sha256', $sqliteToken), $stateHashes, true), 'the SQLite document stores the reset hash');

echo $failed === 0 ? "\n{$checks} checks passed\n" : "\n{$failed} failed\n";
exit($failed === 0 ? 0 : 1);
