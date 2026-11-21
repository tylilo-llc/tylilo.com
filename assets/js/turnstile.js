(function () {
  var widgetId = null;
  var shownTheme = "";

  function siteTheme() {
    var mode = document.documentElement.getAttribute("data-theme") || "auto";
    if (mode === "dark" || mode === "light") return mode;
    try {
      return window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
    } catch (e) {
      return "auto";
    }
  }

  function mount() {
    var el = document.querySelector("[data-contact-turnstile]");
    if (!el || !window.turnstile || typeof turnstile.render !== "function") return;
    var sitekey = el.getAttribute("data-sitekey") || "";
    if (!sitekey) return;
    var next = siteTheme();
    if (widgetId !== null && next === shownTheme) return;
    if (widgetId !== null) {
      try { turnstile.remove(widgetId); } catch (e) {}
      widgetId = null;
    }
    el.setAttribute("data-theme", next);
    widgetId = turnstile.render(el, {
      sitekey: sitekey,
      action: el.getAttribute("data-action") || "contact",
      appearance: el.getAttribute("data-appearance") || "interaction-only",
      size: el.getAttribute("data-size") || "flexible",
      theme: next
    });
    shownTheme = next;
  }

  window.tyliloTurnstile = function () {
    mount();
    var root = document.documentElement;
    if (window.MutationObserver) {
      new MutationObserver(mount).observe(root, {
        attributes: true,
        attributeFilter: ["data-theme"]
      });
    }
    try {
      var mq = window.matchMedia("(prefers-color-scheme: dark)");
      var onScheme = function () {
        if ((root.getAttribute("data-theme") || "auto") === "auto") mount();
      };
      if (mq.addEventListener) mq.addEventListener("change", onScheme);
      else if (mq.addListener) mq.addListener(onScheme);
    } catch (e) {}
  };
})();
