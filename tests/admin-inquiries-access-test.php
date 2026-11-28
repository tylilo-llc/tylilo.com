<?php

declare(strict_types=1);

$case = $argv[1] ?? '';
$root = dirname(__DIR__);

if ($case === '') {
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

    $dir = sys_get_temp_dir() . '/tylilo-inq-access-unit-' . getmypid();
    putenv('TYLILO_DATA_DIR=' . $dir);
    putenv('TYLILO_ADMIN_STORE=json');
    $_SERVER['SCRIPT_FILENAME'] = __FILE__;
    $_SERVER['DOCUMENT_ROOT'] = $root;
    require $root . '/admin/_init.php';

    $legacy = [
        'id' => 2,
        'username' => 'sam',
        'role' => 'member',
        'disabled' => 0,
        'permissions' => [
            'notes' => 0,
            'feedback' => 1,
            'ideas' => 1,
            'leaderboards' => 1,
            'beta' => 1,
        ],
    ];
    check(!tylilo_admin_can($legacy, 'inquiries.view'), 'an arcade-only person cannot open Inquiries');
    check(tylilo_admin_can(['id' => 1, 'role' => 'owner', 'disabled' => 0, 'permissions' => []], 'inquiries.view'), 'the owner can open Inquiries without the key being stored');
    check(tylilo_admin_can([
        'id' => 3,
        'role' => 'member',
        'disabled' => 0,
        'permissions' => ['inquiries.view' => 1],
    ], 'inquiries.view'), 'a member granted inquiries.view can open Inquiries');

    $defaults = tylilo_admin_default_permissions();
    check(($defaults['inquiries.view'] ?? 1) === 0, 'new non-owner defaults leave inquiries.view off');

    $_POST = ['perm_notes' => '1', 'perm_feedback' => '1', 'perm_ideas' => '1'];
    $fromPost = tylilo_admin_permissions_from_post();
    check(($fromPost['inquiries.view'] ?? 1) === 0, 'an invite or save that omits the Inquiries box leaves it off');
    $_POST['perm_inquiries_view'] = '1';
    $fromPost = tylilo_admin_permissions_from_post();
    check(($fromPost['inquiries.view'] ?? 0) === 1, 'checking Inquiries in the Users form stores inquiries.view');
    $_POST = [];

    $fields = tylilo_admin_permissions_fields_html(null);
    check(str_contains($fields, '<legend>Consulting</legend>'), 'the Users form has a Consulting permission group');
    check(str_contains($fields, 'name="perm_inquiries_view"'), 'the Consulting group includes the Inquiries checkbox');
    check(!str_contains($fields, 'name="perm_inquiries_view" checked'), 'Inquiries is off by default on a new invite');
    $fieldsOn = tylilo_admin_permissions_fields_html(['inquiries.view' => 1], true);
    check(str_contains($fieldsOn, 'name="perm_inquiries_view" checked'), 'a granted Inquiries box stays checked when editing a member');

    $migrated = tylilo_admin_migrate([
        'users' => [$legacy],
        'notes' => [],
        'todos' => [],
        'ideas' => [],
        'invites' => [],
        'password_resets' => [],
        'feedback' => [],
        'attempts' => [],
        'versions' => [],
        'comments' => [],
        'activity' => [],
        'seeded' => true,
    ]);
    $after = $migrated['users'][0]['permissions'];
    check(($after['inquiries.view'] ?? 0) !== 1, 'a migration does not grant inquiries.view to an existing arcade-only user');
    check($after['leaderboards'] === 1 && $after['beta'] === 1, 'a migration leaves existing arcade permissions in place');
    check(!tylilo_admin_can($migrated['users'][0], 'inquiries.view'), 'an existing arcade-only user still cannot open Inquiries after migrate');

    foreach (['arcade-get', 'arcade-post', 'owner-get', 'granted-get'] as $name) {
        passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($name), $code);
        if ($code !== 0) {
            $failed++;
        }
    }

    echo "\n{$checks} local checks, plus page cases; {$failed} failed in this process\n";
    exit($failed === 0 ? 0 : 1);
}

else {
$dir = sys_get_temp_dir() . '/tylilo-inq-access-' . $case . '-' . getmypid();
if (!is_dir($dir)) {
    mkdir($dir, 0700, true);
}
putenv('TYLILO_DATA_DIR=' . $dir);
putenv('TYLILO_ADMIN_STORE=json');
$_SERVER['SCRIPT_FILENAME'] = $root . '/admin/inquiries.php';
$_SERVER['DOCUMENT_ROOT'] = $root;
$_SERVER['HTTPS'] = 'off';
$_SERVER['REQUEST_URI'] = '/admin/inquiries.php';
$_SERVER['REQUEST_METHOD'] = str_starts_with($case, 'arcade-post') ? 'POST' : 'GET';
$_GET = [];
$_POST = [];

$now = time();
$token = bin2hex(random_bytes(32));
$userId = str_starts_with($case, 'arcade') ? 2 : (str_starts_with($case, 'granted') ? 3 : 1);
$users = [
    [
        'id' => 1,
        'username' => 'ryan',
        'password_hash' => 'hash',
        'role' => 'owner',
        'disabled' => 0,
        'permissions' => [],
        'totp_secret' => '',
        'totp_enabled' => 0,
        'recovery_hashes' => '[]',
        'created_at' => $now,
        'session_version' => 1,
    ],
    [
        'id' => 2,
        'username' => 'sam',
        'password_hash' => 'hash',
        'role' => 'member',
        'disabled' => 0,
        'permissions' => [
            'notes' => 0,
            'feedback' => 1,
            'ideas' => 1,
            'leaderboards' => 1,
            'beta' => 1,
        ],
        'totp_secret' => '',
        'totp_enabled' => 0,
        'recovery_hashes' => '[]',
        'created_at' => $now,
        'session_version' => 1,
    ],
    [
        'id' => 3,
        'username' => 'pat',
        'password_hash' => 'hash',
        'role' => 'member',
        'disabled' => 0,
        'permissions' => ['inquiries.view' => 1],
        'totp_secret' => '',
        'totp_enabled' => 0,
        'recovery_hashes' => '[]',
        'created_at' => $now,
        'session_version' => 1,
    ],
];
file_put_contents($dir . '/admin.json', json_encode([
    'users' => $users,
    'ideas' => [],
    'comments' => [],
    'seeded' => true,
], JSON_UNESCAPED_SLASHES));
file_put_contents($dir . '/inquiries.json', json_encode([
    'inquiries' => [[
        'id' => 7,
        'name' => 'Sample',
        'email' => 'sample@example.com',
        'created_at' => $now,
        'answers' => [['Q', 'A']],
    ]],
    'rates' => [],
], JSON_UNESCAPED_SLASHES));

ini_set('session.save_path', $dir);
ini_set('session.use_strict_mode', '0');
session_save_path($dir);
$sessionId = 'inqacc' . preg_replace('/[^a-z0-9]/', '', $case);
session_id($sessionId);
file_put_contents($dir . '/sess_' . $sessionId, 'admin_user_id|i:' . $userId . ';admin_session_version|i:1;admin_last_seen|i:' . $now . ';admin_login_at|i:' . $now . ';admin_csrf|s:64:"' . $token . '";');

if ($case === 'arcade-post') {
    $_POST = [
        'csrf' => $token,
        'action' => 'delete',
        'confirm' => 'yes',
        'id' => '7',
    ];
}

$failed = 0;
$checks = 0;
function check(bool $ok, string $label): void
{
    global $failed, $checks, $case;
    $checks++;
    echo ($ok ? 'ok  ' : 'not ok  ') . $case . ': ' . $label . "\n";
    if (!$ok) {
        $failed++;
    }
}

register_shutdown_function(static function () use (&$failed, $dir, $case): void {
    $html = ob_get_clean();
    $html = is_string($html) ? $html : '';
    $status = http_response_code();
    if (str_starts_with($case, 'arcade')) {
        check($status === 403, 'returns 403');
        check(str_contains($html, 'You do not have access to that page.'), 'shows the not-allowed message');
        check(!str_contains($html, 'href="/admin/inquiries.php"'), 'does not put Inquiries in the menu');
        if ($case === 'arcade-post') {
            $raw = (string) file_get_contents($dir . '/inquiries.json');
            check(str_contains($raw, '"id": 7') || str_contains($raw, '"id":7'), 'does not delete an inquiry');
        }
    } else {
        check($status === 200 || $status === false || $status === 0, 'returns a successful page');
        check(str_contains($html, 'Consulting inquiries') || str_contains($html, 'Inquiries'), 'renders the Inquiries page');
        check(str_contains($html, 'href="/admin/inquiries.php"'), 'shows Inquiries in the menu');
    }
    exit($failed === 0 ? 0 : 1);
});

ob_start();
require $root . '/admin/inquiries.php';

}
