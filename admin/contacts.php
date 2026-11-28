<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';
require_once dirname(__DIR__) . '/includes/contacts.php';

$me = tylilo_admin_guard();
if (!tylilo_admin_can($me, 'contacts.view')) {
    tylilo_admin_forbid();
}

$notice = '';
$error = '';
$statuses = tylilo_contact_statuses();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = tylilo_admin_field('action');
    $cid = tylilo_admin_field('id');
    if (!tylilo_admin_csrf_ok(tylilo_admin_field('csrf'))) {
        $error = 'That request was not accepted. Please try again.';
        $cid = '';
    }
    $profile = $cid !== '' ? tylilo_contact_get($cid) : null;
    if ($error === '' && $profile === null) {
        $error = 'That person was not found.';
    } elseif ($error === '') {
        $to = '/admin/contacts.php?c=' . rawurlencode($cid);
        $who = (int) $me['id'];
        $user = (string) $me['username'];
        if ($action === 'note') {
            if (tylilo_contact_add_note((string) $profile['email'], tylilo_admin_field('note'))) {
                tylilo_admin_log($who, $user, 'contact-note', 'Added a note to contact ' . $cid);
                header('Location: ' . $to . '&saved=note', true, 303);
                exit;
            }
            $error = 'Write a note of up to 4,000 characters.';
        } elseif ($action === 'status') {
            if (tylilo_contact_set_status((string) $profile['email'], tylilo_admin_field('status'))) {
                tylilo_admin_log($who, $user, 'contact-status', 'Changed status for contact ' . $cid);
                header('Location: ' . $to . '&saved=status', true, 303);
                exit;
            }
            $error = 'Choose one of the listed statuses.';
        } elseif ($action === 'export') {
            $format = tylilo_admin_field('format') === 'text' ? 'text' : 'json';
            tylilo_admin_log($who, $user, 'contact-export', 'Exported contact ' . $cid . ' as ' . $format);
            if ($format === 'text') {
                header('Content-Type: text/plain; charset=UTF-8');
                header('Content-Disposition: attachment; filename="contact-' . $cid . '.txt"');
                $body = tylilo_contact_export_text($profile);
            } else {
                header('Content-Type: application/json; charset=UTF-8');
                header('Content-Disposition: attachment; filename="contact-' . $cid . '.json"');
                $body = tylilo_contact_export_json($profile);
            }
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: no-store');
            echo $body;
            exit;
        } elseif ($action === 'delete') {
            if (tylilo_admin_field('confirm') !== 'yes') {
                $error = 'Tick the box to confirm before deleting.';
            } else {
                $removed = tylilo_contact_delete((string) $profile['email']);
                tylilo_admin_log($who, $user, 'contact-delete', 'Deleted contact ' . $cid . ' and ' . $removed . ' inquiries');
                header('Location: /admin/contacts.php?deleted=1', true, 303);
                exit;
            }
        }
    }
}

try {
    $purged = tylilo_contacts_purge_expired();
    if ($purged > 0) {
        tylilo_admin_log((int) $me['id'], (string) $me['username'], 'contact-purge', 'Deleted ' . $purged . ' profiles with no activity for over 3 years');
    }
} catch (Throwable $e) {
    $purged = 0;
}

$html = '';
if (isset($_GET['deleted'])) {
    $notice = 'That person, their inquiries, and their notes were deleted.';
}
if (($_GET['saved'] ?? '') === 'note') {
    $notice = 'Note saved.';
} elseif (($_GET['saved'] ?? '') === 'status') {
    $notice = 'Status saved.';
}
if ($purged > 0) {
    $notice = ($notice !== '' ? $notice . ' ' : '') . $purged . ' ' . ($purged === 1 ? 'profile was' : 'profiles were') . ' deleted automatically after 3 years without activity.';
}
if ($notice !== '') {
    $html .= '<div class="form-status success" role="status"><p>' . tylilo_e($notice) . '</p></div>';
}
if ($error !== '') {
    $html .= '<div class="form-status error" role="alert"><p>' . tylilo_e($error) . '</p></div>';
}

$selected = null;
if (isset($_GET['c']) && is_string($_GET['c'])) {
    $selected = tylilo_contact_get($_GET['c']);
    if ($selected === null) {
        http_response_code(404);
        $html .= '<div class="card" role="alert"><h2>Person not found</h2><p><a href="/admin/contacts.php">Back to contacts</a></p></div>';
        tylilo_admin_layout('Contacts', $html);
        exit;
    }
}

if ($selected !== null) {
    $p = $selected;
    $cid = (string) $p['id'];
    $title = $p['name'] !== '' ? $p['name'] : $p['email'];
    $csrf = tylilo_e(tylilo_admin_csrf());
    $html .= '<p class="contact-back"><a href="/admin/contacts.php">&larr; All contacts</a></p>';
    $html .= '<div class="card contact-profile"><h2>' . tylilo_e($title) . '</h2>';
    $html .= '<p class="muted">Private. Built only from what this person sent through the inquiry form and your own notes.</p>';
    if (!empty($p['due'])) {
        $html .= '<p class="contact-flag" role="status"><strong>Due for deletion:</strong> no activity for over 3 years. It will be removed automatically.</p>';
    }
    $html .= '<dl class="inq-admin-answers">';
    $rows = [
        'Email' => '<a href="mailto:' . tylilo_e((string) $p['email']) . '">' . tylilo_e((string) $p['email']) . '</a>',
        'Business' => tylilo_e((string) $p['business']),
        'Website' => tylilo_e((string) $p['website']),
        'Location' => tylilo_e((string) $p['location']),
        'Stage' => tylilo_e((string) $p['stage']),
        'Team size' => tylilo_e((string) $p['size']),
        'Industry' => tylilo_e((string) $p['industry']),
        'Latest suggested fit (private)' => tylilo_e((string) $p['fit']),
        'First contact' => tylilo_e(tylilo_contact_when((int) $p['first_contact'])),
        'Last activity' => tylilo_e(tylilo_contact_when((int) $p['last_activity'])),
    ];
    foreach ($rows as $label => $value) {
        $html .= '<dt>' . tylilo_e($label) . '</dt><dd>' . ($value !== '' ? $value : '<span class="muted">Not given</span>') . '</dd>';
    }
    $html .= '</dl>';
    $html .= '<form class="contact-status" method="post" action="/admin/contacts.php?c=' . tylilo_e($cid) . '">'
        . '<input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="' . tylilo_e($cid) . '">'
        . '<label for="contact-status">Status</label><select id="contact-status" name="status">';
    foreach ($statuses as $value => $label) {
        $html .= '<option value="' . tylilo_e($value) . '"' . ($p['status'] === $value ? ' selected' : '') . '>' . tylilo_e($label) . '</option>';
    }
    $html .= '</select><button type="submit">Save status</button></form></div>';

    $html .= '<div class="card"><h2>Private notes</h2>';
    $html .= '<form method="post" action="/admin/contacts.php?c=' . tylilo_e($cid) . '">'
        . '<input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="note"><input type="hidden" name="id" value="' . tylilo_e($cid) . '">'
        . '<label for="contact-note">Add a note</label><textarea id="contact-note" name="note" rows="4" maxlength="4000" required></textarea>'
        . '<button class="btn send" type="submit">Save note</button></form>';
    if ($p['notes'] === []) {
        $html .= '<p class="muted">No notes yet.</p>';
    } else {
        $html .= '<ul class="contact-notes">';
        foreach ($p['notes'] as $n) {
            $html .= '<li><p class="muted">' . tylilo_e(tylilo_contact_when((int) $n['at'])) . '</p><p>' . nl2br(tylilo_e((string) $n['text'])) . '</p></li>';
        }
        $html .= '</ul>';
    }
    $html .= '</div>';

    $html .= '<div class="card"><h2>Timeline</h2><p class="muted">' . count($p['inquiries']) . ' ' . (count($p['inquiries']) === 1 ? 'inquiry' : 'inquiries') . ', newest first.</p>';
    if ($p['inquiries'] === []) {
        $html .= '<p>No inquiries on file.</p>';
    }
    foreach ($p['inquiries'] as $row) {
        $a = is_array($row['answers'] ?? null) ? $row['answers'] : [];
        $html .= '<details class="contact-inquiry"><summary>#' . (int) $row['id'] . ' · ' . tylilo_e(tylilo_contact_when((int) $row['created_at'])) . ' · Suggested fit: ' . tylilo_e((string) ($row['fit'] ?? '')) . '</summary>';
        $html .= '<dl class="inq-admin-answers">';
        foreach (tylilo_inquiry_summary($a) as [$label, $value]) {
            $html .= '<dt>' . tylilo_e($label) . '</dt><dd>' . nl2br(tylilo_e($value)) . '</dd>';
        }
        $html .= '</dl></details>';
    }
    $html .= '</div>';

    $html .= '<div class="card"><h2>Access and deletion requests</h2>';
    $html .= '<p>Export everything held about this person, or delete it. Deleting removes the profile, every inquiry from this email, and all notes.</p>';
    $html .= '<form class="contact-export" method="post" action="/admin/contacts.php">'
        . '<input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="export"><input type="hidden" name="id" value="' . tylilo_e($cid) . '">'
        . '<label for="contact-format">Export format</label><select id="contact-format" name="format"><option value="json">JSON</option><option value="text">Plain text</option></select>'
        . '<button type="submit">Export data</button></form>';
    $html .= '<form class="inq-admin-delete" method="post" action="/admin/contacts.php">'
        . '<input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . tylilo_e($cid) . '">'
        . '<label for="contact-del"><input type="checkbox" id="contact-del" name="confirm" value="yes"> Yes, permanently delete this person, their inquiries, and their notes</label>'
        . '<button type="submit">Delete person</button></form></div>';
    tylilo_admin_layout('Contact: ' . $title, $html);
    exit;
}

try {
    $profiles = tylilo_contacts_all();
} catch (Throwable $e) {
    $profiles = [];
    $html .= '<div class="form-status error" role="alert"><p>Contacts could not be read right now.</p></div>';
}
$html .= '<div class="card"><p>One private profile per person, built only from what they sent through the inquiry form and your own notes. Nothing is gathered from anywhere else.</p>';
$html .= '<p class="muted">' . count($profiles) . ' ' . (count($profiles) === 1 ? 'person' : 'people') . '. Profiles with no activity for over 3 years are deleted automatically, unless the status is Engaged.</p></div>';
if ($profiles === []) {
    $html .= '<div class="card"><p>No contacts yet.</p></div>';
} else {
    $html .= '<div class="card contact-table-wrap"><table class="contact-table"><caption class="visually-hidden">Contacts, most recent activity first</caption><thead><tr>'
        . '<th scope="col">Name</th><th scope="col">Business</th><th scope="col">Status</th><th scope="col">Latest fit</th><th scope="col">Inquiries</th><th scope="col">Last activity</th></tr></thead><tbody>';
    foreach ($profiles as $p) {
        $name = $p['name'] !== '' ? $p['name'] : $p['email'];
        $html .= '<tr><th scope="row"><a href="/admin/contacts.php?c=' . tylilo_e((string) $p['id']) . '">' . tylilo_e($name) . '</a>'
            . '<span class="contact-email">' . tylilo_e((string) $p['email']) . '</span></th>'
            . '<td>' . tylilo_e((string) $p['business']) . '</td>'
            . '<td>' . tylilo_e($statuses[$p['status']] ?? 'New') . (!empty($p['due']) ? ' <strong class="contact-flag">· due for deletion</strong>' : '') . '</td>'
            . '<td>' . tylilo_e((string) $p['fit']) . '</td>'
            . '<td>' . count($p['inquiries']) . '</td>'
            . '<td>' . tylilo_e(tylilo_contact_when((int) $p['last_activity'])) . '</td></tr>';
    }
    $html .= '</tbody></table></div>';
}
tylilo_admin_layout('Contacts', $html);
