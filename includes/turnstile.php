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
 * Every place the deploy may have written the secret, most private first.
 * Missing directories are skipped. open_basedir makes is_file() false there.
 *
 * @return list<string>
 */
function tylilo_turnstile_secret_candidates(): array
{
    $paths = [];
    $add = static function (string $path) use (&$paths): void {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_contains($path, "\0")) {
            return;
        }
        $paths[$path] = $path;
    };

    $fromEnv = getenv('TYLILO_DATA_DIR');
    if (is_string($fromEnv) && $fromEnv !== '') {
        $add($fromEnv . '/turnstile.php');
    }

    $doc = $_SERVER['DOCUMENT_ROOT'] ?? '';
    if (is_string($doc) && $doc !== '') {
        $doc = rtrim($doc, '/');
        $parent = dirname($doc);
        if ($parent !== '/' && $parent !== $doc && $parent !== '.' && $parent !== '') {
            $add($parent . '/tylilo-data/turnstile.php');
        }
        $add($doc . '/tylilo-data/turnstile.php');
        $add($doc . '/data/turnstile.php');
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
        $add(rtrim($home, '/') . '/tylilo-data/turnstile.php');
    }

    $site = dirname(__DIR__);
    $siteParent = dirname($site);
    if ($siteParent !== '/' && $siteParent !== $site && $siteParent !== '.' && $siteParent !== '') {
        $add($siteParent . '/tylilo-data/turnstile.php');
    }
    $add($site . '/tylilo-data/turnstile.php');
    $add($site . '/data/turnstile.php');

    return array_values($paths);
}

function tylilo_turnstile_secret(): string
{
    $env = getenv('TURNSTILE_SECRET');
    if (is_string($env) && $env !== '') {
        return $env;
    }

    foreach (tylilo_turnstile_secret_candidates() as $file) {
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
 * @return array{ok: bool, error: string}
 */
function tylilo_turnstile_verify(string $token, string $remoteIp, string $expectedAction = 'contact'): array
{
    $fail = static function (string $why): array {
        if ($why !== '') {
            error_log('Turnstile: ' . $why);
        }

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
    if ($token === '' || strlen($token) > 2048) {
        return $fail('missing token');
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
        return $fail('siteverify rejected the token');
    }

    $hostname = strtolower((string) ($data['hostname'] ?? ''));
    $action = $data['action'] ?? null;
    $allowed = $hostname === 'tylilo.com' || $hostname === 'www.tylilo.com';
    // Cloudflare's public always-pass test secret reports hostname example.com and omits action.
    $dummyPass = hash_equals('1x0000000000000000000000000000000AA', $secret);
    if (!$allowed && !($dummyPass && $hostname === 'example.com')) {
        return $fail('unexpected hostname');
    }
    if ($action !== $expectedAction && !($dummyPass && ($action === null || $action === ''))) {
        return $fail('unexpected action');
    }

    return ['ok' => true, 'error' => ''];
}
