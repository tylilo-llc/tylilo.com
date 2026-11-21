<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/scores.php';

$stream = fopen('php://input', 'rb');
$body = $stream === false ? '' : (stream_get_contents($stream, TYLILO_SCORE_BODY_MAX + 1) ?: '');
if ($stream !== false) {
    fclose($stream);
}

try {
    $result = tylilo_score_handle((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), $_GET, $body, $_SERVER);
} catch (Throwable $error) {
    $result = ['ok' => false, 'status' => 500, 'error' => 'Scores are not available right now.'];
}

$status = (int) ($result['status'] ?? 200);
unset($result['status']);

http_response_code($status);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex');
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
