<?php

declare(strict_types=1);

if (!defined('TYLILO_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}
?>
  <footer class="site-footer">
<?php if (!tylilo_request_is_admin()): ?>
    <div class="footer-pro">
      <div class="footer-brand">
        <p class="footer-name"><?= tylilo_e(TYLILO_LEGAL_NAME) ?></p>
        <p>Business consultation and software development.</p>
      </div>
      <nav aria-label="Explore">
        <p class="footer-label">Explore</p>
        <a href="/#consulting">Consulting</a>
        <a href="/#software">Software</a>
        <a href="/#about">About</a>
        <a href="/#contact">Contact</a>
        <a href="/arcade/"<?php if (($pageCanonical ?? '') === TYLILO_URL . '/arcade/'): ?> aria-current="page"<?php endif; ?>>Arcade</a>
      </nav>
      <nav aria-label="Company">
        <p class="footer-label">Company</p>
<?php if (TYLILO_OTRADIO_ENABLED): ?>
        <a href="/otradio/"<?php if (($pageCanonical ?? '') === TYLILO_URL . '/otradio/'): ?> aria-current="page"<?php endif; ?>>OTRadio</a>
<?php endif; ?>
        <a href="/accessibility"<?php if (($pageCanonical ?? '') === TYLILO_URL . '/accessibility'): ?> aria-current="page"<?php endif; ?>>Accessibility</a>
        <a href="/privacy"<?php if (($pageCanonical ?? '') === TYLILO_URL . '/privacy'): ?> aria-current="page"<?php endif; ?>>Privacy</a>
      </nav>
    </div>
    <p class="legal">© <?= tylilo_e(TYLILO_YEAR) ?> <?= tylilo_e(TYLILO_LEGAL_NAME) ?><a class="legal-sign" href="/admin/" rel="nofollow">Sign in</a></p>
<?php else: ?>
    <nav aria-label="Footer">
      <a href="/arcade/">Arcade</a>
      <a href="/accessibility">Accessibility</a>
      <a href="/privacy">Privacy</a>
    </nav>
    <p>© <?= tylilo_e(TYLILO_YEAR) ?> <?= tylilo_e(TYLILO_LEGAL_NAME) ?></p>
<?php endif; ?>
  </footer>
<?php if (!empty($pageA2hs)): ?>
  <script src="<?= tylilo_e(tylilo_asset('/assets/js/a2hs.js')) ?>" defer></script>
<?php endif; ?>
  <script src="<?= tylilo_e(tylilo_asset('/assets/js/theme.js')) ?>" defer></script>
  <script src="<?= tylilo_e(tylilo_asset('/assets/js/form.js')) ?>" defer></script>
<?php if (!empty($pageTurnstile)): ?>
  <script src="<?= tylilo_e(tylilo_asset('/assets/js/turnstile.js')) ?>" defer></script>
  <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&amp;onload=tyliloTurnstile" defer></script>
<?php endif; ?>
  <script nonce="<?= tylilo_e($cspNonce) ?>">
    (function () {
      if (window.HTMLScriptElement && HTMLScriptElement.supports && HTMLScriptElement.supports("speculationrules")) return;
      var seen = Object.create(null);
      function prefetch(a) {
        var url;
        try { url = new URL(a.href, location.href); } catch (e) { return; }
        if (url.origin !== location.origin) return;
        if (url.pathname === location.pathname && url.search === location.search) return;
        var href = url.pathname + url.search;
        if (seen[href]) return;
        seen[href] = 1;
        var link = document.createElement("link");
        link.rel = "prefetch";
        link.as = "document";
        link.href = href;
        document.head.appendChild(link);
      }
      function fromEvent(e) {
        var node = e.target;
        var a = node && node.closest ? node.closest("a[href]") : null;
        if (a) prefetch(a);
      }
      document.addEventListener("mouseover", fromEvent, true);
      document.addEventListener("touchstart", fromEvent, true);
    })();
  </script>
</body>
</html>
