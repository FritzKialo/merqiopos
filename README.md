# Merqio POS

A multi-tenant SaaS business management platform built for Kenyan small and medium enterprises. Available at [merqiopos.com](https://merqiopos.com) — the domain migration (2026-09-04) is done: merqiopos.com serves the identical app and database as the original [smemanagers.com](https://smemanagers.com) (kept running in parallel — it's the exact same site behind both names, not a separate copy — until every business's own M-Pesa/Pesapal callback registrations have been re-pointed at the new domain, at which point smemanagers.com is free to be repurposed).

> **Naming note:** a full text-level rebrand pass (2026-09-04) removed "SME Manager"/"smemanagers.com" from class/config comments, transactional emails, and support-contact addresses throughout the codebase — support/no-reply mail now goes through `@merqiopos.com`. Two things were deliberately **not** renamed, on purpose, given the risk/benefit tradeoff: the production **database name and DB user** (`your_database` / `your_db_user`) and the **server/project directory names** (`sme_manager`, `~/smemanagers`, `~/smemanagers.com`) — nobody outside direct DB/server admin access ever sees either, and the app's own `index.php` hardcodes the directory path as a literal string, so renaming it means a careful, dedicated migration, not a text find-and-replace.

Originally scoped as inventory + POS + expenses + basic reporting, the platform has grown substantially beyond that: full HR/payroll, multi-branch operations, a customer-facing storefront, bookings, loyalty/marketing tools, and a broad finance/purchasing suite now all live under one login. See [Features](#features) below for the real current scope — it's considerably larger than a first glance at the marketing pages suggests.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 12, PHP 8.2+ |
| Database | MySQL 8.0+ |
| Frontend | Blade templates, vanilla JS, Chart.js (CDN) |
| PDF | barryvdh/laravel-dompdf |
| Spreadsheets | phpoffice/phpspreadsheet (Excel / ODS import & export) |
| QR codes | bacon/bacon-qr-code (2FA setup) |
| Payments | M-Pesa Daraja (STK Push + C2B + B2C), Paystack (platform subscriptions), Pesapal v3 (card) |
| SMS | Africa's Talking / Twilio (direct HTTP integration, no SDK dependency) |
| 2FA | pragmarx/google2fa-laravel |

No frontend build framework (React/Vue) — every page is server-rendered Blade with vanilla JS for interactivity (POS cart, live search, charts).

---

## Data Model Overview

The platform uses a two-level multi-tenant hierarchy:

```
Organization  (owned by one user — the "owner")
    └── Business / Store  (one or many, depending on plan)
            └── Users  (attached via pivot with a role)
```

- An **Organization** is the top-level billing entity. It holds the subscription plan, owner details, and org-level settings.
- A **Business** (store/branch) is an operational unit with its own inventory, sales, customers, staff, and reports.
- A single owner logs in, selects their active store via the **store switcher** in the sidebar, and manages it. Owners and overall managers can switch between stores without logging out.
- Users (managers, cashiers, staff) are attached to one or more stores via the `business_user` pivot table with a role per store.

### Tenant scoping — the correct pattern

Every tenant-scoped query must be scoped to the user's **currently active** store, not a legacy fixed field:

```php
// Correct — resolves the store the user is actually working in right now,
// which can differ from Auth::user()->business_id for any user with
// access to more than one store.
$businessId = Auth::user()->currentBusiness()->id;

// Wrong — a stale/legacy column that does not track the active store
// switch. This exact mistake has caused real production bugs (false
// "already in use" validation errors, cross-store data mixups) multiple
// times in this codebase — grep for `Auth::user()->business_id` before
// adding new code and fix it wherever it still appears.
$businessId = Auth::user()->business_id;
```

---

## Features

The feature list below is organized by module. Given the size of the app (80+ controllers, 100+ Eloquent models), this documents what exists rather than every field and workflow — see the relevant controller/view for exact behavior.

### Sales & POS
- Point of sale with barcode scanning (USB/BT scanner + camera), live product search, cart, variants, serials, and bundles
- A dedicated **kiosk-mode** POS screen (hides the app's sidebar/topbar for the till itself) with its own top bar (invoice number, scanner status, printer connect, void sale, back, logout) and a bottom "nav dock" of role-gated quick-access tiles to other parts of the app
- Cash, M-Pesa (STK Push), bank transfer, and credit payment methods; on-screen numeric keypad for touch registers
- Discounts (percentage/flat), coupon codes, loyalty-point redemption at checkout
- Offline-first: sales made while offline are queued in IndexedDB and synced automatically on reconnect (service worker)
- Sales history, customer returns (RMA) with restock/write-off and refund/store-credit, shift management (float, cash-count, variance)
- Product bundles, product variants, serial-number tracking, batch tracking

### Home-menu launcher
Every store role (owner, overall manager, manager, cashier, staff) lands on a colorful tile "app launcher" screen right after login instead of a fixed per-role page — see `HomeController`. Each tile is gated to exactly what that role can reach (same conditions as the sidebar); staff, who can't access most of the app, simply see a single "Dashboard" tile.

### Inventory
- Product catalogue: categories with one level of sub-categories, SKU, barcode (with symbology), stock quantity, reorder level, buy-unit vs sell-unit conversion, brand, product image + gallery, featured/hide-in-POS/hide-in-shop flags
- Bulk import (CSV/Excel/ODS) as a background job; stock adjustments with reason codes; stock receiving against a PO or standalone; inter-store stock transfers; physical stock counts; product waitlist (notify when back in stock)
- Low-stock alerts (scheduled email)

### Sales-adjacent finance
- Invoices (with PDF), quotes/estimates, recurring invoices, proforma invoices, credit notes, delivery notes
- Customer credit accounts (limit, ledger, running balance), customer deposits
- Withholding tax, VAT (registration, per-product override, VAT return report)

### Purchasing
- Suppliers, purchase orders (full lifecycle), purchase requisitions, supplier credit notes, supplier payments

### Expenses & cash
- Expense tracking with categories and attachments, staff expense claims (submit → approve → pay)
- Petty cash accounts, cash registers, bank accounts, bank statement import + reconciliation, business loans, budgets

### Customers & marketing
- Customer profiles, tags, custom fields, purchase history, credit/deposit accounts
- Loyalty program (points, redemption), coupons, discount codes, marketing campaigns, payment links
- Price tiers (customer-group pricing), product reviews
- A public **online shop / storefront** per business (its own layout, independent of the main app's responsive CSS) with online orders and M-Pesa checkout

### Bookings
- Services, appointments (calendar), and a restaurant table/floor-plan module with table orders — a whole booking subsystem with no top-level marketing mention

### HR & payroll (organization plans)
- Staff HR profiles, leave requests + leave types + balances, attendance (clock in/out sessions and records), salary advances, staff commissions
- Payroll: PAYE/NSSF/SHIF calculation for the 2024/25 KRA tax year, payslips, P9 certificates, selective/auto payroll disbursement (M-Pesa B2C)
- A dedicated **staff self-service portal dashboard** (separate Blade view, `dashboard.staff`) for the `staff` role: payslips, leave, attendance clock-in, salary advances — staff have no access to POS, inventory, customers, or bookings anywhere in the app (see the `staff` row in [User Roles](#user-roles))

### Reporting & audit
- Sales, profit & loss, gross margin, VAT return, stock reports
- An audit log (`AuditLog`) with two historically-merged schemas (`event`/`subject_*`/`metadata` and `action`/`model_*`/`old_values`/`new_values`, coalesced by the controller) — populated by a generic `LogsActivity` trait (auto-logs create/update/delete diffs, opt-in per model) and a dedicated `LogsBusinessSettings` trait (curated field whitelist + redaction for secrets like M-Pesa/SMS credentials, since `Business` is written to far too often for unrelated reasons to log every save)

### Platform
- Full multi-tenant data isolation — see [Tenant scoping](#tenant-scoping--the-correct-pattern) above
- Roles: `owner`, `overall_manager`, `manager`, `cashier`, `staff`, plus a separate `is_super_admin` flag — see [User Roles](#user-roles)
- Store switcher for owners/overall managers managing multiple stores
- Two-factor authentication (TOTP) with sudo-mode re-verification for sensitive actions
- Feature gating via `config/plans.php` — `$business->hasFeature('key')` (store-level) / `$organization->hasFeature('key')` (org-level)
- Webhooks (outgoing, with delivery records), a REST API (Enterprise), custom field definitions
- Multi-currency rates, multi-language (English/Swahili)
- Super admin panel at `/admin`
- PWA — installable, offline-capable service worker
- Fully responsive — mobile, tablet, and desktop (several distinct responsive subsystems: main app `responsive.css`, a separate `admin.css` mobile system, and the customer portal's own bespoke mobile CSS — they don't share code, so a fix in one doesn't apply to the others)

---

## Kenyan Statutory Compliance

The payroll engine implements Kenya Revenue Authority (KRA) requirements for the 2024/25 tax year:

| Statutory | Rate | Notes |
|---|---|---|
| **PAYE** | KRA 2024/25 progressive bands | Personal relief KSh 2,400/month applied automatically |
| **NSSF** (Act 2013) | 6% employee + 6% employer | Capped per tier as set by NSSF |
| **SHIF** | 2.75% of gross | Replaces NHIF; no cap |

Per-deduction enable/disable toggles are in **Settings → Payroll** so businesses that are not yet registered for a statutory can still use payroll without incorrect deductions.

P9 annual tax certificates are generated as downloadable PDFs for each employee, month-indexed with annual totals.

---

## Subscription Plans — Feature Keys

All feature gating is driven by `config/plans.php`. Use `$business->hasFeature('key')` for store-level keys and `$organization->hasFeature('key')` for org-level keys anywhere in the app.

**Store-level plan keys (`free` / `starter` / `business` / `enterprise`):**

| Key | Starter | Business | Enterprise |
|---|---|---|---|
| `dashboard` | ✓ | ✓ | ✓ |
| `inventory` | ✓ | ✓ | ✓ |
| `sales` | ✓ | ✓ | ✓ |
| `stock_adjustments` | ✓ | ✓ | ✓ |
| `reports_basic` | ✓ | ✓ | ✓ |
| `customers` | ✓ | ✓ | ✓ |
| `mpesa_sales` | ✓ | ✓ | ✓ |
| `expenses` | ✗ | ✓ | ✓ |
| `pdf_invoices` | ✗ | ✓ | ✓ |
| `reports_advanced` | ✗ | ✓ | ✓ |
| `team_management` | ✗ | ✓ | ✓ |
| `unlimited_products` | ✗ | ✓ | ✓ |
| `data_export` | ✗ | ✓ | ✓ |
| `quotes` | ✗ | ✓ | ✓ |
| `suppliers` | ✗ | ✓ | ✓ |
| `recurring_invoices` | ✗ | ✓ | ✓ |
| `sms_notifications` | ✗ | ✓ | ✓ |
| `barcode` | ✗ | ✗ | ✓ | ✓ |
| `unlimited_users` | ✗ | ✗ | ✗ | ✓ |
| `api_access` | ✗ | ✗ | ✗ | ✓ |

Plan limits (`config/plans.php`): Starter (KSh 999/mo) — 200 products, 3 users. Business (KSh 2,999/mo) — unlimited products, 15 users. Enterprise (KSh 5,999/mo, store-level) — unlimited products, unlimited users.

**No free tier** — collapsed from 5 org tiers / 4 store tiers down to exactly 3 of each in a 2026-09-12 pricing restructure (see `config/plans.php`'s own header comment). Only the Solo org plan carries a trial (1 month); Growth and Enterprise are billed from signup. A lapsed trial or subscription redirects to the `subscription.required` paywall instead of falling back to a free plan — there is no more "trial grants Business-plan feature access" fallback in `hasFeature()`.

**Organization-level plan keys (`solo` / `growth` / `enterprise`):**

| Key | Solo | Growth | Enterprise |
|---|---|---|---|
| `payroll` | ✓ | ✓ | ✓ |
| `payroll_statutory` | ✗ | ✓ | ✓ |
| `p9_forms` | ✗ | ✓ | ✓ |
| `cross_store_reports` | ✗ | ✓ | ✓ |
| `api_access` | ✗ | ✗ | ✓ |

| Org plan | Price | Stores | Users |
|---|---|---|---|
| Solo | KSh 999/mo (1-month free trial) | 1 | 3 |
| Growth | KSh 2,999/mo | 3 | 15 |
| Enterprise | KSh 5,999/mo | Unlimited | Unlimited |

Each org plan also grants its stores a specific store-level plan (`store_plan` in config) — Solo → Starter, Growth → Business, Enterprise → Enterprise, for every store, regardless of that store's own billing.

---

## User Roles

| Role | Access |
|---|---|
| `owner` | Full access — settings, team, subscription, billing, payroll approval, audit log, all data, org-wide dashboard, can switch between stores |
| `overall_manager` | Cross-branch oversight — org dashboard, branch management, inter-branch transfers, cross-branch reports. **No POS access** (explicitly blocked in `SalesController`, since the `role:owner,...` route middleware can't exclude them — they inherit "owner" permissions on any route that allows it) |
| `manager` | Inventory, sales/POS, quotes, suppliers, expenses, invoices, shifts, stock receives, returns, reports, team settings for their store |
| `cashier` | Sales/POS, barcode scanning, inventory view (no write access), customers, bookings |
| `staff` | HR/payroll self-service only — their own portal dashboard (payslips, leave, attendance, salary advances). Excluded from every other top-level section (sales, inventory, customers, bookings) at both the route-middleware and sidebar level |
| *(flag)* `is_super_admin` | Platform admin panel at `/admin` — manage all organizations. Independent of the `role` column; a super admin can also hold a normal store role |

---

## Key Models

The app has **100+ Eloquent models** — this table lists the core ones; the rest live in `app/Models/` grouped roughly by the feature areas above (bookings: `Appointment`, `Service`, `RestaurantTable`, `TableOrder`; marketing: `Campaign`, `Coupon`, `Discount`, `LoyaltyProgram`, `PaymentLink`, `PriceTier`; finance: `CreditNote`, `DeliveryNote`, `ProformaInvoice`, `WithholdingTax`, `BankAccount`, `BankStatementImport`, `Budget`, `PettyCashAccount`, `BusinessLoan`; purchasing: `PurchaseRequisition`, `SupplierCreditNote`, `SupplierPayment`; HR: `LeaveRequest`, `LeaveType`, `LeaveBalance`, `AttendanceRecord`, `AttendanceSession`, `SalaryAdvance`, `CommissionRule`, `ExpenseClaim`; and more).

| Model | Table | Notes |
|---|---|---|
| `Organization` | `organizations` | Top-level billing entity |
| `Business` | `businesses` | Store/branch; belongs to Organization |
| `User` | `users` | Attached to businesses via `business_user` pivot; `role` enum + `is_super_admin` flag |
| `StaffProfile` | `staff_profiles` | HR record; one per user per business |
| `Product` | `products` | Inventory item; per business — image/gallery, brand, buy/sell unit, featured flag |
| `Sale` + `SaleItem` | `sales`, `sale_items` | POS transaction |
| `SaleReturn` + `SaleReturnItem` | `sale_returns`, `sale_return_items` | Customer RMA |
| `StockAdjustment` | `stock_adjustments` | Manual stock change with reason |
| `StockReceive` + `StockReceiveItem` | `stock_receives`, `stock_receive_items` | GRN; reference `RCV-XXXXX` |
| `StockCount` + `StockCountItem` | `stock_counts`, `stock_count_items` | Physical stock count |
| `Shift` | `shifts` | POS shift with opening float and cash variance |
| `Invoice` + `InvoiceItem` + `InvoicePayment` | `invoices`, `invoice_items`, `invoice_payments` | Customer tax invoice; reference `INV-XXXXX` |
| `Quote` + `QuoteItem` | `quotes`, `quote_items` | Estimate / quotation |
| `RecurringInvoice` + `RecurringInvoiceItem` | `recurring_invoices`, `recurring_invoice_items` | Auto-generate sales on schedule |
| `Customer` | `customers` | Includes `credit_limit` and `credit_balance` |
| `CustomerCredit` | `customer_credits` | Credit-sale / repayment / store-credit ledger |
| `Supplier` | `suppliers` | Supplier contacts |
| `PurchaseOrder` + `PurchaseOrderItem` | `purchase_orders`, `purchase_order_items` | PO lifecycle |
| `Expense` + `ExpenseCategory` | `expenses`, `expense_categories` | Business expenses |
| `PayrollPeriod` + `PayrollItem` | `payroll_periods`, `payroll_items` | Payroll cycle and per-employee calculation |
| `StockTransfer` + `StockTransferItem` | `stock_transfers`, `stock_transfer_items` | Inter-store transfer; reference `TRF-XXXXX` |
| `AuditLog` | `audit_logs` | Event log — two merged schemas, see [Reporting & audit](#reporting--audit) |
| `MpesaTransaction` | `mpesa_transactions` | STK push / C2B / callback record |
| `PaystackTransaction` | `paystack_transactions` | Subscription payment via Paystack |
| `PesapalTransaction` | `pesapal_transactions` | Card payment via Pesapal |
| `Subscription` | `subscriptions` | Org billing history |
| `Webhook` + `WebhookDelivery` | `webhooks`, `webhook_deliveries` | Outgoing webhook config + delivery log |

---

## Routes Summary

All business-scoped routes sit inside the authenticated middleware group (`auth`, `2fa`, `subscribed`, `store.limit`). This lists the major groups — see `routes/web.php` (and `routes/features_*.php`, which are `require()`'d from it) for the full ~80-controller route surface.

| Group | Prefix | Notes |
|---|---|---|
| Home menu | `/home` (route name `menu`) | The post-login tile launcher — named `menu`, not `home`, because the public marketing homepage already owns the `home` route name |
| Dashboard | `/dashboard` | Business, org, and staff-portal dashboards (role-branched in `DashboardController`) |
| Inventory | `/inventory` | Products, import, adjustments, receives, stock counts, waitlist |
| Sales / POS | `/sales` | POS (`sales.create`), history, returns |
| Shifts | `/shifts` | Open, show, close |
| Invoices | `/invoices` | CRUD, send, payments, PDF |
| Quotes / Proforma | `/quotes`, `/proforma-invoices` | CRUD, convert to sale |
| Recurring | `/recurring` | Auto-invoice schedules |
| Credit / Delivery | `/credit-notes`, `/delivery-notes` | Customer credit notes, delivery notes |
| Expenses | `/expenses`, `/expense-claims` | Business expenses; staff expense claims |
| Customers | `/customers` | CRUD, credit accounts, deposits, tags |
| Suppliers / Purchasing | `/suppliers`, `/purchases`, `/purchase-requisitions`, `/supplier-credit-notes` | Full purchasing lifecycle |
| Bookings | `/services`, `/appointments`, `/tables` | Services, appointment calendar, restaurant tables |
| Marketing | `/discounts`, `/coupons`, `/campaigns`, `/payment-links`, `/price-tiers`, `/product-reviews` | Marketing/loyalty tools |
| Online shop | `/shop/{slug}` | Public per-business storefront; separate layout from the main app |
| Reports | `/reports` | Sales, P&L, gross margin, VAT return, stock |
| Payroll / HR | `/payroll`, `/staff`, `/staff/leave`, `/staff/attendance`, `/staff/advances`, `/staff/commissions` | Periods, items, payslips, P9, HR self-service |
| Audit Log | `/audit-log` | Owner only |
| Org / Transfers | `/org/*` | Org dashboard, store switcher, inter-store transfers |
| Settings | `/settings/*` | Business, team, M-Pesa, Pesapal, SMS, payroll, VAT, webhooks, API, 2FA |
| Admin | `/admin` | Super admin panel |
| API | `/api/v1/*` | REST API (Enterprise org plan) |

---

## Requirements

- PHP 8.2+
- MySQL 8.0+
- Composer
- Node.js 18+ & npm

> **Note:** `phpoffice/phpspreadsheet` requires the `ext-gd` PHP extension for Excel image support. If `ext-gd` is not installed and image handling is not needed, install with `composer install --ignore-platform-req=ext-gd`.

---

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/yourorg/sme-managers.git
cd sme-managers
```

> This project is not currently under git version control in its live working copy — deploys happen by FTP-uploading individual changed files, not `git pull`. If you're setting up a fresh clone from a repository that does exist, the steps below still apply.

### 2. Install dependencies

```bash
composer install
npm install && npm run build
```

### 3. Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your database, mail, and M-Pesa credentials (see the table below).

### 4. Run migrations

```bash
php artisan migrate
```

### 5. Seed demo data (optional)

```bash
php artisan db:seed
```

Check `database/seeders/DatabaseSeeder.php` for what accounts/data this actually creates in the current codebase — the seeder has likely grown alongside the schema and may not match any specific set of demo credentials documented here.

### 6. Set permissions

```bash
chmod -R 775 storage bootstrap/cache
php artisan storage:link
```

> **Production note:** on shared hosting where the web docroot is a *different* physical directory from the Laravel app root (as it is for both merqiopos.com and smemanagers.com — each has its own docroot folder, both bootstrapping the same shared app root via a hardcoded path in `index.php`), Laravel's default `public/storage` symlink is created inside the app's own `public/` folder and is **never web-accessible**. The symlink must instead be created at `<web-docroot>/storage` pointing to `<app-root>/storage/app/public`. This was broken for this app's entire production lifetime until caught and fixed — if uploaded files (product images, attachments) 404 on a fresh deploy, check this first.

### 7. Start the queue worker

Several features require a queue worker (product import, email delivery, M-Pesa B2C payroll disbursement). In production, run this as a supervised process via Supervisor:

```bash
php artisan queue:work
```

For local development, set `QUEUE_CONNECTION=sync` in `.env` to process jobs inline without a worker.

### 8. Set up the scheduler

Add to your server crontab:

```
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```

---

## Environment Variables

| Variable | Description |
|---|---|
| `APP_NAME` | Application name shown in UI — currently `"Merqio POS"` |
| `APP_URL` | Full URL including scheme, e.g. `https://merqiopos.com` |
| `APP_TIMEZONE` | Server timezone — set to `Africa/Nairobi` for correct scheduler times |
| `DB_DATABASE` | MySQL database name |
| `DB_USERNAME` | MySQL username |
| `DB_PASSWORD` | MySQL password |
| `QUEUE_CONNECTION` | `sync` for dev, `database` or `redis` for production |
| `MAIL_MAILER` | Mail driver (`smtp`, `sendmail`) |
| `MAIL_HOST` | SMTP host |
| `MAIL_USERNAME` | SMTP username |
| `MAIL_PASSWORD` | SMTP password |
| `MAIL_FROM_ADDRESS` | From address for outgoing email |
| `MPESA_CONSUMER_KEY` | Platform Daraja consumer key (subscription billing only — not per-business sale payments) |
| `MPESA_CONSUMER_SECRET` | Platform Daraja consumer secret |
| `MPESA_SHORTCODE` | Platform M-Pesa shortcode |
| `MPESA_PASSKEY` | Platform M-Pesa passkey |
| `MPESA_ENVIRONMENT` | `sandbox` or `production` |
| `MPESA_CALLBACK_URL` | Full URL to `api/mpesa/callback` |
| `PAYSTACK_SECRET_KEY` | Paystack secret key (platform subscription billing) |
| `PAYSTACK_PUBLIC_KEY` | Paystack public key (frontend checkout) |

> **Production checklist:** set `SESSION_SECURE_COOKIE=true` on HTTPS, `APP_DEBUG=false`, and `LOG_LEVEL=error`.

---

## Scheduled Commands

| Command | Schedule | Description |
|---|---|---|
| `payroll:auto-run` | Daily 03:00 UTC (06:00 Nairobi) | Auto-create, calculate, and optionally pay payroll for businesses whose pay-day matches today |
| `sme:subscription-warnings` | Daily 05:00 AM | Notify businesses whose trial/subscription expires in 3 days |
| `sme:low-stock-alerts` | Daily 04:00 AM | Email owners about products at or below reorder level |
| `sme:payment-reminders` | Monday 05:00 AM | Weekly digest of customers with outstanding balances |
| `sme:process-recurring-invoices` | Daily 06:00 AM | Auto-generate sales from recurring invoices that are due |

All times are in `APP_TIMEZONE`. Set to `Africa/Nairobi` to ensure commands fire at the correct local time. Check `routes/console.php` / `app/Console/Kernel.php` for the authoritative, current list — commands get added faster than this table.

### Auto-payroll command

The `payroll:auto-run` command iterates every business that has:
- Payroll enabled (`payroll_settings.enabled = true`)
- Auto-payroll enabled (`payroll_settings.auto_payroll = true`)
- `pay_day` equal to today's day of month

For each qualifying business it:
1. Skips if a period already exists for the current month range
2. Skips if the business has no active staff profiles
3. Creates the payroll period and calculates all items
4. If mode is `auto_approve` or `fully_auto` — approves the period
5. If mode is `fully_auto` — marks all items paid and posts the salary expense

Run manually:

```bash
php artisan payroll:auto-run
php artisan payroll:auto-run --dry-run   # logs what would happen without writing
```

---

## Documentation

- **[DOCUMENTATION.md](DOCUMENTATION.md)** — technical/architecture reference
- **[docs/USER_GUIDE.md](docs/USER_GUIDE.md)** — customer-facing guide

Both were significantly out of date relative to the live app as of this pass (wrong product name, an incomplete/incorrect user-roles table, a documented multi-tenancy pattern that was itself a known bug shape, and whole feature areas — bookings, marketing, HR self-service, the POS kiosk redesign, the home-menu launcher — missing entirely). This README and DOCUMENTATION.md have been corrected; USER_GUIDE.md has had its most load-bearing inaccuracies fixed (branding, roles, a pointer to the newer features) but has not been rewritten feature-by-feature against all 80+ controllers — treat specific step-by-step instructions in it with more skepticism than this file.

---

## Payment Architecture

```
Platform Subscriptions (Paystack)
  Owner pays via Paystack hosted checkout (card / M-Pesa) → developer's Paystack account

Per-Business M-Pesa Sale Payments (Daraja STK Push + C2B)
  Customer phone receives STK Push or pays via Paybill/Till → business's M-Pesa account
  Auto-detection: /api/mpesa/callback (STK) + /api/mpesa/c2b/confirm (C2B)
  Both auto-complete the sale and redirect cashier to invoice on payment confirmation

Per-Business Card Payments (Pesapal v3)
  Customer pays via Visa/Mastercard/Airtel → business's Pesapal account
  Verified via Pesapal IPN callback + server-side status check

Payroll B2C Disbursement (Daraja B2C)
  Payroll item marked paid → SendMpesaPayment job → employee's M-Pesa phone
  Uses business's Daraja credentials; dry-run in non-production environments

Customer Portal Invoice Payments (Daraja STK Push)
  Customer pays an outstanding invoice from their self-service portal → business's M-Pesa account
```

---

## M-Pesa Setup

There are two independent M-Pesa integrations:

**Platform billing** — processes subscription payments. Configured via `.env` variables. All subscription STK pushes go to the platform shortcode.

**Per-business sale payments** — each business enters their own Daraja credentials in **Settings → M-Pesa**. Customer payments go directly to the business shortcode. A diagnostic gotcha to know about: any query that selects only specific `Business` columns (e.g. `Business::select('id','name')->get()`) and then calls `hasMpesaConfigured()` will give a false negative, since that method reads columns that weren't selected.

**Payroll B2C disbursement** — when a payroll item is marked paid and the employee has a phone number registered, a `SendMpesaPayment` job is dispatched to the queue. This uses the Daraja B2C API. In non-production environments the job logs the attempt without calling Safaricom.

To configure per-business payments:

1. Register at the [Safaricom Developer Portal](https://developer.safaricom.co.ke)
2. Create a Daraja app; obtain Consumer Key, Consumer Secret, and Passkey
3. Enter credentials in **Settings → M-Pesa**
4. Set the callback URL to: `https://yourdomain.com/api/mpesa/callback`
5. Optionally register C2B URLs (**Settings → M-Pesa → Register C2B URLs**) to auto-detect Paybill/Till payments

M-Pesa credentials are encrypted at rest using Laravel's `encrypt()` / `decrypt()` helpers.

---

## Pesapal Setup

For card payment acceptance (per-business):

1. Register at [pesapal.com](https://www.pesapal.com) and create an account
2. Enter Consumer Key and Consumer Secret in **Settings → Pesapal (Card)**
3. Set environment to `sandbox` for testing or `production` for live
4. Click **Register IPN** so Pesapal knows where to send payment notifications

IPN URL: `https://yourdomain.com/api/pesapal/ipn`

---

## Paystack Setup

For platform subscription billing:

Add to `.env`:
```
PAYSTACK_SECRET_KEY=sk_live_xxx
PAYSTACK_PUBLIC_KEY=pk_live_xxx
```

Webhook URL to register in Paystack dashboard: `https://yourdomain.com/api/paystack/webhook`

---

## SMS Setup

1. Register with [Africa's Talking](https://africastalking.com) or [Twilio](https://twilio.com)
2. Enter API credentials in **Settings → SMS Notifications**
3. Use **Send Test SMS** to verify

| Provider | API Key field | Username field | Sender ID field |
|---|---|---|---|
| Africa's Talking | API Key | Username | Sender ID (optional) |
| Twilio | Auth Token | Account SID | From number |

---

## VAT Setup

1. Go to **Settings → VAT**
2. Toggle "VAT Registered"
3. Enter your KRA VAT registration number
4. Set your default VAT rate (16% Kenya standard, or 0 for exempt businesses)
5. Override the VAT rate on individual products where needed (null inherits the business default, 0 exempts the product)

VAT amount is tracked on every invoice and surfaced in reports, including a dedicated VAT Return report.

---

## Barcode Scanning

Two input modes are supported on the POS and Add Inventory pages:

- **USB / Bluetooth scanner** — scanners behave as a keyboard; scanned codes are typed into the barcode input and submitted on Enter
- **Camera** — uses the browser's `BarcodeDetector` API; supported in **Chrome and Edge only**; not available in Safari or Firefox

Supported formats: EAN-13, EAN-8, UPC-A, UPC-E, Code 128, Code 39, QR Code.

When a barcode matches a product already in the cart, its quantity is incremented rather than adding a duplicate row.

---

## Two-Factor Authentication

TOTP-based 2FA is available to all users via **Settings → Two-Factor Authentication**. Users scan a QR code with any authenticator app (Google Authenticator, Authy, etc.).

Sensitive write actions (business profile, team changes, API tokens, M-Pesa settings) require **sudo mode** — users must re-verify their TOTP code within the last 15 minutes.

---

## Bulk Stock Import

Supported formats: CSV, Excel (.xlsx / .xls), ODS. Maximum file size: 5 MB.

**Required columns (first row = header):**

| Column | Required | Notes |
|---|---|---|
| `name` | Yes | Product name |
| `sku` | No | Auto-generated if blank |
| `barcode` | No | |
| `price` | Yes | Selling price, numeric |
| `cost_price` | No | |
| `stock_qty` | No | Defaults to 0 |
| `reorder_level` | No | Defaults to 0 |
| `category` | No | Must match an existing category name |

Download the template from the **Inventory → Import** page. The import runs as a background job; refresh the page after a few seconds to see results. Rows with errors are skipped and reported in the job output.

---

## REST API

Available on the Enterprise organization plan. Authenticate with a Bearer token:

```
Authorization: Bearer YOUR_TOKEN
```

Tokens are generated in **Settings → API**. The raw token is shown once at generation time and stored as a SHA-256 hash.

**Base URL**: `https://yourdomain.com/api/v1`

| Method | Endpoint | Description |
|---|---|---|
| GET | `/products` | List products |
| GET | `/products/{id}` | Get product |
| POST | `/products` | Create product |
| PUT | `/products/{id}` | Update product |
| GET | `/sales` | List sales |
| GET | `/sales/summary` | Monthly summary |
| GET | `/sales/{id}` | Get sale |
| GET | `/customers` | List customers |
| GET | `/customers/{id}` | Get customer |
| POST | `/customers` | Create customer |
| PUT | `/customers/{id}` | Update customer |

See `app/Http/Controllers/Api/` for the current, complete endpoint list — this table only covers the original core resources.

---

## Super Admin

Platform-level admin panel at `/admin`, styled and structured independently from the main app (its own `admin.css` mobile system, its own layout). Super admins can view all organizations, suspend or activate accounts, extend trials, and grant subscriptions manually.

Create the first super admin:

```bash
php artisan admin:make
# or promote an existing user by email:
php artisan admin:make admin@example.com
```

---

## Running Tests

```bash
php artisan test
```

Feature tests are in `tests/Feature/` and cover organization subscriptions, payroll settings, staff management, and more.

---

## Support

- Email: support@merqiopos.com
- WhatsApp: +254 718 215 432
- Hours: Monday – Friday, 8 am – 6 pm EAT

---

## License

Proprietary. All rights reserved. © 2026 Merqio POS.
