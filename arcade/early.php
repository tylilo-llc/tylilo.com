<?php

declare(strict_types=1);

/**
 * Public gate for Alpha/Beta Arcade games.
 * Hidden games return the site 404. Visible games are streamed unchanged.
 * This file must not send the site CSP header. Crash Cars
 * still loads fonts from Google Fonts and its 3D code from jsDelivr,
 * which that policy would block.
 */

define('TYLILO_SEND_HEADERS', false);

require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/beta-arcade.php';

$slug = is_string($_GET['game'] ?? null) ? strtolower(trim($_GET['game'])) : '';
// Hidden or unknown Alpha/Beta URLs redirect to the Arcade list.
// Including the site 404 page from this gate produced an empty body on
// HostM/LiteSpeed when PHP set a 404 status (ErrorDocument).
if (preg_match('/^[a-z0-9-]{1,40}$/', $slug) !== 1 || in_array($slug, tylilo_arcade_released_slugs(), true) || !tylilo_beta_is_gated_slug($slug) || !tylilo_beta_visible($slug)) {
    header('Cache-Control: no-store, private');
    header('Location: /arcade/', true, 302);
    exit;
}

$path = tylilo_beta_arcade_public_file($slug);
if ($path === null) {
    header('Cache-Control: no-store, private');
    header('Location: /arcade/', true, 302);
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
header('X-Robots-Tag: noindex, nofollow');
header('Content-Length: ' . (string) filesize($path));
readfile($path);
exit;
