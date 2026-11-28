(function () {
  var KEY = "tylilo-theme";
  var ORDER = ["auto", "light", "dark"];
  var NAMES = { auto: "Auto", light: "Light", dark: "Dark" };
  var root = document.documentElement;
  var btn = document.querySelector("[data-theme-toggle]");
  var meta = document.querySelector('meta[name="theme-color"]');

  function stored() {
    try {
      return localStorage.getItem(KEY) || "auto";
    } catch (e) {
      return "auto";
    }
  }

  function allowed(theme) {
    return theme === "light" || theme === "dark" || theme === "auto";
  }

  function isDark(theme) {
    if (theme === "dark") return true;
    if (theme === "light") return false;
    try {
      return window.matchMedia("(prefers-color-scheme: dark)").matches;
    } catch (e) {
      return false;
    }
  }

  function nextTheme(theme) {
    if (!allowed(theme)) theme = "auto";
    return ORDER[(ORDER.indexOf(theme) + 1) % ORDER.length];
  }

  function announce(theme) {
    var name = NAMES[theme] || "Auto";
    var next = NAMES[nextTheme(theme)];
    return "Theme: " + name + ". Click to switch to " + next;
  }

  function colors() {
    if (root.classList.contains("signal")) {
      return { dark: "#050B14", light: "#071525" };
    }
    return { dark: "#071526", light: "#0A3161" };
  }

  function paint(theme) {
    if (!btn) return;
    var label = announce(theme);
    btn.setAttribute("aria-label", label);
    btn.setAttribute("title", label);
    var text = btn.querySelector(".theme-label");
    if (text) text.textContent = NAMES[theme] || "Auto";
    btn.querySelectorAll("[data-theme-icon]").forEach(function (el) {
      el.hidden = el.getAttribute("data-theme-icon") !== theme;
    });
  }

  function apply(theme, save) {
    if (!allowed(theme)) theme = "auto";
    root.setAttribute("data-theme", theme);
    if (save) {
      try {
        localStorage.setItem(KEY, theme);
      } catch (e) {}
    }
    paint(theme);
    if (meta) {
      var c = colors();
      meta.setAttribute("content", isDark(theme) ? c.dark : c.light);
    }
  }

  if (btn) {
    btn.addEventListener("click", function () {
      apply(nextTheme(stored()), true);
    });
  }

  apply(stored(), false);

  try {
    var mq = window.matchMedia("(prefers-color-scheme: dark)");
    var onChange = function () {
      if (stored() === "auto") apply("auto", false);
    };
    if (mq.addEventListener) mq.addEventListener("change", onChange);
    else if (mq.addListener) mq.addListener(onChange);
  } catch (e) {}
})();
