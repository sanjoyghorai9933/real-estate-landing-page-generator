# Landing Page Generator — Multi-Theme, Self-Hosted Backend

Turns a form submission into a ready-to-use landing page folder (index.html,
crm_connect.php, thanks.html, assets/) zipped up with a download link —
no n8n, no third-party workflow tool. Just PHP on your own hosting.

The generator supports multiple independent themes. Each theme owns its own
layout, sections, fields, styling and renderer. The core dispatcher
(`generate.php`) and frontend orchestrator (`core/app.js`) remain theme-agnostic.

## Folder structure

```
LP-Generator/
├── index.html
├── landing-page-request.html
├── core/
│   ├── app.js
│   └── app.css
├── themes/
│   ├── registry.json
│   ├── default/
│   │   ├── theme.json
│   │   ├── thumbnail.svg
│   │   ├── form.html
│   │   ├── form.css
│   │   ├── form.js
│   │   ├── ai.js
│   │   └── ai.css
│   └── palm-estate/
└── backend/
    ├── generate.php
    ├── ai.php
    ├── core/helpers.php
    ├── themes/<id>/...
    └── output/
```

## AI Content Assistant

The default theme includes an **AI Content Assistant**. It sends a project brief
to `backend/ai.php`, which calls the OpenAI Responses API from the server and
returns structured landing-page content. The browser never receives the API key.

Configure it with environment variables:

```text
OPENAI_API_KEY=your-server-side-key
OPENAI_MODEL=gpt-5.5
```

For local PowerShell testing:

```powershell
$env:OPENAI_API_KEY="your-key"
$env:OPENAI_MODEL="gpt-5.5"
php -S localhost:8000
```

If the key is not configured, the normal non-AI generator continues to work.

The AI assistant is intentionally constrained to facts supplied in the brief;
it should not be used as a source of verified property prices, RERA details,
distances, approvals, unit counts or other legal/commercial facts. Review the
draft before applying it.

## Property Matrimony lead delivery

The default generated landing page supports the Property Matrimony lead-delivery
profile. SMTP and CRM secrets are server-side only; they are never embedded in
the generated HTML or committed to the repository.

Configure the deployment environment with:

```text
PROPERTY_MATRIMONY_SMTP_SERVER=
PROPERTY_MATRIMONY_SMTP_PORT=465
PROPERTY_MATRIMONY_SMTP_SECURE=ssl
PROPERTY_MATRIMONY_SMTP_USERNAME=
PROPERTY_MATRIMONY_SMTP_PASSWORD=
PROPERTY_MATRIMONY_TO_EMAIL=
PROPERTY_MATRIMONY_CC_EMAIL=
PROPERTY_MATRIMONY_BCC_EMAIL=
PROPERTY_MATRIMONY_CRM_ENDPOINT=
PROPERTY_MATRIMONY_CRM_API_KEY=
```

The generated `crm_connect.php` sends the lead to the configured CRM endpoint
with an API key, then sends the lead notification through the configured SMTP
account. CRM failures are logged without exposing the API key to the visitor;
SMTP failures are also handled as a controlled redirect instead of printing
raw SMTP diagnostics.

The CRM request uses the standard Leadrat third-party integration pattern:
POST JSON with an `API-Key` header. Leadrat documents fields including name,
mobile, project, notes and email, and documents API-key authentication and
success/failure responses. urlLeadrat API integration documentationhttps://apidocs.leadrat.com/index.php/docs/technical-documentation-for-crm-api-integration-with-third-party-providers/

## Adding a new theme

1. Copy the frontend and backend theme folders.
2. Rewrite the theme form and renderer for your own fields.
3. Add an entry to `themes/registry.json`.
4. Optional capabilities can be provided as `ai.js` + `ai.css` exposing
   `mountAi(container, ctx)`. The core shell will load them automatically.

## Live deployment

1. Upload the whole project to your hosting account.
2. Fill each theme's `backend/themes/<id>/assets/` with real fallback assets.
3. Configure the Property Matrimony SMTP/CRM environment variables above when
   using the default client profile.
4. Ensure `backend/output/` is writable.
5. Use PHP with the ZipArchive extension enabled.
6. Set `OPENAI_API_KEY` only as a server environment variable if AI is enabled.
7. Keep `backend/themes/` protected from direct web access.

## Request flow

1. Select a theme.
2. The theme's `form.js` renders its fields.
3. Optionally use the AI assistant to draft structured content.
4. The theme submits multipart form data to `backend/generate.php`.
5. `generate.php` validates the theme and delegates to that theme's renderer.
6. The renderer creates the generated page, preview and ZIP.
7. The page registry powers **My Landing Pages** and edit-in-place behavior.

## Security notes

- Never commit `.env` or real API keys/passwords.
- `backend/ai.php` reads `OPENAI_API_KEY` only from the server environment.
- Property Matrimony SMTP and CRM credentials are read only from server environment variables.
- Generated output and theme source should remain protected by server rules.
- Image uploads should remain limited to the formats accepted by each theme.
- For public deployments, consider an application-level rate limit or shared
  secret for generation endpoints to prevent automated ZIP generation abuse.
- If using Nginx, reproduce the Apache `.htaccess` access restrictions in the
  Nginx server configuration.

## Troubleshooting

- **AI is not configured** → set `OPENAI_API_KEY` in the environment before starting PHP.
- **Lead email is not configured** → set the Property Matrimony SMTP and recipient environment variables.
- **CRM leads are not arriving** → verify the CRM endpoint/API key and outbound HTTPS access from PHP/cURL.
- **Could not create working directory** → check `backend/output/` permissions.
- **Unknown or misconfigured theme** → verify the `themeId` and backend theme folder.
- **Blank/500 response** → inspect the PHP error log; ZipArchive and file permissions are common causes.
- **Download link 404s** → verify the output access rules and hosting configuration.
