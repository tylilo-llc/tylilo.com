<?php

declare(strict_types=1);

define('TYLILO_BOOTSTRAPPED', true);

$root = dirname(__DIR__);
$dir = sys_get_temp_dir() . '/tylilo-turnstile-log-' . getmypid();
putenv('TYLILO_DATA_DIR=' . $dir);
putenv('TURNSTILE_SECRET=2x0000000000000000000000000000000AA');
$_SERVER['DOCUMENT_ROOT'] = $dir . '/public_html';
$_SERVER['HOME'] = $dir . '/home';

require $root . '/includes/turnstile.php';

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

$line = tylilo_turnstile_failure_line([
    'success' => false,
    'error-codes' => ['invalid-input-secret'],
    'hostname' => 'tylilo.com',
    'action' => 'contact',
    'token' => 'super-secret-token',
], 'siteverify');
check(str_contains($line, 'error-codes=invalid-input-secret'), 'the log line names the error code');
check(str_contains($line, 'hostname=tylilo.com') && str_contains($line, 'action=contact'), 'the log line names hostname and action');
check(!str_contains($line, 'super-secret-token'), 'the log line omits the token');

$canary = 'CANARY-TOKEN-VALUE';
$empty = tylilo_turnstile_verify('', '127.0.0.1', 'contact');
$bad = tylilo_turnstile_verify($canary, '127.0.0.1', 'contact');
check($empty['ok'] === false && $bad['ok'] === false, 'empty and rejected tokens still fail closed');

$log = file_get_contents($dir . '/turnstile.log');
$log = is_string($log) ? $log : '';
check(str_contains($log, 'error-codes=missing-input-response'), 'an empty token is logged as missing-input-response');
check(str_contains($log, 'error-codes=invalid-input-response'), 'a rejected token is logged as invalid-input-response');
check(!str_contains($log, $canary) && !str_contains($log, '2x0000000000000000000000000000000AA'), 'the log file contains neither the token nor the secret');

echo $failed === 0 ? "\n{$checks} checks passed\n" : "\n{$failed} failed\n";
exit($failed === 0 ? 0 : 1);
