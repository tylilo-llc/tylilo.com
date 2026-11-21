(function () {
  var buttons = document.querySelectorAll(".score-toggle");
  if (!buttons.length) return;

  function format(score, unit) {
    var n = Number(score);
    if (!isFinite(n)) return "";
    if (unit === "seconds") {
      var s = Math.max(0, Math.round(n));
      var m = Math.floor(s / 60);
      var r = s % 60;
      return m + ":" + (r < 10 ? "0" : "") + r;
    }
    try {
      return n.toLocaleString("en-US");
    } catch (e) {
      return String(n);
    }
  }

  function fill(body, game, data) {
    var status = body.querySelector(".score-status");
    var results = body.querySelector(".score-results");
    if (!results) return;
    results.replaceChildren();
    var boards = data && data.boards ? data.boards : [];
    if (!boards.length) {
      if (status) status.textContent = "No scores yet.";
      var noneAll = document.createElement("p");
      noneAll.className = "score-status";
      noneAll.textContent = "No scores yet.";
      results.appendChild(noneAll);
      return;
    }
    var any = false;
    boards.forEach(function (board) {
      var title = document.createElement("p");
      title.className = "score-board";
      title.id = "score-label-" + game + "-" + String(board.board || "board");
      title.textContent = String(board.label || board.board || "Scores");
      results.appendChild(title);
      var scores = board.scores || [];
      if (!scores.length) {
        var none = document.createElement("p");
        none.className = "score-status";
        none.textContent = "No scores yet.";
        results.appendChild(none);
        return;
      }
      any = true;
      var list = document.createElement("ol");
      list.className = "score-list";
      list.setAttribute("aria-labelledby", title.id);
      scores.forEach(function (row, i) {
        var item = document.createElement("li");
        item.className = "score-row";
        var rank = document.createElement("span");
        rank.className = "score-rank";
        rank.textContent = String(i + 1);
        var name = document.createElement("span");
        name.className = "score-name";
        name.textContent = String(row.name == null ? "" : row.name);
        var value = document.createElement("span");
        value.className = "score-value";
        value.textContent = format(row.score, board.unit);
        item.append(rank, name, value);
        list.appendChild(item);
      });
      results.appendChild(list);
    });
    if (status) status.textContent = any ? "Showing top scores." : "No scores yet.";
  }

  function fail(body) {
    var status = body.querySelector(".score-status");
    var results = body.querySelector(".score-results");
    if (results) results.replaceChildren();
    if (status) status.textContent = "Top scores could not be loaded.";
    if (results) {
      var note = document.createElement("p");
      note.className = "score-status";
      note.textContent = "Top scores could not be loaded.";
      results.appendChild(note);
    }
  }

  buttons.forEach(function (button) {
    var body = document.getElementById(button.getAttribute("aria-controls") || "");
    if (!body) return;
    button.addEventListener("click", function () {
      var open = button.getAttribute("aria-expanded") === "true";
      button.setAttribute("aria-expanded", open ? "false" : "true");
      if (open) {
        body.hidden = true;
        return;
      }
      body.hidden = false;
      if (body.dataset.loaded === "1" || body.dataset.loading === "1") return;
      body.dataset.loading = "1";
      var status = body.querySelector(".score-status");
      if (status) status.textContent = "Loading top scores.";
      var game = button.getAttribute("data-game") || "";
      fetch("/api/scores.php?game=" + encodeURIComponent(game) + "&limit=10", {
        headers: { Accept: "application/json" },
        credentials: "same-origin"
      }).then(function (res) {
        if (!res.ok) throw new Error("bad");
        return res.json();
      }).then(function (data) {
        if (!data || data.ok !== true) throw new Error("bad");
        fill(body, game, data);
        body.dataset.loaded = "1";
      }).catch(function () {
        fail(body);
      }).then(function () {
        delete body.dataset.loading;
      });
    });
  });
})();
