<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Privacy — Tylilo';
$pageDescription = 'What tylilo.com collects: the contact and inquiry forms, one session cookie, settings the browser keeps on your device, and names saved to the arcade leaderboard.';
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
        <p><strong>Effective date:</strong> November 28, 2026<br><strong>Last updated:</strong> November 28, 2026</p>
        <p>Tylilo, LLC (“Tylilo”) runs <a href="/">tylilo.com</a>. This page says what the site actually does with information. It is short on purpose.</p>

        <h2 id="summary">What we collect and who receives it</h2>
        <p>These are the kinds of personal information the site collects, and who else receives them. Details for each are further down this page.</p>
        <ul>
          <li><strong>Contact form:</strong> your name, email address, and message. Sent by email to us through our email provider.</li>
          <li><strong>Consulting inquiry form:</strong> your name, email address, and the answers you choose to give about your business, such as its name, website, general location, stage, size, and goals. Kept in our private storage and sent by email to us and to you through our email provider.</li>
          <li><strong>Team accounts:</strong> a username, a password hash, and the notes and activity of people the owner invites. Kept in our private storage.</li>
          <li><strong>Arcade leaderboard:</strong> the name you choose and your score, plus a hash of your network address used only to limit repeats.</li>
          <li><strong>Spam checks:</strong> Cloudflare Turnstile receives signals such as your IP address and browser type to tell people from bots.</li>
        </ul>
        <p>We share personal information only with the service providers that run the site for us: our web host (HostM), our email provider, and Cloudflare for Turnstile. We do not sell it, and we do not share it with advertisers or data brokers.</p>

        <h2 id="storage">Cookies and local storage</h2>
        <p>In short: the site sets no advertising or tracking cookies. It uses only first-party storage that the site needs or that remembers your own choices, and Cloudflare Turnstile on the forms.</p>
        <ul>
          <li><strong>Essential:</strong> one session cookie, <code>tylilo</code>, that protects the forms and keeps the admin sign-in. It ends when you close the browser.</li>
          <li><strong>Preferences:</strong> your theme choice, and arcade settings and high scores, saved in this browser’s local storage. They stay on your device.</li>
          <li><strong>Spam check:</strong> Cloudflare Turnstile runs on the forms and may use its own storage to tell people from bots. We do not use it for advertising.</li>
        </ul>
        <p>The sections below list every key and what it holds.</p>

        <h2 id="dnt">Do Not Track</h2>
        <p>Some browsers send a “Do Not Track” signal. This site does not track you across other websites or over time, so that signal does not change what it does. We do not allow third parties to collect information about your browsing on this site for advertising or behavioral tracking, and the site has no analytics or ad scripts.</p>

        <h2>Contact form</h2>
        <p>If you write to us, the form asks for your name, email address, and message. We email those three things to ourselves so we can read them and reply. The site does not show our mailbox address. The site does not sell that information, and it does not send it to advertisers or other companies.</p>
        <p>We do not keep a database of messages. Your words sit in a temporary server session only long enough to show a thank-you or an error, and then that copy is removed. The same session holds a security token and the times of recent sends (five messages in 30 minutes) so the form is harder to spam. The site code does not write your message into its own log file. The email itself goes out through the web host’s mail system, which is how it reaches us.</p>
        <p>Before a message is accepted, the form asks Cloudflare Turnstile to tell a person from a bot. Cloudflare processes the visitor’s signals for that check, including the IP address, browser type, and the site the form was on. We do not use Turnstile for advertising. Cloudflare’s description of that processing is in the <a href="https://www.cloudflare.com/turnstile-privacy-policy/">Turnstile Privacy Addendum</a>.</p>

        <h2 id="inquiries">Consulting inquiries</h2>
        <p>The <a href="/inquiry">inquiry form</a> asks about your business or organization so we can tell whether and how we can help: its stage, your goals, a few follow-up questions, its size and type, your timing, the kind of help you want, and your general location. It also asks for your name and email address, and, if you choose, a business name, a website or social link, how you heard about us, and anything else you want to add. It does not ask for financial account numbers, government ID numbers, or other sensitive details. Please do not include them.</p>
        <p><strong>What we collect and why.</strong> We collect only what you type into the form: your name, email address, and the answers described above. We use it to respond to you, to prepare for and provide consulting if you decide to work with us, and to keep a record of our conversations. When you send the form, we email you a copy of your answers and email a notice to Ryan Thorpe at Tylilo, LLC. Those emails are sent from our own mailbox through our email provider.</p>
        <p><strong>A private record about you.</strong> We keep your answers in the site’s private storage, which is not public and is open only to the owner and to team members the owner has allowed. If you write to us more than once, the answers are grouped into one private profile for you, matched by email address. That profile is built only from what you send us and from our own notes about our conversations and where things stand. We do not look you up elsewhere, buy information about you, scrape websites or social profiles, or track you around the web.</p>
        <p><strong>Who sees it.</strong> We do not sell your information, and we do not share it with anyone except the service providers that help us run this form: our web host, our email provider, and Cloudflare, which runs the Turnstile spam check described above. They handle it only to provide those services.</p>
        <p><strong>How long we keep it.</strong> We keep your inquiry and profile while we are in contact and for up to 3 years after our last contact. After that we delete them. If you become a client, we keep the records for as long as we need them for the work and for our legal, tax, and accounting obligations.</p>
        <p><strong>Your choices.</strong> You can ask to see what we have about you, to correct it, or to delete it at any time by emailing <a href="mailto:ryan@tylilo.com">ryan@tylilo.com</a>. We will send you a copy or delete your profile, your inquiries, and our notes about you, except records we must keep by law. Deleting your profile does not delete copies in emails you or we already sent. If we take a single inquiry out of our records, we keep your profile and our notes unless you have asked us to delete everything, or the 3-year period above has ended.</p>
        <p><strong>Children.</strong> The inquiry form is for business owners and leaders. It is not for anyone under 16, and we do not knowingly collect information from children under 13 or 16. If you believe a child has sent us information, please email us and we will delete it.</p>
        <p><strong>Security.</strong> The form and the admin area use HTTPS. Inquiries and notes are stored in private files outside the public website, and only people the owner has allowed can open them. No system is perfectly secure, so please do not include sensitive details such as account numbers or government ID numbers.</p>
        <p>To limit spam, we keep a keyed hash of your network address for one hour, not the address itself, and the same session cookie described below holds a security token and the times of recent sends. Before an inquiry is accepted, Cloudflare Turnstile checks that a person sent it, as described for the contact form above.</p>
        <p>If you start an inquiry and leave before sending it, this browser may keep a draft of your answers in session storage under <code>tyliloInquiryDraft</code>. The draft does not include your name or email address. It stays on this device, is used only to put your answers back in the form if you return, and is deleted when you send the inquiry or close the tab. The site does not send that draft to us.</p>

        <h2>Cookies</h2>
        <p>The homepage sets one cookie named <code>tylilo</code>. It is a session cookie: it lasts until you close the browser. It is HttpOnly, so page scripts cannot read it, and SameSite is Lax. When you visit over HTTPS, it is also marked Secure.</p>
        <p>Signing in at the private admin area uses that same cookie so the browser stays signed in. The cookie is necessary for the login. There is no public sign-up. A team account is created only when someone opens an invite from the owner.</p>
        <p>The inquiry form uses that same cookie in the same way. That cookie exists so the contact form can tell a real visit from a forged or repeated one. It is not a tracking cookie. The site does not set advertising cookies.</p>

        <h2>Team accounts</h2>
        <p>People who help run the site can sign in. There is no public sign-up. The owner can invite someone with a one-time link that works once and expires after 7 days. The owner can also make a one-time password reset link. We store a hash of that link, not the link itself. It expires after 24 hours. When it is used we mark the hash used, and when a newer link is created the older unused one is deleted. We store the username, a password hash, optional authenticator-app setup, the permissions the owner chose, private notes and to-dos, game feedback, ideas, comments, one vote per person on an idea, an optional sketch stored in private storage and shown only to signed-in people who can open Ideas, and an activity log of who did what and when. That stays in the site’s private storage. It is not shown on the public pages. The owner can see team members’ notes and to-dos. The owner’s own notes and to-dos stay private. Unpublished beta games are only opened by signed-in people the owner allows. They are not on the public Arcade. Feedback about a beta game stays in that same private storage and is tagged with the beta version. Feedback can include optional attached files, such as screenshots, short videos, PDFs, or logs. We store those files in the same private storage, along with the original file name. They are shown only to the person who sent the feedback and to the owner. The beta player does not let those games call this site, including the leaderboard.</p>

        <h2>Saved on your device</h2>
        <p>The theme button stores Auto, Light, or Dark in local storage under <code>tylilo-theme</code>. Auto follows the color scheme on your device. It stays on your device. The site does not send it to us.</p>
        <p>If you close the Add to Home Screen note on the Arcade page, the browser stores that under <code>tyliloA2hsDismissed</code>. It stays on your device so the note stays closed. The site does not send it to us.</p>
        <p>Arcade games save their settings and high scores the same way, in this browser’s local storage. Red Rain stores music and mute, difficulty, options, and high scores, and the last name entered for the leaderboard under <code>redRainLbName</code>. Red Rain also stores <code>udlr_splash_seen_red-rain</code> = <code>1</code> once its intro splash has been shown (or, only if local storage is unavailable, a first-party cookie with the same name and value). Solitaire stores its draw mode under <code>klondike.draw</code>, its stats under <code>klondike.stats</code> (games played and won, streaks, best score and time for each draw mode, and whether a game is in progress; no personal data), sound on or off under <code>klondike.muted</code>, and the last name saved to the leaderboard under <code>klondike.lbName</code>. Solitaire also stores <code>udlr_splash_seen_solitaire</code> = <code>1</code> once its intro splash has been shown, so it plays only the first time. Pop Spiral stores a best score under <code>popSpiral.best</code>, its settings under <code>popSpiral.settings.v1</code>, and the last name saved to the leaderboard under <code>popSpiral.playerName</code>. That saved name fills in the name box the next time. Pop Spiral can submit a score to the shared leaderboard. Jungle Rescue stores options under <code>jungleRescueOpts</code>, difficulty (0–2) under <code>jungleRescueDiff</code>, an all-time high score under <code>jungleRescueHi</code>, best distance for each stage under <code>jungleRescueBestDist</code> and <code>jungleRescueBestDist2</code> through <code>jungleRescueBestDist20</code>, the highest unlocked stage under <code>jungleRescueUnlocked</code>, difficulties beaten under <code>jungleRescueBeat</code>, and collectible cards under <code>jungleRescueCards</code>. The last name used to save a score is stored under <code>jungleRescueName</code>. Options → Reset Progress clears the unlocked stage, the beaten difficulties, and the cards. Jungle Rescue also stores <code>udlr_splash_seen_jungle-rescue</code> = <code>1</code> once its intro splash has been shown. Only if local storage is unavailable, it writes a first-party cookie with the same name and value instead (one year, path /), which records only that the splash was seen. Jungle Rescue does not otherwise use session storage, cookies, or IndexedDB. Unfinished leaderboard saves stay on this device under <code>jungleRescueLbQueue</code>. Each entry holds the start token, the name, the score, how long that game ran, when the entry expires, and when to try again. It stays until the site accepts that save, rejects it, or the entry is 6 hours old. Options → Reset Progress does not clear that queue. The name, score, and play time from a queued save are sent only when a retry goes through, as described under Arcade leaderboard. The other on-device game settings are not sent to us. The games load no outside resources.</p>
        <p>Beta games can also save on this device. Jumpy Jumperton stores a high score under <code>jjHi</code> and the on-screen control choice under <code>jjCtl</code>. It does not contact other sites. Starbase Defender does not save anything on the device and does not contact other sites. Crash Cars stores its settings under <code>crashcars.settings.v1</code> and the last radio station under <code>crashcars.radio.v1</code> (the station id and the stream address). When its radio is used, Crash Cars asks TuneIn (<code>opml.radiotime.com</code> and <code>feed.tunein.com</code>) for station lists and now-playing details, loads station art from TuneIn, and may ask Triton Digital (<code>np.tritondigital.com</code>) or Live365 (<code>api.live365.com</code>) what is playing. The audio stream then comes from the station. Crash Cars also loads its 3D code and an HLS helper from jsDelivr and two display fonts from Google Fonts. Those requests go to those services, not to Tylilo. None of the beta saves are shown on the public pages.</p>

        <h2>Arcade leaderboard</h2>
        <p>The arcade can also keep a shared list of top scores on this site. That is separate from the on-device scores above. When a game submits a score, the browser sends the name you choose, the score, the game, the board, and how long that game ran to <code>/api/scores.php</code> on tylilo.com. We store the name, score, game, board, and the time it was saved so anyone can read the top scores on the Arcade page. We do not ask for an email address or create an account for this.</p>
        <p>To slow down repeated submissions, we store a hash of the network address with the time of the save, not the address itself. That hash cannot be turned back into the address, and we delete it after it is no longer needed for the limit. We also store a hash of a single-use start token so the same game cannot be saved twice. The leaderboard does not set a cookie.</p>

        <h2>What we don’t add</h2>
        <p>The site code does not include analytics, ads, or third-party tracking scripts. The contact form and the inquiry form load Cloudflare Turnstile, described above, only to tell people from bots. Fonts are files on this site, not a font service.</p>

        <h2>Hosting</h2>
        <p>The site is hosted on HostM. Like most web hosts, their servers may keep ordinary access logs, such as an IP address, browser type, and the time of a visit. This site’s pages do not turn on a separate analytics log.</p>

        <h2 id="review">Reviewing or changing your information</h2>
        <p>You can ask what we have from a message or inquiry you sent or from a name on the leaderboard, ask us to correct it, or ask us to delete it. Email <a href="mailto:ryan@tylilo.com">ryan@tylilo.com</a> or use the <a href="/#contact">contact form</a>. We may ask you to confirm the email address you used so we give the information only to you.</p>
        <p>Anyone can make these requests, wherever they live. We do not sell personal information.</p>
        <p>This site is not directed at children under 13, and we do not knowingly collect information from them.</p>

        <h2>Changes</h2>
        <p>If this page changes, the new version will be posted here, the effective date and “last updated” date at the top will change, and we will add a note below saying what changed. If a change is significant, we will also put a notice on the site’s home page or the inquiry form for a while.</p>
        <p><strong>November 28, 2026:</strong> added how we keep and delete consulting inquiry answers, the private record we keep about people who send inquiries, how we respond to Do Not Track, and a summary of cookies and local storage. Later the same day: clarified that removing one inquiry from our records does not, by itself, delete your profile or our notes. A request from you still deletes both.</p>
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
