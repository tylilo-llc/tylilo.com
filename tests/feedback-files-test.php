<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$dir = sys_get_temp_dir() . '/tylilo-feedback-files-' . getmypid();
putenv('TYLILO_DATA_DIR=' . $dir);
putenv('TYLILO_ADMIN_STORE=json');
$_SERVER['SCRIPT_FILENAME'] = $root . '/tests/feedback-files-test.php';
$_SERVER['DOCUMENT_ROOT'] = $root;
require $root . '/includes/bootstrap.php';
require $root . '/includes/admin.php';

$failed = 0;
$checks = 0;

function check(bool $ok, string $label): void
{
    global $failed, $checks;
    $checks++;
    echo ($ok ? 'ok  ' : 'not ok  ') . $label . "\n";
    if (!$ok) {
        $failed++;
    }
}

if (!is_dir($dir)) {
    mkdir($dir, 0700, true);
}

$png = $dir . '/sample.png';
$image = imagecreatetruecolor(20, 16);
imagefilledrectangle($image, 0, 0, 19, 15, imagecolorallocate($image, 10, 49, 97));
imagepng($image, $png);
imagedestroy($image);

$jpg = $dir . '/sample.jpg';
$image = imagecreatetruecolor(12, 12);
imagejpeg($image, $jpg, 90);
imagedestroy($image);

$txt = $dir . '/notes.txt';
file_put_contents($txt, "Radio dropped at 1:12\n");

$json = $dir . '/state.json';
file_put_contents($json, '{"ok":true,"level":3}');

$php = $dir . '/evil.php.txt';
file_put_contents($php, "<?php echo 1;");

check(tylilo_feedback_identify($png, 'shot.png')['ext'] === 'png', 'a PNG is identified by its contents');
check(tylilo_feedback_identify($jpg, 'shot.jpg')['ext'] === 'jpg', 'a JPEG is identified by its contents');
check(tylilo_feedback_identify($txt, 'notes.txt')['ext'] === 'txt', 'a text file is accepted');
check(tylilo_feedback_identify($json, 'state.json')['ext'] === 'json', 'a JSON file is accepted');
check(tylilo_feedback_identify($png, 'shot.exe') === null, 'an allowed picture with a disallowed extension is rejected');
check(tylilo_feedback_identify($php, 'notes.txt') === null, 'a PHP file pretending to be text is rejected');
$fakePng = $dir . '/fake.png';
file_put_contents($fakePng, 'not an image');
check(tylilo_feedback_identify($fakePng, 'fake.png') === null, 'a text file with a .png name is rejected');

$saved = tylilo_feedback_store_uploads([
    ['name' => 'shot.png', 'tmp_name' => $png, 'error' => UPLOAD_ERR_OK, 'size' => filesize($png), 'require_upload' => false],
    ['name' => 'notes.txt', 'tmp_name' => $txt, 'error' => UPLOAD_ERR_OK, 'size' => filesize($txt), 'require_upload' => false],
]);
check($saved['ok'] === true && count($saved['files']) === 2, 'two allowed files are stored');
check(preg_match('/^[a-f0-9]{32}\.png$/', $saved['files'][0]['stored']) === 1, 'stored names are random and do not use the original filename');
check($saved['files'][0]['name'] === 'shot.png', 'the original filename is kept for display');
$storedPath = tylilo_feedback_file_path($saved['files'][0]['stored']);
check(is_string($storedPath) && is_file($storedPath), 'a stored file can be resolved inside the private directory');
check(tylilo_feedback_file_path('../' . $saved['files'][0]['stored']) === null, 'path traversal in a stored name is rejected');

$tooMany = [];
for ($i = 0; $i < 6; $i++) {
    $tooMany[] = ['name' => 'shot.png', 'tmp_name' => $png, 'error' => UPLOAD_ERR_OK, 'size' => filesize($png), 'require_upload' => false];
}
$rejected = tylilo_feedback_store_uploads($tooMany);
check($rejected['ok'] === false && str_contains($rejected['error'], '5'), 'a sixth file is rejected');

$owner = ['id' => 1, 'role' => 'owner'];
$member = ['id' => 2, 'role' => 'member'];
$item = ['id' => 9, 'user_id' => 2, 'attachments' => $saved['files']];
check(tylilo_feedback_can_view($owner, $item) && tylilo_feedback_can_view($member, $item), 'the owner and the author can view attachments');
check(!tylilo_feedback_can_view(['id' => 3, 'role' => 'member'], $item), 'another teammate cannot view someone else’s attachments');

$html = tylilo_feedback_attachments_html($item);
check(str_contains($html, 'shot.png') && str_contains($html, 'thumb=1') && str_contains($html, tylilo_e($saved['files'][0]['stored'])), 'the inbox lists a thumbnail and a safe file link');

$dirListing = tylilo_feedback_files_dir();
check(is_file($dirListing . '/.htaccess') && str_contains((string) file_get_contents($dirListing . '/.htaccess'), 'Require all denied'), 'the storage folder includes a deny-all htaccess file');

tylilo_feedback_delete_files($saved['files']);
check(!is_file((string) $storedPath), 'deleting feedback removes the stored files');

$normalized = tylilo_feedback_normalize_uploads([
    'name' => ['a.png', 'b.txt'],
    'type' => ['image/png', 'text/plain'],
    'tmp_name' => ['/tmp/a', '/tmp/b'],
    'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE],
    'size' => [10, 0],
]);
check(count($normalized) === 1 && $normalized[0]['name'] === 'a.png' && $normalized[0]['require_upload'] === true, 'empty file slots are skipped when normalizing uploads');

check(tylilo_feedback_display_name('C:\\\\windows\\\\..\\\\secret.png') === 'secret.png', 'display names use only the base filename');
check(tylilo_feedback_max_bytes() <= tylilo_feedback_cap_bytes() && tylilo_feedback_max_bytes() > 0, 'the size cap never exceeds 10 MB and respects PHP limits');

echo $failed === 0 ? "\n{$checks} checks passed\n" : "\n{$failed} failed\n";
exit($failed === 0 ? 0 : 1);
