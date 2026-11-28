<?php

declare(strict_types=1);

require dirname(__DIR__) . '/_init.php';

$me = tylilo_admin_guard();
if (!tylilo_admin_can($me, 'beta')) {
    tylilo_admin_forbid();
}

$slug = isset($_GET['game']) && is_string($_GET['game']) ? $_GET['game'] : '';
$file = isset($_GET['file']) && is_string($_GET['file']) ? $_GET['file'] : 'index.html';
$path = tylilo_beta_file($slug, $file);
if ($path === null) {
    http_response_code(404);
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: private, no-store');
    tylilo_admin_layout('Beta game', '<div class="card" role="alert"><h2>That beta game is not available.</h2></div>');
    exit;
}

$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$types = [
    'html' => 'text/html; charset=UTF-8',
    'js' => 'text/javascript; charset=UTF-8',
    'css' => 'text/css; charset=UTF-8',
    'json' => 'application/json; charset=UTF-8',
    'txt' => 'text/plain; charset=UTF-8',
    'map' => 'application/json; charset=UTF-8',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
    'gif' => 'image/gif',
    'svg' => 'image/svg+xml',
    'ico' => 'image/x-icon',
    'woff2' => 'font/woff2',
    'wasm' => 'application/wasm',
    'webmanifest' => 'application/manifest+json',
];
header('Content-Type: ' . ($types[$extension] ?? 'application/octet-stream'));
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
if ($extension === 'html') {
    // Games here may call only the two playtest endpoints (to send a report), nothing else on this site.
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    $hostName = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $playtestOrigin = preg_match('/^[A-Za-z0-9.\-]+(:[0-9]{1,5})?$/', $hostName) === 1 ? ($secure ? 'https://' : 'http://') . $hostName : '';
    $playtestConnect = $playtestOrigin !== '' ? ' ' . $playtestOrigin . '/api/playtest-token.php ' . $playtestOrigin . '/api/playtest-upload.php' : '';
    header(
        "Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'none'; frame-ancestors 'self'; "
        .         "frame-src 'none'; object-src 'none'; img-src 'self' data: blob: https://cdn-profiles.tunein.com https://*.tunein.com; "
        . "font-src 'self' data: https://fonts.gstatic.com; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        . "script-src 'self' 'unsafe-inline' 'unsafe-eval' blob: https://cdn.jsdelivr.net https://opml.radiotime.com; "
        . "media-src 'self' data: blob: https:; worker-src 'self' blob:; "
        . "connect-src https://opml.radiotime.com https://feed.tunein.com https://np.tritondigital.com https://api.live365.com" . $playtestConnect
    );
    if (basename($path) === 'index.html') {
        tylilo_admin_log((int) $me['id'], (string) $me['username'], 'play-beta', $slug);
    }
    $html = file_get_contents($path);
    echo tylilo_beta_prepare_html(is_string($html) ? $html : '', $slug);
    exit;
}

header("Content-Security-Policy: default-src 'none'; connect-src 'none'");
readfile($path);
