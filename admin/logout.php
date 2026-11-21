<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !tylilo_admin_csrf_ok(tylilo_admin_field('csrf'))) {
    http_response_code(400);
    tylilo_admin_layout('Log out', '<div class="card"><p>That log-out request was not accepted.</p></div>');
    exit;
}

tylilo_session_start();
$_SESSION = [];
session_regenerate_id(true);
header('Location: /admin/', true, 303);
exit;
