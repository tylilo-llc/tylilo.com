<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

/**
 * ARCADE GAMES
 * ------------
 * One entry is one tile, in the order shown on the page.
 * Add a game with one entry here plus arcade/<slug>/art.svg.
 * The game file is arcade/<slug>/index.html. Another maintainer owns
 * that file; drop a new version in unchanged. Do not edit it here.
 * Artwork is decorative: the tile name is the accessible text.
 * Games open in a new window. Leave newWindow unset (it defaults to
 * true). A Top scores control, if added later, goes after the link
 * inside the article, not inside the link.
 */
$games = [
    [
        'title' => 'Solitaire',
        'description' => 'Classic Klondike solitaire.',
        'href' => '/arcade/solitaire/',
        'art' => '/arcade/solitaire/art.svg',
    ],
    [
        'title' => 'Red Rain',
        'description' => 'A missile-defense arcade game, playable on desktop and touch.',
        'href' => '/arcade/red-rain/',
        'art' => '/arcade/red-rain/art.svg',
    ],
    [
        'title' => 'Pop Spiral',
        'description' => 'An original marble-shooter puzzle.',
        'href' => '/arcade/pop-spiral/',
        'art' => '/arcade/pop-spiral/art.svg',
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
<?php $opensNewWindow = ($game['newWindow'] ?? true) !== false; ?>
        <article class="game-slot">
          <a class="game-tile" href="<?= tylilo_e($game['href']) ?>"<?php if ($opensNewWindow): ?> target="_blank" rel="noopener"<?php endif; ?>>
            <span class="game-tile-art">
              <img src="<?= tylilo_e(tylilo_asset($game['art'])) ?>" alt="" width="200" height="150">
            </span>
            <span class="game-tile-name"><?= tylilo_e($game['title']) ?></span>
<?php if ($opensNewWindow): ?>
            <svg class="game-tile-out" aria-hidden="true" focusable="false" viewBox="0 0 24 24" width="16" height="16">
              <path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M14 5h5v5M19 5l-8 8"/>
              <path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M10 5H6.5A1.5 1.5 0 0 0 5 6.5v11A1.5 1.5 0 0 0 6.5 19h11a1.5 1.5 0 0 0 1.5-1.5V14"/>
            </svg>
            <span class="visually-hidden"><?= tylilo_e($game['description']) ?> (opens in a new window)</span>
<?php else: ?>
            <span class="visually-hidden"><?= tylilo_e($game['description']) ?></span>
<?php endif; ?>
          </a>
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
