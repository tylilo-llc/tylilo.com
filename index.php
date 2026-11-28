<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

/**
 * HOMEPAGE COPY
 * -------------
 * Consulting comes first. Software is second. The Arcade is a side note.
 * Testimonials are the array in includes/testimonials.php. Leave it empty
 * until a real quote exists. Messages go to TYLILO_CONTACT_EMAIL in config.php.
 * That address is not shown on the page.
 */
$pageTitle = 'Tylilo';
$pageDescription = 'Tylilo, LLC provides business consultation and software development.';
$pageCanonical = TYLILO_URL . '/';

require_once __DIR__ . '/includes/testimonials.php';
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
        <h1>Business consultation.</h1>
        <p class="lede">Tylilo, LLC provides business consultation, and also develops software.</p>
        <div class="hero-actions">
          <a class="btn" href="#consulting">Business consultation</a>
          <a class="btn btn-quiet" href="#contact">Contact</a>
        </div>
      </div>
    </section>

    <section class="band" id="consulting" aria-labelledby="consulting-heading">
      <div class="signal-inner">
        <p class="eyebrow">Consulting</p>
        <h2 id="consulting-heading">Business consultation</h2>
        <p class="lede-quiet">Tylilo, LLC provides business consultation.</p>
        <p><a class="btn" href="#contact">Contact</a></p>
      </div>
    </section>

    <section class="band band-soft" id="software" aria-labelledby="software-heading">
      <div class="signal-inner">
        <p class="eyebrow">Software</p>
        <h2 id="software-heading">Software development</h2>
        <p class="lede-quiet">Tylilo, LLC also develops software. Castmaker is one example.</p>
      </div>
    </section>

    <section class="band" id="about" aria-labelledby="about-heading">
      <div class="signal-inner narrow">
        <p class="eyebrow">The company</p>
        <h2 id="about-heading">About Tylilo, LLC</h2>
        <p>Tylilo, LLC provides business consultation and develops software.</p>
      </div>
    </section>

    <section class="band band-soft" id="testimonials" aria-labelledby="testimonials-heading">
      <div class="signal-inner narrow">
        <p class="eyebrow">Clients</p>
        <h2 id="testimonials-heading">Testimonials</h2>
        <?= tylilo_testimonials_html($testimonials) ?>
      </div>
    </section>

    <aside class="side-note">
      <div class="signal-inner">
        <p>The <a href="/arcade/">Arcade</a> is a side project.</p>
      </div>
    </aside>

    <div class="wrap">
      <section class="contact-block" id="contact" aria-labelledby="contact-heading">
        <div class="card">
          <p class="kicker">Contact</p>
          <h2 id="contact-heading">Drop us a line.</h2>
          <p class="muted">Send a message with the form. We read it and reply by email.</p>

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
      </section>
    </div>
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
