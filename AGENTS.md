# Standards for tylilo.com

Every change to this site must meet WCAG 2.2 Level AA and stay easy for search engines to understand. These rules apply to new pages, copy edits, and visual tweaks.

The legal name is written **Tylilo, LLC**, with a comma. Use `TYLILO_LEGAL_NAME` from `config.php` for the footer, meta text, and JSON-LD. When the name is typed out, keep the comma.

## Accessibility

- Use semantic HTML: one `h1`, headings in order, and landmarks for `header`, `main`, and `footer`. Add `nav` when a group of links is navigation.
- The first focusable control is a “Skip to content” link that becomes visible on focus and moves focus to `main#main`.
- Every link, button, input, and theme control shows a clear `:focus-visible` outline in light and dark.
- Text meets 4.5:1 contrast. Large text and UI boundaries, including focus rings and field borders, meet 3:1. Check both themes. Brand navy is `#0A3161`.
- Honor `prefers-reduced-motion`: do not rely on animation, and do not leave transitions running.
- Forms use visible labels tied to inputs, autocomplete, `required`, `aria-invalid`, and errors linked with `aria-describedby`. Success and errors sit in an `aria-live` region. The honeypot stays in the form but is hidden from assistive tech (`aria-hidden`, `tabindex="-1"`, `autocomplete="off"`).
- Images have useful alt text. The theme switch has an accessible name and a pressed state.
- Layout reflows at 320px wide and at 200% zoom without sideways scrolling. Tap targets are at least 24×24 CSS pixels, and 44×44 where the control can grow without breaking the layout.
- `/accessibility` states the WCAG 2.2 AA target and how to report a barrier. Keep that page linked from the footer.
- Every significant site change must review and update `/privacy`, including its effective date. Do this especially when forms, cookies, storage, analytics, embeds, or third-party services change. Turning OTRadio back on is one of those changes: restore its privacy section (on-device caption, speed, and position storage, and media loaded from `pub-a3eee908554d4a948d807c160caa454e.r2.dev`) and update the effective date. The steps are in the README’s OTRadio section.

## SEO

- Each page has its own title, meta description, canonical URL, and Open Graph tags.
- Add structured data when the page is a real entity (the company on the homepage, a `WebPage` for a standalone document).
- Add every new public URL to `sitemap.xml`. Keep `robots.txt` valid, allow public pages, and point it at the sitemap.
- Keep pages fast: small CSS and images, no unused frameworks, and a stable layout so Core Web Vitals stay healthy.
- Follow current indexing guidance from Google, Bing, DuckDuckGo, and Brave: crawlable HTML, unique titles and descriptions, accurate canonicals, and no doorway or hidden-text tricks.

## Arcade

Each game is one folder, `/arcade/<slug>/index.html`. The list on `/arcade/` is the `$games` array in `arcade/index.php`: title, one-line description, path, and cover art. Adding a game is one new entry, in the order it should appear, plus `arcade/<slug>/cover-400.webp` and `arcade/<slug>/cover-800.webp`. The tile name is the accessible text, so the artwork stays decorative (`alt=""`).

Every game opens in the same tab. Do not add `target="_blank"`, a new-window hint, or a window icon to a game link. The homepage Arcade tile also opens `/arcade/` in the same tab. Speculation rules prefetch `a.game-tile` and do not prerender those links, so a game’s audio and loop do not start in the background.

Each tile has a Top scores button in the article, after the link, not inside it. It loads `/api/scores.php` and shows that game’s boards. The button’s accessible name includes the game title.

Game files come from their own maintainers. Drop the new `index.html` in unchanged. Do not edit it in this repo. Games may keep settings and high scores in the browser’s local storage and must not load outside resources. When a game stores something new, or starts submitting to the leaderboard, describe it on `/privacy` and update the effective date if the policy text changes.

## Leaderboard API

`/api/scores.php` is the shared leaderboard for game bots. It is same-origin JSON. `GET` is public. `POST` must come from this host: the browser sends `Origin` on a page fetch, and a non-browser bot must send `Origin` or `Referer` whose host matches the request `Host`. There is no cookie and no account. Storage is a SQLite file when PHP has PDO SQLite, otherwise a flocked JSON file. It is written to `tylilo-data/` next to the site root when that directory is outside the web root, or to `data/` inside the site (which `.htaccess` does not serve). The deploy excludes `data/` and `tests/`. Set `TYLILO_DATA_DIR` to move it. Do not commit the data directory. The raw network address is not stored. A HMAC of it is kept for at most 10 minutes of rate-limit history.

Slugs and boards:

| Game | `game` | Boards | Order | Unit | Range | Minimum play |
| --- | --- | --- | --- | --- | --- | --- |
| Solitaire | `solitaire` | `draw-1`, `draw-3` | higher wins | points | 0–20000 | 20 seconds |
| Red Rain | `red-rain` | `easy`, `normal`, `expert`, `insane` | higher wins | points | 0–5000000 | 30 seconds |
| Pop Spiral | `pop-spiral` | `default` | higher wins | points | 0–5000000 | 20 seconds |
| Jungle Rescue | `jungle-rescue` | `easy`, `normal`, `expert` | higher wins | points | 0–5000000 | 20 seconds |
| Jungle Rescue | `jungle-rescue` | `easy-time`, `normal-time`, `expert-time` | lower wins | seconds | 1–7200 | 20 seconds |

Jungle Rescue has one score board and one time board per difficulty (labels `Easy`, `Normal`, `Expert`, `Easy time`, `Normal time`, `Expert time`). A time is a completion time in whole seconds. Pop Spiral, Jungle Rescue, and Red Rain report a level (`'levels' => true` in the game config). Their boards are the ones that show it. Solitaire does not.

Each Jungle Rescue board also has `group` (the difficulty) and `short` (`Score` or `Time`) in the `GET` response. The Arcade groups boards that have `group`: Easy shows Score and Time together, then Normal, then Expert. Boards without `group` (every other game) show one after another as before.

Legacy aliases. A game can list `'aliases' => ['old-board' => 'new-board']` in `tylilo_score_games()`. An alias still accepts `op:start` and `op:submit`, but it is never listed (not in a `GET` without `board`, not in the Arcade, not on the admin pages), and its scores are stored on the target board. Jungle Rescue has two, kept so v2.17 and older keep saving: `default` → `easy` and `time` → `easy-time`. Tokens stay bound to the board that was asked for, so a token from `op:start` with `board:"default"` must be submitted with `board:"default"` (a submit to `easy` with that token is HTTP 400). `op:start` on an alias also returns `"storedAs":"easy"`. `GET ...&board=default` returns the `easy` board. A seed on an alias board is stored on its target. Remove an alias only when no live build still sends it.

One-time migrations. `tylilo_score_migrations()` lists data changes that run once per store, right after the seeds, and are then remembered by id in the same applied list as the seeds (the `seeds` table in SQLite, the `seeds` array in JSON). They work the same on both stores and must be safe on an empty store. `jungle-rescue-difficulty-split-2026-11-21` moved every Jungle Rescue row on `default` to `easy` and on `time` to `easy-time` (those runs had no difficulty recorded), and deleted LOGAN 187100, a cheat-code run, at Ryan’s request on Nov 21, 2026. The `logan-jungle-rescue-187100` seed was removed so it cannot come back. Do not re-add it.

Read the top scores:

`GET /api/scores.php?game=solitaire&board=draw-1&limit=10`

`board` is optional. Omit it to get every board for that game. `limit` defaults to 10 and is capped at 25. A missing game or board is HTTP 404.

```json
{"ok":true,"game":"solitaire","limit":10,"boards":[{"board":"draw-1","label":"Draw 1","order":"desc","unit":"points","levels":false,"scores":[{"name":"Ada","score":1500,"at":"2026-11-21T12:00:00+00:00","level":null}]}]}
```

`levels` is true only for games that report a level. Every public score includes `level`. It is `null` when the run did not send one. Unlike `version`, `level` is public.

Start a run, once, when the run begins:

`POST /api/scores.php` with `Content-Type: application/json`

```json
{"op":"start","game":"solitaire","board":"draw-1"}
```

```json
{"ok":true,"token":"<signed>","minPlayedMs":20000,"game":"solitaire","board":"draw-1"}
```

The token is `<base64url payload>.<hex hmac-sha256>`. It is tied to that game and board, lasts 6 hours, and works once. `minPlayedMs` is the shortest run this board will accept.

Save the score after the run has lasted at least that long in real time:

```json
{"op":"submit","game":"solitaire","board":"draw-1","name":"Ada","score":1500,"playedMs":45000,"version":"1.1.6","token":"<signed>"}
```

A game that reports levels can send the optional `level` as a JSON whole number from 1 to 999:

```json
{"op":"submit","game":"pop-spiral","board":"default","name":"Ada","score":1500,"playedMs":30000,"version":"0.2.5","level":7,"token":"<signed>"}
```

`level` is optional. Omit it, or send `null` or `""`, and the score is stored with `level` null. A present value must be a JSON integer from 1 to 999. Strings, floats, booleans, `0`, and `1000` are HTTP 400 with `Level must be a whole number from 1 to 999.` The stored value is kept in both the SQLite and JSON stores. Older rows stay null. The public `GET` and the `scores` array in a successful submit include `level`. The Arcade shows `Lv 7` only on boards whose game has `levels` set. The private admin Games page and the leaderboard moderation page show it too.

`version` is optional. Omit it, or send `null` or `""`, and the score is stored with no version. When present it is a string of 1–16 characters from `0-9`, `A-Z`, `a-z`, `.`, `_`, and `-` only. Anything else is HTTP 400. The public `GET`, and the `scores` array in a successful submit, never include `version`. Older rows stay null. The private admin Games page shows it and can clear one board.

`score` and `playedMs` are whole numbers. `playedMs` must be at least `minPlayedMs`, must not be longer than the token’s age plus 15 seconds, and the token itself must already be at least `minPlayedMs` old. `name` is 1–12 characters: letters, numbers, spaces, and `. ' - ! ? ,`. A fixed word list rejects names after a leetspeak fold (`0`→o, `1`→i, `3`→e, `4`→a, `5`→s, `@`→a, `$`→s, symbols removed, repeated letters collapsed). The check is on the whole name and on each word, not on a substring, so ordinary words that merely contain a fragment still pass. If `board` is omitted on submit it defaults to `default`, which Solitaire and Red Rain do not have.

Cheat runs. A submit whose `cheat` field is truthy (`true`, `1`, or a non-empty string other than `"0"`) is HTTP 400 with `Cheat runs are not saved.`, for every game, before any other check. Nothing is stored and the token is not used up. Games should not submit cheat-code runs at all. The flag is a server-side backstop for a game that sends one. Omit `cheat`, or send `false`, for a normal run.

Success is HTTP 200: `{"ok":true,"rank":1,"scores":[...]}` where `scores` is the top 10 for that board and `rank` counts every stored row, not just the top 10. The site keeps the best 100 rows per board. One network can save 5 scores in 10 minutes, across every game. The sixth is HTTP 429.

Other errors are `{"ok":false,"error":"<plain sentence>"}` with HTTP 400 (name, score, token, play time, or a cheat run), 403 (cross-site POST), 404 (unknown game or board), 405 (not GET or POST), 413 (body over 4096 bytes), or 500 (storage failure). `GET` does not require `Origin`.

The homepage links to `/arcade/` from the tile between About and Contact. The footer does too.

## OTRadio

The player files in `otradio/` stay in the repo. While `TYLILO_OTRADIO_ENABLED` in `config.php` is `false`, `/otradio/` returns the site 404 page, sends `noindex`, is omitted from `sitemap.xml`, and is not linked from the footer. The script, stylesheet, and fonts under `otradio/` remain available. To publish the player again, set that flag to `true`, add `https://tylilo.com/otradio/` to `sitemap.xml`, and restore the OTRadio section on `/privacy`: on-device caption, speed, and position storage, plus catalog, audio, captions, and artwork loaded from `pub-a3eee908554d4a948d807c160caa454e.r2.dev`. Update the effective date on `/privacy` when you do. The footer link follows the flag.

## Pre-merge checklist

Run this before asking Ryan to merge:

1. axe or pa11y on each changed page, with no new serious issues.
2. Lighthouse accessibility and SEO on those pages.
3. Keyboard only: tab through the page, use the skip link, the theme switch, and the form.
4. Light theme and dark theme.
5. 200% zoom and a 320px-wide window, with no sideways scroll.
6. If the change affects what the site collects or loads, update `/privacy` and its effective date.
