<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';
tylilo_admin_guard();

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
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
        tylilo_admin_with(static function (array $data) use ($action, $id, $title, $notes, $priority, $status, &$bad): array {
            if ($action === 'delete') {
                $data['todos'] = array_values(array_filter($data['todos'], static fn (array $row): bool => (int) $row['id'] !== $id));

                return $data;
            }
            if ($action === 'done') {
                foreach ($data['todos'] as &$row) {
                    if ((int) $row['id'] === $id) {
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
                if ((int) $row['id'] === $id) {
                    $row['title'] = $title;
                    $row['notes'] = $notes;
                    $row['priority'] = $priority;
                    $row['status'] = $status;
                    $row['updated_at'] = $now;
                    $found = true;
                }
            }
            unset($row);
            if (!$found) {
                $data['todos'][] = [
                    'id' => tylilo_admin_next_id($data['todos']),
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
$editing = null;
foreach ($data['todos'] as $todo) {
    if ((int) $todo['id'] === $editId) {
        $editing = $todo;
    }
}
$html = '<div class="card"><h2>' . ($editing ? 'Edit to-do' : 'New to-do') . '</h2>';
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
foreach ($data['todos'] as $todo) {
    $html .= '<article class="card"><h2>' . tylilo_e((string) $todo['title']) . '</h2>';
    $html .= '<p>' . tylilo_e(ucfirst((string) $todo['priority'])) . ' · ' . tylilo_e(ucfirst((string) $todo['status'])) . '</p>';
    if ((string) $todo['notes'] !== '') {
        $html .= '<p>' . tylilo_e((string) $todo['notes']) . '</p>';
    }
    $html .= '<p><a href="/admin/todos.php?id=' . (int) $todo['id'] . '">Edit</a></p>';
    $html .= '<form method="post" action="/admin/todos.php"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="done"><input type="hidden" name="id" value="' . (int) $todo['id'] . '"><button type="submit">' . ($todo['status'] === 'done' ? 'Mark open' : 'Mark done') . '</button></form>';
    $html .= '<form method="post" action="/admin/todos.php"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . (int) $todo['id'] . '"><button type="submit">Delete</button></form></article>';
}
tylilo_admin_layout('To-dos', $html);
