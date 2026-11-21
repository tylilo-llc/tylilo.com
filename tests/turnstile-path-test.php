<?php

declare(strict_types=1);

define('TYLILO_BOOTSTRAPPED', true);

$root = dirname(__DIR__);
$base = sys_get_temp_dir() . '/tylilo-turnstile-path-' . getmypid();
putenv('TURNSTILE_SECRET');
putenv('TYLILO_DATA_DIR');
$_SERVER['DOCUMENT_ROOT'] = $base . '/public_html';
$_SERVER['HOME'] = $base . '/home';
putenv('HOME=' . $base . '/home');

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

function write_secret(string $path, string $value): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create ' . $dir);
    }
    file_put_contents($path, "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($value, true) . ";\n");
}

$doc = $base . '/public_html';
$candidates = tylilo_turnstile_secret_candidates();
check(in_array($doc . '/data/turnstile.php', $candidates, true), 'candidates include the blocked data directory');
check(in_array(dirname($doc) . '/tylilo-data/turnstile.php', $candidates, true), 'candidates include the parent of DOCUMENT_ROOT');
check(in_array($doc . '/tylilo-data/turnstile.php', $candidates, true), 'candidates include tylilo-data inside the web root');
check(in_array($base . '/home/tylilo-data/turnstile.php', $candidates, true), 'candidates include the home directory');

write_secret($doc . '/data/turnstile.php', 'from-data');
check(tylilo_turnstile_secret() === 'from-data', 'a secret only in data/ is used');

write_secret($doc . '/tylilo-data/turnstile.php', 'from-web-tylilo-data');
check(tylilo_turnstile_secret() === 'from-web-tylilo-data', 'tylilo-data inside the web root wins over data/');

write_secret(dirname($doc) . '/tylilo-data/turnstile.php', 'from-parent');
check(tylilo_turnstile_secret() === 'from-parent', 'the parent of DOCUMENT_ROOT wins over paths inside the web root');

write_secret($base . '/only-env/turnstile.php', 'from-env');
putenv('TYLILO_DATA_DIR=' . $base . '/only-env');
check(tylilo_turnstile_secret() === 'from-env', 'TYLILO_DATA_DIR wins over the other files');
putenv('TYLILO_DATA_DIR');

putenv('TURNSTILE_SECRET=from-environment');
check(tylilo_turnstile_secret() === 'from-environment', 'the environment secret wins and is not printed');
putenv('TURNSTILE_SECRET');

file_put_contents(dirname($doc) . '/tylilo-data/turnstile.php', "<?php\nreturn 42;\n");
check(tylilo_turnstile_secret() === 'from-web-tylilo-data', 'a non-string file is skipped for the next readable secret');

echo $failed === 0 ? "\n{$checks} checks passed\n" : "\n{$failed} failed\n";
exit($failed === 0 ? 0 : 1);
