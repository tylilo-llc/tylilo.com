<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Accessibility — Tylilo';
$pageDescription = 'Tylilo, LLC aims to meet WCAG 2.2 Level AA on tylilo.com. Report a barrier through the contact form or by email.';
$pageCanonical = TYLILO_URL . '/accessibility';
$pageRobots = 'index, follow';

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
        <p class="kicker">Access</p>
        <h1>Accessibility</h1>
        <p>Tylilo, LLC wants tylilo.com to work for everyone. We aim to meet the <a href="https://www.w3.org/TR/WCAG22/">Web Content Accessibility Guidelines (WCAG) 2.2</a>, Level AA.</p>
        <h2>Report a problem</h2>
        <p>If a page, form, or control gets in your way, please tell us. Use the <a href="/#contact">contact form</a> on the homepage.</p>
        <h2>What we pay attention to</h2>
        <ul>
          <li>Pages can be reached and used with a keyboard.</li>
          <li>Focus is visible on links, buttons, and fields.</li>
          <li>Text and controls keep enough contrast in light and dark.</li>
          <li>Animation is reduced when your device asks for that.</li>
        </ul>
        <h2>Games in the UDLR Arcade</h2>
        <p>The UDLR Arcade pages, menus, and leaderboards follow the same standard as the rest of the site. The games themselves are different. Many depend on fast reactions, precise timing, color, motion, and sound, and some of those can't change without changing the game.</p>
        <p>In our UDLR games, we put playability first. We add accessibility features where they fit, like keyboard controls, pause, and sound toggles, but we won't add one if it would make the game worse to play. If something in a game gets in your way, please tell us anyway. We read every note.</p>
      </article>
    </div>
  </main>
  <script type="application/ld+json" nonce="<?= tylilo_e($cspNonce) ?>">
    <?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => 'Accessibility',
        'url' => TYLILO_URL . '/accessibility',
        'description' => $pageDescription,
        'isPartOf' => [
            '@type' => 'WebSite',
            'name' => TYLILO_SITE_NAME,
            'url' => TYLILO_URL . '/',
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => TYLILO_LEGAL_NAME,
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR) ?>
  </script>
<?php require __DIR__ . '/includes/footer.php'; ?>
