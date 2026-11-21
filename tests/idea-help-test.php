<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$_SERVER['SCRIPT_FILENAME'] = $root . '/tests/idea-help-test.php';
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

$texts = tylilo_idea_help_texts();
$keys = ['title', 'pitch', 'genre', 'style', 'inspired', 'controls', 'devices', 'session', 'players', 'difficulty', 'scoring', 'leaderboard', 'audience', 'effort', 'status', 'priority', 'notes', 'image'];
$missing = array_diff($keys, array_keys($texts));
check($missing === [], 'every idea field has help text');
$plain = true;
foreach ($texts as $text) {
    if (!str_contains($text, 'Example:')) {
        $plain = false;
    }
}
check($plain && count($texts) === count($keys), 'each help text includes a short example');

$html = tylilo_idea_info('genre', 'Genre', 'genre');
check(str_contains($html, 'type="button"') && str_contains($html, 'aria-label="About Genre"') && str_contains($html, 'aria-expanded="false"') && str_contains($html, 'The kind of game it is.'), 'the genre control is a button named About Genre');
check(!str_contains($html, 'onclick='), 'the help button has no inline handler');

echo $failed === 0 ? "\n{$checks} checks passed\n" : "\n{$failed} failed\n";
exit($failed === 0 ? 0 : 1);
