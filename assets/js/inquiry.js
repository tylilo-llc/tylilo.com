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

  function setEnabled(box, on) {
    box.querySelectorAll("input, textarea, select").forEach(function (el) {
      el.disabled = !on;
      var wrap = el.closest(".inq-q");
      if (!wrap || wrap.getAttribute("data-required") !== "1") return;
      if (el.type === "checkbox") return;
      if (on) {
        el.setAttribute("required", "required");
        if (el.type !== "radio") el.setAttribute("aria-required", "true");
      } else {
        el.removeAttribute("required");
        el.removeAttribute("aria-required");
      }
    });
  }

  function syncBranches() {
    form.querySelectorAll(".inq-branch").forEach(function (box) {
      var when = {};
      try { when = JSON.parse(box.getAttribute("data-when") || "{}"); } catch (e) {}
      var on = applies(when);
      box.hidden = !on;
      setEnabled(box, on);
    });
  }

  function syncReveals(moveFocus) {
    var focusEl = null;
    form.querySelectorAll(".inq-reveal").forEach(function (box) {
      var when = {};
      try { when = JSON.parse(box.getAttribute("data-reveal-when") || "{}"); } catch (e) {}
      var on = applies(when);
      var wasHidden = !!box.hidden;
      box.hidden = !on;
      setEnabled(box, on);
      if (box.id) {
        form.querySelectorAll("[aria-controls]").forEach(function (el) {
          var ids = (el.getAttribute("aria-controls") || "").split(/\s+/);
          if (ids.indexOf(box.id) !== -1) el.setAttribute("aria-expanded", on ? "true" : "false");
        });
      }
      if (on && wasHidden && moveFocus && !focusEl) {
        var field = box.querySelector("textarea, input:not([type=hidden]):not([type=checkbox]):not([type=radio])");
        if (!field) field = box.querySelector("input:not([type=hidden])");
        if (field && !field.disabled) focusEl = field;
      }
    });
    if (focusEl) focusEl.focus();
  }

  function sync(moveFocus) {
    syncBranches();
    syncReveals(!!moveFocus);
  }

  var typoMap = {
    "gmial.com": "gmail.com", "gmal.com": "gmail.com", "gamil.com": "gmail.com",
    "gnail.com": "gmail.com", "gmaill.com": "gmail.com", "gmail.con": "gmail.com",
    "gmail.co": "gmail.com", "gmai.com": "gmail.com",
    "yaho.com": "yahoo.com", "yahho.com": "yahoo.com", "yahooo.com": "yahoo.com",
    "yahoo.con": "yahoo.com",
    "hotmial.com": "hotmail.com", "hotmal.com": "hotmail.com", "hotmai.com": "hotmail.com",
    "hotmail.con": "hotmail.com",
    "outlok.com": "outlook.com", "outlock.com": "outlook.com", "outloo.com": "outlook.com",
    "outlook.con": "outlook.com",
    "iclod.com": "icloud.com", "icloud.con": "icloud.com",
    "protonmai.com": "protonmail.com", "protonmal.com": "protonmail.com",
    "live.con": "live.com", "msn.con": "msn.com", "aol.con": "aol.com", "me.con": "me.com"
  };
  var knownDomains = ["gmail.com","yahoo.com","hotmail.com","outlook.com","icloud.com","aol.com","live.com","msn.com","me.com","mac.com","ymail.com","proton.me","protonmail.com","comcast.net","verizon.net","att.net","sbcglobal.net"];

  function emailSuggestion(value) {
    var email = (value || "").replace(/^\s+|\s+$/g, "").toLowerCase();
    var at = email.lastIndexOf("@");
    if (at < 1 || at === email.length - 1) return "";
    var local = email.slice(0, at);
    var domain = email.slice(at + 1).replace(/\.+$/, "");
    if (typoMap[domain]) return local + "@" + typoMap[domain];
    if (knownDomains.indexOf(domain) !== -1) return "";
    var best = "", bestDist = 3, i, cand, dist;
    function levenshtein(a, b) {
      var m = [];
      for (var i = 0; i <= a.length; i++) { m[i] = [i]; }
      for (var j = 0; j <= b.length; j++) { m[0][j] = j; }
      for (i = 1; i <= a.length; i++) {
        for (j = 1; j <= b.length; j++) {
          m[i][j] = Math.min(m[i - 1][j] + 1, m[i][j - 1] + 1, m[i - 1][j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
        }
      }
      return m[a.length][b.length];
    }
    for (i = 0; i < knownDomains.length; i++) {
      cand = knownDomains[i];
      dist = levenshtein(domain, cand);
      if (dist > 0 && dist < bestDist) { bestDist = dist; best = cand; }
    }
    return best && bestDist <= 2 ? local + "@" + best : "";
  }

  function describeEmail(field, extraIds) {
    var ids = [];
    if (field.getAttribute("data-base-describedby")) ids = field.getAttribute("data-base-describedby").split(/\s+/).filter(Boolean);
    else if (field.getAttribute("aria-describedby")) {
      field.setAttribute("data-base-describedby", field.getAttribute("aria-describedby"));
      ids = field.getAttribute("aria-describedby").split(/\s+/).filter(Boolean);
    }
    (extraIds || []).forEach(function (id) { if (id && ids.indexOf(id) === -1) ids.push(id); });
    if (ids.length) field.setAttribute("aria-describedby", ids.join(" "));
    else field.removeAttribute("aria-describedby");
  }

  function setEmailState(field, message) {
    var q = field.closest(".inq-q");
    if (!q) return;
    var err = q.querySelector("#inq-email-error");
    if (!err) {
      err = document.createElement("p");
      err.className = "field-error";
      err.id = "inq-email-error";
      err.setAttribute("role", "alert");
      var privacy = q.querySelector(".inq-privacy");
      q.insertBefore(err, privacy || null);
    }
    if (message) {
      err.textContent = message;
      err.hidden = false;
      field.setAttribute("aria-invalid", "true");
      describeEmail(field, ["inq-email-error"]);
    } else {
      err.textContent = "";
      err.hidden = true;
      field.removeAttribute("aria-invalid");
      describeEmail(field, []);
    }
  }

  function showEmailSuggestion(field) {
    var q = field.closest(".inq-q");
    if (!q) return;
    var suggest = emailSuggestion(field.value);
    var box = q.querySelector("#inq-email-suggest");
    if (!box) {
      box = document.createElement("p");
      box.className = "inq-suggest";
      box.id = "inq-email-suggest";
      box.setAttribute("aria-live", "polite");
      var privacy = q.querySelector(".inq-privacy");
      q.insertBefore(box, privacy || null);
    }
    if (!suggest || suggest === field.value.replace(/^\s+|\s+$/g, "").toLowerCase()) {
      box.hidden = true;
      box.textContent = "";
      describeEmail(field, field.getAttribute("aria-invalid") === "true" ? ["inq-email-error"] : []);
      return;
    }
    box.hidden = false;
    box.innerHTML = "";
    var btn = document.createElement("button");
    btn.type = "button";
    btn.className = "inq-suggest-btn";
    btn.setAttribute("data-inq-suggest", suggest);
    btn.textContent = "Did you mean " + suggest + "?";
    box.appendChild(btn);
    describeEmail(field, (field.getAttribute("aria-invalid") === "true" ? ["inq-email-error"] : []).concat(["inq-email-suggest"]));
  }

  function checkEmail(field, showSuggest) {
    field.value = field.value.replace(/^\s+|\s+$/g, "");
    if (field.value === "") {
      setEmailState(field, field.required ? "Please enter your email address." : "");
      if (showSuggest) showEmailSuggestion(field);
      return !field.required;
    }
    if (!field.checkValidity()) {
      setEmailState(field, "That email address does not look right.");
      if (showSuggest) showEmailSuggestion(field);
      return false;
    }
    setEmailState(field, "");
    if (showSuggest) showEmailSuggestion(field);
    return true;
  }

  var DRAFT_KEY = "tyliloInquiryDraft";
  var draftSkip = { name: true, email: true, csrf: true, ty_extra: true, "cf-turnstile-response": true, preview_selftest: true, preview_smtp_check: true };

  function saveDraft() {
    var data = {};
    form.querySelectorAll("input, textarea, select").forEach(function (el) {
      var name = el.name;
      if (!name || el.disabled || draftSkip[name]) return;
      if (el.type === "file") return;
      if (el.type === "checkbox" || el.type === "radio") {
        if (!el.checked) return;
        if (name.slice(-2) === "[]") {
          var key = name.slice(0, -2);
          if (!data[key]) data[key] = [];
          data[key].push(el.value);
        } else data[name] = el.value;
        return;
      }
      data[name] = el.value;
    });
    try { sessionStorage.setItem(DRAFT_KEY, JSON.stringify(data)); } catch (e) {}
  }

  function restoreDraft() {
    var raw;
    try { raw = sessionStorage.getItem(DRAFT_KEY); } catch (e) { return; }
    if (!raw) return;
    var data;
    try { data = JSON.parse(raw); } catch (e) { return; }
    if (!data || typeof data !== "object") return;
    Object.keys(data).forEach(function (name) {
      if (draftSkip[name]) return;
      var value = data[name];
      if (Array.isArray(value)) {
        value.forEach(function (v) {
          var el = form.querySelector('input[name="' + name + '[]"][value="' + v + '"]');
          if (el) el.checked = true;
        });
        return;
      }
      var radios = form.querySelectorAll('input[name="' + name + '"]');
      if (radios.length && radios[0].type === "radio") {
        var match = form.querySelector('input[name="' + name + '"][value="' + CSS.escape(String(value)) + '"]');
        if (match) match.checked = true;
        return;
      }
      var field = form.querySelector('[name="' + name + '"]');
      if (field && field.type === "checkbox") field.checked = field.value === value;
      else if (field && field.type !== "hidden") field.value = value;
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
      if (q.closest(".inq-branch[hidden], .inq-reveal[hidden]")) return;
      var invalid = false;
      if (q.getAttribute("data-required") === "1" && q.querySelector("legend")) {
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
      if (field.type === "email") {
        if (!checkEmail(field, true) && !first) {
          first = field;
          message = field.value === "" ? "Please enter your email address." : "That email address does not look right.";
        }
        return;
      }
      if (!field.checkValidity()) {
        field.setAttribute("aria-invalid", "true");
        if (!first) {
          first = field;
          var label = q.querySelector("label");
          var name = label ? label.textContent.replace(" (required)", "").trim() : "this field";
          message = "Please fill in: " + name + (/[?.!]$/.test(name) ? "" : ".");
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
    sync(false);
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
    var suggest = t.closest("[data-inq-suggest]");
    if (suggest) {
      e.preventDefault();
      var email = form.querySelector("#inq-email");
      if (email) {
        email.value = suggest.getAttribute("data-inq-suggest") || "";
        checkEmail(email, true);
        email.focus();
      }
      saveDraft();
      return;
    }
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
    if (t && (t.type === "checkbox" || t.type === "radio")) sync(true);
    saveDraft();
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

  var emailField = form.querySelector("#inq-email");
  if (emailField) {
    emailField.addEventListener("blur", function () { checkEmail(emailField, true); });
    emailField.addEventListener("input", function () {
      if (emailField.getAttribute("aria-invalid") === "true") checkEmail(emailField, true);
      else showEmailSuggestion(emailField);
    });
  }

  form.addEventListener("input", function () { saveDraft(); });

  restoreDraft();

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
    sync(false);
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
