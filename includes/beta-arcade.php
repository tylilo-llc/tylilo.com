<?php

declare(strict_types=1);

if (!defined('TYLILO_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/turnstile.php';
require_once __DIR__ . '/beta.php';

/** @var list<string> */
const TYLILO_ARCADE_RELEASED_SLUGS = ['solitaire', 'red-rain', 'pop-spiral', 'jungle-rescue'];

/** @var list<string> */
const TYLILO_BETA_ARCADE_DEFAULT_VISIBLE = ['crash-cars'];

/**
 * Released Arcade games stay static files. Keep this list in sync with
 * the exclusion in /.htaccess and router.php.
 *
 * @return list<string>
 */
function tylilo_arcade_released_slugs(): array
{
    return TYLILO_ARCADE_RELEASED_SLUGS;
}

function tylilo_beta_visibility_path(): string
{
    return rtrim(tylilo_turnstile_dir(), '/') . '/beta-visibility.json';
}

/**
 * @return array<string, bool>
 */
function tylilo_beta_visibility_defaults(): array
{
    $defaults = [];
    $seed = array_fill_keys(TYLILO_BETA_ARCADE_DEFAULT_VISIBLE, true);
    foreach (tylilo_beta_games() as $game) {
        $slug = $game['slug'];
        $defaults[$slug] = isset($seed[$slug]);
    }

    return $defaults;
}

/**
 * @return array<string, bool>
 */
function tylilo_beta_visibility(): array
{
    $defaults = tylilo_beta_visibility_defaults();
    $path = tylilo_beta_visibility_path();
    if (!is_file($path)) {
        return $defaults;
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        return $defaults;
    }
    $state = $defaults;
    foreach ($decoded as $slug => $visible) {
        if (!is_string($slug) || preg_match('/^[a-z0-9-]{1,40}$/', $slug) !== 1) {
            continue;
        }
        $state[$slug] = $visible === true || $visible === 1 || $visible === '1';
    }

    return $state;
}

function tylilo_beta_visible(string $slug): bool
{
    $state = tylilo_beta_visibility();

    return !empty($state[$slug]);
}

function tylilo_beta_set_visible(string $slug, bool $visible): bool
{
    if (preg_match('/^[a-z0-9-]{1,40}$/', $slug) !== 1 || tylilo_beta_game($slug) === null) {
        return false;
    }
    $dir = tylilo_turnstile_dir();
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Beta visibility storage is not available.');
    }
    $path = tylilo_beta_visibility_path();
    $handle = fopen($path, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Beta visibility storage is not available.');
    }
    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Beta visibility storage is not available.');
        }
        rewind($handle);
        $raw = stream_get_contents($handle);
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
        if (!is_array($decoded)) {
            $decoded = [];
        }
        $decoded[$slug] = $visible;
        $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (!is_string($encoded)) {
            throw new RuntimeException('Beta visibility could not be saved.');
        }
        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, $encoded . "\n") === false) {
            throw new RuntimeException('Beta visibility could not be saved.');
        }
        fflush($handle);
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    return true;
}

/**
 * Catalog for the public Alpha/Beta Games section. Badge is "Beta" or "Alpha".
 *
 * @return array<string, array{title: string, description: string, href: string, badge: string}>
 */
function tylilo_arcade_early_catalog(): array
{
    return [
        'crash-cars' => [
            'title' => 'Crash Cars',
            'description' => 'A 3D demolition-derby driving game.',
            'href' => '/arcade/crash-cars/',
            'badge' => 'Beta',
        ],
        'jumpy-jumperton' => [
            'title' => 'Jumpy Jumperton',
            'description' => 'A jumping game still in testing.',
            'href' => '/arcade/jumpy-jumperton/',
            'badge' => 'Beta',
        ],
        'starbase-defender' => [
            'title' => 'Starbase Defender',
            'description' => 'A base-defense game still in testing.',
            'href' => '/arcade/starbase-defender/',
            'badge' => 'Beta',
        ],
    ];
}

/**
 * Visible early games, in catalog order, then any other visible beta.
 *
 * @return list<array{slug: string, title: string, description: string, href: string, badge: string}>
 */
function tylilo_arcade_visible_early_games(): array
{
    $visible = [];
    $state = tylilo_beta_visibility();
    $catalog = tylilo_arcade_early_catalog();
    $known = [];
    foreach (tylilo_beta_games() as $game) {
        $known[$game['slug']] = $game;
    }
    $order = array_unique([...array_keys($catalog), ...array_keys($known)]);
    foreach ($order as $slug) {
        if (empty($state[$slug])) {
            continue;
        }
        $meta = $catalog[$slug] ?? null;
        $game = $known[$slug] ?? null;
        if ($meta === null && $game === null) {
            continue;
        }
        $title = $meta['title'] ?? ($game['title'] ?? $slug);
        $visible[] = [
            'slug' => $slug,
            'title' => $title,
            'description' => $meta['description'] ?? 'A beta game still in testing.',
            'href' => $meta['href'] ?? '/arcade/' . rawurlencode($slug) . '/',
            'badge' => $meta['badge'] ?? 'Beta',
        ];
    }

    return $visible;
}

function tylilo_beta_arcade_public_file(string $slug): ?string
{
    if (preg_match('/^[a-z0-9-]{1,40}$/', $slug) !== 1 || !tylilo_beta_is_gated_slug($slug)) {
        return null;
    }
    $arcade = dirname(__DIR__) . '/arcade/' . $slug . '/index.html';
    if (is_file($arcade)) {
        $real = realpath($arcade);
        $root = realpath(dirname(__DIR__) . '/arcade/' . $slug);
        if ($real !== false && $root !== false && ($real === $root || str_starts_with($real, $root . DIRECTORY_SEPARATOR))) {
            return $real;
        }
    }

    return tylilo_beta_file($slug, 'index.html');
}

function tylilo_beta_is_gated_slug(string $slug): bool
{
    return tylilo_beta_game($slug) !== null;
}

/**
 * @param array<string, mixed> $user
 * @return array{status: int, error: string}
 */
function tylilo_beta_arcade_toggle(array $user, string $slug, bool $visible, string $csrf): array
{
    if (!tylilo_admin_csrf_ok($csrf)) {
        return ['status' => 400, 'error' => 'That request was not accepted. Please try again.'];
    }
    if (!tylilo_admin_can($user, 'arcade_publish')) {
        return ['status' => 403, 'error' => 'You do not have access to that page.'];
    }
    if (tylilo_beta_game($slug) === null) {
        return ['status' => 400, 'error' => 'That beta game was not found.'];
    }
    tylilo_beta_set_visible($slug, $visible);
    $game = tylilo_beta_game($slug);
    $title = is_array($game) ? (string) $game['title'] : $slug;
    $detail = $visible ? ('Showed ' . $title . ' in the Arcade') : ('Hid ' . $title . ' from the Arcade');
    tylilo_admin_log((int) ($user['id'] ?? 0), (string) ($user['username'] ?? ''), 'beta-arcade', $detail);

    return ['status' => 204, 'error' => ''];
}
