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
require_once __DIR__ . '/config.php';
if ($uri === '/otradio/index.php' && TYLILO_OTRADIO_ENABLED) {
    header('Location: /otradio/', true, 301);
    exit;
}
if ($uri === '/arcade/index.php') {
    header('Location: /arcade/', true, 301);
    exit;
}
if ($uri !== '/' && is_file($path)) {
    return false;
}

// Match Apache DirectoryIndex so /otradio/ serves otradio/index.php.
// A missing trailing slash is redirected so relative URLs stay in the folder.
if ($uri !== '/' && is_dir($path)) {
    if (!str_ends_with($uri, '/')) {
        if (!preg_match('#\A/[A-Za-z0-9._/-]*\z#', $uri)) {
            http_response_code(400);
            exit;
        }
        $query = $_SERVER['QUERY_STRING'] ?? '';
        header('Location: ' . $uri . '/' . ($query !== '' ? '?' . $query : ''), true, 302);
        exit;
    }

    $index = $path . 'index.php';
    if (is_file($index)) {
        require $index;
        exit;
    }

    // Self-contained arcade games are index.html and are not PHP pages.
    $html = $path . 'index.html';
    if (is_file($html)) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($html);
        exit;
    }
}

if ($uri === '/' || $uri === '/index.php') {
    require __DIR__ . '/index.php';
    exit;
}

if ($uri === '/accessibility' || $uri === '/accessibility/') {
    require __DIR__ . '/accessibility.php';
    exit;
}

if ($uri === '/privacy' || $uri === '/privacy/') {
    require __DIR__ . '/privacy.php';
    exit;
}

http_response_code(404);
require __DIR__ . '/404.php';
