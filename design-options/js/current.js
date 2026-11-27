/* Theme switch for the current-design replicas.
   Uses its own storage key so it does not change the live site preference. */
(function () {
  var KEY = "tylilo-design-current-theme";
  var params = new URLSearchParams(location.search);
  var theme = params.get("theme");
  if (theme !== "light" && theme !== "dark" && theme !== "auto") {
    try { theme = localStorage.getItem(KEY) || "auto"; } catch (e) { theme = "auto"; }
  }
  if (theme !== "light" && theme !== "dark" && theme !== "auto") theme = "auto";
  document.documentElement.setAttribute("data-theme", theme);

  function isDark(value) {
    if (value === "dark") return true;
    if (value === "light") return false;
    try { return window.matchMedia("(prefers-color-scheme: dark)").matches; } catch (e) { return false; }
  }

  function paint(value, save) {
    if (value !== "light" && value !== "dark" && value !== "auto") value = "auto";
    document.documentElement.setAttribute("data-theme", value);
    if (save) {
      try { localStorage.setItem(KEY, value); } catch (e) {}
    }
    var meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute("content", isDark(value) ? "#071526" : "#0A3161");
    document.querySelectorAll("[data-theme-set]").forEach(function (btn) {
      btn.setAttribute("aria-pressed", btn.getAttribute("data-theme-set") === value ? "true" : "false");
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    paint(document.documentElement.getAttribute("data-theme") || "auto", false);
    document.querySelectorAll("[data-theme-set]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        paint(btn.getAttribute("data-theme-set"), true);
      });
    });
  });
})();
