<?php

declare(strict_types=1);

$case = $argv[1] ?? '';
if ($case === '') {
    $failed = 0;
    foreach (['board', 'new', 'open', 'propose-error', 'edit-error'] as $name) {
        passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($name), $code);
        if ($code !== 0) {
            $failed++;
        }
    }
    exit($failed === 0 ? 0 : 1);
}

$root = dirname(__DIR__);
$dir = sys_get_temp_dir() . '/tylilo-ideas-ui-' . $case . '-' . getmypid();
putenv('TYLILO_DATA_DIR=' . $dir);
putenv('TYLILO_ADMIN_STORE=json');
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
$_SERVER['DOCUMENT_ROOT'] = $root;
$_SERVER['HTTPS'] = 'off';
$_SERVER['REQUEST_URI'] = '/admin/ideas.php' . ($case === 'new' ? '?new=1' : ($case === 'open' ? '?open=4' : ''));
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = [];
$_POST = [];
if ($case === 'new') {
    $_GET['new'] = '1';
}
if ($case === 'open') {
    $_GET['open'] = '4';
}

if (!is_dir($dir)) {
    mkdir($dir, 0700, true);
}
$token = bin2hex(random_bytes(32));
$now = time();
ini_set('session.save_path', $dir);
ini_set('session.use_strict_mode', '0');
session_save_path($dir);
$sessionId = 'ideasui' . preg_replace('/[^a-z]/', '', $case);
session_id($sessionId);
file_put_contents($dir . '/sess_' . $sessionId, 'admin_user_id|i:1;admin_session_version|i:1;admin_last_seen|i:' . $now . ';admin_login_at|i:' . $now . ';admin_csrf|s:64:"' . $token . '";');
$idea = [
    'id' => 4,
    'user_id' => 1,
    'title' => 'Harbor Match',
    'pitch' => 'Boats come into port.',
    'notes' => 'Keep the lighthouse.',
    'inspired' => '',
    'genre' => 'puzzle',
    'style' => '',
    'controls' => [],
    'devices' => [],
    'session' => '',
    'players' => '',
    'difficulty' => '',
    'scoring' => '',
    'leaderboard' => 0,
    'audience' => '',
    'effort' => '',
    'status' => 'idea',
    'priority' => 'high',
    'image' => '',
    'votes' => [],
    'created_at' => time(),
];
file_put_contents($dir . '/admin.json', json_encode([
    'users' => [[
        'id' => 1,
        'username' => 'ryan',
        'password_hash' => 'hash',
        'role' => 'owner',
        'disabled' => 0,
        'permissions' => ['notes' => 1, 'feedback' => 1, 'ideas' => 1, 'leaderboards' => 1, 'beta' => 1],
        'totp_secret' => '',
        'totp_enabled' => 0,
        'recovery_hashes' => '[]',
        'created_at' => time(),
        'session_version' => 1,
    ]],
    'ideas' => [$idea],
    'comments' => [],
    'seeded' => true,
], JSON_UNESCAPED_SLASHES));

if ($case === 'propose-error') {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        'csrf' => $token,
        'action' => 'propose',
        'id' => '0',
        'title' => 'Harbor',
        'pitch' => '',
        'genre' => 'puzzle',
    ];
}
if ($case === 'edit-error') {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        'csrf' => $token,
        'action' => 'edit',
        'id' => '4',
        'title' => 'Changed',
        'pitch' => '',
        'genre' => 'racing',
    ];
}

$failed = 0;
$checks = 0;

function check(bool $ok, string $label): void
{
    global $failed, $checks, $caseLabel;
    $checks++;
    echo ($ok ? 'ok  ' : 'not ok  ') . $caseLabel . $label . "\n";
    if (!$ok) {
        $failed++;
    }
}

$caseLabel = $case . ': ';
ob_start();
require $root . '/admin/ideas.php';
$html = ob_get_clean();
check(is_string($html), 'the ideas page renders');
$html = (string) $html;

if ($case === 'board') {
    check(str_contains($html, 'id="new-idea-open"'), 'the board has a New idea control');
    check(str_contains($html, 'href="/admin/ideas.php?new=1"'), 'without script the control links to the form page');
    check(str_contains($html, 'id="new-idea-dialog"'), 'the add form lives in a dialog');
    check(str_contains($html, 'aria-labelledby="new-idea-title"'), 'the dialog has a name');
    check(str_contains($html, '>New idea</h2>'), 'the dialog title is New idea');
    check(!str_contains($html, '>Propose a game</h2>'), 'the old top form heading is gone');
    check(str_contains($html, 'id="idea-4"'), 'the saved idea is listed');
    $cardAt = strpos($html, 'id="idea-4"');
    $summaryEnd = strpos($html, '</summary>', (int) $cardAt);
    $summary = $cardAt !== false && $summaryEnd !== false ? substr($html, $cardAt, $summaryEnd - $cardAt) : '';
    check(str_contains($summary, '>Harbor Match</h2>'), 'a collapsed idea shows its name');
    check(str_contains($summary, 'Category: '), 'the category is named for assistive tech');
    check(str_contains($summary, '>Puzzle</span>'), 'the category is the genre');
    check(!str_contains($summary, 'Boats come into port.'), 'the description stays out of the collapsed summary');
    check(!str_contains($summary, 'Keep the lighthouse.'), 'notes stay out of the collapsed summary');
    check(preg_match('/id="idea-4"[^>]*>\s*<details>/', $html) === 1, 'ideas start collapsed');
    check(str_contains($html, 'Boats come into port.'), 'the description is still in the idea');
    check(str_contains($html, 'Keep the lighthouse.'), 'notes are still in the idea');
    check(str_contains($html, '>Edit this idea</summary>'), 'the edit action is still available');
    check(str_contains($html, '>Vote</button>'), 'voting is still available');
}

if ($case === 'new') {
    check(str_contains($html, 'id="new-idea-panel"'), 'the fallback page shows the form');
    check(str_contains($html, '>Share idea</button>'), 'the fallback form can be submitted');
    check(str_contains($html, 'href="/admin/ideas.php">Cancel</a>'), 'Cancel leaves the fallback page without script');
    check(!str_contains($html, 'id="idea-4"'), 'the fallback page is the form on its own');
    check(!str_contains($html, 'id="new-idea-dialog"'), 'the fallback page does not depend on the dialog');
}

if ($case === 'open') {
    check(preg_match('/id="idea-4"[^>]*>\s*<details open>/', $html) === 1, 'an idea opened from a saved form stays open');
}

if ($case === 'propose-error') {
    check(str_contains($html, 'An idea needs a working title and a one-line pitch.'), 'a rejected new idea explains the problem');
    check(str_contains($html, 'value="Harbor"'), 'the title that was entered is kept');
    check(str_contains($html, 'value="puzzle" selected'), 'the genre that was entered is kept');
    check(str_contains($html, 'data-open="true"'), 'the dialog is marked to reopen');
    check(str_contains($html, 'id="new-idea-open"'), 'the board is still there behind the dialog');
    $stored = json_decode((string) file_get_contents($dir . '/admin.json'), true);
    $titles = is_array($stored) ? array_column($stored['ideas'] ?? [], 'title') : [];
    check(!in_array('Harbor', $titles, true), 'a rejected new idea is not stored');
}

if ($case === 'edit-error') {
    check(str_contains($html, 'An idea needs a working title and a one-line pitch.'), 'a rejected edit explains the problem');
    check(preg_match('/id="idea-4"[^>]*>\s*<details open>/', $html) === 1, 'the idea with the error stays open');
    check(str_contains($html, '<details open><summary>Edit this idea</summary>'), 'the edit form stays open after a validation error');
    check(str_contains($html, 'value="Changed"'), 'the edited title is kept');
    check(str_contains($html, 'value="racing" selected'), 'the edited genre is kept');
    $stored = json_decode((string) file_get_contents($dir . '/admin.json'), true);
    $saved = is_array($stored) ? ($stored['ideas'][0]['title'] ?? '') : '';
    check($saved === 'Harbor Match', 'a rejected edit does not replace the saved idea');
}

echo $failed === 0 ? "\n{$case}: {$checks} checks passed\n" : "\n{$case}: {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
