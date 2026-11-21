# tylilo.com

Company and studio site for Tylilo LLC. The first version is a single homepage: a navy hero, a short About section, and a contact form. It is plain HTML, CSS, and JavaScript plus a small PHP contact handler, so HostM can serve it with no build step.

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

`hello@tylilo.com` is a placeholder. It lives in **one** place:

```php
const TYLILO_CONTACT_EMAIL = 'hello@tylilo.com';
```

That line is in `config.php`, under the heading **CONTACT EMAIL**. The homepage button and the form’s recipient both read it. Create that mailbox in cPanel (or change the constant to a mailbox that already exists) before you rely on the form. Hosting mail filters often drop messages whose From address is not a real mailbox on the account.

The footer reads `© 2026 Tylilo LLC` from `TYLILO_YEAR` and `TYLILO_LEGAL_NAME` in the same file.

## How the contact form works

The form posts to `contact.php`, which checks the fields on the server and emails the result to `TYLILO_CONTACT_EMAIL` with PHP `mail()`. Reply goes to the person who wrote in.

- A hidden field (`ty_extra`) is a honeypot. People never see it. If it is filled in, the submission is discarded and the page still says thank you, so a bot is not told it was blocked.
- Name, email, and message are required. The email must look like an email. The message can be up to 5,000 characters.
- A session token blocks forged cross-site posts. More than five successful sends in half an hour from the same browser asks the visitor to wait.
- The email button stays on the page. If PHP mail is unavailable, or the visitor would rather not use the form, that button opens their mail app. If sending fails, the page says so and points them back to the button.

Nothing secret is stored in this repository. The address above is the public contact address.

## Project layout

```
index.php                 Homepage
404.php                   Missing-page page
contact.php               Form handler
config.php                Email, site name, year
includes/                 Shared header, footer, and form code
assets/css/styles.css     All styles
assets/js/theme.js        Light / dark / auto switch
assets/js/form.js         Form status and double-submit guard
assets/img/               Logos, favicons, social image
assets/fonts/             Nunito (SIL Open Font License)
favicon.ico               Browser icon
site.webmanifest          Home-screen icon info
.htaccess                 404 page, caching, and blocked private files
.github/workflows/        Deploy to HostM
router.php                Local preview only
scripts/generate-icons.py Rebuild favicons from the round logo
```

`assets/img/logo-round.webp` is the badge in the hero. `assets/img/logo.svg` is the wide wordmark, ready for later pages. Nunito’s license is `assets/fonts/OFL.txt`.

To add another page, copy the top of `index.php` (the bootstrap and head includes), reuse `includes/topbar.php` inside `<header class="hero">`, and end with `includes/footer.php`.

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
