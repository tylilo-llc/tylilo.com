<?php

declare(strict_types=1);

if (!defined('TYLILO_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

/**
 * Playtest reports: a short screen recording plus notes, sent by an approved
 * Alpha/Beta tester from inside a game. Files live in the private data dir
 * (outside the web root) and are read only through auth-checked endpoints.
 */

const TYLILO_PLAYTEST_CAP_BYTES = 52428800;
const TYLILO_PLAYTEST_FORM_OVERHEAD = 262144;
const TYLILO_PLAYTEST_RETENTION_DAYS = 90;
const TYLILO_PLAYTEST_HOURLY_LIMIT = 10;
const TYLILO_PLAYTEST_NOTES_MIN = 10;
const TYLILO_PLAYTEST_NOTES_MAX = 4000;
const TYLILO_PLAYTEST_VERSION_MAX = 32;

function tylilo_playtest_ini_bytes(string $name): int
{
    $raw = trim((string) ini_get($name));
    if ($raw === '' || $raw === '0' || $raw === '-1') {
        return 0;
    }
    if (preg_match('/^([0-9]+)\s*([KMG])?B?$/i', $raw, $match) !== 1) {
        return 0;
    }
    $value = (int) $match[1];

    return $value * match (strtoupper($match[2] ?? '')) {
        'K' => 1024,
        'M' => 1024 * 1024,
        'G' => 1024 * 1024 * 1024,
        default => 1,
    };
}

/**
 * The real upload cap: 50 MB, or lower when the server's own limits are lower.
 * The multipart body also carries the notes and form fields, so the
 * post_max_size limit is reduced by a small allowance for them.
 */
function tylilo_playtest_max_bytes(): int
{
    $cap = TYLILO_PLAYTEST_CAP_BYTES;
    $file = tylilo_playtest_ini_bytes('upload_max_filesize');
    $post = tylilo_playtest_ini_bytes('post_max_size');
    if ($file > 0) {
        $cap = min($cap, $file);
    }
    if ($post > 0) {
        $cap = min($cap, $post - TYLILO_PLAYTEST_FORM_OVERHEAD);
    }

    return max(1, $cap);
}

function tylilo_playtest_size_label(int $bytes): string
{
    if ($bytes >= 1048576) {
        $mb = $bytes / 1048576;

        return ($mb >= 10 ? (string) (int) round($mb) : rtrim(rtrim(number_format($mb, 1, '.', ''), '0'), '.')) . ' MB';
    }
    if ($bytes >= 1024) {
        return (string) (int) round($bytes / 1024) . ' KB';
    }

    return $bytes . ' bytes';
}

function tylilo_playtest_dir(): string
{
    $dir = rtrim(tylilo_turnstile_dir(), '/') . '/playtest';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }

    return $dir;
}

function tylilo_playtest_slug_ok(string $slug): bool
{
    if (preg_match('/^[a-z0-9-]{1,40}$/', $slug) !== 1) {
        return false;
    }
    if ($slug === 'crash-cars') {
        return true;
    }

    return function_exists('tylilo_beta_game') && tylilo_beta_game($slug) !== null;
}

function tylilo_playtest_id_ok(string $id): bool
{
    return preg_match('/^[0-9]{8}T[0-9]{6}Z-[a-f0-9]{12}$/', $id) === 1;
}

/**
 * Identify the recording by its first bytes, never by its name or the
 * browser's claimed type. WebM/Matroska starts with the EBML header
 * 1A 45 DF A3. MP4/MOV starts with a box size then "ftyp".
 *
 * @return array{ext: string, type: string}|null
 */
function tylilo_playtest_sniff(string $path): ?array
{
    $handle = @fopen($path, 'rb');
    if ($handle === false) {
        return null;
    }
    $head = (string) fread($handle, 12);
    fclose($handle);
    if (strlen($head) >= 4 && substr($head, 0, 4) === "\x1A\x45\xDF\xA3") {
        return ['ext' => 'webm', 'type' => 'video/webm'];
    }
    if (strlen($head) >= 12 && substr($head, 4, 4) === 'ftyp') {
        return ['ext' => 'mp4', 'type' => 'video/mp4'];
    }

    return null;
}

function tylilo_playtest_request_host(array $server): string
{
    $host = strtolower((string) ($server['HTTP_HOST'] ?? ''));

    return preg_match('/^[a-z0-9.\-]+(:[0-9]{1,5})?$/', $host) === 1 ? $host : '';
}

/**
 * Same-origin check for the upload POST. The Origin header is required when
 * the browser sends one; Referer is the fallback. Neither present means no.
 */
function tylilo_playtest_same_origin(array $server): bool
{
    $host = tylilo_playtest_request_host($server);
    if ($host === '') {
        return false;
    }
    foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $key) {
        $value = $server[$key] ?? '';
        if (!is_string($value) || $value === '') {
            continue;
        }
        $parts = parse_url($value);
        if (!is_array($parts) || empty($parts['host']) || empty($parts['scheme'])) {
            return false;
        }
        $seen = strtolower((string) $parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');

        return in_array($parts['scheme'], ['http', 'https'], true) && hash_equals($host, $seen);
    }

    return false;
}

function tylilo_playtest_json(int $status, array $body, array $extraHeaders = []): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex, nofollow');
    foreach ($extraHeaders as $header) {
        header($header);
    }
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function tylilo_playtest_can(?array $user): bool
{
    return is_array($user) && tylilo_admin_can($user, 'beta');
}

/**
 * Signed-in tester or null. Applies the same idle and absolute session
 * limits as the admin pages.
 */
function tylilo_playtest_user(): ?array
{
    tylilo_admin_boot();
    $user = tylilo_admin_current();

    return tylilo_playtest_can($user) ? $user : null;
}

function tylilo_playtest_meta_path(string $game, string $id): string
{
    return tylilo_playtest_dir() . '/' . $game . '/' . $id . '.json';
}

/**
 * @return array<string, mixed>|null
 */
function tylilo_playtest_read_meta(string $path): ?array
{
    $raw = @file_get_contents($path);
    $meta = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($meta) || !isset($meta['id'], $meta['game'], $meta['ext'])) {
        return null;
    }
    if (!tylilo_playtest_id_ok((string) $meta['id']) || preg_match('/^[a-z0-9-]{1,40}$/', (string) $meta['game']) !== 1) {
        return null;
    }
    if (!in_array($meta['ext'], ['webm', 'mp4'], true)) {
        return null;
    }

    return $meta;
}

/**
 * Every stored report, oldest first. Optionally one game, and only ids after $since.
 *
 * @return list<array<string, mixed>>
 */
function tylilo_playtest_list(?string $game = null, string $since = ''): array
{
    $base = tylilo_playtest_dir();
    $games = [];
    if ($game !== null) {
        if (preg_match('/^[a-z0-9-]{1,40}$/', $game) === 1) {
            $games[] = $game;
        }
    } else {
        foreach (scandir($base) ?: [] as $name) {
            if (preg_match('/^[a-z0-9-]{1,40}$/', $name) === 1 && is_dir($base . '/' . $name)) {
                $games[] = $name;
            }
        }
    }
    $items = [];
    foreach ($games as $slug) {
        foreach (glob($base . '/' . $slug . '/*.json') ?: [] as $path) {
            $meta = tylilo_playtest_read_meta($path);
            if ($meta === null || $meta['game'] !== $slug || basename($path) !== $meta['id'] . '.json') {
                continue;
            }
            if ($since !== '' && strcmp((string) $meta['id'], $since) <= 0) {
                continue;
            }
            if (!is_file($base . '/' . $slug . '/' . $meta['id'] . '.' . $meta['ext'])) {
                continue;
            }
            $items[] = $meta;
        }
    }
    usort($items, static fn (array $a, array $b): int => strcmp((string) $a['id'], (string) $b['id']));

    return $items;
}

/**
 * @return array{meta: array<string, mixed>, video: string}|null
 */
function tylilo_playtest_find(string $id): ?array
{
    if (!tylilo_playtest_id_ok($id)) {
        return null;
    }
    foreach (glob(tylilo_playtest_dir() . '/*/' . $id . '.json') ?: [] as $path) {
        $meta = tylilo_playtest_read_meta($path);
        if ($meta === null || $meta['id'] !== $id) {
            continue;
        }
        $video = dirname($path) . '/' . $id . '.' . $meta['ext'];
        if (is_file($video)) {
            return ['meta' => $meta, 'video' => $video];
        }
    }

    return null;
}

function tylilo_playtest_delete(string $id): bool
{
    $found = tylilo_playtest_find($id);
    if ($found === null) {
        return false;
    }
    $json = dirname($found['video']) . '/' . $id . '.json';
    @unlink($found['video']);
    @unlink($json);

    return !is_file($found['video']);
}

/**
 * Remove reports older than 90 days. Runs at most once an hour, from the
 * upload and the admin list, so no scheduled job is needed.
 */
function tylilo_playtest_purge(?int $now = null, bool $force = false): int
{
    $now ??= time();
    $dir = tylilo_playtest_dir();
    $marker = $dir . '/.purged';
    if (!$force && is_file($marker) && ($now - (int) @filemtime($marker)) < 3600) {
        return 0;
    }
    @touch($marker, $now);
    $cutoff = $now - TYLILO_PLAYTEST_RETENTION_DAYS * 86400;
    $removed = 0;
    foreach (tylilo_playtest_list() as $meta) {
        $received = strtotime((string) ($meta['receivedAt'] ?? ''));
        if ($received !== false && $received < $cutoff && tylilo_playtest_delete((string) $meta['id'])) {
            $removed++;
        }
    }

    return $removed;
}

/**
 * Successful uploads by this user in the last hour. Held in one small
 * locked file so a count never needs a scan of every report.
 */
function tylilo_playtest_rate(int $userId, bool $record, ?int $now = null): bool
{
    $now ??= time();
    $path = tylilo_playtest_dir() . '/.rate.json';
    $handle = fopen($path, 'c+');
    if ($handle === false) {
        return true;
    }
    try {
        if (!flock($handle, LOCK_EX)) {
            return true;
        }
        $raw = stream_get_contents($handle);
        $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
        $data = is_array($data) ? $data : [];
        $fresh = [];
        foreach ($data as $key => $times) {
            if (!is_array($times)) {
                continue;
            }
            $times = array_values(array_filter($times, static fn ($t): bool => is_int($t) && ($now - $t) < 3600));
            if ($times !== []) {
                $fresh[(string) $key] = $times;
            }
        }
        $mine = $fresh[(string) $userId] ?? [];
        $allowed = count($mine) < TYLILO_PLAYTEST_HOURLY_LIMIT;
        if ($record && $allowed) {
            $fresh[(string) $userId] = [...$mine, $now];
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) json_encode($fresh));
        }

        return $allowed;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

/**
 * @param array<string, mixed> $server
 * @param array<string, mixed> $post
 * @param array<string, mixed> $files
 * @param ?callable(string, string): bool $move  Test seam. Defaults to move_uploaded_file.
 * @return array{0: int, 1: array<string, mixed>}
 */
function tylilo_playtest_handle_upload(array $server, array $post, array $files, ?array $user, string $sessionCsrf, ?callable $move = null, ?int $now = null): array
{
    $now ??= time();
    $max = tylilo_playtest_max_bytes();
    $fail = static fn (int $status, string $code, string $message, array $more = []): array => [$status, ['ok' => false, 'error' => $code, 'message' => $message] + $more];

    if (($server['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return $fail(405, 'method', 'Use POST to send a playtest report.');
    }
    if (!tylilo_playtest_same_origin($server)) {
        return $fail(403, 'origin', 'Reports can only be sent from tylilo.com.');
    }
    // PHP drops the whole body when it is over post_max_size, so say so plainly.
    $length = (int) ($server['CONTENT_LENGTH'] ?? 0);
    if ($length > $max + TYLILO_PLAYTEST_FORM_OVERHEAD) {
        return $fail(413, 'too_large', 'That video is too large. The limit is ' . tylilo_playtest_size_label($max) . '.', ['maxBytes' => $max]);
    }
    if (!tylilo_playtest_can($user)) {
        return $fail(403, 'forbidden', 'Sign in as an approved Alpha/Beta tester to send a report.');
    }
    $sent = is_string($post['csrf'] ?? null) ? $post['csrf'] : '';
    if ($sessionCsrf === '' || $sent === '' || !hash_equals($sessionCsrf, $sent)) {
        return $fail(403, 'csrf', 'Your session check failed. Reload the game and try again.');
    }
    $userId = (int) $user['id'];
    if (!tylilo_playtest_rate($userId, false, $now)) {
        return $fail(429, 'rate', 'You have sent ' . TYLILO_PLAYTEST_HOURLY_LIMIT . ' reports in the last hour. Please try again later.');
    }

    $game = is_string($post['game'] ?? null) ? trim($post['game']) : '';
    if (!tylilo_playtest_slug_ok($game)) {
        return $fail(422, 'game', 'That game is not accepting playtest reports.');
    }
    $notes = is_string($post['notes'] ?? null) ? trim($post['notes']) : '';
    $notesLength = mb_strlen($notes, 'UTF-8');
    if ($notesLength < TYLILO_PLAYTEST_NOTES_MIN || $notesLength > TYLILO_PLAYTEST_NOTES_MAX) {
        return $fail(422, 'notes', 'Notes are required, between ' . TYLILO_PLAYTEST_NOTES_MIN . ' and ' . TYLILO_PLAYTEST_NOTES_MAX . ' characters.');
    }
    $version = is_string($post['version'] ?? null) ? trim($post['version']) : '';
    if (mb_strlen($version, 'UTF-8') > TYLILO_PLAYTEST_VERSION_MAX) {
        return $fail(422, 'version', 'The version is too long.');
    }
    $clientTime = is_string($post['clientTime'] ?? null) ? trim($post['clientTime']) : '';
    if ($clientTime !== '' && (strlen($clientTime) > 40 || strtotime($clientTime) === false)) {
        return $fail(422, 'clientTime', 'The client time is not a valid date.');
    }

    $file = $files['video'] ?? null;
    if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
        return $fail(422, 'video', 'Attach one video file.');
    }
    $error = (int) $file['error'];
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        return $fail(413, 'too_large', 'That video is too large. The limit is ' . tylilo_playtest_size_label($max) . '.', ['maxBytes' => $max]);
    }
    if ($error === UPLOAD_ERR_NO_FILE) {
        return $fail(422, 'video', 'Attach one video file.');
    }
    if ($error !== UPLOAD_ERR_OK) {
        return $fail(422, 'video', 'The video did not finish uploading. Please try again.');
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    $size = is_file($tmp) ? (int) filesize($tmp) : 0;
    if ($size <= 0) {
        return $fail(422, 'video', 'The video file is empty.');
    }
    if ($size > $max) {
        return $fail(413, 'too_large', 'That video is too large. The limit is ' . tylilo_playtest_size_label($max) . '.', ['maxBytes' => $max]);
    }
    $kind = tylilo_playtest_sniff($tmp);
    if ($kind === null) {
        return $fail(415, 'type', 'Only WebM or MP4 videos are accepted.');
    }

    $id = gmdate('Ymd\THis\Z', $now) . '-' . bin2hex(random_bytes(6));
    $folder = tylilo_playtest_dir() . '/' . $game;
    if (!is_dir($folder) && !@mkdir($folder, 0700, true) && !is_dir($folder)) {
        return $fail(500, 'storage', 'Reports cannot be stored right now.');
    }
    $target = $folder . '/' . $id . '.' . $kind['ext'];
    $mover = $move ?? static fn (string $from, string $to): bool => move_uploaded_file($from, $to);
    if (!$mover($tmp, $target)) {
        return $fail(500, 'storage', 'Reports cannot be stored right now.');
    }
    @chmod($target, 0600);
    $received = gmdate('Y-m-d\TH:i:s\Z', $now);
    $meta = [
        'id' => $id,
        'game' => $game,
        'ext' => $kind['ext'],
        'type' => $kind['type'],
        'size' => $size,
        'md5' => (string) md5_file($target),
        'notes' => $notes,
        'version' => $version,
        'clientTime' => $clientTime,
        'receivedAt' => $received,
        'userId' => $userId,
        'user' => (string) ($user['username'] ?? ''),
        'ipHash' => tylilo_admin_ip_hash((string) ($server['REMOTE_ADDR'] ?? '')),
    ];
    $json = $folder . '/' . $id . '.json';
    if (file_put_contents($json, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
        @unlink($target);

        return $fail(500, 'storage', 'Reports cannot be stored right now.');
    }
    @chmod($json, 0600);
    tylilo_playtest_rate($userId, true, $now);
    tylilo_admin_log($userId, (string) ($user['username'] ?? ''), 'playtest-upload', $game . ' ' . $id . ' (' . tylilo_playtest_size_label($size) . ')');
    tylilo_playtest_purge($now);

    return [200, ['ok' => true, 'id' => $id, 'receivedAt' => $received]];
}

/* ---- Owner token feed ---- */

function tylilo_playtest_read_secret(): string
{
    $env = getenv('PLAYTEST_READ_TOKEN');
    if (is_string($env) && $env !== '') {
        return $env;
    }

    return tylilo_private_php_value('playtest-read.php');
}

/**
 * The presented token. "Authorization: Bearer" is the standard form. Preview
 * already uses Authorization for its site password, so X-Playtest-Token is
 * accepted too.
 */
function tylilo_playtest_presented_token(array $server): string
{
    foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
        $value = $server[$key] ?? '';
        if (is_string($value) && preg_match('/^Bearer\s+(\S+)$/i', trim($value), $match) === 1) {
            return $match[1];
        }
    }
    $alt = $server['HTTP_X_PLAYTEST_TOKEN'] ?? '';

    return is_string($alt) ? trim($alt) : '';
}

/**
 * @return array{0: int, 1: array<string, mixed>}|null  A refusal, or null when allowed.
 */
function tylilo_playtest_feed_refusal(array $server, string $secret): ?array
{
    if ($secret === '') {
        return [503, ['ok' => false, 'error' => 'not_configured', 'message' => 'The playtest feed is not set up yet.']];
    }
    $given = tylilo_playtest_presented_token($server);
    if ($given === '' || !hash_equals(hash('sha256', $secret), hash('sha256', $given))) {
        return [401, ['ok' => false, 'error' => 'unauthorized', 'message' => 'A valid token is required.']];
    }

    return null;
}

function tylilo_playtest_base_url(array $server): string
{
    $https = (!empty($server['HTTPS']) && $server['HTTPS'] !== 'off') || (int) ($server['SERVER_PORT'] ?? 0) === 443
        || strtolower((string) ($server['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    $host = tylilo_playtest_request_host($server);

    return ($https ? 'https' : 'http') . '://' . ($host !== '' ? $host : 'tylilo.com');
}

/**
 * @param array<string, mixed> $meta
 * @return array<string, mixed>
 */
function tylilo_playtest_feed_item(array $meta, string $base): array
{
    return [
        'id' => $meta['id'],
        'game' => $meta['game'],
        'receivedAt' => $meta['receivedAt'] ?? '',
        'version' => $meta['version'] ?? '',
        'notes' => $meta['notes'] ?? '',
        'user' => $meta['user'] ?? '',
        'size' => (int) ($meta['size'] ?? 0),
        'type' => $meta['type'] ?? '',
        'md5' => $meta['md5'] ?? '',
        'fileUrl' => $base . '/api/playtest-file.php?id=' . rawurlencode((string) $meta['id']),
    ];
}

/**
 * Stream a stored video with Range support so players can seek.
 */
function tylilo_playtest_stream(string $path, string $type, string $downloadName = ''): never
{
    $size = (int) filesize($path);
    $start = 0;
    $end = $size - 1;
    $status = 200;
    $range = $_SERVER['HTTP_RANGE'] ?? '';
    if (is_string($range) && $range !== '') {
        if (preg_match('/^bytes=(\d*)-(\d*)$/', trim($range), $m) === 1 && ($m[1] !== '' || $m[2] !== '')) {
            if ($m[1] === '') {
                $start = max(0, $size - (int) $m[2]);
            } else {
                $start = (int) $m[1];
                $end = $m[2] !== '' ? min((int) $m[2], $size - 1) : $end;
            }
            if ($start > $end || $start >= $size) {
                http_response_code(416);
                header('Content-Range: bytes */' . $size);
                exit;
            }
            $status = 206;
        }
    }
    http_response_code($status);
    header('Content-Type: ' . $type);
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex, nofollow');
    header("Content-Security-Policy: default-src 'none'; sandbox");
    if ($downloadName !== '') {
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    }
    if ($status === 206) {
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    }
    header('Content-Length: ' . ($end - $start + 1));
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'HEAD') {
        exit;
    }
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        exit;
    }
    fseek($handle, $start);
    $left = $end - $start + 1;
    while ($left > 0 && !feof($handle)) {
        $chunk = fread($handle, min(65536, $left));
        if ($chunk === false || $chunk === '') {
            break;
        }
        echo $chunk;
        $left -= strlen($chunk);
        if (connection_aborted()) {
            break;
        }
    }
    fclose($handle);
    exit;
}

/* ---- Admin: Playtest reports ---- */

/**
 * @return array{status: int, error: string}
 */
function tylilo_playtest_admin_delete(array $me, string $id, string $csrf): array
{
    if (!tylilo_admin_can($me, 'arcade_publish')) {
        return ['status' => 403, 'error' => ''];
    }
    if (!tylilo_admin_csrf_ok($csrf)) {
        return ['status' => 403, 'error' => ''];
    }
    $found = tylilo_playtest_find($id);
    if ($found === null) {
        return ['status' => 422, 'error' => 'That report was already removed.'];
    }
    $game = (string) $found['meta']['game'];
    if (!tylilo_playtest_delete($id)) {
        return ['status' => 422, 'error' => 'That report could not be deleted. Please try again.'];
    }
    tylilo_admin_log((int) $me['id'], (string) ($me['username'] ?? ''), 'playtest-delete', $game . ' ' . $id);

    return ['status' => 204, 'error' => ''];
}

function tylilo_playtest_admin_when(string $iso): string
{
    $time = strtotime($iso);
    if ($time === false) {
        return $iso;
    }
    $zone = new DateTimeZone('America/New_York');

    return (new DateTimeImmutable('@' . $time))->setTimezone($zone)->format('M j, Y, g:i A T');
}

function tylilo_playtest_admin_html(): string
{
    tylilo_playtest_purge();
    $items = array_reverse(tylilo_playtest_list());
    $file = tylilo_playtest_ini_bytes('upload_max_filesize');
    $post = tylilo_playtest_ini_bytes('post_max_size');
    $html = '<section class="playtest-reports" aria-labelledby="playtest-heading">';
    $html .= '<div class="card"><h2 id="playtest-heading">Playtest reports</h2>';
    $html .= '<p>Screen recordings and notes sent by approved testers from inside a game. They are stored privately and deleted automatically after ' . TYLILO_PLAYTEST_RETENTION_DAYS . ' days.</p>';
    $html .= '<p class="playtest-limits">Upload limit: <strong>' . tylilo_e(tylilo_playtest_size_label(tylilo_playtest_max_bytes())) . '</strong>'
        . ' (server upload_max_filesize ' . ($file > 0 ? tylilo_e(tylilo_playtest_size_label($file)) : 'unlimited')
        . ', post_max_size ' . ($post > 0 ? tylilo_e(tylilo_playtest_size_label($post)) : 'unlimited') . ').</p></div>';
    if ($items === []) {
        $html .= '<div class="card"><p>No playtest reports yet.</p></div>';
    }
    foreach ($items as $meta) {
        $id = (string) $meta['id'];
        $headingId = 'playtest-' . $id;
        $src = '/admin/beta/playtest-video.php?id=' . rawurlencode($id);
        $title = tylilo_beta_game((string) $meta['game'])['title'] ?? (string) $meta['game'];
        $html .= '<article class="card playtest-card" aria-labelledby="' . tylilo_e($headingId) . '">';
        $html .= '<p class="kicker">' . tylilo_e($title) . '</p>';
        $html .= '<h3 id="' . tylilo_e($headingId) . '">Report from ' . tylilo_e((string) ($meta['user'] ?? 'a tester')) . '</h3>';
        $html .= '<p class="playtest-meta">' . tylilo_e(tylilo_playtest_admin_when((string) ($meta['receivedAt'] ?? '')))
            . ' · Version ' . tylilo_e(($meta['version'] ?? '') !== '' ? (string) $meta['version'] : 'not given')
            . ' · ' . tylilo_e(tylilo_playtest_size_label((int) ($meta['size'] ?? 0)))
            . ' · ' . tylilo_e((string) ($meta['type'] ?? '')) . '</p>';
        $html .= '<video class="playtest-video" controls preload="metadata" src="' . tylilo_e($src) . '" aria-label="Screen recording from ' . tylilo_e((string) ($meta['user'] ?? 'a tester')) . '">'
            . '<a href="' . tylilo_e($src) . '">Open the recording</a></video>';
        $html .= '<p class="playtest-note-label"><strong>Notes</strong></p>';
        $html .= '<p class="playtest-notes">' . nl2br(tylilo_e((string) ($meta['notes'] ?? '')), false) . '</p>';
        $html .= '<p class="playtest-small">Report ID ' . tylilo_e($id) . ' · md5 ' . tylilo_e((string) ($meta['md5'] ?? '')) . '</p>';
        $who = tylilo_e((string) ($meta['user'] ?? 'a tester'));
        $html .= '<details class="playtest-delete"><summary class="btn">Delete this report</summary>';
        $html .= '<form method="post" action="/admin/beta/">';
        $html .= '<p>This permanently removes the recording and its notes.</p>';
        $html .= '<input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '">';
        $html .= '<input type="hidden" name="action" value="playtest-delete">';
        $html .= '<input type="hidden" name="id" value="' . tylilo_e($id) . '">';
        $html .= '<button class="btn playtest-delete-btn" type="submit">Yes, delete the report from ' . $who . '</button>';
        $html .= '</form></details></article>';
    }
    $html .= '</section>';

    return $html;
}
