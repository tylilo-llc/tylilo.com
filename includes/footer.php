<?php

declare(strict_types=1);

if (!defined('TYLILO_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}
?>
  <footer class="site-footer">
    <nav aria-label="Footer">
<?php if (TYLILO_OTRADIO_ENABLED): ?>
      <a href="/otradio/"<?php if (($pageCanonical ?? '') === TYLILO_URL . '/otradio/'): ?> aria-current="page"<?php endif; ?>>OTRadio</a>
<?php endif; ?>
      <a href="/accessibility"<?php if (($pageCanonical ?? '') === TYLILO_URL . '/accessibility'): ?> aria-current="page"<?php endif; ?>>Accessibility</a>
      <a href="/privacy"<?php if (($pageCanonical ?? '') === TYLILO_URL . '/privacy'): ?> aria-current="page"<?php endif; ?>>Privacy</a>
    </nav>
    <p>© <?= tylilo_e(TYLILO_YEAR) ?> <?= tylilo_e(TYLILO_LEGAL_NAME) ?></p>
  </footer>
  <script src="/assets/js/theme.js"></script>
  <script src="/assets/js/form.js"></script>
</body>
</html>
