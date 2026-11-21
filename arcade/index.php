<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

/**
 * ARCADE GAMES
 * ------------
 * One entry is one card, in the order shown on the page.
 * The game file is arcade/<slug>/index.html. Another maintainer owns
 * that file; drop a new version in unchanged. Do not edit it here.
 * Thumbnails live next to the game. Leave alt empty: the card title
 * already names the game.
 */
$games = [
    [
        'title' => 'Solitaire',
        'description' => 'Classic Klondike solitaire.',
        'href' => '/arcade/solitaire/',
        'thumb' => '/arcade/solitaire/thumb.png',
        'width' => 843,
        'height' => 321,
    ],
    [
        'title' => 'Red Rain',
        'description' => 'A missile-defense arcade game, playable on desktop and touch.',
        'href' => '/arcade/red-rain/',
        'thumb' => '/arcade/red-rain/thumb.png',
        'width' => 843,
        'height' => 321,
    ],
    [
        'title' => 'Pop Spiral',
        'description' => 'An original marble-shooter puzzle.',
        'href' => '/arcade/pop-spiral/',
        'thumb' => '/arcade/pop-spiral/thumb.png',
        'width' => 843,
        'height' => 321,
    ],
];

$pageTitle = 'Arcade — Tylilo';
$pageDescription = 'Play Solitaire, Red Rain, and Pop Spiral in the browser: Klondike solitaire, missile defense, and a marble-shooter puzzle.';
$pageCanonical = TYLILO_URL . '/arcade/';
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
        <p class="muted">Three games in the browser. Nothing to install.</p>
      </div>
      <div class="arcade-grid">
<?php foreach ($games as $game): ?>
        <article class="card game-card">
          <div class="game-thumb">
            <img src="<?= tylilo_e($game['thumb']) ?>" alt="" width="<?= (int) $game['width'] ?>" height="<?= (int) $game['height'] ?>">
          </div>
          <h2><?= tylilo_e($game['title']) ?></h2>
          <p><?= tylilo_e($game['description']) ?></p>
          <a class="btn" href="<?= tylilo_e($game['href']) ?>">Play <?= tylilo_e($game['title']) ?></a>
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
        'isPartOf' => [
            '@type' => 'WebSite',
            'name' => TYLILO_SITE_NAME,
            'url' => TYLILO_URL . '/',
        ],
        'hasPart' => $gameParts,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR) ?>
  </script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
