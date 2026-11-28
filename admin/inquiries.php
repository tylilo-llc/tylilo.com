<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';
require_once dirname(__DIR__) . '/includes/inquiry.php';

$me = tylilo_admin_guard();
if (!tylilo_admin_can($me, 'inquiries')) {
    tylilo_admin_forbid();
}

$notice = '';
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!tylilo_admin_csrf_ok(tylilo_admin_field('csrf'))) {
        $error = 'That request was not accepted. Please try again.';
    } elseif (tylilo_admin_field('action') === 'delete' && tylilo_admin_field('confirm') !== 'yes') {
        $error = 'Tick the box to confirm before deleting.';
    } elseif (tylilo_admin_field('action') === 'delete') {
        $id = (int) tylilo_admin_field('id');
        if ($id > 0) {
            tylilo_inquiry_delete($id);
            tylilo_admin_log((int) $me['id'], (string) $me['username'], 'inquiry', 'Deleted inquiry ' . $id);
            $notice = 'Inquiry ' . $id . ' was deleted.';
        }
    }
}

try {
    $rows = tylilo_inquiry_all();
} catch (Throwable $e) {
    $rows = [];
    $error = 'Inquiries could not be read right now.';
}

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

$html = '';
if ($notice !== '') {
    $html .= '<div class="form-status success" role="status"><p>' . tylilo_e($notice) . '</p></div>';
}
if ($error !== '') {
    $html .= '<div class="form-status error" role="alert"><p>' . tylilo_e($error) . '</p></div>';
}
$html .= '<div class="card inq-admin-intro"><p>Consulting inquiries from the website, newest first. The suggested fit is private and is never shown to the person who wrote in.</p>';
$html .= '<p class="muted">' . count($rows) . ' ' . (count($rows) === 1 ? 'inquiry' : 'inquiries') . '.</p></div>';

if ($rows === []) {
    $html .= '<div class="card"><p>No inquiries yet.</p></div>';
}

foreach ($rows as $row) {
    $id = (int) ($row['id'] ?? 0);
    $a = is_array($row['answers'] ?? null) ? $row['answers'] : [];
    $name = (string) ($a['name'] ?? '');
    $business = (string) ($a['business'] ?? '');
    $email = (string) ($a['email'] ?? '');
    $fit = (string) ($row['fit'] ?? '');
    $reasons = is_array($row['fit_reasons'] ?? null) ? $row['fit_reasons'] : [];
    $html .= '<article class="card inq-admin-card" id="inquiry-' . $id . '"><details>';
    $html .= '<summary><h2>#' . $id . ' ' . tylilo_e($name !== '' ? $name : 'No name') . ($business !== '' ? ' <span class="muted">· ' . tylilo_e($business) . '</span>' : '') . '</h2>';
    $html .= '<span class="user-status">' . tylilo_e($when((int) ($row['created_at'] ?? 0))) . ' · Suggested fit: ' . tylilo_e($fit) . (!empty($row['preview']) ? ' · preview' : '') . '</span></summary>';
    $html .= '<div class="user-card-body">';
    $html .= '<p><strong>Email:</strong> <a href="mailto:' . tylilo_e($email) . '">' . tylilo_e($email) . '</a></p>';
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
    $html .= '<p class="muted">Copy to them: ' . $mailLabel((string) ($row['mail_visitor'] ?? '')) . ' · Notice to Ryan: ' . $mailLabel((string) ($row['mail_owner'] ?? '')) . '</p>';
    $html .= '<form class="inq-admin-delete" method="post" action="/admin/inquiries.php">'
        . '<input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '">'
        . '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . $id . '">'
        . '<label for="inq-del-' . $id . '"><input type="checkbox" id="inq-del-' . $id . '" name="confirm" value="yes"> Yes, delete inquiry ' . $id . ' permanently</label>'
        . '<button type="submit">Delete<span class="visually-hidden"> inquiry ' . $id . '</span></button></form>';
    $html .= '</div></details></article>';
}

tylilo_admin_layout('Inquiries', $html);
