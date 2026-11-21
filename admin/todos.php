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
        $notes = is_string($_POST['notes'] ?? null) ? trim($_POST['notes']) : '';
        $priority = tylilo_admin_field('priority');
        $status = tylilo_admin_field('status');
        if (!in_array($priority, ['low', 'normal', 'high'], true)) {
            $priority = 'normal';
        }
        if (!in_array($status, ['open', 'doing', 'done'], true)) {
            $status = 'open';
        }
        $bad = false;
        $uid = (int) $me['id'];
        tylilo_admin_with(static function (array $data) use ($action, $id, $title, $notes, $priority, $status, $uid, &$bad): array {
            $owns = static function (array $row) use ($uid, $data): bool {
                $owner = isset($row['user_id']) ? (int) $row['user_id'] : tylilo_admin_owner_id($data);

                return $owner === $uid;
            };
            if ($action === 'delete') {
                $data['todos'] = array_values(array_filter($data['todos'], static fn (array $row): bool => (int) $row['id'] !== $id || !$owns($row)));

                return $data;
            }
            if ($action === 'done') {
                foreach ($data['todos'] as &$row) {
                    if ((int) $row['id'] === $id && $owns($row)) {
                        $row['status'] = $row['status'] === 'done' ? 'open' : 'done';
                        $row['updated_at'] = time();
                    }
                }

                return $data;
            }
            if ($title === '') {
                $bad = true;

                return $data;
            }
            $now = time();
            $found = false;
            foreach ($data['todos'] as &$row) {
                if ((int) $row['id'] === $id && $owns($row)) {
                    $row['title'] = $title;
                    $row['notes'] = $notes;
                    $row['priority'] = $priority;
                    $row['status'] = $status;
                    $row['updated_at'] = $now;
                    $found = true;
                }
            }
            unset($row);
            if (!$found && $id === 0) {
                $data['todos'][] = [
                    'id' => tylilo_admin_next_id($data['todos']),
                    'user_id' => $uid,
                    'title' => $title,
                    'notes' => $notes,
                    'priority' => $priority,
                    'status' => $status,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            return $data;
        });
        if ($bad) {
            $error = 'A to-do needs a title.';
        } else {
            header('Location: /admin/todos.php', true, 303);
            exit;
        }
    }
}

$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$data = tylilo_admin_with(static fn (array $stored): array => $stored);
$ownerFallback = tylilo_admin_owner_id($data);
$todos = array_values(array_filter($data['todos'], static function (array $todo) use ($viewId, $ownerFallback): bool {
    $uid = isset($todo['user_id']) ? (int) $todo['user_id'] : $ownerFallback;

    return $uid === $viewId;
}));
$editing = null;
foreach ($todos as $todo) {
    if ((int) $todo['id'] === $editId) {
        $editing = $todo;
    }
}
$html = '';
if ($readOnly) {
    $html .= '<div class="card"><h2>Team to-dos</h2><p>These to-dos are read-only.</p></div>';
} else {
    $html .= '<div class="card"><h2>' . ($editing ? 'Edit to-do' : 'New to-do') . '</h2>';
    if ($error !== '') {
        $html .= '<div class="form-status error" role="alert"><p>' . tylilo_e($error) . '</p></div>';
    }
    $html .= '<form method="post" action="/admin/todos.php"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="' . (int) ($editing['id'] ?? 0) . '">';
    $html .= '<label for="title">Title</label><input id="title" name="title" required value="' . tylilo_e((string) ($editing['title'] ?? '')) . '">';
    $html .= '<label for="notes">Notes</label><textarea id="notes" name="notes">' . tylilo_e((string) ($editing['notes'] ?? '')) . '</textarea>';
    $html .= '<label for="priority">Priority</label><select id="priority" name="priority">';
    foreach (['low', 'normal', 'high'] as $priority) {
        $sel = (($editing['priority'] ?? 'normal') === $priority) ? ' selected' : '';
        $html .= '<option value="' . $priority . '"' . $sel . '>' . ucfirst($priority) . '</option>';
    }
    $html .= '</select><label for="status">Status</label><select id="status" name="status">';
    foreach (['open', 'doing', 'done'] as $status) {
        $sel = (($editing['status'] ?? 'open') === $status) ? ' selected' : '';
        $html .= '<option value="' . $status . '"' . $sel . '>' . ucfirst($status) . '</option>';
    }
    $html .= '</select><button class="btn send" type="submit">Save to-do</button></form></div>';
}
foreach ($todos as $todo) {
    $html .= '<article class="card"><h2>' . tylilo_e((string) $todo['title']) . '</h2>';
    $html .= '<p>' . tylilo_e(ucfirst((string) $todo['priority'])) . ' · ' . tylilo_e(ucfirst((string) $todo['status'])) . '</p>';
    if ((string) $todo['notes'] !== '') {
        $html .= '<p>' . tylilo_e((string) $todo['notes']) . '</p>';
    }
    if (!$readOnly) {
        $html .= '<p><a href="/admin/todos.php?id=' . (int) $todo['id'] . '">Edit</a></p>';
        $html .= '<form method="post" action="/admin/todos.php"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="done"><input type="hidden" name="id" value="' . (int) $todo['id'] . '"><button type="submit">' . ($todo['status'] === 'done' ? 'Mark open' : 'Mark done') . '</button></form>';
        $html .= '<form method="post" action="/admin/todos.php"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . (int) $todo['id'] . '"><button type="submit">Delete</button></form>';
    }
    $html .= '</article>';
}
tylilo_admin_layout('To-dos', $html);
