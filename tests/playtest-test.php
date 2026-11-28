<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$dir = sys_get_temp_dir() . '/tylilo-playtest-' . getmypid();
if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
    fwrite(STDERR, "could not create test data dir\n");
    exit(1);
}
putenv('TYLILO_DATA_DIR=' . $dir);
putenv('TYLILO_ADMIN_STORE=json');
putenv('ADMIN_SETUP_CODE=correct-horse-battery');
putenv('TURNSTILE_SECRET=1x0000000000000000000000000000000AA');
putenv('PLAYTEST_READ_TOKEN');
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
$_SERVER['REQUEST_URI'] = '/api/playtest-upload.php';
define('TYLILO_SEND_HEADERS', false);
require $root . '/includes/bootstrap.php';
require $root . '/includes/admin.php';
require $root . '/includes/playtest.php';

$ownerCsrf = tylilo_admin_csrf();
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

$tester = ['id' => 7, 'username' => 'tess', 'role' => 'member', 'disabled' => 0, 'permissions' => ['beta' => 1]];
$outsider = ['id' => 8, 'username' => 'olive', 'role' => 'member', 'disabled' => 0, 'permissions' => ['notes' => 1]];
$owner = ['id' => 1, 'username' => 'ryan', 'role' => 'owner', 'disabled' => 0, 'permissions' => []];
$csrf = str_repeat('a1', 32);
$server = [
    'REQUEST_METHOD' => 'POST',
    'HTTP_HOST' => 'tylilo.com',
    'HTTP_ORIGIN' => 'https://tylilo.com',
    'REMOTE_ADDR' => '203.0.113.9',
    'CONTENT_LENGTH' => '2000',
];
$notes = 'The car clipped the wall at the second turn and froze for a second.';
$post = ['game' => 'crash-cars', 'notes' => $notes, 'version' => '2.4.1', 'clientTime' => '2026-11-28T15:00:00.000Z', 'csrf' => $csrf];
$move = static fn (string $from, string $to): bool => copy($from, $to);

function sample(string $kind, int $extra = 2048): array
{
    $path = tempnam(sys_get_temp_dir(), 'pt');
    $head = $kind === 'mp4' ? "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom" : ($kind === 'webm' ? "\x1A\x45\xDF\xA3\x9F\x42\x86\x81\x01" : 'GIF89a not a video');
    file_put_contents($path, $head . str_repeat('x', $extra));

    return ['name' => 'clip.' . $kind, 'type' => 'video/webm', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => filesize($path)];
}

function upload(array $server, array $post, array $files, ?array $user, string $csrf, ?int $now = null): array
{
    global $move;

    return tylilo_playtest_handle_upload($server, $post, $files, $user, $csrf, $move, $now);
}

// Auth
[$status, $body] = upload($server, $post, ['video' => sample('webm')], null, $csrf);
check($status === 403 && $body['ok'] === false, 'a signed-out visitor gets 403');
[$status] = upload($server, $post, ['video' => sample('webm')], $outsider, $csrf);
check($status === 403, 'a signed-in user without the Alpha/Beta permission gets 403');
check(!tylilo_playtest_can(array_merge($tester, ['disabled' => 1])), 'a disabled tester is refused');
check(tylilo_playtest_can($tester) && tylilo_playtest_can($owner), 'an approved tester and the owner are allowed');

// CSRF and origin
[$status, $body] = upload($server, ['csrf' => 'wrong'] + $post, ['video' => sample('webm')], $tester, $csrf);
check($status === 403 && $body['error'] === 'csrf', 'a wrong CSRF token gets 403');
[$status, $body] = upload($server, array_diff_key($post, ['csrf' => 1]), ['video' => sample('webm')], $tester, $csrf);
check($status === 403 && $body['error'] === 'csrf', 'a missing CSRF token gets 403');
[$status, $body] = upload(['HTTP_ORIGIN' => 'https://evil.example'] + $server, $post, ['video' => sample('webm')], $tester, $csrf);
check($status === 403 && $body['error'] === 'origin', 'a foreign Origin gets 403');
$noOrigin = $server;
unset($noOrigin['HTTP_ORIGIN']);
[$status] = upload($noOrigin, $post, ['video' => sample('webm')], $tester, $csrf);
check($status === 403, 'no Origin and no Referer gets 403');
[$status] = upload(['HTTP_REFERER' => 'https://tylilo.com/arcade/crash-cars/'] + $noOrigin, $post, ['video' => sample('webm')], $tester, $csrf);
check($status === 200, 'a same-site Referer is accepted when Origin is absent');
[$status] = upload(['HTTP_ORIGIN' => 'https://tylilo.com.evil.example'] + $server, $post, ['video' => sample('webm')], $tester, $csrf);
check($status === 403, 'a look-alike Origin host gets 403');
[$status, $body] = upload(['REQUEST_METHOD' => 'GET'] + $server, $post, [], $tester, $csrf);
check($status === 405, 'GET is not allowed on the upload');

// Type
[$status, $body] = upload($server, $post, ['video' => sample('gif')], $tester, $csrf);
check($status === 415 && $body['error'] === 'type', 'a non-video file gets 415 even with a video type claimed');
$renamed = sample('gif');
$renamed['name'] = 'movie.webm';
[$status] = upload($server, $post, ['video' => $renamed], $tester, $csrf);
check($status === 415, 'a .webm name does not make a non-video file pass');
[$status, $body] = upload($server, $post, ['video' => sample('webm')], $tester, $csrf);
check($status === 200 && $body['ok'] === true && preg_match('/^[0-9]{8}T[0-9]{6}Z-[a-f0-9]{12}$/', (string) $body['id']) === 1 && str_ends_with((string) $body['receivedAt'], 'Z'), 'a WebM (EBML) passes and returns ok, id and receivedAt');
$webmId = (string) $body['id'];
[$status, $body] = upload($server, $post, ['video' => sample('mp4')], $tester, $csrf);
check($status === 200, 'an MP4 (ftyp) passes');
$mp4Id = (string) $body['id'];

// Notes, game, version, clientTime
foreach (['short' => 'too short', 'blank' => "         \n  ", 'long' => str_repeat('n', 4001)] as $label => $bad) {
    [$status, $body] = upload($server, ['notes' => $bad] + $post, ['video' => sample('webm')], $tester, $csrf);
    check($status === 422 && $body['error'] === 'notes', 'notes that are ' . $label . ' get 422');
}
[$status] = upload($server, ['notes' => str_repeat('n', 4000)] + $post, ['video' => sample('webm')], $tester, $csrf);
check($status === 200, 'notes of exactly 4000 characters pass');
[$status] = upload($server, ['notes' => str_repeat('é', 10)] + $post, ['video' => sample('webm')], $tester, $csrf);
check($status === 200, 'ten multi-byte characters count as ten');
[$status, $body] = upload($server, ['game' => '../etc'] + $post, ['video' => sample('webm')], $tester, $csrf);
check($status === 422 && $body['error'] === 'game', 'a path-like game slug is refused');
[$status] = upload($server, ['game' => 'solitaire'] + $post, ['video' => sample('webm')], $tester, $csrf);
check($status === 422, 'a released game is not accepted');
[$status] = upload($server, ['game' => 'jumpy-jumperton'] + $post, ['video' => sample('webm')], $tester, $csrf);
check($status === 200, 'another pre-release game is accepted');
[$status] = upload($server, ['version' => str_repeat('9', 33)] + $post, ['video' => sample('webm')], $tester, $csrf);
check($status === 422, 'a version over 32 characters is refused');
[$status] = upload($server, ['clientTime' => 'not a date'] + $post, ['video' => sample('webm')], $tester, $csrf);
check($status === 422, 'a bad clientTime is refused');
[$status, $body] = upload($server, $post, [], $tester, $csrf);
check($status === 422 && $body['error'] === 'video', 'a missing video is refused');

// Size
$max = tylilo_playtest_max_bytes();
check($max <= 52428800 && $max >= 1, 'the cap never exceeds 50 MB (' . $max . ' bytes here)');
$big = sample('webm', $max + 10);
[$status, $body] = upload($server, $post, ['video' => $big], $tester, $csrf);
check($status === 413 && $body['error'] === 'too_large' && $body['maxBytes'] === $max, 'a file over the cap gets 413 with maxBytes');
[$status] = upload(['CONTENT_LENGTH' => (string) ($max + 600000)] + $server, $post, [], $tester, $csrf);
check($status === 413, 'a request body over the cap is answered 413 before anything else');
[$status] = upload($server, $post, ['video' => ['error' => UPLOAD_ERR_INI_SIZE] + sample('webm')], $tester, $csrf);
check($status === 413, 'PHP’s own size error maps to 413');
$cmd = escapeshellarg(PHP_BINARY) . ' -d upload_max_filesize=8M -d post_max_size=10M -r '
    . escapeshellarg('define("TYLILO_BOOTSTRAPPED", true); require ' . var_export($root . '/includes/playtest.php', true) . '; echo tylilo_playtest_max_bytes();');
$low = (int) shell_exec($cmd);
check($low === 8 * 1048576, 'a lower upload_max_filesize lowers the cap (8M -> ' . $low . ')');
$cmd = escapeshellarg(PHP_BINARY) . ' -d upload_max_filesize=64M -d post_max_size=10M -r '
    . escapeshellarg('define("TYLILO_BOOTSTRAPPED", true); require ' . var_export($root . '/includes/playtest.php', true) . '; echo tylilo_playtest_max_bytes();');
$low = (int) shell_exec($cmd);
check($low === 10 * 1048576 - 262144, 'a lower post_max_size lowers the cap, with room for the form fields');

// Rate limit: 10 per hour per user
$now = time() + 10;
$rateUser = ['id' => 21, 'username' => 'rae'] + $tester;
$codes = [];
for ($i = 0; $i < 11; $i++) {
    [$status] = upload($server, $post, ['video' => sample('webm')], $rateUser, $csrf, $now + $i);
    $codes[] = $status;
}
check(array_slice($codes, 0, 10) === array_fill(0, 10, 200) && $codes[10] === 429, 'the eleventh upload in an hour gets 429');
[$status, $body] = upload($server, $post, ['video' => sample('webm')], $rateUser, $csrf, $now + 3601 + 10);
check($status === 200, 'the limit lifts after an hour');
[$status] = upload($server, $post, ['video' => sample('webm')], $tester, $csrf, $now + 20);
check($status === 200, 'one tester’s limit does not affect another');

// Storage and log
$found = tylilo_playtest_find($webmId);
$meta = $found['meta'] ?? [];
check($found !== null && is_file($found['video']) && str_starts_with($found['video'], $dir . '/playtest/crash-cars/' . $webmId), 'the video is stored under playtest/<game>/<id> in the private data dir');
check(($meta['notes'] ?? '') === $notes && ($meta['version'] ?? '') === '2.4.1' && ($meta['userId'] ?? 0) === 7 && ($meta['user'] ?? '') === 'tess' && ($meta['type'] ?? '') === 'video/webm' && ($meta['md5'] ?? '') === md5_file($found['video'] ?? '') && (int) ($meta['size'] ?? 0) === filesize($found['video'] ?? ''), 'the .json holds notes, version, user, type, size and md5');
check(preg_match('/^[a-f0-9]{64}$/', (string) ($meta['ipHash'] ?? '')) === 1 && !str_contains((string) json_encode($meta), '203.0.113.9'), 'the IP is stored only as a hash');
$data = tylilo_admin_with(static fn (array $stored): array => $stored);
$logged = array_filter($data['activity'] ?? [], static fn (array $row): bool => $row['action'] === 'playtest-upload' && str_contains($row['detail'], $webmId));
check(count($logged) === 1, 'each upload is logged as playtest-upload');
check(!str_contains(realpath($found['video']) ?: '', realpath($root) . '/') || str_contains($found['video'], '/tmp/'), 'storage is outside the web root here');

// Feed auth
$secret = 'test-secret-value-1234567890';
check(tylilo_playtest_feed_refusal([], '')[0] === 503, 'the feed fails closed with 503 when no secret is set');
check(tylilo_playtest_feed_refusal(['HTTP_AUTHORIZATION' => 'Bearer ' . $secret], '')[0] === 503, 'a token does not open the feed when no secret is set');
check(tylilo_playtest_feed_refusal([], $secret)[0] === 401, 'no token gets 401');
check(tylilo_playtest_feed_refusal(['HTTP_AUTHORIZATION' => 'Bearer nope'], $secret)[0] === 401, 'a wrong token gets 401');
check(tylilo_playtest_feed_refusal(['HTTP_AUTHORIZATION' => 'Basic ' . base64_encode('ryan:' . $secret)], $secret)[0] === 401, 'a Basic header is not a bearer token');
check(tylilo_playtest_feed_refusal(['HTTP_AUTHORIZATION' => 'Bearer ' . $secret], $secret) === null, 'the right bearer token opens the feed');
check(tylilo_playtest_feed_refusal(['REDIRECT_HTTP_AUTHORIZATION' => 'Bearer ' . $secret], $secret) === null, 'the redirected Authorization header works');
check(tylilo_playtest_feed_refusal(['HTTP_X_PLAYTEST_TOKEN' => $secret], $secret) === null, 'X-Playtest-Token works where Authorization is taken');
check(tylilo_playtest_feed_refusal(['HTTP_X_PLAYTEST_TOKEN' => $secret . 'x'], $secret)[0] === 401, 'a near-miss token gets 401');
putenv('PLAYTEST_READ_TOKEN=' . $secret);
check(tylilo_playtest_read_secret() === $secret, 'the secret is read from PLAYTEST_READ_TOKEN');
putenv('PLAYTEST_READ_TOKEN');

// Feed contents and ordering
$items = tylilo_playtest_list('crash-cars');
check(count($items) >= 2 && $items === tylilo_playtest_list('crash-cars'), 'the feed lists reports');
$ids = array_column($items, 'id');
$sorted = $ids;
sort($sorted, SORT_STRING);
check($ids === $sorted, 'reports are listed oldest first');
$cut = $ids[0];
check(array_column(tylilo_playtest_list('crash-cars', $cut), 'id') === array_slice($ids, 1), 'since=<id> returns only newer reports');
check(tylilo_playtest_list('jumpy-jumperton') !== [] && array_unique(array_column(tylilo_playtest_list('jumpy-jumperton'), 'game')) === ['jumpy-jumperton'], 'the feed filters by game');
$item = tylilo_playtest_feed_item($meta, 'https://tylilo.com');
check(array_keys($item) === ['id', 'game', 'receivedAt', 'version', 'notes', 'user', 'size', 'type', 'md5', 'fileUrl'] && $item['fileUrl'] === 'https://tylilo.com/api/playtest-file.php?id=' . $webmId, 'a feed item has the agreed fields and a fileUrl');
check(tylilo_playtest_find('../../etc/passwd') === null && tylilo_playtest_find('20261128T000000Z-zzzzzzzzzzzz') === null, 'the file endpoint rejects odd ids');

// Admin delete and purge
check(tylilo_playtest_admin_delete($tester, $mp4Id, $ownerCsrf)['status'] === 403, 'a tester cannot delete a report');
$perm = $tester;
$perm['permissions']['arcade_publish'] = 1;
check(tylilo_playtest_admin_delete($perm, $mp4Id, 'wrong')['status'] === 403, 'delete needs the CSRF token');
check(tylilo_playtest_admin_delete($perm, $mp4Id, $ownerCsrf)['status'] === 204 && tylilo_playtest_find($mp4Id) === null, 'delete removes the video and its notes');
$data = tylilo_admin_with(static fn (array $stored): array => $stored);
check(count(array_filter($data['activity'], static fn (array $row): bool => $row['action'] === 'playtest-delete')) === 1, 'delete is logged as playtest-delete');
$html = tylilo_playtest_admin_html();
check(str_contains($html, 'Playtest reports') && str_contains($html, '<video') && str_contains($html, tylilo_e($notes)) && str_contains($html, 'name="csrf"'), 'the admin list shows notes, a video player and a CSRF-protected delete');
$old = tylilo_playtest_find($webmId);
file_put_contents(dirname($old['video']) . '/' . $webmId . '.json', json_encode(array_merge($old['meta'], ['receivedAt' => gmdate('Y-m-d\TH:i:s\Z', time() - 91 * 86400)])));
$before = count(tylilo_playtest_list());
$removed = tylilo_playtest_purge(time(), true);
check($removed === 1 && tylilo_playtest_find($webmId) === null && count(tylilo_playtest_list()) === $before - 1, 'purge removes reports older than 90 days and keeps newer ones');

// Files in the repo
$boot = (string) file_get_contents($root . '/includes/playtest-boot.php');
check(str_contains($boot, "'TYLILO_SEND_HEADERS'"), 'the API files send no HTML headers');
$htaccess = (string) file_get_contents($root . '/.htaccess');
check(str_contains($htaccess, 'E=HTTP_AUTHORIZATION'), '.htaccess hands the Authorization header to PHP');
$deploy = (string) file_get_contents($root . '/.github/workflows/' . (is_file($root . '/.github/workflows/deploy-preview.yml') ? 'deploy-preview.yml' : 'deploy.yml'));
check(str_contains($deploy, 'secrets.PLAYTEST_READ_TOKEN') && !preg_match('/PLAYTEST_READ_TOKEN\s*[:=]\s*[\'"]?[A-Za-z0-9]{16,}/', $deploy), 'the workflow reads PLAYTEST_READ_TOKEN from a repository secret');

$tmpDirs = [$dir];
foreach ($tmpDirs as $d) {
    exec('rm -rf ' . escapeshellarg($d));
}
echo "\n" . ($checks - $failed) . '/' . $checks . " checks passed\n";
exit($failed === 0 ? 0 : 1);
