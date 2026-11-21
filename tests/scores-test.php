<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$dir = sys_get_temp_dir() . '/tylilo-scores-' . getmypid();
putenv('TYLILO_DATA_DIR=' . $dir);
$_SERVER['DOCUMENT_ROOT'] = $root;
require $root . '/includes/scores.php';

$failed = 0;
$checks = 0;

function check(bool $ok, string $label): void
{
    global $failed, $checks;
    $checks++;
    if ($ok) {
        echo "ok  {$label}\n";
        return;
    }
    $failed++;
    echo "not ok  {$label}\n";
}

$server = ['HTTP_HOST' => 'tylilo.test', 'HTTP_ORIGIN' => 'https://tylilo.test', 'REMOTE_ADDR' => '203.0.113.10'];
$now = 1_700_000_000;

$start = tylilo_score_handle('POST', [], json_encode(['op' => 'start', 'game' => 'solitaire', 'board' => 'draw-1']), $server, $now);
check($start['ok'] === true && is_string($start['token'] ?? null), 'start issues a token');
$token = (string) $start['token'];

$saved = tylilo_score_handle('POST', [], json_encode([
    'op' => 'submit', 'game' => 'solitaire', 'board' => 'draw-1',
    'name' => 'Ada', 'score' => 1500, 'playedMs' => 45000, 'token' => $token,
]), $server, $now + 45);
check(($saved['ok'] ?? false) === true && ($saved['rank'] ?? 0) === 1, 'submit stores a plausible score');

$again = tylilo_score_handle('POST', [], json_encode([
    'op' => 'submit', 'game' => 'solitaire', 'board' => 'draw-1',
    'name' => 'Ada', 'score' => 1600, 'playedMs' => 45000, 'token' => $token,
]), $server, $now + 90);
check(($again['ok'] ?? true) === false, 'a token cannot be reused');

foreach (['Scunthorpe', 'class', 'assassin', 'glasses', "Ann-Marie"] as $allowed) {
    $clean = tylilo_score_clean_name($allowed);
    check($clean['ok'] === true, "allows {$allowed}");
}

foreach (['f4ck', 'sh1t', 'a$$', 'a55', 'f.u.c.k', 'f u c k', 'fuuuuck', '@ss', '5hit', 'b1tch'] as $blocked) {
    $clean = tylilo_score_clean_name($blocked);
    check($clean['ok'] === false, "blocks {$blocked}");
}

check(tylilo_score_clean_name('')['ok'] === false, 'blocks an empty name');
check(tylilo_score_clean_name('This name is long')['ok'] === false, 'blocks a name over 12 characters');
check(tylilo_score_clean_name('Bad_name')['ok'] === false, 'blocks punctuation outside the allowed set');

$tooSoon = tylilo_score_handle('POST', [], json_encode(['op' => 'start', 'game' => 'solitaire', 'board' => 'draw-1']), $server, $now);
$early = tylilo_score_handle('POST', [], json_encode([
    'op' => 'submit', 'game' => 'solitaire', 'board' => 'draw-1',
    'name' => 'Bea', 'score' => 10, 'playedMs' => 1000, 'token' => $tooSoon['token'],
]), $server, $now + 2);
check(($early['ok'] ?? true) === false, 'rejects a score before the minimum play time');

$huge = tylilo_score_handle('POST', [], json_encode(['op' => 'start', 'game' => 'solitaire', 'board' => 'draw-1']), $server, $now);
$over = tylilo_score_handle('POST', [], json_encode([
    'op' => 'submit', 'game' => 'solitaire', 'board' => 'draw-1',
    'name' => 'Bea', 'score' => 999999, 'playedMs' => 30000, 'token' => $huge['token'],
]), $server, $now + 30);
check(($over['ok'] ?? true) === false, 'rejects a score above the board maximum');

$foreign = $server;
$foreign['HTTP_ORIGIN'] = 'https://evil.test';
unset($foreign['HTTP_REFERER']);
$cross = tylilo_score_handle('POST', [], json_encode(['op' => 'start', 'game' => 'solitaire', 'board' => 'draw-1']), $foreign, $now);
check(($cross['status'] ?? 0) === 403, 'rejects a cross-site post');

$ips = $server;
for ($i = 0; $i < 5; $i++) {
    $begun = tylilo_score_handle('POST', [], json_encode(['op' => 'start', 'game' => 'pop-spiral', 'board' => 'default']), $ips, $now);
    tylilo_score_handle('POST', [], json_encode([
        'op' => 'submit', 'game' => 'pop-spiral', 'board' => 'default',
        'name' => 'P' . $i, 'score' => 100 + $i, 'playedMs' => 30000, 'token' => $begun['token'],
    ]), $ips, $now + 30 + $i);
}
$sixth = tylilo_score_handle('POST', [], json_encode(['op' => 'start', 'game' => 'pop-spiral', 'board' => 'default']), $ips, $now + 40);
$limited = tylilo_score_handle('POST', [], json_encode([
    'op' => 'submit', 'game' => 'pop-spiral', 'board' => 'default',
    'name' => 'Late', 'score' => 50, 'playedMs' => 30000, 'token' => $sixth['token'],
]), $ips, $now + 70);
check(($limited['status'] ?? 0) === 429, 'rate limit blocks the sixth score from one network');

$orderServer = $server;
$orderServer['REMOTE_ADDR'] = '203.0.113.20';
$high = tylilo_score_handle('POST', [], json_encode(['op' => 'start', 'game' => 'red-rain', 'board' => 'easy']), $orderServer, $now);
tylilo_score_handle('POST', [], json_encode([
    'op' => 'submit', 'game' => 'red-rain', 'board' => 'easy',
    'name' => 'Low', 'score' => 10, 'playedMs' => 40000, 'token' => $high['token'],
]), $orderServer, $now + 40);
$high2 = tylilo_score_handle('POST', [], json_encode(['op' => 'start', 'game' => 'red-rain', 'board' => 'easy']), $orderServer, $now + 41);
tylilo_score_handle('POST', [], json_encode([
    'op' => 'submit', 'game' => 'red-rain', 'board' => 'easy',
    'name' => 'High', 'score' => 90, 'playedMs' => 40000, 'token' => $high2['token'],
]), $orderServer, $now + 80);
$list = tylilo_score_handle('GET', ['game' => 'red-rain', 'board' => 'easy', 'limit' => '10'], '', [], $now + 90);
check(($list['boards'][0]['scores'][0]['name'] ?? '') === 'High', 'higher scores rank first');

$timeServer = $server;
$timeServer['REMOTE_ADDR'] = '203.0.113.30';
$slow = tylilo_score_handle('POST', [], json_encode(['op' => 'start', 'game' => 'jungle-rescue', 'board' => 'time']), $timeServer, $now);
tylilo_score_handle('POST', [], json_encode([
    'op' => 'submit', 'game' => 'jungle-rescue', 'board' => 'time',
    'name' => 'Slow', 'score' => 90, 'playedMs' => 90000, 'token' => $slow['token'],
]), $timeServer, $now + 90);
$fast = tylilo_score_handle('POST', [], json_encode(['op' => 'start', 'game' => 'jungle-rescue', 'board' => 'time']), $timeServer, $now + 91);
tylilo_score_handle('POST', [], json_encode([
    'op' => 'submit', 'game' => 'jungle-rescue', 'board' => 'time',
    'name' => 'Fast', 'score' => 30, 'playedMs' => 30000, 'token' => $fast['token'],
]), $timeServer, $now + 130);
$times = tylilo_score_handle('GET', ['game' => 'jungle-rescue', 'board' => 'time'], '', [], $now + 140);
check(($times['boards'][0]['scores'][0]['name'] ?? '') === 'Fast', 'lower times rank first');
check(($times['boards'][0]['order'] ?? '') === 'asc', 'time boards are ascending');

$logan = tylilo_score_handle('GET', ['game' => 'jungle-rescue', 'board' => 'default', 'limit' => '10'], '', [], $now);
$loganScores = array_map(static fn (array $row): int => (int) ($row['score'] ?? 0), $logan['boards'][0]['scores'] ?? []);
check(count(array_filter($loganScores, static fn (int $score): bool => $score === 187100)) === 1, 'seeds Logan 187100 on jungle-rescue once');
$loganAgain = tylilo_score_handle('GET', ['game' => 'jungle-rescue', 'board' => 'default', 'limit' => '10'], '', [], $now);
$loganAgainScores = array_map(static fn (array $row): int => (int) ($row['score'] ?? 0), $loganAgain['boards'][0]['scores'] ?? []);
check(count(array_filter($loganAgainScores, static fn (int $score): bool => $score === 187100)) === 1, 'reading the board does not seed Logan twice');

$bigServer = $server;
$bigServer['REMOTE_ADDR'] = '203.0.113.40';
$bigStart = tylilo_score_handle('POST', [], json_encode(['op' => 'start', 'game' => 'jungle-rescue', 'board' => 'default']), $bigServer, $now);
$bigSave = tylilo_score_handle('POST', [], json_encode([
    'op' => 'submit', 'game' => 'jungle-rescue', 'board' => 'default',
    'name' => 'LOGAN', 'score' => 187100, 'playedMs' => 120000, 'token' => $bigStart['token'],
]), $bigServer, $now + 120);
check(($bigSave['ok'] ?? false) === true, 'a two-minute run of 187100 is accepted');

$missing = tylilo_score_handle('GET', ['game' => 'not-a-game'], '', [], $now);
check(($missing['status'] ?? 0) === 404, 'unknown games are rejected');

$public = json_encode($list);
check(is_string($public) && !str_contains($public, 'hash') && !str_contains($public, '203.0.113'), 'public scores omit network data');

$both = tylilo_score_handle('GET', ['game' => 'solitaire'], '', [], $now);
$boardIds = array_map(static fn (array $row): string => (string) ($row['board'] ?? ''), $both['boards'] ?? []);
check($boardIds === ['draw-1', 'draw-3'], 'omitting board returns every solitaire board');

echo $failed === 0 ? "\n{$checks} checks passed\n" : "\n{$failed} failed\n";
exit($failed === 0 ? 0 : 1);
