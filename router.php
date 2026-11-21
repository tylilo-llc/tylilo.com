<?php

declare(strict_types=1);

/**
 * Local preview router for PHP's built-in server:
 *   php -S localhost:8000 router.php
 *
 * Apache on HostM uses .htaccess instead. This file is not uploaded.
 */

if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
if (str_contains($uri, "\0") || str_contains($uri, '..')) {
    http_response_code(400);
    exit;
}

$path = __DIR__ . $uri;
if ($uri !== '/' && is_file($path)) {
    return false;
}

if ($uri === '/' || $uri === '/index.php') {
    require __DIR__ . '/index.php';
    exit;
}

http_response_code(404);
require __DIR__ . '/404.php';
