<?php

declare(strict_types=1);

if (!defined('TYLILO_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

/**
 * @return list<string>
 */
function tylilo_beta_roots(): array
{
    $roots = [];
    $add = static function (string $dir) use (&$roots): void {
        $dir = rtrim(str_replace('\\', '/', $dir), '/');
        if ($dir === '' || $dir === '/' || $dir === '.' || str_contains($dir, "\0") || str_contains($dir, '..')) {
            return;
        }
        $roots[$dir] = $dir;
    };

    $fromEnv = getenv('TYLILO_BETA_DIR');
    if (is_string($fromEnv) && $fromEnv !== '') {
        $add($fromEnv);
    }
    if (function_exists('tylilo_private_dirs')) {
        foreach (tylilo_private_dirs() as $dir) {
            $add($dir . '/beta');
        }
    }
    $add(dirname(__DIR__) . '/beta');

    return array_values($roots);
}

/**
 * @return array{title: string, version: string}
 */
function tylilo_beta_meta(string $dir): array
{
    $title = '';
    $version = '';
    $path = $dir . '/meta.json';
    if (is_file($path)) {
        $decoded = json_decode((string) file_get_contents($path), true);
        if (is_array($decoded)) {
            $rawTitle = is_string($decoded['title'] ?? null) ? trim($decoded['title']) : '';
            $rawVersion = is_string($decoded['version'] ?? null) ? trim($decoded['version']) : '';
            if ($rawTitle !== '' && strlen($rawTitle) <= 80) {
                $title = $rawTitle;
            }
            if (preg_match('/^[0-9A-Za-z._-]{1,16}$/', $rawVersion) === 1) {
                $version = $rawVersion;
            }
        }
    }

    return ['title' => $title, 'version' => $version];
}

function tylilo_beta_version_from_html(string $file): string
{
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

/**
 * @return list<array{slug: string, title: string, version: string, root: string}>
 */
function tylilo_beta_games(): array
{
    $games = [];
    foreach (tylilo_beta_roots() as $root) {
        if (!is_dir($root)) {
            continue;
        }
        foreach (scandir($root) ?: [] as $name) {
            if (preg_match('/^[a-z0-9-]{1,40}$/', $name) !== 1 || isset($games[$name])) {
                continue;
            }
            $folder = $root . '/' . $name;
            if (!is_file($folder . '/index.html')) {
                continue;
            }
            $meta = tylilo_beta_meta($folder);
            $version = $meta['version'] !== '' ? $meta['version'] : tylilo_beta_version_from_html($folder . '/index.html');
            $games[$name] = [
                'slug' => $name,
                'title' => $meta['title'] !== '' ? $meta['title'] : $name,
                'version' => $version,
                'root' => $folder,
            ];
        }
    }
    ksort($games);

    return array_values($games);
}

function tylilo_beta_game(string $slug): ?array
{
    foreach (tylilo_beta_games() as $game) {
        if ($game['slug'] === $slug) {
            return $game;
        }
    }

    return null;
}

function tylilo_beta_file(string $slug, string $file): ?string
{
    $game = tylilo_beta_game($slug);
    if ($game === null) {
        return null;
    }
    $file = str_replace('\\', '/', $file);
    if ($file === '' || $file === '/') {
        $file = 'index.html';
    }
    $file = ltrim($file, '/');
    if ($file === '' || str_contains($file, "\0") || str_contains($file, '..') || preg_match('#^[a-zA-Z0-9._/-]+$#', $file) !== 1) {
        return null;
    }
    if (str_starts_with($file, '.') || str_contains($file, '/.')) {
        return null;
    }
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $allowed = ['html', 'js', 'css', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'svg', 'ico', 'woff2', 'json', 'txt', 'map', 'wasm', 'webmanifest'];
    if (!in_array($extension, $allowed, true)) {
        return null;
    }
    $base = realpath($game['root']);
    if ($base === false) {
        return null;
    }
    $target = realpath($base . '/' . $file);
    if ($target === false || !is_file($target)) {
        return null;
    }
    if ($target !== $base && !str_starts_with($target, $base . DIRECTORY_SEPARATOR)) {
        return null;
    }

    return $target;
}

function tylilo_beta_prepare_html(string $html, string $slug): string
{
    $base = '/admin/beta/g/' . rawurlencode($slug) . '/';
    $bar = '<div id="tylilo-beta-bar"><a href="/admin/beta/">Back to admin</a></div>'
        . '<style>#tylilo-beta-bar{position:fixed;z-index:2147483647;top:0;left:0;right:0;display:flex;align-items:center;min-height:2.75rem;padding:0.25rem 0.85rem;background:#0A3161;color:#FFF6EA;font:800 1rem/1.2 sans-serif}#tylilo-beta-bar a{color:#FFF6EA;display:inline-flex;align-items:center;min-height:2.75rem}</style>';
    if (!str_contains($html, '<base ')) {
        $tag = '<base href="' . htmlspecialchars($base, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
        if (stripos($html, '<head>') !== false) {
            $html = preg_replace('/<head>/i', '<head>' . $tag, $html, 1) ?? $html;
        } else {
            $html = $tag . $html;
        }
    }
    if (stripos($html, '<body') !== false) {
        $html = preg_replace('/<body([^>]*)>/i', '<body$1>' . $bar, $html, 1) ?? ($bar . $html);
    } else {
        $html = $bar . $html;
    }

    return $html;
}
