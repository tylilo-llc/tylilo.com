/* Local design-preview switch. Not used by the live site.
   Reads ?d=a|b|c and ?theme=light|dark|auto before CSS applies. */
(function () {
  var params = new URLSearchParams(location.search);
  var direction = params.get("d");
  if (direction !== "a" && direction !== "b" && direction !== "c") direction = "a";
  var theme = params.get("theme");
  if (theme !== "light" && theme !== "dark" && theme !== "auto") {
    try {
      theme = localStorage.getItem("tylilo-design-preview-theme") || "auto";
    } catch (e) {
      theme = "auto";
    }
  }
  if (theme !== "light" && theme !== "dark" && theme !== "auto") theme = "auto";

  var root = document.documentElement;
  root.setAttribute("data-direction", direction);
  root.setAttribute("data-theme", theme);

  var colors = {
    a: { light: "#F3F0E8", dark: "#141311" },
    b: { light: "#14120E", dark: "#070605" },
    c: { light: "#F6F0E9", dark: "#1A1513" }
  };

  function isDark(value) {
    if (value === "dark") return true;
    if (value === "light") return false;
    try {
      return window.matchMedia("(prefers-color-scheme: dark)").matches;
    } catch (e) {
      return false;
    }
  }

  function paint(value, save) {
    if (value !== "light" && value !== "dark" && value !== "auto") value = "auto";
    root.setAttribute("data-theme", value);
    if (save) {
      try { localStorage.setItem("tylilo-design-preview-theme", value); } catch (e) {}
    }
    var meta = document.querySelector('meta[name="theme-color"]');
    var pair = colors[root.getAttribute("data-direction")] || colors.a;
    if (meta) meta.setAttribute("content", isDark(value) ? pair.dark : pair.light);
    document.querySelectorAll("[data-theme-set]").forEach(function (btn) {
      var on = btn.getAttribute("data-theme-set") === value;
      btn.setAttribute("aria-pressed", on ? "true" : "false");
    });
  }

  function keep(href) {
    var url;
    try { url = new URL(href, location.href); } catch (e) { return href; }
    if (url.origin !== location.origin) return href;
    if (url.pathname.indexOf("/design-options/") === -1) return href;
    if (url.pathname.indexOf("/design-options/current/") !== -1) return href;
    if (!url.searchParams.has("d")) url.searchParams.set("d", root.getAttribute("data-direction") || "a");
    var current = root.getAttribute("data-theme");
    if (current && current !== "auto" && !url.searchParams.has("theme")) {
      url.searchParams.set("theme", current);
    }
    return url.pathname + url.search + url.hash;
  }

  document.addEventListener("DOMContentLoaded", function () {
    paint(root.getAttribute("data-theme") || "auto", false);
    document.querySelectorAll("[data-theme-set]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        paint(btn.getAttribute("data-theme-set"), true);
      });
    });
    document.querySelectorAll("a[href]").forEach(function (a) {
      var href = a.getAttribute("href");
      if (!href || href.charAt(0) === "#") return;
      a.setAttribute("href", keep(href));
    });
    document.querySelectorAll("form[data-preview]").forEach(function (form) {
      form.addEventListener("submit", function (event) {
        event.preventDefault();
      });
    });
    try {
      var mq = window.matchMedia("(prefers-color-scheme: dark)");
      var onChange = function () {
        if (params.get("theme")) return;
        var saved = "auto";
        try { saved = localStorage.getItem("tylilo-design-preview-theme") || "auto"; } catch (e) {}
        if (saved === "auto") paint("auto", false);
      };
      if (mq.addEventListener) mq.addEventListener("change", onChange);
    } catch (e) {}
  });
})();
