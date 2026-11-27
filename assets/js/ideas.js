(function () {
  var dialog = document.getElementById("new-idea-dialog");
  var opener = document.getElementById("new-idea-open");
  var panel = document.getElementById("new-idea-panel");

  function closeControl() {
    return document.getElementById("new-idea-close");
  }

  function canDialog() {
    return !!(dialog && typeof dialog.showModal === "function");
  }

  function enhance() {
    var close = closeControl();
    var back = panel ? panel.querySelector(".idea-cancel-link") : null;
    if (close) close.hidden = false;
    if (back) back.hidden = true;
  }

  function openDialog() {
    if (!canDialog()) return;
    if (panel && !dialog.contains(panel)) {
      panel.classList.remove("card");
      dialog.appendChild(panel);
    }
    enhance();
    if (!dialog.open) dialog.showModal();
  }

  if (canDialog() && opener) {
    opener.addEventListener("click", function (event) {
      event.preventDefault();
      openDialog();
    });
  }

  document.addEventListener("click", function (event) {
    var target = event.target;
    if (target && target.nodeType !== 1) target = target.parentElement;
    if (!target || !target.closest || !target.closest("#new-idea-close")) return;
    if (dialog && dialog.open) dialog.close();
  });

  if (canDialog()) {
    dialog.addEventListener("close", function () {
      if (opener) opener.focus();
    });
    if (dialog.getAttribute("data-open") === "true") openDialog();
    else enhance();
  }

  var hash = window.location.hash || "";
  if (hash.indexOf("#idea-") === 0) {
    var card = document.getElementById(hash.slice(1));
    var details = card ? card.querySelector("details") : null;
    if (details) details.open = true;
  }
})();
