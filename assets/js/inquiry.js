(function () {
  var form = document.querySelector("form[data-inquiry]");
  if (!form || !form.querySelectorAll) return;

  var steps = Array.prototype.slice.call(form.querySelectorAll(".inq-step"));
  if (steps.length < 2) return;
  var total = steps.length;
  var current = 0;

  function goals() {
    var out = [];
    form.querySelectorAll('input[name="goals[]"]:checked').forEach(function (el) { out.push(el.value); });
    return out;
  }

  function value(name) {
    var el = form.querySelector('input[name="' + name + '"]:checked');
    return el ? el.value : "";
  }

  function applies(when) {
    var g = goals();
    for (var key in when) {
      if (!Object.prototype.hasOwnProperty.call(when, key)) continue;
      var list = when[key];
      if (key === "goals_none_of") {
        if (g.some(function (v) { return list.indexOf(v) !== -1; })) return false;
      } else if (key === "goals") {
        if (!g.some(function (v) { return list.indexOf(v) !== -1; })) return false;
      } else if (list.indexOf(value(key)) === -1) {
        return false;
      }
    }
    return true;
  }

  function syncBranches() {
    form.querySelectorAll(".inq-branch").forEach(function (box) {
      var when = {};
      try { when = JSON.parse(box.getAttribute("data-when") || "{}"); } catch (e) {}
      var on = applies(when);
      box.hidden = !on;
      box.querySelectorAll("input, textarea, select").forEach(function (el) { el.disabled = !on; });
    });
  }

  // Progress, live status, and the Back / Continue buttons.
  var bar = document.createElement("div");
  bar.className = "inq-progress";
  bar.innerHTML = '<p class="inq-progress-text" id="inq-progress-text" aria-live="polite"></p>' +
    '<div class="inq-progress-track" aria-hidden="true"><span class="inq-progress-fill"></span></div>';
  form.insertBefore(bar, form.firstChild);
  var barText = bar.querySelector(".inq-progress-text");
  var barFill = bar.querySelector(".inq-progress-fill");

  steps.forEach(function (step, i) {
    var count = step.querySelector(".inq-count");
    if (count) count.hidden = true;
    var err = document.createElement("p");
    err.className = "field-error inq-step-error";
    err.setAttribute("role", "alert");
    err.hidden = true;
    var title = step.querySelector(".inq-step-title");
    if (title && title.nextSibling) step.insertBefore(err, title.nextSibling);
    else step.appendChild(err);

    var row = step.querySelector(".inq-submit-row");
    if (!row) {
      row = document.createElement("div");
      row.className = "inq-actions";
      step.appendChild(row);
      var next = document.createElement("button");
      next.type = "button";
      next.className = "btn send";
      next.textContent = "Continue";
      next.setAttribute("data-inq-next", "");
      row.appendChild(next);
    }
    if (i > 0) {
      var back = document.createElement("button");
      back.type = "button";
      back.className = "inq-back";
      back.textContent = "Back";
      back.setAttribute("data-inq-back", "");
      row.insertBefore(back, row.firstChild);
    }
  });

  function stepValid(step) {
    var err = step.querySelector(".inq-step-error");
    var first = null;
    var message = "";
    step.querySelectorAll(".inq-q").forEach(function (q) {
      if (q.closest(".inq-branch[hidden]")) return;
      var invalid = false;
      if (q.getAttribute("data-required") === "1") {
        invalid = !q.querySelector("input:checked");
        q.querySelectorAll("input").forEach(function (el) {
          if (invalid) el.setAttribute("aria-invalid", "true"); else el.removeAttribute("aria-invalid");
        });
        if (invalid && !first) {
          first = q.querySelector("input");
          var legend = q.querySelector("legend");
          var qText = legend ? legend.textContent.replace(" (required)", "").trim() : "this question";
          message = "Please answer: " + qText + (/[?.!]$/.test(qText) ? "" : ".");
        }
        return;
      }
      var field = q.querySelector("input, textarea");
      if (!field || field.disabled) return;
      field.value = field.value.replace(/^\s+|\s+$/g, "") === "" ? "" : field.value;
      if (!field.checkValidity()) {
        field.setAttribute("aria-invalid", "true");
        if (!first) {
          first = field;
          var label = q.querySelector("label");
          var name = label ? label.textContent.replace(" (required)", "") : "this field";
          message = field.validity.typeMismatch ? "Please check the email address." : "Please fill in: " + name + ".";
        }
      } else if (!q.querySelector(".field-error")) {
        field.removeAttribute("aria-invalid");
      }
    });
    var consent = step.querySelector("#inq-consent");
    if (!first && consent && !consent.checked) {
      first = consent;
      consent.setAttribute("aria-invalid", "true");
      message = "Please confirm that you have read the note above the button.";
    } else if (consent && consent.checked) {
      consent.removeAttribute("aria-invalid");
    }
    if (first) {
      err.textContent = message;
      err.hidden = false;
      first.focus();
      return false;
    }
    err.hidden = true;
    err.textContent = "";
    return true;
  }

  function show(index, focus) {
    syncBranches();
    current = Math.max(0, Math.min(total - 1, index));
    steps.forEach(function (step, i) { step.hidden = i !== current; });
    var title = steps[current].querySelector(".inq-step-title");
    barText.textContent = "Step " + (current + 1) + " of " + total + (title ? ": " + title.textContent : "");
    barFill.style.width = Math.round(((current + 1) / total) * 100) + "%";
    if (focus && title) title.focus();
  }

  form.classList.add("is-stepped");
  form.noValidate = true;

  // Error summary links jump to the step that holds the field.
  var summary = document.getElementById("form-status");
  if (summary) {
    summary.addEventListener("click", function (e) {
      var a = e.target && e.target.closest ? e.target.closest('a[href^="#"]') : null;
      if (!a) return;
      var target = document.getElementById(a.getAttribute("href").slice(1));
      var step = target && target.closest ? target.closest(".inq-step") : null;
      if (!step) return;
      e.preventDefault();
      show(steps.indexOf(step), false);
      var field = target.matches("input, textarea") ? target : target.querySelector("input, textarea");
      if (field) field.focus();
    });
  }

  form.addEventListener("click", function (e) {
    var t = e.target;
    if (!t || !t.closest) return;
    if (t.closest("[data-inq-next]")) {
      e.preventDefault();
      if (stepValid(steps[current])) show(current + 1, true);
    } else if (t.closest("[data-inq-back]")) {
      e.preventDefault();
      show(current - 1, true);
    }
  });

  form.addEventListener("change", function (e) {
    var t = e.target;
    if (t && (t.name === "goals[]" || t.name === "stage")) syncBranches();
    if (t && t.closest) {
      var q = t.closest(".inq-q");
      if (q && q.querySelector("input:checked")) {
        q.querySelectorAll("input").forEach(function (el) { el.removeAttribute("aria-invalid"); });
      }
      if (t.id === "inq-consent" && t.checked) t.removeAttribute("aria-invalid");
      // Once the visitor answers, the old step message goes away; Continue checks again.
      var stepEl = t.closest(".inq-step");
      var stepErr = stepEl ? stepEl.querySelector(".inq-step-error") : null;
      if (stepErr && !stepErr.hidden && (t.checked || t.value)) { stepErr.hidden = true; stepErr.textContent = ""; }
    }
  });

  // Enter in a one-line field moves on instead of sending early.
  form.addEventListener("keydown", function (e) {
    var t = e.target;
    if (e.key !== "Enter" || !t || t.tagName !== "INPUT" || t.type === "checkbox" || t.type === "radio") return;
    if (current < total - 1) {
      e.preventDefault();
      if (stepValid(steps[current])) show(current + 1, true);
    }
  });

  form.addEventListener("submit", function (e) {
    syncBranches();
    for (var i = 0; i < total; i++) {
      show(i, false);
      if (!stepValid(steps[i])) {
        e.preventDefault();
        return;
      }
    }
    show(total - 1, false);
    var btn = form.querySelector("[data-inq-submit]");
    if (btn) {
      btn.disabled = true;
      btn.textContent = "Sending…";
    }
  });

  // Start on the first step with a server error, if any.
  var start = 0;
  for (var s = 0; s < total; s++) {
    if (steps[s].querySelector(".field-error:not(.inq-step-error)")) { start = s; break; }
  }
  show(start, false);
  if (summary) summary.focus();
})();
