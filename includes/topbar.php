<?php

declare(strict_types=1);

if (!defined('TYLILO_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

$nonce = tylilo_e($cspNonce);
?>
    <div class="top">
<?php if (!tylilo_request_is_admin()): ?>
      <a class="wordmark" href="/" aria-label="Tylilo, home"><img class="brand-mark" src="/resources/Tylilo-Logo/Tylilo-Logo-Small.png" alt="" width="96" height="36"></a>
      <nav class="site-nav" aria-label="Primary">
        <a href="/#consulting">Consulting</a>
        <a href="/#software">Software</a>
        <a href="/#about">About</a>
        <a href="/#contact">Contact</a>
        <a class="nav-cta" href="/inquiry"<?php if (($pageCanonical ?? '') === TYLILO_URL . '/inquiry'): ?> aria-current="page"<?php endif; ?>>Start an inquiry</a>
      </nav>
<?php else: ?>
      <a class="wordmark" href="/" aria-label="Tylilo, home">Tylilo</a>
<?php endif; ?>
      <div class="theme">
        <button type="button" data-theme-toggle aria-label="Theme: Auto. Click to switch to Light" title="Theme: Auto. Click to switch to Light">
          <span class="theme-icon" data-theme-icon="auto" aria-hidden="true">
            <svg focusable="false" viewBox="0 0 24 24" width="22" height="22">
              <circle cx="12" cy="12" r="7.25" fill="none" stroke="currentColor" stroke-width="2"/>
              <path fill="currentColor" d="M12 5.35a6.65 6.65 0 0 0 0 13.3V5.35z"/>
            </svg>
          </span>
          <span class="theme-icon" data-theme-icon="light" hidden aria-hidden="true">
            <svg focusable="false" viewBox="0 0 24 24" width="22" height="22">
              <circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/>
              <g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M12 2.6v2.2M12 19.2v2.2M2.6 12h2.2M19.2 12h2.2"/>
                <path d="M5.4 5.4l1.6 1.6M17 17l1.6 1.6M18.6 5.4L17 7M7 17l-1.6 1.6"/>
              </g>
            </svg>
          </span>
          <span class="theme-icon" data-theme-icon="dark" hidden aria-hidden="true">
            <svg focusable="false" viewBox="0 0 24 24" width="22" height="22">
              <path fill="currentColor" d="M14.6 3.1a8.2 8.2 0 1 0 6.3 12.8A7.1 7.1 0 0 1 14.6 3.1z"/>
            </svg>
          </span>
          <span class="theme-label">Auto</span>
        </button>
      </div>
    </div>
    <script nonce="<?= $nonce ?>">
      (function () {
        var theme = document.documentElement.getAttribute("data-theme") || "auto";
        if (theme !== "light" && theme !== "dark" && theme !== "auto") theme = "auto";
        var next = theme === "auto" ? "Light" : theme === "light" ? "Dark" : "Auto";
        var name = theme === "auto" ? "Auto" : theme === "light" ? "Light" : "Dark";
        var btn = document.querySelector("[data-theme-toggle]");
        if (!btn) return;
        var label = "Theme: " + name + ". Click to switch to " + next;
        btn.setAttribute("aria-label", label);
        btn.setAttribute("title", label);
        var text = btn.querySelector(".theme-label");
        if (text) text.textContent = name;
        btn.querySelectorAll("[data-theme-icon]").forEach(function (el) {
          el.hidden = el.getAttribute("data-theme-icon") !== theme;
        });
      })();
    </script>
