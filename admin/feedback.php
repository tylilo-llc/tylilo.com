<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

$me = tylilo_admin_guard();
if (!tylilo_admin_can($me, 'feedback')) {
    tylilo_admin_forbid();
}
$owner = tylilo_admin_is_owner($me);
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!tylilo_admin_csrf_ok(tylilo_admin_field('csrf'))) {
        $error = 'That request was not accepted. Please try again.';
    } else {
        $action = tylilo_admin_field('action');
        if ($action === 'send' && !$owner) {
            $game = tylilo_admin_field('game');
            $type = tylilo_admin_field('type');
            $text = is_string($_POST['text'] ?? null) ? trim($_POST['text']) : '';
            $rating = tylilo_admin_field('rating');
            if (!in_array($type, ['bug', 'idea', 'other'], true) || $text === '' || !in_array($game, tylilo_admin_game_slugs(), true)) {
                $error = 'Choose a game and a type, and write the feedback.';
            } else {
                $score = $rating === '' ? null : (int) $rating;
                if ($score !== null && ($score < 1 || $score > 5)) {
                    $score = null;
                }
                $version = tylilo_admin_current_game_version($game);
                tylilo_admin_with(static function (array $data) use ($me, $game, $type, $text, $score, $version): array {
                    $data['feedback'][] = [
                        'id' => tylilo_admin_next_id($data['feedback']),
                        'user_id' => (int) $me['id'],
                        'game' => $game,
                        'type' => $type,
                        'text' => $text,
                        'rating' => $score,
                        'version' => $version,
                        'resolved' => 0,
                        'created_at' => time(),
                    ];

                    return $data;
                });
                tylilo_admin_log((int) $me['id'], (string) $me['username'], 'feedback', $game . ' ' . $type);
                header('Location: /admin/feedback.php', true, 303);
                exit;
            }
        } elseif ($owner && ($action === 'resolve' || $action === 'todo')) {
            $id = (int) tylilo_admin_field('id');
            tylilo_admin_with(static function (array $data) use ($action, $id, $me): array {
                foreach ($data['feedback'] as &$item) {
                    if ((int) $item['id'] !== $id) {
                        continue;
                    }
                    $item['resolved'] = 1;
                    if ($action === 'todo') {
                        $data['todos'][] = [
                            'id' => tylilo_admin_next_id($data['todos']),
                            'user_id' => (int) $me['id'],
                            'title' => ucfirst((string) $item['type']) . ' in ' . $item['game'],
                            'notes' => (string) $item['text'],
                            'priority' => 'normal',
                            'status' => 'open',
                            'created_at' => time(),
                            'updated_at' => time(),
                        ];
                    }
                }

                return $data;
            });
            tylilo_admin_log((int) $me['id'], (string) $me['username'], $action === 'todo' ? 'feedback-todo' : 'feedback-resolved', 'Feedback ' . $id);
            header('Location: /admin/feedback.php', true, 303);
            exit;
        }
    }
}

$data = tylilo_admin_with(static fn (array $stored): array => $stored);
$html = '';
if (!$owner) {
    $html .= '<div class="card"><h2>Game feedback</h2>';
    if ($error !== '') {
        $html .= '<div class="form-status error" role="alert"><p>' . tylilo_e($error) . '</p></div>';
    }
    $html .= '<form method="post" action="/admin/feedback.php"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="send">';
    $html .= '<label for="game">Game</label><select id="game" name="game">';
    foreach (tylilo_admin_game_slugs() as $slug) {
        $html .= '<option value="' . tylilo_e($slug) . '">' . tylilo_e($slug) . '</option>';
    }
    $html .= '</select><label for="type">Type</label><select id="type" name="type"><option value="bug">Bug</option><option value="idea">Idea</option><option value="other">Other</option></select>';
    $html .= '<label for="text">Feedback</label><textarea id="text" name="text" required></textarea>';
    $html .= '<label for="rating">Rating, optional</label><select id="rating" name="rating"><option value="">No rating</option>';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<option value="' . $i . '">' . $i . '</option>';
    }
    $html .= '</select><button class="btn send" type="submit">Send feedback</button></form></div>';
} else {
    $html .= '<div class="card"><h2>Feedback inbox</h2><p>New notes from the team. Resolving one keeps it here as done.</p></div>';
}
foreach (array_reverse($data['feedback']) as $item) {
    if (!$owner && (int) $item['user_id'] !== (int) $me['id']) {
        continue;
    }
    $html .= '<article class="card"><h2>' . tylilo_e((string) $item['game']) . ' · ' . tylilo_e((string) $item['type']) . '</h2>';
    $html .= '<p>' . tylilo_e((string) $item['text']) . '</p>';
    $html .= '<p>Version ' . tylilo_e((string) ($item['version'] !== '' ? $item['version'] : 'unknown'));
    if ($item['rating'] !== null) {
        $html .= ' · rating ' . tylilo_e((string) $item['rating']);
    }
    $html .= (int) $item['resolved'] === 1 ? ' · resolved' : '';
    $html .= '</p>';
    if ($owner && (int) $item['resolved'] !== 1) {
        $html .= '<form method="post" action="/admin/feedback.php"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="resolve"><input type="hidden" name="id" value="' . (int) $item['id'] . '"><button type="submit">Mark resolved</button></form>';
        $html .= '<form method="post" action="/admin/feedback.php"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="todo"><input type="hidden" name="id" value="' . (int) $item['id'] . '"><button type="submit">Convert to my to-do</button></form>';
    }
    $html .= '</article>';
}
tylilo_admin_layout($owner ? 'Feedback inbox' : 'Feedback', $html);
