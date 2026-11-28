<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/playtest-boot.php';

try {
    $user = tylilo_playtest_user();
    tylilo_session_start();
    $csrf = $_SESSION['admin_csrf'] ?? '';
    [$status, $body] = tylilo_playtest_handle_upload(
        $_SERVER,
        $_POST,
        $_FILES,
        $user,
        is_string($csrf) ? $csrf : ''
    );
} catch (Throwable $error) {
    $status = 500;
    $body = ['ok' => false, 'error' => 'unavailable', 'message' => 'Playtest reports are not available right now.'];
}
tylilo_playtest_json($status, $body, $status === 405 ? ['Allow: POST'] : []);
