<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

/**
 * HOMEPAGE COPY
 * -------------
 * Consulting comes first. Software is second. The UDLR Arcade lives in the footer only.
 * Testimonials are the array in includes/testimonials.php. Leave it empty
 * until a real quote exists. Messages go to TYLILO_CONTACT_EMAIL in config.php.
 * That address is not shown on the page.
 */
$pageTitle = 'Tylilo, LLC — Business consulting and software';
$pageDescription = 'Practical business consulting for small businesses, new ventures, and mission-driven organizations, plus software development. Remote everywhere; in person in East Tennessee.';
$pageCanonical = TYLILO_URL . '/';

require_once __DIR__ . '/includes/testimonials.php';
require_once __DIR__ . '/includes/inquiry.php';
$testimonials = tylilo_testimonials();

tylilo_session_start();
$flash = $_SESSION['contact_flash'] ?? null;
unset($_SESSION['contact_flash']);
if (!is_array($flash)) {
    $flash = [];
}
$errors = is_array($flash['errors'] ?? null) ? $flash['errors'] : [];
$old = is_array($flash['old'] ?? null) ? $flash['old'] : [];
$success = !empty($flash['success']);
$oldName = is_string($old['name'] ?? null) ? $old['name'] : '';
$oldEmail = is_string($old['email'] ?? null) ? $old['email'] : '';
$oldMessage = is_string($old['message'] ?? null) ? $old['message'] : '';
$csrf = tylilo_csrf_token();
require_once __DIR__ . '/includes/turnstile.php';
$turnstileSitekey = tylilo_turnstile_sitekey();

require __DIR__ . '/includes/head.php';
?>
<body>
  <a class="skip-link" href="#main">Skip to content</a>
  <header class="hero">
<?php require __DIR__ . '/includes/topbar.php'; ?>
  </header>

  <main id="main" tabindex="-1">
    <section class="signal-hero">
      <div class="signal-inner">
        <p class="eyebrow">Tylilo, LLC</p>
        <h1>Practical business consulting for owners and leaders.</h1>
        <p class="lede">Clear thinking, a written plan, and steady help putting it to work. For small businesses, new ventures, and mission-driven organizations.</p>
        <div class="hero-actions">
          <a class="btn" href="/inquiry">Start an inquiry</a>
          <a class="btn btn-quiet" href="#consulting">How it works</a>
        </div>
      </div>
    </section>

    <section class="band" id="consulting" aria-labelledby="consulting-heading">
      <div class="signal-inner">
        <p class="eyebrow">Consulting</p>
        <h2 id="consulting-heading">Business consulting</h2>
        <p class="lede-quiet">Tylilo, LLC helps owners find what matters most right now and make steady progress on it, in plain language, with plans you can keep.</p>
        <h3 class="band-subhead">Ways to work together</h3>
        <ul class="offer-grid">
          <li class="offer">
            <h4>A one-time diagnosis</h4>
            <p>A focused look at where things stand, ending in a written 90-day plan.</p>
          </li>
          <li class="offer">
            <h4>Work alongside you for a few months</h4>
            <p>One or two priorities over 8 to 12 weeks, with weekly check-ins, and processes and roles written down.</p>
          </li>
          <li class="offer">
            <h4>An ongoing advisor</h4>
            <p>A monthly planning note, decision support by email, and a quarterly check-in.</p>
          </li>
        </ul>
        <h3 class="band-subhead">How it starts</h3>
        <ol class="steps">
          <li><strong>Answer a few short questions.</strong> It takes about five minutes.</li>
          <li><strong>Get a personal reply.</strong> Ryan reads every inquiry and replies by email within one business day.</li>
          <li><strong>Agree on a starting point.</strong> If it is not a good fit, we will point you to someone who can help.</li>
        </ol>
        <p class="band-note">Everything we offer works remotely, wherever you are. At this time, in-person visits are available in East Tennessee.</p>
        <p class="band-cta"><a class="btn" href="/inquiry">Start an inquiry</a></p>
        <p class="disclaimer"><?= tylilo_e(TYLILO_INQUIRY_DISCLAIMER) ?></p>
      </div>
    </section>

    <section class="band band-soft" id="software" aria-labelledby="software-heading">
      <div class="signal-inner">
        <p class="eyebrow">Software</p>
        <h2 id="software-heading">Software development</h2>
        <p class="lede-quiet">Tylilo, LLC also builds software: small, dependable tools made for a specific job. Castmaker is one example.</p>
      </div>
    </section>

    <section class="band" id="about" aria-labelledby="about-heading">
      <div class="signal-inner">
        <p class="eyebrow">The company</p>
        <h2 id="about-heading">About Tylilo, LLC</h2>
        <p class="lede-quiet">Tylilo, LLC is a consulting and software company led by Ryan Thorpe. Work is done personally and explained plainly, and every engagement ends with something written down that you keep.</p>
      </div>
    </section>

<?php if (tylilo_testimonials_html($testimonials) !== tylilo_testimonials_html([])): ?>
    <section class="band band-soft" id="testimonials" aria-labelledby="testimonials-heading">
      <div class="signal-inner">
        <p class="eyebrow">Clients</p>
        <h2 id="testimonials-heading">What clients say</h2>
        <?= tylilo_testimonials_html($testimonials) ?>
      </div>
    </section>

<?php endif; ?>
    <section class="band band-soft" id="contact" aria-labelledby="contact-heading">
      <div class="signal-inner">
        <p class="eyebrow">Contact</p>
        <h2 id="contact-heading">A general question?</h2>
        <p class="lede-quiet">For consulting, the <a href="/inquiry">inquiry form</a> is the quickest way to start. For anything else, send a short message and we will reply by email.</p>
        <div class="contact-form-wrap">

<?php if ($success): ?>
          <div class="form-status success" id="form-status" tabindex="-1" role="status" aria-live="polite" aria-atomic="true">
            <p>Thanks. Your message is on its way.</p>
          </div>
<?php elseif (!empty($errors['form'])): ?>
          <div class="form-status error" id="form-status" tabindex="-1" role="alert" aria-live="assertive" aria-atomic="true">
            <p><?= tylilo_e((string) $errors['form']) ?></p>
          </div>
<?php elseif ($errors !== []): ?>
          <div class="form-status error" id="form-status" tabindex="-1" role="alert" aria-live="assertive" aria-atomic="true">
            <p>Please fix the highlighted fields and try again.</p>
          </div>
<?php endif; ?>

          <form action="/contact.php" method="post" data-contact-form accept-charset="UTF-8" autocomplete="on">
            <div class="hp" aria-hidden="true">
              <label for="ty-extra">Leave this field empty</label>
              <input id="ty-extra" type="text" name="ty_extra" tabindex="-1" autocomplete="off" aria-hidden="true" value="">
            </div>
            <input type="hidden" name="csrf" value="<?= tylilo_e($csrf) ?>">
            <div class="row">
              <div>
                <label for="name">Name <span class="visually-hidden">(required)</span></label>
                <input id="name" name="name" type="text" autocomplete="name" placeholder="Your name" required maxlength="100" value="<?= tylilo_e($oldName) ?>"<?php if (isset($errors['name'])): ?> aria-invalid="true" aria-describedby="name-error"<?php endif; ?>>
<?php if (isset($errors['name'])): ?>
                <p class="field-error" id="name-error"><?= tylilo_e((string) $errors['name']) ?></p>
<?php endif; ?>
              </div>
              <div>
                <label for="email">Email <span class="visually-hidden">(required)</span></label>
                <input id="email" name="email" type="email" autocomplete="email" inputmode="email" placeholder="Your email" required maxlength="254" value="<?= tylilo_e($oldEmail) ?>"<?php if (isset($errors['email'])): ?> aria-invalid="true" aria-describedby="email-error"<?php endif; ?>>
<?php if (isset($errors['email'])): ?>
                <p class="field-error" id="email-error"><?= tylilo_e((string) $errors['email']) ?></p>
<?php endif; ?>
              </div>
            </div>
            <div>
              <label for="message">Message <span class="visually-hidden">(required)</span></label>
              <textarea id="message" name="message" placeholder="A short message" required maxlength="5000" enterkeyhint="send"<?php if (isset($errors['message'])): ?> aria-invalid="true" aria-describedby="message-error"<?php endif; ?>><?= tylilo_e($oldMessage) ?></textarea>
<?php if (isset($errors['message'])): ?>
              <p class="field-error" id="message-error"><?= tylilo_e((string) $errors['message']) ?></p>
<?php endif; ?>
            </div>
            <div class="turnstile-slot">
              <div class="cf-turnstile" data-contact-turnstile data-sitekey="<?= tylilo_e($turnstileSitekey) ?>" data-action="contact" data-appearance="interaction-only" data-size="flexible" data-theme="auto"></div>
            </div>
            <button class="btn send" type="submit">Send message</button>
          </form>
        </div>
      </div>
    </section>

  </main>
  <script type="application/ld+json" nonce="<?= tylilo_e($cspNonce) ?>">
    <?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => TYLILO_LEGAL_NAME,
        'description' => $pageDescription,
        'url' => TYLILO_URL . '/',
        'logo' => TYLILO_URL . '/resources/Tylilo-Logo/Tylilo-Logo-Small.png',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR) ?>
  </script>
<?php
$pageTurnstile = true;
require __DIR__ . '/includes/footer.php';
?>
