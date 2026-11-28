(function () {
  var button = document.getElementById("admin-menu-toggle");
  var panel = document.getElementById("admin-menu-panel");
  if (!button || !panel) return;

  var desktop = window.matchMedia("(min-width: 1024px)");

  function isDesktop() {
    return desktop.matches;
  }

  function setOpen(open) {
    if (isDesktop()) open = false;
    button.setAttribute("aria-expanded", open ? "true" : "false");
    panel.classList.toggle("is-open", open);
  }

  button.addEventListener("click", function () {
    if (isDesktop()) return;
    setOpen(button.getAttribute("aria-expanded") !== "true");
  });

  document.addEventListener("keydown", function (event) {
    if (isDesktop()) return;
    if (event.key !== "Escape" || button.getAttribute("aria-expanded") !== "true") return;
    if (document.querySelector("dialog[open]")) return;
    setOpen(false);
    button.focus();
  });

  document.addEventListener("pointerdown", function (event) {
    if (isDesktop()) return;
    if (button.getAttribute("aria-expanded") !== "true") return;
    var target = event.target;
    if (target && target.nodeType !== 1) target = target.parentElement;
    if (!target || !target.closest) return;
    if (target.closest("#admin-menu-toggle") || target.closest("#admin-menu-panel")) return;
    setOpen(false);
  });

  if (desktop.addEventListener) {
    desktop.addEventListener("change", function () { setOpen(false); });
  } else if (desktop.addListener) {
    desktop.addListener(function () { setOpen(false); });
  }
})();
