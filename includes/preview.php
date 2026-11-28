<?php

declare(strict_types=1);

/**
 * Preview-only helpers. Used by bootstrap and by the scores API, which
 * does not load bootstrap (bootstrap sends HTML headers).
 * Isolates data so preview never reads or writes production storage.
 */

function tylilo_is_preview(): bool
{
    if (defined('TYLILO_IS_PREVIEW')) {
        return TYLILO_IS_PREVIEW === true;
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (!is_string($host) || $host === '') {
        return false;
    }

    $host = strtolower($host);
    $colon = strpos($host, ':');
    if ($colon !== false) {
        $host = substr($host, 0, $colon);
    }

    return $host === 'preview.tylilo.com';
}

function tylilo_preview_data_dir(): string
{
    $site = dirname(__DIR__);
    $fallback = $site . '/data';
    $parent = dirname($site);
    $candidates = [];
    if ($parent !== '/' && $parent !== $site && $parent !== '.') {
        $home = dirname($parent);
        if ($home !== '/' && $home !== $parent && $home !== '.') {
            $candidates[] = $home . '/tylilo-preview-data';
        }
        $candidates[] = $parent . '/tylilo-preview-data';
    }
    $candidates[] = $fallback;

    foreach ($candidates as $dir) {
        $dir = rtrim(str_replace('\\', '/', $dir), '/');
        $base = basename($dir);
        if ($base !== 'tylilo-preview-data' && $base !== 'data') {
            continue;
        }
        if ($base === 'data' && dirname($dir) !== $site) {
            continue;
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            return $dir;
        }
    }

    return $fallback;
}

function tylilo_preview_isolate_data(): void
{
    if (!tylilo_is_preview()) {
        return;
    }

    if (!defined('TYLILO_IS_PREVIEW')) {
        define('TYLILO_IS_PREVIEW', true);
    }

    $current = getenv('TYLILO_DATA_DIR');
    if (is_string($current) && $current !== '') {
        $base = basename(rtrim(str_replace('\\', '/', $current), '/'));
        if ($base === 'tylilo-preview-data' || ($base === 'data' && dirname(rtrim($current, '/')) === dirname(__DIR__))) {
            return;
        }
    }

    $dir = tylilo_preview_data_dir();
    putenv('TYLILO_DATA_DIR=' . $dir);
    $_ENV['TYLILO_DATA_DIR'] = $dir;
    $_SERVER['TYLILO_DATA_DIR'] = $dir;
}

tylilo_preview_isolate_data();
