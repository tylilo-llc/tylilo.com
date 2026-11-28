<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/playtest-boot.php';

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    tylilo_playtest_json(405, ['ok' => false, 'error' => 'method', 'message' => 'Use GET.'], ['Allow: GET']);
}
try {
    $user = tylilo_playtest_user();
    if ($user === null) {
        tylilo_playtest_json(403, ['ok' => false, 'error' => 'forbidden', 'message' => 'Sign in as an approved Alpha/Beta tester to send a report.']);
    }
    tylilo_playtest_json(200, [
        'csrf' => tylilo_admin_csrf(),
        'user' => (string) ($user['username'] ?? ''),
        'maxBytes' => tylilo_playtest_max_bytes(),
    ]);
} catch (RuntimeException $error) {
    tylilo_playtest_json(500, ['ok' => false, 'error' => 'unavailable', 'message' => 'Playtest reports are not available right now.']);
}
