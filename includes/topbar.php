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
      <div class="theme" role="group" aria-label="Theme">
        <button type="button" data-theme-set="light" aria-pressed="false" aria-label="Light theme" title="Light theme">
          <svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" width="22" height="22">
            <circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/>
            <g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <path d="M12 2.6v2.2M12 19.2v2.2M2.6 12h2.2M19.2 12h2.2"/>
              <path d="M5.4 5.4l1.6 1.6M17 17l1.6 1.6M18.6 5.4L17 7M7 17l-1.6 1.6"/>
            </g>
          </svg>
        </button>
        <button type="button" data-theme-set="dark" aria-pressed="false" aria-label="Dark theme" title="Dark theme">
          <svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" width="22" height="22">
            <path fill="currentColor" d="M14.6 3.1a8.2 8.2 0 1 0 6.3 12.8A7.1 7.1 0 0 1 14.6 3.1z"/>
          </svg>
        </button>
        <button type="button" data-theme-set="auto" aria-pressed="true" aria-label="Auto theme (match device)" title="Auto theme (match device)">
          <svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" width="22" height="22">
            <circle cx="12" cy="12" r="7.25" fill="none" stroke="currentColor" stroke-width="2"/>
            <path fill="currentColor" d="M12 5.35a6.65 6.65 0 0 0 0 13.3V5.35z"/>
          </svg>
        </button>
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
