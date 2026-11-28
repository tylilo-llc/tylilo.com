<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$dir = sys_get_temp_dir() . '/tylilo-menu-' . getmypid();
putenv('TYLILO_DATA_DIR=' . $dir);
putenv('TYLILO_ADMIN_STORE=json');
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
$_SERVER['DOCUMENT_ROOT'] = $root;
$_SERVER['REQUEST_URI'] = '/admin/ideas.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
require $root . '/admin/_init.php';

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
        'permissions' => tylilo_admin_default_permissions(true),
        'totp_secret' => '',
        'totp_enabled' => 0,
        'recovery_hashes' => '[]',
        'created_at' => time(),
        'session_version' => 1,
    ];

    return $data;
});
$_SESSION['admin_user_id'] = 1;
$_SESSION['admin_session_version'] = 1;
$_SESSION['admin_last_seen'] = time();
$_SESSION['admin_login_at'] = time();

ob_start();
tylilo_admin_layout('Ideas', '<p>Ideas body</p>');
$ownerPage = ob_get_clean();
check(is_string($ownerPage), 'the admin page renders');
$ownerPage = (string) $ownerPage;

check(str_contains($ownerPage, 'id="admin-menu-toggle"'), 'the menu control is a button');
check(str_contains($ownerPage, 'aria-expanded="false"'), 'the menu starts closed for scripted pages');
check(str_contains($ownerPage, 'aria-controls="admin-menu-panel"'), 'the menu button names its panel');
check(str_contains($ownerPage, 'Menu</button>'), 'the menu button is labeled Menu');
check(str_contains($ownerPage, '<h1 class="tagline">Ideas</h1>'), 'the current page name sits in the header');
check(str_contains($ownerPage, 'class="js"') || str_contains($ownerPage, "classList.add(\"js\")"), 'scripted pages can hide the menu until it is opened');
check(substr_count($ownerPage, 'class="admin-menu-divider"') === 5, 'an owner sees a divider between each of the six groups');
check(str_contains($ownerPage, 'href="/admin/inquiries.php"'), 'an owner sees Inquiries in the menu');
check(str_contains($ownerPage, 'href="/admin/ideas.php" aria-current="page"'), 'Ideas is the current page');
check(!str_contains($ownerPage, 'href="/admin/notes.php" aria-current="page"'), 'Notes is not marked current on Ideas');
check(str_contains($ownerPage, 'action="/admin/logout.php"'), 'log out still posts to the logout action');
check(str_contains($ownerPage, 'name="csrf"'), 'log out still sends the CSRF token');
check(str_contains($ownerPage, '>Log out</button>'), 'log out keeps its label');

$labels = ['Home', 'Workspace', 'Games', 'Team', 'Account'];
$cursor = 0;
$orderOk = true;
foreach ($labels as $label) {
    $at = strpos($ownerPage, '<h2 class="admin-menu-label">' . $label . '</h2>', $cursor);
    if ($at === false) {
        $orderOk = false;
        break;
    }
    $cursor = $at + 1;
}
check($orderOk, 'group headings stay in order');

$links = ['Notes', 'To-dos', 'Ideas', 'Feedback', 'Games', 'Beta', 'Scores', 'Users', 'Activity', 'Settings', 'Log out'];
$cursor = strpos($ownerPage, 'id="admin-menu-panel"');
$linkOrder = is_int($cursor);
foreach ($links as $label) {
    $at = strpos($ownerPage, '>' . $label . '<', (int) $cursor);
    if ($at === false) {
        $linkOrder = false;
        break;
    }
    $cursor = $at + 1;
}
check($linkOrder, 'owner links stay in the requested group order');

$ideasOnly = [
    'id' => 2,
    'role' => 'member',
    'disabled' => 0,
    'permissions' => [
        'notes' => 0,
        'feedback' => 0,
        'ideas' => 1,
        'leaderboards' => 0,
        'beta' => 0,
    ],
];
$ideasMenu = tylilo_admin_menu_html($ideasOnly, '/admin/ideas.php');
check(str_contains($ideasMenu, '>Workspace</h2>'), 'a person who can see Ideas still gets the Workspace group');
check(str_contains($ideasMenu, 'href="/admin/ideas.php" aria-current="page"'), 'Ideas stays current for that person');
check(!str_contains($ideasMenu, '>Notes<'), 'notes stay hidden without that permission');
check(!str_contains($ideasMenu, '>To-dos<'), 'to-dos stay hidden without that permission');
check(!str_contains($ideasMenu, '>Feedback<'), 'feedback stays hidden without that permission');
check(!str_contains($ideasMenu, '>Games</h2>') && !str_contains($ideasMenu, '>Games<'), 'the Games group is omitted when it would be empty');
check(!str_contains($ideasMenu, '>Team</h2>'), 'the Team group is omitted for a team member');
check(!str_contains($ideasMenu, '>Users<') && !str_contains($ideasMenu, '>Activity<') && !str_contains($ideasMenu, '>Beta<') && !str_contains($ideasMenu, '>Scores<'), 'owner and other gated links stay hidden');
check(substr_count($ideasMenu, 'class="admin-menu-divider"') === 2, 'empty groups do not leave a divider behind');
check(str_contains($ideasMenu, '>Settings<') && str_contains($ideasMenu, '>Log out</button>'), 'account links stay available');

$plain = [
    'id' => 3,
    'role' => 'member',
    'disabled' => 0,
    'permissions' => [
        'notes' => 0,
        'feedback' => 0,
        'ideas' => 0,
        'leaderboards' => 0,
        'beta' => 0,
    ],
];
$plainMenu = tylilo_admin_menu_html($plain, '/admin/');
check(str_contains($plainMenu, 'href="/admin/" aria-current="page"'), 'Home is current on the admin index');
check(!str_contains($plainMenu, '>Workspace</h2>'), 'Workspace is hidden when none of its links are allowed');
check(substr_count($plainMenu, 'class="admin-menu-divider"') === 1, 'only Home and Account remain, with one divider');

$beta = tylilo_admin_menu_html($ideasOnly, '/admin/beta/g/harbor/');
check(!str_contains($beta, 'aria-current="page"'), 'a beta page is not current when that person cannot open Beta');
$ownerBeta = tylilo_admin_menu_html([
    'id' => 1,
    'role' => 'owner',
    'disabled' => 0,
    'permissions' => [],
], '/admin/beta/g/harbor/');
check(str_contains($ownerBeta, 'href="/admin/beta/" aria-current="page"'), 'Beta stays current on a beta game page');

echo $failed === 0 ? "\n{$checks} checks passed\n" : "\n{$failed} failed\n";
exit($failed === 0 ? 0 : 1);
