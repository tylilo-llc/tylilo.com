<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

$me = tylilo_admin_guard();
if (!tylilo_admin_can($me, 'feedback')) {
    http_response_code(403);
    exit;
}

$feedbackId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$stored = isset($_GET['file']) && is_string($_GET['file']) ? $_GET['file'] : '';
$thumb = isset($_GET['thumb']) && $_GET['thumb'] === '1';
$data = tylilo_admin_with(static fn (array $storedData): array => $storedData);
$item = null;
foreach ($data['feedback'] as $row) {
    if ((int) ($row['id'] ?? 0) === $feedbackId) {
        $item = $row;
        break;
    }
}
if (!is_array($item) || !tylilo_feedback_can_view($me, $item)) {
    http_response_code(404);
    exit;
}
$found = null;
foreach (tylilo_feedback_attachments($item) as $attachment) {
    if (hash_equals($attachment['stored'], $stored)) {
        $found = $attachment;
        break;
    }
}
$path = $found !== null ? tylilo_feedback_file_path($found['stored']) : null;
if ($found === null || $path === null) {
    http_response_code(404);
    exit;
}

$mime = $found['mime'];
$allowed = [];
foreach (tylilo_feedback_allowed_map() as $types) {
    foreach ($types as $type) {
        $allowed[$type] = true;
    }
}
if (!isset($allowed[$mime])) {
    $mime = 'application/octet-stream';
}

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$filename = $found['name'];
$dispositionName = 'filename="' . str_replace(['"', "\r", "\n"], '', $filename) . '"';
if (preg_match('/[^\x20-\x7E]/', $filename) === 1) {
    $dispositionName .= "; filename*=UTF-8''" . rawurlencode($filename);
}

if ($thumb && tylilo_feedback_is_previewable_image($found['stored'])) {
    $ext = strtolower(pathinfo($found['stored'], PATHINFO_EXTENSION));
    $loaders = [
        'jpg' => 'imagecreatefromjpeg',
        'png' => 'imagecreatefrompng',
        'webp' => 'imagecreatefromwebp',
        'gif' => 'imagecreatefromgif',
    ];
    $loader = $loaders[$ext] ?? '';
    if ($loader !== '' && function_exists($loader)) {
        $image = @$loader($path);
        if ($image !== false) {
            $width = imagesx($image);
            $height = imagesy($image);
            $max = 480;
            if ($width > 0 && $height > 0 && ($width > $max || $height > $max)) {
                $scale = $max / max($width, $height);
                $newW = max(1, (int) round($width * $scale));
                $newH = max(1, (int) round($height * $scale));
                $thumbImage = imagecreatetruecolor($newW, $newH);
                if ($ext === 'png' || $ext === 'gif' || $ext === 'webp') {
                    imagealphablending($thumbImage, false);
                    imagesavealpha($thumbImage, true);
                    $transparent = imagecolorallocatealpha($thumbImage, 0, 0, 0, 127);
                    imagefilledrectangle($thumbImage, 0, 0, $newW, $newH, $transparent);
                }
                imagecopyresampled($thumbImage, $image, 0, 0, 0, 0, $newW, $newH, $width, $height);
                imagedestroy($image);
                $image = $thumbImage;
            }
            header('Content-Type: image/jpeg');
            header('Content-Disposition: inline; ' . $dispositionName);
            imagejpeg($image, null, 80);
            imagedestroy($image);
            exit;
        }
    }
}

$inline = tylilo_feedback_is_previewable_image($found['stored']) && !$thumb;
header('Content-Type: ' . $mime);
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; ' . $dispositionName);
header('Content-Length: ' . (string) filesize($path));
readfile($path);
