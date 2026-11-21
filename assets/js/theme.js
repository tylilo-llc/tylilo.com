(function () {
  var KEY = "tylilo-theme";
  var root = document.documentElement;
  var buttons = document.querySelectorAll("[data-theme-set]");
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

  function apply(theme, save) {
    if (!allowed(theme)) theme = "auto";
    root.setAttribute("data-theme", theme);
    if (save) {
      try {
        localStorage.setItem(KEY, theme);
      } catch (e) {}
    }
    buttons.forEach(function (btn) {
      var on = btn.getAttribute("data-theme-set") === theme;
      btn.setAttribute("aria-pressed", on ? "true" : "false");
    });
    if (meta) {
      meta.setAttribute("content", isDark(theme) ? "#071526" : "#0A3161");
    }
  }

  buttons.forEach(function (btn) {
    btn.addEventListener("click", function () {
      apply(btn.getAttribute("data-theme-set"), true);
    });
  });

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
