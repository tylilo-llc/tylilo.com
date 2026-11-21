<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

$me = tylilo_admin_guard();
if (!tylilo_admin_can($me, 'ideas')) {
    http_response_code(403);
    exit;
}
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$data = tylilo_admin_with(static fn (array $stored): array => $stored);
$name = '';
foreach ($data['ideas'] as $idea) {
    if ((int) $idea['id'] === $id) {
        $name = (string) $idea['image'];
    }
}
if ($name === '' || preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|gif)$/', $name) !== 1) {
    http_response_code(404);
    exit;
}
$path = tylilo_turnstile_dir() . '/idea-images/' . $name;
if (!is_file($path)) {
    http_response_code(404);
    exit;
}
$types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
$ext = pathinfo($name, PATHINFO_EXTENSION);
header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: private, no-store');
readfile($path);
