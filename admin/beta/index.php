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
    $html .= '<div class="card"><p>No beta games yet.</p></div>';
}
foreach ($games as $game) {
    $html .= '<article class="card"><p class="kicker">Beta</p>';
    $html .= '<h2>' . tylilo_e($game['title']) . '</h2>';
    $html .= '<p>Version ' . tylilo_e($game['version'] !== '' ? $game['version'] : 'unknown') . '</p>';
    $html .= '<p><a class="btn" href="/admin/beta/g/' . rawurlencode($game['slug']) . '/">Play</a></p></article>';
}
tylilo_admin_layout('Beta games', $html);
