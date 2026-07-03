# TaterDash — Changelog

Running log of what's shipped, what's pending manual activation, and what's next.
See [`ARCHITECTURE.md`](ARCHITECTURE.md) and [`UI-SYSTEM.md`](UI-SYSTEM.md) for the
system reference; this file is just the timeline.

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

- Wipe test/demo data from the live database, then import real historical
  clients + invoices (in progress — see below).
- Stripe: real Checkout Session per invoice with a prefilled amount.
- Proposal system visual polish (styling/interaction only — not the RF Studios
  static-file architecture, confirmed out of scope).
- Email delivery: EmailJS vs. a server-side sender — still an open decision.
- Full audit (security, data integrity, edge cases).
- White-label exploration for G-Space Agency / MIUX — its own planning pass, not a
  simple feature addition.
