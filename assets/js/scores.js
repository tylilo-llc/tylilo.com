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
    var groupName = null;
    var groupBox = null;
    var groupTitleId = "";
    boards.forEach(function (board) {
      var target = results;
      var labelledBy = "";
      var text = String(board.label || board.board || "Scores");
      if (board.group) {
        if (board.group !== groupName) {
          groupName = board.group;
          groupBox = document.createElement("div");
          groupBox.className = "score-group";
          groupBox.setAttribute("role", "group");
          var groupTitle = document.createElement("p");
          groupTitle.className = "score-group-title";
          groupTitleId = "score-group-" + game + "-" + String(groupName).toLowerCase().replace(/[^a-z0-9]+/g, "-");
          groupTitle.id = groupTitleId;
          groupTitle.textContent = String(groupName);
          groupBox.setAttribute("aria-labelledby", groupTitleId);
          groupBox.appendChild(groupTitle);
          results.appendChild(groupBox);
        }
        target = groupBox;
        labelledBy = groupTitleId + " ";
        text = String(board.short || text);
      } else {
        groupName = null;
        groupBox = null;
      }
      var title = document.createElement("p");
      title.className = "score-board";
      title.id = "score-label-" + game + "-" + String(board.board || "board");
      title.textContent = text;
      target.appendChild(title);
      var scores = board.scores || [];
      if (!scores.length) {
        var none = document.createElement("p");
        none.className = "score-status";
        none.textContent = "No scores yet.";
        target.appendChild(none);
        return;
      }
      any = true;
      var list = document.createElement("ol");
      list.className = "score-list";
      list.setAttribute("aria-labelledby", labelledBy + title.id);
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
        var meta = document.createElement("span");
        meta.className = "score-meta";
        meta.append(value);
        if (board.levels) {
          var level = document.createElement("span");
          level.className = "score-level";
          var hidden = document.createElement("span");
          hidden.className = "visually-hidden";
          var shown = document.createElement("span");
          shown.setAttribute("aria-hidden", "true");
          if (row.level == null) {
            hidden.textContent = "No level";
            shown.textContent = "\u2013";
          } else {
            hidden.textContent = "Level " + String(row.level);
            shown.textContent = "Lv " + String(row.level);
          }
          level.append(hidden, shown);
          meta.append(level);
        }
        item.append(rank, name, meta);
        list.appendChild(item);
      });
      target.appendChild(list);
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
