# Merqio POS — Technical Documentation

> **Naming/scale note:** the product's public name is "Merqio POS" and, as of 2026-09-04, its official domain is merqiopos.com (smemanagers.com still serves the identical app/database in parallel — see the main [README.md](README.md) for why). A text-level rebrand pass (2026-09-04) removed "SME Manager"/"smemanagers.com" from class/config comments, transactional emails, and support addresses app-wide. Two things stay unchanged on purpose — the production **database name** (`your_database`) and the **project/server directory names** (`sme_manager`, `~/smemanagers`, `~/smemanagers.com`) — see the README's own naming note for why renaming those is a much bigger, riskier job than a text find-and-replace. Separately, this document was originally written when the app had ~35 migrations, ~19 models, and ~13 controllers; it now has **152 migrations, 110+ models, and 83+ controllers**. Sections below have been corrected where they stated something actively wrong (the tenant-scoping pattern in particular — see §1), but the Database Schema section (§3) still only documents the original core tables in full detail; treat `database/migrations/` as the source of truth for anything newer (bookings, marketing/loyalty, HR self-service, purchasing documents, finance documents, the POS kiosk redesign, the home-menu launcher, etc.).

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Directory Structure](#2-directory-structure)
3. [Database Schema](#3-database-schema)
4. [Authentication & Security](#4-authentication--security)
5. [Subscription & Feature Gating](#5-subscription--feature-gating)
6. [M-Pesa Integration](#6-m-pesa-integration)
7. [REST API](#7-rest-api)
8. [Scheduled Jobs](#8-scheduled-jobs)
9. [Key Services](#9-key-services)
10. [Adding a New Feature](#10-adding-a-new-feature)

---

## 1. Architecture Overview

### Multi-Tenant Design

Merqio POS uses a **shared database, shared schema** multi-tenancy model. Every tenant is a `Business` record. All business-owned resources (products, sales, customers, expenses, etc.) carry a `business_id` foreign key that ties them to their owner.

There is no row-level security at the database level. Instead, tenancy is enforced at the application layer in two ways:

1. **`BelongsToBusiness` trait** — applied to every tenant-scoped model. Provides a `business()` relationship and a `scopeForBusiness()` query scope.
2. **Controller-level ownership checks** — every controller retrieves the **currently active** business from `Auth::user()->currentBusiness()->id` and passes it to all queries and service calls.

A user who belongs to Business A can never see Business B's data because every query is filtered with `.forBusiness($businessId)` before execution — **provided `$businessId` was actually resolved via `currentBusiness()`**. This document previously told readers to use `Auth::user()->business_id` here, which is **wrong** and was itself the exact bug shape behind several real production incidents: `business_id` is a legacy/stale column that does not track which store a multi-store user (owner or manager) has switched to via the store switcher, so code using it silently operates against the wrong store, or — in validation `Rule::unique(...)->where('business_id', ...)` checks specifically — causes false "already in use" errors because a record's own self-exclusion (`->ignore()`) is compared against the wrong business. Always resolve the active business via `Auth::user()->currentBusiness()->id` (or `$business = Auth::user()->currentBusiness()` when you need the model itself); never read `->business_id` directly in new code, and fix it on sight if you find it in old code.

### BelongsToBusiness Trait

**Location**: `app/Traits/BelongsToBusiness.php`

Applied to most tenant-scoped models. The original short list here (`Product`, `Sale`, `Customer`, `Expense`, `ExpenseCategory`, `Category`, `Quote`, `Supplier`, `PurchaseOrder`, `RecurringInvoice`, `StockAdjustment`, `MpesaTransaction`, `AuditLog`, `Subscription`) is no longer complete — the app now has 100+ models across bookings, marketing/loyalty, HR, and finance/purchasing (see [README.md](README.md#features)), most of which follow the same trait pattern. Grep the model directory for `use BelongsToBusiness` for the current, authoritative list rather than trusting an enumerated list here.

```php
public function scopeForBusiness($query, int $businessId)
{
    return $query->where('business_id', $businessId);
}
```

Usage in controllers:

```php
$products = Product::forBusiness($businessId)->active()->paginate(20);
```

### Plan Feature Gating

Feature access is driven by a single config file (`config/plans.php`) and checked via `Business::hasFeature(string $feature)`. This method reads the business's current `subscription_plan`, looks up the feature flag in the config, and returns a boolean.

**Updated 2026-09-12** — after the 3-tier pricing restructure, `hasFeature()`/`planLimit()` simply resolve the business's actual plan config with no trial-upsell branching. (Previously, an active trial granted Business-plan feature access regardless of the business's real plan; that fallback was removed since Starter is now the only entry-level paid tier and already includes M-Pesa + customers by default — see `config/plans.php`'s own header comment.)

Feature gates are applied at the **route level** using the `feature` middleware alias, which maps to `CheckPlanFeature`. They are also checked programmatically in controllers for resource limits (e.g., max products per plan).

---

## 2. Directory Structure

The counts below are real as of this pass (`ls app/Http/Controllers`, `ls app/Models`, `ls database/migrations` — re-run those yourself if this drifts further). The tree keeps the original illustrative sample of each directory rather than listing all 83 controllers / 110+ models; it is not exhaustive.

```
sme_manager/                        # directory name predates the "Merqio POS" rename
├── app/
│   ├── Console/
│   │   └── Commands/               # Artisan commands (scheduled jobs) — 5+ shown below,
│   │       │                       # more have been added alongside newer features
│   │       ├── MakeSuperAdmin.php
│   │       ├── ProcessRecurringInvoices.php
│   │       ├── SendLowStockAlerts.php
│   │       ├── SendPaymentReminders.php
│   │       └── SendSubscriptionWarnings.php
│   ├── Http/
│   │   ├── Controllers/            # 83 controllers — a representative sample:
│   │   │   ├── Admin/              # Super admin panel controllers
│   │   │   ├── Api/                # REST API controllers (Enterprise)
│   │   │   ├── Auth/               # Login, register, password reset, 2FA
│   │   │   ├── Shop/               # Public online-shop storefront (per business)
│   │   │   ├── HomeController.php  # Post-login tile launcher (route name 'menu')
│   │   │   ├── DashboardController.php
│   │   │   ├── SalesController.php         # POS + sales history
│   │   │   ├── InventoryController.php
│   │   │   ├── CustomerController.php
│   │   │   ├── ExpenseController.php
│   │   │   ├── SettingsController.php
│   │   │   ├── ReportController.php
│   │   │   ├── AppointmentController.php   # Bookings module
│   │   │   ├── ServiceController.php       # Bookings module
│   │   │   ├── TableController.php         # Restaurant table/floor plan
│   │   │   ├── LoyaltyController.php       # Marketing module
│   │   │   ├── CampaignController.php      # Marketing module
│   │   │   ├── PayrollController.php       # HR module
│   │   │   ├── LeaveController.php         # HR self-service
│   │   │   ├── AttendanceController.php    # HR self-service
│   │   │   └── ... (see `ls app/Http/Controllers` for the rest)
│   │   └── Middleware/
│   │       ├── AdminMiddleware.php         # is_super_admin check
│   │       ├── ApiTokenAuth.php            # Bearer token → Business lookup
│   │       ├── Authenticate.php            # Standard Laravel auth
│   │       ├── CheckPlanFeature.php        # Route-level feature gate
│   │       ├── CheckSubscription.php       # Active trial/subscription gate
│   │       ├── RequireRole.php             # Route-level role gate (role:owner,manager,...)
│   │       ├── RequireTwoFactor.php        # Redirect to 2FA challenge if needed
│   │       └── RequireRecentTwoFactor.php  # Sudo mode (15-minute window)
│   ├── Jobs/                       # Queued jobs
│   ├── Mail/                       # Mailable classes (alerts, confirmations)
│   ├── Models/                     # 110+ models — core ones listed in README.md;
│   │   │                           # see `ls app/Models` for the full, current list
│   │   ├── AuditLog.php
│   │   ├── Business.php            # Central tenant model
│   │   ├── Category.php
│   │   ├── Customer.php
│   │   ├── Product.php
│   │   ├── Sale.php / SaleItem.php
│   │   ├── Supplier.php / PurchaseOrder.php
│   │   └── User.php
│   ├── Services/
│   │   ├── MpesaService.php        # Daraja API wrapper
│   │   ├── ReportService.php       # Dashboard & report data aggregation
│   │   ├── SaleService.php         # Sale creation & cancellation logic
│   │   ├── SmsService.php          # Africa's Talking / Twilio SMS
│   │   └── SubscriptionService.php # Subscription activation after payment
│   └── Traits/
│       ├── BelongsToBusiness.php
│       ├── LogsActivity.php        # Generic audit-log trait — auto-logs create/
│       │                           # update/delete diffs; opt-in per model
│       └── LogsBusinessSettings.php # Curated, redacting audit trait specifically
│                                    # for Business (too write-heavy for LogsActivity)
├── bootstrap/
│   └── app.php                     # Middleware registration, scheduler, routing
├── config/
│   ├── mpesa.php                   # Platform M-Pesa defaults (reads from .env)
│   └── plans.php                   # Single source of truth for plan features/limits
├── database/
│   └── migrations/                 # 152 migration files
├── resources/
│   └── views/                      # Blade templates
├── routes/
│   ├── api.php                     # M-Pesa callback + REST API v1 routes
│   ├── web.php                     # All web routes (guest, auth, subscribed, admin) —
│   │                                # require()'s the feature_*.php files below
│   └── features_*.php              # business / finance / inventory / marketing / ops /
│                                    # payroll / tax / ux — route groups split out of
│                                    # web.php by feature area as it grew
└── tests/
```

---

## 3. Database Schema

> This section only documents the original core tables in full detail. The schema has since grown to 152 migrations — anything not listed here (bookings, marketing/loyalty, HR self-service, purchasing/finance documents, and more — see [README.md](README.md#features)) should be read from `database/migrations/` directly.

### `users`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `business_id` | bigint FK | Nullable (super admins have no business). **Legacy/stale** — do not use this to determine a multi-store user's active store; use `Auth::user()->currentBusiness()->id` (see §1) |
| `organization_id` | bigint FK | Nullable — set for owners/overall managers |
| `name` | string | |
| `email` | string unique | |
| `password` | string | Bcrypt hashed |
| `role` | enum | `owner`, `overall_manager`, `manager`, `cashier`, `staff` (added across two later migrations — `overall_manager` and `staff` were not part of the original enum) |
| `is_active` | boolean | Inactive users cannot log in |
| `is_super_admin` | boolean | Platform admin access — independent of `role`; a super admin can also hold a normal store role |
| `google2fa_secret` | text | Encrypted TOTP secret |
| `two_factor_enabled` | boolean | |
| `two_factor_confirmed_at` | timestamp | Set when user first confirms 2FA |
| `last_login_at` | timestamp | |

Per-business role is actually tracked separately on the `business_user` pivot table (`role` enum `owner`, `manager`, `cashier`, `staff` — no `overall_manager`, since that's an org-wide role that doesn't attach per-store the same way), for users attached to more than one store.

### `businesses`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `name` | string | |
| `email` | string unique | |
| `phone` | string | |
| `address`, `city`, `industry` | string | Optional profile fields |
| `logo` | string | Storage path |
| `kra_pin` | string | Kenya Revenue Authority PIN (optional) |
| `subscription_plan` | enum | `starter`, `business`, `enterprise` (store-level — see `config/plans.php`); this enum never included a `free` value at the business/store level — only the separate `organizations.subscription_plan` column (org-level; this document doesn't have a dedicated section for that table yet — see `database/migrations/2026_06_10_000001_create_organizations_table.php` and `..._add_free_plan_to_organizations_enum.php`) was ever widened to permit a legacy `free` |
| `status` | enum | `trial`, `active`, `suspended` |
| `trial_ends_at` | timestamp | |
| `mpesa_shortcode` | string | Per-business M-Pesa shortcode |
| `mpesa_consumer_key` | text | Encrypted |
| `mpesa_consumer_secret` | text | Encrypted |
| `mpesa_passkey` | text | Encrypted |
| `mpesa_till_number` | string | Optional (Buy Goods vs Pay Bill) |
| `mpesa_environment` | string | `sandbox` or `production` |
| `sms_provider` | string | `africas_talking` or `twilio` |
| `sms_api_key` | string | |
| `sms_username` | string | |
| `sms_sender_id` | string | |
| `api_token` | string | SHA-256 hash of the raw token |
| `payment_terms` | string | Default payment terms for invoices |

### `subscriptions`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `business_id` | bigint FK | |
| `plan` | enum | `starter`, `business`, `enterprise` |
| `amount` | decimal | Amount paid in KSh |
| `start_date` | date | |
| `end_date` | date | Access valid until this date |
| `status` | enum | `active`, `expired`, `cancelled` |
| `payment_reference` | string | M-Pesa receipt number |

### `products`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `business_id` | bigint FK | |
| `category_id` | bigint FK | Nullable |
| `name` | string | |
| `sku` | string | Nullable; unique per business |
| `barcode` | string | Optional; scanned via camera or handheld |
| `description` | text | |
| `buying_price` | decimal(12,2) | Cost price |
| `selling_price` | decimal(12,2) | |
| `stock_qty` | integer | Current stock level |
| `reorder_level` | integer | Triggers low-stock alert |
| `unit` | string | e.g. `piece`, `kg`, `litre` |
| `status` | enum | `active`, `inactive` |
| `low_stock_alert_sent_at` | timestamp | Prevents duplicate alerts on the same day |
| `deleted_at` | timestamp | Soft delete |

Columns added later, not reflected above: `image`, `brand`, `barcode_symbology`, `is_featured`, `hide_in_pos`, `hide_in_shop`, `buy_unit`, `units_per_buy_unit` (buy-unit-to-sell-unit conversion — stock is always tracked in the sell unit), plus `parent_id` on `categories` for one level of sub-categories. See the `product_images` and `product_suppliers` tables for the gallery and per-supplier pricing this unlocked.

### `categories`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `business_id` | bigint FK | |
| `name` | string | Unique per business |
| `description` | string | |

### `sales`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `business_id` | bigint FK | |
| `customer_id` | bigint FK | Nullable (walk-in sales) |
| `user_id` | bigint FK | Staff member who created the sale |
| `recurring_invoice_id` | bigint | Nullable; set when auto-generated |
| `invoice_number` | string unique | Auto-generated (e.g. `INV-0001`) |
| `subtotal` | decimal(12,2) | Before discount |
| `discount_amount` | decimal(12,2) | Order-level discount |
| `tax_amount` | decimal(12,2) | Currently reserved (defaults to 0) |
| `total_amount` | decimal(12,2) | Final amount due |
| `paid_amount` | decimal(12,2) | Amount received so far |
| `balance_due` | decimal(12,2) | `total_amount - paid_amount` |
| `payment_method` | enum | `cash`, `mpesa`, `bank_transfer`, `credit` |
| `mpesa_reference` | string | M-Pesa receipt number |
| `payment_status` | enum | `paid`, `partial`, `unpaid` |
| `sale_status` | enum | `completed`, `cancelled` |
| `notes` | text | |
| `deleted_at` | timestamp | Soft delete |

### `sale_items`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `sale_id` | bigint FK | |
| `product_id` | bigint FK | Nullable (product may be deleted) |
| `product_name` | string | Snapshot of name at time of sale |
| `unit_price` | decimal(12,2) | Snapshot of price at time of sale |
| `buying_price` | decimal(12,2) | Snapshot for profit calculation |
| `quantity` | integer | |
| `discount` | decimal(5,2) | Line-level percentage discount |
| `subtotal` | decimal(12,2) | After line discount |

### `customers`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `business_id` | bigint FK | |
| `name` | string | |
| `phone` | string | |
| `email` | string | |
| `address` | string | |
| `balance_owed` | decimal(12,2) | Running credit balance |
| `notes` | text | |
| `payment_reminder_sent_at` | timestamp | Prevents duplicate weekly digests |
| `deleted_at` | timestamp | Soft delete |

### `expenses`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `business_id` | bigint FK | |
| `expense_category_id` | bigint FK | Nullable |
| `user_id` | bigint FK | Who recorded it |
| `description` | string | |
| `amount` | decimal(12,2) | |
| `expense_date` | date | |
| `payment_method` | string | |
| `reference` | string | Receipt or reference number |
| `notes` | text | |
| `deleted_at` | timestamp | Soft delete |

### `expense_categories`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `business_id` | bigint FK | |
| `name` | string | Unique per business |

### `quotes` / `quote_items`

**quotes**

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `business_id` | bigint FK | |
| `customer_id` | bigint FK | Nullable |
| `user_id` | bigint FK | |
| `quote_number` | string | Unique per business |
| `quote_date` | date | |
| `valid_until` | date | Expiry date |
| `subtotal`, `discount_amount`, `tax_rate`, `tax_amount`, `total` | decimal | |
| `status` | enum | `draft`, `sent`, `accepted`, `rejected`, `expired`, `converted` |
| `converted_to_sale_id` | bigint | Set when quote is converted to a sale |
| `notes`, `terms` | text | |
| `deleted_at` | timestamp | Soft delete |

**quote_items**: quote_id, product_id (nullable), product_name, unit_price, quantity, subtotal.

### `suppliers`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `business_id` | bigint FK | |
| `name` | string | |
| `email`, `phone`, `address` | string | |
| `contact_person` | string | |
| `account_number` | string | Supplier's account reference |
| `notes` | text | |
| `is_active` | boolean | |
| `deleted_at` | timestamp | Soft delete |

### `purchase_orders` / `purchase_order_items`

**purchase_orders**

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `business_id` | bigint FK | |
| `supplier_id` | bigint FK | Nullable |
| `user_id` | bigint FK | |
| `po_number` | string | |
| `order_date`, `expected_date`, `received_date` | date | |
| `subtotal`, `tax_amount`, `total`, `amount_paid` | decimal | |
| `status` | enum | `draft`, `ordered`, `partially_received`, `received`, `cancelled` |
| `payment_status` | enum | `unpaid`, `partial`, `paid` |
| `deleted_at` | timestamp | Soft delete |

**purchase_order_items**: purchase_order_id, product_id (nullable), product_name, quantity_ordered, quantity_received, unit_cost, subtotal.

When stock is received (`PurchaseOrderController::receive`), `products.stock_qty` is incremented by the received quantity and `purchase_order_items.quantity_received` is updated.

### `recurring_invoices` / `recurring_invoice_items`

**recurring_invoices**

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `business_id` | bigint FK | |
| `customer_id` | bigint FK | Nullable |
| `user_id` | bigint FK | Owner who created it |
| `title` | string | Display name |
| `frequency` | enum | `weekly`, `monthly`, `quarterly`, `yearly` |
| `next_run_date` | date | Date the scheduler next fires this invoice |
| `last_run_date` | date | |
| `end_date` | date | Nullable; invoice stops after this date |
| `is_active` | boolean | |
| `run_count` | integer | Number of times it has been executed |
| `subtotal`, `discount_amount`, `tax_rate`, `tax_amount`, `total` | decimal | |
| `deleted_at` | timestamp | Soft delete |

**recurring_invoice_items**: recurring_invoice_id, product_id (nullable), product_name, unit_price, quantity, subtotal.

### `mpesa_transactions`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `sale_id` | bigint FK | Nullable (null for subscription payments) |
| `subscription_id` | bigint | Nullable; set after successful subscription activation |
| `type` | enum | `sale`, `subscription` |
| `phone` | string | Normalised to `254XXXXXXXXX` |
| `amount` | decimal(10,2) | |
| `checkout_id` | string unique | `CheckoutRequestID` from Daraja |
| `api_ref` | string | For sales: MerchantRequestID. For subscriptions: `plan:business_id` |
| `mpesa_receipt` | string | `MpesaReceiptNumber` from callback |
| `status` | enum | `PENDING`, `COMPLETE`, `FAILED` |
| `payload` | json | Full Daraja callback body |

### `stock_adjustments`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `business_id` | bigint FK | |
| `product_id` | bigint FK | |
| `user_id` | bigint FK | |
| `type` | enum | `add`, `remove`, `set` |
| `quantity` | integer | Adjustment quantity |
| `reason` | string | Free-text reason |
| `notes` | text | |

### `audit_logs`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `business_id` | bigint | Nullable (system events have no business) |
| `user_id` | bigint | Nullable |
| `event` | string | e.g. `sale.created`, `sale.cancelled`, `subscription.paid` |
| `subject_type` | string | Fully qualified model class |
| `subject_id` | bigint | |
| `metadata` | json | Event-specific context (invoice number, amount, etc.) |
| `ip_address` | string | |
| `created_at` | timestamp | No `updated_at` — append-only log |

---

## 4. Authentication & Security

### Login Flow

1. User submits email and password to `POST /login`.
2. `LoginController` calls `Auth::attempt()`. On failure, the attempt is rate-limited by Laravel's throttle middleware.
3. On success, if the user has `two_factor_enabled = true`, they are redirected to `GET /2fa/challenge` before gaining access to the application.
4. The `2fa` middleware alias (`RequireTwoFactor`) enforces this redirect — any route in the `auth + 2fa` group checks `session('2fa_verified')` and bounces unauthenticated 2FA sessions back to the challenge page.

### Two-Factor Authentication (TOTP)

- **Package**: `pragmarx/google2fa-laravel`
- **Setup**: Users visit **Settings → Two-Factor Authentication**, scan the displayed QR code, and confirm by entering their current TOTP code. The `google2fa_secret` is stored encrypted in the `users` table.
- **Verification**: On login, `TwoFactorController::verify` validates the submitted code against the decrypted secret. On success it sets `session('2fa_verified', true)` and `session('2fa_last_verified', now()->timestamp)`.
- **Disable**: Owners can disable 2FA from settings. This is a sudo-protected action.

### Sudo Mode

Sensitive write operations are protected by a **15-minute re-verification window**. The `sudo` middleware alias maps to `RequireRecentTwoFactor`.

When triggered, it checks `session('2fa_last_verified')`. If the timestamp is more than 15 minutes ago (or missing), the user is redirected to `/2fa/challenge?intent=sudo`. After successful re-verification, the user is forwarded to their originally intended URL stored in `session('2fa_intended')`.

Routes protected by sudo: business profile update, team member changes, M-Pesa settings update, SMS settings update, API token generation/revocation.

Sudo mode is only active if the user has 2FA enabled. If 2FA is not set up, the route is accessible without re-verification.

### Subscription Gate

The `subscribed` middleware alias (`CheckSubscription`) is applied to all business routes. It allows access if:

- `$business->isOnTrial()` returns true (status is `trial` and `trial_ends_at` is in the future), OR
- There is at least one `Subscription` record with `status = active` and `end_date >= today`.

If neither condition is met, the user is redirected to `/subscription/required`. The subscription settings page and the M-Pesa subscription payment endpoint are intentionally excluded from this gate so expired users can renew.

### CSRF Exception

The Daraja callback endpoint (`api/mpesa/callback`) is excluded from CSRF verification in `bootstrap/app.php` because it is called by Safaricom's servers, not a browser session.

### Password Reset

Standard Laravel password reset flow: email link sent via `ForgotPasswordController`, token validated in `ResetPasswordController`. Uses the `password_reset_tokens` table.

---

## 5. Subscription & Feature Gating

### Plans Configuration

**Location**: `config/plans.php`

Each plan is a keyed array with three sections:

```php
'business' => [
    'name'   => 'Business',
    'price'  => 2499,          // KSh per month
    'color'  => '#4f46e5',     // UI badge colour
    'limits' => [
        'products' => PHP_INT_MAX,
        'users'    => 5,
    ],
    'features' => [
        'customers'          => true,
        'expenses'           => true,
        'mpesa_sales'        => true,
        'pdf_invoices'       => true,
        'reports_advanced'   => true,
        'team_management'    => true,
        'quotes'             => true,
        'suppliers'          => true,
        'recurring_invoices' => true,
        'sms_notifications'  => true,
        'barcode'            => true,
        'data_export'        => true,
        'api_access'         => false, // Enterprise only
        // ...
    ],
],
```

This file is the **single source of truth**. Adding a new feature flag here is all that is needed for the gating logic to pick it up.

### Business Model Helpers

**`Business::hasFeature(string $feature): bool`**

Reads the plan config for the business's current `subscription_plan`. Returns `true` if the feature key is `true` in the config. During a live trial, it reads the `business` plan config instead.

**`Business::planLimit(string $resource): int`**

Returns the numeric limit for `products` or `users`. Used in controllers to enforce product/user caps.

**`Business::upgradePlan(): ?string`**

Returns the next plan key (`starter → business → enterprise → null`). Used by `CheckPlanFeature` to include the correct upgrade target in the redirect flash message.

### CheckPlanFeature Middleware

**Location**: `app/Http/Middleware/CheckPlanFeature.php`

Applied to routes as `->middleware('feature:feature_key')`. If `$business->hasFeature($feature)` returns false, the user is redirected to `settings.subscription` with a warning flash message that names the required plan.

Example route definition:

```php
Route::prefix('customers')->middleware('feature:customers')->group(function () {
    // ...
});
```

### Programmatic Feature Checks in Controllers

For resource limit enforcement (not route-level gating), controllers call `hasFeature()` or `planLimit()` directly:

```php
$limit = $business->planLimit('products');
if ($limit !== PHP_INT_MAX && Product::forBusiness($businessId)->count() >= $limit) {
    return back()->with('error', 'Product limit reached for your plan.');
}
```

---

## 6. M-Pesa Integration

### Two Independent Integrations

**Platform billing** — when a business pays for a subscription, the STK push goes to the platform's own Daraja shortcode. Credentials are read from `.env` / `config/mpesa.php`. `MpesaService` is instantiated with no arguments: `new MpesaService()`.

**Per-business sale payments** — when a business's customer pays for a sale, the STK push goes to the business owner's own Daraja shortcode. Credentials are read from the `businesses` table (encrypted). `MpesaService` is instantiated with the business's credential array: `new MpesaService($business->mpesaCredentials())`.

### STK Push Flow (Sale Payment)

```
1. Frontend calls POST /api/mpesa/initiate with { sale_id, phone }
2. MpesaController::initiate() validates the request and checks:
   - sale belongs to current business
   - sale is not already paid
   - business has M-Pesa credentials configured
3. MpesaService::stkPush() is called:
   a. getAccessToken() — fetches OAuth token from Daraja, cached 55 minutes per consumer key
   b. Builds STK Push payload with timestamp-based password
   c. Determines transaction type: CustomerBuyGoodsOnline (till) or CustomerPayBillOnline (shortcode)
   d. POSTs to /mpesa/stkpush/v1/processrequest
4. A MpesaTransaction record is created with status=PENDING, checkout_id=CheckoutRequestID
5. Frontend receives { success: true, transaction_id }
6. Frontend polls GET /api/mpesa/status?sale_id={id} every few seconds
7. Daraja calls POST /api/mpesa/callback (CSRF-exempt)
8. MpesaController::callback():
   a. Locates transaction by checkout_id
   b. Checks idempotency — skips if already COMPLETE or FAILED
   c. ResultCode === 0 → success:
      - Updates transaction: status=COMPLETE, mpesa_receipt
      - Calls completeSalePayment() → updates sale.paid_amount, balance_due, payment_status
      - Decrements customer.balance_owed if applicable
   d. ResultCode !== 0 → sets status=FAILED
9. Frontend poll sees COMPLETE → shows success UI
```

### STK Push Flow (Subscription Payment)

Identical to the sale flow with two differences:

- `MpesaService` uses platform credentials (no arguments to constructor).
- `api_ref` is set to `"plan:business_id"` (e.g. `"business:42"`).
- On callback success, `SubscriptionService::activateFromPayment()` is called instead of `completeSalePayment()`. It parses `api_ref`, creates a `Subscription` record (30-day validity), updates `Business.subscription_plan` and `Business.status`, and queues a `SubscriptionConfirmed` email to the owner.

### Phone Number Normalisation

`MpesaService::formatPhone()` normalises any Kenyan number format to `254XXXXXXXXX` (required by Daraja):

| Input | Output |
|---|---|
| `0712345678` | `254712345678` |
| `+254712345678` | `254712345678` |
| `712345678` | `254712345678` |
| `254712345678` | `254712345678` |

### Access Token Caching

Tokens are cached using `Cache::remember()` with the key `mpesa_token_{md5(consumerKey)}` for 55 minutes (Daraja tokens expire after 60 minutes). This means:

- The platform token and each business's token are cached independently.
- A failed token fetch throws an exception that surfaces to the user.

### Credential Encryption

The three sensitive M-Pesa fields (`mpesa_consumer_key`, `mpesa_consumer_secret`, `mpesa_passkey`) are encrypted at rest. The `Business` model uses Eloquent accessors/mutators:

- **Mutator** (`setMpesaConsumerKeyAttribute`): calls `encrypt($value)` before storing.
- **Accessor** (`getMpesaConsumerKeyAttribute`): calls `decrypt($value)`, with a `try/catch` fallback that returns the raw value if decryption fails (handles legacy plaintext values gracefully).

---

## 7. REST API

### Overview

The REST API is available exclusively to **Enterprise plan** businesses. It is versioned under `/api/v1` and authenticated with a static bearer token.

### Token Generation & Storage

1. An owner visits **Settings → API** and clicks "Generate Token".
2. The `SettingsController::generateApiToken()` method (sudo-protected) generates a cryptographically random token using `Str::random(64)`.
3. The raw token is shown once in the UI.
4. A SHA-256 hash of the token (`hash('sha256', $token)`) is stored in `businesses.api_token`.

The raw token is never stored. On subsequent requests the incoming token is hashed and compared: `Business::where('api_token', hash('sha256', $token))->first()`.

### ApiTokenAuth Middleware

**Location**: `app/Http/Middleware/ApiTokenAuth.php`

Accepts the token via `Authorization: Bearer {token}` or the `X-API-Token` header. On a match, it calls `$business->hasFeature('api_access')` to confirm the business is on the Enterprise plan. The authenticated business is attached to the request as `$request->_api_business` for use in API controllers.

### Available Endpoints

**Base URL**: `/api/v1`

All endpoints return JSON. All list endpoints return data scoped to the authenticated business.

**Products**

| Method | Path | Description |
|---|---|---|
| GET | `/products` | Paginated list of active products |
| GET | `/products/{id}` | Single product |
| POST | `/products` | Create product |
| PUT | `/products/{id}` | Update product |

**Sales**

| Method | Path | Description |
|---|---|---|
| GET | `/sales` | Paginated list of completed sales |
| GET | `/sales/summary` | Revenue summary for current month |
| GET | `/sales/{id}` | Single sale with items |

**Customers**

| Method | Path | Description |
|---|---|---|
| GET | `/customers` | Paginated list of customers |
| GET | `/customers/{id}` | Single customer |
| POST | `/customers` | Create customer |
| PUT | `/customers/{id}` | Update customer |

### M-Pesa Callback

`POST /api/mpesa/callback` is a public endpoint (no auth). It is registered in `routes/api.php` outside the `api.token` middleware group. CSRF is excluded via `bootstrap/app.php`.

---

## 8. Scheduled Jobs

All four commands are registered in `bootstrap/app.php` using `withSchedule()`. The server crontab must call `php artisan schedule:run` every minute.

### `sme:subscription-warnings`

**Schedule**: Daily at 05:00 (UTC)
**Class**: `App\Console\Commands\SendSubscriptionWarnings`

Finds all businesses whose active subscription `end_date` is exactly 3 days from today, or whose `trial_ends_at` is exactly 3 days from today. Queues a `SubscriptionExpiringSoon` mailable to the business owner for each match.

Only fires on an exact 3-day match to avoid sending multiple warnings. If a more flexible warning window is needed (e.g. 7 days, 3 days, 1 day), the command would need to be updated to check multiple thresholds.

### `sme:low-stock-alerts`

**Schedule**: Daily at 04:00 (UTC)
**Class**: `App\Console\Commands\SendLowStockAlerts`

For every active/trial business, fetches products where `stock_qty <= reorder_level` that have not been alerted today (`low_stock_alert_sent_at` is null or before today). Queues a `LowStockAlert` mailable to the owner and stamps `low_stock_alert_sent_at = now()` on each product to prevent re-alerting the same day.

### `sme:payment-reminders`

**Schedule**: Weekly on Monday at 05:00 (UTC)
**Class**: `App\Console\Commands\SendPaymentReminders`

For every active/trial business, fetches customers with `balance_owed > 0` (via a `withDebt()` scope). Queues a `PaymentReminderDigest` mailable to the owner containing a ranked list of debtors. Stamps `payment_reminder_sent_at = now()` on each customer.

### `sme:process-recurring-invoices`

**Schedule**: Daily at 06:00 (UTC)
**Class**: `App\Console\Commands\ProcessRecurringInvoices`

Fetches all `RecurringInvoice` records that are active and due today (via a `due()` scope on the model). For each:

1. Wraps processing in a `DB::transaction()`.
2. Creates a `Sale` record with `payment_status = unpaid` and `recurring_invoice_id` set.
3. Creates `SaleItem` records mirroring the recurring invoice's items.
4. Increments `customer.balance_owed` by the total.
5. Updates `recurring_invoice.last_run_date`, `next_run_date` (calculated by `nextRunAfter()`), and `run_count`.
6. Deactivates the invoice if `end_date` has passed.

Failures are caught per-invoice, logged, and reported in the command output without halting the rest of the batch.

---

## 9. Key Services

### SaleService

**Location**: `app/Services/SaleService.php`

Encapsulates the two complex sale mutations to keep `SalesController` thin.

**`createSale(array $data, int $businessId, int $userId): Sale`**

- Validates that every product belongs to the current business.
- Validates that stock is sufficient for each line item.
- Calculates per-line totals with optional percentage discounts.
- Applies an order-level discount amount.
- Determines `payment_status` (`paid` / `partial` / `unpaid`) based on `paid_amount` vs `total_amount`.
- Creates the `Sale` and `SaleItem` records.
- Decrements `products.stock_qty` for each item.
- Increments `customers.balance_owed` if balance is due.
- Writes an `AuditLog` entry.

**`cancelSale(Sale $sale): void`**

- Prevents double-cancellation.
- Restores stock for all items.
- Decrements `customers.balance_owed` by the remaining `balance_due`.
- Updates `sale_status = cancelled`, `payment_status = unpaid`.
- Writes an `AuditLog` entry.

### SmsService

**Location**: `app/Services/SmsService.php`

Provider-agnostic SMS wrapper. Instantiated per-business (`SmsService::forBusiness($business)`).

**`isConfigured(): bool`** — returns true if `sms_provider` and `sms_api_key` are non-empty.

**`send(string $phone, string $message): bool`** — normalises the phone number and dispatches to the correct provider via `match()`. Returns a boolean success flag and never throws — all errors are logged instead.

**Providers**:

- **Africa's Talking** — HTTP POST to `https://api.africastalking.com/version1/messaging`. API key in header, form-encoded body. Checks `SMSMessageData.Recipients[0].status === 'Success'`.
- **Twilio** — HTTP POST to Twilio's REST API. Basic auth with Account SID + Auth Token. Checks `status` in `['queued', 'sent', 'delivered']`.

**Phone normalisation** (`normalizePhone()`) — converts any Kenyan number variant to `+254XXXXXXXXX` (E.164 format) for maximum provider compatibility.

### ReportService

**Location**: `app/Services/ReportService.php`

Provides `getDashboardData(int $businessId): array`, which aggregates all data needed for the dashboard in a single service call. Key data points returned:

- Today's sales value, transaction count, and expenses.
- Month-to-date revenue, expenses, and profit.
- Inventory stats: total products, low-stock count, total stock value.
- Customer stats: total customers, total outstanding debt.
- Last 6 months revenue/expense/profit chart data.
- Top 5 products by quantity sold this month.
- Top 5 customers by revenue this month.
- Last 5 sales and 5 low-stock products.
- Overdue invoices (unpaid, older than 30 days) with count and total amount.
- Cash collected vs outstanding this month.
- 14-day daily revenue sparkline.
- Pending quotes count and upcoming recurring invoices count.

### MpesaService

**Location**: `app/Services/MpesaService.php`

Daraja API wrapper. Instantiated with optional credentials array; falls back to `.env` values if the array is empty or omitted.

**`getAccessToken(): string`** — fetches and caches the OAuth token. Caches per consumer key using `Cache::remember()`.

**`stkPush(string $phone, float $amount, string $accountRef, string $description): array`** — initiates STK Push. Automatically selects `CustomerBuyGoodsOnline` (Buy Goods / till number) or `CustomerPayBillOnline` (Pay Bill / shortcode) based on whether `till_number` is set.

**`formatPhone(string $phone): string`** — normalises any Kenyan number to `254XXXXXXXXX` for the Daraja payload.

### SubscriptionService

**Location**: `app/Services/SubscriptionService.php`

**`activateFromPayment(MpesaTransaction $transaction, float $amount, ?string $receipt): void`** — called from `MpesaController::callback()` when a subscription payment succeeds. Parses the `api_ref` field to extract the plan key and business ID, creates a 30-day `Subscription` record, updates `Business.subscription_plan` and `Business.status`, writes an audit log, and queues a confirmation email.

**`isExpired(Business $business): bool`** — checks if the business's most recent active subscription has an `end_date` in the past.

---

## 10. Adding a New Feature

This guide walks through adding a new module as a fully gated Business+ feature — using a hypothetical "Projects" module as the example.

### Step 1: Define the feature flag in the plans config

Open `config/plans.php` and add the new flag to each plan:

```php
// starter
'projects' => false,

// business
'projects' => true,

// enterprise
'projects' => true,
```

### Step 2: Create the migration

```bash
php artisan make:migration create_projects_table
```

In the migration, include `business_id` as a foreign key and add a soft-delete column:

```php
Schema::create('projects', function (Blueprint $table) {
    $table->id();
    $table->foreignId('business_id')->constrained()->onDelete('cascade');
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('name');
    $table->text('description')->nullable();
    $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
    $table->timestamps();
    $table->softDeletes();
});
```

Run the migration:

```bash
php artisan migrate
```

### Step 3: Create the Model

```bash
php artisan make:model Project
```

Apply the `BelongsToBusiness` trait and define soft deletes:

```php
namespace App\Models;

use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use BelongsToBusiness, SoftDeletes;

    protected $fillable = ['business_id', 'user_id', 'name', 'description', 'status'];
}
```

### Step 4: Create the Controller

```bash
php artisan make:controller ProjectController
```

Retrieve the **active** business from the authenticated user and scope all queries — see the §1 callout on why `Auth::user()->business_id` is the wrong thing to read here:

```php
public function index(Request $request)
{
    $businessId = Auth::user()->currentBusiness()->id;
    $projects   = Project::forBusiness($businessId)->latest()->paginate(20);
    return view('projects.index', compact('projects'));
}

public function store(Request $request)
{
    $businessId = Auth::user()->currentBusiness()->id;
    // Validate, then create:
    Project::create(array_merge($validated, [
        'business_id' => $businessId,
        'user_id'     => Auth::id(),
    ]));
    return redirect()->route('projects.index')->with('success', 'Project created.');
}
```

### Step 5: Register Routes

In `routes/web.php`, add the new routes inside the `subscribed` middleware group and apply the feature gate:

```php
Route::prefix('projects')->name('projects.')
    ->middleware('feature:projects')
    ->group(function () {
        Route::get('/',              [ProjectController::class, 'index'])->name('index');
        Route::get('/create',        [ProjectController::class, 'create'])->name('create');
        Route::post('/',             [ProjectController::class, 'store'])->name('store');
        Route::get('/{project}',     [ProjectController::class, 'show'])->name('show');
        Route::get('/{project}/edit',[ProjectController::class, 'edit'])->name('edit');
        Route::put('/{project}',     [ProjectController::class, 'update'])->name('update');
        Route::delete('/{project}',  [ProjectController::class, 'destroy'])->name('destroy');
    });
```

### Step 6: Create Blade Views

Add views under `resources/views/projects/`. Follow the existing pattern:

- `index.blade.php` — table with pagination.
- `create.blade.php` / `edit.blade.php` — shared form partial if appropriate.
- `show.blade.php` — detail view.

Extend the `layouts.app` layout and use the existing component patterns (cards, tables, flash messages).

### Step 7: Add Navigation

Add the sidebar link inside `resources/views/layouts/app.blade.php` (the sidebar is inline in that one file, not a separate partial — see `public/css/sidebar.css` for its styling). Conditionally show it based on the feature flag, using the active business, not `->business` (a real relationship, but it resolves to the user's *first* assigned store, same active-store problem as `business_id` — see §1):

```blade
@if(auth()->user()->currentBusiness()?->hasFeature('projects'))
    <a href="{{ route('projects.index') }}" class="sidebar-link ...">
        Projects
    </a>
@endif
```

### Step 8: Add to the Dashboard (Optional)

If the feature should surface data on the dashboard, add a query to `ReportService::getDashboardData()` and update `DashboardController` and the dashboard Blade view.

### Step 9: Write Tests

Add feature tests under `tests/Feature/`. Test:

- Starter plan user is redirected away from `/projects` (feature gate).
- Business plan user can create, read, update, and delete projects.
- A user from Business A cannot see or modify Business B's projects.

### Checklist Summary

- [ ] Feature flag added to all three plans in `config/plans.php`
- [ ] Migration created with `business_id` FK and `softDeletes()`
- [ ] Model uses `BelongsToBusiness` trait and `SoftDeletes`
- [ ] Controller scopes all queries to `Auth::user()->currentBusiness()->id` (never the legacy `->business_id`)
- [ ] Routes are inside the `subscribed` middleware group with `feature:projects`
- [ ] Blade views follow existing layout and component patterns
- [ ] Navigation link is conditionally shown via `hasFeature()`
- [ ] Feature tests cover plan gating and tenant isolation
