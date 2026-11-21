<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$dir = sys_get_temp_dir() . '/tylilo-ideas-' . getmypid();
putenv('TYLILO_DATA_DIR=' . $dir);
putenv('TYLILO_ADMIN_STORE=json');
$_SERVER['SCRIPT_FILENAME'] = $root . '/tests/ideas-board-test.php';
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

$migrated = tylilo_admin_migrate([
    'ideas' => [[
        'id' => 4,
        'user_id' => 2,
        'title' => 'Old harbor',
        'pitch' => 'Boats',
        'notes' => 'Keep this',
        'image' => '',
        'status' => 'proposed',
        'created_at' => 10,
    ]],
]);
$old = $migrated['ideas'][0];
check($old['status'] === 'idea' && $old['genre'] === '' && $old['priority'] === '' && $old['votes'] === [] && $old['notes'] === 'Keep this', 'an older idea keeps its words and starts with blank new fields');

$_POST = [
    'title' => 'Harbor Match',
    'pitch' => 'Boats come into port.',
    'genre' => 'puzzle',
    'style' => 'pixel',
    'controls' => ['keyboard', 'mouse'],
    'devices' => ['desktop'],
    'status' => 'approved',
    'priority' => 'high',
    'leaderboard' => '1',
    'scoring' => 'score',
];
$member = tylilo_idea_from_post(false, ['status' => 'idea', 'user_id' => 3]);
check($member['status'] === 'idea' && $member['priority'] === '' && $member['genre'] === 'puzzle' && $member['leaderboard'] === 1, 'a team member cannot set Approved or a priority');
$locked = tylilo_idea_from_post(false, ['status' => 'approved', 'priority' => 'high', 'user_id' => 3]);
check($locked['status'] === 'approved' && $locked['priority'] === 'high', 'a team member cannot clear an owner status');
$owner = tylilo_idea_from_post(true, ['status' => 'idea', 'user_id' => 1]);
check($owner['status'] === 'approved' && $owner['priority'] === 'high' && $owner['controls'] === ['keyboard', 'mouse'], 'the owner can set Approved and a priority');

$votes = tylilo_idea_toggle_vote([], 7);
$votes = tylilo_idea_toggle_vote($votes, 7);
$votes = tylilo_idea_toggle_vote($votes, 8);
check($votes === [8], 'each person has one vote and can take it back');

$image = imagecreatetruecolor(12, 8);
imagefilledrectangle($image, 0, 0, 11, 7, imagecolorallocate($image, 20, 40, 80));
$plain = $dir . '/plain.jpg';
if (!is_dir($dir)) {
    mkdir($dir, 0700, true);
}
imagejpeg($image, $plain, 90);
$raw = (string) file_get_contents($plain);
$marker = 'EXIF-SECRET-MARKER';
$segment = "\xFF\xE1" . pack('n', strlen($marker) + 2) . $marker;
file_put_contents($plain, substr($raw, 0, 2) . $segment . substr($raw, 2));
$clean = $dir . '/clean.jpg';
check(str_contains((string) file_get_contents($plain), $marker), 'the test picture starts with a metadata marker');
check(tylilo_admin_idea_image_write($plain, $clean, 'jpg') && !str_contains((string) file_get_contents($clean), $marker), 'saving a sketch drops the metadata marker');

echo $failed === 0 ? "\n{$checks} checks passed\n" : "\n{$failed} failed\n";
exit($failed === 0 ? 0 : 1);
