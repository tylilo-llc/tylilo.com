<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

/**
 * ARCADE GAMES
 * ------------
 * One entry is one tile, in the order shown on the page.
 * Add a game with one entry here plus arcade/<slug>/cover-400.webp
 * and arcade/<slug>/cover-800.webp.
 * The game file is arcade/<slug>/index.html. Another maintainer owns
 * that file; drop a new version in unchanged. Do not edit it here.
 * Artwork is decorative: the tile name is the accessible text.
 * Games open in the same tab. Do not add target="_blank".
 * The Top scores button sits after the link, inside the
 * article, and is not part of the link. Its data-game value is the
 * leaderboard slug in includes/scores.php.
 */
$games = [
    [
        'title' => 'Solitaire',
        'description' => 'Classic Klondike solitaire.',
        'href' => '/arcade/solitaire/',
        'art' => '/arcade/solitaire/cover-400.webp',
        'art2x' => '/arcade/solitaire/cover-800.webp',
    ],
    [
        'title' => 'Red Rain',
        'description' => 'A missile-defense arcade game, playable on desktop and touch.',
        'href' => '/arcade/red-rain/',
        'art' => '/arcade/red-rain/cover-400.webp',
        'art2x' => '/arcade/red-rain/cover-800.webp',
    ],
    [
        'title' => 'Pop Spiral',
        'description' => 'An original marble-shooter puzzle.',
        'href' => '/arcade/pop-spiral/',
        'art' => '/arcade/pop-spiral/cover-400.webp',
        'art2x' => '/arcade/pop-spiral/cover-800.webp',
    ],
    [
        'title' => 'Jungle Rescue',
        'description' => 'A commando rescue run through the jungle.',
        'href' => '/arcade/jungle-rescue/',
        'art' => '/arcade/jungle-rescue/cover-400.webp',
        'art2x' => '/arcade/jungle-rescue/cover-800.webp',
    ],
];

$pageTitle = 'Arcade — Tylilo';
$pageDescription = 'Browser games from Tylilo, including Solitaire, Red Rain, Pop Spiral, and Jungle Rescue.';
$pageCanonical = TYLILO_URL . '/arcade/';
$pageImage = '/assets/img/og-arcade.jpg';
$pageImageAlt = 'Tylilo Arcade: a neon ARCADE sign over a montage of Solitaire, Red Rain, Pop Spiral, and Jungle Rescue box art';
$pageRobots = 'index, follow';

$gameParts = [];
foreach ($games as $game) {
    $gameParts[] = [
        '@type' => 'VideoGame',
        'name' => $game['title'],
        'description' => $game['description'],
        'url' => TYLILO_URL . $game['href'],
    ];
}

require __DIR__ . '/../includes/head.php';
?>
<body>
  <a class="skip-link" href="#main">Skip to content</a>
  <header class="hero">
    <div class="hero-shapes" aria-hidden="true">
      <span class="blob a"></span>
      <span class="blob b"></span>
      <span class="blob c"></span>
    </div>
<?php require __DIR__ . '/../includes/topbar.php'; ?>
    <div class="hero-inner">
      <div class="logo-seal">
        <img src="/assets/img/logo-round.webp" alt="Tylilo logo" width="168" height="168">
      </div>
    </div>
  </header>

  <main id="main" tabindex="-1">
    <div class="wrap wide">
      <div class="card about-card arcade-lead">
        <p class="kicker">Play</p>
        <h1>Arcade</h1>
      </div>
      <div class="arcade-grid">
<?php foreach ($games as $index => $game): ?>
<?php
    $gameSlug = basename(trim((string) (parse_url($game['href'], PHP_URL_PATH) ?? ''), '/'));
    $scoresId = 'scores-' . $gameSlug;
    $aboveFold = $index < 3;
?>
        <article class="game-slot">
          <a class="game-tile" href="<?= tylilo_e($game['href']) ?>">
            <span class="game-tile-art">
              <img src="<?= tylilo_e(tylilo_asset($game['art'])) ?>" srcset="<?= tylilo_e(tylilo_asset($game['art'])) ?> 400w, <?= tylilo_e(tylilo_asset($game['art2x'])) ?> 800w" sizes="200px" alt="" width="200" height="150" decoding="async"<?= $aboveFold ? '' : ' loading="lazy"' ?><?= $index === 0 ? ' fetchpriority="high"' : '' ?>>
            </span>
            <span class="game-tile-name"><?= tylilo_e($game['title']) ?></span>
            <span class="visually-hidden"><?= tylilo_e($game['description']) ?></span>
          </a>
          <div class="score-panel">
            <button class="score-toggle" type="button" aria-expanded="false" aria-controls="<?= tylilo_e($scoresId) ?>" data-game="<?= tylilo_e($gameSlug) ?>">
              Top scores<span class="visually-hidden"> for <?= tylilo_e($game['title']) ?></span>
            </button>
            <div class="score-body" id="<?= tylilo_e($scoresId) ?>" role="region" aria-label="Top scores for <?= tylilo_e($game['title']) ?>" hidden>
              <p class="score-status visually-hidden" role="status"></p>
              <div class="score-results"></div>
            </div>
          </div>
        </article>
<?php endforeach; ?>
      </div>
    </div>
  </main>
  <script type="application/ld+json" nonce="<?= tylilo_e($cspNonce) ?>">
    <?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => 'Arcade',
        'url' => TYLILO_URL . '/arcade/',
        'description' => $pageDescription,
        'image' => TYLILO_URL . $pageImage,
        'isPartOf' => [
            '@type' => 'WebSite',
            'name' => TYLILO_SITE_NAME,
            'url' => TYLILO_URL . '/',
        ],
        'hasPart' => $gameParts,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR) ?>
  </script>
  <script src="<?= tylilo_e(tylilo_asset('/assets/js/scores.js')) ?>" defer></script>
<?php $pageA2hs = true; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
