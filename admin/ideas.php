<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

$me = tylilo_admin_guard();
if (!tylilo_admin_can($me, 'ideas')) {
    tylilo_admin_forbid();
}
$owner = tylilo_admin_is_owner($me);
$proposeError = '';
$proposeDraft = [];
$boardError = '';
$ideaError = '';
$ideaErrorId = 0;
$editDraft = null;
$editFailed = false;
$openIdea = 0;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = tylilo_admin_field('action');
    $id = (int) tylilo_admin_field('id');
    $blankIdea = ['user_id' => (int) $me['id'], 'status' => 'idea', 'created_at' => time()];
    if (!tylilo_admin_csrf_ok(tylilo_admin_field('csrf'))) {
        $message = 'That request was not accepted. Please try again.';
        if ($action === 'propose') {
            $proposeError = $message;
            $proposeDraft = tylilo_idea_from_post($owner, $blankIdea);
        } else {
            $ideaError = $message;
            $ideaErrorId = $id;
            $openIdea = $id;
            $editFailed = $action === 'edit';
            if ($editFailed) {
                $editDraft = tylilo_idea_from_post($owner, $blankIdea);
            }
        }
    } else {
        if ($action === 'propose' || $action === 'edit') {
            $previous = $blankIdea;
            if ($action === 'edit') {
                $found = null;
                $stored = tylilo_admin_with(static fn (array $data): array => $data);
                foreach ($stored['ideas'] as $idea) {
                    if ((int) $idea['id'] === $id && ((int) $idea['user_id'] === (int) $me['id'] || $owner)) {
                        $found = $idea;
                    }
                }
                if (!is_array($found)) {
                    $boardError = 'That idea could not be changed.';
                } else {
                    $previous = $found;
                }
            }
            $image = '';
            $imageError = false;
            if ($boardError === '' && isset($_FILES['image']) && is_array($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $image = tylilo_admin_store_idea_image($_FILES['image']);
                $imageError = $image === '';
            }
            $fields = $boardError === '' ? tylilo_idea_from_post($owner, $previous) : [];
            if ($boardError !== '') {
                // already set
            } elseif ($imageError || ($fields['title'] ?? '') === '' || ($fields['pitch'] ?? '') === '') {
                $message = $imageError ? 'Use a JPEG, PNG, WebP, or GIF under 2 MB.' : 'An idea needs a working title and a one-line pitch.';
                if ($action === 'edit') {
                    $ideaError = $message;
                    $ideaErrorId = $id;
                    $openIdea = $id;
                    $editFailed = true;
                    $editDraft = $fields;
                } else {
                    $proposeError = $message;
                    $proposeDraft = $fields;
                }
            } else {
                if ($image !== '') {
                    $fields['image'] = $image;
                }
                $savedId = $action === 'edit' ? $id : 0;
                tylilo_admin_with(static function (array $data) use ($action, $id, $me, $fields, $owner, &$savedId): array {
                    if ($action === 'edit') {
                        foreach ($data['ideas'] as &$idea) {
                            if ((int) $idea['id'] === $id && ((int) $idea['user_id'] === (int) $me['id'] || $owner)) {
                                $fields['id'] = (int) $idea['id'];
                                $fields['user_id'] = (int) $idea['user_id'];
                                $fields['votes'] = tylilo_idea_normalize($idea)['votes'];
                                $fields['created_at'] = (int) ($idea['created_at'] ?? time());
                                if ($fields['image'] === '') {
                                    $fields['image'] = (string) ($idea['image'] ?? '');
                                }
                                $idea = tylilo_idea_normalize($fields);
                                $savedId = (int) $idea['id'];
                            }
                        }

                        return $data;
                    }
                    $fields['id'] = tylilo_admin_next_id($data['ideas']);
                    $fields['user_id'] = (int) $me['id'];
                    $fields['created_at'] = time();
                    $fields['votes'] = [];
                    $savedId = (int) $fields['id'];
                    $data['ideas'][] = tylilo_idea_normalize($fields);

                    return $data;
                });
                tylilo_admin_log((int) $me['id'], (string) $me['username'], $action === 'edit' ? 'idea-edit' : 'idea', $fields['title'] . ' · ' . tylilo_idea_choice('status', $fields['status']));
                header('Location: /admin/ideas.php?open=' . $savedId . '#idea-' . $savedId, true, 303);
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
            header('Location: /admin/ideas.php?open=' . $id . '#idea-' . $id, true, 303);
            exit;
        } elseif ($action === 'vote') {
            tylilo_admin_with(static function (array $data) use ($id, $me): array {
                foreach ($data['ideas'] as &$idea) {
                    if ((int) $idea['id'] === $id) {
                        $idea = tylilo_idea_normalize($idea);
                        $idea['votes'] = tylilo_idea_toggle_vote($idea['votes'], (int) $me['id']);
                    }
                }

                return $data;
            });
            tylilo_admin_log((int) $me['id'], (string) $me['username'], 'vote', 'Idea ' . $id);
            header('Location: /admin/ideas.php?open=' . $id . '#idea-' . $id, true, 303);
            exit;
        }
    }
}

$choices = tylilo_idea_choices();
$filterGenre = isset($_GET['genre']) && is_string($_GET['genre']) ? $_GET['genre'] : '';
$filterStatus = isset($_GET['status']) && is_string($_GET['status']) ? $_GET['status'] : '';
$filterPriority = isset($_GET['priority']) && is_string($_GET['priority']) ? $_GET['priority'] : '';
if (tylilo_idea_choice('genre', $filterGenre) === '') {
    $filterGenre = '';
}
if (tylilo_idea_choice('status', $filterStatus) === '') {
    $filterStatus = '';
}
if (tylilo_idea_choice('priority', $filterPriority) === '') {
    $filterPriority = '';
}

$data = tylilo_admin_with(static fn (array $stored): array => $stored);
$names = [];
foreach ($data['users'] as $user) {
    $names[(int) $user['id']] = (string) $user['username'];
}

$newRequested = isset($_GET['new']) && (is_string($_GET['new']) || is_int($_GET['new'])) && (string) $_GET['new'] === '1';
if ($openIdea === 0 && isset($_GET['open']) && (is_string($_GET['open']) || is_int($_GET['open'])) && preg_match('/\A[0-9]+\z/', (string) $_GET['open']) === 1) {
    $openIdea = (int) $_GET['open'];
}

$panel = tylilo_idea_new_panel($proposeError, $proposeDraft, $owner);
$html = '';
if ($boardError !== '') {
    $html .= '<div class="card" role="alert"><p>' . tylilo_e($boardError) . '</p></div>';
}
if ($newRequested && $proposeError === '') {
    $html .= '<div class="card" id="new-idea-panel">' . $panel . '</div>';
} else {
    $html .= '<p class="idea-new-row"><a class="btn send" id="new-idea-open" href="/admin/ideas.php?new=1">New idea</a></p>';
    if ($proposeError !== '') {
        $html .= '<div class="card" id="new-idea-panel">' . $panel . '</div>';
        $html .= '<dialog id="new-idea-dialog" class="card idea-dialog" aria-labelledby="new-idea-title" aria-modal="true" data-open="true"></dialog>';
    } else {
        $html .= '<dialog id="new-idea-dialog" class="card idea-dialog" aria-labelledby="new-idea-title" aria-modal="true">';
        $html .= '<div id="new-idea-panel">' . $panel . '</div></dialog>';
    }
    $html .= '<form class="card idea-filters" method="get" action="/admin/ideas.php">';
    $html .= '<h2>Filter the board</h2>';
    $html .= tylilo_idea_select('genre', 'filter-genre', 'Genre', $choices['genre'], $filterGenre);
    $html .= tylilo_idea_select('status', 'filter-status', 'Status', $choices['status'], $filterStatus);
    $html .= tylilo_idea_select('priority', 'filter-priority', 'Priority', $choices['priority'], $filterPriority);
    $html .= '<button class="btn send" type="submit">Apply filters</button> <a class="btn" href="/admin/ideas.php">Clear filters</a></form>';

    $shown = 0;
    foreach (array_reverse($data['ideas']) as $raw) {
        $idea = tylilo_idea_normalize($raw);
        if ($filterGenre !== '' && $idea['genre'] !== $filterGenre) {
            continue;
        }
        if ($filterStatus !== '' && $idea['status'] !== $filterStatus) {
            continue;
        }
        if ($filterPriority !== '' && $idea['priority'] !== $filterPriority) {
            continue;
        }
        $shown++;
        $ideaId = (int) $idea['id'];
        $html .= tylilo_idea_card(
            $idea,
            $data['comments'],
            $names,
            $me,
            $owner,
            $openIdea === $ideaId,
            $ideaErrorId === $ideaId ? $ideaError : '',
            $editFailed && $ideaErrorId === $ideaId,
            $editFailed && $ideaErrorId === $ideaId ? $editDraft : null
        );
    }
    if ($shown === 0) {
        $html .= '<div class="card"><p>No ideas match these filters.</p></div>';
    }
}
$html .= '<script src="' . tylilo_e(tylilo_asset('/assets/js/idea-help.js')) . '" defer></script>';
$html .= '<script src="' . tylilo_e(tylilo_asset('/assets/js/ideas.js')) . '" defer></script>';
tylilo_admin_layout('Ideas', $html);

function tylilo_idea_select(string $name, string $id, string $label, array $options, string $value): string
{
    $html = tylilo_idea_info($id, $label, $name);
    $html .= '<select id="' . tylilo_e($id) . '" name="' . tylilo_e($name) . '" aria-describedby="' . tylilo_e($id) . '-help">';
    $html .= '<option value="">Choose</option>';
    foreach ($options as $key => $text) {
        $html .= '<option value="' . tylilo_e($key) . '"' . ($value === $key ? ' selected' : '') . '>' . tylilo_e($text) . '</option>';
    }

    return $html . '</select>';
}

function tylilo_idea_checks(string $legend, string $name, array $options, array $selected, string $prefix): string
{
    $helpId = $prefix . '-help';
    $html = '<fieldset aria-describedby="' . tylilo_e($helpId) . '"><legend><span class="idea-label">' . tylilo_e($legend) . tylilo_idea_info_button($helpId, $legend) . '</span></legend>';
    $html .= tylilo_idea_info_panel($helpId, $name);
    foreach ($options as $key => $text) {
        $id = $prefix . '-' . $key;
        $html .= '<label for="' . tylilo_e($id) . '"><input type="checkbox" id="' . tylilo_e($id) . '" name="' . tylilo_e($name) . '[]" value="' . tylilo_e($key) . '"' . (in_array($key, $selected, true) ? ' checked' : '') . '> ' . tylilo_e($text) . '</label>';
    }

    return $html . '</fieldset>';
}

function tylilo_idea_form(array $idea, string $action, int $id, bool $owner): string
{
    $idea = tylilo_idea_normalize($idea);
    $choices = tylilo_idea_choices();
    $suffix = $id > 0 ? '-' . $id : '';
    $html = '<form class="idea-form" method="post" action="/admin/ideas.php" enctype="multipart/form-data">';
    $html .= '<input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="' . tylilo_e($action) . '"><input type="hidden" name="id" value="' . $id . '">';
    $html .= tylilo_idea_info('title' . $suffix, 'Working title', 'title');
    $html .= '<input id="title' . $suffix . '" name="title" required maxlength="80" aria-describedby="title' . $suffix . '-help" value="' . tylilo_e($idea['title']) . '">';
    $html .= tylilo_idea_info('pitch' . $suffix, 'One-line pitch', 'pitch');
    $html .= '<input id="pitch' . $suffix . '" name="pitch" required maxlength="180" aria-describedby="pitch' . $suffix . '-help" value="' . tylilo_e($idea['pitch']) . '">';
    $html .= tylilo_idea_select('genre', 'genre' . $suffix, 'Genre', $choices['genre'], $idea['genre']);
    $html .= tylilo_idea_select('style', 'style' . $suffix, 'Visual style', $choices['style'], $idea['style']);
    $html .= tylilo_idea_info('inspired' . $suffix, 'Inspired by', 'inspired');
    $html .= '<input id="inspired' . $suffix . '" name="inspired" maxlength="120" aria-describedby="inspired' . $suffix . '-help" value="' . tylilo_e($idea['inspired']) . '">';
    $html .= tylilo_idea_checks('Controls', 'controls', $choices['controls'], $idea['controls'], 'controls' . $suffix);
    $html .= tylilo_idea_checks('Devices', 'devices', $choices['devices'], $idea['devices'], 'devices' . $suffix);
    $html .= tylilo_idea_select('session', 'session' . $suffix, 'Session length', $choices['session'], $idea['session']);
    $html .= tylilo_idea_select('players', 'players' . $suffix, 'Players', $choices['players'], $idea['players']);
    $html .= tylilo_idea_select('difficulty', 'difficulty' . $suffix, 'Difficulty', $choices['difficulty'], $idea['difficulty']);
    $html .= tylilo_idea_select('scoring', 'scoring' . $suffix, 'Scoring', $choices['scoring'], $idea['scoring']);
    $html .= '<span class="idea-label"><label for="leaderboard' . $suffix . '"><input type="checkbox" id="leaderboard' . $suffix . '" name="leaderboard" value="1" aria-describedby="leaderboard' . $suffix . '-help"' . ($idea['leaderboard'] === 1 ? ' checked' : '') . '> Leaderboard</label>' . tylilo_idea_info_button('leaderboard' . $suffix . '-help', 'Leaderboard') . '</span>';
    $html .= tylilo_idea_info_panel('leaderboard' . $suffix . '-help', 'leaderboard');
    $html .= tylilo_idea_select('audience', 'audience' . $suffix, 'Audience', $choices['audience'], $idea['audience']);
    $html .= tylilo_idea_select('effort', 'effort' . $suffix, 'Effort', $choices['effort'], $idea['effort']);
    if ($owner) {
        $html .= tylilo_idea_select('status', 'status' . $suffix, 'Status', $choices['status'], $idea['status']);
        $html .= tylilo_idea_select('priority', 'priority' . $suffix, 'Priority', $choices['priority'], $idea['priority']);
    } elseif (!in_array($idea['status'], tylilo_idea_owner_statuses(), true)) {
        $html .= tylilo_idea_select('status', 'status' . $suffix, 'Status', ['idea' => 'Idea', 'discussing' => 'Discussing'], $idea['status'] === 'discussing' ? 'discussing' : 'idea');
    } else {
        $html .= '<p>Status: ' . tylilo_e(tylilo_idea_choice('status', $idea['status'])) . '</p>';
    }
    $html .= tylilo_idea_info('notes' . $suffix, 'Notes', 'notes');
    $html .= '<textarea id="notes' . $suffix . '" name="notes" aria-describedby="notes' . $suffix . '-help">' . tylilo_e($idea['notes']) . '</textarea>';
    $imageLabel = $action === 'edit' ? 'Replace sketch, optional' : 'Sketch or reference, optional';
    $html .= tylilo_idea_info('image' . $suffix, $imageLabel, 'image');
    $html .= '<input id="image' . $suffix . '" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif" aria-describedby="image' . $suffix . '-help">';
    $html .= '<button class="btn send" type="submit">' . ($action === 'edit' ? 'Save changes' : 'Share idea') . '</button></form>';

    return $html;
}

function tylilo_idea_new_panel(string $error, array $draft, bool $owner): string
{
    $html = '<div class="idea-dialog-bar">';
    $html .= '<h2 id="new-idea-title">New idea</h2>';
    $html .= '<button type="button" class="idea-dialog-close" id="new-idea-close" hidden>Cancel</button>';
    $html .= '<a class="idea-cancel-link" href="/admin/ideas.php">Cancel</a>';
    $html .= '</div>';
    if ($error !== '') {
        $html .= '<div class="form-status error" role="alert"><p>' . tylilo_e($error) . '</p></div>';
    }
    $html .= tylilo_idea_form($draft, 'propose', 0, $owner);

    return $html;
}

function tylilo_idea_category(array $idea): string
{
    $genre = tylilo_idea_choice('genre', (string) ($idea['genre'] ?? ''));
    if ($genre !== '') {
        return $genre;
    }
    $label = tylilo_idea_choice('status', (string) ($idea['status'] ?? ''));
    $priority = tylilo_idea_choice('priority', (string) ($idea['priority'] ?? ''));
    if ($priority !== '') {
        $label .= ' · ' . $priority;
    }

    return $label;
}

function tylilo_idea_card(array $idea, array $comments, array $names, array $me, bool $owner, bool $open = false, string $error = '', bool $editOpen = false, ?array $editDraft = null): string
{
    $id = (int) $idea['id'];
    $category = tylilo_idea_category($idea);
    $kicker = tylilo_idea_choice('status', $idea['status']);
    if ($idea['priority'] !== '') {
        $kicker .= ' · ' . tylilo_idea_choice('priority', $idea['priority']);
    }
    $html = '<article class="card idea-card" id="idea-' . $id . '">';
    $html .= '<details' . ($open ? ' open' : '') . '>';
    $html .= '<summary><h2>' . tylilo_e($idea['title']) . '</h2>';
    $html .= '<span class="idea-category"><span class="visually-hidden">Category: </span>' . tylilo_e($category) . '</span></summary>';
    $html .= '<div class="idea-card-body">';
    if ($error !== '') {
        $html .= '<div class="form-status error" role="alert"><p>' . tylilo_e($error) . '</p></div>';
    }
    if ($kicker !== $category) {
        $html .= '<p class="kicker">' . tylilo_e($kicker) . '</p>';
    }
    $html .= '<p>' . tylilo_e($idea['pitch']) . '</p>';
    $html .= '<dl class="idea-facts">';
    foreach (['genre' => 'Genre', 'style' => 'Visual style', 'session' => 'Session length', 'players' => 'Players', 'difficulty' => 'Difficulty', 'scoring' => 'Scoring', 'audience' => 'Audience', 'effort' => 'Effort'] as $key => $label) {
        if ($idea[$key] === '') {
            continue;
        }
        $html .= '<dt>' . tylilo_e($label) . '</dt><dd>' . tylilo_e(tylilo_idea_choice($key, $idea[$key])) . '</dd>';
    }
    if ($idea['inspired'] !== '') {
        $html .= '<dt>Inspired by</dt><dd>' . tylilo_e($idea['inspired']) . '</dd>';
    }
    if ($idea['controls'] !== []) {
        $html .= '<dt>Controls</dt><dd>' . tylilo_e(implode(', ', array_map(static fn (string $item): string => tylilo_idea_choice('controls', $item), $idea['controls']))) . '</dd>';
    }
    if ($idea['devices'] !== []) {
        $html .= '<dt>Devices</dt><dd>' . tylilo_e(implode(', ', array_map(static fn (string $item): string => tylilo_idea_choice('devices', $item), $idea['devices']))) . '</dd>';
    }
    if ($idea['leaderboard'] === 1) {
        $html .= '<dt>Leaderboard</dt><dd>Yes</dd>';
    }
    $html .= '</dl>';
    if ($idea['notes'] !== '') {
        $html .= '<p>' . tylilo_e($idea['notes']) . '</p>';
    }
    if ($idea['image'] !== '') {
        $html .= '<p><img src="/admin/idea-image.php?id=' . $id . '" alt="Sketch for ' . tylilo_e($idea['title']) . '"></p>';
    }
    $voted = in_array((int) $me['id'], $idea['votes'], true);
    $html .= '<form method="post" action="/admin/ideas.php#idea-' . $id . '"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="vote"><input type="hidden" name="id" value="' . $id . '">';
    $html .= '<button type="submit">' . ($voted ? 'Remove vote' : 'Vote') . '</button> <span>' . count($idea['votes']) . ' ' . (count($idea['votes']) === 1 ? 'vote' : 'votes') . '</span></form>';
    $mine = [];
    foreach ($comments as $comment) {
        if ((int) $comment['idea_id'] === $id) {
            $mine[] = $comment;
        }
    }
    if ($mine !== []) {
        $html .= '<h3>Comments</h3><ul class="idea-comments">';
        foreach ($mine as $comment) {
            $who = $names[(int) ($comment['user_id'] ?? 0)] ?? 'Someone';
            $html .= '<li><strong>' . tylilo_e($who) . '</strong> ' . tylilo_e((string) $comment['body']) . '</li>';
        }
        $html .= '</ul>';
    }
    $html .= '<form method="post" action="/admin/ideas.php#idea-' . $id . '"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="comment"><input type="hidden" name="id" value="' . $id . '">';
    $html .= '<label for="comment-' . $id . '">Comment</label><input id="comment-' . $id . '" name="comment" maxlength="500">';
    $html .= '<button type="submit">Comment</button></form>';
    if ((int) $idea['user_id'] === (int) $me['id'] || $owner) {
        $html .= '<details' . ($editOpen ? ' open' : '') . '><summary>Edit this idea</summary>';
        $html .= tylilo_idea_form(is_array($editDraft) ? $editDraft : $idea, 'edit', $id, $owner);
        $html .= '</details>';
    }
    $html .= '</div></details></article>';

    return $html;
}
