<?php

declare(strict_types=1);

$root = dirname(__DIR__);
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

$_SERVER['REQUEST_URI'] = '/privacy';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['DOCUMENT_ROOT'] = $root;
$_SERVER['HTTP_HOST'] = 'tylilo.com';
putenv('TYLILO_DATA_DIR=' . sys_get_temp_dir() . '/tylilo-privacy-' . getmypid());
ob_start();
require $root . '/privacy.php';
$html = (string) ob_get_clean();
$text = html_entity_decode(strip_tags($html), ENT_QUOTES);

check(str_contains($text, 'Effective date: November 28, 2026') && str_contains($text, 'Last updated: November 28, 2026'), 'shows an effective date and a last-updated date');
check(str_contains($html, 'id="dnt"') && str_contains($text, 'Do Not Track') && str_contains($text, 'does not change what it does'), 'says how Do Not Track signals are handled');
check(str_contains($text, 'third parties') && str_contains($text, 'behavioral tracking'), 'says third-party behavioral tracking is not allowed');
check(str_contains($html, 'id="storage"') && str_contains($text, 'no advertising or tracking cookies'), 'summarizes cookies and local storage');
check(str_contains($html, 'id="summary"') && str_contains($text, 'HostM') && str_contains($text, 'email provider') && str_contains($text, 'Cloudflare'), 'lists categories of information and who receives them');
check(str_contains($html, 'href="mailto:ryan@tylilo.com"') && str_contains($html, 'id="review"'), 'says how to review or change information, by email');
check(str_contains($text, 'we will add a note below saying what changed'), 'says how changes are announced');
check(str_contains($html, 'id="inquiries"') && str_contains($text, 'up to 3 years after our last contact') && str_contains($text, 'under 16'), 'the inquiry section covers retention and children');
check(preg_match('/CCPA|GDPR|CalOPPA|European Economic Area|California/', $text) !== 1, 'describes practices without naming laws or saying any law does not apply');
foreach (['tyliloInquiryDraft', 'tylilo-theme', 'tyliloA2hsDismissed', 'redRainLbName', 'klondike.lbName', 'popSpiral.playerName', 'jungleRescueLbQueue', 'jjHi', 'crashcars.settings.v1'] as $key) {
    check(str_contains($html, $key), 'keeps the ' . $key . ' storage text');
}
check(str_contains($text, 'does not write radio-station data') && str_contains($text, 'does not contact radio or stream services'), 'says Crash Cars does not write radio-station data or contact radio services');
check(!str_contains($html, 'crashcars.radio.v1') && !str_contains($html, 'crashcars.radio.v2'), 'does not name the old radio-station storage keys');
check(!str_contains($text, 'TuneIn') && !str_contains($html, 'radiotime.com') && !str_contains($html, 'tritondigital.com') && !str_contains($html, 'live365.com'), 'does not name radio or stream services');
echo "\n{$checks} checks, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
