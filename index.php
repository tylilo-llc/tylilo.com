<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

/**
 * HOMEPAGE COPY
 * -------------
 * The tagline is set. The About heading and paragraph below are still
 * placeholders. The contact email is not here — change
 * TYLILO_CONTACT_EMAIL in config.php. That one constant updates the
 * email button and the form together.
 */
$pageTitle = 'Tylilo';
$tagline = 'Under Construction';
$aboutHeading = 'A friendly little studio.';
$aboutBody = 'A short description of Tylilo will live here — who you are, what you make, and why it matters. This is placeholder copy for a company or studio homepage.';

$pageDescription = $aboutBody;
$pageCanonical = TYLILO_URL . '/';

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
        <img src="/assets/img/logo-round.webp" alt="Tylilo logo" width="168" height="168" fetchpriority="high">
      </div>
      <h1 class="tagline"><?= tylilo_e($tagline) ?></h1>
      <a class="hero-cta" href="#contact">Say hello</a>
    </div>
  </header>

  <main id="main" tabindex="-1">
    <div class="wrap">
      <section class="card about-card" id="about" aria-labelledby="about-heading">
        <p class="kicker">About</p>
        <h2 id="about-heading"><?= tylilo_e($aboutHeading) ?></h2>
        <p class="muted"><?= tylilo_e($aboutBody) ?></p>
      </section>

      <section class="arcade-home" aria-labelledby="arcade-home-heading">
        <h2 id="arcade-home-heading" class="arcade-home-heading">Arcade</h2>
        <a class="arcade-tile" href="/arcade/">
          <svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" width="40" height="40">
            <path fill="currentColor" d="M8 7.4h8c2.55 0 4.35 1.85 4.15 4.35l-.35 2.7c-.28 1.7-1.75 2.95-3.45 2.95-1.05 0-2.05-.5-2.65-1.35L12.9 15h-1.8l-.8 1.05c-.6.85-1.6 1.35-2.65 1.35-1.7 0-3.17-1.25-3.45-2.95l-.35-2.7C3.65 9.25 5.45 7.4 8 7.4z"/>
            <path fill="var(--btn2-bg)" d="M7.15 10.55h1.2v1.15h1.15v1.2H8.35v1.15h-1.2V12.9H6v-1.2h1.15z"/>
            <circle cx="15.15" cy="11.35" r=".72" fill="var(--btn2-bg)"/>
            <circle cx="16.85" cy="12.9" r=".72" fill="var(--btn2-bg)"/>
            <circle cx="15.15" cy="14.45" r=".72" fill="var(--btn2-bg)"/>
            <circle cx="13.45" cy="12.9" r=".72" fill="var(--btn2-bg)"/>
          </svg>
          Arcade
        </a>
      </section>

      <section class="contact-block" id="contact" aria-labelledby="contact-heading">
        <div class="card">
          <p class="kicker">Contact</p>
          <h2 id="contact-heading">Drop us a line.</h2>
          <!-- Address comes from TYLILO_CONTACT_EMAIL in config.php -->
          <a class="email-btn" href="mailto:<?= tylilo_e($contactEmail) ?>"><?= tylilo_e($contactEmail) ?></a>

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
            <p>Please fix the highlighted fields and try again. You can also use the email button above.</p>
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
                <input id="email" name="email" type="email" autocomplete="email" inputmode="email" placeholder="you@example.com" required maxlength="254" value="<?= tylilo_e($oldEmail) ?>"<?php if (isset($errors['email'])): ?> aria-invalid="true" aria-describedby="email-error"<?php endif; ?>>
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
        'url' => TYLILO_URL . '/',
        'logo' => TYLILO_URL . '/assets/img/logo-round.webp',
        'email' => $contactEmail,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR) ?>
  </script>
<?php require __DIR__ . '/includes/footer.php'; ?>
