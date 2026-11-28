<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/playtest-boot.php';

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    tylilo_playtest_json(405, ['ok' => false, 'error' => 'method', 'message' => 'Use GET.'], ['Allow: GET']);
}
$refusal = tylilo_playtest_feed_refusal($_SERVER, tylilo_playtest_read_secret());
if ($refusal !== null) {
    tylilo_playtest_json($refusal[0], $refusal[1], $refusal[0] === 401 ? ['WWW-Authenticate: Bearer realm="playtest"'] : []);
}
$game = is_string($_GET['game'] ?? null) ? $_GET['game'] : '';
$since = is_string($_GET['since'] ?? null) ? $_GET['since'] : '';
if (preg_match('/^[a-z0-9-]{1,40}$/', $game) !== 1) {
    tylilo_playtest_json(422, ['ok' => false, 'error' => 'game', 'message' => 'Add ?game=<slug>.']);
}
if ($since !== '' && !tylilo_playtest_id_ok($since)) {
    tylilo_playtest_json(422, ['ok' => false, 'error' => 'since', 'message' => 'since must be a report id.']);
}
tylilo_playtest_purge();
$base = tylilo_playtest_base_url($_SERVER);
$items = [];
foreach (tylilo_playtest_list($game, $since) as $meta) {
    $items[] = tylilo_playtest_feed_item($meta, $base);
}
tylilo_playtest_json(200, ['ok' => true, 'game' => $game, 'items' => $items]);
