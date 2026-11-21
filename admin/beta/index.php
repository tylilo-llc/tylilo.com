<?php

declare(strict_types=1);

require dirname(__DIR__) . '/_init.php';

$me = tylilo_admin_guard();
if (!tylilo_admin_can($me, 'beta')) {
    tylilo_admin_forbid();
}

$html = '<div class="card"><h2>Beta games</h2>';
$html .= '<p>These builds are for signed-in testing. They are not in the Arcade. Publishing one is a separate site change.</p></div>';
$games = tylilo_beta_games();
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
    $html .= '<a class="btn beta-play" href="' . tylilo_e($href) . '">Play</a></article>';
}
if ($games !== []) {
    $html .= '</div>';
}
tylilo_admin_layout('Beta games', $html);
