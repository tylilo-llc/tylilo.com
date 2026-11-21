(function () {
  var dataEl = document.getElementById("admin-session-data");
  var dialog = document.getElementById("session-warn");
  if (!dataEl || !dialog || !dialog.showModal) return;
  var data;
  try { data = JSON.parse(dataEl.textContent || ""); } catch (e) { return; }
  if (!data || !data.csrf) return;

  var text = document.getElementById("session-warn-text");
  var countdown = document.getElementById("session-countdown");
  var live = document.getElementById("session-live");
  var unsaved = document.getElementById("session-unsaved");
  var stay = document.getElementById("session-stay");
  var leave = document.getElementById("session-leave");
  var idleDeadline = Date.now() + Number(data.idleLeft) * 1000;
  var absoluteDeadline = Date.now() + Number(data.absoluteLeft) * 1000;
  var warn = Number(data.warn) || 120;
  var open = false;
  var dirty = false;
  var ended = false;
  var lastSpoken = -1;
  var previous = null;

  function markDirty(event) {
    var target = event.target;
    if (!target || !target.form) return;
    if (target.closest && target.closest("#session-warn")) return;
    var type = (target.type || "").toLowerCase();
    if (type === "hidden" || type === "submit" || type === "button") return;
    dirty = true;
  }
  document.addEventListener("input", markDirty, true);
  document.addEventListener("change", markDirty, true);

  function remaining() {
    var now = Date.now();
    var idle = Math.ceil((idleDeadline - now) / 1000);
    var absolute = Math.ceil((absoluteDeadline - now) / 1000);
    return { seconds: Math.min(idle, absolute), cap: absolute <= idle };
  }

  function clock(seconds) {
    var s = Math.max(0, seconds);
    var m = Math.floor(s / 60);
    var r = s % 60;
    return m + ":" + (r < 10 ? "0" : "") + r;
  }

  function speak(seconds) {
    if (countdown) countdown.textContent = clock(seconds) + " remaining.";
    if (!live) return;
    if (seconds === 120 || seconds === 60 || seconds === 30 || seconds === 10) {
      if (lastSpoken === seconds) return;
      lastSpoken = seconds;
      live.textContent = clock(seconds) + " remaining.";
    }
  }

  function show(state) {
    if (text) {
      text.textContent = state.cap
        ? "This sign-in ends soon. It lasts 12 hours and cannot be extended."
        : "This page has been quiet. You will be signed out after 30 minutes unless you stay.";
    }
    if (unsaved) unsaved.hidden = !dirty;
    speak(state.seconds);
    if (!open) {
      previous = document.activeElement;
      dialog.showModal();
      open = true;
      if (stay) stay.focus();
    }
  }

  function hide() {
    if (!open) return;
    dialog.close();
    open = false;
    if (previous && previous.focus) previous.focus();
  }

  function expired() {
    if (ended) return;
    ended = true;
    if (dirty) {
      if (text) text.textContent = "You have been signed out. Your unsaved text is still on this page. Copy it before you leave.";
      if (unsaved) unsaved.hidden = true;
      if (stay) stay.hidden = true;
      if (leave) {
        leave.hidden = false;
        leave.focus();
      }
      if (countdown) countdown.textContent = "Signed out.";
      if (!open) {
        dialog.showModal();
        open = true;
      }
      return;
    }
    window.location.assign("/admin/?out=1");
  }

  function staySignedIn() {
    var body = "csrf=" + encodeURIComponent(data.csrf);
    fetch("/admin/keepalive.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded", Accept: "application/json" },
      body: body,
      credentials: "same-origin"
    }).then(function (response) {
      if (!response.ok) throw new Error("out");
      return response.json();
    }).then(function (payload) {
      if (!payload || payload.ok !== true) throw new Error("out");
      idleDeadline = Date.now() + Number(payload.idleLeft) * 1000;
      absoluteDeadline = Date.now() + Number(payload.absoluteLeft) * 1000;
      dirty = false;
      hide();
    }).catch(function () {
      expired();
    });
  }

  if (stay) stay.addEventListener("click", staySignedIn);
  if (leave) leave.addEventListener("click", function () { window.location.assign("/admin/?out=1"); });
  dialog.addEventListener("cancel", function (event) { event.preventDefault(); });
  dialog.addEventListener("keydown", function (event) {
    if (event.key !== "Tab") return;
    var items = dialog.querySelectorAll("button:not([hidden])");
    if (!items.length) return;
    var first = items[0];
    var last = items[items.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  window.setInterval(function () {
    var state = remaining();
    if (state.seconds <= 0) {
      expired();
      return;
    }
    if (state.seconds <= warn) show(state);
  }, 1000);
})();
