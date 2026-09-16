# TaterDash — Changelog

Running log of what's shipped, what's pending manual activation, and what's next.
See [`ARCHITECTURE.md`](ARCHITECTURE.md) and [`UI-SYSTEM.md`](UI-SYSTEM.md) for the
system reference; this file is just the timeline.

---

## 2026-09-16 — Stripe (test mode)

### Shipped

**Stripe Checkout**
- `stripe/stripe-php` v21.3.2 vendored into the repo (Hostinger deploy is file-copy
  only — there is no `composer install` step on the server).
- `taterdash/create-checkout-session.php` — public by necessity, because the person
  paying is the client and is not logged in. The invoice token is the credential, the
  same one that gates the invoice page. Token-only with no `?id=` fallback; the format
  is checked against `^[a-f0-9]{32}$` before any query runs.
- **The charged amount is always read from the database.** Nothing about the price
  comes from the request, so a crafted URL cannot change what is charged.
- Deliberately charges `td_invoices.total` as a single Checkout line item rather than
  itemising `td_line_items`. Those rows are not guaranteed to sum to `total` —
  invoices created before the July `create-invoice-from-proposal` fix have zeroed line
  items against a non-zero total (live invoice #7 is one). Billing the itemisation
  would charge the wrong amount. `total` is the figure the client agreed to and the
  figure the invoice page shows as "total due".
- `taterdash/stripe-webhook.php` — the ONLY thing that may mark an invoice paid from a
  card payment. The browser returning to `success_url` proves nothing (anyone can visit
  that URL), so the redirect never touches status. Authenticity comes from the
  `Stripe-Signature` header verified against `STRIPE_WEBHOOK_SECRET`.
- Idempotent via a new `td_stripe_events` table with a UNIQUE `event_id`: Stripe
  retries until it gets a 2xx and can deliver the same event twice, so a replay becomes
  a duplicate-key error rather than a second "paid" log entry or a second email. The
  status flip itself is the atomic claim (`WHERE id = ? AND status <> 'paid'`), and the
  side effects only run when `rowCount()` is 1.
- `checkout.session.expired` / `async_payment_failed` release the processing lock back
  to `viewed`, guarded so they can never demote an invoice that reached `paid`.
- Notification emails are wrapped in their own try/catch — a mail failure must not
  return non-2xx, or Stripe would retry work that already succeeded.

**Status model**
- `taterdash/migrate-stripe.sql` adds `payment_processing` to the invoice status ENUM
  (designed in the UI since the client-facing redesign, with no trigger until now),
  plus `stripe_session_id`, `stripe_payment_intent`, `paid_at`, and an index on the
  session id the webhook looks up on every event.
- The new state was threaded through everywhere it would otherwise silently vanish:
  the outstanding-total query (money mid-checkout is still owed), the dashboard row
  menu (so "Mark as paid" stays reachable), the "active" sub-filter, the client
  active-invoice counts in `clients.php` and `get-clients.php`, and `update-status.php`'s
  forward-only guard so Gina can still mark it paid by hand.
- `status_badge()` renders it as "Processing" — `ucfirst()` alone printed the raw enum.

**Client-facing**
- The Pay button is a real link to a per-invoice Checkout Session. It uses the
  invoice's own token, never the URL's, so an invoice opened through a legacy `?id=`
  link still gets a working button.
- New states: "Confirming…" when the client returns from a completed Checkout before
  the webhook lands (bounded re-check, 4 attempts, then it stops — an endless reload
  loop would be worse than a stale page), and a "Payment cancelled — nothing was
  charged" notice on return from an abandoned Checkout.
- A sticky TEST MODE banner shows while `STRIPE_MODE` is not `'live'`, so nobody
  mistakes a test payment for a real one.
- Every Stripe constant is read through `defined()`. The live `config.php` is edited by
  hand on the server and will not have the keys until someone adds them, so an
  unconfigured install degrades to the old "not set up yet" button instead of fataling
  on a client-facing page.

**Dependency security**
- dompdf 3.1.5 → 3.1.6, inside the existing `^3.1` constraint. 3.1.5 carried six
  medium-severity advisories (CVE-2026-59941/59942/59943/56722 and two more), all in
  SVG/image handling — directly relevant, since the signed-proposal PDF embeds a
  client-drawn signature as a data URI. `composer audit` is now clean.

### Verified

33 automated tests, all passing, plus a PDF regression check:
- **Webhook (25 tests)** driven over real HTTP against `php -S`, because the CLI SAPI
  does not wire `php://input` to stdin — an in-process call hands the webhook an empty
  body and every signature check fails for the wrong reason. Stripe signatures are
  HMAC-SHA256, so correctly-signed payloads can be produced locally without touching
  Stripe. Covers: forged signature, absent signature, stale-timestamp replay, GET,
  happy path, duplicate delivery, second event on an already-paid invoice,
  completed-but-unpaid, expiry release, expiry against a paid invoice, unknown invoice,
  resolution by session id when metadata is missing, and unrelated event types.
- **Checkout guards (8 tests)**: missing/non-hex/short/injection-shaped/unknown tokens
  all 404, already-paid redirects instead of charging twice, sub-minimum total 422.
  Every case returns before the Stripe call, so no network traffic and no keys needed.
- **PDF regression**: the real `render_proposal_pdf()` against dompdf 3.1.6 with a
  signature data URI — renders a valid PDF.
- **Invoice page**: all four states rendered and checked at 375px — unpaid (live Pay
  link + banner), cancelled, confirming, paid. Zero horizontal overflow.

**Not verified:** no real Checkout Session has been created, because that needs live
account keys. The success path through Stripe's API is the one part still unproven —
that is what the first test payment is for.

### Found, not fixed

- `database/schema.sql` is materially out of date. `save-proposal.php` inserts
  `campaign_name`, `platform`, `campaign_start`, `campaign_end`, `package_id` and
  `partner_industries`, none of which the file declares, and it declares a `scope`
  column the insert never uses. The live database clearly has them (proposals work),
  so the file — which claims to reflect the live schema — would produce a broken
  install if anyone rebuilt from it. Fixing it properly needs a dump of the live
  schema rather than a guess at column types.

---

## 2026-09-16

### Shipped

**Mobile pass — client-facing**
- `invoice/index.php`: the line-item table was 464px wide inside a 375px viewport.
  The intended behaviour was a horizontal scroll (`min-width: 420px` on the table +
  `overflow-x: auto` on `.doc-body`), but with no visible scrollbar on a phone it just
  read as clipped — a client could not see Qty or Amount at all. Replaced with a
  stacked layout below 600px: each line item becomes a card, with Qty and Amount as
  labelled rows via `data-label` + `::before`.
- `invoice/index.php`: the paid state still said "You'll be taken to Stripe's secure
  checkout" under a "Paid ✓" button, and claimed "Secured by Stripe" on an invoice
  Stripe never touched. Replaced with a plain paid confirmation.
- `proposal/index.php`: the page pushed 13px past the viewport on a phone.
  Cause was `.press-strip` — a non-wrapping flex row (Forbes/New Times/TimeOut/AdWeek)
  whose 356px min-content width inflated the `1fr` grid track above the 311px content
  box. Fixed with `minmax(0, 1fr)` tracks + `flex-wrap: wrap` on the strip.

**Mobile pass — admin**
- The admin was desktop-only: a fixed 240px sidebar (280px on the three form pages)
  with `margin-left` on the content, a 4-up KPI grid, and no media queries on six of
  eight pages. On a phone that left ~150px of usable width plus horizontal scroll.
- Admin CSS is duplicated per page (each page declares its own `.sidebar`/`.nav` and
  `.main` rules), so rather than add a breakpoint to eight stylesheets the mobile
  layer went into one new partial, `admin/partials/mobile.php`, included from
  `partials/topbar.php` — which every admin page already includes. Below 900px:
  - the sidebar becomes an off-canvas drawer behind a hamburger in the topbar, with a
    backdrop, Escape-to-close, and close-on-nav-tap
  - `.main` / `.layout` drop their left margin and collapse to a single column; the
    form pages' two inner scroll panes become one page scroll
  - `.stats-row` goes 4-up → 2-up; `.field-group`, `.pkg-grid`, `.panel-stats`,
    `.p-parties` all stack; `.line-item-row` puts the description full-width above
    qty/price
  - every table is wrapped in a scroll container by JS (no existing CSS used
    `> table` selectors, so wrapping is safe) so wide tables scroll instead of being
    clipped by the `overflow: hidden` card
- Inputs under 16px make iOS zoom the page on focus. Fixed across the admin,
  `taterdash/login.php`, and `taterdash/new-invoice.html`. Note the override has to be
  scoped `.field input` — pages set the size at that specificity, so a bare `input`
  selector loses the cascade.

### Verified

Emulated viewports only (375 / 768 / 1440), not a real handset — the device checks in
TODO still stand. Client pages were checked by rebuilding the live HTML with the new
CSS; the admin by a static harness stitched from the real dashboard CSS and sidebar
markup, since it needs a DB and a login. Confirmed: zero horizontal overflow at 375,
drawer opens/closes, and desktop is byte-for-byte unchanged in layout (sidebar 240px,
`.main` margin 240px, topbar left 240px, KPI grid 4-up, toggle hidden).

### Known limits

- Wide admin tables scroll sideways rather than stacking into cards. Stacking all
  three (`clients-table`, `activity-table`, `p-table`) is a bigger per-column job.
- `.table-wrap`'s rounded corners don't clip the table on mobile, since the scroll
  container needs `overflow: visible` on the parent.
- `taterdash/new-invoice.html` still hides its nav entirely below 768px (pre-existing).

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
