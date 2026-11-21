<?php

declare(strict_types=1);

if (!defined('TYLILO_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

/**
 * Cloudflare Turnstile for the contact form.
 * The sitekey is public. The secret is never in this repository:
 * TURNSTILE_SECRET in the environment (local tests), otherwise the first
 * turnstile.php found in a private directory PHP can actually read.
 * Deploy writes that file in several places because the FTP login root
 * is not always the parent of the directory PHP uses as DOCUMENT_ROOT.
 */

function tylilo_turnstile_sitekey(): string
{
    $env = getenv('TURNSTILE_SITEKEY');
    if (is_string($env) && $env !== '') {
        return $env;
    }

    return TYLILO_TURNSTILE_SITEKEY;
}

function tylilo_turnstile_dir(): string
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

    return $site . '/data';
}

/**
 * Directories where deploy may have written a private PHP file, most private first.
 * Missing directories are skipped later. open_basedir makes is_file() false there.
 *
 * @return list<string>
 */
function tylilo_private_dirs(): array
{
    $dirs = [];
    $add = static function (string $dir) use (&$dirs): void {
        $dir = rtrim(str_replace('\\', '/', $dir), '/');
        if ($dir === '' || $dir === '/' || $dir === '.' || str_contains($dir, "\0")) {
            return;
        }
        $dirs[$dir] = $dir;
    };

    $fromEnv = getenv('TYLILO_DATA_DIR');
    if (is_string($fromEnv) && $fromEnv !== '') {
        $add($fromEnv);
    }

    $doc = $_SERVER['DOCUMENT_ROOT'] ?? '';
    if (is_string($doc) && $doc !== '') {
        $doc = rtrim($doc, '/');
        $parent = dirname($doc);
        if ($parent !== '/' && $parent !== $doc && $parent !== '.' && $parent !== '') {
            $add($parent . '/tylilo-data');
        }
        $add($doc . '/tylilo-data');
        $add($doc . '/data');
    }

    $homes = [];
    $envHome = getenv('HOME');
    if (is_string($envHome) && $envHome !== '') {
        $homes[] = $envHome;
    }
    $serverHome = $_SERVER['HOME'] ?? '';
    if (is_string($serverHome) && $serverHome !== '') {
        $homes[] = $serverHome;
    }
    if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
        $info = posix_getpwuid(posix_geteuid());
        if (is_array($info) && isset($info['dir']) && is_string($info['dir']) && $info['dir'] !== '') {
            $homes[] = $info['dir'];
        }
    }
    foreach (array_unique($homes) as $home) {
        $add(rtrim($home, '/') . '/tylilo-data');
    }

    $site = dirname(__DIR__);
    $siteParent = dirname($site);
    if ($siteParent !== '/' && $siteParent !== $site && $siteParent !== '.' && $siteParent !== '') {
        $add($siteParent . '/tylilo-data');
    }
    $add($site . '/tylilo-data');
    $add($site . '/data');

    return array_values($dirs);
}

/**
 * @return list<string>
 */
function tylilo_private_file_candidates(string $filename): array
{
    $filename = str_replace('\\', '/', $filename);
    if ($filename === '' || str_contains($filename, "\0") || str_contains($filename, '/') || str_contains($filename, '..')) {
        return [];
    }
    $files = [];
    foreach (tylilo_private_dirs() as $dir) {
        $files[] = $dir . '/' . $filename;
    }

    return $files;
}

function tylilo_private_php_value(string $filename): string
{
    foreach (tylilo_private_file_candidates($filename) as $file) {
        if (!is_file($file)) {
            continue;
        }
        $value = include $file;
        if (is_string($value) && $value !== '') {
            return $value;
        }
    }

    return '';
}

/**
 * @return list<string>
 */
function tylilo_turnstile_secret_candidates(): array
{
    return tylilo_private_file_candidates('turnstile.php');
}

function tylilo_turnstile_secret(): string
{
    $env = getenv('TURNSTILE_SECRET');
    if (is_string($env) && $env !== '') {
        return $env;
    }

    return tylilo_private_php_value('turnstile.php');
}

/**
 * @return array{ok: bool, error: string}
 */
/**
 * One line for turnstile.log. Only Cloudflare's error-codes, hostname, and action.
 * The secret and the visitor token are never included.
 */
function tylilo_turnstile_failure_line(?array $data, string $reason): string
{
    $codes = [];
    if (is_array($data['error-codes'] ?? null)) {
        foreach ($data['error-codes'] as $code) {
            if (is_string($code) && preg_match('/^[a-z0-9_-]{1,80}$/', $code) === 1) {
                $codes[] = $code;
            }
        }
    }
    $hostname = strtolower((string) ($data['hostname'] ?? ''));
    if (preg_match('/^[a-z0-9.-]{1,253}$/', $hostname) !== 1) {
        $hostname = '';
    }
    $action = is_string($data['action'] ?? null) ? $data['action'] : '';
    if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $action) !== 1) {
        $action = '';
    }
    $reason = preg_replace('/[^a-z0-9_-]/', '', $reason) ?? '';
    if ($reason === '') {
        $reason = 'rejected';
    }

    return 'error-codes=' . ($codes === [] ? '-' : implode(',', $codes))
        . ' hostname=' . ($hostname === '' ? '-' : $hostname)
        . ' action=' . ($action === '' ? '-' : $action)
        . ' reason=' . $reason;
}

function tylilo_turnstile_log(string $line): void
{
    $line = str_replace(["\r", "\n"], ' ', $line);
    $dirs = [tylilo_turnstile_dir() => true];
    foreach (tylilo_turnstile_secret_candidates() as $file) {
        if (is_file($file)) {
            $dirs[dirname($file)] = true;
        }
    }
    $entry = gmdate('c') . ' ' . $line . "\n";
    foreach (array_keys($dirs) as $dir) {
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            continue;
        }
        @file_put_contents($dir . '/turnstile.log', $entry, FILE_APPEND | LOCK_EX);
    }
    error_log('Turnstile: ' . $line);
}

function tylilo_turnstile_verify(string $token, string $remoteIp, string $expectedAction = 'contact'): array
{
    $reject = static function (?array $data, string $reason): array {
        tylilo_turnstile_log(tylilo_turnstile_failure_line($data, $reason));

        return [
            'ok' => false,
            'error' => 'The security check did not pass. Please try again.',
        ];
    };

    $secret = tylilo_turnstile_secret();
    if ($secret === '') {
        error_log('Turnstile secret is not configured. Contact form submissions are rejected.');

        return [
            'ok' => false,
            'error' => 'The contact form is not available right now. Please try again later.',
        ];
    }

    $token = trim($token);
    if (strlen($token) > 2048) {
        return $reject(null, 'token-length');
    }

    $body = http_build_query([
        'secret' => $secret,
        'response' => $token,
        'remoteip' => $remoteIp,
    ]);
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $body,
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ]);
    $raw = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($data) || ($data['success'] ?? null) !== true) {
        return $reject(is_array($data) ? $data : null, 'siteverify');
    }

    $hostname = strtolower((string) ($data['hostname'] ?? ''));
    $action = $data['action'] ?? null;
    $allowed = $hostname === 'tylilo.com' || $hostname === 'www.tylilo.com';
    // Cloudflare's public always-pass test secret reports hostname example.com and omits action.
    $dummyPass = hash_equals('1x0000000000000000000000000000000AA', $secret);
    if (!$allowed && !($dummyPass && $hostname === 'example.com')) {
        return $reject($data, 'hostname');
    }
    if ($action !== $expectedAction && !($dummyPass && ($action === null || $action === ''))) {
        return $reject($data, 'action');
    }

    return ['ok' => true, 'error' => ''];
}
