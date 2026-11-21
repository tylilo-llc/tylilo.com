<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';
$user = tylilo_admin_guard();
if (!tylilo_admin_is_owner($user)) {
    tylilo_admin_forbid();
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!tylilo_admin_csrf_ok(tylilo_admin_field('csrf'))) {
        $error = 'That request was not accepted. Please try again.';
    } else {
        $id = (int) tylilo_admin_field('id');
        $slug = tylilo_admin_field('slug');
        $version = tylilo_admin_field('version');
        $date = tylilo_admin_field('released_on');
        $md5 = strtolower(tylilo_admin_field('md5'));
        $notes = is_string($_POST['notes'] ?? null) ? trim($_POST['notes']) : '';
        $action = tylilo_admin_field('action');
        $bad = false;
        tylilo_admin_with(static function (array $data) use ($action, $id, $slug, $version, $date, $md5, $notes, &$bad): array {
            if ($action === 'delete') {
                $data['versions'] = array_values(array_filter($data['versions'], static fn (array $row): bool => (int) $row['id'] !== $id));

                return $data;
            }
            if ($slug === '' || $version === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
                $bad = true;

                return $data;
            }
            $now = time();
            $found = false;
            foreach ($data['versions'] as &$row) {
                if ((int) $row['id'] === $id) {
                    $row['slug'] = $slug;
                    $row['version'] = $version;
                    $row['released_on'] = $date;
                    $row['md5'] = $md5;
                    $row['notes'] = $notes;
                    $found = true;
                }
            }
            unset($row);
            if (!$found) {
                $data['versions'][] = [
                    'id' => tylilo_admin_next_id($data['versions']),
                    'slug' => $slug,
                    'version' => $version,
                    'released_on' => $date,
                    'md5' => $md5,
                    'notes' => $notes,
                    'created_at' => $now,
                ];
            }

            return $data;
        });
        if ($bad) {
            $error = 'A version needs a game, a version, and a YYYY-MM-DD date.';
        } else {
            header('Location: /admin/games.php?game=' . rawurlencode($slug), true, 303);
            exit;
        }
    }
}

$slugs = tylilo_admin_game_slugs();
$current = isset($_GET['game']) && is_string($_GET['game']) ? $_GET['game'] : ($slugs[0] ?? '');
if (!in_array($current, $slugs, true) && $slugs !== []) {
    $current = $slugs[0];
}
$data = tylilo_admin_with(static fn (array $stored): array => $stored);
$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$editing = null;
$rows = [];
foreach ($data['versions'] as $row) {
    if ((int) $row['id'] === $editId) {
        $editing = $row;
        $current = (string) $row['slug'];
    }
    if ((string) $row['slug'] === $current) {
        $rows[] = $row;
    }
}
usort($rows, static fn (array $a, array $b): int => strcmp((string) $b['released_on'], (string) $a['released_on']));
$html = '<div class="card"><h2>Beta games</h2><p>A beta build is not in the Arcade. Publishing it is a separate site change, not a button here.</p></div>';
foreach (tylilo_beta_games() as $beta) {
    $html .= '<article class="card"><p class="kicker">Beta</p><h2>' . tylilo_e($beta['title']) . '</h2>';
    $html .= '<p>Version ' . tylilo_e($beta['version'] !== '' ? $beta['version'] : 'unknown') . '</p>';
    $html .= '<p><a class="btn" href="/admin/beta/g/' . rawurlencode($beta['slug']) . '/">Play</a></p></article>';
}
$html .= '<nav class="admin-nav" aria-label="Games">';
foreach ($slugs as $slug) {
    $html .= '<a href="/admin/games.php?game=' . rawurlencode($slug) . '"' . ($slug === $current ? ' aria-current="page"' : '') . '>' . tylilo_e($slug) . '</a>';
}
$html .= '</nav><div class="card"><h2>' . ($editing ? 'Edit version' : 'Add version') . '</h2>';
if ($error !== '') {
    $html .= '<div class="form-status error" role="alert"><p>' . tylilo_e($error) . '</p></div>';
}
$html .= '<form method="post" action="/admin/games.php"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="' . (int) ($editing['id'] ?? 0) . '">';
$html .= '<label for="slug">Game</label><input id="slug" name="slug" required value="' . tylilo_e((string) ($editing['slug'] ?? $current)) . '">';
$html .= '<label for="version">Version</label><input id="version" name="version" required value="' . tylilo_e((string) ($editing['version'] ?? '')) . '">';
$html .= '<label for="released_on">Date</label><input id="released_on" name="released_on" required placeholder="YYYY-MM-DD" value="' . tylilo_e((string) ($editing['released_on'] ?? '')) . '">';
$html .= '<label for="md5">MD5</label><input id="md5" name="md5" value="' . tylilo_e((string) ($editing['md5'] ?? '')) . '">';
$html .= '<label for="notes">Notes</label><textarea id="notes" name="notes">' . tylilo_e((string) ($editing['notes'] ?? '')) . '</textarea>';
$html .= '<button class="btn send" type="submit">Save version</button></form></div>';
foreach ($rows as $row) {
    $html .= '<article class="card"><h2>' . tylilo_e((string) $row['version']) . '</h2>';
    $html .= '<p>' . tylilo_e((string) $row['released_on']) . '</p>';
    if ((string) $row['md5'] !== '') {
        $html .= '<p><code>' . tylilo_e((string) $row['md5']) . '</code></p>';
    }
    if ((string) $row['notes'] !== '') {
        $html .= '<p>' . tylilo_e((string) $row['notes']) . '</p>';
    }
    $html .= '<p><a href="/admin/games.php?id=' . (int) $row['id'] . '">Edit</a></p></article>';
}
tylilo_admin_layout('Games', $html);
