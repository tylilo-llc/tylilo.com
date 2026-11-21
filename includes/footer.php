<?php

declare(strict_types=1);

if (!defined('TYLILO_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}
?>
  <footer class="site-footer">
    <p>© <?= tylilo_e(TYLILO_YEAR) ?> <?= tylilo_e(TYLILO_LEGAL_NAME) ?></p>
  </footer>
  <script src="/assets/js/theme.js"></script>
  <script src="/assets/js/form.js"></script>
</body>
</html>
