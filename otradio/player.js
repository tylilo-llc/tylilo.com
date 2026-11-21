/* OTRadio web player. Catalog URL is the single config constant below. */
(function () {
  "use strict";

  // Single config constant. Media keys resolve against the catalog's
  // public_base_url, or against this same origin when that field is missing.
  const CATALOG_URL = "https://pub-a3eee908554d4a948d807c160caa454e.r2.dev/catalog.json";

  const THEME_KEY = "tylilo-theme";
  const CC_KEY = "otradio:captions";
  const SPEED_KEY = "otradio:speed";
  const POS_PREFIX = "otradio:pos:";
  const SKIP = 30;

  const $ = (id) => document.getElementById(id);

  const state = {
    catalog: null,
    base: new URL(".", CATALOG_URL).href.replace(/\/?$/, "/"),
    series: null,
    episode: null,
    captionsOn: loadCaptionsPref(),
    cues: [],
    cueText: "",
    seeking: false
  };

  function loadCaptionsPref() {
    try {
      const v = localStorage.getItem(CC_KEY);
      if (v === "off") return false;
    } catch (e) {}
    return true;
  }

  function mediaUrl(fileOrKey) {
    if (!fileOrKey) return "";
    if (typeof fileOrKey === "string") {
      if (/^https?:\/\//i.test(fileOrKey)) return fileOrKey;
      return new URL(fileOrKey.replace(/^\//, ""), state.base).href;
    }
    if (fileOrKey.url) return fileOrKey.url;
    if (fileOrKey.key) return new URL(String(fileOrKey.key).replace(/^\//, ""), state.base).href;
    return "";
  }

  function formatDuration(seconds) {
    const n = Number(seconds);
    if (!Number.isFinite(n) || n < 0) return "0:00";
    const total = Math.round(n);
    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = total % 60;
    const mm = h ? String(m).padStart(2, "0") : String(m);
    const ss = String(s).padStart(2, "0");
    return h ? h + ":" + mm + ":" + ss : mm + ":" + ss;
  }

  function formatAirDate(iso) {
    if (!iso || typeof iso !== "string") return "date unknown";
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso.trim());
    if (!m) return "date unknown";
    const d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
    if (Number.isNaN(d.getTime())) return "date unknown";
    return d.toLocaleDateString("en-US", { year: "numeric", month: "long", day: "numeric" });
  }

  function humanStatus(status) {
    const labels = {
      treated_as_public_domain_pending_review: "Treated as public domain (pending review)"
    };
    if (status && labels[status]) return labels[status];
    if (!status) return "Unknown";
    return String(status).replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
  }

  function posKey(ep) {
    return POS_PREFIX + ep.series_slug + ":" + ep.episode_slug;
  }

  function readPos(ep) {
    try {
      const raw = localStorage.getItem(posKey(ep));
      if (!raw) return 0;
      const data = JSON.parse(raw);
      const t = Number(data.t);
      const dur = Number(ep.duration_seconds) || Number(data.dur) || 0;
      if (!Number.isFinite(t) || t < 3) return 0;
      if (dur && t >= dur - 5) return 0;
      return t;
    } catch (e) {
      return 0;
    }
  }

  function writePos(ep, t, dur) {
    if (!ep) return;
    try {
      localStorage.setItem(posKey(ep), JSON.stringify({ t: t, dur: dur || ep.duration_seconds || 0, at: Date.now() }));
    } catch (e) {}
  }

  function currentTheme() {
    const el = document.documentElement.getAttribute("data-theme");
    if (el === "light" || el === "dark" || el === "auto") return el;
    try {
      const stored = localStorage.getItem(THEME_KEY);
      if (stored === "light" || stored === "dark" || stored === "auto") return stored;
    } catch (e) {}
    return "auto";
  }

  function applyTheme(theme) {
    if (theme !== "light" && theme !== "dark" && theme !== "auto") theme = "auto";
    document.documentElement.setAttribute("data-theme", theme);
    try { localStorage.setItem(THEME_KEY, theme); } catch (e) {}
    let dark = false;
    try {
      dark = theme === "dark" || (theme !== "light" && window.matchMedia("(prefers-color-scheme: dark)").matches);
    } catch (e) {}
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute("content", dark ? "#071526" : "#0A3161");
    document.querySelectorAll(".otr-theme [data-theme-value]").forEach((btn) => {
      btn.setAttribute("aria-pressed", btn.getAttribute("data-theme-value") === theme ? "true" : "false");
    });
  }

  function showBanner(msg) {
    const el = $("otr-banner");
    if (!el) return;
    if (!msg) {
      el.hidden = true;
      el.textContent = "";
      return;
    }
    el.hidden = false;
    el.textContent = msg;
  }

  function setStatus(msg) {
    const el = $("otr-player-status");
    if (el) el.textContent = msg;
  }

  function parseVttTime(raw) {
    const s = String(raw).trim().split(/\s+/)[0];
    const parts = s.split(":");
    if (parts.length === 3) return Number(parts[0]) * 3600 + Number(parts[1]) * 60 + parseFloat(parts[2]);
    if (parts.length === 2) return Number(parts[0]) * 60 + parseFloat(parts[1]);
    return NaN;
  }

  function parseVtt(text) {
    const cues = [];
    const blocks = String(text).replace(/^\uFEFF/, "").replace(/\r\n/g, "\n").split(/\n\n+/);
    for (const block of blocks) {
      const lines = block.split("\n").filter((line) => line.length);
      if (!lines.length) continue;
      if (/^WEBVTT/i.test(lines[0]) || /^NOTE\b/i.test(lines[0]) || /^STYLE\b/i.test(lines[0])) continue;
      let i = 0;
      if (lines[0].indexOf("-->") === -1 && lines[1] && lines[1].indexOf("-->") !== -1) i = 1;
      const timing = lines[i];
      if (!timing || timing.indexOf("-->") === -1) continue;
      const bits = timing.split("-->");
      const start = parseVttTime(bits[0]);
      const end = parseVttTime(bits[1]);
      const cueText = lines.slice(i + 1).join(" ").replace(/<[^>]+>/g, "").trim();
      if (Number.isFinite(start) && Number.isFinite(end) && cueText) cues.push({ start: start, end: end, text: cueText });
    }
    return cues;
  }

  function renderCaptions(text) {
    const box = $("otr-captions");
    if (!box) return;
    if (!state.captionsOn || !text) {
      box.hidden = true;
      box.textContent = "";
      state.cueText = "";
      return;
    }
    if (text === state.cueText && !box.hidden) return;
    state.cueText = text;
    box.hidden = false;
    box.textContent = text;
  }

  function cueAt(time) {
    const media = $("otr-media");
    if (media && media.textTracks && media.textTracks.length) {
      const track = media.textTracks[0];
      if (track && track.activeCues && track.activeCues.length) {
        const parts = [];
        for (let i = 0; i < track.activeCues.length; i++) parts.push(track.activeCues[i].text);
        return parts.join(" ").replace(/<[^>]+>/g, "").trim();
      }
      if (track && (track.mode === "showing" || track.mode === "hidden") && track.cues && track.cues.length) {
        /* fall through if none active */
      } else if (state.cues.length) {
        /* use parsed fallback */
      } else {
        return "";
      }
    }
    for (let i = 0; i < state.cues.length; i++) {
      const c = state.cues[i];
      if (time >= c.start && time < c.end) return c.text;
    }
    return "";
  }

  function updateCaptionBox() {
    const media = $("otr-media");
    renderCaptions(cueAt(media ? media.currentTime : 0));
  }

  function setCaptionsOn(on) {
    state.captionsOn = !!on;
    try { localStorage.setItem(CC_KEY, state.captionsOn ? "on" : "off"); } catch (e) {}
    const btn = $("otr-cc");
    if (btn) {
      btn.setAttribute("aria-pressed", state.captionsOn ? "true" : "false");
      btn.setAttribute("aria-label", state.captionsOn ? "CC, captions on" : "CC, captions off");
    }
    const media = $("otr-media");
    if (media && media.textTracks) {
      for (let i = 0; i < media.textTracks.length; i++) {
        media.textTracks[i].mode = "hidden";
      }
    }
    updateCaptionBox();
  }

  function artworkFor(series, episode) {
    const epArt = episode && episode.artwork;
    const seriesArt = series && series.artwork;
    return mediaUrl(epArt || seriesArt);
  }

  function renderSeries() {
    const root = $("otr-series");
    root.innerHTML = "";
    (state.catalog.series || []).forEach((series) => {
      const count = (series.episodes || []).length;
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "otr-series-card";
      btn.setAttribute("role", "listitem");
      btn.setAttribute("aria-current", state.series && state.series.slug === series.slug ? "true" : "false");
      const img = document.createElement("img");
      img.src = artworkFor(series);
      img.alt = series.artwork && series.artwork.is_placeholder
        ? series.title + " placeholder artwork"
        : series.title + " artwork";
      img.loading = "lazy";
      const title = document.createElement("strong");
      title.textContent = series.title;
      const meta = document.createElement("span");
      meta.textContent = count + (count === 1 ? " episode" : " episodes");
      btn.append(img, title, meta);
      btn.addEventListener("click", () => selectSeries(series.slug, { play: false, setHash: true }));
      root.appendChild(btn);
    });
  }

  function renderEpisodes() {
    const panel = $("otr-episodes-panel");
    const list = $("otr-episodes");
    const title = $("otr-series-title");
    const meta = $("otr-series-meta");
    if (!state.series) {
      panel.hidden = true;
      list.innerHTML = "";
      return;
    }
    panel.hidden = false;
    title.textContent = state.series.title;
    meta.textContent = state.series.network_or_distribution || "";
    list.innerHTML = "";
    (state.series.episodes || []).forEach((ep) => {
      const li = document.createElement("li");
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "otr-episode";
      btn.setAttribute("aria-current", state.episode && state.episode.episode_slug === ep.episode_slug ? "true" : "false");
      const name = document.createElement("span");
      name.className = "otr-episode-name";
      name.textContent = ep.title;
      const date = document.createElement("span");
      date.className = "otr-episode-date";
      date.textContent = formatAirDate(ep.air_date);
      const dur = document.createElement("span");
      dur.className = "otr-episode-dur";
      dur.textContent = formatDuration(ep.duration_seconds);
      btn.append(name, dur, date);
      btn.addEventListener("click", () => selectEpisode(state.series.slug, ep.episode_slug, { play: true, setHash: true }));
      li.appendChild(btn);
      list.appendChild(li);
    });
  }

  function listItem(label, node) {
    const wrap = document.createElement("div");
    const dt = document.createElement("dt");
    dt.textContent = label;
    const dd = document.createElement("dd");
    if (typeof node === "string") dd.textContent = node;
    else dd.appendChild(node);
    wrap.append(dt, dd);
    return wrap;
  }

  function renderNotes(series, ep) {
    const aside = $("otr-notes");
    const body = $("otr-notes-body");
    body.innerHTML = "";
    const dl = document.createElement("dl");

    const air = document.createElement("div");
    air.appendChild(document.createTextNode(formatAirDate(ep.air_date)));
    if (ep.air_date_notes) {
      const p = document.createElement("p");
      p.className = "otr-muted";
      p.style.marginTop = "0.35rem";
      p.textContent = ep.air_date_notes;
      air.appendChild(p);
    }
    dl.appendChild(listItem("Air date", air));

    const sourceWork = ep.source_work;
    if (sourceWork && (sourceWork.title || sourceWork.author)) {
      const bits = [];
      if (sourceWork.title) bits.push(sourceWork.title);
      if (sourceWork.author) bits.push("by " + sourceWork.author);
      dl.appendChild(listItem("Source work", bits.join(" ")));
    }

    const castBox = document.createElement("div");
    const cast = Array.isArray(ep.cast) ? ep.cast : [];
    if (cast.length) {
      const ul = document.createElement("ul");
      cast.forEach((person) => {
        const li = document.createElement("li");
        const name = person.name || "Unknown";
        const role = person.role ? " — " + person.role : "";
        li.appendChild(document.createTextNode(name + role));
        if (person.source) {
          const src = document.createElement("div");
          src.className = "otr-muted";
          src.textContent = "Source: " + person.source;
          li.appendChild(src);
        }
        ul.appendChild(li);
      });
      castBox.appendChild(ul);
    } else {
      const p = document.createElement("p");
      p.textContent = "Cast not confirmed for this circulating recording.";
      castBox.appendChild(p);
    }
    if (ep.cast_notes) {
      const notes = document.createElement("p");
      notes.className = "otr-muted";
      notes.style.marginTop = "0.4rem";
      notes.textContent = ep.cast_notes;
      castBox.appendChild(notes);
    }
    dl.appendChild(listItem("Cast", castBox));

    const rights = ep.rights || {};
    const rightsBox = document.createElement("div");
    const pill = document.createElement("span");
    pill.className = "otr-pill";
    pill.textContent = humanStatus(rights.status);
    rightsBox.appendChild(pill);
    if (rights.is_legal_verdict === false) {
      const notVerdict = document.createElement("p");
      notVerdict.className = "otr-muted";
      notVerdict.style.marginTop = "0.4rem";
      notVerdict.textContent = "This is a research starting point, not a legal verdict.";
      rightsBox.appendChild(notVerdict);
    }
    if (Array.isArray(rights.evidence) && rights.evidence.length) {
      const ul = document.createElement("ul");
      rights.evidence.forEach((item) => {
        const li = document.createElement("li");
        li.textContent = item;
        ul.appendChild(li);
      });
      rightsBox.appendChild(ul);
    }
    if (Array.isArray(rights.caveats) && rights.caveats.length) {
      const caveat = document.createElement("div");
      caveat.className = "otr-caveat";
      caveat.textContent = rights.caveats.join(" ");
      rightsBox.appendChild(caveat);
    }
    dl.appendChild(listItem("Rights", rightsBox));

    const archive = ep.archive || {};
    if (archive.item_url) {
      const a = document.createElement("a");
      a.href = archive.item_url;
      a.target = "_blank";
      a.rel = "noopener noreferrer";
      a.textContent = archive.item_id ? "Internet Archive: " + archive.item_id : "Open source on Internet Archive";
      dl.appendChild(listItem("Source", a));
    }

    const capNote = document.createElement("p");
    capNote.className = "otr-caption-note";
    capNote.textContent = "Captions are a first-pass auto transcript and may mishear names or opening lines.";
    body.append(dl, capNote);
    aside.hidden = false;
  }

  function setHash(seriesSlug, episodeSlug) {
    const next = episodeSlug
      ? "#/" + encodeURIComponent(seriesSlug) + "/" + encodeURIComponent(episodeSlug)
      : seriesSlug
        ? "#/" + encodeURIComponent(seriesSlug)
        : "#";
    if (location.hash !== next) history.replaceState(null, "", next);
  }

  function parseHash() {
    const raw = (location.hash || "").replace(/^#/, "").replace(/^\/+/, "");
    if (!raw) return { series: null, episode: null };
    const parts = raw.split("/").map((p) => decodeURIComponent(p));
    return { series: parts[0] || null, episode: parts[1] || null };
  }

  function selectSeries(slug, opts) {
    opts = opts || {};
    const series = (state.catalog.series || []).find((s) => s.slug === slug);
    if (!series) return;
    state.series = series;
    renderSeries();
    renderEpisodes();
    if (opts.setHash && !opts.keepEpisode) setHash(series.slug, state.episode && state.episode.series_slug === series.slug ? state.episode.episode_slug : null);
  }

  function findEpisode(seriesSlug, episodeSlug) {
    const series = (state.catalog.series || []).find((s) => s.slug === seriesSlug);
    if (!series) return { series: null, episode: null };
    const episode = (series.episodes || []).find((e) => e.episode_slug === episodeSlug);
    return { series: series, episode: episode || null };
  }

  async function selectEpisode(seriesSlug, episodeSlug, opts) {
    opts = opts || {};
    const found = findEpisode(seriesSlug, episodeSlug);
    if (!found.series || !found.episode) return;
    const prev = state.episode;
    const media = $("otr-media");
    if (prev && media && !media.paused) writePos(prev, media.currentTime, media.duration);

    state.series = found.series;
    state.episode = found.episode;
    renderSeries();
    renderEpisodes();
    if (opts.setHash) setHash(found.series.slug, found.episode.episode_slug);

    $("otr-empty").hidden = true;
    $("otr-player-card").hidden = false;

    const art = artworkFor(found.series, found.episode);
    const poster = $("otr-poster");
    poster.src = art;
    poster.alt = found.series.artwork && found.series.artwork.is_placeholder
      ? found.series.title + " placeholder artwork"
      : found.series.title + " artwork";

    $("otr-episode-title").textContent = found.episode.title;
    $("otr-episode-series").textContent = found.series.title;
    $("otr-duration").textContent = formatDuration(found.episode.duration_seconds);
    $("otr-seek").max = String(found.episode.duration_seconds || 0);
    $("otr-seek").value = "0";
    $("otr-time").textContent = "0:00";
    renderNotes(found.series, found.episode);
    state.cues = [];
    state.cueText = "";
    renderCaptions("");

    const audio = mediaUrl(found.episode.files && found.episode.files.audio);
    const captions = mediaUrl(found.episode.files && found.episode.files.captions);
    if (!audio) {
      showBanner("This episode is missing an audio file.");
      return;
    }

    media.pause();
    media.removeAttribute("src");
    while (media.firstChild) media.removeChild(media.firstChild);
    media.poster = art;
    const source = document.createElement("source");
    source.src = audio;
    source.type = (found.episode.files.audio && found.episode.files.audio.content_type) || "audio/mp4";
    media.appendChild(source);
    if (captions) {
      const track = document.createElement("track");
      track.kind = "captions";
      track.srclang = "en";
      track.label = "English (auto)";
      track.default = true;
      track.src = captions;
      media.appendChild(track);
    }
    media.load();

    if (captions) {
      fetch(captions)
        .then((res) => {
          if (!res.ok) throw new Error("captions " + res.status);
          return res.text();
        })
        .then((text) => {
          if (state.episode === found.episode) state.cues = parseVtt(text);
        })
        .catch(() => {});
    }

    const startAt = readPos(found.episode);
    const onReady = () => {
      if (state.episode !== found.episode) return;
      if (startAt) {
        try { media.currentTime = startAt; } catch (e) {}
        setStatus("Resumed " + found.episode.title + " at " + formatDuration(startAt));
      }
      if (opts.play) {
        media.play().catch(() => setStatus("Press play to start."));
      }
      updateMediaSession();
    };
    if (media.readyState >= 1) onReady();
    else media.addEventListener("loadedmetadata", onReady, { once: true });

    bindTextTrack(media);
    setCaptionsOn(state.captionsOn);
    updatePlayButton();
    updateMediaSession();
  }

  function bindTextTrack(media) {
    const hook = () => {
      if (!media.textTracks) return;
      for (let i = 0; i < media.textTracks.length; i++) {
        const t = media.textTracks[i];
        t.mode = "hidden";
        t.oncuechange = updateCaptionBox;
      }
    };
    hook();
    media.textTracks && media.textTracks.addEventListener && media.textTracks.addEventListener("addtrack", hook);
  }

  function updatePlayButton() {
    const media = $("otr-media");
    const btn = $("otr-play");
    const playing = media && !media.paused && !media.ended;
    btn.textContent = playing ? "Pause" : "Play";
    btn.setAttribute("aria-label", playing ? "Pause" : "Play");
  }

  function togglePlay() {
    const media = $("otr-media");
    if (!state.episode) return;
    if (media.paused) media.play().catch(() => {});
    else media.pause();
  }

  function skip(seconds) {
    const media = $("otr-media");
    if (!state.episode) return;
    const dur = Number.isFinite(media.duration) ? media.duration : (state.episode.duration_seconds || 0);
    const next = Math.min(Math.max(0, (media.currentTime || 0) + seconds), dur || media.currentTime + seconds);
    media.currentTime = next;
    updateTimes();
    updateCaptionBox();
  }

  function updateTimes() {
    const media = $("otr-media");
    if (!media) return;
    const dur = Number.isFinite(media.duration) && media.duration > 0
      ? media.duration
      : (state.episode && state.episode.duration_seconds) || 0;
    if (!state.seeking) {
      $("otr-seek").max = String(dur || 0);
      $("otr-seek").value = String(media.currentTime || 0);
    }
    $("otr-time").textContent = formatDuration(media.currentTime || 0);
    $("otr-duration").textContent = formatDuration(dur);
  }

  function updateMediaSession() {
    if (!("mediaSession" in navigator) || !state.episode || !state.series) return;
    const art = artworkFor(state.series, state.episode);
    const artwork = art ? [{ src: art, sizes: "1400x1400", type: "image/jpeg" }] : [];
    try {
      navigator.mediaSession.metadata = new MediaMetadata({
        title: state.episode.title,
        artist: state.series.title,
        album: "OTRadio",
        artwork: artwork
      });
    } catch (e) {}
    const set = (action, handler) => {
      try { navigator.mediaSession.setActionHandler(action, handler); } catch (e) {}
    };
    set("play", () => { $("otr-media").play().catch(() => {}); });
    set("pause", () => { $("otr-media").pause(); });
    set("seekbackward", (d) => skip(-(d && d.seekOffset) || -SKIP));
    set("seekforward", (d) => skip((d && d.seekOffset) || SKIP));
    set("seekto", (d) => {
      if (!d) return;
      if (d.fastSeek && $("otr-media").fastSeek) $("otr-media").fastSeek(d.seekTime);
      else $("otr-media").currentTime = d.seekTime;
    });
    try { navigator.mediaSession.playbackState = $("otr-media").paused ? "paused" : "playing"; } catch (e) {}
  }

  function bindPlayer() {
    const media = $("otr-media");
    $("otr-play").addEventListener("click", togglePlay);
    $("otr-back-30").addEventListener("click", () => skip(-SKIP));
    $("otr-fwd-30").addEventListener("click", () => skip(SKIP));
    $("otr-cc").addEventListener("click", () => setCaptionsOn(!state.captionsOn));
    $("otr-speed").addEventListener("change", (e) => {
      const rate = Number(e.target.value) || 1;
      media.playbackRate = rate;
      try { localStorage.setItem(SPEED_KEY, String(rate)); } catch (err) {}
    });
    try {
      const saved = Number(localStorage.getItem(SPEED_KEY));
      if (saved && [0.75, 1, 1.25, 1.5, 2].indexOf(saved) !== -1) {
        $("otr-speed").value = String(saved);
        media.playbackRate = saved;
      }
    } catch (e) {}

    const seek = $("otr-seek");
    seek.addEventListener("pointerdown", () => { state.seeking = true; });
    seek.addEventListener("pointerup", () => { state.seeking = false; });
    seek.addEventListener("input", () => {
      state.seeking = true;
      $("otr-time").textContent = formatDuration(Number(seek.value) || 0);
    });
    seek.addEventListener("change", () => {
      media.currentTime = Number(seek.value) || 0;
      state.seeking = false;
      updateTimes();
      updateCaptionBox();
    });

    media.addEventListener("play", () => { updatePlayButton(); updateMediaSession(); setStatus("Playing " + (state.episode && state.episode.title || "")); });
    media.addEventListener("pause", () => {
      updatePlayButton();
      updateMediaSession();
      if (state.episode) writePos(state.episode, media.currentTime, media.duration);
      setStatus("Paused");
    });
    media.addEventListener("timeupdate", () => {
      updateTimes();
      updateCaptionBox();
      if (state.episode && !media.paused) writePos(state.episode, media.currentTime, media.duration);
      if ("mediaSession" in navigator && navigator.mediaSession.setPositionState) {
        try {
          const dur = Number.isFinite(media.duration) ? media.duration : (state.episode && state.episode.duration_seconds) || 0;
          if (dur) navigator.mediaSession.setPositionState({ duration: dur, playbackRate: media.playbackRate || 1, position: Math.min(media.currentTime || 0, dur) });
        } catch (e) {}
      }
    });
    media.addEventListener("ended", () => {
      updatePlayButton();
      if (state.episode) writePos(state.episode, 0, media.duration);
      setStatus("Finished " + (state.episode && state.episode.title || ""));
    });
    media.addEventListener("error", () => {
      showBanner("The audio could not be loaded. Check your connection and try again.");
    });
    media.addEventListener("loadedmetadata", updateTimes);

    document.addEventListener("visibilitychange", () => {
      if (document.hidden && state.episode) writePos(state.episode, media.currentTime, media.duration);
    });
    window.addEventListener("pagehide", () => {
      if (state.episode) writePos(state.episode, media.currentTime, media.duration);
    });
  }

  function bindKeyboard() {
    document.addEventListener("keydown", (e) => {
      if (!document.querySelector(".otr-player")) return;
      const target = e.target;
      if (!target || !target.closest || !target.closest(".otr-player")) return;
      if (target.closest("a, .theme, .skip-link")) return;
      const tag = (target.tagName) || "";
      const isField = tag === "INPUT" || tag === "SELECT" || tag === "TEXTAREA";
      if (e.key === " " || e.code === "Space" || e.key === "k" || e.key === "K") {
        if (tag === "BUTTON" || tag === "SELECT" || (tag === "INPUT" && e.target.type !== "range")) return;
        if (tag === "INPUT" && e.target.type === "range" && e.key !== " " && e.code !== "Space") return;
        if (e.key === " " || e.code === "Space") {
          e.preventDefault();
          togglePlay();
        } else if (!isField) {
          e.preventDefault();
          togglePlay();
        }
        return;
      }
      if (isField && tag === "INPUT" && e.target.type === "range") return;
      if (isField && tag === "SELECT") return;
      if (e.key === "ArrowLeft") { e.preventDefault(); skip(e.shiftKey ? -SKIP : -10); }
      else if (e.key === "ArrowRight") { e.preventDefault(); skip(e.shiftKey ? SKIP : 10); }
      else if (e.key === "j" || e.key === "J") { e.preventDefault(); skip(-SKIP); }
      else if (e.key === "l" || e.key === "L") { e.preventDefault(); skip(SKIP); }
      else if (e.key === "c" || e.key === "C") { e.preventDefault(); setCaptionsOn(!state.captionsOn); }
      else if (e.key === "Home") { e.preventDefault(); $("otr-media").currentTime = 0; updateTimes(); }
      else if (e.key === "End" && state.episode) {
        e.preventDefault();
        const dur = $("otr-media").duration || state.episode.duration_seconds || 0;
        $("otr-media").currentTime = Math.max(0, dur - 1);
        updateTimes();
      }
    });
  }

  function bindTheme() {
    applyTheme(currentTheme());
    document.querySelectorAll(".otr-theme [data-theme-value]").forEach((btn) => {
      btn.addEventListener("click", () => applyTheme(btn.getAttribute("data-theme-value")));
    });
    try {
      window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", () => applyTheme(currentTheme()));
    } catch (e) {}
  }

  async function init() {
    bindTheme();
    bindPlayer();
    bindKeyboard();
    setCaptionsOn(state.captionsOn);
    showBanner("");
    try {
      const res = await fetch(CATALOG_URL, { mode: "cors" });
      if (!res.ok) throw new Error("Catalog request failed (" + res.status + ")");
      const catalog = await res.json();
      state.catalog = catalog;
      if (catalog.public_base_url) {
        state.base = String(catalog.public_base_url).replace(/\/?$/, "/");
      }
      renderSeries();
      const hash = parseHash();
      if (hash.series && hash.episode) {
        await selectEpisode(hash.series, hash.episode, { play: false, setHash: true });
      } else if (hash.series) {
        selectSeries(hash.series, { setHash: true });
      }
    } catch (err) {
      showBanner("Could not load the OTRadio catalog. " + (err && err.message ? err.message : "Please try again."));
    }
    window.addEventListener("hashchange", () => {
      const hash = parseHash();
      if (hash.series && hash.episode) selectEpisode(hash.series, hash.episode, { play: false, setHash: false });
      else if (hash.series) selectSeries(hash.series, { setHash: false });
    });
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();
})();
