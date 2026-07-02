# TaterDash 🐾

> Business management for [@mallowfrenchie](https://instagram.com/mallowfrenchie) — invoices, proposals, and client tracking.

Built with PHP + MySQL on Hostinger. Branded with the Mallow Frenchie design system (Satoshi, #e04d80).

---

## Structure

```
TaterDash/
  taterdash/
    config.example.php   ← copy to config.php, add real credentials
    config.php           ← local + Hostinger only, never in git
    save-invoice.php     ← POST endpoint, saves invoice to DB
    new-invoice.html     ← Gina's invoice form + live preview
    .htaccess            ← blocks config.php from web, forces HTTPS
  invoice/
    index.php            ← client-facing branded invoice view
  admin/
    index.php            ← Gina's dashboard (Phase 2)
  database/
    schema.sql           ← run once in phpMyAdmin to create tables
```

---

## Setup

### 1. Database
Run `database/schema.sql` in phpMyAdmin on `u335521326_TaterDash_db`.

Tables created:
- `td_clients`
- `td_invoices`
- `td_line_items`
- `td_proposals`
- `td_signatures`

### 2. Config
```bash
cp taterdash/config.example.php taterdash/config.php
```
Edit `config.php` with real DB password and admin password.

### 3. Deploy to Hostinger
Upload all files to `public_html/` via FTP or Hostinger File Manager.
`config.php` must exist on the server but is excluded from git.

---

## URLs

| URL | Who sees it |
|---|---|
| `mallowfrenchie.com/taterdash/new-invoice.html` | Gina (internal) |
| `mallowfrenchie.com/invoice/?id=1` | Client (public link) |
| `mallowfrenchie.com/admin/` | Gina (password protected) |

---

## Phases

- [x] **Phase 1** — Invoice form, DB save, client view
- [ ] **Phase 2** — Admin dashboard, invoice list, year-end totals
- [ ] **Phase 3** — Proposals + e-signature + EmailJS confirmation
- [ ] **Phase 4** — Stripe API integration (dynamic payment links)

---

## Stack

- PHP 8+ / MySQL (Hostinger Business plan)
- Vanilla JS (no framework)
- Satoshi typeface via Fontshare
- Mallow Frenchie brand system v0.2

---

## Brand

Design system docs: `miuxcreative.github.io/mallowfrenchie`
Brand skill: `mallow-frenchie-brand` (MIUX Creative skill files)

---

*TaterDash by MIUX Creative · madebymiu.com · G-Space Agency*
