<?php

declare(strict_types=1);

/*
 * Private contact profiles: data layer, retention, and the admin page's
 * server-side access rule. Page cases re-run this file with a case name.
 */

$case = $argv[1] ?? '';
$root = dirname(__DIR__);
$now = time();

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

function contact_cid(string $email): string
{
    return substr(hash('sha256', 'tylilo-contact|' . strtolower(trim($email))), 0, 24);
}

function contact_seed(string $dir, int $now): void
{
    $users = [];
    foreach ([
        [1, 'ryan', 'owner', []],
        [2, 'sam', 'member', ['notes' => 0, 'feedback' => 1, 'ideas' => 1, 'leaderboards' => 1, 'beta' => 1]],
        [3, 'pat', 'member', ['inquiries.view' => 1]],
        [4, 'lee', 'member', ['contacts.view' => 1]],
    ] as [$id, $name, $role, $perms]) {
        $users[] = ['id' => $id, 'username' => $name, 'password_hash' => 'hash', 'role' => $role, 'disabled' => 0,
            'permissions' => $perms, 'totp_secret' => '', 'totp_enabled' => 0, 'recovery_hashes' => '[]',
            'created_at' => $now, 'session_version' => 1];
    }
    file_put_contents($dir . '/admin.json', json_encode(['users' => $users, 'ideas' => [], 'comments' => [], 'seeded' => true]));
    $row = static fn (int $id, int $at, array $a, string $fit): array => [
        'id' => $id, 'created_at' => $at, 'answers' => $a, 'fit' => $fit, 'fit_reasons' => [],
        'preview' => false, 'mail_visitor' => 'sent', 'mail_owner' => 'sent',
    ];
    file_put_contents($dir . '/inquiries.json', json_encode([
        'inquiries' => [
            $row(1, $now - 86400 * 30, ['name' => 'Alice Ames', 'email' => 'Alice@Example.com ', 'business' => 'Ames Bakery', 'stage' => 'Running', 'message' => 'First message'], 'Improvement'),
            $row(2, $now - 86400 * 2, ['name' => 'Alice Ames', 'email' => 'alice@example.com', 'business' => 'Ames Bakery Co', 'website' => 'ames.example', 'location' => 'Ohio', 'message' => 'Second message'], 'Strategy'),
            $row(3, $now - 86400 * 5, ['name' => 'Bob Bell', 'email' => 'bob@example.com', 'message' => 'Hello'], 'Not sure'),
            $row(4, $now - 86400 * 1500, ['name' => 'Old Olly', 'email' => 'olly@example.com', 'message' => 'Long ago'], 'Not sure'),
            $row(5, $now - 86400 * 1500, ['name' => 'Old Client', 'email' => 'client@example.com', 'message' => 'Long ago'], 'Not sure'),
        ],
        'rates' => [],
    ]));
}

if ($case === '') {

    $dir = sys_get_temp_dir() . '/tylilo-contacts-unit-' . getmypid();
    mkdir($dir, 0700, true);
    putenv('TYLILO_DATA_DIR=' . $dir);
    putenv('TYLILO_ADMIN_STORE=json');
    $_SERVER['SCRIPT_FILENAME'] = __FILE__;
    $_SERVER['DOCUMENT_ROOT'] = $root;
    require $root . '/admin/_init.php';
    require $root . '/includes/contacts.php';
    contact_seed($dir, $now);

    // Permission.
    $cat = tylilo_admin_permission_catalog();
    check(isset($cat['Consulting']['contacts.view']) || str_contains(json_encode($cat), 'contacts.view'), 'contacts.view is in the permission catalog');
    check((tylilo_admin_default_permissions()['contacts.view'] ?? 1) === 0, 'contacts.view is off by default');
    check(tylilo_admin_can(['id' => 1, 'role' => 'owner', 'disabled' => 0, 'permissions' => []], 'contacts.view'), 'the owner can always open Contacts');
    check(!tylilo_admin_can(['id' => 3, 'role' => 'member', 'disabled' => 0, 'permissions' => ['inquiries.view' => 1]], 'contacts.view'), 'inquiries.view alone does not open Contacts');
    check(tylilo_admin_can(['id' => 4, 'role' => 'member', 'disabled' => 0, 'permissions' => ['contacts.view' => 1]], 'contacts.view'), 'contacts.view opens Contacts');
    $_POST = [];
    check((tylilo_admin_permissions_from_post()['contacts.view'] ?? 1) === 0, 'an invite that omits Contacts leaves it off');
    $_POST = ['perm_contacts_view' => '1'];
    check((tylilo_admin_permissions_from_post()['contacts.view'] ?? 0) === 1, 'checking Contacts stores contacts.view');
    $_POST = [];
    check(str_contains(tylilo_admin_permissions_fields_html(null), 'name="perm_contacts_view"') && !str_contains(tylilo_admin_permissions_fields_html(null), 'name="perm_contacts_view" checked'), 'the Users form offers Contacts, unchecked');
    $legacy = ['id' => 2, 'username' => 'sam', 'role' => 'member', 'disabled' => 0, 'permissions' => ['inquiries.view' => 1, 'feedback' => 1]];
    $mig = tylilo_admin_migrate(['users' => [$legacy], 'notes' => [], 'todos' => [], 'ideas' => [], 'invites' => [], 'password_resets' => [], 'feedback' => [], 'attempts' => [], 'versions' => [], 'comments' => [], 'activity' => [], 'seeded' => true]);
    check(($mig['users'][0]['permissions']['contacts.view'] ?? 0) !== 1, 'a migration does not grant contacts.view');

    // Profiles merge by normalized email.
    $all = tylilo_contacts_all($now);
    $by = [];
    foreach ($all as $p) {
        $by[$p['email']] = $p;
    }
    check(count($all) === 4, 'five inquiries from four emails make four profiles');
    $alice = $by['alice@example.com'] ?? null;
    check($alice !== null && count($alice['inquiries']) === 2, 'differing case and spaces in an email merge into one person');
    check($alice['business'] === 'Ames Bakery Co' && $alice['website'] === 'ames.example' && $alice['location'] === 'Ohio', 'profile fields come from the latest answers that have them');
    check($alice['stage'] === 'Running', 'a field only given earlier is still shown');
    check($alice['fit'] === 'Strategy', 'the latest suggested fit is shown');
    check((int) $alice['inquiries'][0]['id'] === 2, 'the timeline is newest first');
    check($all[0]['email'] === 'alice@example.com', 'profiles are ordered by recent activity');
    check($alice['status'] === 'new', 'a profile starts as New');
    check($alice['id'] === contact_cid('Alice@Example.com') && !str_contains($alice['id'], 'alice'), 'the profile id is a hash, not the email');
    check(tylilo_contact_get($alice['id']) !== null && tylilo_contact_get('../etc') === null && tylilo_contact_get(str_repeat('z', 24)) === null, 'only a valid id loads a profile');
    check(array_keys(tylilo_contact_statuses()) === ['new', 'replied', 'call', 'engaged', 'not_fit', 'closed'], 'the six statuses are offered');

    // Notes and status.
    check(!tylilo_contact_add_note('alice@example.com', "  \n "), 'an empty note is refused');
    check(!tylilo_contact_add_note('alice@example.com', str_repeat('x', 4001)), 'an over-long note is refused');
    check(tylilo_contact_add_note('ALICE@example.com ', 'Called, <b>friendly</b>'), 'a note can be added');
    check(tylilo_contact_add_note('alice@example.com', 'Second note'), 'a second note can be added');
    $alice = tylilo_contact_get(contact_cid('alice@example.com'));
    check(count($alice['notes']) === 2 && $alice['notes'][0]['text'] === 'Second note' && $alice['notes'][0]['at'] >= $now, 'notes are timestamped, newest first');
    check(!tylilo_contact_set_status('alice@example.com', 'vip'), 'an unknown status is refused');
    check(tylilo_contact_set_status('alice@example.com', 'call'), 'a status can be set');
    check(tylilo_contact_get(contact_cid('alice@example.com'))['status'] === 'call', 'the status is saved');
    $raw = (string) file_get_contents($dir . '/contacts.json');
    check(!str_contains($raw, 'Ames Bakery') && !str_contains($raw, 'First message'), 'the notes file holds notes and status only, not copies of answers');
    check(is_file($dir . '/contacts.json'), 'notes are stored in the data directory');

    // Export.
    $alice = tylilo_contact_get(contact_cid('alice@example.com'));
    $json = json_decode(tylilo_contact_export_json($alice), true);
    check(is_array($json) && str_contains(json_encode($json), 'First message') && str_contains(json_encode($json), 'Second note'), 'the JSON export holds answers and notes');
    $text = tylilo_contact_export_text($alice);
    check(str_contains($text, 'alice@example.com') && str_contains($text, 'First message') && str_contains($text, 'Second message') && str_contains($text, 'Call scheduled'), 'the text export holds answers, status, and timeline');
    check(!str_contains($text, 'bob@example.com') && !str_contains(json_encode($json), 'bob@example.com'), 'an export holds only that person');

    // Delete.
    tylilo_contact_add_note('bob@example.com', 'Bob note');
    check(tylilo_contact_delete('Alice@Example.com') === 2, 'deleting a person removes both of their inquiries');
    check(tylilo_contact_get(contact_cid('alice@example.com')) === null, 'the deleted profile is gone');
    check(!str_contains((string) file_get_contents($dir . '/contacts.json'), 'Second note') && !str_contains((string) file_get_contents($dir . '/inquiries.json'), 'Ames'), 'their notes and answers are gone from storage');
    check(tylilo_contact_get(contact_cid('bob@example.com')) !== null && count(tylilo_contact_get(contact_cid('bob@example.com'))['notes']) === 1, 'other people are untouched');

    // Retention.
    $due = [];
    foreach (tylilo_contacts_all($now) as $p) {
        $due[$p['email']] = $p['due'];
    }
    check($due['olly@example.com'] === true && $due['bob@example.com'] === false, 'only people with no activity for over 3 years are flagged');
    tylilo_contact_set_status('client@example.com', 'engaged');
    check(tylilo_contacts_purge_expired($now) === 1, 'the purge removes one expired profile');
    check(tylilo_contact_get(contact_cid('olly@example.com')) === null, 'the expired profile and its inquiry are deleted');
    check(tylilo_contact_get(contact_cid('client@example.com')) !== null, 'an engaged client is kept past 3 years');
    check(tylilo_contacts_purge_expired($now) === 0, 'a second purge finds nothing');
    check(!str_contains((string) file_get_contents($dir . '/contacts.json'), 'olly'), 'the purge log holds a count, not who');

    foreach (['arcade-get', 'arcade-post', 'inq-get', 'owner-list', 'owner-profile', 'owner-note', 'owner-export', 'owner-delete', 'owner-delete-unconfirmed', 'lee-list'] as $name) {
        passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($name), $code);
        if ($code !== 0) {
            $failed++;
        }
    }
    echo "\n{$checks} local checks, plus page cases; {$failed} failed in this process\n";
    exit($failed === 0 ? 0 : 1);
}

$dir = sys_get_temp_dir() . '/tylilo-contacts-' . $case . '-' . getmypid();
mkdir($dir, 0700, true);
putenv('TYLILO_DATA_DIR=' . $dir);
putenv('TYLILO_ADMIN_STORE=json');
$_SERVER['SCRIPT_FILENAME'] = $root . '/admin/contacts.php';
$_SERVER['DOCUMENT_ROOT'] = $root;
$_SERVER['HTTPS'] = 'off';
$_SERVER['REQUEST_URI'] = '/admin/contacts.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = [];
$_POST = [];
contact_seed($dir, $now);
file_put_contents($dir . '/contacts.json', json_encode(['contacts' => [contact_cid('client@example.com') => ['email' => 'client@example.com', 'status' => 'engaged', 'status_at' => $now, 'notes' => [], 'created_at' => $now]], 'purges' => []]));

$alice = contact_cid('alice@example.com');
$token = bin2hex(random_bytes(32));
$userId = match (true) {
    str_starts_with($case, 'arcade') => 2,
    $case === 'inq-get' => 3,
    $case === 'lee-list' => 4,
    default => 1,
};
if ($case === 'arcade-post') {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['csrf' => $token, 'action' => 'delete', 'confirm' => 'yes', 'id' => $alice];
} elseif ($case === 'owner-profile') {
    $_GET = ['c' => $alice];
} elseif ($case === 'owner-note') {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['csrf' => $token, 'action' => 'note', 'id' => $alice, 'note' => 'Private <i>note</i>'];
} elseif ($case === 'owner-export') {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['csrf' => $token, 'action' => 'export', 'id' => $alice, 'format' => 'json'];
} elseif ($case === 'owner-delete' || $case === 'owner-delete-unconfirmed') {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['csrf' => $token, 'action' => 'delete', 'id' => $alice] + ($case === 'owner-delete' ? ['confirm' => 'yes'] : []);
}

ini_set('session.save_path', $dir);
ini_set('session.use_strict_mode', '0');
session_save_path($dir);
$sessionId = 'contacts' . preg_replace('/[^a-z0-9]/', '', $case);
session_id($sessionId);
file_put_contents($dir . '/sess_' . $sessionId, 'admin_user_id|i:' . $userId . ';admin_session_version|i:1;admin_last_seen|i:' . $now . ';admin_login_at|i:' . $now . ';admin_csrf|s:64:"' . $token . '";');

register_shutdown_function(static function () use (&$failed, $dir, $case, $alice): void {
    $html = ob_get_clean();
    $html = is_string($html) ? $html : '';
    $status = http_response_code();
    $inq = (string) file_get_contents($dir . '/inquiries.json');
    $notes = is_file($dir . '/contacts.json') ? (string) file_get_contents($dir . '/contacts.json') : '';
    if (in_array($case, ['arcade-get', 'arcade-post', 'inq-get'], true)) {
        check($status === 403, 'returns 403');
        check(str_contains($html, 'You do not have access to that page.'), 'shows the not-allowed message');
        check(!str_contains($html, 'Alice') && !str_contains($html, 'alice@example.com'), 'shows no contact data');
        check(!str_contains($html, 'href="/admin/contacts.php"'), 'no Contacts link in the menu');
        check(str_contains($inq, 'Ames Bakery'), 'deletes nothing');
    } elseif ($case === 'owner-list' || $case === 'lee-list') {
        check($status === 200 || $status === false, 'returns a page');
        check(str_contains($html, 'Alice Ames') && str_contains($html, 'Bob Bell'), 'lists the people');
        check(str_contains($html, 'href="/admin/contacts.php?c=' . $alice . '"') && !str_contains($html, 'c=alice'), 'links to a profile by hashed id, not by email');
        check(str_contains($html, 'due for deletion') === false, 'purged expired profiles are not listed');
        check(!str_contains($inq, 'Old Olly') && str_contains($inq, 'Old Client') === true, 'opening the list purges an expired profile and keeps an engaged one');
        check(substr_count($html, 'href="/admin/contacts.php?c=') === 3, 'one row per person');
        check(str_contains($html, 'href="/admin/contacts.php"'), 'Contacts is in the menu');
    } elseif ($case === 'owner-profile') {
        check($status === 200 || $status === false, 'returns a page');
        check(str_contains($html, 'Ames Bakery Co') && str_contains($html, 'First message') && str_contains($html, 'Second message'), 'shows fields and every inquiry with answers');
        check(str_contains($html, 'name="status"') && str_contains($html, 'Call scheduled') && str_contains($html, 'Not a fit'), 'offers the status choices');
        check(str_contains($html, 'name="action" value="export"') && str_contains($html, 'name="action" value="delete"') && str_contains($html, 'name="confirm"'), 'offers export and a confirmed delete');
        check(str_contains($html, 'name="csrf"'), 'forms carry a CSRF token');
    } elseif ($case === 'owner-note') {
        check($status === 303, 'redirects after saving');
        check(str_contains($notes, 'Private <i>note<\/i>') || str_contains($notes, 'Private <i>note</i>'), 'saves the note in the data directory');
    } elseif ($case === 'owner-export') {
        check(str_contains($html, 'First message') && !str_contains($html, 'Bob'), 'exports only that person');
                check(is_array(json_decode($html, true)), 'the export is valid JSON');
    } elseif ($case === 'owner-delete') {
        check($status === 303, 'redirects after deleting');
        check(!str_contains($inq, 'Ames Bakery') && str_contains($inq, 'Bob Bell'), 'deletes that person only');
    } elseif ($case === 'owner-delete-unconfirmed') {
        check(str_contains($html, 'confirm') && str_contains($inq, 'Ames Bakery'), 'refuses to delete without confirmation');
    }
    exit($failed === 0 ? 0 : 1);
});

ob_start();
require $root . '/admin/contacts.php';
