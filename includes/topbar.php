<?php

declare(strict_types=1);

if (!defined('TYLILO_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

$nonce = tylilo_e($cspNonce);
?>
    <div class="top">
      <a class="wordmark" href="/" aria-label="Tylilo, home">Tylilo</a>
      <div class="theme" role="group" aria-label="Color theme">
        <button type="button" data-theme-set="light" aria-pressed="false">Light</button>
        <button type="button" data-theme-set="dark" aria-pressed="false">Dark</button>
        <button type="button" data-theme-set="auto" aria-pressed="true">Auto</button>
      </div>
    </div>
    <script nonce="<?= $nonce ?>">
      (function () {
        var theme = document.documentElement.getAttribute("data-theme") || "auto";
        document.querySelectorAll("[data-theme-set]").forEach(function (btn) {
          btn.setAttribute("aria-pressed", btn.getAttribute("data-theme-set") === theme ? "true" : "false");
        });
      })();
    </script>
