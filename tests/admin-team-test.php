<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$dir = sys_get_temp_dir() . '/tylilo-team-' . getmypid();
putenv('TYLILO_DATA_DIR=' . $dir);
putenv('TYLILO_ADMIN_STORE=json');
$_SERVER['SCRIPT_FILENAME'] = $root . '/tests/admin-team-test.php';
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

$defaults = tylilo_admin_default_permissions();
check($defaults['notes'] === 1 && $defaults['feedback'] === 1 && $defaults['ideas'] === 1 && $defaults['leaderboards'] === 0 && $defaults['beta'] === 0, 'new invites default to notes, feedback, and ideas');

tylilo_admin_with(static function (array $data): array {
    $data['users'][] = [
        'id' => 1,
        'username' => 'ryan',
        'password_hash' => 'hash',
        'role' => 'admin',
        'disabled' => 0,
        'permissions' => tylilo_admin_default_permissions(true),
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
        'disabled' => 0,
        'permissions' => tylilo_admin_default_permissions(),
        'totp_secret' => '',
        'totp_enabled' => 0,
        'recovery_hashes' => '[]',
        'created_at' => time(),
    ];
    $data['notes'][] = ['id' => 1, 'user_id' => 1, 'title' => 'Owner note', 'body' => 'private', 'tags' => '', 'created_at' => time(), 'updated_at' => time()];
    $data['notes'][] = ['id' => 2, 'user_id' => 2, 'title' => 'Sam note', 'body' => 'team', 'tags' => '', 'created_at' => time(), 'updated_at' => time()];
    $token = 'invite-token';
    $data['invites'][] = [
        'id' => 1,
        'token_hash' => hash('sha256', $token),
        'permissions' => tylilo_admin_default_permissions(),
        'expires_at' => time() + 100,
        'used_at' => 0,
        'revoked' => 0,
        'created_at' => time(),
    ];

    return $data;
});

$owner = tylilo_admin_user_by_name('ryan');
$member = tylilo_admin_user_by_name('sam');
check(is_array($owner) && tylilo_admin_is_owner($owner), 'an admin account is the owner');
check(is_array($member) && tylilo_admin_can($member, 'feedback') && !tylilo_admin_can($member, 'leaderboards') && !tylilo_admin_can($member, 'beta'), 'a member has the default permissions');
$expected = ['crash-cars' => ['Crash Cars', '2.3'], 'jumpy-jumperton' => ['Jumpy Jumperton', '1.4'], 'starbase-defender' => ['Starbase Defender', '1.5.0']];
foreach ($expected as $slug => [$title, $version]) {
    $game = tylilo_beta_game($slug);
    check(is_array($game) && $game['title'] === $title && $game['version'] === $version, $title . ' is a beta game at ' . $version);
    check(tylilo_beta_file($slug, 'index.html') !== null && tylilo_beta_file($slug, '../.htaccess') === null, $title . ' stays inside its own folder');
}
check(tylilo_beta_game('sample') === null, 'the sample beta game is gone');
check(is_array($owner) && tylilo_admin_can($owner, 'beta'), 'the owner can play beta games');
$arcadePage = (string) file_get_contents($root . '/arcade/index.php');
$sitemap = (string) file_get_contents($root . '/sitemap.xml');
check(!str_contains($arcadePage, '/beta/') && !str_contains($sitemap, '/beta/'), 'beta games are not on the Arcade or the sitemap');
check(is_array($member) && !tylilo_admin_is_owner($member), 'a member is not the owner');

$data = tylilo_admin_with(static fn (array $stored): array => $stored);
$ownerNotes = array_values(array_filter($data['notes'], static fn (array $note): bool => (int) $note['user_id'] === 1));
$memberNotes = array_values(array_filter($data['notes'], static fn (array $note): bool => (int) $note['user_id'] === 2));
check(count($ownerNotes) === 1 && $ownerNotes[0]['title'] === 'Owner note', 'owner notes stay on the owner');
check(count($memberNotes) === 1 && $memberNotes[0]['title'] === 'Sam note', 'team notes stay on that person');

$png = imagecreatetruecolor(2, 2);
$path = $dir . '/tiny.png';
imagepng($png, $path);
check(tylilo_admin_idea_image_extension($path, 100) === 'png', 'a small PNG is an allowed idea picture');
file_put_contents($dir . '/bad.txt', '<?php echo 1;');
check(tylilo_admin_idea_image_extension($dir . '/bad.txt', 12) === '', 'a text file is not an allowed idea picture');
check(tylilo_admin_idea_image_extension($path, 3000000) === '', 'a picture over 2 MB is rejected');

$version = tylilo_admin_current_game_version('pop-spiral');
check($version === '0.2.5', 'feedback can read the current Pop Spiral version');

$hash = tylilo_admin_hash_password('correct horse battery staple');
check(str_starts_with($hash, '$argon2id$') || str_starts_with($hash, '$2y$'), 'passwords use argon2id or bcrypt');

$_SESSION['admin_user_id'] = 2;
tylilo_admin_with(static function (array $data): array {
    foreach ($data['users'] as &$user) {
        if ((int) $user['id'] === 2) {
            $user['disabled'] = 1;
        }
    }

    return $data;
});
check(tylilo_admin_current() === null, 'a turned-off account is signed out');

$_SESSION['admin_user_id'] = 1;
$_SESSION['admin_last_seen'] = time() - 2000;
tylilo_admin_touch_session();
check(!isset($_SESSION['admin_user_id']), 'a signed-in person is signed out after 30 idle minutes');

echo $failed === 0 ? "\n{$checks} checks passed\n" : "\n{$failed} failed\n";
exit($failed === 0 ? 0 : 1);
