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

Each game is one folder, `/arcade/<slug>/index.html`. The list on `/arcade/` is the `$games` array in `arcade/index.php`: title, one-line description, path, and `art`. Adding a game is one new entry, in the order it should appear, plus an `art.svg` in that game’s folder. The tile name is the accessible text, so the artwork stays decorative (`alt=""`).

Every game opens in the same tab. Do not add `target="_blank"`, a new-window hint, or a window icon to a game link. The homepage Arcade tile also opens `/arcade/` in the same tab. Speculation rules prefetch `a.game-tile` and do not prerender those links, so a game’s audio and loop do not start in the background.

A Top scores control, if one is added later, goes in the article under the tile link, not inside the link.

Game files come from their own maintainers. Drop the new `index.html` in unchanged. Do not edit it in this repo. Games may keep settings and high scores in the browser’s local storage and must not load outside resources. When a game stores something new, describe it on `/privacy` under “Saved on your device” and update the effective date if the policy text changes.

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
