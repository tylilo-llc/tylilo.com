<?php

declare(strict_types=1);

require dirname(__DIR__) . '/_init.php';
require_once dirname(__DIR__, 2) . '/includes/playtest.php';

$me = tylilo_admin_guard();
if (!tylilo_admin_can($me, 'beta') || !tylilo_admin_can($me, 'arcade_publish')) {
    http_response_code(403);
    header('Cache-Control: private, no-store');
    exit;
}
$found = tylilo_playtest_find(is_string($_GET['id'] ?? null) ? $_GET['id'] : '');
if ($found === null) {
    http_response_code(404);
    header('Cache-Control: private, no-store');
    exit;
}
tylilo_playtest_stream($found['video'], (string) $found['meta']['type']);
