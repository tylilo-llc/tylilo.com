<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

$me = tylilo_admin_guard();
if (!tylilo_admin_can($me, 'ideas')) {
    tylilo_admin_forbid();
}
$owner = tylilo_admin_is_owner($me);
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!tylilo_admin_csrf_ok(tylilo_admin_field('csrf'))) {
        $error = 'That request was not accepted. Please try again.';
    } else {
        $action = tylilo_admin_field('action');
        $id = (int) tylilo_admin_field('id');
        if ($action === 'propose' || $action === 'edit') {
            $title = tylilo_admin_field('title');
            $pitch = is_string($_POST['pitch'] ?? null) ? trim($_POST['pitch']) : '';
            $notes = is_string($_POST['notes'] ?? null) ? trim($_POST['notes']) : '';
            $image = '';
            $imageError = false;
            if (isset($_FILES['image']) && is_array($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $image = tylilo_admin_store_idea_image($_FILES['image']);
                $imageError = $image === '';
            }
            if ($title === '' || $pitch === '' || $imageError) {
                $error = $imageError ? 'Use a JPEG, PNG, WebP, or GIF under 2 MB.' : 'An idea needs a title and a pitch.';
            } else {
                tylilo_admin_with(static function (array $data) use ($action, $id, $me, $title, $pitch, $notes, $image): array {
                    if ($action === 'edit') {
                        foreach ($data['ideas'] as &$idea) {
                            if ((int) $idea['id'] === $id && ((int) $idea['user_id'] === (int) $me['id'] || tylilo_admin_is_owner($me))) {
                                $idea['title'] = $title;
                                $idea['pitch'] = $pitch;
                                $idea['notes'] = $notes;
                                if ($image !== '') {
                                    $idea['image'] = $image;
                                }
                            }
                        }

                        return $data;
                    }
                    $data['ideas'][] = [
                        'id' => tylilo_admin_next_id($data['ideas']),
                        'user_id' => (int) $me['id'],
                        'title' => $title,
                        'pitch' => $pitch,
                        'notes' => $notes,
                        'image' => $image,
                        'status' => 'proposed',
                        'created_at' => time(),
                    ];

                    return $data;
                });
                tylilo_admin_log((int) $me['id'], (string) $me['username'], $action === 'edit' ? 'idea-edit' : 'idea', $title);
                header('Location: /admin/ideas.php', true, 303);
                exit;
            }
        } elseif ($action === 'comment') {
            $body = is_string($_POST['comment'] ?? null) ? trim($_POST['comment']) : '';
            if ($body !== '') {
                tylilo_admin_with(static function (array $data) use ($id, $me, $body): array {
                    $data['comments'][] = [
                        'id' => tylilo_admin_next_id($data['comments']),
                        'idea_id' => $id,
                        'user_id' => (int) $me['id'],
                        'body' => $body,
                        'created_at' => time(),
                    ];

                    return $data;
                });
                tylilo_admin_log((int) $me['id'], (string) $me['username'], 'comment', 'Idea ' . $id);
            }
            header('Location: /admin/ideas.php', true, 303);
            exit;
        } elseif ($action === 'approve' && $owner) {
            tylilo_admin_with(static function (array $data) use ($id): array {
                foreach ($data['ideas'] as &$idea) {
                    if ((int) $idea['id'] === $id) {
                        $idea['status'] = 'approved';
                    }
                }

                return $data;
            });
            tylilo_admin_log((int) $me['id'], (string) $me['username'], 'approve', 'Idea ' . $id);
            header('Location: /admin/ideas.php', true, 303);
            exit;
        }
    }
}

$data = tylilo_admin_with(static fn (array $stored): array => $stored);
$html = '<div class="card"><h2>Propose a game</h2>';
if ($error !== '') {
    $html .= '<div class="form-status error" role="alert"><p>' . tylilo_e($error) . '</p></div>';
}
$html .= '<form method="post" action="/admin/ideas.php" enctype="multipart/form-data"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="propose">';
$html .= '<label for="title">Title</label><input id="title" name="title" required>';
$html .= '<label for="pitch">Pitch</label><textarea id="pitch" name="pitch" required></textarea>';
$html .= '<label for="notes">Notes</label><textarea id="notes" name="notes"></textarea>';
$html .= '<label for="image">Picture, optional</label><input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif">';
$html .= '<button class="btn send" type="submit">Share idea</button></form></div>';
foreach (array_reverse($data['ideas']) as $idea) {
    $html .= '<article class="card"><h2>' . tylilo_e((string) $idea['title']) . '</h2>';
    $html .= '<p>' . ($idea['status'] === 'approved' ? 'Approved' : 'Proposed') . '</p>';
    $html .= '<p>' . tylilo_e((string) $idea['pitch']) . '</p>';
    if ((string) $idea['notes'] !== '') {
        $html .= '<p>' . tylilo_e((string) $idea['notes']) . '</p>';
    }
    if ((string) $idea['image'] !== '') {
        $html .= '<p><img src="/admin/idea-image.php?id=' . (int) $idea['id'] . '" alt=""></p>';
    }
    foreach ($data['comments'] as $comment) {
        if ((int) $comment['idea_id'] === (int) $idea['id']) {
            $html .= '<p>' . tylilo_e((string) $comment['body']) . '</p>';
        }
    }
    if ((int) $idea['user_id'] === (int) $me['id'] || $owner) {
        $html .= '<form method="post" action="/admin/ideas.php" enctype="multipart/form-data"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="' . (int) $idea['id'] . '">';
        $html .= '<label for="title-' . (int) $idea['id'] . '">Title</label><input id="title-' . (int) $idea['id'] . '" name="title" required value="' . tylilo_e((string) $idea['title']) . '">';
        $html .= '<label for="pitch-' . (int) $idea['id'] . '">Pitch</label><textarea id="pitch-' . (int) $idea['id'] . '" name="pitch" required>' . tylilo_e((string) $idea['pitch']) . '</textarea>';
        $html .= '<label for="notes-' . (int) $idea['id'] . '">Notes</label><textarea id="notes-' . (int) $idea['id'] . '" name="notes">' . tylilo_e((string) $idea['notes']) . '</textarea>';
        $html .= '<label for="image-' . (int) $idea['id'] . '">Replace picture, optional</label><input id="image-' . (int) $idea['id'] . '" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif">';
        $html .= '<button type="submit">Save changes</button></form>';
    }
    $html .= '<form method="post" action="/admin/ideas.php"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="comment"><input type="hidden" name="id" value="' . (int) $idea['id'] . '">';
    $html .= '<label for="comment-' . (int) $idea['id'] . '">Comment</label><input id="comment-' . (int) $idea['id'] . '" name="comment">';
    $html .= '<button type="submit">Comment</button></form>';
    if ($owner && $idea['status'] !== 'approved') {
        $html .= '<form method="post" action="/admin/ideas.php"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="' . (int) $idea['id'] . '"><button type="submit">Mark approved</button></form>';
    }
    $html .= '</article>';
}
tylilo_admin_layout('Ideas', $html);
