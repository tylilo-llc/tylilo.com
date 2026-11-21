<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

$me = tylilo_admin_guard();
if (!tylilo_admin_is_owner($me)) {
    tylilo_admin_forbid();
}
$data = tylilo_admin_with(static fn (array $stored): array => $stored);
$html = '<div class="card"><h2>Activity</h2>';
if ($data['activity'] === []) {
    $html .= '<p>Nothing has been recorded yet.</p>';
}
$html .= '<ul>';
foreach (array_reverse($data['activity']) as $row) {
    $html .= '<li>' . tylilo_e(gmdate('Y-m-d H:i', (int) $row['at'])) . ' · ' . tylilo_e((string) $row['username']) . ' · ' . tylilo_e((string) $row['action']) . ' · ' . tylilo_e((string) $row['detail']) . '</li>';
}
$html .= '</ul></div>';
tylilo_admin_layout('Activity', $html);
