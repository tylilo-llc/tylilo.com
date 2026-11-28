<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Privacy — Tylilo';
$pageDescription = 'What tylilo.com collects: the contact form, one session cookie, settings the browser keeps on your device, and names saved to the arcade leaderboard.';
$pageCanonical = TYLILO_URL . '/privacy';
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
        <p class="kicker">Privacy</p>
        <h1>Privacy</h1>
        <p><strong>Effective date:</strong> November 28, 2026</p>
        <p>Tylilo, LLC (“Tylilo”) runs <a href="/">tylilo.com</a>. This page says what the site actually does with information. It is short on purpose.</p>

        <h2>Contact form</h2>
        <p>If you write to us, the form asks for your name, email address, and message. We email those three things to ourselves so we can read them and reply. The site does not show our mailbox address. The site does not sell that information, and it does not send it to advertisers or other companies.</p>
        <p>We do not keep a database of messages. Your words sit in a temporary server session only long enough to show a thank-you or an error, and then that copy is removed. The same session holds a security token and the times of recent sends (five messages in 30 minutes) so the form is harder to spam. The site code does not write your message into its own log file. The email itself goes out through the web host’s mail system, which is how it reaches us.</p>
        <p>Before a message is accepted, the form asks Cloudflare Turnstile to tell a person from a bot. Cloudflare processes the visitor’s signals for that check, including the IP address, browser type, and the site the form was on. We do not use Turnstile for advertising. Cloudflare’s description of that processing is in the <a href="https://www.cloudflare.com/turnstile-privacy-policy/">Turnstile Privacy Addendum</a>.</p>

        <h2>Cookies</h2>
        <p>The homepage sets one cookie named <code>tylilo</code>. It is a session cookie: it lasts until you close the browser. It is HttpOnly, so page scripts cannot read it, and SameSite is Lax. When you visit over HTTPS, it is also marked Secure.</p>
        <p>Signing in at the private admin area uses that same cookie so the browser stays signed in. The cookie is necessary for the login. There is no public sign-up. A team account is created only when someone opens an invite from the owner.</p>
        <p>That cookie exists so the contact form can tell a real visit from a forged or repeated one. It is not a tracking cookie. The site does not set advertising cookies.</p>

        <h2>Team accounts</h2>
        <p>People who help run the site can sign in. There is no public sign-up. The owner can invite someone with a one-time link that works once and expires after 7 days. The owner can also make a one-time password reset link. We store a hash of that link, not the link itself. It expires after 24 hours. When it is used we mark the hash used, and when a newer link is created the older unused one is deleted. We store the username, a password hash, optional authenticator-app setup, the permissions the owner chose, private notes and to-dos, game feedback, ideas, comments, one vote per person on an idea, an optional sketch stored in private storage and shown only to signed-in people who can open Ideas, and an activity log of who did what and when. That stays in the site’s private storage. It is not shown on the public pages. The owner can see team members’ notes and to-dos. The owner’s own notes and to-dos stay private. Early games, labeled Alpha or Beta (today Crash Cars, Jumpy Jumperton and Starbase Defender, all Alpha), are listed on the Arcade under Alpha/Beta Games only when the owner chooses to show them, and the owner can show or hide each one at any time from the admin Alpha/Beta tab. A hidden early game is not publicly reachable. It can be opened only by signed-in people the owner allows. Feedback about an early game stays in that same private storage and is tagged with the game version. Feedback can include optional attached files, such as screenshots, short videos, PDFs, or logs. We store those files in the same private storage, along with the original file name. They are shown only to the person who sent the feedback and to the owner. The signed-in player for those games does not let them call this site, including the leaderboard, except to send a playtest report, described below.</p>

        <h2>Playtest reports</h2>
        <p>Signed-in people the owner has approved to play Alpha and Beta games can send a playtest report from inside a game. A report is one screen recording (WebM or MP4) with notes that the sender must write, and it carries the game version and the time on the sender’s device. Nobody else can send one, and each person can send up to 10 an hour. The recording is whatever the sender chose to record, so it may show anything that was on their screen at the time. Please keep personal information off the screen while recording.</p>
        <p>We keep the recording and notes in the site’s private storage. That storage is not reachable from the web. Alongside them we store the sender’s username, the time received, the file’s size, type and checksum, and a one-way hash of the network address, not the address itself, which is used only to spot abuse. The activity log records who sent a report, for which game, and when. Reports are shown only to the owner, in the admin Alpha/Beta tab. For review, a copy is also downloaded to a private working folder that Tylilo uses to look at reports, and nothing there is shared outside Tylilo. Reports on the site are deleted automatically after 90 days, and the owner can delete one sooner. A downloaded copy is deleted on request.</p>
        <p>To ask what we have from a report you sent, or to have it deleted, email <a href="mailto:ryan@tylilo.com">ryan@tylilo.com</a> or use the <a href="/#contact">contact form</a>.</p>

        <h2>Saved on your device</h2>
        <p>If you pick Light, Dark, or Auto, the browser stores that choice in local storage under <code>tylilo-theme</code>. It stays on your device. The site does not send it to us.</p>
        <p>If you close the Add to Home Screen note on the Arcade page, the browser stores that under <code>tyliloA2hsDismissed</code>. It stays on your device so the note stays closed. The site does not send it to us.</p>
        <p>Arcade games save their settings and high scores the same way, in this browser’s local storage. Red Rain stores music and mute, difficulty, options, and high scores, and the last name entered for the leaderboard under <code>redRainLbName</code>. Red Rain also stores <code>udlr_splash_seen_red-rain</code> = <code>1</code> once its intro splash has been shown (or, only if local storage is unavailable, a first-party cookie with the same name and value). Solitaire stores its draw mode under <code>klondike.draw</code>, its stats under <code>klondike.stats</code> (games played and won, streaks, best score and time for each draw mode, and whether a game is in progress; no personal data), sound on or off under <code>klondike.muted</code>, and the last name saved to the leaderboard under <code>klondike.lbName</code>. Solitaire also stores <code>udlr_splash_seen_solitaire</code> = <code>1</code> once its intro splash has been shown, so it plays only the first time. Pop Spiral stores a best score under <code>popSpiral.best</code>, its settings under <code>popSpiral.settings.v1</code>, and the last name saved to the leaderboard under <code>popSpiral.playerName</code>. That saved name fills in the name box the next time. Pop Spiral can submit a score to the shared leaderboard. Pop Spiral also stores <code>udlr_splash_seen_pop-spiral</code> = <code>1</code> once its intro splash has been shown. Only if local storage is unavailable, it writes a first-party cookie with the same name and value instead (one year, path /, SameSite=Lax), which records only that the splash was seen. Jungle Rescue stores options under <code>jungleRescueOpts</code>, difficulty (0–2) under <code>jungleRescueDiff</code>, an all-time high score under <code>jungleRescueHi</code>, best distance for each stage under <code>jungleRescueBestDist</code> and <code>jungleRescueBestDist2</code> through <code>jungleRescueBestDist20</code>, the highest unlocked stage under <code>jungleRescueUnlocked</code>, difficulties beaten under <code>jungleRescueBeat</code>, and collectible cards under <code>jungleRescueCards</code>. The last name used to save a score is stored under <code>jungleRescueName</code>. Options → Reset Progress clears the unlocked stage, the beaten difficulties, and the cards. Jungle Rescue also stores <code>udlr_splash_seen_jungle-rescue</code> = <code>1</code> once its intro splash has been shown. Only if local storage is unavailable, it writes a first-party cookie with the same name and value instead (one year, path /), which records only that the splash was seen. Jungle Rescue does not otherwise use session storage, cookies, or IndexedDB. Unfinished leaderboard saves stay on this device under <code>jungleRescueLbQueue</code>. Each entry holds the start token, the name, the score, how long that game ran, when the entry expires, and when to try again. It stays until the site accepts that save, rejects it, or the entry is 6 hours old. Options → Reset Progress does not clear that queue. The name, score, and play time from a queued save are sent only when a retry goes through, as described under Arcade leaderboard. The other on-device game settings are not sent to us. The games load no outside resources.</p>
        <p>Some early games on the Arcade, and unpublished early games, can also save on this device. Jumpy Jumperton stores a high score under <code>jjHi</code> and the on-screen control choice under <code>jjCtl</code>. It does not contact other sites. Starbase Defender does not save anything on the device and does not contact other sites. Crash Cars, an Alpha game listed under Alpha/Beta Games, stores its settings under <code>crashcars.settings.v1</code>. It does not write radio-station data, and it does not contact radio or stream services. Crash Cars still loads its 3D code from jsDelivr and two display fonts from Google Fonts. Those requests go to those services, not to Tylilo. None of these saves are shown on the public pages.</p>

        <h2>Arcade leaderboard</h2>
        <p>The arcade can also keep a shared list of top scores on this site. That is separate from the on-device scores above. When a game submits a score, the browser sends the name you choose, the score, the game, the board, and how long that game ran to <code>/api/scores.php</code> on tylilo.com. We store the name, score, game, board, and the time it was saved so anyone can read the top scores on the Arcade page. We do not ask for an email address or create an account for this.</p>
        <p>To slow down repeated submissions, we store a hash of the network address with the time of the save, not the address itself. That hash cannot be turned back into the address, and we delete it after it is no longer needed for the limit. We also store a hash of a single-use start token so the same game cannot be saved twice. The leaderboard does not set a cookie.</p>

        <h2>What we don’t add</h2>
        <p>The site code does not include analytics, ads, or third-party tracking scripts. The contact form loads Cloudflare Turnstile, described above, only to tell people from bots. Fonts are files on this site, not a font service.</p>

        <h2>Hosting</h2>
        <p>The site is hosted on HostM. Like most web hosts, their servers may keep ordinary access logs, such as an IP address, browser type, and the time of a visit. This site’s pages do not turn on a separate analytics log.</p>

        <h2>Asking about your information</h2>
        <p>You can ask what we have from a message you sent or from a name on the leaderboard, ask us to correct it, or ask us to delete it. Use the <a href="/#contact">contact form</a>.</p>
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
        'dateModified' => '2026-11-28',
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
