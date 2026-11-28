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
$js = (string) file_get_contents($root . '/assets/js/theme.js');
$head = (string) file_get_contents($root . '/includes/head.php');
$privacy = (string) file_get_contents($root . '/privacy.php');

check(substr_count($topbar, '<button') === 1, 'the header has a single theme button');
check(str_contains($topbar, 'data-theme-toggle'), 'the theme button is the cycle toggle');
check(!str_contains($topbar, 'data-theme-set'), 'the three separate theme buttons are gone');
check(!str_contains($topbar, 'role="group"'), 'the theme control is no longer a button group');
check(str_contains($topbar, 'aria-label="Theme: Auto. Click to switch to Light"'), 'the default label names Auto and the next mode, Light');
check(str_contains($topbar, 'data-theme-icon="auto"') && str_contains($topbar, 'data-theme-icon="light"') && str_contains($topbar, 'data-theme-icon="dark"'), 'the button has Auto, Light, and Dark icons');
check(str_contains($topbar, '<span class="theme-label">Auto</span>'), 'the button has a visible Auto label by default');
check(str_contains($js, 'tylilo-theme') && str_contains($head, 'tylilo-theme'), 'the existing tylilo-theme storage key is unchanged');
check(str_contains($js, '["auto", "light", "dark"]') || str_contains($js, "['auto', 'light', 'dark']"), 'clicks cycle Auto, then Light, then Dark');
check(str_contains($js, 'localStorage.getItem(KEY) || "auto"'), 'a first visit with nothing stored defaults to Auto');
check(str_contains($head, 'theme = localStorage.getItem("tylilo-theme") || "auto"'), 'the head script defaults to Auto before paint');
check(str_contains($privacy, 'tylilo-theme') && str_contains($privacy, 'Auto, Light, or Dark'), 'the privacy policy still names the theme key and its values');

echo "\n{$checks} checks, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
