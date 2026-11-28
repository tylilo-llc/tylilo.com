<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';
require_once dirname(__DIR__) . '/includes/inquiry.php';
require_once dirname(__DIR__) . '/includes/contacts.php';

$me = tylilo_admin_guard();
if (!tylilo_admin_can($me, 'inquiries.view')) {
    tylilo_admin_forbid();
}

$userId = (int) $me['id'];
$canContacts = tylilo_admin_can($me, 'contacts.view');
[$sort, $dir] = tylilo_inquiry_sort_from_request();
$viewId = (int) ($_GET['view'] ?? 0);
$notice = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = tylilo_admin_field('action');
    $id = (int) tylilo_admin_field('id');
    if (!tylilo_admin_csrf_ok(tylilo_admin_field('csrf'))) {
        $error = 'That request was not accepted. Please try again.';
    } elseif ($action === 'delete' && tylilo_admin_field('confirm') !== 'yes') {
        $error = 'Tick the box to confirm before deleting.';
    } elseif ($action === 'delete' && $id > 0) {
        tylilo_inquiry_delete($id);
        tylilo_admin_log($userId, (string) $me['username'], 'inquiry', 'Deleted inquiry ' . $id);
        $notice = 'Inquiry ' . $id . ' was deleted. The contact profile was kept.';
        $viewId = 0;
        header('Location: ' . tylilo_inquiries_url($sort, $dir) . (str_contains(tylilo_inquiries_url($sort, $dir), '?') ? '&' : '?') . 'notice=deleted&deleted=' . $id, true, 303);
        exit;
    } elseif ($action === 'mark_all_read') {
        tylilo_inquiry_mark_all_read($userId);
        header('Location: ' . tylilo_inquiries_url($sort, $dir, $viewId) . (str_contains(tylilo_inquiries_url($sort, $dir, $viewId), '?') ? '&' : '?') . 'notice=allread', true, 303);
        exit;
    } elseif ($action === 'mark_unread' && $id > 0) {
        tylilo_inquiry_mark_unread($userId, $id);
        header('Location: ' . tylilo_inquiries_url($sort, $dir, $id), true, 303);
        exit;
    } elseif ($action === 'mark_read' && $id > 0) {
        tylilo_inquiry_mark_read($userId, $id);
        header('Location: ' . tylilo_inquiries_url($sort, $dir, $id), true, 303);
        exit;
    }
}

if (($_GET['notice'] ?? '') === 'deleted') {
    $deleted = (int) ($_GET['deleted'] ?? 0);
    $notice = $deleted > 0
        ? 'Inquiry ' . $deleted . ' was deleted. The contact profile was kept.'
        : 'The inquiry was deleted. The contact profile was kept.';
} elseif (($_GET['notice'] ?? '') === 'allread') {
    $notice = 'All inquiries are marked as read.';
}

try {
    $rows = tylilo_inquiry_all();
} catch (Throwable $e) {
    $rows = [];
    $error = 'Inquiries could not be read right now.';
}

if ($viewId > 0) {
    $found = false;
    foreach ($rows as $row) {
        if ((int) ($row['id'] ?? 0) === $viewId) {
            $found = true;
            break;
        }
    }
    if ($found) {
        tylilo_inquiry_mark_read($userId, $viewId);
    } else {
        $viewId = 0;
    }
}

$rows = tylilo_inquiry_sorted($rows, $sort, $dir, $userId);
$unreadCount = tylilo_inquiry_unread_count($userId, $rows);
$readMap = tylilo_inquiry_reads_load()['users'][(string) $userId] ?? [];
$readMap = is_array($readMap) ? $readMap : [];

$when = static function (int $ts): string {
    $dt = new DateTimeImmutable('@' . $ts);
    $dt = $dt->setTimezone(new DateTimeZone('America/New_York'));

    return $dt->format('M j, Y, g:i A') . ' ET';
};
$mailLabel = static fn (string $s): string => match ($s) {
    'sent' => 'sent',
    'failed' => 'failed',
    default => 'not sent',
};

$sortLabels = [
    'received' => 'Received',
    'name' => 'Name',
    'company' => 'Company',
    'email' => 'Email',
    'stage' => 'Stage',
    'fit' => 'Suggested fit',
    'status' => 'Status',
];
$sortCaption = match ($sort) {
    'received' => $dir === 'desc' ? 'sorted by date received, newest first' : 'sorted by date received, oldest first',
    'status' => $dir === 'desc' ? 'sorted by status, unread first' : 'sorted by status, read first',
    default => 'sorted by ' . strtolower($sortLabels[$sort]) . ', ' . ($dir === 'asc' ? 'A to Z' : 'Z to A'),
};

$sortHeader = static function (string $column, string $label) use ($sort, $dir): string {
    $active = $column === $sort;
    $nextDir = $active ? ($dir === 'asc' ? 'desc' : 'asc') : tylilo_inquiry_sort_default_dir($column);
    $ariaSort = 'none';
    $arrow = '';
    $hint = 'Sort by ' . $label;
    if ($active) {
        $ariaSort = $dir === 'asc' ? 'ascending' : 'descending';
        $arrow = '<span class="inq-sort-arrow" aria-hidden="true">' . ($dir === 'asc' ? '↑' : '↓') . '</span>';
        if ($column === 'received') {
            $current = $dir === 'desc' ? 'currently newest first' : 'currently oldest first';
            $next = $nextDir === 'desc' ? 'Sort newest first' : 'Sort oldest first';
        } elseif ($column === 'status') {
            $current = $dir === 'desc' ? 'currently unread first' : 'currently read first';
            $next = $nextDir === 'desc' ? 'Sort unread first' : 'Sort read first';
        } else {
            $current = $dir === 'asc' ? 'currently A to Z' : 'currently Z to A';
            $next = $nextDir === 'asc' ? 'Sort A to Z' : 'Sort Z to A';
        }
        $hint = $label . ', ' . $current . '. ' . $next . '.';
    }
    $html = '<th scope="col" aria-sort="' . $ariaSort . '">';
    $html .= '<form method="get" action="/admin/inquiries.php">';
    $html .= '<input type="hidden" name="sort" value="' . tylilo_e($column) . '">';
    $html .= '<input type="hidden" name="dir" value="' . tylilo_e($nextDir) . '">';
    $html .= '<button type="submit" class="inq-sort-btn" aria-label="' . tylilo_e($hint) . '">' . tylilo_e($label) . $arrow . '</button>';
    $html .= '</form></th>';

    return $html;
};

$html = '';
if ($notice !== '') {
    $html .= '<div class="form-status success" role="status"><p>' . tylilo_e($notice) . '</p></div>';
}
if ($error !== '') {
    $html .= '<div class="form-status error" role="alert"><p>' . tylilo_e($error) . '</p></div>';
}
$html .= '<div class="card inq-admin-intro"><p>Consulting inquiries from the website. The suggested fit is private and is never shown to the person who wrote in. Deleting an inquiry does not delete the contact profile.</p>';
$html .= '<p class="muted">' . count($rows) . ' ' . (count($rows) === 1 ? 'inquiry' : 'inquiries');
if ($unreadCount > 0) {
    $html .= ' · ' . ($unreadCount === 1 ? '1 unread' : $unreadCount . ' unread');
}
$html .= ' · ' . tylilo_e($sortCaption) . '.</p>';
if ($unreadCount > 0) {
    $html .= '<form method="post" action="' . tylilo_e(tylilo_inquiries_url($sort, $dir, $viewId)) . '" class="inq-admin-actions">';
    $html .= '<input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '">';
    $html .= '<input type="hidden" name="action" value="mark_all_read">';
    $html .= '<button type="submit">Mark all as read</button></form>';
}
$html .= '</div>';

if ($rows === []) {
    $html .= '<div class="card"><p>No inquiries yet.</p></div>';
} else {
    $html .= '<div class="card contact-table-wrap"><table class="contact-table inq-admin-table">';
    $html .= '<caption class="visually-hidden">Inquiries, ' . tylilo_e($sortCaption) . '</caption><thead><tr>';
    $html .= $sortHeader('status', 'Status');
    $html .= $sortHeader('name', 'Name');
    $html .= $sortHeader('company', 'Company');
    $html .= $sortHeader('email', 'Email');
    $html .= $sortHeader('stage', 'Stage');
    $html .= $sortHeader('fit', 'Suggested fit');
    $html .= $sortHeader('received', 'Received');
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $fields = tylilo_inquiry_row_fields($row);
        $id = $fields['id'];
        $unread = tylilo_inquiry_is_unread($userId, $id, $readMap) && $id !== $viewId;
        $name = $fields['name'] !== '' ? $fields['name'] : 'No name';
        $href = tylilo_inquiries_url($sort, $dir, $id);
        $html .= '<tr class="' . ($unread ? 'inq-unread' : 'inq-read') . '" data-inquiry-id="' . $id . '">';
        $html .= '<td>';
        if ($unread) {
            $html .= '<span class="inq-new-tag">New</span>';
        } else {
            $html .= 'Read';
        }
        $html .= '</td>';
        $html .= '<th scope="row"><a href="' . tylilo_e($href) . '">' . tylilo_e($name) . '</a></th>';
        $html .= '<td>' . tylilo_e($fields['company']) . '</td>';
        $html .= '<td>' . tylilo_e($fields['email']) . '</td>';
        $html .= '<td>' . tylilo_e($fields['stage']) . '</td>';
        $html .= '<td>' . tylilo_e($fields['fit']) . '</td>';
        $html .= '<td>' . tylilo_e($when($fields['received'])) . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table></div>';
}

$open = null;
if ($viewId > 0) {
    foreach ($rows as $row) {
        if ((int) ($row['id'] ?? 0) === $viewId) {
            $open = $row;
            break;
        }
    }
}

if (is_array($open)) {
    $id = (int) ($open['id'] ?? 0);
    $a = is_array($open['answers'] ?? null) ? $open['answers'] : [];
    $name = (string) ($a['name'] ?? '');
    $business = (string) ($a['business'] ?? '');
    $email = (string) ($a['email'] ?? '');
    $fit = (string) ($open['fit'] ?? '');
    $reasons = is_array($open['fit_reasons'] ?? null) ? $open['fit_reasons'] : [];
    $isUnread = tylilo_inquiry_is_unread($userId, $id);
    $html .= '<article class="card inq-admin-card" id="inquiry-' . $id . '">';
    $html .= '<h2>#' . $id . ' ' . tylilo_e($name !== '' ? $name : 'No name') . ($business !== '' ? ' <span class="muted">· ' . tylilo_e($business) . '</span>' : '') . '</h2>';
    $html .= '<p class="user-status">' . tylilo_e($when((int) ($open['created_at'] ?? 0))) . ' · Suggested fit: ' . tylilo_e($fit) . (!empty($open['preview']) ? ' · preview' : '') . ($isUnread ? ' · New' : '') . '</p>';
    $html .= '<p><strong>Email:</strong> <a href="mailto:' . tylilo_e($email) . '">' . tylilo_e($email) . '</a></p>';
    if ($canContacts && $email !== '') {
        $html .= '<p><a href="/admin/contacts.php?c=' . tylilo_e(tylilo_contact_id_for_email($email)) . '">View this person&rsquo;s profile</a></p>';
    }
    $html .= '<div class="inq-admin-fit"><p><strong>Suggested fit (private):</strong> ' . tylilo_e($fit) . '</p>';
    if ($reasons !== []) {
        $html .= '<ul>';
        foreach ($reasons as $reason) {
            $html .= '<li>' . tylilo_e((string) $reason) . '</li>';
        }
        $html .= '</ul>';
    }
    $html .= '</div>';
    $html .= '<dl class="inq-admin-answers">';
    foreach (tylilo_inquiry_summary($a) as [$label, $value]) {
        $html .= '<dt>' . tylilo_e($label) . '</dt><dd>' . nl2br(tylilo_e($value)) . '</dd>';
    }
    $html .= '</dl>';
    $html .= '<p class="muted">Copy to them: ' . $mailLabel((string) ($open['mail_visitor'] ?? '')) . ' · Notice to Ryan: ' . $mailLabel((string) ($open['mail_owner'] ?? '')) . '</p>';
    $html .= '<div class="inq-admin-actions">';
    $html .= '<form method="post" action="' . tylilo_e(tylilo_inquiries_url($sort, $dir, $id)) . '">';
    $html .= '<input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '">';
    $html .= '<input type="hidden" name="id" value="' . $id . '">';
    if ($isUnread) {
        $html .= '<input type="hidden" name="action" value="mark_read"><button type="submit">Mark as read</button>';
    } else {
        $html .= '<input type="hidden" name="action" value="mark_unread"><button type="submit">Mark as unread</button>';
    }
    $html .= '</form>';
    $html .= '<form class="inq-admin-delete" method="post" action="' . tylilo_e(tylilo_inquiries_url($sort, $dir)) . '">'
        . '<input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '">'
        . '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . $id . '">'
        . '<label for="inq-del-' . $id . '"><input type="checkbox" id="inq-del-' . $id . '" name="confirm" value="yes"> Yes, delete inquiry ' . $id . ' permanently. This does not delete the contact profile.</label>'
        . '<button type="submit">Delete<span class="visually-hidden"> inquiry ' . $id . '</span></button></form>';
    $html .= '</div></article>';
}

tylilo_admin_layout('Inquiries', $html);
