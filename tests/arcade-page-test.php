<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failed = 0;
$checks = 0;

function check(bool $ok, string $message): void
{
    global $failed, $checks;
    $checks++;
    if ($ok) {
        echo "ok  {$message}\n";
    } else {
        $failed++;
        echo "not ok  {$message}\n";
    }
}

$topbar = (string) file_get_contents($root . '/includes/topbar.php');
$footer = (string) file_get_contents($root . '/includes/footer.php');
$home = (string) file_get_contents($root . '/index.php');
$arcade = (string) file_get_contents($root . '/arcade/index.php');

check(!str_contains($topbar, '/arcade/'), 'the header nav has no Arcade link');
check(!str_contains($home, 'side-note') && !str_contains($home, 'href="/arcade/"'), 'the home page body has no Arcade note or link');
check(substr_count($footer, 'href="/arcade/"') === 2 && substr_count($footer, '>UDLR Arcade</a>') === 2, 'the footer is the only public place with an Arcade link, labeled UDLR Arcade');
check(!str_contains($footer, '>Arcade</a>'), 'the footer no longer uses the shorter Arcade label');
check(str_contains($arcade, "\$pageTitle = 'UDLR Arcade — Tylilo, LLC'"), 'the arcade title names the UDLR Arcade and Tylilo, LLC');
check(str_contains($arcade, 'UDLR Arcade is a side project of browser games from Tylilo, LLC'), 'the meta description names the UDLR Arcade and Tylilo, LLC');
check(str_contains($arcade, "<h1>UDLR Arcade</h1>"), 'the arcade heading is UDLR Arcade');
check(str_contains($arcade, "'name' => 'UDLR Arcade'"), 'JSON-LD names the page UDLR Arcade');
check(str_contains($arcade, 'The UDLR Arcade is a side project I build with my sons.'), 'the arcade page has the first-person side-project line');
check(str_contains($arcade, "/assets/img/udlr-logo.webp"), 'the arcade page shows the UDLR logo');
check(str_contains($arcade, 'alt="UDLR logo"') && str_contains($arcade, 'width="280"') && str_contains($arcade, 'height="107"'), 'the UDLR logo has alt text, width, and height');
check(is_file($root . '/assets/img/udlr-logo.webp'), 'the optimized UDLR logo file is in the assets folder');

echo "\n{$checks} checks, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
