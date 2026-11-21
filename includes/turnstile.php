<?php

declare(strict_types=1);

if (!defined('TYLILO_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

/**
 * Cloudflare Turnstile for the contact form.
 * The sitekey is public. The secret is never in this repository:
 * TURNSTILE_SECRET in the environment (local tests), otherwise
 * turnstile.php outside the web root, otherwise data/turnstile.php
 * which .htaccess refuses to serve.
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

function tylilo_turnstile_secret(): string
{
    $env = getenv('TURNSTILE_SECRET');
    if (is_string($env) && $env !== '') {
        return $env;
    }

    $file = tylilo_turnstile_dir() . '/turnstile.php';
    if (!is_file($file)) {
        return '';
    }
    $value = include $file;

    return is_string($value) ? $value : '';
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
