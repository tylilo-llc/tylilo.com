<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/inquiry.php';

$pageTitle = 'Terms — Tylilo';
$pageDescription = 'Terms for using tylilo.com and the general nature of the business consulting Tylilo, LLC provides.';
$pageCanonical = TYLILO_URL . '/terms';
$pageRobots = 'index, follow';

require __DIR__ . '/includes/head.php';
?>
<body>
  <a class="skip-link" href="#main">Skip to content</a>
  <header class="hero">
<?php require __DIR__ . '/includes/topbar.php'; ?>
  </header>

  <main id="main" tabindex="-1">
    <div class="wrap solo">
      <article class="card about-card prose">
        <p class="kicker">Terms</p>
        <h1>Terms</h1>
        <p><strong>Effective date:</strong> November 28, 2026</p>
        <p>Tylilo, LLC (“Tylilo”) runs <a href="/">tylilo.com</a>. By using this site, you agree to these terms.</p>

        <h2>Consulting</h2>
        <p><?= tylilo_e(TYLILO_INQUIRY_DISCLAIMER) ?></p>
        <p>Sending an inquiry does not create a client relationship. Any engagement is agreed separately, in writing, before work begins.</p>

        <h2>Using this site</h2>
        <p>The information on this site is general and provided as is. We work to keep it accurate, but we do not promise it is complete or current. Please do not misuse the site, try to break its security, or send automated submissions through its forms.</p>

        <h2>UDLR Arcade</h2>
        <p>The UDLR Arcade games are offered for fun, free of charge, and as is. Names saved to the leaderboard must not be offensive or impersonate someone, and we may remove any entry.</p>

        <h2>Your information</h2>
        <p>The <a href="/privacy">privacy policy</a> explains what the site collects and how it is used.</p>

        <h2>Changes</h2>
        <p>If these terms change, the new version will be posted here and the effective date above will change.</p>

        <h2>Questions</h2>
        <p>Use the <a href="/#contact">contact form</a>.</p>
      </article>
    </div>
  </main>
<?php require __DIR__ . '/includes/footer.php'; ?>
