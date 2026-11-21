<?php

declare(strict_types=1);

if (!defined('TYLILO_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

function tylilo_feedback_max_files(): int
{
    return 5;
}

function tylilo_feedback_cap_bytes(): int
{
    return 10 * 1024 * 1024;
}

function tylilo_feedback_ini_bytes(string $name): int
{
    $raw = (string) ini_get($name);
    if ($raw === '' || $raw === '0') {
        return 0;
    }
    if (preg_match('/^([0-9]+)(K|M|G)?$/i', trim($raw), $match) !== 1) {
        return 0;
    }
    $value = (int) $match[1];
    $unit = strtoupper($match[2] ?? '');
    $value *= match ($unit) {
        'K' => 1024,
        'M' => 1024 * 1024,
        'G' => 1024 * 1024 * 1024,
        default => 1,
    };

    return $value;
}

function tylilo_feedback_max_bytes(): int
{
    $cap = tylilo_feedback_cap_bytes();
    foreach (['upload_max_filesize', 'post_max_size'] as $name) {
        $limit = tylilo_feedback_ini_bytes($name);
        if ($limit > 0 && $limit < $cap) {
            $cap = $limit;
        }
    }

    return max(1, $cap);
}

function tylilo_feedback_size_label(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        $mb = $bytes / (1024 * 1024);
        $shown = $mb >= 10 ? (string) (int) round($mb) : rtrim(rtrim(number_format($mb, 1, '.', ''), '0'), '.');

        return $shown . ' MB';
    }
    if ($bytes >= 1024) {
        return (string) (int) round($bytes / 1024) . ' KB';
    }

    return $bytes . ' bytes';
}

/**
 * @return array<string, list<string>>
 */
function tylilo_feedback_allowed_map(): array
{
    return [
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'heic' => ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'],
        'heif' => ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'],
        'mp4' => ['video/mp4'],
        'mov' => ['video/quicktime', 'video/mp4'],
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain'],
        'log' => ['text/plain', 'text/x-log'],
        'json' => ['application/json', 'text/json', 'text/plain'],
    ];
}

function tylilo_feedback_stored_extension(string $ext): string
{
    return match (strtolower($ext)) {
        'jpeg' => 'jpg',
        'heif' => 'heic',
        default => strtolower($ext),
    };
}

function tylilo_feedback_accept_attr(): string
{
    return '.png,.jpg,.jpeg,.gif,.webp,.heic,.heif,.mp4,.mov,.pdf,.txt,.log,.json';
}

function tylilo_feedback_files_dir(): string
{
    $dir = tylilo_turnstile_dir() . '/feedback-files';
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Feedback storage is not available.');
    }
    $htaccess = $dir . '/.htaccess';
    if (!is_file($htaccess)) {
        file_put_contents(
            $htaccess,
            "# Feedback attachments are served only through admin/feedback-file.php.\n"
            . "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n",
            LOCK_EX
        );
        @chmod($htaccess, 0600);
    }

    return $dir;
}

function tylilo_feedback_display_name(string $name): string
{
    $name = str_replace(['\\', "\0"], '/', $name);
    $name = basename($name);
    $name = trim($name);
    if ($name === '' || $name === '.' || $name === '..') {
        return 'attachment';
    }
    if (strlen($name) > 100) {
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $base = substr($name, 0, 80);
        $name = is_string($ext) && $ext !== '' ? $base . '.' . $ext : $base;
    }

    return $name;
}

function tylilo_feedback_looks_like_php(string $path): bool
{
    $start = (string) file_get_contents($path, false, null, 0, 256);
    $start = ltrim($start, "\xEF\xBB\xBF \t\r\n");

    return str_starts_with($start, '<?php') || str_starts_with($start, '<?=');
}

function tylilo_feedback_is_mostly_text(string $path): bool
{
    $chunk = (string) file_get_contents($path, false, null, 0, 4096);
    if ($chunk === '' || str_contains($chunk, "\0")) {
        return false;
    }

    return preg_match('/^[\\x09\\x0A\\x0D\\x20-\\x7E\\x80-\\xFF]*$/', $chunk) === 1;
}

function tylilo_feedback_magic_kind(string $path): string
{
    $head = (string) file_get_contents($path, false, null, 0, 16);
    if (str_starts_with($head, "\x89PNG\r\n\x1A\n")) {
        return 'png';
    }
    if (str_starts_with($head, "\xFF\xD8\xFF")) {
        return 'jpg';
    }
    if (str_starts_with($head, 'GIF87a') || str_starts_with($head, 'GIF89a')) {
        return 'gif';
    }
    if (strlen($head) >= 12 && str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP') {
        return 'webp';
    }
    if (str_starts_with($head, '%PDF')) {
        return 'pdf';
    }
    if (strlen($head) >= 12 && substr($head, 4, 4) === 'ftyp') {
        $brand = strtolower(substr($head, 8, 4));
        if (in_array($brand, ['heic', 'heix', 'heif', 'mif1', 'msf1', 'hevc', 'hevx'], true)) {
            return 'heic';
        }
        if (in_array($brand, ['isom', 'iso2', 'iso4', 'iso5', 'iso6', 'mp41', 'mp42', 'avc1', 'dash'], true)) {
            return 'mp4';
        }
        if (in_array($brand, ['qt  ', 'mqt '], true)) {
            return 'mov';
        }
    }

    return '';
}

function tylilo_feedback_sniff_mime(string $path): string
{
    if (!is_file($path)) {
        return '';
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($path);
    if (!is_string($mime) || $mime === '') {
        return '';
    }

    return strtolower(trim($mime));
}

/**
 * @return array{ext: string, mime: string}|null
 */
function tylilo_feedback_identify(string $path, string $originalName): ?array
{
    if (!is_file($path) || tylilo_feedback_looks_like_php($path)) {
        return null;
    }
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $map = tylilo_feedback_allowed_map();
    if (!isset($map[$ext])) {
        return null;
    }
    $mime = tylilo_feedback_sniff_mime($path);
    $magic = tylilo_feedback_magic_kind($path);
    $ok = in_array($mime, $map[$ext], true);
    if (!$ok && $magic !== '') {
        $storedMagic = tylilo_feedback_stored_extension($magic);
        $storedExt = tylilo_feedback_stored_extension($ext);
        if ($storedMagic === $storedExt || ($storedExt === 'mov' && $storedMagic === 'mp4') || ($storedExt === 'mp4' && $storedMagic === 'mov')) {
            $ok = true;
            if ($mime === '' || $mime === 'application/octet-stream') {
                $mime = $map[$ext][0];
            }
        }
    }
    if (!$ok && in_array($ext, ['txt', 'log', 'json'], true) && ($mime === 'application/octet-stream' || $mime === 'text/html') && tylilo_feedback_is_mostly_text($path)) {
        // Do not accept HTML sniffed as a text attachment if it is actually HTML with a script.
        if ($mime === 'text/html') {
            return null;
        }
        $ok = tylilo_feedback_is_mostly_text($path);
        $mime = $ext === 'json' ? 'application/json' : 'text/plain';
    }
    if (!$ok) {
        return null;
    }
    if (in_array($ext, ['txt', 'log', 'json'], true) && !tylilo_feedback_is_mostly_text($path)) {
        return null;
    }
    if ($ext === 'json') {
        $raw = (string) file_get_contents($path, false, null, 0, 1000000);
        json_decode($raw);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        $mime = 'application/json';
    }

    return [
        'ext' => tylilo_feedback_stored_extension($ext),
        'mime' => $map[$ext][0],
    ];
}

/**
 * @param list<array<string, mixed>> $files Normalized uploads
 * @return array{ok: bool, error: string, files: list<array{id: string, name: string, stored: string, mime: string, size: int}>}
 */
function tylilo_feedback_store_uploads(array $files): array
{
    $empty = ['ok' => true, 'error' => '', 'files' => []];
    if ($files === []) {
        return $empty;
    }
    if (count($files) > tylilo_feedback_max_files()) {
        return ['ok' => false, 'error' => 'You can attach up to ' . tylilo_feedback_max_files() . ' files.', 'files' => []];
    }
    $max = tylilo_feedback_max_bytes();
    $stored = [];
    $dir = tylilo_feedback_files_dir();
    try {
        foreach ($files as $file) {
            $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
                throw new RuntimeException('Each file must be ' . tylilo_feedback_size_label($max) . ' or smaller.');
            }
            if ($error !== UPLOAD_ERR_OK) {
                throw new RuntimeException('One of the files could not be uploaded. Please try again.');
            }
            $tmp = is_string($file['tmp_name'] ?? null) ? $file['tmp_name'] : '';
            $size = (int) ($file['size'] ?? 0);
            $original = is_string($file['name'] ?? null) ? $file['name'] : '';
            $requireUpload = !empty($file['require_upload']);
            if ($tmp === '' || !is_file($tmp) || ($requireUpload && !is_uploaded_file($tmp))) {
                throw new RuntimeException('One of the files could not be uploaded. Please try again.');
            }
            if ($size < 1 || $size > $max) {
                throw new RuntimeException('Each file must be ' . tylilo_feedback_size_label($max) . ' or smaller.');
            }
            $identified = tylilo_feedback_identify($tmp, $original);
            if ($identified === null) {
                throw new RuntimeException('Use a picture (PNG, JPEG, GIF, WebP, or HEIC), a short video (MP4 or MOV), a PDF, a text or log file, or a JSON file.');
            }
            $name = bin2hex(random_bytes(16)) . '.' . $identified['ext'];
            $dest = $dir . '/' . $name;
            $moved = ($requireUpload && is_uploaded_file($tmp)) ? move_uploaded_file($tmp, $dest) : copy($tmp, $dest);
            if (!$moved) {
                throw new RuntimeException('One of the files could not be saved. Please try again.');
            }
            @chmod($dest, 0600);
            $stored[] = [
                'id' => bin2hex(random_bytes(8)),
                'name' => tylilo_feedback_display_name($original),
                'stored' => $name,
                'mime' => $identified['mime'],
                'size' => $size,
            ];
        }
    } catch (RuntimeException $exception) {
        tylilo_feedback_delete_files($stored);

        return ['ok' => false, 'error' => $exception->getMessage(), 'files' => []];
    }

    return ['ok' => true, 'error' => '', 'files' => $stored];
}

/**
 * @return list<array<string, mixed>>
 */
function tylilo_feedback_normalize_uploads(mixed $incoming): array
{
    if (!is_array($incoming) || !isset($incoming['name'])) {
        return [];
    }
    $names = $incoming['name'];
    if (!is_array($names)) {
        $incoming = [
            'name' => [$incoming['name'] ?? ''],
            'type' => [$incoming['type'] ?? ''],
            'tmp_name' => [$incoming['tmp_name'] ?? ''],
            'error' => [$incoming['error'] ?? UPLOAD_ERR_NO_FILE],
            'size' => [$incoming['size'] ?? 0],
        ];
        $names = $incoming['name'];
    }
    $out = [];
    foreach ($names as $i => $name) {
        $error = (int) ($incoming['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $out[] = [
            'name' => is_string($name) ? $name : '',
            'type' => is_string($incoming['type'][$i] ?? null) ? $incoming['type'][$i] : '',
            'tmp_name' => is_string($incoming['tmp_name'][$i] ?? null) ? $incoming['tmp_name'][$i] : '',
            'error' => $error,
            'size' => (int) ($incoming['size'][$i] ?? 0),
            'require_upload' => true,
        ];
    }

    return $out;
}

/**
 * @param list<array<string, mixed>> $attachments
 */
function tylilo_feedback_delete_files(array $attachments): void
{
    $dir = '';
    try {
        $dir = tylilo_feedback_files_dir();
    } catch (RuntimeException) {
        return;
    }
    $base = realpath($dir);
    if ($base === false) {
        return;
    }
    foreach ($attachments as $file) {
        $stored = is_string($file['stored'] ?? null) ? $file['stored'] : '';
        if (preg_match('/^[a-f0-9]{32}\.(png|jpg|gif|webp|heic|mp4|mov|pdf|txt|log|json)$/', $stored) !== 1) {
            continue;
        }
        $path = realpath($base . '/' . $stored);
        if ($path !== false && str_starts_with($path, $base . DIRECTORY_SEPARATOR) && is_file($path)) {
            @unlink($path);
        }
    }
}

/**
 * @param array<string, mixed> $item
 */
function tylilo_feedback_attachments(array $item): array
{
    $rows = is_array($item['attachments'] ?? null) ? $item['attachments'] : [];
    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $stored = is_string($row['stored'] ?? null) ? $row['stored'] : '';
        if (preg_match('/^[a-f0-9]{32}\.(png|jpg|gif|webp|heic|mp4|mov|pdf|txt|log|json)$/', $stored) !== 1) {
            continue;
        }
        $out[] = [
            'id' => is_string($row['id'] ?? null) && $row['id'] !== '' ? $row['id'] : $stored,
            'name' => tylilo_feedback_display_name(is_string($row['name'] ?? null) ? $row['name'] : 'attachment'),
            'stored' => $stored,
            'mime' => is_string($row['mime'] ?? null) ? $row['mime'] : 'application/octet-stream',
            'size' => (int) ($row['size'] ?? 0),
        ];
    }

    return $out;
}

function tylilo_feedback_file_path(string $stored): ?string
{
    if (preg_match('/^[a-f0-9]{32}\.(png|jpg|gif|webp|heic|mp4|mov|pdf|txt|log|json)$/', $stored) !== 1) {
        return null;
    }
    $base = realpath(tylilo_feedback_files_dir());
    if ($base === false) {
        return null;
    }
    $path = realpath($base . '/' . $stored);
    if ($path === false || !is_file($path) || !str_starts_with($path, $base . DIRECTORY_SEPARATOR)) {
        return null;
    }

    return $path;
}

function tylilo_feedback_is_previewable_image(string $stored): bool
{
    $ext = strtolower(pathinfo($stored, PATHINFO_EXTENSION));

    return in_array($ext, ['png', 'jpg', 'gif', 'webp'], true);
}

/**
 * @param array<string, mixed> $me
 * @param array<string, mixed> $item
 */
function tylilo_feedback_can_view(array $me, array $item): bool
{
    if (tylilo_admin_is_owner($me)) {
        return true;
    }

    return (int) ($item['user_id'] ?? 0) === (int) ($me['id'] ?? 0);
}

function tylilo_feedback_post_too_large(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        && $_POST === []
        && $_FILES === []
        && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
}

/**
 * @param array<string, mixed> $item
 */
function tylilo_feedback_attachments_html(array $item): string
{
    $files = tylilo_feedback_attachments($item);
    if ($files === []) {
        return '';
    }
    $id = (int) $item['id'];
    $html = '<ul class="feedback-files">';
    foreach ($files as $file) {
        $href = '/admin/feedback-file.php?id=' . $id . '&file=' . rawurlencode($file['stored']);
        $html .= '<li>';
        if (tylilo_feedback_is_previewable_image($file['stored'])) {
            $html .= '<a class="feedback-thumb" href="' . tylilo_e($href) . '"><img src="' . tylilo_e($href . '&thumb=1') . '" alt="" width="160" height="160"></a>';
        }
        $label = $file['name'];
        if ($file['size'] > 0) {
            $label .= ' (' . tylilo_feedback_size_label($file['size']) . ')';
        }
        $html .= '<a href="' . tylilo_e($href) . '">' . tylilo_e($label) . '</a></li>';
    }
    $html .= '</ul>';

    return $html;
}
