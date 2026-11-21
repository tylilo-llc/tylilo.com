<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';
require dirname(__DIR__) . '/includes/scores.php';

$me = tylilo_admin_guard();
if (!tylilo_admin_can($me, 'leaderboards')) {
    tylilo_admin_forbid();
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (tylilo_admin_csrf_ok(tylilo_admin_field('csrf')) && tylilo_admin_field('action') === 'clear') {
        $game = tylilo_admin_field('game');
        $board = tylilo_admin_field('board');
        if (tylilo_score_board($game, $board) !== null) {
            tylilo_score_clear_board($game, $board);
            tylilo_admin_log((int) $me['id'], (string) $me['username'], 'clear-board', $game . '/' . $board);
        }
    }
    header('Location: /admin/scores.php', true, 303);
    exit;
}

$html = '<div class="card"><h2>Leaderboards</h2><p>Clearing a board removes its public scores. It does not remove the game.</p></div>';
foreach (tylilo_score_games() as $slug => $game) {
    foreach ($game['boards'] as $board => $spec) {
        $list = tylilo_score_list($slug, (string) $board, 10);
        $rows = $list['boards'][0]['scores'] ?? [];
        $html .= '<article class="card"><h2>' . tylilo_e($slug) . ' · ' . tylilo_e((string) $spec['label']) . '</h2>';
        if ($rows === []) {
            $html .= '<p>No scores.</p>';
        } else {
            $html .= '<ul>';
            foreach ($rows as $row) {
                $html .= '<li>' . tylilo_e((string) $row['name']) . ', ' . tylilo_e((string) $row['score']) . '</li>';
            }
            $html .= '</ul>';
        }
        $html .= '<form method="post" action="/admin/scores.php"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="clear"><input type="hidden" name="game" value="' . tylilo_e($slug) . '"><input type="hidden" name="board" value="' . tylilo_e((string) $board) . '"><button type="submit">Clear ' . tylilo_e((string) $spec['label']) . '</button></form></article>';
    }
}
tylilo_admin_layout('Scores', $html);
