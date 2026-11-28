<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';
require_once dirname(__DIR__) . '/includes/beta-arcade.php';

$me = tylilo_admin_guard();
if (!tylilo_admin_can($me, 'feedback')) {
    tylilo_admin_forbid();
}
$owner = tylilo_admin_is_owner($me);
$error = '';
if (tylilo_feedback_post_too_large()) {
    $error = 'Those files were too large for the server to accept. Try fewer or smaller files, up to ' . tylilo_feedback_size_label(tylilo_feedback_max_bytes()) . ' each.';
} elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!tylilo_admin_csrf_ok(tylilo_admin_field('csrf'))) {
        $error = 'That request was not accepted. Please try again.';
    } else {
        $action = tylilo_admin_field('action');
        if ($action === 'send' && !$owner) {
            $game = tylilo_admin_field('game');
            $type = tylilo_admin_field('type');
            $text = is_string($_POST['text'] ?? null) ? trim($_POST['text']) : '';
            $rating = tylilo_admin_field('rating');
            $betaSlug = str_starts_with($game, 'beta:') ? substr($game, 5) : '';
            $betaGame = $betaSlug !== '' ? tylilo_beta_game($betaSlug) : null;
            $published = in_array($game, tylilo_admin_game_slugs(), true);
            if (!in_array($type, ['bug', 'idea', 'other'], true) || $text === '' || (!$published && $betaGame === null)) {
                $error = 'Choose a game and a type, and write the feedback.';
            } elseif ($betaGame !== null && !tylilo_admin_can($me, 'beta')) {
                $error = 'Choose a game and a type, and write the feedback.';
            } else {
                $score = $rating === '' ? null : (int) $rating;
                if ($score !== null && ($score < 1 || $score > 5)) {
                    $score = null;
                }
                $uploads = tylilo_feedback_store_uploads(tylilo_feedback_normalize_uploads($_FILES['attachments'] ?? null));
                if (!$uploads['ok']) {
                    $error = $uploads['error'];
                } else {
                    if ($betaGame !== null) {
                        $game = 'beta/' . $betaGame['slug'];
                        $version = $betaGame['version'];
                    } else {
                        $version = tylilo_admin_current_game_version($game);
                    }
                    $attachments = $uploads['files'];
                    tylilo_admin_with(static function (array $data) use ($me, $game, $type, $text, $score, $version, $attachments): array {
                        $data['feedback'][] = [
                            'id' => tylilo_admin_next_id($data['feedback']),
                            'user_id' => (int) $me['id'],
                            'game' => $game,
                            'type' => $type,
                            'text' => $text,
                            'rating' => $score,
                            'version' => $version,
                            'attachments' => $attachments,
                            'resolved' => 0,
                            'created_at' => time(),
                        ];

                        return $data;
                    });
                    tylilo_admin_log((int) $me['id'], (string) $me['username'], 'feedback', $game . ' ' . $type);
                    header('Location: /admin/feedback.php', true, 303);
                    exit;
                }
            }
        } elseif ($owner && $action === 'delete') {
            $id = (int) tylilo_admin_field('id');
            tylilo_admin_with(static function (array $data) use ($id): array {
                $kept = [];
                foreach ($data['feedback'] as $item) {
                    if ((int) $item['id'] === $id) {
                        tylilo_feedback_delete_files(tylilo_feedback_attachments($item));
                        continue;
                    }
                    $kept[] = $item;
                }
                $data['feedback'] = array_values($kept);

                return $data;
            });
            tylilo_admin_log((int) $me['id'], (string) $me['username'], 'feedback-delete', 'Feedback ' . $id);
            header('Location: /admin/feedback.php', true, 303);
            exit;
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
    $html .= '<form method="post" action="/admin/feedback.php" enctype="multipart/form-data"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="send">';
    $html .= '<label for="game">Game</label><select id="game" name="game">';
    foreach (tylilo_admin_game_slugs() as $slug) {
        $html .= '<option value="' . tylilo_e($slug) . '">' . tylilo_e($slug) . '</option>';
    }
    if (tylilo_admin_can($me, 'beta')) {
        foreach (tylilo_beta_games() as $beta) {
            $html .= '<option value="beta:' . tylilo_e($beta['slug']) . '">' . tylilo_e($beta['title']) . ' (' . tylilo_e(tylilo_beta_stage_label(tylilo_beta_stage($beta['slug']))) . ')</option>';
        }
    }
    $html .= '</select><label for="type">Type</label><select id="type" name="type"><option value="bug">Bug</option><option value="idea">Idea</option><option value="other">Other</option></select>';
    $html .= '<label for="text">Feedback</label><textarea id="text" name="text" required></textarea>';
    $html .= '<label for="rating">Rating, optional</label><select id="rating" name="rating"><option value="">No rating</option>';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<option value="' . $i . '">' . $i . '</option>';
    }
    $html .= '</select>';
    $html .= '<label for="attachments">Attachments, optional</label>';
    $html .= '<input id="attachments" name="attachments[]" type="file" multiple accept="' . tylilo_e(tylilo_feedback_accept_attr()) . '" aria-describedby="attachments-hint attachments-list">';
    $html .= '<p id="attachments-hint" class="muted">You can attach up to ' . tylilo_feedback_max_files() . ' files. Pictures (PNG, JPEG, GIF, WebP, or HEIC), short videos (MP4 or MOV), PDFs, text or log files, and JSON are accepted. Each file can be up to ' . tylilo_e(tylilo_feedback_size_label(tylilo_feedback_max_bytes())) . '.</p>';
    $html .= '<ul id="attachments-list" aria-live="polite"></ul>';
    $html .= '<button class="btn send" type="submit">Send feedback</button></form></div>';
    $html .= '<script nonce="' . tylilo_e($cspNonce) . '">(function(){var input=document.getElementById("attachments"),list=document.getElementById("attachments-list");if(!input||!list){return;}function size(n){if(n>=1048576){return(Math.round(n/1048576*10)/10)+" MB";}if(n>=1024){return Math.round(n/1024)+" KB";}return n+" bytes";}input.addEventListener("change",function(){list.replaceChildren();var files=input.files;if(!files||!files.length){return;}var max=' . tylilo_feedback_max_files() . ';if(files.length>max){var note=document.createElement("li");note.textContent="Choose up to "+max+" files.";list.appendChild(note);}for(var i=0;i<files.length;i++){var item=document.createElement("li");item.textContent=files[i].name+" ("+size(files[i].size)+")";list.appendChild(item);}});})();</script>';
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
    $html .= tylilo_feedback_attachments_html($item);
    if ($owner && (int) $item['resolved'] !== 1) {
        $html .= '<form method="post" action="/admin/feedback.php"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="resolve"><input type="hidden" name="id" value="' . (int) $item['id'] . '"><button type="submit">Mark resolved</button></form>';
        $html .= '<form method="post" action="/admin/feedback.php"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="todo"><input type="hidden" name="id" value="' . (int) $item['id'] . '"><button type="submit">Convert to my to-do</button></form>';
    }
    if ($owner) {
        $html .= '<form method="post" action="/admin/feedback.php"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . (int) $item['id'] . '"><button type="submit">Delete</button></form>';
    }
    $html .= '</article>';
}
tylilo_admin_layout($owner ? 'Feedback inbox' : 'Feedback', $html);
