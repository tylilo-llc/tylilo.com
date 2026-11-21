<?php

declare(strict_types=1);

if (!defined('TYLILO_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/turnstile.php';

function tylilo_admin_boot(): void
{
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store, private');
    tylilo_session_start();
    tylilo_admin_with(static function (array $data): array {
        if (empty($data['seeded'])) {
            $data = tylilo_admin_seed($data);
            $data['seeded'] = true;
        }

        return $data;
    });
}

function tylilo_admin_blank(): array
{
    return [
        'users' => [],
        'attempts' => [],
        'notes' => [],
        'todos' => [],
        'versions' => [],
        'seeded' => false,
    ];
}

function tylilo_admin_store_path(string $ext): string
{
    $dir = tylilo_turnstile_dir();
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Admin storage is not available.');
    }

    return $dir . '/admin.' . $ext;
}

function tylilo_admin_uses_sqlite(): bool
{
    $force = getenv('TYLILO_ADMIN_STORE');
    $available = class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true);
    if ($force === 'json') {
        return false;
    }

    return $available;
}

function tylilo_admin_with(callable $change): array
{
    if (tylilo_admin_uses_sqlite()) {
        return tylilo_admin_with_sqlite($change);
    }

    return tylilo_admin_with_json($change);
}

function tylilo_admin_with_json(callable $change): array
{
    $path = tylilo_admin_store_path('json');
    $handle = fopen($path, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Admin storage is not available.');
    }
    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Admin storage is not available.');
        }
        $raw = stream_get_contents($handle);
        $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            $data = tylilo_admin_blank();
        }
        $data += tylilo_admin_blank();
        $data = $change($data);
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            throw new RuntimeException('Admin storage is not available.');
        }
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, $encoded);
        fflush($handle);
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    return $data;
}

function tylilo_admin_pdo(): PDO
{
    $pdo = new PDO('sqlite:' . tylilo_admin_store_path('sqlite'));
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('CREATE TABLE IF NOT EXISTS admin_state (id INTEGER PRIMARY KEY CHECK (id = 1), json TEXT NOT NULL)');
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL CHECK (role IN (\'admin\', \'member\')),
            totp_secret TEXT NOT NULL DEFAULT \'\',
            totp_enabled INTEGER NOT NULL DEFAULT 0,
            recovery_hashes TEXT NOT NULL DEFAULT \'[]\',
            created_at INTEGER NOT NULL
        )'
    );

    return $pdo;
}

function tylilo_admin_with_sqlite(callable $change): array
{
    $pdo = tylilo_admin_pdo();
    $pdo->beginTransaction();
    try {
        $row = $pdo->query('SELECT json FROM admin_state WHERE id = 1')->fetch();
        $data = is_array($row) ? json_decode((string) $row['json'], true) : null;
        if (!is_array($data)) {
            $data = tylilo_admin_blank();
        }
        $data += tylilo_admin_blank();
        $storedUsers = tylilo_admin_users_from_table($pdo);
        if ($storedUsers !== []) {
            $data['users'] = $storedUsers;
        }
        $data = $change($data);
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pdo->exec('DELETE FROM admin_state');
        $stmt = $pdo->prepare('INSERT INTO admin_state (id, json) VALUES (1, ?)');
        $stmt->execute([$encoded]);
        tylilo_admin_write_users($pdo, $data['users']);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }

    return $data;
}

function tylilo_admin_users_from_table(PDO $pdo): array
{
    $rows = $pdo->query('SELECT id, username, password_hash, role, totp_secret, totp_enabled, recovery_hashes, created_at FROM users ORDER BY id')->fetchAll();
    $users = [];
    foreach ($rows as $row) {
        $users[] = [
            'id' => (int) $row['id'],
            'username' => (string) $row['username'],
            'password_hash' => (string) $row['password_hash'],
            'role' => (string) $row['role'],
            'totp_secret' => (string) $row['totp_secret'],
            'totp_enabled' => (int) $row['totp_enabled'],
            'recovery_hashes' => (string) $row['recovery_hashes'],
            'created_at' => (int) $row['created_at'],
        ];
    }

    return $users;
}

function tylilo_admin_write_users(PDO $pdo, array $users): void
{
    $pdo->exec('DELETE FROM users');
    $stmt = $pdo->prepare('INSERT INTO users (id, username, password_hash, role, totp_secret, totp_enabled, recovery_hashes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($users as $user) {
        $role = (string) ($user['role'] ?? '');
        if ($role !== 'admin' && $role !== 'member') {
            throw new RuntimeException('A user role must be admin or member.');
        }
        $stmt->execute([
            (int) $user['id'],
            (string) $user['username'],
            (string) $user['password_hash'],
            $role,
            (string) ($user['totp_secret'] ?? ''),
            (int) ($user['totp_enabled'] ?? 0),
            (string) ($user['recovery_hashes'] ?? '[]'),
            (int) ($user['created_at'] ?? time()),
        ]);
    }
}

function tylilo_admin_next_id(array $rows): int
{
    $max = 0;
    foreach ($rows as $row) {
        $max = max($max, (int) ($row['id'] ?? 0));
    }

    return $max + 1;
}

function tylilo_admin_seed(array $data): array
{
    if ($data['versions'] !== []) {
        return $data;
    }
    $now = time();
    $seed = [
        ['solitaire', '1.1.5', '2026-11-21', '076cbe377db1d332f23e3d895c6b79b7', 'Update Solitaire to 1.1.5.'],
        ['solitaire', '1.1.3', '2026-11-21', '6d9dfcddf6931bfa29d81349fd9fcc18', 'Use rounded Arcade tiles and write Tylilo, LLC.'],
        ['solitaire', '1.1.2', '2026-11-21', '9c5e8368ca46326b9f246c87570c85fa', 'Add an Arcade with Solitaire, Red Rain, and Pop Spiral.'],
        ['red-rain', '2.2.4', '2026-11-21', 'd6e4c40c9618f45eac980d09ba541e19', 'Update Red Rain to 2.2.4.'],
        ['red-rain', '2.2.2', '2026-11-21', 'd22f1cadfed10b9826e26af2fda1572b', 'Update Red Rain to 2.2.2.'],
        ['red-rain', '2.2.1', '2026-11-21', '68b0b01064c45697ac3ddb18b0f55e7d', 'Use rounded Arcade tiles and write Tylilo, LLC.'],
        ['red-rain', '2.2', '2026-11-21', 'ed8262fbd655c88ef319f2a8383e39c0', 'Add an Arcade with Solitaire, Red Rain, and Pop Spiral.'],
        ['pop-spiral', '0.2.1', '2026-11-21', 'a34993035f1db78210488bf387b83441', 'Cache-bust local assets and ship Pop Spiral 0.2.1.'],
        ['pop-spiral', '0.2.0', '2026-11-21', 'f1ac5b0dac2943f51bf7190016b3b011', 'Add an Arcade with Solitaire, Red Rain, and Pop Spiral.'],
        ['jungle-rescue', '2.14', '2026-11-21', '0247818561223ad096e64a6780cd4fcd', 'Update Jungle Rescue to 2.14.'],
        ['jungle-rescue', '2.12', '2026-11-21', '2fcf34dd1b56ba9f071f7074e24f63d1', 'Add Jungle Rescue to the arcade.'],
    ];
    foreach ($seed as $row) {
        $data['versions'][] = [
            'id' => tylilo_admin_next_id($data['versions']),
            'slug' => $row[0],
            'version' => $row[1],
            'released_on' => $row[2],
            'md5' => $row[3],
            'notes' => $row[4],
            'created_at' => $now,
        ];
    }

    return $data;
}

function tylilo_admin_game_slugs(): array
{
    $dir = dirname(__DIR__) . '/arcade';
    $slugs = [];
    if (is_dir($dir)) {
        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '' || $name[0] === '.') {
                continue;
            }
            if (is_file($dir . '/' . $name . '/index.html')) {
                $slugs[] = $name;
            }
        }
    }
    $data = tylilo_admin_with(static fn (array $stored): array => $stored);
    foreach ($data['versions'] as $row) {
        $slugs[] = (string) $row['slug'];
    }
    $slugs = array_values(array_unique($slugs));
    sort($slugs);

    return $slugs;
}

function tylilo_admin_ip_hash(string $ip): string
{
    $path = tylilo_turnstile_dir() . '/admin-ip.salt';
    if (!is_file($path)) {
        file_put_contents($path, random_bytes(32), LOCK_EX);
        @chmod($path, 0600);
    }
    $salt = file_get_contents($path);
    if (!is_string($salt) || strlen($salt) < 16) {
        $salt = random_bytes(32);
        file_put_contents($path, $salt, LOCK_EX);
    }

    return hash_hmac('sha256', $ip, $salt);
}

function tylilo_admin_hash_password(string $password): string
{
    $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;

    return password_hash($password, $algo);
}

function tylilo_admin_setup_code(): string
{
    $env = getenv('ADMIN_SETUP_CODE');
    if (is_string($env) && $env !== '') {
        return $env;
    }

    return tylilo_private_php_value('admin-setup.php');
}

function tylilo_admin_has_admin(): bool
{
    $data = tylilo_admin_with(static fn (array $stored): array => $stored);
    foreach ($data['users'] as $user) {
        if (($user['role'] ?? '') === 'admin') {
            return true;
        }
    }

    return false;
}

function tylilo_admin_user_by_name(string $username): ?array
{
    $data = tylilo_admin_with(static fn (array $stored): array => $stored);
    foreach ($data['users'] as $user) {
        if (hash_equals((string) $user['username'], $username)) {
            return $user;
        }
    }

    return null;
}

function tylilo_admin_user_by_id(int $id): ?array
{
    $data = tylilo_admin_with(static fn (array $stored): array => $stored);
    foreach ($data['users'] as $user) {
        if ((int) $user['id'] === $id) {
            return $user;
        }
    }

    return null;
}

function tylilo_admin_csrf(): string
{
    tylilo_session_start();
    $token = $_SESSION['admin_csrf'] ?? '';
    if (!is_string($token) || strlen($token) < 32) {
        $token = bin2hex(random_bytes(32));
        $_SESSION['admin_csrf'] = $token;
    }

    return $token;
}

function tylilo_admin_csrf_ok(string $token): bool
{
    tylilo_session_start();
    $expected = $_SESSION['admin_csrf'] ?? '';

    return is_string($expected) && $expected !== '' && hash_equals($expected, $token);
}

function tylilo_admin_locked(string $ipHash, string $username, int $now): bool
{
    $data = tylilo_admin_with(static fn (array $stored): array => $stored);
    $ipFails = 0;
    $nameFails = 0;
    foreach ($data['attempts'] as $row) {
        if ((int) $row['ok'] === 1 || ($now - (int) $row['at']) >= 900) {
            continue;
        }
        if ($row['ip_hash'] === $ipHash) {
            $ipFails++;
        }
        if (hash_equals((string) $row['username'], $username)) {
            $nameFails++;
        }
    }

    return $ipFails >= 5 || $nameFails >= 5;
}

function tylilo_admin_note_attempt(string $ipHash, string $username, bool $ok, int $now): void
{
    tylilo_admin_with(static function (array $data) use ($ipHash, $username, $ok, $now): array {
        $kept = [];
        foreach ($data['attempts'] as $row) {
            if (($now - (int) $row['at']) < 86400) {
                $kept[] = $row;
            }
        }
        $kept[] = [
            'ip_hash' => $ipHash,
            'username' => $username,
            'at' => $now,
            'ok' => $ok ? 1 : 0,
        ];
        $data['attempts'] = $kept;

        return $data;
    });
}

function tylilo_admin_current(): ?array
{
    tylilo_session_start();
    $id = $_SESSION['admin_user_id'] ?? 0;

    return is_int($id) && $id > 0 ? tylilo_admin_user_by_id($id) : null;
}

function tylilo_admin_markdown(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $blocks = preg_split("/\n{2,}/", trim($text)) ?: [];
    $html = '';
    foreach ($blocks as $block) {
        $lines = explode("\n", $block);
        if (count($lines) === 1 && preg_match('/^(#{1,3}) (.+)$/', $lines[0], $match) === 1) {
            $level = strlen($match[1]) + 1;
            $html .= '<h' . $level . '>' . tylilo_admin_inline($match[2]) . '</h' . $level . '>';
            continue;
        }
        $list = $lines !== [] && !in_array(false, array_map(static fn (string $line): bool => str_starts_with($line, '- '), $lines), true);
        if ($list) {
            $html .= '<ul>';
            foreach ($lines as $line) {
                $html .= '<li>' . tylilo_admin_inline(substr($line, 2)) . '</li>';
            }
            $html .= '</ul>';
            continue;
        }
        $html .= '<p>' . tylilo_admin_inline(implode("\n", $lines)) . '</p>';
    }

    return $html;
}

function tylilo_admin_inline(string $text): string
{
    $safe = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safe = preg_replace('/`([^`\n]+)`/', '<code>$1</code>', $safe) ?? $safe;
    $safe = preg_replace('/\*\*([^*\n]+)\*\*/', '<strong>$1</strong>', $safe) ?? $safe;
    $safe = preg_replace_callback('/\[([^\]\n]+)\]\((https?:\/\/[^\s)]+)\)/', static function (array $match): string {
        $url = htmlspecialchars_decode($match[2], ENT_QUOTES);
        if (preg_match('#\Ahttps?://#', $url) !== 1) {
            return $match[1];
        }

        return '<a href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . $match[1] . '</a>';
    }, $safe) ?? $safe;

    return nl2br($safe, false);
}

function tylilo_admin_base32(string $binary): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    $length = strlen($binary);
    for ($i = 0; $i < $length; $i++) {
        $bits .= str_pad(decbin(ord($binary[$i])), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        if (strlen($chunk) < 5) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        }
        $out .= $alphabet[bindec($chunk)];
    }

    return $out;
}

function tylilo_admin_base32_decode(string $text): string
{
    $alphabet = array_flip(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'));
    $text = strtoupper(preg_replace('/[^A-Z2-7]/', '', $text) ?? '');
    $bits = '';
    $length = strlen($text);
    for ($i = 0; $i < $length; $i++) {
        if (!isset($alphabet[$text[$i]])) {
            continue;
        }
        $bits .= str_pad(decbin($alphabet[$text[$i]]), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $chunk) {
        if (strlen($chunk) === 8) {
            $out .= chr(bindec($chunk));
        }
    }

    return $out;
}

function tylilo_admin_hotp(string $secret, int $counter): string
{
    $bin = pack('N2', 0, $counter);
    $hash = hash_hmac('sha1', $bin, $secret, true);
    $offset = ord($hash[19]) & 0x0f;
    $value = ((ord($hash[$offset]) & 0x7f) << 24)
        | ((ord($hash[$offset + 1]) & 0xff) << 16)
        | ((ord($hash[$offset + 2]) & 0xff) << 8)
        | (ord($hash[$offset + 3]) & 0xff);

    return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
}

function tylilo_admin_totp_ok(string $secret, string $code, int $now): bool
{
    $code = preg_replace('/\s+/', '', $code) ?? '';
    if (preg_match('/^\d{6}$/', $code) !== 1) {
        return false;
    }
    $counter = intdiv($now, 30);
    for ($delta = -1; $delta <= 1; $delta++) {
        if (hash_equals(tylilo_admin_hotp($secret, $counter + $delta), $code)) {
            return true;
        }
    }

    return false;
}
