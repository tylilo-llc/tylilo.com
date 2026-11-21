(function () {
  var form = document.querySelector("[data-contact-form]");
  if (form) {
    form.addEventListener("submit", function () {
      var button = form.querySelector('[type="submit"]');
      if (!button || button.disabled) return;
      if (!button.dataset.label) button.dataset.label = button.textContent;
      button.disabled = true;
      button.setAttribute("aria-disabled", "true");
      button.textContent = "Sending…";
    });
  }

  window.addEventListener("pageshow", function () {
    var button = document.querySelector('[data-contact-form] [type="submit"]');
    if (!button) return;
    button.disabled = false;
    button.removeAttribute("aria-disabled");
    if (button.dataset.label) button.textContent = button.dataset.label;
  });

  var status = document.getElementById("form-status");
  if (status && typeof status.focus === "function") {
    status.focus();
  }
})();
