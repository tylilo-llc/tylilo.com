<?php

declare(strict_types=1);

require dirname(__DIR__) . '/_init.php';

$me = tylilo_admin_guard();
if (!tylilo_admin_can($me, 'beta')) {
    tylilo_admin_forbid();
}

require_once dirname(__DIR__, 2) . '/includes/beta-arcade.php';

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && tylilo_admin_field('action') === 'arcade-visibility') {
    $result = tylilo_beta_arcade_toggle(
        $me,
        tylilo_admin_field('slug'),
        tylilo_admin_field('visible') === '1',
        tylilo_admin_field('csrf')
    );
    if ($result['status'] === 403) {
        tylilo_admin_forbid();
    }
    if ($result['status'] === 204) {
        header('Location: /admin/beta/', true, 303);
        exit;
    }
    $error = $result['error'];
}

$html = '<div class="card"><h2>Beta games</h2>';
$html .= '<p>These are test builds in progress. Some can also be played in the Arcade under Alpha/Beta Games. Use the button next to a game to show or hide it there.</p></div>';
if ($error !== '') {
    $html .= '<div class="card" role="alert"><p>' . tylilo_e($error) . '</p></div>';
}
$games = tylilo_beta_games();
$canPublish = tylilo_admin_can($me, 'arcade_publish');
if ($games === []) {
    $html .= '<div class="beta-list"><div class="card"><p>No beta games yet.</p></div></div>';
}
if ($games !== []) {
    $html .= '<div class="beta-list">';
}
foreach ($games as $game) {
    $slug = $game['slug'];
    $href = '/admin/beta/g/' . rawurlencode($slug) . '/';
    $html .= '<article class="card beta-card">';
    if (tylilo_beta_file($slug, 'cover-400.webp') !== null) {
        $src = $href . 'cover-400.webp';
        $srcset = tylilo_beta_file($slug, 'cover-800.webp') !== null ? ' srcset="' . tylilo_e($src) . ' 400w, ' . tylilo_e($href . 'cover-800.webp') . ' 800w" sizes="(min-width: 900px) 30vw, (min-width: 640px) 45vw, 100vw"' : '';
        $html .= '<img class="beta-cover" src="' . tylilo_e($src) . '"' . $srcset . ' alt="" width="200" height="150">';
    }
    $html .= '<p class="kicker">Beta</p>';
    $html .= '<h2>' . tylilo_e($game['title']) . '</h2>';
    $html .= '<p class="beta-version">Version ' . tylilo_e($game['version'] !== '' ? $game['version'] : 'unknown') . '</p>';
    $html .= '<div class="beta-card-actions">';
    $html .= '<a class="btn beta-play" href="' . tylilo_e($href) . '">Play</a>';
    if ($canPublish) {
        $inArcade = tylilo_beta_visible($slug);
        $html .= '<form method="post" action="/admin/beta/">';
        $html .= '<input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '">';
        $html .= '<input type="hidden" name="action" value="arcade-visibility">';
        $html .= '<input type="hidden" name="slug" value="' . tylilo_e($slug) . '">';
        $html .= '<input type="hidden" name="visible" value="' . ($inArcade ? '0' : '1') . '">';
        $html .= '<button class="btn beta-arcade-toggle" type="submit">' . ($inArcade ? 'Hide from Arcade' : 'Show in Arcade') . '</button>';
        $html .= '</form>';
    }
    $html .= '</div></article>';
}
if ($games !== []) {
    $html .= '</div>';
}
tylilo_admin_layout('Beta games', $html);
