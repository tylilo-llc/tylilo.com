<?php

declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(404);
    exit;
}

if (defined('TYLILO_BOOTSTRAPPED')) {
    return;
}

define('TYLILO_BOOTSTRAPPED', true);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../config.php';

$cspNonce = bin2hex(random_bytes(16));

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
// OTRadio (/otradio/) loads a public catalog, JPEG artwork, AAC audio, and
// WebVTT captions from one R2 bucket. Other pages keep the original policy.
$imgSrc = "'self'";
$otradioSrc = '';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestPath = is_string($requestPath) ? $requestPath : '/';
if ($requestPath === '/otradio' || str_starts_with($requestPath, '/otradio/')) {
    $otradioOrigin = 'https://pub-a3eee908554d4a948d807c160caa454e.r2.dev';
    $imgSrc .= ' ' . $otradioOrigin;
    $otradioSrc = "; connect-src 'self' {$otradioOrigin}; media-src 'self' {$otradioOrigin}";
}

header(
    "Content-Security-Policy: default-src 'self'; "
    . "base-uri 'self'; "
    . "form-action 'self'; "
    . "frame-ancestors 'self'; "
    . "object-src 'none'; "
    . "img-src {$imgSrc}; "
    . "font-src 'self'; "
    . "style-src 'self'; "
    . "script-src 'self' 'nonce-{$cspNonce}'"
    . $otradioSrc
);

function tylilo_e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Local asset URL with a file-mtime query so a changed file is a new URL.
 * Covers stylesheets, scripts, and images such as the arcade tile art.
 * Returning visitors otherwise keep the old file for days.
 */
function tylilo_asset(string $webPath): string
{
    $path = '/' . ltrim($webPath, '/');
    if (str_contains($path, '..') || str_contains($path, "\0")) {
        return $path;
    }

    $file = dirname(__DIR__) . $path;
    $version = is_file($file) ? (string) filemtime($file) : '0';

    return $path . '?v=' . rawurlencode($version);
}

function tylilo_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);

    session_name('tylilo');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function tylilo_csrf_token(): string
{
    tylilo_session_start();
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}
