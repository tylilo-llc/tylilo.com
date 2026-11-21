(function () {
  var KEY = "tyliloA2hsDismissed";
  var path = location.pathname || "";
  if (path !== "/arcade" && path !== "/arcade/" && path !== "/arcade/index.php") return;

  try {
    if (localStorage.getItem(KEY)) return;
  } catch (e) {
    return;
  }

  var ua = navigator.userAgent || "";
  var touchMac = navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1;
  var iosDevice = /iPad|iPhone|iPod/.test(ua) || touchMac;
  var otherBrowser = /CriOS|FxiOS|EdgiOS|OPiOS|DuckDuckGo|OPT\/|Chrome|Chromium|Android/i.test(ua);
  var safari = /Safari/i.test(ua) && !otherBrowser;
  if (!iosDevice || !safari) return;

  var standalone = false;
  try {
    standalone = window.navigator.standalone === true || window.matchMedia("(display-mode: standalone)").matches;
  } catch (e) {}
  if (standalone) return;

  var banner = document.createElement("aside");
  banner.className = "a2hs";
  banner.setAttribute("role", "region");
  banner.setAttribute("aria-label", "Add Tylilo Arcade to your Home Screen");
  banner.innerHTML =
    '<p>To open the Arcade like an app, tap the Share button ' +
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' +
    '<path fill="currentColor" d="M12 3.2 7.5 7.7l1.4 1.4L11 7v8h2V7l2.1 2.1 1.4-1.4L12 3.2z"/>' +
    '<path fill="none" stroke="currentColor" stroke-width="1.8" d="M6 11.5V19h12v-7.5"/>' +
    '</svg>, then Add to Home Screen.</p>' +
    '<button type="button" class="a2hs-close" aria-label="Dismiss add to Home Screen instructions">' +
    '<span aria-hidden="true">×</span></button>';

  var footer = document.querySelector(".site-footer");
  if (footer) footer.insertAdjacentElement("afterend", banner);
  else document.body.appendChild(banner);

  banner.querySelector("button").addEventListener("click", function () {
    try {
      localStorage.setItem(KEY, "1");
    } catch (e) {}
    banner.remove();
  });
})();
