<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: private, no-store');

$csrf = tylilo_admin_field('csrf');
$user = tylilo_admin_current();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !tylilo_admin_csrf_ok($csrf) || $user === null) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

$now = time();
$loginAt = $_SESSION['admin_login_at'] ?? 0;
$loginAt = is_int($loginAt) ? $loginAt : $now;
echo json_encode([
    'ok' => true,
    'idleLeft' => TYLILO_ADMIN_IDLE,
    'absoluteLeft' => max(0, TYLILO_ADMIN_ABSOLUTE - ($now - $loginAt)),
]);
