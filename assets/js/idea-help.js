(function () {
  var buttons = document.querySelectorAll(".idea-info");
  if (!buttons.length) return;
  var openButton = null;
  var closeTimer = 0;

  function panel(button) {
    var id = button.getAttribute("aria-controls");
    return id ? document.getElementById(id) : null;
  }

  function close(button) {
    if (!button) return;
    button.setAttribute("aria-expanded", "false");
    button.dataset.pinned = "0";
    var tip = panel(button);
    if (tip) tip.hidden = true;
    if (openButton === button) openButton = null;
  }

  function open(button) {
    if (openButton && openButton !== button) close(openButton);
    button.setAttribute("aria-expanded", "true");
    var tip = panel(button);
    if (tip) tip.hidden = false;
    openButton = button;
  }

  function cancelClose() {
    window.clearTimeout(closeTimer);
  }

  function scheduleClose(button) {
    if (button.dataset.pinned === "1") return;
    cancelClose();
    closeTimer = window.setTimeout(function () { close(button); }, 200);
  }

  buttons.forEach(function (button) {
    var tip = panel(button);
    button.addEventListener("click", function () {
      if (button.getAttribute("aria-expanded") === "true" && button.dataset.pinned === "1") {
        close(button);
        return;
      }
      open(button);
      button.dataset.pinned = "1";
    });
    button.addEventListener("mouseenter", function () {
      cancelClose();
      open(button);
    });
    button.addEventListener("mouseleave", function () { scheduleClose(button); });
    button.addEventListener("focus", function () {
      cancelClose();
      open(button);
    });
    button.addEventListener("blur", function () { scheduleClose(button); });
    if (tip) {
      tip.addEventListener("mouseenter", cancelClose);
      tip.addEventListener("mouseleave", function () { scheduleClose(button); });
    }
  });

  document.addEventListener("keydown", function (event) {
    if (event.key !== "Escape" || !openButton) return;
    var button = openButton;
    close(button);
    button.focus();
  });

  document.addEventListener("pointerdown", function (event) {
    if (!openButton) return;
    var tip = panel(openButton);
    var target = event.target;
    if (openButton.contains(target) || (tip && tip.contains(target))) return;
    close(openButton);
  });
})();
