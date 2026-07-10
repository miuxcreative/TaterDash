# TaterDash — To Do

Living checklist. Check items off as they land; see [`CHANGELOG.md`](CHANGELOG.md) for
what's already shipped in detail.

---

## 🔴 Blocking — do these first

- [x] **Run `taterdash/migrate-error-log.sql`** in phpMyAdmin — creates `td_error_log`.
- [x] **Add `log_php_error()` to live `config.php`**.
- [ ] **Verify error logging end-to-end** — trigger a real failure through the sandbox
      UI, confirm a row lands in `td_error_log`.
- [x] **Run all migrations from the 2026-07-07/08 build** (tokens, activity events,
      pdf_path, settings ×2, signature_image) — see CHANGELOG for the full list, confirm
      none were missed.
- [x] **Sync live `config.php`** with every new helper added to `config.example.php`
      this round (`send_email`, `get_settings`, `get_setting`) — confirm all three exist
      live, not just the ones added first.
- [ ] **Activate Stripe** — see Build queue below, this is the last major placeholder.
- [x] **Self-directed code review of the 2026-07-07/08 build** — 8-angle review against
      everything from that round, fixed 5 confirmed bugs: stored XSS on the public
      proposal page (`notes` field wasn't escaped), a missing status guard on invoices
      (proposals got one, invoices didn't — could silently demote `paid` back to `sent`),
      a double-sign race condition, a JS-injection vector via unvalidated `client_email`
      in the dashboard, and misleading "Pay with card" copy that claimed Stripe checkout
      would happen when it isn't activated yet. Two more findings flagged below rather
      than fixed unilaterally.

## 🟠 End-to-end testing — needed before this goes near a real client

Everything below was verified in a local sandboxed mock (fake DB, fake email/PDF calls)
during development, which proves the *code paths* work — it does not prove the *live
site* works with real data, real email delivery, or on a real phone. Both matter before
handoff. Test on the live site, not localhost.

**Security & links**
- [ ] Hit `update-status.php`/`save-invoice.php` directly while logged out → confirm 401
- [ ] Open an old `?id=` invoice/proposal link → still works (fallback intact)
- [ ] Open a new `?t=TOKEN` link → works; confirm the token isn't guessable/sequential

**Send flow**
- [ ] Send a real invoice to your own inbox — confirm branded email arrives, layout
      holds up in Gmail *and* Apple Mail (table-based email HTML can render differently)
- [ ] Send a real proposal the same way
- [ ] Resend the same invoice — confirm status doesn't change, a "Resent" event shows in
      the bell + All Activity, no duplicate "Sent" event
- [ ] Use the plain "Copy link" action — confirm it never changes status
- [ ] Mark a real invoice paid, confirm the split-screen payment panel flips state

**Signing**
- [ ] Sign a real proposal end-to-end **on an actual phone** (not a resized browser) —
      draw with a finger, use Clear, submit
- [ ] Confirm the signed PDF attachment opens correctly on both a phone mail app and
      desktop
- [ ] Confirm the confirmation state persists on reload (return visit) with no download
      button needed
- [ ] Test an expired proposal link — friendly notice, not the form
- [ ] Confirm the "Signed PDF" download button in the Dashboard/Edit Proposal works and
      the file isn't reachable by guessing the `generated-pdfs/` path directly

**Settings**
- [ ] Change company address in Settings, confirm it shows correctly on the next real
      invoice sent (not just a page reload of an old one)
- [ ] Change a media-kit stat, confirm it reflects on the next proposal opened

**Mobile — broad pass, not just the signature pad**
- [ ] Full invoice view on a phone (not just signing) — split-screen stacking, line
      items readable, Pay button reachable
- [ ] Full proposal view on a phone — hero, stats, package card, timeline all readable
- [ ] Admin dashboard on a phone/tablet if Gina or Miu ever need to act on the go
      (not designed for this yet — flag if it's actually needed)

**Images**
- [ ] Confirm real photos are uploaded to `public_html/proposal/images/` and the random
      picker is choosing from them (not falling back to the placeholder/emoji)

## 🟡 Data migration — waiting on real client/invoice data from Milena

- [ ] Gather existing customer + invoice history (spreadsheet, export, or a list)
- [ ] Run `taterdash/wipe-test-data.sql` — clears all demo data, resets IDs
- [ ] Build + run the import (script depends on the data format once provided)

## 🟢 Build queue — in priority order

- [ ] **Activate Stripe** — real Checkout Session per invoice via the API, with the
      invoice amount passed through automatically. Replaces the current static
      `STRIPE_PAYMENT_URL` placeholder (currently just a disabled/greyed "Pay with
      card" button on the new split-screen invoice page). Needs a Stripe account + API
      keys, plus webhook handling to flip an invoice to `paid` automatically instead of
      relying on a manual "Mark as paid" click. This also unlocks the "Payment
      processing" status pill state that's designed but has no trigger yet.
- [ ] **Mobile pass** — see testing checklist above; fix whatever it turns up. The
      client-facing pages were built mobile-first this round, but haven't been tested
      on a real device yet, only resized browser windows.
- [ ] **Proper proposal images + naming convention** — Milena is uploading stock photos
      to `proposal/images/` to use randomly for now (see CHANGELOG); a real system
      (curated photo per proposal, or at least separate hero/about pools so crops don't
      look random) is future work.
- [ ] **Partner logo section** — the old "Previous Partnerships" logo grid was dropped
      in the client-facing redesign (the new mockup didn't have a slot for it). Decide
      whether it comes back in some form, or whether `td_partners` becomes unused.
- [ ] **`?id=` fallback removal** — `invoice/index.php` and `proposal/index.php` still
      accept the old id-based links alongside the new token links (marked `TODO: remove
      after launch` in both files). Remove once confident no old links are still in use.
      Worth noting: until removed, this fully bypasses the token security model for any
      `?id=N` guess, not just genuinely old links — confirmed in the 2026-07-08 code
      review.
- [ ] **Confirm the Terms/campaign-dates drop on the client-facing proposal was
      intentional** — the redesign's mockup didn't include the old Terms bullet list
      (7-day delivery window, revision billing, deposit/Net-7 terms, repost rights, etc.)
      or the campaign start/end dates the client used to see before signing. Neither
      appears anywhere now — not the signing page, not the emailed/archived PDF. This was
      a faithful port of the approved mockup, not a coding mistake, but it's a real
      content/legal loss worth a deliberate yes/no rather than staying silently dropped.
- [ ] **`get_setting()` singular helper is unused** — added alongside `get_settings()`
      for convenience but no call site actually uses it yet. Harmless, but worth using
      or removing next time settings code is touched.

## 🔵 Backlog

Sequenced — do these in order, each is a separate project:

1. [ ] **White-label / multi-tenant exploration (TaterDash part)** — every table needs
       a `tenant_id`, auth becomes multi-tenant, branding becomes per-tenant config.
       Needs its own planning pass once the single-tenant product is stable. This is the
       foundational piece; the G Space item below builds on it rather than shipping
       standalone.
2. [ ] **G Space Agency branding option** — a way to send an invoice/proposal under
       "G Space Agency LLC" instead of "Mallow Frenchie." Deliberately sequenced *after*
       the white-label work above, not before — treat it as an application of that
       tenant/branding model rather than a quick standalone toggle. Needs a real
       conversation before building either piece: where the choice lives, whether it
       affects the PDF/email too, etc.
- [ ] **Full QA pass before handoff** — broad manual testing of the whole app once the
      build queue is done.
- [ ] **Full audit** — security (session/auth, input handling), data integrity
      (orphaned records, ENUM mismatches), UX edge cases. Do this after the build queue,
      not before.
