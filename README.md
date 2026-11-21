# tylilo.com

Company and studio site for Tylilo, LLC. The first version is a single homepage: a navy hero, a short About section, and a contact form. It is plain HTML, CSS, and JavaScript plus a small PHP contact handler, so HostM can serve it with no build step.

The look is the approved “style B, bold playful” design: big navy hero, the round logo as a badge, soft blob shapes, cream cards, and pill-shaped buttons. A Light / Dark / Auto switch remembers the choice in this browser. Auto follows the device setting. The saved choice is applied before the page paints, so it does not flash the wrong theme.

## Preview locally

You need PHP, which HostM already runs. On your own computer, check with:

```bash
php -v
```

If that fails, install PHP from [php.net](https://www.php.net/downloads) (the CLI is enough). Then, from this folder:

```bash
php -S localhost:8000 router.php
```

Open [http://localhost:8000](http://localhost:8000).

`router.php` is only for that local server. It lets unknown addresses show the 404 page. Apache on HostM uses `.htaccess` instead, and `router.php` is not uploaded.

The contact form needs PHP, so opening the files directly from Finder or File Explorer will not run it. Use the command above.

## Edit the placeholder text

The tagline on the homepage is **Under Construction**. It is the `$tagline` line at the top of `index.php`.

Ryan has not written the About copy yet. Those stand-in words are in the same block:

- About heading
- About paragraph

The browser title stays **Tylilo**. The About paragraph is also the page description used for search and link previews. Change that paragraph once and both update. The tagline is not copied into the title or the description.

### Contact email

`hello@tylilo.com` is a placeholder mailbox. It lives in **one** place, `TYLILO_CONTACT_EMAIL` in `config.php`. The form emails that mailbox. The address is not shown on the site, and there is no mailto link. Create that mailbox in cPanel (or change the constant to a mailbox that already exists) before you rely on the form. Hosting mail filters often drop messages whose From address is not a real mailbox on the account.

The footer reads `© 2026 Tylilo, LLC` from `TYLILO_YEAR` and `TYLILO_LEGAL_NAME` in the same file.

## How the contact form works

The form posts to `contact.php`. The server checks Cloudflare Turnstile before the other checks, then emails the result to `TYLILO_CONTACT_EMAIL` with PHP `mail()`. Reply goes to the person who wrote in.

- Turnstile’s public sitekey is in the homepage. The secret is not in this repository. Deploy writes it from the GitHub Actions secret `TURNSTILE_SECRET` beside the web root and also into `data/turnstile.php` and `tylilo-data/turnstile.php` under `FTP_SERVER_DIR`. PHP checks those paths, `DOCUMENT_ROOT`, and the account home, and uses the first file it can read. `data/` and `tylilo-data/` are not served. If that secret is missing, the form rejects the message. The widget stays hidden unless Cloudflare asks the visitor to interact.
- Local tests can set `TURNSTILE_SITEKEY` and `TURNSTILE_SECRET` to Cloudflare’s dummy keys. Do not put those dummy values in `config.php`.
- The private admin area is `/admin`. There is no public sign-up. The first admin is created at `/admin/setup` with the GitHub Actions secret `ADMIN_SETUP_CODE`, which deploy writes to `tylilo-data/admin-setup.php` (or `data/admin-setup.php`). That page closes once an admin exists. Notes, to-dos, and game versions are stored in `admin.sqlite` in the same private directory, which the FTP deploy does not upload.
- A hidden field (`ty_extra`) is a honeypot. People never see it. If it is filled in, the submission is discarded and the page still says thank you, so a bot is not told it was blocked.
- Name, email, and message are required. The email must look like an email. The message can be up to 5,000 characters.
- A session token blocks forged cross-site posts. More than five successful sends in half an hour from the same browser asks the visitor to wait.

Nothing secret is stored in this repository.

## Project layout

```
index.php                 Homepage
accessibility.php         Accessibility statement
privacy.php               Privacy policy
arcade/index.php          Arcade list (one PHP array of cards)
arcade/<slug>/index.html  One self-contained game, dropped in unchanged
404.php                   Missing-page page
contact.php               Form handler
config.php                Email, site name, year
includes/                 Shared header, footer, and form code
assets/css/styles.css     Site styles
assets/js/theme.js        Light / dark / auto switch
assets/js/form.js         Form status and double-submit guard
assets/img/               Logos, favicons, social image
assets/fonts/             Nunito (SIL Open Font License)
otradio/                  OTRadio player (see below)
favicon.ico               Browser icon
site.webmanifest          Home-screen icon info
sitemap.xml               Pages search engines should list
.htaccess                 404 page, caching, and blocked private files
.github/workflows/        Deploy to HostM
router.php                Local preview only
scripts/generate-icons.py Rebuild favicons from the round logo
```

`assets/img/logo-round.webp` is the badge in the hero. `assets/img/logo.svg` is the wide wordmark, ready for later pages. Nunito’s license is `assets/fonts/OFL.txt`. The player ships its own Nunito files; the same license is copied to `otradio/assets/fonts/OFL.txt`.

To add another page, copy the top of `index.php` (the bootstrap and head includes), reuse `includes/topbar.php` inside `<header class="hero">`, and end with `includes/footer.php`. Give the page its own title, description, and canonical URL, and add it to `sitemap.xml`. Set `$pageStylesheets` to an array of stylesheet URLs before including the head when a page needs its own CSS.

## Standards

Every change should meet WCAG 2.2 Level AA and stay in good shape for search. That means semantic HTML, a unique title, meta description, canonical URL, and Open Graph tags on each page, structured data where it fits, an updated `sitemap.xml` for new public pages, and a valid `robots.txt`. Keep pages fast enough for Core Web Vitals, and follow current indexing guidance from Google, Bing, DuckDuckGo, and Brave.

A significant change, especially one that touches forms, cookies, storage, analytics, embeds, or another company’s service, must also review `/privacy` and update its effective date.

The full rules and the pre-merge checklist (axe or pa11y, Lighthouse accessibility and SEO, keyboard-only use, both themes, 200% zoom, and a privacy-page check) are in [AGENTS.md](AGENTS.md).

## Arcade

Games live under `arcade/`, one folder per game. The page at `/arcade/` reads a small array at the top of `arcade/index.php`. To add a game, add one entry there (title, description, URL, artwork) and put the files in `arcade/<slug>/`:

```
arcade/solitaire/index.html   Klondike Solitaire
arcade/solitaire/art.svg      Tile artwork
arcade/red-rain/index.html    Red Rain
arcade/red-rain/art.svg       Tile artwork
arcade/pop-spiral/index.html    Pop Spiral
arcade/pop-spiral/art.svg       Tile artwork
arcade/jungle-rescue/index.html Jungle Rescue
arcade/jungle-rescue/art.svg    Tile artwork
```

The artwork is an SVG. The tile shows it above the game name, and the whole tile is the link. The name is the accessible text, so the image alt stays empty. The one-line description stays in the page for search and screen readers, visually hidden on the tile.

Every game opens in the same tab. The tile link does not use `target="_blank"`, and it has no new-window hint or window icon. The homepage Arcade tile also opens `/arcade/` in the same tab. Game links are prefetched, not prerendered, so the game does not start running in the background.

`index.html` is the whole game. It is maintained elsewhere and replaced as a unit, so leave the file as it arrives. Do not reformat it or point it at other hosts. The folder is served as `/arcade/<slug>/` with or without the trailing slash. `DirectoryIndex` lists `index.html` after `index.php`, so a game folder is not blocked by the PHP index used on the rest of the site.

Red Rain stores music and mute, difficulty, options, and high scores in the browser’s local storage, and the last name entered for the leaderboard under `redRainLbName`. Solitaire stores its draw mode under `klondike.draw`, stats under `klondike.stats` (games played and won, streaks, best score and time for each draw mode, and whether a game is in progress; no personal data), sound on or off under `klondike.muted`, and the last name saved to the leaderboard under `klondike.lbName`. Pop Spiral stores a best score under `popSpiral.best` and its settings under `popSpiral.settings.v1`. Jungle Rescue stores `jungleRescueOpts`, `jungleRescueDiff`, `jungleRescueHi`, `jungleRescueBestDist` and `jungleRescueBestDist2` through `jungleRescueBestDist20`, `jungleRescueUnlocked`, `jungleRescueBeat`, `jungleRescueCards`, and the last name used to save a score under `jungleRescueName`. Options → Reset Progress clears Unlocked, Beat, and Cards. It does not use session storage, cookies, or IndexedDB. None of that on-device storage is sent to Tylilo, and the games do not load scripts, images, or fonts from another site. `/privacy` says so. If a future game stores something else, or starts loading a third-party file, update that section and its effective date.

The shared leaderboard is `/api/scores.php`. Each Arcade tile has a Top scores button under the link. Game files are still not edited here. A maintainer’s bot calls the API from the game page. The exact request and response shapes, board ids, and limits are in [AGENTS.md](AGENTS.md). Scores are stored outside the web root when the host allows it (`tylilo-data/` beside the site), otherwise in `data/`, which is not served and is excluded from the FTP deploy. The site stores the chosen name, score, game, board, and time. It stores a hash of the network address for rate limiting, not the address. `/privacy` describes that.

The homepage has an Arcade tile between About and Contact. It opens this page in the same tab. The footer links to Arcade as well.

## OTRadio

The player is in the repo, but it is hidden until the app is further along. `TYLILO_OTRADIO_ENABLED` in `config.php` is `false`, so `/otradio/` returns the normal 404 page (with `noindex`), the footer does not link to it, and it is not in `sitemap.xml`. The player files stay in place. `otradio/player.js`, `otradio/player.css`, and `otradio/assets/fonts/` are still served, so turning the page back on does not require putting those files back.

To show it again, set `TYLILO_OTRADIO_ENABLED` to `true` and add `https://tylilo.com/otradio/` to `sitemap.xml`. Also restore the OTRadio section on `/privacy` (on-device caption, speed, and position storage, plus catalog, audio, captions, and artwork loaded from `https://pub-a3eee908554d4a948d807c160caa454e.r2.dev`) and update that page’s effective date. The footer link follows the flag. The page is `otradio/index.php`. It uses the same header, footer, skip link, focus ring, and theme as the homepage.

```
otradio/index.php          Page (shared header and footer)
otradio/player.css         Player styles, scoped under .otr-player
otradio/player.js          Catalog, playback, captions
otradio/assets/fonts/      Nunito for this page
```

The catalog address is the `CATALOG_URL` constant at the top of `otradio/player.js`. Episode audio, captions, and artwork resolve from the catalog’s `public_base_url`, which is `https://pub-a3eee908554d4a948d807c160caa454e.r2.dev`. That origin is allowed in the content security policy for this page only (`connect-src` for the catalog and caption files, `media-src` for audio and WebVTT tracks, `img-src` for artwork). Other pages are unchanged. If a different media host is used later, allow that origin in the same three directives.

The header’s theme switch is the only theme control. It uses the `tylilo-theme` value in this browser and sets `data-theme` on the page before the first paint, the same way the homepage does. Player colors are wired to the site’s tokens at the bottom of `player.css` (for example `--otr-navy` follows `--navy`, which stays `#0A3161`). The player’s own theme buttons are not on the page.

Captions start on. The CC button turns them off for this browser. If a caption file cannot be loaded, the player says so and disables that button. Show notes turn a bare web address into a short link (an archived log, a public-domain license, or the site’s domain) and still wrap inside the card if a long word remains. None of that is linked from the public site while the flag above is `false`.

While OTRadio is switched off with `TYLILO_OTRADIO_ENABLED` in `config.php`, `/privacy` does not mention it. Turning it back on requires restoring that privacy section: on-device storage of caption preference, playback speed, and episode position, plus catalog, audio, captions, and artwork loaded from `https://pub-a3eee908554d4a948d807c160caa454e.r2.dev`. Update the effective date on `/privacy` when you restore it.

## Deploy to HostM

Pushes to `main` run **Deploy to HostM** (`.github/workflows/deploy.yml`). The workflow uploads the site over explicit FTPS, which is what HostM documents (FTP with TLS, port 21). You can also run it by hand from the Actions tab with **Run workflow**.

Until the FTP secrets exist, the job stays green and skips the upload. It prints a short “Skipping deploy” note instead of failing.

These are not uploaded: `.git`, `.github`, `README.md`, other Markdown files, `router.php`, `scripts/`, and local junk such as `.DS_Store`. The workflow does not delete unrelated files already on the server.

### 1. Add the GitHub secrets

You need admin access to the GitHub repository.

1. Open the repository on GitHub.
2. Click **Settings**.
3. In the left sidebar, open **Secrets and variables**, then click **Actions**.
4. Click **New repository secret**.
5. Add these three secrets. Paste the values into the secret fields. Do not put them in a file in this repository.

| Name | Value |
| --- | --- |
| `FTP_SERVER` | FTP hostname from HostM |
| `FTP_USERNAME` | cPanel username |
| `FTP_PASSWORD` | cPanel password |

6. Optionally add `FTP_SERVER_DIR`. If you skip it, files go to `public_html/`. Include the trailing slash if you set it (`public_html/`). The workflow adds a missing slash for you. Use this only when the site should live in a subfolder of the account.

Secrets are encrypted and are not shown again after you save them. Updating a secret does not by itself upload the site. After they are saved, either push a commit to `main` or open the Actions tab, select **Deploy to HostM**, and click **Run workflow**.

### 2. Find the FTP details in HostM

HostM keeps them on the hosting account’s Info page, not in the Client Lounge password.

1. Sign in to the [HostM Client Lounge](https://www.hostm.com/).
2. On the dashboard, find the hosting account for tylilo.com.
3. Click the **Info** button next to that account.
4. On the Info page, find **FTP Login Information** (the same host is also listed as the account hostname).
5. Copy these into the GitHub secrets from step 1:
   - **FTP server** (or account hostname) → `FTP_SERVER`. Do not include `ftp://` or a folder path.
   - **Username** → `FTP_USERNAME`. This is the cPanel username.
   - **Password** → `FTP_PASSWORD`. This is the cPanel password, not the Client Lounge password. The Info page can reset the cPanel password if you do not have it.
6. The remote folder is `public_html`. That is the default, so you can leave `FTP_SERVER_DIR` unset.

HostM expects **explicit FTP over TLS** (AUTH TLS) on port 21, in passive mode. The workflow already connects that way. FileZilla often warns that the certificate is unknown; HostM documents that warning. The workflow allows that certificate (`security: loose`) so the upload is still encrypted. If your FTP hostname matches the certificate exactly, you can change `security` to `strict` in `.github/workflows/deploy.yml`.

### 3. Confirm it worked

In GitHub, open **Actions**, then the latest **Deploy to HostM** run. A skipped run means the three secrets are still missing. A green deploy means the upload finished. Then visit [https://tylilo.com](https://tylilo.com/).

If the upload fails once the secrets are set, check the host, username, and password against the Info page, and confirm the account allows FTPS.
