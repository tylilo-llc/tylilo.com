<?php

declare(strict_types=1);

$_SERVER['SCRIPT_FILENAME'] = __FILE__;
$_SERVER['REQUEST_URI'] = '/inquiry';
$tmp = sys_get_temp_dir() . '/tylilo-inquiry-test-' . bin2hex(random_bytes(4));
mkdir($tmp, 0700, true);
putenv('TYLILO_DATA_DIR=' . $tmp);
define('TYLILO_SMTP_TEST_SINK', $tmp . '/mail.txt');
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/inquiry-form.php';

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

$base = [
    'csrf' => 'token',
    'stage' => 'stuck',
    'goals' => ['time', 'income'],
    'time_hours' => '55to70',
    'time_only_me' => 'Quotes and hiring.',
    'growth_bottleneck' => 'cash',
    'obstacle' => 'Should be dropped because a goal with its own questions was chosen.',
    'idea_what' => 'Should be dropped because the stage is not idea.',
    'size' => '2to10',
    'industry' => 'Landscaping',
    'timeline' => '3months',
    'help' => 'alongside',
    'location' => 'east_tn',
    'name' => "Test\nPerson",
    'business' => 'Test Co',
    'email' => 'ryan@tylilo.com',
    'website' => 'https://example.com',
    'heard' => 'A friend',
    'message' => "Line one\r\nLine two",
    'consent' => 'yes',
    'unknown' => 'ignored',
];

$r = tylilo_inquiry_validate($base, 'token');
check($r['errors'] === [] && !$r['spam'], 'a complete inquiry passes');
check($r['clean']['name'] === 'Test Person', 'one-line fields lose line breaks');
check($r['clean']['message'] === "Line one\nLine two", 'text areas keep line breaks, normalized');
check($r['clean']['obstacle'] === '', 'the fallback obstacle question is dropped when a branch goal is chosen');
check($r['clean']['growth_bottleneck'] === '', 'a follow-up for a goal not chosen is dropped');
check($r['clean']['idea_what'] === '', 'idea follow-ups are dropped when the stage is not an idea');
check(!array_key_exists('unknown', $r['clean']), 'unknown fields are not kept');

$r = tylilo_inquiry_validate(['csrf' => 'token'], 'token');
foreach (['stage', 'goals', 'size', 'timeline', 'help', 'location', 'name', 'email', 'consent'] as $key) {
    check(isset($r['errors'][$key]), 'a blank form reports ' . $key);
}
check(!isset($r['errors']['business']) && !isset($r['errors']['message']), 'optional fields are not required');

$r = tylilo_inquiry_validate(['goals' => ['nope', 'mission']] + $base, 'token');
check($r['clean']['goals'] === ['mission'], 'only listed goal values are kept');

$r = tylilo_inquiry_validate(['email' => 'not-an-email'] + $base, 'token');
check(isset($r['errors']['email']), 'a bad email is rejected');

$r = tylilo_inquiry_validate(['message' => str_repeat('a', 2001)] + $base, 'token');
check(isset($r['errors']['message']), 'over-long answers are rejected');

$r = tylilo_inquiry_validate($base, 'other');
check(isset($r['errors']['form']), 'a wrong CSRF token is rejected');

$r = tylilo_inquiry_validate(['ty_extra' => 'bot'] + $base, 'token');
check($r['spam'] === true, 'the honeypot marks spam');

$r = tylilo_inquiry_validate(['goals' => ['income', 'stability'], 'obstacle' => 'Cash is tight.'] + $base, 'token');
check($r['clean']['obstacle'] === 'Cash is tight.', 'the obstacle question applies when no branch goal is chosen');

$fit = tylilo_inquiry_fit(['stage' => 'idea', 'idea_tested' => 'not_yet', 'help' => 'diagnosis']);
check($fit['fit'] === 'Not a fit' && str_contains(implode(' ', $fit['reasons']), 'SCORE'), 'an untested idea is not a fit and points to SBDC or SCORE');
check(tylilo_inquiry_fit(['stage' => 'stuck', 'help' => 'diagnosis'])['fit'] === 'Assessment', 'a diagnosis maps to Assessment');
check(tylilo_inquiry_fit(['stage' => 'stuck', 'help' => 'alongside'])['fit'] === 'Improvement', 'working alongside maps to Improvement');
check(tylilo_inquiry_fit(['stage' => 'stuck', 'help' => 'advisor'])['fit'] === 'Advisory', 'an ongoing advisor maps to Advisory');
check(tylilo_inquiry_fit(['stage' => 'change', 'help' => 'unsure', 'goals' => ['exit']])['fit'] === 'Advisory', 'not sure plus a sale suggests Advisory');
check(tylilo_inquiry_fit(['stage' => 'growing', 'help' => 'unsure', 'goals' => ['team'], 'size' => '11to25', 'timeline' => 'now'])['fit'] === 'Improvement', 'not sure plus a team and capacity goals suggests Improvement');
check(tylilo_inquiry_fit(['stage' => 'first_year', 'help' => 'unsure', 'goals' => ['income'], 'size' => 'solo'])['fit'] === 'Assessment', 'not sure otherwise suggests Assessment');

$clean = tylilo_inquiry_validate($base, 'token')['clean'];
$fit = tylilo_inquiry_fit($clean);
$saved = tylilo_inquiry_save($clean, $fit, '203.0.113.9', true);
check($saved['ok'] && $saved['id'] === 1, 'an inquiry is stored with id 1');
$raw = (string) file_get_contents($tmp . '/inquiries.json');
check(!str_contains($raw, '203.0.113.9'), 'the raw network address is not stored');
check(!str_contains($raw, '"consent"'), 'the consent flag is not stored as an answer');
tylilo_inquiry_save($clean, $fit, '203.0.113.9', true);
tylilo_inquiry_save($clean, $fit, '203.0.113.9', true);
$limited = tylilo_inquiry_save($clean, $fit, '203.0.113.9', true);
check($limited['limited'] === true, 'a fourth inquiry from one address within an hour is limited');
check(tylilo_inquiry_save($clean, $fit, '198.51.100.7', true)['ok'], 'another address can still send');
check(count(tylilo_inquiry_all()) === 4, 'all stored inquiries are listed');
tylilo_inquiry_delete(2);
check(count(tylilo_inquiry_all()) === 3, 'an inquiry can be deleted');

$mail = tylilo_inquiry_send($clean, $fit, 1, true);
check($mail['visitor'] && $mail['owner'], 'both emails are handed to the mail sender');
$sent = (string) file_get_contents($tmp . '/mail.txt');
$parts = explode("RCPT ", $sent);
$visitor = $parts[1] ?? '';
$owner = $parts[2] ?? '';
check(str_starts_with($visitor, 'ryan@tylilo.com') && str_starts_with($owner, 'ryan@tylilo.com'), 'the test sends to ryan@tylilo.com only');
check(str_contains($visitor, 'From: "Ryan Thorpe, Tylilo, LLC" <consulting@tylilo.com>'), 'mail is from consulting@ with the right name');
check(str_contains($visitor, 'Reply-To: ryan@tylilo.com') && str_contains($owner, 'Reply-To: ryan@tylilo.com'), 'replies go to ryan@tylilo.com');
check(str_contains($visitor, 'Subject: [Preview] Your inquiry with Tylilo, LLC'), 'preview mail is marked in the subject');
check(str_contains($owner, 'Subject: [Preview] New inquiry #1'), 'the owner notice names the inquiry');
check(str_contains($visitor, 'multipart/alternative') && str_contains($visitor, 'text/html'), 'mail has text and HTML parts');
$visitorDecoded = quoted_printable_decode($visitor);
check(!str_contains($visitorDecoded, 'Suggested fit') && !str_contains($visitorDecoded, $fit['fit'] . "\n"), 'the visitor copy does not include the suggested fit');
check(str_contains(quoted_printable_decode($owner), 'Suggested fit (private): Improvement'), 'the owner notice includes the suggested fit');
check(str_contains($visitorDecoded, 'Quotes and hiring.'), 'the visitor copy includes their answers');
check(tylilo_inquiry_subject('X', false) === 'X', 'live mail has no preview mark');
check(str_contains($visitorDecoded, '[Preview] This is a test message from the private preview site'), 'the preview visitor copy says it is a preview test in the body');
$live = tylilo_inquiry_bodies($clean, $fit, 1, false);
check(!str_contains($live['visitor_text'], '[Preview]') && !str_contains($live['visitor_html'], '[Preview]'), 'live mail bodies have no preview note');
$before = (string) file_get_contents($tmp . '/mail.txt');
check(tylilo_smtp_check() === true && (string) file_get_contents($tmp . '/mail.txt') === $before, 'the mail login check sends nothing');
$page = (string) file_get_contents(dirname(__DIR__) . '/inquiry.php');
check(strpos($page, "preview_smtp_check") < strpos($page, 'tylilo_inquiry_validate('), 'the mail login check runs before any inquiry is stored');
check(str_contains($page, '$preview && ($_POST[\'preview_smtp_check\']'), 'the mail login check only works on the preview');
$wf = (string) file_get_contents(dirname(__DIR__) . '/.github/workflows/deploy-preview.yml');
check(str_contains($wf, 'send_test_email') && str_contains($wf, 'SEND_TEST_EMAIL}" != "true"'), 'deploys send no email unless a manual run asks for it');

$html = tylilo_inquiry_form_html([], [], 'tok', 'sitekey');
check(substr_count($html, 'class="inq-step"') === 8, 'the form has eight steps');
check(str_contains($html, TYLILO_INQUIRY_DISCLAIMER), 'the disclaimer is above Send');
check(str_contains($html, 'name="consent" value="yes" required'), 'the confirmation box is required');
check(str_contains($html, 'data-action="inquiry"'), 'Turnstile uses the inquiry action');
check(str_contains($html, 'href="/privacy#inquiries"'), 'the email field links to the privacy policy');
check(!preg_match('/\$\s?\d/', $html) && stripos($html, 'price') === false, 'the form shows no prices');
check(stripos($html, 'faceless') === false, 'the form does not use the word faceless');

$_SERVER['HTTP_HOST'] = 'tylilo.com';
check(tylilo_inquiry_selftest_ok(str_repeat('a', 40)) === false, 'the preview self-test token never works on the live host');

array_map('unlink', glob($tmp . '/*') ?: []);
@rmdir($tmp);
echo "\n{$checks} checks, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
