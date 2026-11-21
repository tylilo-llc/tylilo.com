<?php

declare(strict_types=1);

if (!defined('TYLILO_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}
?>
  <footer class="site-footer">
    <nav aria-label="Footer">
      <a href="/otradio/"<?php if (($pageCanonical ?? '') === TYLILO_URL . '/otradio/'): ?> aria-current="page"<?php endif; ?>>OTRadio</a>
      <a href="/accessibility"<?php if (($pageCanonical ?? '') === TYLILO_URL . '/accessibility'): ?> aria-current="page"<?php endif; ?>>Accessibility</a>
    </nav>
    <p>© <?= tylilo_e(TYLILO_YEAR) ?> <?= tylilo_e(TYLILO_LEGAL_NAME) ?></p>
  </footer>
  <script src="/assets/js/theme.js"></script>
  <script src="/assets/js/form.js"></script>
</body>
</html>
