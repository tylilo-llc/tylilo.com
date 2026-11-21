<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

$me = tylilo_admin_guard();
if (!tylilo_admin_can($me, 'notes')) {
    tylilo_admin_forbid();
}
$viewId = (int) $me['id'];
$readOnly = false;
if (isset($_GET['user']) && tylilo_admin_is_owner($me) && (int) $_GET['user'] !== $viewId) {
    $viewId = (int) $_GET['user'];
    $readOnly = true;
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if ($readOnly) {
        tylilo_admin_forbid();
    }
    if (!tylilo_admin_csrf_ok(tylilo_admin_field('csrf'))) {
        $error = 'That request was not accepted. Please try again.';
    } else {
        $action = tylilo_admin_field('action');
        $id = (int) tylilo_admin_field('id');
        $title = tylilo_admin_field('title');
        $body = is_string($_POST['body'] ?? null) ? trim($_POST['body']) : '';
        $tags = tylilo_admin_field('tags');
        $uid = (int) $me['id'];
        tylilo_admin_with(static function (array $data) use ($action, $id, $title, $body, $tags, $uid): array {
            $owns = static function (array $row) use ($uid, $data): bool {
                $owner = isset($row['user_id']) ? (int) $row['user_id'] : tylilo_admin_owner_id($data);

                return $owner === $uid;
            };
            if ($action === 'delete') {
                $data['notes'] = array_values(array_filter($data['notes'], static fn (array $row): bool => (int) $row['id'] !== $id || !$owns($row)));

                return $data;
            }
            if ($title === '') {
                return $data;
            }
            $now = time();
            $found = false;
            foreach ($data['notes'] as &$row) {
                if ((int) $row['id'] === $id && $owns($row)) {
                    $row['title'] = $title;
                    $row['body'] = $body;
                    $row['tags'] = $tags;
                    $row['updated_at'] = $now;
                    $found = true;
                }
            }
            unset($row);
            if (!$found && $id === 0) {
                $data['notes'][] = [
                    'id' => tylilo_admin_next_id($data['notes']),
                    'user_id' => $uid,
                    'title' => $title,
                    'body' => $body,
                    'tags' => $tags,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            return $data;
        });
        if ($title === '' && $action !== 'delete') {
            $error = 'A note needs a title.';
        } else {
            header('Location: /admin/notes.php' . ($readOnly ? '?user=' . $viewId : ''), true, 303);
            exit;
        }
    }
}

$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$data = tylilo_admin_with(static fn (array $stored): array => $stored);
$ownerFallback = tylilo_admin_owner_id($data);
$notes = array_values(array_filter($data['notes'], static function (array $note) use ($viewId, $ownerFallback): bool {
    $uid = isset($note['user_id']) ? (int) $note['user_id'] : $ownerFallback;

    return $uid === $viewId;
}));
$editing = null;
foreach ($notes as $note) {
    if ((int) $note['id'] === $editId) {
        $editing = $note;
    }
}
usort($notes, static fn (array $a, array $b): int => ((int) $b['updated_at']) <=> ((int) $a['updated_at']));
$html = '';
if ($readOnly) {
    $html .= '<div class="card"><h2>Team notes</h2><p>These notes are read-only.</p></div>';
} else {
    $html .= '<div class="card"><h2>' . ($editing ? 'Edit note' : 'New note') . '</h2>';
    if ($error !== '') {
        $html .= '<div class="form-status error" role="alert"><p>' . tylilo_e($error) . '</p></div>';
    }
    $html .= '<form method="post" action="/admin/notes.php"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '">';
    $html .= '<input type="hidden" name="action" value="save"><input type="hidden" name="id" value="' . (int) ($editing['id'] ?? 0) . '">';
    $html .= '<label for="title">Title</label><input id="title" name="title" required value="' . tylilo_e((string) ($editing['title'] ?? '')) . '">';
    $html .= '<label for="body">Body</label><textarea id="body" name="body">' . tylilo_e((string) ($editing['body'] ?? '')) . '</textarea>';
    $html .= '<label for="tags">Tags</label><input id="tags" name="tags" value="' . tylilo_e((string) ($editing['tags'] ?? '')) . '">';
    $html .= '<button class="btn send" type="submit">Save note</button></form></div>';
}
foreach ($notes as $note) {
    $html .= '<article class="card"><p class="kicker">' . tylilo_e(gmdate('Y-m-d', (int) $note['created_at'])) . '</p>';
    $html .= '<h2>' . tylilo_e((string) $note['title']) . '</h2>';
    $html .= tylilo_admin_markdown((string) $note['body']);
    if ((string) $note['tags'] !== '') {
        $html .= '<p class="muted">Tags: ' . tylilo_e((string) $note['tags']) . '</p>';
    }
    if (!$readOnly) {
        $html .= '<p><a href="/admin/notes.php?id=' . (int) $note['id'] . '">Edit</a></p>';
        $html .= '<form method="post" action="/admin/notes.php"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . (int) $note['id'] . '"><button type="submit">Delete</button></form>';
    }
    $html .= '</article>';
}
tylilo_admin_layout('Notes', $html);
