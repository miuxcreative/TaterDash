# TaterDash — Architecture & Tech Stack

*Last updated: 2026-07-08*

Internal business dashboard for Mallow Frenchie (pet influencer brand). Handles
invoicing and brand-partnership proposals end to end: create → send → client
views/signs → get paid → track everything on one dashboard.

Company identity shown on documents (name/email/address) is now editable via the
Settings page (`td_settings` table) rather than hardcoded — currently set to "Mallow
Frenchie". The legal entity is G-Space Agency LLC; there's a backlog item (see TODO) to
let an admin choose which identity to stamp on a given document, but that doesn't exist
yet — it's one identity for everything right now.

---

## Repos (there are TWO — don't confuse them)

| Repo | Local path | GitHub | Purpose |
|---|---|---|---|
| **mallowfrenchie** | `mallowfrenchie/website/` | `github.com/miuxcreative/mallowfrenchie` | Public marketing site. Hosted on GitHub Pages at `miuxcreative.github.io/mallowfrenchie`. Also the source of the logo/image assets referenced by TaterDash (`miuxcreative.github.io/mallowfrenchie/images/...`) — treat it as a CDN for those. |
| **TaterDash** | `mallowfrenchie/taterdash/` | `github.com/miuxcreative/TaterDash` | The dashboard app itself — admin UI, invoice/proposal engine, client-facing invoice & proposal pages, PHP backend, MySQL schema. |

Both are independent git repos (separate `.git` folders) living side by side inside the
same `mallowfrenchie/` project folder on disk. `git push` in one has zero effect on the
other — always confirm which directory you're in before pushing.

---

## Hosting & deploy

- **Host:** Hostinger shared hosting, LiteSpeed server, domain `mallowfrenchie.com`.
- **Deploy mechanism:** Hostinger's Git integration watches the `TaterDash` repo's
  `main` branch and auto-deploys on push — but it mirrors the repo into ONE target
  directory, `public_html/taterdash-app/`, not all of `public_html/`. Confirmed
  2026-07: pushing a `taterdash/taterdash/*.php` change auto-deployed correctly;
  pushing an `invoice/index.php` change did not (file stayed 5 days stale until
  manually copied via File Manager).
- **`invoice/` and `proposal/` are NOT covered by auto-deploy.** They live directly
  under `public_html/invoice/` and `public_html/proposal/` (outside the
  `taterdash-app/` deploy target) so client-facing URLs stay short
  (`mallowfrenchie.com/invoice/` instead of `mallowfrenchie.com/taterdash-app/invoice/`).
  Any change to `taterdash/invoice/index.php` or `taterdash/proposal/index.php` must
  be manually copied into Hostinger File Manager after pushing — auto-deploy will
  silently skip them.
- **Live server root:** `public_html/` on Hostinger.
- **config.php is never committed.** It's gitignored in the TaterDash repo and lives
  only on the server (edited manually via Hostinger File Manager) and locally as an
  untracked file for reference. `config.example.php` is the committed template —
  whenever a new helper function or constant is added there, it must be manually
  copy-pasted into the live `config.php` on Hostinger, since deploy does not touch it.
- **Database access:** phpMyAdmin via Hostinger hPanel → Databases → the
  `u335521326_TaterDash_db` database.

### Path mapping (repo folder → live URL)

| Repo folder | Live path | Auto-deploys? | Live URL |
|---|---|---|---|
| `taterdash/admin/` | `public_html/taterdash-app/admin/` | ✅ yes | `mallowfrenchie.com/taterdash-app/admin/` |
| `taterdash/taterdash/` | `public_html/taterdash-app/taterdash/` | ✅ yes | (PHP endpoints, not browsed directly) |
| `taterdash/invoice/` | `public_html/invoice/` | ❌ **no — manual copy required** | `mallowfrenchie.com/invoice/?t=TOKEN` |
| `taterdash/proposal/` | `public_html/proposal/` | ❌ **no — manual copy required** | `mallowfrenchie.com/proposal/?t=TOKEN` |
| `taterdash/database/` | *(not deployed — reference SQL only)* | — | — |

---

## Stack

- **Backend:** Vanilla PHP (no framework), PDO for all DB access with prepared
  statements throughout.
- **Database:** MySQL (MariaDB on Hostinger), database name `u335521326_TaterDash_db`.
- **Auth:** Session-based (`$_SESSION['td_user']`), bcrypt password hash. Most protected
  pages still inline the check (`session_start(); if (empty($_SESSION['td_user'])) { redirect }`)
  rather than pulling in `auth.php`. **`login.php` is the one exception** — it now
  `require_once`s `taterdash/auth.php` and calls `attempt_login()` instead of keeping
  its own duplicate `$USERS` array, so there's one source of truth for credentials.
  Worth knowing: `auth.php` previously had a `// comment containing a literal ?>`,
  which closes PHP's parser early and caused everything after it (including the
  password hashes) to be echoed as plain text the moment something finally required the
  file. Fixed same day it was caught — if you ever see raw PHP source appear on a page,
  check for a stray `?>` inside a comment before anything else.
- **Frontend:** No JS framework. Vanilla JS + fetch() for all AJAX. Chart.js 4.x (CDN)
  for the monthly revenue chart. Intro.js 7.2.0 (CDN) for the dashboard help tour.
  Tabler Icons webfont (CDN) for all nav/UI icons. The proposal signature pad is a
  hand-rolled `<canvas>` (mouse + touch, no library).
- **Fonts:** Satoshi via Fontshare CDN, matching the public site. Dompdf (PDF
  generation) can't reach Fontshare, so the PDF uses a system font stack instead —
  a real Satoshi TTF would need to be added to the repo and registered with Dompdf's
  font loader to fix that, not done yet.
- **Email:** Resend (`send_email()` in `config.php`, cURL → `api.resend.com`). Sends
  invoice/proposal-sent emails and proposal-signed confirmation + notify emails, all
  via `taterdash/email-templates.php` (table-based HTML, since email clients need
  tables not flexbox and won't load Fontshare). Replaced the old EmailJS
  `fire_emailjs()` stub, which is gone. Needs `RESEND_API_KEY` / `MAIL_FROM` /
  `NOTIFY_EMAIL` set in live `config.php` — real values, not the placeholders committed
  in `config.example.php`.
- **PDF generation:** Dompdf (`taterdash/pdf-proposal.php`), installed via Composer
  with `vendor/` committed to git (Hostinger deploy is file-copy only — there's no
  server-side `composer install` step, so the dependency has to already be there).
  Generates a signed-proposal PDF (with the drawn signature image embedded) on
  successful signature, stored in `taterdash/generated-pdfs/` (deny-all `.htaccess`,
  never directly web-accessible) and attached to the confirmation email.
- **Payments:** Stripe is still referenced only as a static `STRIPE_PAYMENT_URL`
  constant (a plain payment link, not the API) — the new split-screen invoice page's
  "Pay with card" button uses this same mechanism (shows disabled/greyed if unset). No
  Stripe account/API integration exists yet — this is the main remaining build-queue
  item (see TODO). A real integration would need a Checkout Session per invoice (so the
  amount passes through automatically) and webhook handling to flip status to `paid`
  automatically instead of the current manual "Mark as paid" click.

---

## Data model

Core tables (see `database/schema.sql` plus every `taterdash/migrate-*.sql` file for
the full incremental history — schema.sql is kept in sync for fresh installs, but the
migrations are the actual change log and are never edited after the fact):

- **`td_clients`** — company, contact, email, phone. Linked from invoices/proposals via
  `client_id`, but most of the app still writes `client_name`/`client_email` directly
  onto the invoice/proposal row rather than joining — client reuse isn't really wired
  up yet (see Known non-obvious workarounds).
- **`td_invoices`** — status ENUM: `draft → sent → viewed → paid` (plus a `_delete`
  pseudo-status used only as an API signal, never stored). Has a unique `token`
  (32-char hex, added 2026-07) used for the public `?t=TOKEN` link — `id` still works
  as a fallback for links sent before tokens existed (see TODO for removal timing).
- **`td_line_items`** — belongs to an invoice, cascade-deletes with it.
- **`td_proposals`** — status ENUM: `draft, sent, viewed, signed, accepted, declined`.
  `signed` was added to the ENUM on 2026-07-02 (was originally missing, which caused a
  bug — see Known Issues in the UI-SYSTEM doc). Also has `token` (same pattern as
  invoices) and `pdf_path` (absolute filesystem path to the signed PDF once generated,
  NULL until then).
- **`td_signatures`** — one row per signed proposal: signer name/email, timestamp,
  `ip_address` (X-Forwarded-For, may be proxy-supplied) and `ip_direct` (REMOTE_ADDR
  unconditionally, added alongside it for a trustworthy value), user agent, and
  `signature_image` (MEDIUMTEXT — the drawn signature as a base64 PNG data URL,
  rendered into the signed PDF as a data-URI `<img>`). Cascade-deletes with the
  proposal.
- **`td_activity`** — append-only event log: `event_type` (`created, sent, viewed,
  paid, signed, deleted, from_proposal, resent, emailed`), `entity_type` (`invoice,
  proposal`), `entity_id`, plus **denormalized** `entity_num`/`entity_name`/`amount` so
  log entries stay readable after the source record is deleted. `is_read` flag drives
  the notification bell's unread badge. Written via the `log_event()` helper in
  `config.php`. `resent` fires when a document is emailed again after it's already past
  `draft` (see Status flow below); `emailed` was added to the ENUM for forward
  compatibility but has no call site yet.
- **`td_settings`** — key-value table (`setting_key` PK, `setting_value` TEXT,
  `updated_at`), added 2026-07. Read via `get_settings($pdo): array` (returns
  everything, with sane defaults for missing keys) or `get_setting($pdo, $key,
  $default)` for one value (added but currently unused — see TODO). Written via
  `taterdash/save-settings.php`, edited through the Settings admin page. Holds company
  name/email/address, media-kit stats (followers/impressions/audience/partnerships),
  contact email + Instagram handle, about blurb, and payment/proposal defaults
  (`payment_terms_days`, `proposal_validity_days`, `deposit_percent`). **The real
  company address is intentionally not in any migration or seed data** — it's typed in
  by hand via the Settings page after deploy, specifically so it never touches git.
- **`td_partners`**, **`td_packages`** — referenced by the proposal builder for partner
  logos and package presets. Note: the client-facing proposal page's "Previous
  Partnerships" logo display was dropped in the 2026-07 redesign (the new landing-page
  design has no slot for it) — `td_partners` data still exists and is still queried by
  `new-proposal.php`/`edit-proposal.php` for the picker, but nothing shows the logos to
  a client anymore. See TODO for the open question on whether this comes back.

---

## Status flow (the core mechanic)

```
draft → (admin clicks "Send to client" → email actually sends) → sent
sent  → (client opens the public link)                          → viewed
viewed → (invoice: Mark as Paid button)                          → paid
viewed → (proposal: client signs)                                → signed
signed → (Create Invoice action)                                 → generates a new draft invoice
```

- **`draft → sent` now requires a successful email send**, not just copying the link.
  `taterdash/send-document.php` sends the branded email via `send_email()` and *only on
  success* runs the guarded status UPDATE. The plain "Copy link" action in the
  Dashboard row menu is now pure — it copies the URL and never touches status. This
  changed 2026-07: previously, copying the link silently marked the document sent
  whether or not the client ever actually received anything.
- **Resending** an already-sent/viewed/etc. document (clicking "Send to client" again)
  does not change status and does not log a duplicate `sent` event — it logs `resent`
  instead. `send-document.php` handles this itself via a rowCount check on the guarded
  UPDATE, same guard mechanism as below.
- **`taterdash/taterdash/update-status.php`** is still the endpoint for the other
  transitions (`{ type: 'invoice'|'proposal', id, status }`) — marking paid, deleting a
  draft. It includes a guard so status can only move forward one step at a time, and
  (added 2026-07) checks `rowCount()` after the guarded UPDATE: if 0 rows changed (the
  status had already moved past that point), it returns `409` instead of silently
  reporting success.
- **Viewed** is set organically: `invoice/index.php` and `proposal/index.php` flip
  `sent → viewed` the moment the public page loads, no separate API call.
- **Signed** happens in `sign-proposal.php`, called from the client-facing signature
  form on `proposal/index.php`. Also generates the signed PDF and fires the
  confirmation/notify emails — all best-effort, none of it can block the signature
  response itself (each step logs its own failure and returns a safe fallback rather
  than throwing).
- **create-invoice-from-proposal.php** only proceeds if the proposal's status is
  `signed` or `accepted`; it copies client info + total into a new draft invoice and a
  single line item, and logs a `from_proposal` activity event.
- Every one of these transitions also calls `log_event()` to write to `td_activity`.

---

## Numbering scheme

`generate_invoice_num()` / `generate_proposal_num()` (in `config.php`) use
`MAX(CAST(SUBSTRING_INDEX(...,'-',-1) AS UNSIGNED))` rather than `COUNT(*)` — this was a
deliberate fix after duplicate invoice numbers occurred when a draft invoice was deleted
and `COUNT(*)` produced a number that collided with an existing one. Always use MAX, never
COUNT, for any future numbering scheme (e.g. if per-client numbering is ever added).

---

## Known non-obvious workarounds

- **PHP execution scoping:** only `taterdash/` (and its subfolders with their own
  `.htaccess` `SetHandler application/x-httpd-php`) reliably execute PHP on this
  Hostinger setup. `admin/` needed its own `.htaccess` added explicitly. If a new
  top-level folder is ever added, check whether it needs the same `.htaccess` treatment
  or PHP will get served as plain text instead of executed.
- **Inline auth instead of `require_once` is still the norm** for most pages — see
  Auth section above. `login.php` is the one page that now pulls in `auth.php`.
- **`config.php` sync is manual:** any new function or define added to
  `config.example.php` must be hand-copied into the live `config.php` on Hostinger
  after every deploy that touches it. There is no automated sync step. As of 2026-07
  this list is: `generate_token()`, `send_email()`, `get_settings()`, `get_setting()`,
  plus the `RESEND_API_KEY`/`MAIL_FROM`/`NOTIFY_EMAIL` defines.
- **Sensitive values never get seeded in migrations.** The `td_settings` migration
  deliberately leaves `company_address` unset — real addresses/PII should be entered by
  hand through the app after deploy, not baked into a SQL file that lives in git
  history forever. Follow this pattern for any future sensitive setting.
- **Binary/generated-content folders get a deny-all `.htaccess`, not code review.**
  `taterdash/generated-pdfs/` (signed PDFs) has `Require all denied` + `Deny from all`
  (both syntaxes, for Apache 2.2 and 2.4+) so files there are never directly
  web-accessible — only reachable through the session-checked `download-pdf.php`.
  Follow the same pattern for any future folder of generated/uploaded files.
- **`vendor/` is committed to git**, unusually for a PHP project — see Stack section.
  Hostinger deploy is file-copy only, so any Composer dependency (currently just
  Dompdf) has to already be in the repo, not installed on deploy.

---

## Accounts / credentials map

| System | Where | Notes |
|---|---|---|
| Hostinger hPanel | hpanel.hostinger.com | Hosting, File Manager, phpMyAdmin, GitHub App deploy config |
| GitHub — mallowfrenchie | github.com/miuxcreative/mallowfrenchie | Public site repo |
| GitHub — TaterDash | github.com/miuxcreative/TaterDash | Dashboard app repo |
| MySQL DB | `u335521326_TaterDash_db` via phpMyAdmin | Credentials live only in server-side `config.php` |
| TaterDash login | `mallowfrenchie.com/taterdash-app/taterdash/login.php` | Session-based, single admin user in `$_SESSION['td_user']` (bcrypt password, not multi-user) |
| Stripe | *(not yet connected)* | Only a placeholder `STRIPE_PAYMENT_URL` constant exists — see TODO, this is the main remaining build item |
| Resend | Live — `RESEND_API_KEY` in server-side `config.php` | Transactional email (invoice/proposal sent, proposal signed). Replaced EmailJS, which was never actually connected. |
