<?php

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

if (!TYLILO_OTRADIO_ENABLED) {
    header('X-Robots-Tag: noindex, follow');
    require __DIR__ . '/../404.php';
    exit;
}

require __DIR__ . '/../includes/bootstrap.php';

$pageTitle = 'OTRadio — Old-time radio';
$pageDescription = 'Listen to cleaned-up old-time radio episodes with captions, show notes, and series artwork.';
$pageCanonical = TYLILO_URL . '/otradio/';
$pageStylesheets = ['/otradio/player.css'];

require __DIR__ . '/../includes/head.php';
?>
<body>
  <a class="skip-link" href="#main">Skip to content</a>
  <div class="otr-player" id="otr-player">
    <header class="hero">
      <div class="hero-shapes" aria-hidden="true">
        <span class="blob a"></span>
        <span class="blob b"></span>
        <span class="blob c"></span>
      </div>
<?php require __DIR__ . '/../includes/topbar.php'; ?>
      <div class="otr-hero-inner">
        <p class="otr-wordmark">OTRadio</p>
        <h1>Old-time radio, cleaned up and ready to play</h1>
        <p class="otr-lede">A handful of public-domain test episodes, loudness-matched, with captions and show notes. Captions are a first-pass auto transcript and may mishear names or opening lines.</p>
      </div>
    </header>

    <main id="main" class="otr-main" tabindex="-1">
      <p class="otr-banner" id="otr-banner" role="alert" hidden></p>
      <div class="otr-layout">
        <section class="otr-library" aria-labelledby="otr-library-heading">
          <h2 id="otr-library-heading" class="otr-kicker">Library</h2>
          <div id="otr-series" class="otr-series" role="list"></div>
          <div id="otr-episodes-panel" class="otr-episodes-panel" hidden>
            <div class="otr-episodes-head">
              <h3 id="otr-series-title"></h3>
              <p id="otr-series-meta" class="otr-muted"></p>
            </div>
            <ul id="otr-episodes" class="otr-episodes"></ul>
          </div>
        </section>

        <section class="otr-stage" aria-labelledby="otr-now-heading">
          <div id="otr-empty" class="otr-card otr-empty">
            <p class="otr-kicker">Now playing</p>
            <h2 id="otr-now-heading">Pick a series, then an episode</h2>
            <p class="otr-muted">Artwork, captions, and show notes will show up here once you start a story.</p>
          </div>

          <div id="otr-player-card" class="otr-card otr-player-card" hidden>
            <p class="otr-kicker">Now playing</p>
            <div class="otr-art-wrap">
              <img id="otr-poster" class="otr-poster" alt="">
              <div id="otr-captions" class="otr-captions" aria-live="polite" hidden></div>
              <video id="otr-media" class="otr-media" playsinline preload="metadata" crossorigin="anonymous" aria-hidden="true" tabindex="-1"></video>
            </div>
            <h2 id="otr-episode-title" class="otr-episode-title"></h2>
            <p id="otr-episode-series" class="otr-muted"></p>

            <div class="otr-controls">
              <div class="otr-control-row">
                <button type="button" id="otr-play" class="otr-btn otr-btn-primary" aria-label="Play">Play</button>
                <button type="button" id="otr-back-30" class="otr-btn" aria-label="Skip back 30 seconds">−30</button>
                <button type="button" id="otr-fwd-30" class="otr-btn" aria-label="Skip forward 30 seconds">+30</button>
                <label class="otr-speed-label">
                  <span class="otr-visually-hidden">Playback speed</span>
                  <select id="otr-speed" class="otr-speed" aria-label="Playback speed">
                    <option value="0.75">0.75×</option>
                    <option value="1" selected>1×</option>
                    <option value="1.25">1.25×</option>
                    <option value="1.5">1.5×</option>
                    <option value="2">2×</option>
                  </select>
                </label>
                <button type="button" id="otr-cc" class="otr-btn" aria-pressed="true" aria-label="CC, captions on" title="CC, captions on">CC</button>
              </div>
              <div class="otr-seek-row">
                <span id="otr-time" class="otr-time">0:00</span>
                <input type="range" id="otr-seek" class="otr-seek" min="0" max="0" step="0.1" value="0" aria-label="Seek" aria-valuetext="0 minutes 0 seconds of 0 minutes 0 seconds">
                <span id="otr-duration" class="otr-time">0:00</span>
              </div>
              <p id="otr-caption-status" class="otr-caption-status" hidden role="status"></p>
            </div>
            <p id="otr-player-status" class="otr-visually-hidden" role="status" aria-live="polite"></p>
          </div>

          <aside id="otr-notes" class="otr-card otr-notes" hidden>
            <h2 class="otr-kicker">Show notes</h2>
            <div id="otr-notes-body"></div>
          </aside>
        </section>
      </div>
    </main>

    <div class="otr-foot">
      <p>Test catalog for OTRadio. Rights notes are a research starting point, not a legal verdict. Captions are first-pass auto transcripts and will need a human pass for production.</p>
    </div>
  </div>
  <script src="/otradio/player.js" defer></script>
  <script type="application/ld+json" nonce="<?= tylilo_e($cspNonce) ?>">
    <?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => 'OTRadio',
        'url' => TYLILO_URL . '/otradio/',
        'description' => $pageDescription,
        'isPartOf' => [
            '@type' => 'WebSite',
            'name' => TYLILO_SITE_NAME,
            'url' => TYLILO_URL . '/',
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => TYLILO_LEGAL_NAME,
            'url' => TYLILO_URL . '/',
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR) ?>
  </script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
