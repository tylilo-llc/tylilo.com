<?php

declare(strict_types=1);

if (!defined('TYLILO_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

const TYLILO_ADMIN_IDLE = 1800;
const TYLILO_ADMIN_ABSOLUTE = 43200;
const TYLILO_ADMIN_WARN = 120;
const TYLILO_ADMIN_INVITE_TTL = 604800;

function tylilo_admin_default_permissions(bool $leaderboards = false): array
{
    return [
        'notes' => 1,
        'feedback' => 1,
        'ideas' => 1,
        'leaderboards' => $leaderboards ? 1 : 0,
        'beta' => 0,
    ];
}

function tylilo_admin_permissions_from_post(): array
{
    return [
        'notes' => isset($_POST['perm_notes']) ? 1 : 0,
        'feedback' => isset($_POST['perm_feedback']) ? 1 : 0,
        'ideas' => isset($_POST['perm_ideas']) ? 1 : 0,
        'leaderboards' => isset($_POST['perm_leaderboards']) ? 1 : 0,
        'beta' => isset($_POST['perm_beta']) ? 1 : 0,
    ];
}

function tylilo_admin_migrate(array $data): array
{
    $data += tylilo_admin_blank();
    $ownerId = 0;
    foreach ($data['users'] as &$user) {
        if (($user['role'] ?? '') === 'admin') {
            $user['role'] = 'owner';
        }
        if (($user['role'] ?? '') === 'owner' && $ownerId === 0) {
            $ownerId = (int) $user['id'];
        }
        $user['disabled'] = (int) ($user['disabled'] ?? 0);
        if (!isset($user['permissions']) || !is_array($user['permissions'])) {
            $user['permissions'] = tylilo_admin_default_permissions(($user['role'] ?? '') === 'owner');
        }
    }
    unset($user);
    foreach (['notes', 'todos'] as $key) {
        foreach ($data[$key] as &$row) {
            if (!isset($row['user_id'])) {
                $row['user_id'] = $ownerId;
            }
        }
        unset($row);
    }

    return $data;
}

function tylilo_admin_is_owner(array $user): bool
{
    return ($user['role'] ?? '') === 'owner' || ($user['role'] ?? '') === 'admin';
}

function tylilo_admin_can(array $user, string $permission): bool
{
    if ((int) ($user['disabled'] ?? 0) === 1) {
        return false;
    }
    if (tylilo_admin_is_owner($user)) {
        return true;
    }
    $permissions = $user['permissions'] ?? [];

    return is_array($permissions) && !empty($permissions[$permission]);
}

function tylilo_admin_touch_session(): void
{
    tylilo_session_start();
    $now = time();
    $last = $_SESSION['admin_last_seen'] ?? 0;
    $last = is_int($last) ? $last : 0;
    $login = $_SESSION['admin_login_at'] ?? 0;
    $login = is_int($login) ? $login : 0;
    $signedIn = !empty($_SESSION['admin_user_id']);
    $idleEnded = $signedIn && $last > 0 && ($now - $last) > TYLILO_ADMIN_IDLE;
    $capEnded = $signedIn && ($login <= 0 || ($now - $login) > TYLILO_ADMIN_ABSOLUTE);
    if ($idleEnded || $capEnded) {
        $who = (int) $_SESSION['admin_user_id'];
        unset($_SESSION['admin_user_id'], $_SESSION['admin_pending_id'], $_SESSION['admin_login_at'], $_SESSION['admin_session_version'], $_SESSION['admin_last_seen']);
        $_SESSION['admin_notice'] = 'signed-out';
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
        tylilo_admin_log($who, '', $capEnded ? 'session-cap' : 'session-idle', 'Signed out');

        return;
    }
    if ($signedIn || !empty($_SESSION['admin_pending_id'])) {
        $_SESSION['admin_last_seen'] = $now;
    }
}

function tylilo_admin_begin_session(array $account): void
{
    tylilo_session_start();
    session_regenerate_id(true);
    $now = time();
    unset($_SESSION['admin_pending_id'], $_SESSION['admin_notice']);
    $_SESSION['admin_user_id'] = (int) $account['id'];
    $_SESSION['admin_last_seen'] = $now;
    $_SESSION['admin_login_at'] = $now;
    $_SESSION['admin_session_version'] = (int) ($account['session_version'] ?? 1);
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

function tylilo_admin_end_session(string $notice = ''): void
{
    tylilo_session_start();
    $_SESSION = [];
    if ($notice !== '') {
        $_SESSION['admin_notice'] = $notice;
    }
    session_regenerate_id(true);
}

function tylilo_admin_bump_session(array &$user): void
{
    $user['session_version'] = (int) ($user['session_version'] ?? 1) + 1;
}

function tylilo_admin_log(int $userId, string $username, string $action, string $detail): void
{
    tylilo_admin_with(static function (array $data) use ($userId, $username, $action, $detail): array {
        $data['activity'][] = [
            'id' => tylilo_admin_next_id($data['activity'] ?? []),
            'at' => time(),
            'user_id' => $userId,
            'username' => $username,
            'action' => $action,
            'detail' => $detail,
        ];
        if (count($data['activity']) > 400) {
            $data['activity'] = array_slice($data['activity'], -400);
        }

        return $data;
    });
}

function tylilo_admin_owner_id(array $data): int
{
    foreach ($data['users'] as $user) {
        if (tylilo_admin_is_owner($user)) {
            return (int) $user['id'];
        }
    }

    return 0;
}

function tylilo_admin_current_game_version(string $slug): string
{
    if (preg_match('/^[a-z0-9-]{1,40}$/', $slug) !== 1) {
        return '';
    }
    $file = dirname(__DIR__) . '/arcade/' . $slug . '/index.html';
    if (!is_file($file)) {
        return '';
    }
    $text = file_get_contents($file, false, null, 0, 300000);
    if (!is_string($text)) {
        return '';
    }
    if (preg_match("/VERSION\\s*=\\s*['\"]([0-9A-Za-z._-]{1,16})['\"]/", $text, $match) === 1) {
        return $match[1];
    }
    if (preg_match('/"version"\\s*:\\s*"([0-9A-Za-z._-]{1,16})"/', $text, $match) === 1) {
        return $match[1];
    }

    return '';
}

function tylilo_admin_idea_image_extension(string $path, int $size): string
{
    if ($size < 1 || $size > 2000000 || !is_file($path)) {
        return '';
    }
    $info = @getimagesize($path);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    if (!is_array($info) || !isset($types[$info[2] ?? 0])) {
        return '';
    }

    return $types[$info[2]];
}

function tylilo_admin_store_idea_image(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return '';
    }
    $tmp = is_string($file['tmp_name'] ?? null) ? $file['tmp_name'] : '';
    $ext = tylilo_admin_idea_image_extension($tmp, (int) ($file['size'] ?? 0));
    if ($ext === '') {
        return '';
    }
    $dir = tylilo_turnstile_dir() . '/idea-images';
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        return '';
    }
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($tmp, $dir . '/' . $name)) {
        return '';
    }

    return $name;
}
