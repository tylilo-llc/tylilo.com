<?php

declare(strict_types=1);

$case = $argv[1] ?? '';
$root = dirname(__DIR__);

$failed = 0;
$checks = 0;
function check(bool $ok, string $label): void
{
    global $failed, $checks, $case;
    $checks++;
    echo ($ok ? 'ok  ' : 'not ok  ') . ($case !== '' ? $case . ': ' : '') . $label . "\n";
    if (!$ok) {
        $failed++;
    }
}

function seed_inquiries(string $dir, int $now): void
{
    $users = [];
    foreach ([
        [1, 'ryan', 'owner', []],
        [2, 'sam', 'member', ['notes' => 0, 'feedback' => 1, 'ideas' => 1, 'leaderboards' => 1, 'beta' => 1]],
        [3, 'pat', 'member', ['inquiries.view' => 1]],
    ] as [$id, $name, $role, $perms]) {
        $users[] = [
            'id' => $id, 'username' => $name, 'password_hash' => 'hash', 'role' => $role, 'disabled' => 0,
            'permissions' => $perms, 'totp_secret' => '', 'totp_enabled' => 0, 'recovery_hashes' => '[]',
            'created_at' => $now, 'session_version' => 1,
        ];
    }
    file_put_contents($dir . '/admin.json', json_encode(['users' => $users, 'ideas' => [], 'comments' => [], 'seeded' => true]));
    $row = static fn (int $id, int $at, array $a, string $fit): array => [
        'id' => $id, 'created_at' => $at, 'answers' => $a, 'fit' => $fit, 'fit_reasons' => [],
        'preview' => false, 'mail_visitor' => 'sent', 'mail_owner' => 'sent',
    ];
    file_put_contents($dir . '/inquiries.json', json_encode([
        'inquiries' => [
            $row(1, $now - 300, ['name' => 'Zoe Ward', 'email' => 'zoe@example.com', 'business' => 'Zeta Labs', 'stage' => 'growing'], 'Improvement'),
            $row(2, $now - 200, ['name' => 'Amy Cole', 'email' => 'amy@example.com', 'business' => 'Ames Bakery', 'stage' => 'idea'], 'Not a fit'),
            $row(3, $now - 100, ['name' => 'Mia Bell', 'email' => 'mia@example.com', 'business' => 'Midway Co', 'stage' => 'stuck'], 'Assessment'),
        ],
        'rates' => [],
    ], JSON_UNESCAPED_SLASHES));
}

if ($case === '') {
    $dir = sys_get_temp_dir() . '/tylilo-inq-list-' . getmypid();
    mkdir($dir, 0700, true);
    putenv('TYLILO_DATA_DIR=' . $dir);
    putenv('TYLILO_ADMIN_STORE=json');
    $_SERVER['SCRIPT_FILENAME'] = __FILE__;
    $_SERVER['DOCUMENT_ROOT'] = $root;
    $_GET = [];
    $now = time();
    require $root . '/admin/_init.php';
    require_once $root . '/includes/inquiry.php';
    seed_inquiries($dir, $now);

    $rows = tylilo_inquiry_all();
    check(count($rows) === 3 && (int) $rows[0]['id'] === 3, 'the store lists inquiries newest first');
    check(tylilo_inquiry_unread_count(1) === 3 && tylilo_inquiry_unread_count(3) === 3, 'new inquiries are unread for every reviewer');
    check(tylilo_inquiry_is_unread(1, 3) && tylilo_inquiry_is_unread(3, 3), 'an unopened inquiry is unread');

    tylilo_inquiry_mark_read(1, 3);
    check(!tylilo_inquiry_is_unread(1, 3) && tylilo_inquiry_unread_count(1) === 2, 'opening an inquiry marks it read for that person');
    check(tylilo_inquiry_is_unread(3, 3) && tylilo_inquiry_unread_count(3) === 3, 'another person still sees that inquiry as unread');

    tylilo_inquiry_mark_unread(1, 3);
    check(tylilo_inquiry_is_unread(1, 3) && tylilo_inquiry_unread_count(1) === 3, 'an inquiry can be marked unread again');

    tylilo_inquiry_mark_all_read(1);
    check(tylilo_inquiry_unread_count(1) === 0 && tylilo_inquiry_unread_count(3) === 3, 'mark all read is per person');

    tylilo_inquiry_delete(2);
    check(tylilo_inquiry_unread_count(3) === 2, 'deleting an inquiry drops it from unread counts');
    $reads = tylilo_inquiry_reads_load();
    check(!isset($reads['users']['1']['2']), 'read state for a deleted inquiry is removed');

    $left = tylilo_inquiry_all();
    $byName = tylilo_inquiry_sorted($left, 'name', 'asc', 1);
    check(array_map(static fn (array $r): int => (int) $r['id'], $byName) === [3, 1], 'name sorts A to Z');
    $byNameDesc = tylilo_inquiry_sorted($left, 'name', 'desc', 1);
    check(array_map(static fn (array $r): int => (int) $r['id'], $byNameDesc) === [1, 3], 'name sorts Z to A');
    $byEmail = tylilo_inquiry_sorted($left, 'email', 'asc', 1);
    check(array_map(static fn (array $r): string => (string) $r['answers']['email'], $byEmail) === ['mia@example.com', 'zoe@example.com'], 'email sorts A to Z');
    $byCompany = tylilo_inquiry_sorted($left, 'company', 'asc', 1);
    check(array_map(static fn (array $r): string => (string) $r['answers']['business'], $byCompany) === ['Midway Co', 'Zeta Labs'], 'company sorts A to Z');
    $byStage = tylilo_inquiry_sorted($left, 'stage', 'asc', 1);
    check(array_map(static fn (array $r): string => (string) $r['answers']['stage'], $byStage) === ['stuck', 'growing'], 'stage sorts by its label');
    $byFit = tylilo_inquiry_sorted($left, 'fit', 'asc', 1);
    check(array_map(static fn (array $r): string => (string) $r['fit'], $byFit) === ['Assessment', 'Improvement'], 'suggested fit sorts A to Z');
    $default = tylilo_inquiry_sorted($left, 'received', 'desc', 1);
    check(array_map(static fn (array $r): int => (int) $r['id'], $default) === [3, 1], 'date received defaults to newest first');
    $oldest = tylilo_inquiry_sorted($left, 'received', 'asc', 1);
    check(array_map(static fn (array $r): int => (int) $r['id'], $oldest) === [1, 3], 'date received can sort oldest first');

    tylilo_inquiry_mark_unread(1, 3);
    $byStatus = tylilo_inquiry_sorted($left, 'status', 'desc', 1);
    check((int) $byStatus[0]['id'] === 3 && (int) $byStatus[1]['id'] === 1, 'status sorts unread first when descending');
    $byStatusAsc = tylilo_inquiry_sorted($left, 'status', 'asc', 1);
    check((int) $byStatusAsc[0]['id'] === 1 && (int) $byStatusAsc[1]['id'] === 3, 'status sorts read first when ascending');

    $_GET = ['sort' => 'nope', 'dir' => 'sideways'];
    [$sort, $dir] = tylilo_inquiry_sort_from_request();
    check($sort === 'received' && $dir === 'desc', 'an invalid sort falls back to newest first');
    $_GET = ['sort' => 'name', 'dir' => 'asc'];
    [$sort, $dir] = tylilo_inquiry_sort_from_request();
    check($sort === 'name' && $dir === 'asc', 'a valid sort is taken from the query');
    $_GET = [];
    check(tylilo_inquiries_url() === '/admin/inquiries.php', 'the default list URL has no sort query');
    check(tylilo_inquiries_url('name', 'asc', 3) === '/admin/inquiries.php?sort=name&dir=asc&view=3', 'the list URL keeps the sort and the open inquiry');

    $owner = ['id' => 1, 'role' => 'owner', 'disabled' => 0, 'permissions' => []];
    $arcade = ['id' => 2, 'role' => 'member', 'disabled' => 0, 'permissions' => ['feedback' => 1, 'ideas' => 1, 'leaderboards' => 1, 'beta' => 1]];
    check(tylilo_admin_inquiries_unread($owner) === 1, 'the owner unread helper counts only unread items');
    check(tylilo_admin_inquiries_unread($arcade) === 0, 'a person without inquiries.view gets an unread count of 0');

    $menuOwner = tylilo_admin_menu_html($owner, '/admin/');
    check(str_contains($menuOwner, 'aria-label="Inquiries, 1 unread"') && str_contains($menuOwner, 'class="admin-unread-badge" aria-hidden="true">1</span>'), 'the owner menu shows an Inquiries unread badge with an accessible name');
    $menuArcade = tylilo_admin_menu_html($arcade, '/admin/');
    check(!str_contains($menuArcade, 'admin-unread-badge') && !str_contains($menuArcade, 'href="/admin/inquiries.php"'), 'an arcade-only person does not see an Inquiries badge');
    $dash = tylilo_admin_dashboard_html($owner);
    check(str_contains($dash, '1 unread inquiry') && str_contains($dash, 'aria-label="View inquiries, 1 unread"'), 'the Consulting card reports the unread count');
    tylilo_inquiry_mark_all_read(1);
    $menuZero = tylilo_admin_menu_html($owner, '/admin/ideas.php');
    check(str_contains($menuZero, '>Inquiries</a>') && !str_contains($menuZero, 'aria-label="Inquiries, 0 unread"') && !str_contains($menuZero, 'admin-unread-badge'), 'the badge is hidden when there is nothing unread');

    foreach (['list', 'sort-name', 'view', 'menu-page', 'arcade-mark'] as $name) {
        passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($name), $code);
        if ($code !== 0) {
            $failed++;
        }
    }

    echo "\n{$checks} local checks, plus page cases; {$failed} failed in this process\n";
    exit($failed === 0 ? 0 : 1);
}

$dir = sys_get_temp_dir() . '/tylilo-inq-list-' . $case . '-' . getmypid();
mkdir($dir, 0700, true);
putenv('TYLILO_DATA_DIR=' . $dir);
putenv('TYLILO_ADMIN_STORE=json');
$now = time();
seed_inquiries($dir, $now);
$userId = $case === 'arcade-mark' ? 2 : 1;
$_SERVER['SCRIPT_FILENAME'] = $root . '/admin/inquiries.php';
$_SERVER['DOCUMENT_ROOT'] = $root;
$_SERVER['HTTPS'] = 'off';
$_SERVER['REQUEST_METHOD'] = $case === 'arcade-mark' ? 'POST' : 'GET';
$_GET = [];
$_POST = [];
if ($case === 'sort-name') {
    $_GET = ['sort' => 'name', 'dir' => 'asc'];
    $_SERVER['REQUEST_URI'] = '/admin/inquiries.php?sort=name&dir=asc';
} elseif ($case === 'view') {
    $_GET = ['view' => '2'];
    $_SERVER['REQUEST_URI'] = '/admin/inquiries.php?view=2';
} elseif ($case === 'menu-page') {
    $_SERVER['SCRIPT_FILENAME'] = $root . '/admin/index.php';
    $_SERVER['REQUEST_URI'] = '/admin/';
} else {
    $_SERVER['REQUEST_URI'] = '/admin/inquiries.php';
}

ini_set('session.save_path', $dir);
ini_set('session.use_strict_mode', '0');
session_save_path($dir);
$sessionId = 'inqlist' . preg_replace('/[^a-z0-9]/', '', $case);
session_id($sessionId);
$token = str_repeat('b', 64);
file_put_contents($dir . '/sess_' . $sessionId, 'admin_user_id|i:' . $userId . ';admin_session_version|i:1;admin_last_seen|i:' . $now . ';admin_login_at|i:' . $now . ';admin_csrf|s:64:"' . $token . '";');

if ($case === 'arcade-mark') {
    $_POST = ['csrf' => $token, 'action' => 'mark_all_read'];
}

register_shutdown_function(static function () use (&$failed, $dir, $case, $token): void {
    $html = ob_get_clean();
    $html = is_string($html) ? $html : '';
    $status = http_response_code();
    if ($case === 'arcade-mark') {
        check($status === 403, 'returns 403');
        $reads = is_file($dir . '/inquiry-reads.json') ? (string) file_get_contents($dir . '/inquiry-reads.json') : '';
        check($reads === '' || !str_contains($reads, '"2"'), 'does not mark inquiries read');
        check(!str_contains($html, 'Mark all as read'), 'does not show mark-all-read');
        exit($failed === 0 ? 0 : 1);
    }
    if ($case === 'menu-page') {
        check($status === 200 || $status === false || $status === 0, 'the dashboard renders');
        check(str_contains($html, 'aria-label="Menu, 3 unread inquiries"') && str_contains($html, 'class="admin-unread-badge" aria-hidden="true">3</span>'), 'the menu button shows an unread count');
        check(str_contains($html, 'aria-label="Inquiries, 3 unread"'), 'the Inquiries menu link names the unread count');
        check(str_contains($html, 'data-dash="consulting"') && str_contains($html, '3 unread inquiries'), 'the Consulting card shows the unread count');
        exit($failed === 0 ? 0 : 1);
    }
    check($status === 200 || $status === false || $status === 0 || $status === 303, 'returns a successful page');
    check(str_contains($html, 'Consulting inquiries'), 'renders the Inquiries page');
    check(str_contains($html, 'aria-sort="'), 'column headers expose aria-sort');
    check(str_contains($html, 'class="inq-sort-btn"') && str_contains($html, 'name="sort"') && str_contains($html, 'name="dir"'), 'sort controls are buttons in GET forms');
    if ($case === 'list') {
        check(preg_match('/data-inquiry-id="3".*data-inquiry-id="2".*data-inquiry-id="1"/s', $html) === 1, 'the default list is newest first');
        check(str_contains($html, 'aria-sort="descending"') && str_contains($html, 'aria-label="Received, currently newest first. Sort oldest first."'), 'Received is the default descending column');
        check(substr_count($html, 'class="inq-new-tag">New</span>') === 3, 'unread rows are marked New');
        check(str_contains($html, 'class="inq-unread"') && str_contains($html, 'Mark all as read'), 'unread rows are distinguished and mark-all-read is offered');
        check(str_contains($html, 'This does not delete the contact profile') || str_contains($html, 'does not delete the contact profile'), 'delete copy says the contact is kept');
    }
    if ($case === 'sort-name') {
        check(preg_match('/data-inquiry-id="2".*data-inquiry-id="3".*data-inquiry-id="1"/s', $html) === 1, 'sorting by name lists Amy, Mia, then Zoe');
        check(str_contains($html, 'name="dir" value="desc"') && str_contains($html, 'currently A to Z'), 'the active Name header will toggle to Z to A');
        check(str_contains($html, 'sorted by name, A to Z'), 'the page says how the list is sorted');
    }
    if ($case === 'view') {
        check(str_contains($html, 'id="inquiry-2"') && str_contains($html, 'Mark as unread'), 'viewing an inquiry opens it and offers mark unread');
        $reads = json_decode((string) file_get_contents($dir . '/inquiry-reads.json'), true);
        check(isset($reads['users']['1']['2']), 'viewing an inquiry marks it read for that person');
        check(preg_match('/data-inquiry-id="2"[^>]*class="inq-read"|class="inq-read"[^>]*data-inquiry-id="2"/', $html) === 1 || str_contains($html, 'data-inquiry-id="2"'), 'the opened row is present');
        check(substr_count($html, 'class="inq-new-tag">New</span>') === 2, 'the opened inquiry is no longer tagged New');
    }
    exit($failed === 0 ? 0 : 1);
});

ob_start();
if ($case === 'menu-page') {
    require $root . '/admin/_init.php';
    $user = tylilo_admin_current();
    if ($user === null) {
        echo 'NOUSER';
    } else {
        tylilo_admin_layout('Admin', tylilo_admin_dashboard_html($user));
    }
} else {
    require $root . '/admin/inquiries.php';
}
