<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Privacy — Tylilo';
$pageDescription = 'What tylilo.com collects: the contact form, one session cookie, a theme setting saved on your device, and OTRadio files loaded from Cloudflare R2.';
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
        <p>Tylilo LLC (“Tylilo”) runs <a href="/">tylilo.com</a>. This page says what the site actually does with information. It is short on purpose.</p>

        <h2>Contact form</h2>
        <p>If you write to us, the form asks for your name, email address, and message. We email those three things to <a href="mailto:<?= tylilo_e($contactEmail) ?>"><?= tylilo_e($contactEmail) ?></a> so we can read them and reply. The site does not sell that information, and it does not send it to advertisers or other companies.</p>
        <p>We do not keep a database of messages. Your words sit in a temporary server session only long enough to show a thank-you or an error, and then that copy is removed. The same session holds a security token and the times of recent sends (five messages in 30 minutes) so the form is harder to spam. The site code does not write your message into its own log file. The email itself goes out through the web host’s mail system, which is how it reaches us.</p>

        <h2>Cookies</h2>
        <p>The homepage sets one cookie named <code>tylilo</code>. It is a session cookie: it lasts until you close the browser. It is HttpOnly, so page scripts cannot read it, and SameSite is Lax. When you visit over HTTPS, it is also marked Secure.</p>
        <p>That cookie exists so the contact form can tell a real visit from a forged or repeated one. It is not a tracking cookie. The site does not set advertising cookies.</p>

        <h2>Saved on your device</h2>
        <p>If you pick Light, Dark, or Auto, the browser stores that choice in local storage under <code>tylilo-theme</code>. It stays on your device. The site does not send it to us.</p>
        <p>On the <a href="/otradio/">OTRadio</a> page, the browser can also remember whether captions are on, your playback speed, and how far you got in an episode. Those entries stay on your device too. Their names start with <code>otradio:</code>.</p>

        <h2>OTRadio files</h2>
        <p>OTRadio asks a Cloudflare R2 address, <code>pub-a3eee908554d4a948d807c160caa454e.r2.dev</code>, for its catalog. Audio, captions, and artwork are loaded from that same host, unless a catalog entry already contains a full web address. A request there can include your IP address, the same way any web request does. The rest of tylilo.com does not load that host.</p>

        <h2>What we don’t add</h2>
        <p>The site code does not include analytics, ads, or third-party tracking scripts. Fonts are files on this site, not a font service.</p>

        <h2>Hosting</h2>
        <p>The site is hosted on HostM. Like most web hosts, their servers may keep ordinary access logs, such as an IP address, browser type, and the time of a visit. This site’s pages do not turn on a separate analytics log.</p>

        <h2>Asking about your information</h2>
        <p>You can ask what we have from a message you sent, ask us to correct it, or ask us to delete it. Use the <a href="/#contact">contact form</a> or email <a href="mailto:<?= tylilo_e($contactEmail) ?>"><?= tylilo_e($contactEmail) ?></a>.</p>
        <p>If you are in the UK or the European Economic Area, you can make those requests about the message you sent us. If you are in California, you can ask us to tell you what we have from that message and to delete it. We do not sell personal information.</p>
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
