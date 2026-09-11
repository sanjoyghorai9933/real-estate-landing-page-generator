# Landing Page Generator — Multi-Theme, Self-Hosted Backend

Turns a form submission into a ready-to-use landing page folder (index.html,
crm_connect.php, thanks.html, assets/) zipped up with a download link —
no n8n, no third-party workflow tool. Just PHP on your own hosting.

The generator now supports **multiple independent themes**. Each theme owns
its own layout, sections, fields, styling and renderer. The core dispatcher
(`generate.php`) and the frontend orchestrator (`core/app.js`) never contain
theme-specific logic — adding a new theme means adding a new `themes/<id>/`
folder, nothing else changes.

## Folder structure

```
LP-Generator/
├── index.html                    ← generator shell: theme selection + mount point
├── landing-page-request.html     ← same file, alt name (kept for backward compatibility)
├── core/
│   ├── app.js                    ← theme-agnostic orchestrator (selection, mounting, live preview)
│   └── app.css                   ← theme-agnostic chrome (topbar, selection grid, preview panel)
├── themes/
│   ├── registry.json             ← list of available themes shown on the selection screen
│   ├── default/                  ← "Signature Real Estate" theme (the original generator)
│   │   ├── theme.json            ← name/description/colors/sections shown on the selection card
│   │   ├── thumbnail.svg
│   │   ├── form.html             ← this theme's field markup
│   │   ├── form.css              ← this theme's form styling
│   │   └── form.js               ← this theme's form logic + validation + submit + live-preview data
│   └── palm-estate/              ← "Palm Estate" theme (converted from an uploaded design)
│       └── (same file set as default/)
└── backend/
    ├── generate.php              ← CORE DISPATCHER — theme-agnostic, do not add theme logic here
    ├── core/
    │   └── helpers.php           ← shared, theme-agnostic PHP utilities (zip, file copy, colors, gtag…)
    ├── themes/
    │   ├── default/
    │   │   ├── config.php        ← theme metadata + required fields
    │   │   ├── renderer.php      ← ALL of this theme's business logic (token filling, sections)
    │   │   ├── template/         ← index-template.html, crm_connect-template.php, etc. (protected)
    │   │   └── assets/           ← this theme's default css/js/fonts/images
    │   └── palm-estate/
    │       └── (same file set as default/)
    └── output/
        ├── submissions.csv       ← auto-created log of every request (shared across all themes)
        └── zips/, previews/      ← generated .zip files + browsable previews (shared across all themes)
```

## Adding a new theme

1. Copy `backend/themes/default/` to `backend/themes/<your-id>/` and copy
   `themes/default/` (frontend) to `themes/<your-id>/`.
2. Rewrite `template/index-template.html` with your own layout/sections and
   `{{TOKEN}}` placeholders, and `renderer.php`'s `render_theme()` to build
   and fill those tokens from your own `$_POST`/`$_FILES` fields.
3. Rewrite `form.html` / `form.css` / `form.js` (`mount()`/`unmount()`) with
   your own fields, matching the POST field names your `renderer.php` reads.
4. Add an entry to `themes/registry.json`.
5. Nothing in `generate.php`, `core/app.js`, `core/app.css`, or any other
   theme's files needs to change.

Optional: implement `getPreviewSummary()` in your `form.js` (same shape as
the other themes) to power the generic live-preview panel — it's checked
for at runtime, not required.

## Live deployment (luxury-residences.online, GoDaddy cPanel)

This copy is already configured for:
```
https://luxury-residences.online/LP-Generator/
```

1. **Upload the whole `LP-generator/` folder** via cPanel → **File Manager**
   (or FTP) into `public_html/LP-Generator/` — the folder name must match
   exactly (Linux hosting is case-sensitive).
2. **Fill in each theme's `backend/themes/<id>/assets/`** with your real,
   unchanging files — fonts, and one default image per slot. These act as
   fallback images if a visitor skips an upload.
3. **Set your SMTP sender details** in each theme's
   `backend/themes/<id>/template/config_smtp-template.php` — this is the
   *sending* account, same for every generated page from that theme. It's
   separate from the "Lead Email" field in the form, which is the
   *recipient* address and does change per project.
4. **Check folder permissions** — `backend/output/` (shared by every theme)
   needs to be writable (755, or 775 on some hosts).
5. **Confirm PHP version & ZipArchive** — PHP 7.4+ with the `zip` extension.
6. **The webhook URL is centralized** — `core/app.js` points at
   `backend/generate.php` (relative to wherever `index.html` is hosted).
   Only change this constant if you move the backend to a different path.
7. **First submission auto-creates `backend/output/`** along with the
   `.htaccess` files that lock it down — no manual step needed.

## How a request flows through the backend

1. The person picks a theme on the selection screen; the theme's own
   `form.js` renders that theme's fields.
2. On submit, the theme module POSTs `multipart/form-data` (including a
   `themeId` field) to `backend/generate.php`.
3. `generate.php` (the core dispatcher) validates `themeId` against the
   `backend/themes/` folder, creates the shared `output/` tree if needed,
   and hands off to that theme's `renderer.php`.
4. The theme's `render_theme()` validates its own required fields, creates
   a temp working folder, copies that theme's static assets in, saves
   uploaded images, builds its own dynamic HTML fragments, fills its own
   `index-template.html` + `crm_connect-template.php` + `thanks.html`, zips
   the result, writes a public preview copy, cleans up, logs the submission,
   and returns a download link + preview link.
5. `generate.php` JSON-encodes whatever the theme's renderer returned.

## Security notes worth knowing
- `backend/themes/.htaccess` blocks direct web access to the entire
  `themes/` folder (templates, SMTP credentials, source assets) on Apache.
  `output/.htaccess` and `output/zips/.htaccess` do the same for the shared
  output folder — written automatically by `generate.php` on first run.
- If your host uses Nginx instead of Apache, `.htaccess` files are ignored —
  add equivalent `deny all;` rules for `/backend/themes/` and `/output/`
  (excluding `/output/zips/` and `/output/previews/`) in your server block.
- Only `.jpg`, `.jpeg`, `.png`, and `.webp` uploads are accepted per theme;
  anything else is silently skipped rather than saved.
- Consider adding a simple shared-secret header check in `generate.php` if
  the form will be public, so random visitors can't spam-generate zips.

## Troubleshooting
- **"Could not create working directory"** → `output/` isn't writable; check
  permissions.
- **"Unknown or misconfigured theme"** → the `themeId` posted by the form
  doesn't match a folder under `backend/themes/`, or that folder is missing
  `renderer.php`/`template/`.
- **Blank/500 response** → check your host's PHP error log; almost always
  a missing `ZipArchive` extension or a file-permission issue.
- **Download link 404s** → confirm `output/zips/.htaccess` uploaded correctly
  and that your host allows `.htaccess` overrides (`AllowOverride All`).
