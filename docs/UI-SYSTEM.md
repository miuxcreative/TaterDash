# TaterDash — UI System

*Last updated: 2026-07-02*

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
- **Topbar:** fixed, `left: 240px`, 52px tall, white background, `1px solid #e8e8e8`
  bottom border, `z-index: 200`. Page title left-aligned; right side holds page actions
  (Help button, notification bell, Logout). This topbar must stay identical across every
  admin page — it was explicitly unified after inconsistency became a problem.
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
| Dark (sidebar/footer) | `#111111` | Sidebar bg, dark topbar (non-dashboard pages), footer |
| Blush | `#faf0f0` | Stat card backgrounds, hover states |
| Card Rose | `#f2d0dc` | "Viewed" badge, invoice top-band accent |
| Card Sky | `#c4dde8` | "Invoice" type pill |
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
- Helper functions (`bell_chip`, `bell_sentence`, `bell_time`, plus the `$unread_count` /
  `$bell_notifications` query block) currently live inline in `admin/index.php`. If a
  second admin page needs the bell, extract these into a shared include rather than
  copy-pasting — they were written once and are already a candidate for that.

---

## Client-facing documents (invoice/proposal)

- Different visual register from the dashboard: designed to be read/printed, not
  operated. Max-width `740px` (invoice) / `720px` (proposal) centered card on a neutral
  `#e4e4e4`/`#f0f0f0` page background, drop shadow, no sidebar/topbar chrome.
- Proposal page has a full marketing-style hero (dark bg, stats bar, "as seen in" press
  bar) before the actual proposal content — much more elaborate than the invoice, which
  is a straightforward line-item document.
- `@media print` rules strip interactive chrome (pay button, sign button) for the
  "Download PDF" flow, which is just `window.print()` — there's no real PDF generation
  library in use anywhere yet.

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
  admin UI lets you pick an existing client from a list (see Next Steps).
