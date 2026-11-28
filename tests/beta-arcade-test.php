<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$dir = sys_get_temp_dir() . '/tylilo-beta-arcade-' . getmypid();
if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
    fwrite(STDERR, "could not create test data dir\n");
    exit(1);
}
putenv('TYLILO_DATA_DIR=' . $dir);
putenv('TYLILO_ADMIN_STORE=json');
putenv('ADMIN_SETUP_CODE=correct-horse-battery');
putenv('TURNSTILE_SECRET=1x0000000000000000000000000000000AA');
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
$_SERVER['REQUEST_URI'] = '/admin/beta/';
require $root . '/includes/bootstrap.php';
require $root . '/includes/admin.php';
require_once $root . '/includes/beta-arcade.php';

$token = tylilo_admin_csrf();
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

$defaults = tylilo_beta_visibility();
check(!empty($defaults['crash-cars']), 'Crash Cars is visible by default');
check(empty($defaults['jumpy-jumperton']) && empty($defaults['starbase-defender']), 'other beta games are hidden by default');

$early = tylilo_arcade_visible_early_games();
check(count($early) === 1 && $early[0]['slug'] === 'crash-cars' && $early[0]['badge'] === 'Beta' && $early[0]['href'] === '/arcade/crash-cars/', 'the Arcade section lists only Crash Cars by default');

$path = tylilo_beta_arcade_public_file('crash-cars');
check(is_string($path) && is_file($path) && md5_file($path) === '4f66cf2f558c80a3ed010c1e41d0b6dd', 'the public Crash Cars file is the unchanged 2.4 build');
check(tylilo_beta_arcade_public_file('solitaire') === null, 'released Arcade games are not served through the beta gate file helper');

$htaccess = (string) file_get_contents($root . '/.htaccess');
$router = (string) file_get_contents($root . '/router.php');
$expected = tylilo_arcade_released_slugs();
check(preg_match('/RewriteCond %\{REQUEST_URI\} !\^\/arcade\/\(([^)]+)\)/', $htaccess, $match) === 1, 'htaccess excludes released Arcade slugs from the beta gate');
$fromHtaccess = $match[1] ?? '';
check($fromHtaccess === implode('|', $expected), 'the htaccess exclusion list matches tylilo_arcade_released_slugs()');
check(str_contains($router, "['" . implode("', '", $expected) . "']"), 'router.php excludes the same released slugs');

$owner = [
    'id' => 1,
    'username' => 'ryan',
    'role' => 'owner',
    'disabled' => 0,
    'permissions' => tylilo_admin_default_permissions(true),
];
$member = [
    'id' => 2,
    'username' => 'sam',
    'role' => 'member',
    'disabled' => 0,
    'permissions' => tylilo_admin_default_permissions(),
];
$member['permissions']['beta'] = 1;

check(tylilo_admin_can($owner, 'arcade_publish') && !tylilo_admin_can($member, 'arcade_publish'), 'Arcade visibility defaults to the owner');

$forbidden = tylilo_beta_arcade_toggle($member, 'crash-cars', false, $token);
check($forbidden['status'] === 403, 'a team member without permission gets a 403');
check(tylilo_beta_visible('crash-cars'), 'a rejected toggle leaves Crash Cars visible');

$badCsrf = tylilo_beta_arcade_toggle($owner, 'crash-cars', false, 'not-the-token');
check($badCsrf['status'] === 400 && tylilo_beta_visible('crash-cars'), 'a bad CSRF token does not change visibility');

tylilo_admin_with(static function (array $data) use ($owner): array {
    $data['users'][] = [
        'id' => (int) $owner['id'],
        'username' => (string) $owner['username'],
        'password_hash' => 'hash',
        'role' => 'owner',
        'disabled' => 0,
        'permissions' => $owner['permissions'],
        'totp_secret' => '',
        'totp_enabled' => 0,
        'recovery_hashes' => '[]',
        'created_at' => time(),
        'session_version' => 1,
    ];

    return $data;
});

$hidden = tylilo_beta_arcade_toggle($owner, 'crash-cars', false, $token);
check($hidden['status'] === 204 && !tylilo_beta_visible('crash-cars'), 'the owner can hide Crash Cars');
check(tylilo_arcade_visible_early_games() === [], 'the Alpha/Beta Games section is empty when nothing is visible');

$shown = tylilo_beta_arcade_toggle($owner, 'jumpy-jumperton', true, $token);
check($shown['status'] === 204 && tylilo_beta_visible('jumpy-jumperton'), 'the owner can show another beta');
$listed = tylilo_arcade_visible_early_games();
check(count($listed) === 1 && $listed[0]['slug'] === 'jumpy-jumperton', 'the Arcade section lists only the betas that are visible');

$data = tylilo_admin_with(static fn (array $stored): array => $stored);
$actions = array_values(array_filter($data['activity'], static fn (array $row): bool => ($row['action'] ?? '') === 'beta-arcade'));
check(count($actions) === 2, 'each toggle is written to the activity log');
$details = array_map(static fn (array $row): string => (string) $row['detail'], $actions);
check(in_array('Hid Crash Cars from the Arcade', $details, true) && in_array('Showed Jumpy Jumperton in the Arcade', $details, true), 'the activity log names the game and the new state');

$source = (string) file_get_contents($root . '/arcade/index.php');
$betaPage = (string) file_get_contents($root . '/admin/beta/index.php');
$sitemap = (string) file_get_contents($root . '/sitemap.xml');
check(str_contains($source, 'tylilo_arcade_visible_early_games()') && str_contains($source, 'if ($earlyGames !== []):'), 'the Arcade page renders Alpha/Beta Games from the live visibility state');
check(!str_contains($sitemap, 'crash-cars') && !str_contains($sitemap, '/beta/'), 'early games stay out of the sitemap');
check(str_contains($betaPage, 'Show in Arcade') && str_contains($betaPage, 'Hide from Arcade') && !str_contains($betaPage, 'They are not in the Arcade'), 'the Beta tab uses a show/hide button and no longer says the games are not in the Arcade');
check(str_contains($betaPage, 'Some can also be played in the Arcade under Alpha/Beta Games'), 'the Beta tab intro matches the public Arcade section');
check(!preg_match('/header\s*\([^;]*Content-Security-Policy/', (string) file_get_contents($root . '/arcade/early.php')), 'the public game gate does not send the site CSP');

echo "\n{$checks} checks, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
