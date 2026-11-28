<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/playtest-boot.php';

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method !== 'GET' && $method !== 'HEAD') {
    tylilo_playtest_json(405, ['ok' => false, 'error' => 'method', 'message' => 'Use GET.'], ['Allow: GET, HEAD']);
}
$refusal = tylilo_playtest_feed_refusal($_SERVER, tylilo_playtest_read_secret());
if ($refusal !== null) {
    tylilo_playtest_json($refusal[0], $refusal[1], $refusal[0] === 401 ? ['WWW-Authenticate: Bearer realm="playtest"'] : []);
}
$id = is_string($_GET['id'] ?? null) ? $_GET['id'] : '';
$found = tylilo_playtest_find($id);
if ($found === null) {
    tylilo_playtest_json(404, ['ok' => false, 'error' => 'not_found', 'message' => 'No such report.']);
}
tylilo_playtest_stream($found['video'], (string) $found['meta']['type'], 'playtest-' . $id . '.' . $found['meta']['ext']);
