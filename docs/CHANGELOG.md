# TaterDash — Changelog

Running log of what's shipped, what's pending manual activation, and what's next.
See [`ARCHITECTURE.md`](ARCHITECTURE.md) and [`UI-SYSTEM.md`](UI-SYSTEM.md) for the
system reference; this file is just the timeline.

---

## 2026-07-07 — 2026-07-08

### Shipped

**Security & bug fixes**
- Session auth added to `update-status.php` and `save-invoice.php` (previously
  unauthenticated).
- Replaced sequential `?id=` public links with random `?t=TOKEN` links
  (`generate_token()`, 32-char hex) on both invoices and proposals. `?id=` still works
  as a fallback for links already sent — see TODO for removal timing.
- `update-status.php`'s guard now checks `rowCount()` after the guarded UPDATE — a
  no-op status change (e.g. marking an already-sent invoice as sent again) now returns
  `409` instead of silently reporting success.
- Fixed `create-invoice-from-proposal.php`: the line-item insert was missing `total`
  (NOT NULL column, was likely erroring silently or storing NULL), the invoice insert
  was missing `subtotal` and `client_id`.
- **Real vulnerability fixed:** `auth.php` had a `// comment with a literal ?>` in it,
  which closes PHP's parser early — everything after it (the hardcoded bcrypt password
  hashes, `attempt_login()`, etc.) was being echoed as plain text. Dormant until
  `login.php` was wired to actually `require` the file; caught immediately after this
  change went live, fixed same day.
- `login.php` now uses `auth.php`'s `attempt_login()` instead of its own duplicate
  inline `$USERS` array — one source of truth for credentials.
- Deleted `test-db.php` (dead debug script).
- JSON error responses across every endpoint now return a generic "Something went
  wrong — it has been logged" instead of leaking `$e->getMessage()` to the client; the
  real message still goes to `td_error_log` via `log_php_error()`.

**Admin topbar unification + favicon**
- Extracted Dashboard's topbar (Help button, notification bell, Logout) into
  `admin/partials/topbar.php` — every admin page now includes it instead of the ad hoc
  "← Dashboard" links that had drifted across pages. Page-specific actions (Add Client,
  Mark all resolved) survive via an extra-actions slot.
- `edit-invoice.php`, `edit-proposal.php`, `new-proposal.php` previously used a
  completely different layout mechanism (full-width dark topbar above a CSS grid) —
  restructured to the same fixed-sidebar/fixed-topbar mechanism as every other admin
  page, so the topbar is now pixel-identical everywhere, not just similar.
- Favicon (the mascot logo) added to every admin page, login, and both client-facing
  pages — none of them had one before.

**Email — Resend integration**
- `send_email()` (cURL → Resend API) + `taterdash/email-templates.php` (table-based
  branded HTML shell, since email clients need tables not flexbox and won't load
  Fontshare).
- New `taterdash/send-document.php`: sends the branded email, and *only on success*
  advances `draft → sent` (guarded, rowCount-checked). Sending an already-sent document
  again logs a `resent` event instead of a duplicate `sent` event and doesn't touch
  status.
- Every status-changing "Copy Link" action in the Dashboard row menu is now "Send to
  client" (opens a modal, prefilled email, optional note). A separate pure copy-link
  icon action exists alongside it that only copies the URL and never touches status.
- `sign-proposal.php` sends the client a confirmation and `NOTIFY_EMAIL` a "signed by"
  notice — replaces the old `fire_emailjs()` `console.log` stub, which is gone.

**Signed-proposal PDFs (Dompdf)**
- `dompdf/dompdf` installed via Composer, `vendor/` committed (Hostinger deploy is
  file-copy only, no server-side `composer install` step — 13MB, 644 files).
- `taterdash/pdf-proposal.php`: `render_proposal_pdf()` builds a branded print-clean PDF
  (system font stack — Dompdf can't reach Fontshare) and saves it to
  `taterdash/generated-pdfs/` (deny-all `.htaccess`, never directly web-accessible).
- Wired into `sign-proposal.php`: generates on successful signature, stores
  `td_proposals.pdf_path`, attaches it to the client confirmation email.
- New `taterdash/download-pdf.php` (session-checked) streams the file for Dashboard/
  Edit Proposal "Signed PDF" download buttons.
- The signature itself is now a real drawn image (see redesign below), rendered into
  the PDF as a data-URI `<img>` above the signer details.

**Real Settings system**
- New `td_settings` key-value table + `get_settings()`/`get_setting()` helpers.
  Replaces every hardcoded "G Space Agency LLC" / old address reference across
  `invoice/index.php`, `proposal/index.php`, `pdf-proposal.php`, and the admin edit
  previews.
- **The real company address was never committed to git** — only `company_name`/
  `company_email` got seeded; the address field started empty and was entered by hand
  via the new Settings UI after deploy, on purpose.
- Settings page rebuilt from its "Coming soon" placeholder into a real form: Company
  Info, Media Kit Stats (followers/impressions/audience/partnerships — these used to be
  hardcoded on the proposal page and never moved), Contact & Brand (email, handle, about
  blurb), Documents (payment terms days, proposal validity days, deposit %).
- `new-proposal.php`'s expiry date and `new-invoice.html`/`edit-invoice.php`'s due date
  now default off `proposal_validity_days`/`payment_terms_days` — the invoice one only
  fills when the field is empty, never overwrites a saved value.

**Client-facing redesign**
- `invoice/index.php` rebuilt as a split-screen layout (document left, sticky dark
  payment panel right) from an approved mockup. Pay button wired to the existing
  `STRIPE_PAYMENT_URL` mechanism (real Stripe Checkout Session integration is still
  pending — see TODO). Paid/awaiting-payment status states. Fixed a real mobile
  overflow bug in the process (the document card blew out past the viewport width on
  phones — needed `min-width: 0` on the flex containers plus a scrollable line-items
  table).
- `proposal/index.php` fully rebuilt as a landing page from an approved mockup: hero,
  media-kit stats band and about blurb now pulled live from Settings, package card,
  generic 3-phase timeline, and a rebuilt signature section.
- **Real draw-to-sign canvas** (vanilla JS, mouse + touch, Clear button, exports PNG)
  replaces the old fake `.sig-pad` placeholder div. New `td_signatures.signature_image`
  column stores the drawn PNG as a base64 data URL; renders in the PDF.
  `sign-proposal.php` also now accepts an explicit `signer_email` field (previously
  always used the proposal's original contact email regardless of who actually signed).
- After signing, the form swaps inline for a confirmation state ("Signed! A copy is on
  its way to your inbox") with no page reload — the old "Download PDF" button
  (`window.print()`, which just opened the browser print dialog) is gone; the real PDF
  arrives via email instead.
- Expired-proposal and already-signed (return visit) states both render inside the
  dark signature section now, styled to match, instead of a separate light-themed card.
- Signed-state checkmark restyled from a generic ✅ emoji to a light-pink square with a
  dark-pink SVG check, matching brand tokens.
- Hero/about photos on the proposal page now pick randomly from `proposal/images/` (a
  folder on the live server, never deployed via git) instead of a single fixed
  placeholder — graceful fallback to the old placeholder, then to an emoji, if that
  folder is empty.
- Small fix along the way: `admin/clients.php`'s sidebar was missing the "Errors" nav
  link entirely (pre-existing gap, not something this round introduced) — added it with
  the same unresolved-count badge every other admin page has.

### ⏳ Pending manual activation (Hostinger-side)

Migrations to run in phpMyAdmin, in order (several already confirmed run — see TODO):
1. `taterdash/migrate-tokens.sql`
2. `taterdash/migrate-ip-direct.sql`
3. `taterdash/migrate-activity-events.sql`
4. `taterdash/migrate-proposal-pdf-path.sql`
5. `taterdash/migrate-settings.sql`
6. `taterdash/migrate-settings-media-kit.sql`
7. `taterdash/migrate-signature-image.sql`

Live `config.php` needs these functions copied in from `config.example.php` (deploy
never touches `config.php`): `generate_token()`, `send_email()`, `get_settings()`,
`get_setting()`. The three defines `RESEND_API_KEY`/`MAIL_FROM`/`NOTIFY_EMAIL` also
need real values set live (placeholders only in the committed example file).

`invoice/index.php` and `proposal/index.php` changed multiple times this round and, per
the deploy mechanics below, need a manual File Manager copy every time — auto-deploy
silently skips them.

---

## 2026-07-02

### Shipped

- **Activity log system** — new `td_activity` table logs every `created / sent /
  viewed / paid / signed / deleted / from_proposal` event, written via `log_event()`
  from every status-changing endpoint. Denormalizes `entity_num`/`entity_name`/`amount`
  so entries stay readable after the source record is deleted.
- **Notification bell** — topbar bell with unread badge, All/Unread tabs, mark-as-read.
- **Proposal status flow fix** — `update-status.php` now handles both invoices and
  proposals through one endpoint, with a guard so status can only advance, never
  regress. Fixed the `td_proposals.status` ENUM, which was missing `'signed'` entirely
  — any proposal that reached that state before the fix silently stored an empty string.
- **Client reuse** — `td_clients` is now actually used. New/edit-invoice and
  new-proposal forms have an autocomplete client picker; `save-proposal.php` now
  looks up/creates the client row the same way `save-invoice.php` already did
  (previously proposals never touched `td_clients` at all).
- **Clients page rebuild** (`admin/clients.php`) — was a dead `?view=clients` link,
  now a real page: searchable table, status filter chips (All/Active/VIP/Dormant),
  and a right-side slide panel per client with lifetime totals, full invoice/proposal
  history, inline edit, and "New Invoice"/"New Proposal" quick actions that land on a
  pre-filled form.
- **All Activity page** (`admin/all-activity.php`) — was also a dead `?view=all` link.
  Now the date-grouped timeline view from the original notification-bell design spec:
  action chips, plain-English sentences linking back to the source document, filter
  chips (All/Invoices/Proposals/This Month), "Load older" pagination. Opening the page
  marks everything read.
- **Error logging** — new `td_error_log` table + `log_php_error()` helper, wired into
  every `catch` block across the backend (save/update invoice & proposal, sign, delete,
  create-invoice-from-proposal, save-client). `update-status.php` had no try/catch at
  all before this — a thrown exception there previously produced a blank 500 with
  nothing recorded anywhere. New **Errors** page lists everything logged, newest first,
  with the failing request's data shown inline and a mark-resolved action.
- **Docs** — `ARCHITECTURE.md` and `UI-SYSTEM.md` added, recording the two-repo split,
  hosting/deploy mechanics, data model, status-flow mechanic, and dashboard design
  tokens. Brand skill (`mallow-frenchie-brand`, v0.3) updated with a TaterDash-specific
  token quick-reference.

### ⏳ Pending manual activation (Hostinger-side)

Two SQL migrations were written this session but need to be run manually in phpMyAdmin
— pushing to GitHub does not touch the database:

1. **`taterdash/migrate-error-log.sql`** — creates `td_error_log`. Without this, every
   `log_php_error()` call silently no-ops (it catches its own failure on purpose so a
   missing table can't mask the real error).
2. **Live `config.php` needs `log_php_error()` added manually** — copy it from
   `taterdash/config.example.php` (sits directly above `generate_slug()`). Deploy does
   not touch `config.php` since it's gitignored and lives only on the server.

Both are one-time — once done, error logging is fully live with no further steps.

---

## Next up

See [`TODO.md`](TODO.md) for the full checklist — blocking activation steps, a full
end-to-end testing pass (real device, real email, real Stripe once activated), the
data migration, and the prioritized build queue (Stripe activation is now the main
remaining placeholder, plus a mobile testing pass and the G Space Agency branding
question in the backlog).
