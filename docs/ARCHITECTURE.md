# TaterDash — Architecture & Tech Stack

*Last updated: 2026-07-02*

Internal business dashboard for Mallow Frenchie (pet influencer brand, operated under
G-Space Agency LLC). Handles invoicing and brand-partnership proposals end to end:
create → send → client views/signs → get paid → track everything on one dashboard.

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
- **Deploy mechanism:** Hostinger's GitHub App watches the `TaterDash` repo's `main`
  branch and auto-deploys on push — no manual FTP/upload needed for code changes.
- **Live server root:** `public_html/` on Hostinger.
- **config.php is never committed.** It's gitignored in the TaterDash repo and lives
  only on the server (edited manually via Hostinger File Manager) and locally as an
  untracked file for reference. `config.example.php` is the committed template —
  whenever a new helper function or constant is added there, it must be manually
  copy-pasted into the live `config.php` on Hostinger, since deploy does not touch it.
- **Database access:** phpMyAdmin via Hostinger hPanel → Databases → the
  `u335521326_TaterDash_db` database.

### Path mapping (repo folder → live URL)
> ⚠️ Confirm this mapping in hPanel's GitHub deploy settings if anything 404s after a
> push — the exact local-folder → `public_html` mapping hasn't been independently
> re-verified since initial setup, only inferred from in-file `Upload to:` comments.

| Repo folder | Live path (inferred) | Live URL |
|---|---|---|
| `taterdash/admin/` | `public_html/taterdash-app/admin/` | `mallowfrenchie.com/taterdash-app/admin/` |
| `taterdash/taterdash/` | `public_html/taterdash-app/taterdash/` | (PHP endpoints, not browsed directly) |
| `taterdash/invoice/` | `public_html/invoice/` | `mallowfrenchie.com/invoice/?id=X` |
| `taterdash/proposal/` | `public_html/proposal/` | `mallowfrenchie.com/proposal/?id=X` |
| `taterdash/database/` | *(not deployed — reference SQL only)* | — |

---

## Stack

- **Backend:** Vanilla PHP (no framework), PDO for all DB access with prepared
  statements throughout.
- **Database:** MySQL (MariaDB on Hostinger), database name `u335521326_TaterDash_db`.
- **Auth:** Session-based (`$_SESSION['td_user']`), bcrypt password hash. Auth check is
  inlined at the top of every protected page (`session_start(); if (empty($_SESSION['td_user'])) { redirect }`)
  rather than pulled in via `require_once auth.php` — this was a deliberate workaround
  after hitting PHP execution issues on some paths early on.
- **Frontend:** No JS framework. Vanilla JS + fetch() for all AJAX. Chart.js 4.x (CDN)
  for the monthly revenue chart. Intro.js 7.2.0 (CDN) for the dashboard help tour.
  Tabler Icons webfont (CDN) for all nav/UI icons.
- **Fonts:** Satoshi via Fontshare CDN, matching the public site.
- **Email:** Not wired yet — `fire_emailjs()` in `proposal/index.php` is a stub
  (`console.log` only). EmailJS is the intended provider but no account is connected.
- **Payments:** Stripe is referenced only as a static `STRIPE_PAYMENT_URL` constant
  (a plain payment link, not the API) — currently empty, so invoices show a disabled
  "Pay Now" button. No Stripe account/API integration exists yet.

---

## Data model

Core tables (see `database/schema.sql`, `002_proposals.sql`, `003_proposals_alter.sql`,
and `taterdash/migrate-activity-log.sql` for the full incremental history):

- **`td_clients`** — company, contact, email, phone. Linked from invoices/proposals via
  `client_id`, but most of the app still writes `client_name`/`client_email` directly
  onto the invoice/proposal row rather than joining — client reuse isn't really wired
  up yet (see Next Steps).
- **`td_invoices`** — status ENUM: `draft → sent → viewed → paid` (plus a `_delete`
  pseudo-status used only as an API signal, never stored).
- **`td_line_items`** — belongs to an invoice, cascade-deletes with it.
- **`td_proposals`** — status ENUM: `draft, sent, viewed, signed, accepted, declined`.
  `signed` was added to the ENUM on 2026-07-02 (was originally missing, which caused a
  bug — see Known Issues in the UI-SYSTEM doc / commit history).
- **`td_signatures`** — one row per signed proposal: signer name/email, timestamp, IP,
  user agent. Cascade-deletes with the proposal.
- **`td_activity`** — added 2026-07-02. Append-only event log:
  `event_type` (`created, sent, viewed, paid, signed, deleted, from_proposal`),
  `entity_type` (`invoice, proposal`), `entity_id`, plus **denormalized**
  `entity_num`/`entity_name`/`amount` so log entries stay readable after the source
  record is deleted. `is_read` flag drives the notification bell's unread badge.
  Written via the `log_event()` helper in `config.php`, called from every status-changing
  endpoint (see below).
- **`td_partners`**, **`td_packages`** — referenced by the proposal builder for partner
  logos and package presets (not detailed further here — see `new-proposal.php`).

---

## Status flow (the core mechanic)

Both invoices and proposals follow the same shape, driven by client-side actions hitting
one shared endpoint:

```
draft → (Copy Link clicked in dashboard) → sent
sent  → (client opens the public link)    → viewed
viewed → (invoice: Mark as Paid button)   → paid
viewed → (proposal: client signs)         → signed
signed → (Create Invoice action)          → generates a new draft invoice
```

- **`taterdash/taterdash/update-status.php`** is the single endpoint for both entity
  types now (`{ type: 'invoice'|'proposal', id, status }`). It includes a guard so a
  status can only move forward one step at a time (e.g. `sent` can only be set if the
  row is currently `draft`) — this prevents accidental status regressions from stale
  page state.
- **Viewed** is set organically: `invoice/index.php` and `proposal/index.php` flip
  `sent → viewed` the moment the public page loads, no separate API call.
- **Signed** happens in `sign-proposal.php`, called from the client-facing signature
  form on `proposal/index.php`.
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
- **Inline auth instead of `require_once`:** see Auth section above — this is
  intentional, not an oversight.
- **`config.php` sync is manual:** any new function added to `config.example.php` must
  be hand-copied into the live `config.php` on Hostinger after every deploy that touches
  it. There is no automated sync step.

---

## Accounts / credentials map

| System | Where | Notes |
|---|---|---|
| Hostinger hPanel | hpanel.hostinger.com | Hosting, File Manager, phpMyAdmin, GitHub App deploy config |
| GitHub — mallowfrenchie | github.com/miuxcreative/mallowfrenchie | Public site repo |
| GitHub — TaterDash | github.com/miuxcreative/TaterDash | Dashboard app repo |
| MySQL DB | `u335521326_TaterDash_db` via phpMyAdmin | Credentials live only in server-side `config.php` |
| TaterDash login | `mallowfrenchie.com/taterdash-app/taterdash/login.php` | Session-based, single admin user in `$_SESSION['td_user']` (bcrypt password, not multi-user) |
| Stripe | *(not yet connected)* | Only a placeholder constant exists |
| EmailJS | *(not yet connected)* | Stub function only |
