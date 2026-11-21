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
// The LINK 1011970 seed is also on red-rain easy, so compare High with Low, not with first place.
$orderNames = array_map(static fn (array $row): string => (string) ($row['name'] ?? ''), $list['boards'][0]['scores'] ?? []);
$highAt = array_search('High', $orderNames, true);
$lowAt = array_search('Low', $orderNames, true);
check($highAt !== false && $lowAt !== false && $highAt < $lowAt, 'higher scores rank first');

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

$jungleAll = tylilo_score_handle('GET', ['game' => 'jungle-rescue', 'limit' => '25'], '', [], $now);
$jungleAllScores = [];
foreach ($jungleAll['boards'] ?? [] as $jungleBoard) {
    foreach ($jungleBoard['scores'] ?? [] as $row) {
        $jungleAllScores[] = (int) ($row['score'] ?? 0);
    }
}
check(!in_array(187100, $jungleAllScores, true), 'Logan 187100 is not seeded on a fresh store');

$bigServer = $server;
$bigServer['REMOTE_ADDR'] = '203.0.113.40';
$bigStart = tylilo_score_handle('POST', [], json_encode(['op' => 'start', 'game' => 'jungle-rescue', 'board' => 'default']), $bigServer, $now);
$bigSave = tylilo_score_handle('POST', [], json_encode([
    'op' => 'submit', 'game' => 'jungle-rescue', 'board' => 'default',
    'name' => 'MAX', 'score' => 187100, 'playedMs' => 120000, 'token' => $bigStart['token'],
]), $bigServer, $now + 120);
check(($bigSave['ok'] ?? false) === true, 'a two-minute run of 187100 is accepted');

$missing = tylilo_score_handle('GET', ['game' => 'not-a-game'], '', [], $now);
check(($missing['status'] ?? 0) === 404, 'unknown games are rejected');

$public = json_encode($list);
check(is_string($public) && !str_contains($public, 'hash') && !str_contains($public, '203.0.113'), 'public scores omit network data');

$versionServer = $server;
$versionServer['REMOTE_ADDR'] = '203.0.113.60';
$versionStart = tylilo_score_handle('POST', [], json_encode(['op' => 'start', 'game' => 'pop-spiral', 'board' => 'default']), $versionServer, $now);
$withVersion = tylilo_score_handle('POST', [], json_encode([
    'op' => 'submit', 'game' => 'pop-spiral', 'board' => 'default',
    'name' => 'Ver', 'score' => 40, 'playedMs' => 30000, 'version' => '0.2.1', 'token' => $versionStart['token'],
]), $versionServer, $now + 30);
$versionBody = json_encode($withVersion);
check(($withVersion['ok'] ?? false) === true && is_string($versionBody) && !str_contains($versionBody, '0.2.1'), 'a successful submit does not return the version');
$versionList = tylilo_score_handle('GET', ['game' => 'pop-spiral', 'board' => 'default'], '', [], $now + 31);
$versionPublic = json_encode($versionList);
check(is_string($versionPublic) && !str_contains($versionPublic, 'version') && !str_contains($versionPublic, '0.2.1'), 'public scores omit the version');
$adminRows = tylilo_score_admin_rows('pop-spiral', 'default');
$foundVersion = false;
foreach ($adminRows as $row) {
    if ($row['name'] === 'Ver' && $row['version'] === '0.2.1') {
        $foundVersion = true;
    }
}
check($foundVersion, 'the admin list keeps the version');

$plainStart = tylilo_score_handle('POST', [], json_encode(['op' => 'start', 'game' => 'pop-spiral', 'board' => 'default']), $versionServer, $now + 32);
$plain = tylilo_score_handle('POST', [], json_encode([
    'op' => 'submit', 'game' => 'pop-spiral', 'board' => 'default',
    'name' => 'Old', 'score' => 30, 'playedMs' => 30000, 'token' => $plainStart['token'],
]), $versionServer, $now + 62);
check(($plain['ok'] ?? false) === true, 'a submit without version still saves');
$oldVersion = null;
foreach (tylilo_score_admin_rows('pop-spiral', 'default') as $row) {
    if ($row['name'] === 'Old') {
        $oldVersion = $row['version'];
    }
}
check($oldVersion === null, 'a missing version is stored as null');

$badVersionStart = tylilo_score_handle('POST', [], json_encode(['op' => 'start', 'game' => 'pop-spiral', 'board' => 'default']), $versionServer, $now + 63);
$badVersion = tylilo_score_handle('POST', [], json_encode([
    'op' => 'submit', 'game' => 'pop-spiral', 'board' => 'default',
    'name' => 'Nope', 'score' => 20, 'playedMs' => 30000, 'version' => '1.1.6 build', 'token' => $badVersionStart['token'],
]), $versionServer, $now + 93);
check(($badVersion['status'] ?? 0) === 400, 'a version with a space is rejected');

$levelServer = $server;
$levelServer['REMOTE_ADDR'] = '203.0.113.71';
$levelStart = tylilo_score_handle('POST', [], json_encode(['op' => 'start', 'game' => 'pop-spiral', 'board' => 'default']), $levelServer, $now + 100);
$badLevels = [
    'a string' => '7',
    'a float' => 7.5,
    'a boolean' => true,
    'zero' => 0,
    '1000' => 1000,
    'a negative' => -1,
];
foreach ($badLevels as $label => $badLevel) {
    $rejected = tylilo_score_handle('POST', [], json_encode([
        'op' => 'submit', 'game' => 'pop-spiral', 'board' => 'default',
        'name' => 'Nope', 'score' => 25, 'playedMs' => 30000, 'level' => $badLevel, 'token' => $levelStart['token'],
    ]), $levelServer, $now + 130);
    check(
        ($rejected['status'] ?? 0) === 400 && ($rejected['error'] ?? '') === 'Level must be a whole number from 1 to 999.',
        "level {$label} is rejected"
    );
}
$withLevel = tylilo_score_handle('POST', [], json_encode([
    'op' => 'submit', 'game' => 'pop-spiral', 'board' => 'default',
    'name' => 'Lv', 'score' => 80, 'playedMs' => 30000, 'level' => 7, 'token' => $levelStart['token'],
]), $levelServer, $now + 130);
check(($withLevel['ok'] ?? false) === true, 'a whole-number level is stored');
$levelList = tylilo_score_handle('GET', ['game' => 'pop-spiral', 'board' => 'default'], '', [], $now + 131);
$publicLevel = null;
$levelsFlag = $levelList['boards'][0]['levels'] ?? null;
foreach ($levelList['boards'][0]['scores'] ?? [] as $row) {
    if ($row['name'] === 'Lv') {
        $publicLevel = $row['level'] ?? 'missing';
    }
}
check($levelsFlag === true && $publicLevel === 7, 'the public list returns the level');
$storedLevel = null;
foreach (tylilo_score_admin_rows('pop-spiral', 'default') as $row) {
    if ($row['name'] === 'Lv') {
        $storedLevel = $row['level'];
    }
}
check($storedLevel === 7, 'admin rows keep the level');

$blankStart = tylilo_score_handle('POST', [], json_encode(['op' => 'start', 'game' => 'pop-spiral', 'board' => 'default']), $levelServer, $now + 140);
$blankLevel = tylilo_score_handle('POST', [], json_encode([
    'op' => 'submit', 'game' => 'pop-spiral', 'board' => 'default',
    'name' => 'Bare', 'score' => 15, 'playedMs' => 30000, 'level' => null, 'token' => $blankStart['token'],
]), $levelServer, $now + 170);
check(($blankLevel['ok'] ?? false) === true, 'a null level is accepted');
$bareLevel = 'missing';
foreach (tylilo_score_admin_rows('pop-spiral', 'default') as $row) {
    if ($row['name'] === 'Bare') {
        $bareLevel = $row['level'];
    }
}
check($bareLevel === null, 'a null level is stored as null');

$solitaireList = tylilo_score_handle('GET', ['game' => 'solitaire', 'board' => 'draw-1'], '', [], $now + 171);
check(($solitaireList['boards'][0]['levels'] ?? true) === false, 'a game without levels does not flag the board');
check(array_key_exists('level', $solitaireList['boards'][0]['scores'][0] ?? []), 'public rows still include level');

$originalDir = getenv('TYLILO_DATA_DIR');
$originalStore = getenv('TYLILO_SCORE_STORE');
$jsonDir = sys_get_temp_dir() . '/tylilo-scores-json-' . getmypid();
mkdir($jsonDir, 0700, true);
file_put_contents($jsonDir . '/scores.json', json_encode([
    'scores' => [[
        'game' => 'pop-spiral',
        'board' => 'default',
        'name' => 'Old',
        'score' => 11,
        'at' => $now,
    ]],
    'rates' => [],
    'used' => [],
    'seeds' => ['migrated'],
], JSON_UNESCAPED_SLASHES));
putenv('TYLILO_DATA_DIR=' . $jsonDir);
putenv('TYLILO_SCORE_STORE=json');
$migrated = tylilo_score_handle('GET', ['game' => 'pop-spiral', 'board' => 'default'], '', [], $now + 172);
$migratedRow = $migrated['boards'][0]['scores'][0] ?? [];
check(array_key_exists('level', $migratedRow) && $migratedRow['level'] === null, 'an old JSON row gets a null level');
$jsonStart = tylilo_score_handle('POST', [], json_encode(['op' => 'start', 'game' => 'jungle-rescue', 'board' => 'default']), $levelServer, $now + 180);
$jsonSaved = tylilo_score_handle('POST', [], json_encode([
    'op' => 'submit', 'game' => 'jungle-rescue', 'board' => 'default',
    'name' => 'Jay', 'score' => 90, 'playedMs' => 30000, 'level' => 12, 'token' => $jsonStart['token'],
]), $levelServer, $now + 210);
$jsonRaw = json_decode((string) file_get_contents($jsonDir . '/scores.json'), true);
$jsonStored = null;
foreach ($jsonRaw['scores'] ?? [] as $row) {
    if (($row['name'] ?? '') === 'Jay') {
        $jsonStored = $row['level'] ?? 'missing';
    }
}
check(($jsonSaved['ok'] ?? false) === true && $jsonStored === 12, 'the JSON store writes the level');

if (class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $sqliteDir = sys_get_temp_dir() . '/tylilo-scores-sqlite-' . getmypid();
    mkdir($sqliteDir, 0700, true);
    $old = new PDO('sqlite:' . $sqliteDir . '/scores.sqlite');
    $old->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $old->exec('CREATE TABLE scores (game TEXT, board TEXT, name TEXT, score INTEGER, at INTEGER, version TEXT)');
    $old->exec('CREATE TABLE rates (hash TEXT, at INTEGER)');
    $old->exec('CREATE TABLE used_tokens (hash TEXT PRIMARY KEY, exp INTEGER)');
    $old->exec('CREATE TABLE seeds (id TEXT PRIMARY KEY)');
    $old->prepare('INSERT INTO scores (game, board, name, score, at, version) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute(['pop-spiral', 'default', 'Old', 11, $now, null]);
    $old->prepare('INSERT INTO seeds (id) VALUES (?)')->execute(['migrated']);
    $old = null;
    putenv('TYLILO_DATA_DIR=' . $sqliteDir);
    putenv('TYLILO_SCORE_STORE=sqlite');
    $sqliteList = tylilo_score_handle('GET', ['game' => 'pop-spiral', 'board' => 'default'], '', [], $now + 220);
    $sqliteRow = $sqliteList['boards'][0]['scores'][0] ?? [];
    $sqlitePdo = new PDO('sqlite:' . $sqliteDir . '/scores.sqlite');
    $sqliteColumns = array_column($sqlitePdo->query('PRAGMA table_info(scores)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    check(array_key_exists('level', $sqliteRow) && $sqliteRow['level'] === null && in_array('level', $sqliteColumns, true), 'an old SQLite row gets a null level');
    $sqliteStart = tylilo_score_handle('POST', [], json_encode(['op' => 'start', 'game' => 'jungle-rescue', 'board' => 'default']), $levelServer, $now + 230);
    $sqliteSaved = tylilo_score_handle('POST', [], json_encode([
        'op' => 'submit', 'game' => 'jungle-rescue', 'board' => 'default',
        'name' => 'Sam', 'score' => 70, 'playedMs' => 30000, 'level' => 3, 'token' => $sqliteStart['token'],
    ]), $levelServer, $now + 260);
    $sqliteStored = $sqlitePdo->query("SELECT level FROM scores WHERE name = 'Sam'")->fetchColumn();
    check(($sqliteSaved['ok'] ?? false) === true && (int) $sqliteStored === 3, 'the SQLite store writes the level');
    $sqlitePdo = null;
}

putenv('TYLILO_DATA_DIR=' . $originalDir);
if ($originalStore === false) {
    putenv('TYLILO_SCORE_STORE');
} else {
    putenv('TYLILO_SCORE_STORE=' . $originalStore);
}

tylilo_score_clear_board('pop-spiral', 'default');
check(tylilo_score_admin_rows('pop-spiral', 'default') === [], 'clearing a board removes only that board');
check(tylilo_score_admin_rows('jungle-rescue', 'easy') !== [], 'clearing one board leaves the others');

$both = tylilo_score_handle('GET', ['game' => 'solitaire'], '', [], $now);
$boardIds = array_map(static fn (array $row): string => (string) ($row['board'] ?? ''), $both['boards'] ?? []);
check($boardIds === ['draw-1', 'draw-3'], 'omitting board returns every solitaire board');

// Jungle Rescue difficulty boards, legacy aliases, the one-time migration, and the cheat flag.
$jr = tylilo_score_handle('GET', ['game' => 'jungle-rescue'], '', [], $now);
$jrIds = array_map(static fn (array $row): string => (string) ($row['board'] ?? ''), $jr['boards'] ?? []);
check($jrIds === ['easy', 'easy-time', 'normal', 'normal-time', 'expert', 'expert-time'], 'jungle-rescue lists exactly the six difficulty boards');
$jrLabels = array_map(static fn (array $row): string => (string) ($row['label'] ?? ''), $jr['boards'] ?? []);
check($jrLabels === ['Easy', 'Easy time', 'Normal', 'Normal time', 'Expert', 'Expert time'], 'jungle-rescue board labels');
$jrGroups = array_map(static fn (array $row): string => (string) ($row['group'] ?? ''), $jr['boards'] ?? []);
check($jrGroups === ['Easy', 'Easy', 'Normal', 'Normal', 'Expert', 'Expert'], 'jungle-rescue boards carry their difficulty group');
$jrOk = true;
foreach ($jr['boards'] ?? [] as $row) {
    $isTime = str_ends_with((string) $row['board'], '-time');
    $spec = tylilo_score_board('jungle-rescue', (string) $row['board']);
    $jrOk = $jrOk && $row['levels'] === true
        && $row['order'] === ($isTime ? 'asc' : 'desc')
        && $row['unit'] === ($isTime ? 'seconds' : 'points')
        && $spec['min'] === ($isTime ? 1 : 0)
        && $spec['max'] === ($isTime ? 7200 : 5000000)
        && $spec['minPlayedMs'] === 20000;
}
check($jrOk, 'jungle-rescue boards keep the old score and time rules with levels on');
$redRainList = tylilo_score_handle('GET', ['game' => 'red-rain'], '', [], $now);
check(!array_key_exists('group', $redRainList['boards'][0] ?? []), 'other games have no group field');

$aliasServer = $server;
$aliasServer['REMOTE_ADDR'] = '203.0.113.90';
$aliasStart = tylilo_score_handle('POST', [], json_encode(['op' => 'start', 'game' => 'jungle-rescue', 'board' => 'default']), $aliasServer, $now);
check(($aliasStart['ok'] ?? false) === true && ($aliasStart['board'] ?? '') === 'default' && ($aliasStart['storedAs'] ?? '') === 'easy' && ($aliasStart['minPlayedMs'] ?? 0) === 20000, 'op:start still works for the legacy default board');
$aliasToken = tylilo_score_read_token((string) ($aliasStart['token'] ?? ''), $now);
check(($aliasToken['board'] ?? '') === 'default', 'a legacy token stays bound to default');
$wrongBoard = tylilo_score_handle('POST', [], json_encode([
    'op' => 'submit', 'game' => 'jungle-rescue', 'board' => 'easy',
    'name' => 'Alias', 'score' => 321, 'playedMs' => 30000, 'level' => 2, 'token' => $aliasStart['token'],
]), $aliasServer, $now + 30);
check(($wrongBoard['status'] ?? 0) === 400, 'a default token cannot submit to easy');
$aliasSaved = tylilo_score_handle('POST', [], json_encode([
    'op' => 'submit', 'game' => 'jungle-rescue', 'board' => 'default',
    'name' => 'Alias', 'score' => 321, 'playedMs' => 30000, 'level' => 2, 'token' => $aliasStart['token'],
]), $aliasServer, $now + 30);
$aliasNames = array_map(static fn (array $row): string => (string) ($row['name'] ?? ''), $aliasSaved['scores'] ?? []);
check(($aliasSaved['ok'] ?? false) === true && in_array('Alias', $aliasNames, true), 'a default token submits OK under default');
$aliasEasy = array_column(tylilo_score_admin_rows('jungle-rescue', 'easy'), 'name');
check(in_array('Alias', $aliasEasy, true), 'a default submit is stored on easy');
check(tylilo_score_admin_rows('jungle-rescue', 'default') === [], 'nothing is stored on the default alias');
$aliasRead = tylilo_score_handle('GET', ['game' => 'jungle-rescue', 'board' => 'default'], '', [], $now + 31);
check(($aliasRead['boards'][0]['board'] ?? '') === 'easy', 'GET board=default reads easy');
$timeStart = tylilo_score_handle('POST', [], json_encode(['op' => 'start', 'game' => 'jungle-rescue', 'board' => 'time']), $aliasServer, $now + 40);
$timeSaved = tylilo_score_handle('POST', [], json_encode([
    'op' => 'submit', 'game' => 'jungle-rescue', 'board' => 'time',
    'name' => 'Quick', 'score' => 61, 'playedMs' => 61000, 'level' => 4, 'token' => $timeStart['token'],
]), $aliasServer, $now + 101);
$easyTime = array_column(tylilo_score_admin_rows('jungle-rescue', 'easy-time'), 'name');
check(($timeSaved['ok'] ?? false) === true && in_array('Quick', $easyTime, true), 'a time submit is stored on easy-time');
$normalStart = tylilo_score_handle('POST', [], json_encode(['op' => 'start', 'game' => 'jungle-rescue', 'board' => 'expert-time']), $aliasServer, $now + 110);
check(($normalStart['ok'] ?? false) === true && !array_key_exists('storedAs', $normalStart), 'a new board starts without storedAs');

$cheatServer = $server;
$cheatServer['REMOTE_ADDR'] = '203.0.113.95';
foreach (['true' => true, 'a string' => 'yes', 'one' => 1] as $label => $flag) {
    $cheatStart = tylilo_score_handle('POST', [], json_encode(['op' => 'start', 'game' => 'pop-spiral', 'board' => 'default']), $cheatServer, $now);
    $cheat = tylilo_score_handle('POST', [], json_encode([
        'op' => 'submit', 'game' => 'pop-spiral', 'board' => 'default',
        'name' => 'Cheat', 'score' => 99, 'playedMs' => 30000, 'cheat' => $flag, 'token' => $cheatStart['token'],
    ]), $cheatServer, $now + 30);
    check(($cheat['status'] ?? 0) === 400 && ($cheat['error'] ?? '') === 'Cheat runs are not saved.', "cheat {$label} is rejected");
}
check(!in_array('Cheat', array_column(tylilo_score_admin_rows('pop-spiral', 'default'), 'name'), true), 'a cheat run is not stored');
$fairStart = tylilo_score_handle('POST', [], json_encode(['op' => 'start', 'game' => 'pop-spiral', 'board' => 'default']), $cheatServer, $now);
$fair = tylilo_score_handle('POST', [], json_encode([
    'op' => 'submit', 'game' => 'pop-spiral', 'board' => 'default',
    'name' => 'Fair', 'score' => 98, 'playedMs' => 30000, 'cheat' => false, 'token' => $fairStart['token'],
]), $cheatServer, $now + 30);
check(($fair['ok'] ?? false) === true, 'cheat false still saves');

$liveRows = static function () use ($now): array {
    return [
        ['game' => 'jungle-rescue', 'board' => 'default', 'name' => 'LOGAN', 'score' => 187100, 'at' => $now - 50, 'version' => null, 'level' => null],
        ['game' => 'jungle-rescue', 'board' => 'default', 'name' => 'LOGAN', 'score' => 24500, 'at' => $now - 40, 'version' => '2.16', 'level' => 3],
        ['game' => 'jungle-rescue', 'board' => 'default', 'name' => 'LOGAN', 'score' => 4550, 'at' => $now - 30, 'version' => null, 'level' => null],
        ['game' => 'jungle-rescue', 'board' => 'default', 'name' => 'RYAN', 'score' => 700, 'at' => $now - 20, 'version' => '2.17', 'level' => 1],
        ['game' => 'jungle-rescue', 'board' => 'time', 'name' => 'RYAN', 'score' => 300, 'at' => $now - 10, 'version' => '2.17', 'level' => 2],
        ['game' => 'red-rain', 'board' => 'easy', 'name' => 'LINK', 'score' => 1011970, 'at' => $now - 5, 'version' => null, 'level' => null],
    ];
};
$checkMigrated = static function (string $kind) use ($now): void {
    $easy = tylilo_score_handle('GET', ['game' => 'jungle-rescue', 'board' => 'easy'], '', [], $now);
    $easyRows = array_map(static fn (array $row): array => [$row['name'], $row['score'], $row['level']], $easy['boards'][0]['scores'] ?? []);
    check($easyRows === [['LOGAN', 24500, 3], ['LOGAN', 4550, null], ['RYAN', 700, 1]], "{$kind}: migration moves default rows to easy with levels and drops LOGAN 187100");
    $easyTime = tylilo_score_handle('GET', ['game' => 'jungle-rescue', 'board' => 'easy-time'], '', [], $now);
    $timeRows = array_map(static fn (array $row): array => [$row['name'], $row['score'], $row['level']], $easyTime['boards'][0]['scores'] ?? []);
    check($timeRows === [['RYAN', 300, 2]], "{$kind}: migration moves time rows to easy-time");
    $raw = tylilo_scores_with(static fn (array $stored): array => $stored);
    $left = array_filter($raw['scores'], static fn (array $row): bool => $row['game'] === 'jungle-rescue' && in_array($row['board'], ['default', 'time'], true));
    $banned = array_filter($raw['scores'], static fn (array $row): bool => $row['score'] === 187100);
    check($left === [] && $banned === [], "{$kind}: no rows remain on the legacy boards and 187100 is gone");
    check(in_array('jungle-rescue-difficulty-split-2026-11-21', $raw['seeds'], true), "{$kind}: the migration is remembered as applied");
    $redRain = array_filter($raw['scores'], static fn (array $row): bool => $row['game'] === 'red-rain' && $row['name'] === 'LINK');
    check(count($redRain) === 1, "{$kind}: other games are untouched and LINK is not seeded twice");
    $migrations = tylilo_score_migrations();
    $again = $migrations['jungle-rescue-difficulty-split-2026-11-21']($raw);
    check($again['scores'] === $raw['scores'], "{$kind}: running the migration again changes nothing");
};

$beforeDir = getenv('TYLILO_DATA_DIR');
$beforeStore = getenv('TYLILO_SCORE_STORE');
$migJson = sys_get_temp_dir() . '/tylilo-scores-mig-json-' . getmypid();
mkdir($migJson, 0700, true);
file_put_contents($migJson . '/scores.json', json_encode([
    'scores' => $liveRows(), 'rates' => [], 'used' => [],
    'seeds' => ['logan-jungle-rescue-187100', 'link-red-rain-easy-1011970'],
], JSON_UNESCAPED_SLASHES));
putenv('TYLILO_DATA_DIR=' . $migJson);
putenv('TYLILO_SCORE_STORE=json');
$checkMigrated('JSON');

if (class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $migSqlite = sys_get_temp_dir() . '/tylilo-scores-mig-sqlite-' . getmypid();
    mkdir($migSqlite, 0700, true);
    putenv('TYLILO_DATA_DIR=' . $migSqlite);
    putenv('TYLILO_SCORE_STORE=sqlite');
    $pdo = tylilo_scores_pdo();
    $insert = $pdo->prepare('INSERT INTO scores (game, board, name, score, at, version, level) VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach ($liveRows() as $row) {
        $insert->execute(array_values($row));
    }
    $pdo->exec("INSERT INTO seeds (id) VALUES ('logan-jungle-rescue-187100'), ('link-red-rain-easy-1011970')");
    $pdo = null;
    $checkMigrated('SQLite');
}

$freshDir = sys_get_temp_dir() . '/tylilo-scores-fresh-' . getmypid();
mkdir($freshDir, 0700, true);
putenv('TYLILO_DATA_DIR=' . $freshDir);
putenv('TYLILO_SCORE_STORE=json');
tylilo_score_handle('GET', ['game' => 'jungle-rescue'], '', [], $now);
$fresh = tylilo_scores_with(static fn (array $stored): array => $stored);
$freshJungle = array_filter($fresh['scores'], static fn (array $row): bool => $row['game'] === 'jungle-rescue');
check($freshJungle === [] && !in_array('logan-jungle-rescue-187100', $fresh['seeds'], true), 'a fresh store does not re-seed LOGAN 187100');
check(in_array('jungle-rescue-difficulty-split-2026-11-21', $fresh['seeds'], true), 'a fresh store records the migration as applied');
$freshSeeds = array_column(tylilo_score_seeds(), 'id');
check(!in_array('logan-jungle-rescue-187100', $freshSeeds, true), 'the LOGAN 187100 seed entry is gone');

putenv('TYLILO_DATA_DIR=' . $beforeDir);
if ($beforeStore === false) {
    putenv('TYLILO_SCORE_STORE');
} else {
    putenv('TYLILO_SCORE_STORE=' . $beforeStore);
}

echo $failed === 0 ? "\n{$checks} checks passed\n" : "\n{$failed} failed\n";
exit($failed === 0 ? 0 : 1);
