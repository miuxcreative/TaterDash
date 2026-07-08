# TaterDash — UI System

*Last updated: 2026-07-08*

This is the **internal dashboard's** visual language — distinct from the public
mallowfrenchie.com marketing site (see the `mallow-frenchie-brand` skill for that).
TaterDash borrows the same pink/blush palette but applies it as a functional admin UI:
dark sidebar, dense tables, status badges, not an editorial marketing layout.

The client-facing invoice and proposal pages (`invoice/index.php`, `proposal/index.php`)
sit in between — they use the admin palette but are styled as polished documents, since
clients see them.

---

## Layout shell (applies to every admin/*.php page)

- **Sidebar:** fixed, 240px, `#111111` background, full height, `z-index: 300`.
  - Brand block: Mallow logo (40px) + "TaterDash" + pink "MALLOWFRENCHIE" sub-label.
  - Nav grouped under uppercase section labels (`Create`, `Manage`), Tabler icons
    (`<i class="ti ti-...">`), active item gets a 2px pink left border.
  - Profile block pinned to bottom via `flex: 1` spacer + divider: avatar circle,
    username, Settings/Logout links.
- **Topbar:** fixed, 52px tall, white background, `1px solid #e8e8e8` bottom border,
  `z-index: 200`, `left` offset matching whichever sidebar width that page uses (240px
  on most pages; 280px on the three form pages with a live-preview panel — see below).
  Page title left-aligned; right side holds page actions (Help button, notification
  bell, Logout), plus an optional page-specific action slot before them (e.g. "Add
  Client" on the Clients page, "Mark all resolved" on Errors).
  - **Shared partial, not copy-pasted:** lives in `admin/partials/topbar.php` —
    markup, styles, and JS (bell dropdown, help/tour button) all in one file, included
    via `<?php $topbar_title = '...'; include __DIR__ . '/partials/topbar.php'; ?>`.
    Every admin page uses this same include now (unified 2026-07) — if you're adding a
    new admin page, include the partial, don't hand-write a topbar.
  - Three pages (`edit-invoice.php`, `edit-proposal.php`, `new-proposal.php`) used to
    have a completely different topbar (full-width dark bar above a CSS grid layout,
    not a fixed sidebar) — restructured to the same fixed-sidebar/fixed-topbar
    mechanism as every other page so the topbar is now pixel-identical everywhere, not
    just visually similar. These three still use a 280px sidebar width (unchanged, not
    worth the churn of resizing), so they pass `$topbar_sidebar_width = '280px';`
    before including the partial.
  - **Exception:** the public-facing `invoice/` and `proposal/` pages do NOT use this
    topbar at all — they're documents, not app screens.
- **Main content:** `margin-left: 240px`, `padding-top: 52px` to clear the fixed shell.

---

## Color tokens (admin dashboard specific)

| Token | Hex | Usage |
|---|---|---|
| Pink (primary/active) | `#e04d80` | Active states, primary actions, "paid/signed" money color |
| Ink | `#191919` | Primary text |
| Ink-mid | `#6b6b6b` | Secondary text |
| Ink-light | `#b0b0b0` | Muted/disabled text, stat labels |
| Dark (sidebar/footer) | `#111111` | Sidebar bg, dark topbar (non-dashboard pages), footer, invoice payment panel bg |
| Blush | `#faf0f0` | Stat card backgrounds, hover states |
| Blush-dark | `#f5e5e5` | Client-facing document dividers/section backgrounds (invoice/proposal) |
| Card Rose | `#f2d0dc` | "Viewed" badge, invoice top-band accent, signed-checkmark square bg |
| Card Sky | `#c4dde8` | "Invoice" type pill, proposal about-photo placeholder bg |
| Card Sand | `#e6d5b8` | "Awaiting payment" status dot on the invoice payment panel, "paid" invoice badge tint |
| Border | `#e8e8e8` | All card/table borders |

### Status badge palette (the canonical mapping — reuse exactly, don't reinvent)
```php
'draft'    => ['#f5e5e5','#b0b0b0'],
'sent'     => ['#faf0f0','#6b6b6b'],
'viewed'   => ['#f2d0dc','#191919'],
'paid'     => ['#e04d80','#ffffff'],
'signed'   => ['#e04d80','#ffffff'],
'accepted' => ['#e04d80','#ffffff'],  // label displayed as "Signed"
'declined' => ['#f5e5e5','#b0b0b0'],
```
Amount column text turns pink (`#e04d80`) whenever the row's status is
paid/signed/accepted — this is the "money landed" signal, separate from the badge.

---

## Component rules

- **Accent bar → flat top corners.** Whenever a card has a colored bar/line at its top
  edge (e.g. the top-band on invoice/proposal documents, section cards on the proposal
  page), the card's top corners get `border-radius: 0` and only the bottom corners stay
  rounded (`border-radius: 0 0 20px 20px` or similar). This was an explicit correction —
  don't round all four corners when there's a top accent bar.
- **Stat cards:** `#faf0f0` blush background, no color bar, `12px` border-radius, no
  border. Distinct from the bordered white cards used elsewhere (chart section, table
  wrap) which use `1px solid #e8e8e8` + white background instead.
- **Three-dot action menu:** `⋯` button (32×32, `1px solid #e8e8e8`, 8px radius) opens an
  absolutely-positioned dropdown (`z-index: 100`, closes on outside click via a
  document-level click listener + `stopPropagation()` on the toggle). The table wrapper
  must use `overflow: visible` or the dropdown gets clipped — this bit us once.
- **Two-level filter tabs:** category row (`All / Invoices / Proposals / Drafts`, dark
  `#111111` active state) plus a conditional sub-row (`All / Active / Paid` or
  `All / Active / Signed`, pink `#e04d80` active state) that only appears once a
  non-"all" category is picked. Paid vs Signed sub-tab visibility toggles based on
  whether Invoices or Proposals is the active category.
- **Type pill** (Invoice/Proposal, in the activity table): `#c4dde8` for invoice,
  `#f2d0dc` for proposal. Separate concept from the status badge.
- **Toast:** fixed bottom-center, dark pill, fades in/out — the only feedback mechanism
  for async actions (delete, mark paid, copy link, create invoice). No inline error
  states elsewhere in the dashboard yet.
- **Confirm/delete modal:** centered overlay, white card, 4px pink top bar, cancel
  (blush-dark) + danger (pink) button pair. Reused for both invoice and proposal delete.

---

## Notification bell (added 2026-07-02)

- Bell icon in the topbar, left of Logout. Pink circular badge (16×16, white number,
  2px white border) shows unread count from `td_activity`, capped visually at "9+".
- Dropdown: 320px, right-aligned below the bell, `box-shadow: 0 8px 24px rgba(0,0,0,0.1)`.
- Each row: small colored chip for the event type (reusing `bell_chip()` helper, same
  palette as status badges) + a plain-English sentence built by `bell_sentence()` +
  relative timestamp from `bell_time()` (Just now / N min ago / N hr ago / Yesterday,
  h:mm / Mon D, h:mm).
- Read/unread: unread rows get a blush background + pink dot; "Mark all read" posts to
  `mark-read.php`.
- Helper functions (`bell_chip`, `bell_sentence`, `bell_time`, plus the
  `$unread_count`/`$bell_notifications` query) live in `admin/partials/topbar.php` now
  (extracted 2026-07, guarded with `function_exists()` so the include is safe even if
  something else defines them first) — every admin page gets the bell for free by
  including the topbar partial, nothing bell-specific to hand-wire per page anymore.
  `admin/all-activity.php` has its own separate `event_chip`/`event_sentence` pair with
  the same palette, since that page needs slightly different copy (full sentences with
  links, not the shorter bell versions) — the two new event types (`resent`, `emailed`)
  are handled in both places, keep them in sync if a third event type is ever added.

---

## Admin patterns added 2026-07

- **Settings page (`admin/settings.php`):** stacked `.settings-card` sections (white,
  `1px solid #e8e8e8`, 12px radius, 32px padding), each with a title + one-line
  description, then fields. `.field-group` (2 or 3 columns via `.triple`) groups related
  short inputs side by side (e.g. Payment Terms / Proposal Validity / Deposit %);
  standalone `.field` stacks full-width (address textarea, about blurb). One shared
  Save button at the bottom of the whole page saves every field across every card in
  one request, not per-card saves — simpler for both the UI and `save-settings.php`.
  If a future settings field doesn't fit the existing 3 cards (Company Info / Media Kit
  Stats / Contact & Brand / Documents), add a new `.settings-card`, don't force it into
  an unrelated one.
- **Send-to-client modal:** reused across `admin/index.php` (Dashboard row actions),
  `edit-invoice.php`, and `edit-proposal.php` — same visual pattern each time (email
  input prefilled from the record, optional personal-note textarea, Send button) but
  NOT extracted into a shared partial like the topbar was, since the three call sites
  wire up slightly different JS around it (Dashboard needs to know which row/type
  triggered it; the edit pages already know their own single record). If a 4th page
  needs this modal, that's probably the point to extract it — two duplicates was
  judged fine, a third wouldn't be.
- **Pure vs. status-changing actions must look distinct.** The Dashboard row menu has
  both "Send to client" (changes status, sends an email) and a separate small
  "Copy link" action (does neither) sitting next to each other — this split exists
  specifically because a single combined action used to silently mark documents as
  "sent" just from copying the link, which was misleading. If adding a new action that
  touches status, don't fold it into a "convenience" action that clients might expect
  to be side-effect-free.

---

## Client-facing documents (invoice/proposal)

Rebuilt 2026-07 from two approved static mockups (`invoice-split-screen.html`,
`proposal-landing.html`) — different visual register from the dashboard, and from each
other: these are the two pages an actual client sees, so they got more design
investment than anything else in the app. No sidebar/topbar chrome on either.

### Invoice (`invoice/index.php`) — split-screen

- `grid-template-columns: 1fr 420px` — the invoice document on the left (white card,
  `blush` `#faf0f0` page background, max-width 720px), a **sticky** dark payment panel
  on the right (`position: sticky; top: 0; height: 100vh`) that stays in view while the
  document scrolls.
- Payment panel: eyebrow → "Ready when **you** are." → amount due (56px, weight 200,
  `<sup>$</sup>` prefix) → status pill (colored dot + label: `card-sand` "Awaiting
  payment" / green-ish `#8fd6a4` "Paid — thank you!") → "Pay with card" button pinned to
  the bottom via `margin-top: auto` → Stripe security note → "What happens next?" copy
  → contact line. The whole panel gets an `.is-paid` class added to `<body>` when the
  invoice status is `paid`, which recolors the status dot/button and disables the
  button (`pointer-events: none`).
- Mobile (`max-width: 980px`): grid collapses to one column, payment panel goes
  `position: static` and stacks below the document instead of beside it.
- **Known fragile spot:** flex/grid children don't automatically shrink below their
  content's intrinsic width. The document card blew out past the viewport on phones
  until `min-width: 0` was added to `.doc-pane`/`.doc`, plus the line-items table got
  wrapped in an `overflow-x: auto` container with a `min-width` on the table itself
  (lets it scroll horizontally within its own box on very narrow screens instead of
  blowing out the page). If a future edit reintroduces horizontal overflow on mobile,
  check for a similar unshrinkable child before reaching for anything more drastic.
- `@media print` hides the payment panel only, as a courtesy — the real "get a PDF"
  path for proposals is Dompdf (see Architecture doc); the invoice has no PDF
  generation yet, print is genuinely just print here.

### Proposal (`proposal/index.php`) — landing page

Full-page sections, not a card: Hero → Stats band → About → Package/Deliverables →
Timeline → Signature → Footer.

- **Hero:** dark bg, two-column grid (copy + photo), `hero-name` up to 80px weight 300
  with an italic-weight-800 "×" — "Mallow × {campaign name or client name}". Photo is
  `object-fit: cover` inside a container with `border-radius: 0 0 0 200px` (one rounded
  corner, matching the mockup's asymmetric shape) — falls back to a 🐶 emoji if the
  image 404s (see Image sourcing below).
- **Stats band:** plain white background (not the old pink gradient), 4-column grid,
  large bold numbers. **All four values now come from `get_settings()`
  (`stat_followers`, `stat_impressions`, `stat_audience_age`+`_label`,
  `stat_partnerships`) — never hardcode these**, that was the whole point of adding
  Settings. Same for `about_blurb` in the About section.
- **Package card:** `card-rose` background, two-column (deliverables list + investment
  price), `pkg-list` items get a 🐾 emoji bullet via `::before`. Deliverables come from
  the proposal's `deliverables` column, which is **stored as a JSON array** (decoded
  with `json_decode()`), not newline-separated text — don't assume otherwise if
  touching this again.
- **Timeline:** three generic phases (Sign & kickoff / Content creation / Review &
  publish), intentionally static copy, not tied to `campaign_start`/`campaign_end` —
  those fields have no display slot on this page anymore (dropped in the redesign).
- **Signature section:** dark background (matches the hero), two-column — terms copy on
  the left, the actual sign form in a translucent card (`rgba(255,255,255,0.04)` bg,
  `1px solid rgba(255,255,255,0.1)`) on the right.
  - **Real `<canvas>` signature pad**, not a placeholder div. White background
    (deliberately — ink needs to be visible when composited into the white-background
    PDF later), dark `--ink` `#191919` stroke, 2px, round caps/joins. Vanilla JS, no
    library: `mousedown`/`mousemove`/`mouseup` for desktop, `touchstart`/`touchmove`/
    `touchend` (with `{passive:false}` so `preventDefault()` actually stops page
    scroll) for mobile. Scales for devicePixelRatio on setup so it isn't blurry on
    retina/phone screens. A small "Clear" text-button sits inline with the "Signature"
    label, right-aligned.
  - The submit button is `type="button"`, not `type="submit"` — even with the
    surrounding `<form onsubmit="return false;">`, a `type="submit"` button could fall
    through to a native form POST as a fallback path in some conditions. Don't change
    it back without a good reason.
  - **Confirmation state:** on successful sign, the form's parent swaps its
    `innerHTML` for a confirmation block — no page reload. Copy is exactly "Signed! A
    copy is on its way to your inbox" (relies on the emailed PDF attachment; there's no
    in-page download button anymore — the old one was `window.print()`, which just
    opened the browser print dialog and confused people into thinking it was broken).
    The **same markup renders server-side** (via a shared `render_signed_state()` PHP
    function) for a returning visitor who already signed, so the two paths never drift
    apart visually.
  - **Signed-state icon:** a `56×56px`, `14px`-radius `card-rose` square containing a
    small inline SVG checkmark stroked in `--pink` `#e04d80` — light pink square, dark
    pink check. Replaced a generic ✅ emoji 2026-07 specifically to stay on-brand rather
    than relying on whatever the OS renders for that emoji. The SVG is duplicated
    between the PHP function and the JS template string (same reason the confirmation
    markup itself is duplicated) — update both if it ever changes.
  - **Expired state:** same dark card, replaces the form entirely when the proposal's
    `expiry_date` has passed and it was never signed — icon + title + a mailto link to
    `contact_email`. This used to be a separate light-themed card outside the signature
    section; now it lives inside it, styled to match.
- **Footer:** plain white bar, company name pulled from `get_settings()`, "Design by
  MIUX Creative" left as static credit copy (not a settings field — it's Miu's own
  design attribution, not client-editable business data).

### Image sourcing (proposal hero + about photo)

Both photos are picked **randomly on every page load** from `proposal/images/` — a
folder that lives directly on the Hostinger server (`public_html/proposal/images/`),
uploaded via File Manager, **never deployed via git**. `random_proposal_image()` in
`proposal/index.php` globs `.jpg`/`.jpeg`/`.png`/`.webp` and picks one via
`array_rand()`; if the folder's empty or missing, falls back to a fixed placeholder
path, which itself falls back to an emoji if that 404s too — nothing crashes either way.
No naming convention or hero-vs-about pairing logic exists yet (both slots pick from the
same flat pool independently) — see TODO for the "proper naming convention" follow-up.

---

## Known issues / things to watch

- Proposals created before 2026-07-02 may have a **blank status** (`''`) because the
  `td_proposals.status` ENUM didn't originally include `'signed'` — any proposal that
  reached that state before the ENUM was altered silently stored an empty string
  instead of erroring. Reset any found via
  `UPDATE td_proposals SET status = 'draft' WHERE status = '';` and re-progress them
  manually if needed.
- Client reuse is only partially wired — `td_clients` exists and is looked up/updated by
  email in `save-invoice.php`, but proposals don't consistently do the same, and neither
  admin UI lets you pick an existing client from a list (see TODO.md).
- **The proposal's `notes` field got repurposed as the hero subhead** in the 2026-07
  redesign (falls back to a generic templated line using `stat_followers` if empty) —
  the new landing-page mockup had no dedicated "campaign overview" slot, so this was
  the best home for admin-entered personalization. Worth validating with real use that
  this reads well; a short note might work fine as a hero line, a long one might not.
- **"Previous Partnerships" partner-logo display is gone** from the client-facing
  proposal page (no slot in the new mockup) even though the underlying
  `td_partners`/`partner_industries` data and picker UI still exist in
  `new-proposal.php`/`edit-proposal.php`. Not a bug, but worth knowing before assuming
  those logos show up anywhere a client can see — see TODO.md for the open decision.
- **Pay button isn't real Stripe yet** — it links to the static `STRIPE_PAYMENT_URL`
  constant, same as before the redesign, just restyled. No amount-aware Checkout
  Session, no webhook to auto-flip status to `paid`. Main remaining build-queue item.
- **Mobile was verified in a resized desktop browser, not a real device**, for
  everything built 2026-07 (split-screen invoice, proposal landing page, canvas
  signature pad). Touch-event handling was tested via synthetic events in that same
  sandboxed environment — real-device testing (especially the signature pad, which is
  the part most likely to behave differently across actual iOS/Android browsers) is
  still outstanding, see TODO.md.
