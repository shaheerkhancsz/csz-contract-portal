# CSZ Contract Portal — Phase 1

Staff portal for Creative Solution Zone to create client agreements from templates and produce them as PDFs on the CSZ letterhead.

**Address:** https://contracts.creativesolutionzone.com
**Built with:** Laravel 13, Filament 5, mPDF, MySQL. Needs PHP 8.3 or newer with the `intl`, `mbstring`, `gd`, `pdo_mysql`, `fileinfo` and `zip` extensions.

## What Phase 1 does

| Area | What you get |
|---|---|
| Login and roles | Admin, Manager, Staff. Two-factor login with an authenticator app (on by default), password reset by email. |
| Clients | Company, contact, address, NTN/STRN, WHT flag, and the IDs for WHMCS / Zoho Books / Odoo (used in later phases). |
| Templates | Editor with fields `{{like_this}}`, optional clauses `[[if key]] … [[endif]]`, merge-tag button, and a version history. Saving a change creates a new version; contracts keep the version they were drafted from. |
| Contract builder | Pick client + template, fill a short form; end date, tax, WHT and renewal fee are worked out for you. Live preview and PDF preview with a DRAFT watermark. |
| Approval | Staff submit → Manager approves or sends back with a note. Approval freezes the text and stores the final PDF with a SHA-256 hash. |
| After approval | Upload the signed copy → Signed → Active / Expiring / Expired automatically by date. Duplicate a contract for a renewal. Cancel with a reason. |
| Settings | Letterhead details and logo, signatory, courts city, agreement number prefix, SRB sales tax and WHT rates, WHT base, brand colours. |
| Dashboard | Active, expiring in 30/60 days, awaiting approval, sent-not-signed, monthly revenue under contract. |
| Activity log | Every action: who, what, when, IP. |

Starter templates: **WordPress Website Maintenance (WMA)**, **Hosting Services (HOST)**, **Custom Web Application SLA (SLA)**.

Agreement numbers look like `CSZ-WMA-2026-0001`.

## Install on the cPanel server

1. **Subdomain.** cPanel → Domains → create `contracts.creativesolutionzone.com` with document root
   `/home/CPANELUSER/contracts/public` (the `public` folder, not the app folder). Turn on AutoSSL for it.
2. **Database.** cPanel → MySQL Databases: create a database and a user, give the user ALL PRIVILEGES on it.
3. **Upload.** File Manager → upload `csz-contract-portal.zip` to `/home/CPANELUSER/` and extract it. You get a `contracts` folder.
4. **Settings file.** In `contracts`, copy `.env.production.example` to `.env` and fill in: database name/user/password, mail password, and `ADMIN_NAME` / `ADMIN_EMAIL` / `ADMIN_PASSWORD` for the first admin.
5. **PHP version.** cPanel → MultiPHP Manager (or Select PHP Version): PHP 8.3 or 8.4 for the subdomain, with `intl` enabled.
6. **Set up the app.** cPanel → Terminal (or SSH as the cPanel user, not root):
   ```bash
   cd ~/contracts
   php artisan key:generate --force
   php artisan migrate --force
   php artisan db:seed --force          # first admin + the 3 starter templates
   php artisan optimize
   chmod -R ug+rwX storage bootstrap/cache
   ```
7. **Cron job.** cPanel → Cron Jobs → every minute:
   ```
   * * * * * /usr/local/bin/php /home/CPANELUSER/contracts/artisan schedule:run >/dev/null 2>&1
   ```
   (Use the same PHP path that works for your WHMCS cron. On this CloudLinux server it is `/usr/local/bin/php`.)
8. **First login.** Open https://contracts.creativesolutionzone.com, log in with the admin email/password, scan the QR code with an authenticator app, and save the recovery codes.
9. **Settings.** Administration → Settings: upload the official CSZ logo, enter the authorised signatory, NTN/STRN, and check the tax rates. Use **Preview letterhead** to check the result.
10. **Users.** Administration → Users: add managers and staff.

After changing `.env` later, run `php artisan optimize` again.

## Backups

Contracts and uploaded signed copies are in `storage/app/private` (not reachable from the web). Back up that folder and the MySQL database every night to the second server (OVH or Hetzner), as in the spec.

## Updating the code later

With Composer on the server (or locally): `composer install --no-dev -o`, then `php artisan migrate --force && php artisan optimize`.

## Coming next (Phase 2–4)

Online signing with email OTP and a certificate page · client area and WHMCS link · email notifications · Zoho Books connector (customers, recurring invoices, status sync, late fee) · renewal engine · AI-assisted clauses · remaining templates · Odoo connector.
