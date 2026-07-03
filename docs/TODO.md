# TaterDash — To Do

Living checklist. Check items off as they land; see [`CHANGELOG.md`](CHANGELOG.md) for
what's already shipped in detail.

---

## 🔴 Blocking — do these first

- [ ] **Run `taterdash/migrate-error-log.sql`** in phpMyAdmin — creates `td_error_log`.
      Without it, error logging silently no-ops.
- [ ] **Add `log_php_error()` to live `config.php`** — copy from `config.example.php`
      (sits above `generate_slug()`). Deploy doesn't touch `config.php`, it's manual.

## 🟡 Data migration — waiting on real client/invoice data from Milena

- [ ] Gather existing customer + invoice history (spreadsheet, export, or a list)
- [ ] Run `taterdash/wipe-test-data.sql` — clears all demo data, resets IDs
- [ ] Build + run the import (script depends on the data format once provided)

## 🟢 Build queue — in priority order

- [ ] **Stripe** — real Checkout Session per invoice via the API, with the invoice
      amount passed through automatically. Replaces the current static
      `STRIPE_PAYMENT_URL` placeholder (disabled "Pay Now" button). Needs Stripe
      account + API keys.
- [ ] **Email delivery** — decide EmailJS (simple, client-side, limited) vs. a
      server-side sender like Postmark/SendGrid (more reliable, needed for a custom
      sending domain). Wire up:
      - Proposal signed → confirmation email to client + notify Gina
        (`fire_emailjs()` in `proposal/index.php` is currently just a `console.log` stub)
      - Invoice sent → email with the payment link
- [ ] **Proposal visual polish** — borrow RF Studios' styling/interaction craft (PDF
      treatment, layout/animation polish) on top of the existing DB-driven proposal
      system. Confirmed: visual polish only, not their static-file architecture.
- [ ] **Settings page** — currently a "Coming soon" placeholder. Needs real content:
      account info, branding, notification preferences.
- [ ] **Partner logo image uploads** — proposal builder currently references partner
      logos by URL only; no upload flow exists yet.
- [ ] **PDF styling refinement** — invoice/proposal "Download PDF" is just
      `window.print()` with `@media print` rules; no real PDF generation library.

## 🔵 Later

- [ ] **Full audit** — security (session/auth, input handling), data integrity
      (orphaned records, ENUM mismatches like the proposal `'signed'` bug we already
      hit once), UX edge cases. Do this after the build queue above, not before —
      otherwise it just re-surfaces gaps we already know about.
- [ ] **White-label exploration** (G-Space Agency / MIUX) — this is a multi-tenant
      data-model question (every table needs a `tenant_id`, auth becomes multi-tenant,
      branding becomes per-tenant config), not a simple feature. Needs its own planning
      pass once the single-tenant product is stable.
