<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Privacy — Tylilo';
$pageDescription = 'What tylilo.com collects: the contact form, one session cookie, settings the browser keeps on your device, and names saved to the arcade leaderboard.';
$pageCanonical = TYLILO_URL . '/privacy';
$pageRobots = 'index, follow';
$contactEmail = TYLILO_CONTACT_EMAIL;

require __DIR__ . '/includes/head.php';
?>
<body>
  <a class="skip-link" href="#main">Skip to content</a>
  <header class="hero">
    <div class="hero-shapes" aria-hidden="true">
      <span class="blob a"></span>
      <span class="blob b"></span>
      <span class="blob c"></span>
    </div>
<?php require __DIR__ . '/includes/topbar.php'; ?>
    <div class="hero-inner">
      <div class="logo-seal">
        <img src="/assets/img/logo-round.webp" alt="Tylilo logo" width="168" height="168">
      </div>
    </div>
  </header>

  <main id="main" tabindex="-1">
    <div class="wrap solo">
      <article class="card about-card prose">
        <p class="kicker">Privacy</p>
        <h1>Privacy</h1>
        <p><strong>Effective date:</strong> November 21, 2026</p>
        <p>Tylilo, LLC (“Tylilo”) runs <a href="/">tylilo.com</a>. This page says what the site actually does with information. It is short on purpose.</p>

        <h2>Contact form</h2>
        <p>If you write to us, the form asks for your name, email address, and message. We email those three things to <a href="mailto:<?= tylilo_e($contactEmail) ?>"><?= tylilo_e($contactEmail) ?></a> so we can read them and reply. The site does not sell that information, and it does not send it to advertisers or other companies.</p>
        <p>We do not keep a database of messages. Your words sit in a temporary server session only long enough to show a thank-you or an error, and then that copy is removed. The same session holds a security token and the times of recent sends (five messages in 30 minutes) so the form is harder to spam. The site code does not write your message into its own log file. The email itself goes out through the web host’s mail system, which is how it reaches us.</p>

        <h2>Cookies</h2>
        <p>The homepage sets one cookie named <code>tylilo</code>. It is a session cookie: it lasts until you close the browser. It is HttpOnly, so page scripts cannot read it, and SameSite is Lax. When you visit over HTTPS, it is also marked Secure.</p>
        <p>That cookie exists so the contact form can tell a real visit from a forged or repeated one. It is not a tracking cookie. The site does not set advertising cookies.</p>

        <h2>Saved on your device</h2>
        <p>If you pick Light, Dark, or Auto, the browser stores that choice in local storage under <code>tylilo-theme</code>. It stays on your device. The site does not send it to us.</p>
        <p>Arcade games save their settings and high scores the same way, in this browser’s local storage. Red Rain stores music and mute, difficulty, options, and high scores. Solitaire stores its draw mode under <code>klondike.draw</code>, its stats under <code>klondike.stats</code> (games played and won, streaks, best score and time for each draw mode, and whether a game is in progress; no personal data), and sound on or off under <code>klondike.muted</code>. Pop Spiral stores a best score under <code>popSpiral.best</code> and its settings under <code>popSpiral.settings.v1</code>. Jungle Rescue stores options under <code>jungleRescueOpts</code>, difficulty (0–2) under <code>jungleRescueDiff</code>, an all-time high score under <code>jungleRescueHi</code>, best distance for each stage under <code>jungleRescueBestDist</code> and <code>jungleRescueBestDist2</code> through <code>jungleRescueBestDist20</code>, the highest unlocked stage under <code>jungleRescueUnlocked</code>, difficulties beaten under <code>jungleRescueBeat</code>, and collectible cards under <code>jungleRescueCards</code>. Options → Reset Progress clears the unlocked stage, the beaten difficulties, and the cards. Jungle Rescue does not use session storage, cookies, or IndexedDB. None of that on-device storage is sent to us. The games load no outside resources.</p>

        <h2>Arcade leaderboard</h2>
        <p>The arcade can also keep a shared list of top scores on this site. That is separate from the on-device scores above. When a game submits a score, the browser sends the name you choose, the score, the game, the board, and how long that game ran to <code>/api/scores.php</code> on tylilo.com. We store the name, score, game, board, and the time it was saved so anyone can read the top scores on the Arcade page. We do not ask for an email address or create an account for this.</p>
        <p>To slow down repeated submissions, we store a hash of the network address with the time of the save, not the address itself. That hash cannot be turned back into the address, and we delete it after it is no longer needed for the limit. We also store a hash of a single-use start token so the same game cannot be saved twice. The leaderboard does not set a cookie.</p>

        <h2>What we don’t add</h2>
        <p>The site code does not include analytics, ads, or third-party tracking scripts. Fonts are files on this site, not a font service.</p>

        <h2>Hosting</h2>
        <p>The site is hosted on HostM. Like most web hosts, their servers may keep ordinary access logs, such as an IP address, browser type, and the time of a visit. This site’s pages do not turn on a separate analytics log.</p>

        <h2>Asking about your information</h2>
        <p>You can ask what we have from a message you sent or from a name on the leaderboard, ask us to correct it, or ask us to delete it. Use the <a href="/#contact">contact form</a> or email <a href="mailto:<?= tylilo_e($contactEmail) ?>"><?= tylilo_e($contactEmail) ?></a>.</p>
        <p>If you are in the UK or the European Economic Area, you can make those requests about the message you sent us or a leaderboard name. If you are in California, you can ask us to tell you what we have from that message or name and to delete it. We do not sell personal information.</p>
        <p>This site is not directed at children under 13, and we do not knowingly collect information from them.</p>

        <h2>Changes</h2>
        <p>If this page changes, the new version will be posted here and the effective date above will change.</p>
      </article>
    </div>
  </main>
  <script type="application/ld+json" nonce="<?= tylilo_e($cspNonce) ?>">
    <?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => 'Privacy',
        'url' => TYLILO_URL . '/privacy',
        'description' => $pageDescription,
        'dateModified' => '2026-11-21',
        'isPartOf' => [
            '@type' => 'WebSite',
            'name' => TYLILO_SITE_NAME,
            'url' => TYLILO_URL . '/',
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => TYLILO_LEGAL_NAME,
            'email' => $contactEmail,
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR) ?>
  </script>
<?php require __DIR__ . '/includes/footer.php'; ?>
