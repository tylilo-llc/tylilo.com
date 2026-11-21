<?php

declare(strict_types=1);

http_response_code(404);

require __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Page not found — Tylilo';
$pageDescription = 'That page is not on the Tylilo site.';
$pageRobots = 'noindex, follow';

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
      <section class="card about-card">
        <p class="kicker">404</p>
        <h1>Page not found.</h1>
        <p class="muted">That link doesn't go anywhere. It may have moved, or the address might be mistyped.</p>
        <a class="btn" href="/">Back to the homepage</a>
      </section>
    </div>
  </main>
<?php require __DIR__ . '/includes/footer.php'; ?>
