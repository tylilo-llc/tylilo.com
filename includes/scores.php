<?php

declare(strict_types=1);

/**
 * Shared arcade leaderboard. No HTML headers here; api/scores.php is the HTTP edge.
 * Game files are not modified. Bots call the API described in AGENTS.md.
 */

const TYLILO_SCORE_LIMIT_DEFAULT = 10;
const TYLILO_SCORE_LIMIT_MAX = 25;
const TYLILO_SCORE_KEEP = 100;
const TYLILO_SCORE_RATE_MAX = 5;
const TYLILO_SCORE_RATE_WINDOW = 600;
const TYLILO_SCORE_TOKEN_TTL = 21600;
const TYLILO_SCORE_BODY_MAX = 4096;

function tylilo_score_games(): array
{
    $points = static function (string $label, int $max, int $minPlayedMs): array {
        return [
            'label' => $label,
            'order' => 'desc',
            'unit' => 'points',
            'min' => 0,
            'max' => $max,
            'minPlayedMs' => $minPlayedMs,
        ];
    };

    return [
        'solitaire' => [
            'title' => 'Solitaire',
            'boards' => [
                'draw-1' => $points('Draw 1', 20000, 20000),
                'draw-3' => $points('Draw 3', 20000, 20000),
            ],
        ],
        'red-rain' => [
            'title' => 'Red Rain',
            'boards' => [
                'easy' => $points('Easy', 5000000, 30000),
                'normal' => $points('Normal', 5000000, 30000),
                'expert' => $points('Expert', 5000000, 30000),
                'insane' => $points('Insane', 5000000, 30000),
            ],
        ],
        'pop-spiral' => [
            'title' => 'Pop Spiral',
            'boards' => [
                'default' => $points('Score', 5000000, 20000),
            ],
        ],
        'jungle-rescue' => [
            'title' => 'Jungle Rescue',
            'boards' => [
                'default' => $points('Score', 5000000, 20000),
                'time' => [
                    'label' => 'Time',
                    'order' => 'asc',
                    'unit' => 'seconds',
                    'min' => 1,
                    'max' => 7200,
                    'minPlayedMs' => 20000,
                ],
            ],
        ],
    ];
}

function tylilo_score_now(): int
{
    $fixed = getenv('TYLILO_SCORE_NOW');
    if (is_string($fixed) && ctype_digit($fixed)) {
        return (int) $fixed;
    }

    return time();
}

function tylilo_score_strlen(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }
    $count = preg_match_all('/./u', $value, $unused);

    return $count === false ? 0 : $count;
}

function tylilo_scores_dir(): string
{
    $fromEnv = getenv('TYLILO_DATA_DIR');
    if (is_string($fromEnv) && $fromEnv !== '') {
        return rtrim($fromEnv, '/');
    }

    $site = dirname(__DIR__);
    $parent = dirname($site);
    if ($parent !== '/' && $parent !== $site && is_dir($parent) && is_writable($parent)) {
        $private = $parent . '/tylilo-data';
        if (!is_dir($private)) {
            @mkdir($private, 0700, true);
        }
        $realPrivate = realpath($private);
        $doc = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $realDoc = (is_string($doc) && $doc !== '' && is_dir($doc)) ? realpath($doc) : false;
        $insideWeb = $realPrivate && $realDoc
            && str_starts_with($realPrivate . DIRECTORY_SEPARATOR, $realDoc . DIRECTORY_SEPARATOR);
        if ($realPrivate && !$insideWeb) {
            return $realPrivate;
        }
    }

    $fallback = $site . '/data';
    if (!is_dir($fallback)) {
        @mkdir($fallback, 0700, true);
    }

    return $fallback;
}

function tylilo_score_uses_sqlite(): bool
{
    $force = getenv('TYLILO_SCORE_STORE');
    $available = class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true);
    if ($force === 'json') {
        return false;
    }
    if ($force === 'sqlite') {
        return $available;
    }

    return $available;
}

function tylilo_score_banned_words(): array
{
    return [
        'fuck', 'fack', 'fuk', 'fck', 'shit', 'shyt', 'ass', 'asshole', 'bitch',
        'cunt', 'dick', 'cock', 'pussy', 'slut', 'whore', 'fag', 'faggot',
        'nigger', 'nigga', 'retard', 'twat', 'wank', 'prick', 'tits', 'tit',
        'bollocks', 'damn', 'piss', 'crap', 'dyke', 'spastic', 'chink', 'kike',
    ];
}

function tylilo_score_fold(string $value): string
{
    $text = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $text = strtr($text, [
        '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '@' => 'a', '$' => 's',
    ]);
    $text = preg_replace('/[^\p{L}]+/u', '', $text) ?? '';
    $collapsed = preg_replace('/(\p{L})\1{2,}/u', '$1', $text) ?? $text;

    return $collapsed;
}

function tylilo_score_fold_pairs(string $value): string
{
    $folded = tylilo_score_fold($value);

    return preg_replace('/(\p{L})\1+/u', '$1', $folded) ?? $folded;
}

function tylilo_score_is_banned(string $folded): bool
{
    return $folded !== '' && in_array($folded, tylilo_score_banned_words(), true);
}

function tylilo_score_is_profane(string $name): bool
{
    if (tylilo_score_is_banned(tylilo_score_fold($name)) || tylilo_score_is_banned(tylilo_score_fold_pairs($name))) {
        return true;
    }
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    foreach ($parts as $part) {
        if (tylilo_score_is_banned(tylilo_score_fold($part)) || tylilo_score_is_banned(tylilo_score_fold_pairs($part))) {
            return true;
        }
    }

    return false;
}

function tylilo_score_clean_name(string $name): array
{
    $name = preg_replace('/\s+/u', ' ', trim($name)) ?? '';
    $length = tylilo_score_strlen($name);
    if ($length < 1 || $length > 12 || !preg_match('/^[\p{L}\p{M}0-9 .\'\\-!?,]+$/u', $name)) {
        return ['ok' => false, 'error' => 'Use 1–12 letters, numbers, spaces, or simple punctuation.'];
    }
    if (tylilo_score_is_profane($name)) {
        return ['ok' => false, 'error' => 'Please pick a different name.'];
    }

    return ['ok' => true, 'name' => $name];
}

function tylilo_score_board(string $game, string $board): ?array
{
    $games = tylilo_score_games();
    if (!isset($games[$game]['boards'][$board]) || !is_array($games[$game]['boards'][$board])) {
        return null;
    }

    return $games[$game]['boards'][$board];
}

function tylilo_score_secret(string $filename): string
{
    $dir = tylilo_scores_dir();
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Score storage is not available.');
    }
    $path = $dir . '/' . $filename;
    if (is_file($path)) {
        $key = file_get_contents($path);
        if (is_string($key) && strlen($key) >= 32) {
            return $key;
        }
    }
    $key = random_bytes(32);
    file_put_contents($path, $key, LOCK_EX);
    @chmod($path, 0600);

    return $key;
}

function tylilo_score_ip_hash(string $ip): string
{
    return hash_hmac('sha256', $ip, tylilo_score_secret('ip.salt'));
}

function tylilo_score_issue_token(string $game, string $board, int $now): string
{
    $body = rtrim(strtr(base64_encode(json_encode([
        'g' => $game,
        'b' => $board,
        'iat' => $now,
        'exp' => $now + TYLILO_SCORE_TOKEN_TTL,
        'n' => bin2hex(random_bytes(8)),
    ], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    $sig = hash_hmac('sha256', $body, tylilo_score_secret('hmac.key'));

    return $body . '.' . $sig;
}

function tylilo_score_read_token(string $token, int $now): ?array
{
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
        return null;
    }
    $expect = hash_hmac('sha256', $parts[0], tylilo_score_secret('hmac.key'));
    if (!hash_equals($expect, $parts[1])) {
        return null;
    }
    $json = base64_decode(strtr($parts[0], '-_', '+/'), true);
    $payload = is_string($json) ? json_decode($json, true) : null;
    if (!is_array($payload) || !isset($payload['g'], $payload['b'], $payload['iat'], $payload['exp'])) {
        return null;
    }
    if ((int) $payload['exp'] < $now || (int) $payload['iat'] > $now) {
        return null;
    }

    return [
        'game' => (string) $payload['g'],
        'board' => (string) $payload['b'],
        'iat' => (int) $payload['iat'],
    ];
}

function tylilo_scores_blank(): array
{
    return ['scores' => [], 'rates' => [], 'used' => [], 'seeds' => []];
}

/**
 * One recorded score that the game never submitted. Applied once, then remembered
 * so later deploys do not insert it again. Safe to delete this list after it has run.
 *
 * @return list<array{id: string, game: string, board: string, name: string, score: int, at: int}>
 */
function tylilo_score_seeds(): array
{
    return [
        [
            'id' => 'logan-jungle-rescue-187100',
            'game' => 'jungle-rescue',
            'board' => 'default',
            'name' => 'LOGAN',
            'score' => 187100,
            'at' => 1795271100,
        ],
    ];
}

function tylilo_score_ensure_seeds(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    tylilo_scores_with(static function (array $data): array {
        $applied = is_array($data['seeds'] ?? null) ? $data['seeds'] : [];
        foreach (tylilo_score_seeds() as $seed) {
            if (in_array($seed['id'], $applied, true)) {
                continue;
            }
            $present = false;
            foreach ($data['scores'] as $row) {
                if ($row['game'] === $seed['game'] && $row['board'] === $seed['board'] && $row['name'] === $seed['name'] && (int) $row['score'] === $seed['score']) {
                    $present = true;
                    break;
                }
            }
            if (!$present) {
                $data['scores'][] = [
                    'game' => $seed['game'],
                    'board' => $seed['board'],
                    'name' => $seed['name'],
                    'score' => $seed['score'],
                    'at' => $seed['at'],
                ];
            }
            $applied[] = $seed['id'];
        }
        $data['seeds'] = $applied;

        return $data;
    });
}

function tylilo_scores_with(callable $change): array
{
    if (tylilo_score_uses_sqlite()) {
        return tylilo_scores_with_sqlite($change);
    }

    return tylilo_scores_with_json($change);
}

function tylilo_scores_with_json(callable $change): array
{
    $dir = tylilo_scores_dir();
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Score storage is not available.');
    }
    $path = $dir . '/scores.json';
    $handle = fopen($path, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Score storage is not available.');
    }
    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Score storage is not available.');
        }
        $raw = stream_get_contents($handle);
        $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            $data = tylilo_scores_blank();
        }
        $data += tylilo_scores_blank();
        $data = $change($data);
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            throw new RuntimeException('Score storage is not available.');
        }
        rewind($handle);
        ftruncate($handle, 0);
        $written = fwrite($handle, $encoded);
        if ($written === false || $written !== strlen($encoded)) {
            throw new RuntimeException('Score storage is not available.');
        }
        fflush($handle);
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    return $data;
}

function tylilo_scores_pdo(): PDO
{
    $dir = tylilo_scores_dir();
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Score storage is not available.');
    }
    $pdo = new PDO('sqlite:' . $dir . '/scores.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('CREATE TABLE IF NOT EXISTS scores (game TEXT, board TEXT, name TEXT, score INTEGER, at INTEGER, version TEXT)');
    $columns = array_column($pdo->query('PRAGMA table_info(scores)')->fetchAll(), 'name');
    if (!in_array('version', $columns, true)) {
        $pdo->exec('ALTER TABLE scores ADD COLUMN version TEXT');
    }
    $pdo->exec('CREATE TABLE IF NOT EXISTS rates (hash TEXT, at INTEGER)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS used_tokens (hash TEXT PRIMARY KEY, exp INTEGER)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS seeds (id TEXT PRIMARY KEY)');

    return $pdo;
}

function tylilo_scores_with_sqlite(callable $change): array
{
    $pdo = tylilo_scores_pdo();
    $pdo->beginTransaction();
    try {
        $data = tylilo_scores_blank();
        foreach ($pdo->query('SELECT game, board, name, score, at, version FROM scores') as $row) {
            $data['scores'][] = tylilo_score_normalize_row([
                'game' => (string) $row['game'],
                'board' => (string) $row['board'],
                'name' => (string) $row['name'],
                'score' => (int) $row['score'],
                'at' => (int) $row['at'],
                'version' => $row['version'],
            ]);
        }
        foreach ($pdo->query('SELECT hash, at FROM rates') as $row) {
            $data['rates'][] = ['hash' => (string) $row['hash'], 'at' => (int) $row['at']];
        }
        foreach ($pdo->query('SELECT hash, exp FROM used_tokens') as $row) {
            $data['used'][] = ['hash' => (string) $row['hash'], 'exp' => (int) $row['exp']];
        }
        foreach ($pdo->query('SELECT id FROM seeds') as $row) {
            $data['seeds'][] = (string) $row['id'];
        }
        $data = $change($data);
        $pdo->exec('DELETE FROM scores');
        $pdo->exec('DELETE FROM rates');
        $pdo->exec('DELETE FROM used_tokens');
        $pdo->exec('DELETE FROM seeds');
        $scoreStmt = $pdo->prepare('INSERT INTO scores (game, board, name, score, at, version) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($data['scores'] as $row) {
            $row = tylilo_score_normalize_row($row);
            $scoreStmt->execute([$row['game'], $row['board'], $row['name'], $row['score'], $row['at'], $row['version']]);
        }
        $rateStmt = $pdo->prepare('INSERT INTO rates (hash, at) VALUES (?, ?)');
        foreach ($data['rates'] as $row) {
            $rateStmt->execute([$row['hash'], $row['at']]);
        }
        $usedStmt = $pdo->prepare('INSERT INTO used_tokens (hash, exp) VALUES (?, ?)');
        foreach ($data['used'] as $row) {
            $usedStmt->execute([$row['hash'], $row['exp']]);
        }
        $seedStmt = $pdo->prepare('INSERT INTO seeds (id) VALUES (?)');
        foreach ($data['seeds'] ?? [] as $id) {
            if (is_string($id) && $id !== '') {
                $seedStmt->execute([$id]);
            }
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }

    return $data;
}

function tylilo_score_sort(array $rows, string $order): array
{
    usort($rows, static function (array $a, array $b) use ($order): int {
        if ($a['score'] !== $b['score']) {
            $higher = $a['score'] <=> $b['score'];

            return $order === 'asc' ? $higher : -$higher;
        }

        return $a['at'] <=> $b['at'];
    });

    return $rows;
}

function tylilo_score_normalize_row(array $row): array
{
    $version = $row['version'] ?? null;
    if (!is_string($version) || $version === '') {
        $version = null;
    }

    return [
        'game' => (string) ($row['game'] ?? ''),
        'board' => (string) ($row['board'] ?? ''),
        'name' => (string) ($row['name'] ?? ''),
        'score' => (int) ($row['score'] ?? 0),
        'at' => (int) ($row['at'] ?? 0),
        'version' => $version,
    ];
}

/**
 * @return array{ok: bool, version: ?string, error: string}
 */
function tylilo_score_clean_version(mixed $value): array
{
    if ($value === null || $value === '') {
        return ['ok' => true, 'version' => null, 'error' => ''];
    }
    if (!is_string($value) || preg_match('/^[0-9A-Za-z._-]{1,16}$/', $value) !== 1) {
        return ['ok' => false, 'version' => null, 'error' => 'Use a version of up to 16 letters, numbers, dots, underscores, or hyphens.'];
    }

    return ['ok' => true, 'version' => $value, 'error' => ''];
}

function tylilo_score_public_rows(array $data, string $game, string $board, string $order, int $limit): array
{
    $rows = [];
    foreach ($data['scores'] as $row) {
        if ($row['game'] === $game && $row['board'] === $board) {
            $rows[] = $row;
        }
    }
    $rows = tylilo_score_sort($rows, $order);
    $rows = array_slice($rows, 0, $limit);
    $public = [];
    foreach ($rows as $row) {
        $public[] = [
            'name' => $row['name'],
            'score' => $row['score'],
            'at' => gmdate('c', $row['at']),
        ];
    }

    return $public;
}

function tylilo_score_list(string $game, ?string $board, int $limit): array
{
    $games = tylilo_score_games();
    if (!isset($games[$game])) {
        return ['ok' => false, 'status' => 404, 'error' => 'That game does not have a leaderboard.'];
    }
    $limit = max(1, min(TYLILO_SCORE_LIMIT_MAX, $limit));
    $wanted = $board === null || $board === '' ? array_keys($games[$game]['boards']) : [$board];
    $data = tylilo_scores_with(static fn (array $stored): array => $stored);
    $boards = [];
    foreach ($wanted as $id) {
        $spec = tylilo_score_board($game, $id);
        if ($spec === null) {
            return ['ok' => false, 'status' => 404, 'error' => 'That board does not exist.'];
        }
        $boards[] = [
            'board' => $id,
            'label' => $spec['label'],
            'order' => $spec['order'],
            'unit' => $spec['unit'],
            'scores' => tylilo_score_public_rows($data, $game, $id, $spec['order'], $limit),
        ];
    }

    return ['ok' => true, 'status' => 200, 'game' => $game, 'limit' => $limit, 'boards' => $boards];
}

function tylilo_score_origin_ok(array $server): bool
{
    $host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($server['HTTP_HOST'] ?? '')));
    if ($host === '') {
        return false;
    }
    $origin = (string) ($server['HTTP_ORIGIN'] ?? '');
    if ($origin !== '') {
        $name = parse_url($origin, PHP_URL_HOST);

        return is_string($name) && strtolower($name) === $host;
    }
    $referer = (string) ($server['HTTP_REFERER'] ?? '');
    if ($referer !== '') {
        $name = parse_url($referer, PHP_URL_HOST);

        return is_string($name) && strtolower($name) === $host;
    }

    return false;
}

function tylilo_score_whole_number(mixed $value): ?int
{
    if (is_int($value)) {
        return $value;
    }
    if (is_float($value) && floor($value) === $value) {
        return (int) $value;
    }
    if (is_string($value) && preg_match('/^-?\d+$/', $value)) {
        return (int) $value;
    }

    return null;
}

function tylilo_score_start(string $game, string $board, int $now): array
{
    $spec = tylilo_score_board($game, $board);
    if ($spec === null) {
        return ['ok' => false, 'status' => 404, 'error' => 'That game or board does not have a leaderboard.'];
    }

    return [
        'ok' => true,
        'status' => 200,
        'token' => tylilo_score_issue_token($game, $board, $now),
        'minPlayedMs' => $spec['minPlayedMs'],
        'game' => $game,
        'board' => $board,
    ];
}

function tylilo_score_submit(array $input, string $ip, int $now): array
{
    $game = is_string($input['game'] ?? null) ? $input['game'] : '';
    $board = is_string($input['board'] ?? null) ? $input['board'] : 'default';
    $spec = tylilo_score_board($game, $board);
    if ($spec === null) {
        return ['ok' => false, 'status' => 404, 'error' => 'That game or board does not have a leaderboard.'];
    }
    $name = tylilo_score_clean_name(is_string($input['name'] ?? null) ? $input['name'] : '');
    if (!$name['ok']) {
        return ['ok' => false, 'status' => 400, 'error' => $name['error']];
    }
    $score = tylilo_score_whole_number($input['score'] ?? null);
    if ($score === null || $score < $spec['min'] || $score > $spec['max']) {
        return ['ok' => false, 'status' => 400, 'error' => 'That score does not look like a real game.'];
    }
    $playedMs = tylilo_score_whole_number($input['playedMs'] ?? null);
    $version = tylilo_score_clean_version($input['version'] ?? null);
    if (!$version['ok']) {
        return ['ok' => false, 'status' => 400, 'error' => $version['error']];
    }
    $token = is_string($input['token'] ?? null) ? $input['token'] : '';
    $parsed = $token !== '' ? tylilo_score_read_token($token, $now) : null;
    if ($parsed === null || $parsed['game'] !== $game || $parsed['board'] !== $board) {
        return ['ok' => false, 'status' => 400, 'error' => 'Start a game before saving a score.'];
    }
    $elapsedMs = ($now - $parsed['iat']) * 1000;
    if ($playedMs === null || $playedMs < $spec['minPlayedMs'] || $elapsedMs < $spec['minPlayedMs'] || $playedMs > $elapsedMs + 15000) {
        return ['ok' => false, 'status' => 400, 'error' => 'Play a little longer before saving a score.'];
    }

    $tokenHash = hash('sha256', $token);
    $ipHash = tylilo_score_ip_hash($ip);
    $result = ['ok' => true, 'status' => 200];
    $storedVersion = $version['version'];
    tylilo_scores_with(static function (array $data) use ($spec, $game, $board, $name, $score, $now, $tokenHash, $ipHash, $storedVersion, &$result): array {
        $data['rates'] = array_values(array_filter(
            $data['rates'],
            static fn (array $row): bool => $row['at'] >= $now - TYLILO_SCORE_RATE_WINDOW
        ));
        $data['used'] = array_values(array_filter(
            $data['used'],
            static fn (array $row): bool => $row['exp'] >= $now
        ));
        foreach ($data['used'] as $row) {
            if ($row['hash'] === $tokenHash) {
                $result = ['ok' => false, 'status' => 400, 'error' => 'Start a game before saving a score.'];

                return $data;
            }
        }
        $recent = 0;
        foreach ($data['rates'] as $row) {
            if ($row['hash'] === $ipHash) {
                $recent++;
            }
        }
        if ($recent >= TYLILO_SCORE_RATE_MAX) {
            $result = ['ok' => false, 'status' => 429, 'error' => 'Too many scores from this network. Try again in a few minutes.'];

            return $data;
        }
        $data['used'][] = ['hash' => $tokenHash, 'exp' => $now + TYLILO_SCORE_TOKEN_TTL];
        $data['rates'][] = ['hash' => $ipHash, 'at' => $now];
        $data['scores'][] = [
            'game' => $game,
            'board' => $board,
            'name' => $name['name'],
            'score' => $score,
            'at' => $now,
            'version' => $storedVersion,
        ];
        $kept = [];
        $grouped = [];
        foreach ($data['scores'] as $row) {
            $grouped[$row['game'] . "\0" . $row['board']][] = $row;
        }
        foreach ($grouped as $rows) {
            $sample = $rows[0];
            $rule = tylilo_score_board($sample['game'], $sample['board']);
            $order = $rule['order'] ?? 'desc';
            $rows = tylilo_score_sort($rows, $order);
            foreach (array_slice($rows, 0, TYLILO_SCORE_KEEP) as $row) {
                $kept[] = $row;
            }
        }
        $data['scores'] = $kept;
        $ordered = tylilo_score_sort(array_values(array_filter(
            $kept,
            static fn (array $row): bool => $row['game'] === $game && $row['board'] === $board
        )), $spec['order']);
        $rank = null;
        foreach ($ordered as $index => $row) {
            if ($row['name'] === $name['name'] && $row['score'] === $score && $row['at'] === $now) {
                $rank = $index + 1;
                break;
            }
        }
        $result['rank'] = $rank;
        $result['scores'] = tylilo_score_public_rows($data, $game, $board, $spec['order'], TYLILO_SCORE_LIMIT_DEFAULT);

        return $data;
    });

    return $result;
}

/**
 * Scores for the private admin page, including the game version.
 *
 * @return list<array{name: string, score: int, at: int, version: ?string}>
 */
function tylilo_score_admin_rows(string $game, string $board): array
{
    $spec = tylilo_score_board($game, $board);
    if ($spec === null) {
        return [];
    }
    $data = tylilo_scores_with(static fn (array $stored): array => $stored);
    $rows = [];
    foreach ($data['scores'] as $row) {
        $row = tylilo_score_normalize_row($row);
        if ($row['game'] === $game && $row['board'] === $board) {
            $rows[] = [
                'name' => $row['name'],
                'score' => $row['score'],
                'at' => $row['at'],
                'version' => $row['version'],
            ];
        }
    }

    return tylilo_score_sort($rows, $spec['order']);
}

function tylilo_score_clear_board(string $game, string $board): void
{
    if (tylilo_score_board($game, $board) === null) {
        return;
    }
    tylilo_scores_with(static function (array $data) use ($game, $board): array {
        $data['scores'] = array_values(array_filter(
            $data['scores'],
            static fn (array $row): bool => ($row['game'] ?? '') !== $game || ($row['board'] ?? '') !== $board
        ));

        return $data;
    });
}

function tylilo_score_handle(string $method, array $query, string $body, array $server, ?int $now = null): array
{
    tylilo_score_ensure_seeds();
    $now ??= tylilo_score_now();
    $method = strtoupper($method);
    if ($method === 'GET') {
        $game = is_string($query['game'] ?? null) ? $query['game'] : '';
        $board = is_string($query['board'] ?? null) ? $query['board'] : null;
        $limit = tylilo_score_whole_number($query['limit'] ?? TYLILO_SCORE_LIMIT_DEFAULT) ?? TYLILO_SCORE_LIMIT_DEFAULT;

        return tylilo_score_list($game, $board, $limit);
    }
    if ($method !== 'POST') {
        return ['ok' => false, 'status' => 405, 'error' => 'Use GET to read scores and POST to save one.'];
    }
    if (strlen($body) > TYLILO_SCORE_BODY_MAX) {
        return ['ok' => false, 'status' => 413, 'error' => 'That score request is too large.'];
    }
    if (!tylilo_score_origin_ok($server)) {
        return ['ok' => false, 'status' => 403, 'error' => 'Scores can only be saved from this site.'];
    }
    $input = json_decode($body, true);
    if (!is_array($input)) {
        return ['ok' => false, 'status' => 400, 'error' => 'Send a JSON score request.'];
    }
    $op = is_string($input['op'] ?? null) ? $input['op'] : 'submit';
    if ($op === 'start') {
        $game = is_string($input['game'] ?? null) ? $input['game'] : '';
        $board = is_string($input['board'] ?? null) ? $input['board'] : 'default';

        return tylilo_score_start($game, $board, $now);
    }
    $ip = is_string($server['REMOTE_ADDR'] ?? null) && $server['REMOTE_ADDR'] !== '' ? $server['REMOTE_ADDR'] : '0.0.0.0';

    return tylilo_score_submit($input, $ip, $now);
}
