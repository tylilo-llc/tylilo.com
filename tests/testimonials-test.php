<?php

declare(strict_types=1);

$_SERVER['SCRIPT_FILENAME'] = __FILE__;
$_SERVER['REQUEST_URI'] = '/';
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/testimonials.php';

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

$live = tylilo_testimonials();
check($live === [], 'the homepage testimonial list starts empty');

$empty = tylilo_testimonials_html([]);
check(str_contains($empty, 'No testimonials yet.'), 'an empty list says there are no testimonials yet');
check(!str_contains($empty, '<blockquote'), 'an empty list does not render a quotation');

$blank = tylilo_testimonials_html([
    ['quote' => '   ', 'name' => 'Unused'],
    ['quote' => 'A sentence.', 'name' => ''],
]);
check($blank === $empty, 'a quote or a name that is blank is not shown');

$one = tylilo_testimonials_html([
    ['quote' => 'A sentence for the test only.', 'name' => 'Test Person', 'role' => 'Test role'],
]);
check(str_contains($one, '<blockquote>'), 'a complete entry renders a quotation');
check(str_contains($one, 'A sentence for the test only.'), 'a complete entry includes the quote');
check(str_contains($one, 'Test Person'), 'a complete entry includes the name');
check(str_contains($one, 'Test role'), 'a complete entry includes the role');
check(!str_contains($one, 'No testimonials yet.'), 'a complete entry replaces the empty state');

echo $failed === 0 ? "\n{$checks} checks passed\n" : "\n{$failed} failed\n";
exit($failed === 0 ? 0 : 1);
