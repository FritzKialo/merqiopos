# Merqio POS — Complete User Guide

**Version:** 2026 · **Platform:** merqiopos.com · **Support:** support@merqiopos.com

> **Corrected in this pass:** product name (the app was renamed from "SME Manager" to "Merqio POS") and its domain (merqiopos.com is now the official domain as of 2026-09-04 — smemanagers.com still works, same app and data, kept running until every business's own M-Pesa/Pesapal callbacks are re-pointed at the new domain), a stale Solo-plan price (§2.2), the Staff role's actual access (§22 — staff have a real self-service portal, not "no system access"), and a false claim about 2FA backup codes (§23 — that feature doesn't exist). **Updated again 2026-09-12** for the 3-tier pricing restructure (§2.2, §19, §24, Troubleshooting) — the Free and Scale plans no longer exist, the trial is 1 month on Solo only (not 14 days with Growth-level access), and the REST API is Enterprise-only. This guide has **not** been rewritten feature-by-feature against the full app, which has grown well past what's covered below — bookings (services/appointments/restaurant tables), marketing campaigns/payment links/price tiers/product reviews, a public online shop per business, petty cash/budgets/bank reconciliation/withholding tax, purchase requisitions/supplier credit notes, delivery notes/credit notes/proforma invoices, and the home-menu launcher shown right after login are all live features with no section here yet. See [README.md](../README.md#features) for the current full list.

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [Getting Started](#2-getting-started)
   - [Creating Your Account](#21-creating-your-account)
   - [Choosing a Subscription Plan](#22-choosing-a-subscription-plan)
   - [Setting Up Your Business Profile](#23-setting-up-your-business-profile)
3. [Dashboard](#3-dashboard)
4. [Inventory Management](#4-inventory-management)
   - [Adding Products](#41-adding-products)
   - [Product Variants](#42-product-variants)
   - [Product Bundles](#43-product-bundles)
   - [Categories](#44-categories)
   - [Barcode Scanning](#45-barcode-scanning)
   - [Bulk Import](#46-bulk-import)
   - [Stock Adjustments](#47-stock-adjustments)
   - [Low Stock Alerts](#48-low-stock-alerts)
5. [Point of Sale (POS)](#5-point-of-sale-pos)
   - [Making a Sale](#51-making-a-sale)
   - [Searching and Scanning Products](#52-searching-and-scanning-products)
   - [Applying Discounts](#53-applying-discounts)
   - [Loyalty Points](#54-loyalty-points)
   - [Payment Methods](#55-payment-methods)
   - [Printing Receipts](#56-printing-receipts)
6. [Sales Management](#6-sales-management)
   - [Viewing Sales History](#61-viewing-sales-history)
   - [Recording a Payment on a Sale](#62-recording-a-payment-on-a-sale)
   - [M-Pesa STK Push Payment](#63-m-pesa-stk-push-payment)
   - [Card Payment via Pesapal](#64-card-payment-via-pesapal)
   - [Sale Returns and Refunds](#65-sale-returns-and-refunds)
   - [Cancelling a Sale](#66-cancelling-a-sale)
7. [Invoicing](#7-invoicing)
   - [Creating an Invoice](#71-creating-an-invoice)
   - [Sending an Invoice](#72-sending-an-invoice)
   - [Recording Invoice Payments](#73-recording-invoice-payments)
   - [Recurring Invoices](#74-recurring-invoices)
8. [Quotes and Estimates](#8-quotes-and-estimates)
9. [Customer Management](#9-customer-management)
   - [Adding Customers](#91-adding-customers)
   - [Credit Accounts](#92-credit-accounts)
   - [Customer Portal](#93-customer-portal)
10. [Loyalty Program](#10-loyalty-program)
11. [Discounts and Coupons](#11-discounts-and-coupons)
12. [Expenses](#12-expenses)
13. [Suppliers and Purchase Orders](#13-suppliers-and-purchase-orders)
14. [Stock Receiving](#14-stock-receiving)
15. [Shift Management](#15-shift-management)
16. [Reports](#16-reports)
17. [Staff and HR](#17-staff-and-hr)
18. [Payroll](#18-payroll)
19. [Multi-Store Management](#19-multi-store-management)
20. [Payment Integrations](#20-payment-integrations)
    - [M-Pesa Setup (Per Business)](#201-m-pesa-setup-per-business)
    - [Pesapal Card Payments Setup](#202-pesapal-card-payments-setup)
    - [Subscription Billing](#203-subscription-billing)
21. [Settings](#21-settings)
22. [User Roles and Permissions](#22-user-roles-and-permissions)
23. [Two-Factor Authentication (2FA)](#23-two-factor-authentication-2fa)
24. [REST API](#24-rest-api)
25. [Troubleshooting](#25-troubleshooting)

---

## 1. Introduction

Merqio POS is a complete business management platform built specifically for Kenyan small and medium enterprises. It brings together everything you need to run your business into a single, easy-to-use system:

- **Point of Sale** — fast, touch-friendly POS with barcode scanning and thermal receipt printing
- **Inventory** — real-time stock tracking with low-stock alerts
- **Invoicing** — professional PDF invoices with M-Pesa and card payment collection
- **Customers** — loyalty points, credit accounts, purchase history
- **Payroll** — PAYE, NSSF, and SHIF calculations with P9 forms for KRA
- **M-Pesa** — receive payments directly into your business M-Pesa account (not through us)
- **Multi-store** — manage multiple branches from one account

Everything is cloud-based — no installation required. Access it from any browser on your phone, tablet, or computer.

---

## 2. Getting Started

### 2.1 Creating Your Account

1. Go to **[merqiopos.com](https://merqiopos.com)** and click **Get Started** or **Register**
2. Fill in:
   - Your full name
   - Email address
   - Password (minimum 8 characters)
   - Business/Organization name
3. Click **Create Account**
4. You will be logged in immediately and placed on a **1-month free trial** of the Solo plan

> **Your trial includes** full access to all Solo-plan features — M-Pesa sales payments, customer management, invoicing, basic reports, and basic payroll — no credit card required.

---

### 2.2 Choosing a Subscription Plan

After your trial, go to **Settings → Subscription** to pick a plan.

| Plan | Monthly Price | Best For |
|---|---|---|
| **Solo** | KSh 999 | Single shop, up to 3 staff |
| **Growth** | KSh 2,999 | Up to 3 branches, 15 staff, full payroll |
| **Enterprise** | KSh 5,999 | Unlimited branches and staff, API access |

**To subscribe:**

1. Go to **Settings → Subscription**
2. Click **Pay with Card / Paystack** on your chosen plan — you will be redirected to a secure Paystack payment page where you can pay by Visa, Mastercard, or M-Pesa
3. Alternatively click **Pay via M-Pesa** to receive an STK Push prompt on your phone
4. Once payment is confirmed, your plan activates immediately

Subscriptions last 30 days from the payment date and renew manually — you will receive a reminder email 3 days before expiry.

---

### 2.3 Setting Up Your Business Profile

After registration, complete your business profile so receipts, invoices, and reports are accurate.

1. Go to **Settings → Business Profile**
2. Fill in:
   - **Business name** — appears on receipts and invoices
   - **Phone number** — shown on receipts
   - **Email** — used for invoice delivery
   - **Physical address** — shown on invoices
   - **Logo** — upload a PNG or JPG (recommended: 400 × 400 px, max 2 MB)
   - **Currency** — defaults to KSh
3. Click **Save Business Profile**

**VAT Registration (if applicable):**

1. Go to **Settings → VAT Settings**
2. Toggle **VAT Registered** to ON
3. Enter your **KRA PIN** and **VAT registration number**
4. Set your **VAT rate** (standard is 16%)
5. Save — all future sales and invoices will automatically calculate and display VAT

---

## 3. Dashboard

The Dashboard is your business at a glance. It shows:

| Card | What it shows |
|---|---|
| **Today's Revenue** | Total sales value for today |
| **Today's Sales** | Number of transactions today |
| **Monthly Revenue** | Sales for the current calendar month |
| **Stock Alerts** | Products at or below reorder level |
| **Recent Sales** | Last 10 sales with status badges |
| **Revenue Chart** | 30-day sales trend |
| **Top Products** | Best-selling products this month |
| **Expense Summary** | Expenses posted this month |

The dashboard automatically reflects the **currently active store**. If you manage multiple branches, use the store switcher in the top navigation to switch.

---

## 4. Inventory Management

### 4.1 Adding Products

Navigate to **Inventory → Products → Add Product**.

| Field | Description |
|---|---|
| **Product Name** | Required. The display name used everywhere in the system |
| **SKU** | Stock Keeping Unit code. Leave blank to auto-generate |
| **Barcode** | EAN-13, UPC, or Code 128. Scan directly into this field |
| **Category** | Group products for filtering and reports |
| **Selling Price** | The price charged to customers |
| **Buying / Cost Price** | Used for profit calculations — not shown to customers |
| **VAT Rate** | Leave blank to use your business default. Set to 0 for VAT-exempt items |
| **Stock Quantity** | Opening stock on hand |
| **Reorder Level** | You will receive an alert when stock falls to this number |
| **Unit** | e.g. pcs, kg, litres, box |
| **Description** | Optional — appears on the online store if enabled |
| **Image** | Optional — shown in POS grid and online store |

Click **Save Product**. The product immediately appears in your POS and inventory list.

---

### 4.2 Product Variants

Use variants when a product comes in different sizes, colours, or configurations — each variant can have its own price and stock level.

1. Open the product → scroll to **Variants**
2. Click **Add Variant**
3. Enter variant name (e.g. "500ml", "Red / Size L"), price, and stock quantity
4. Repeat for each variant

At the POS, selecting a product with variants prompts the cashier to choose which variant before adding to cart.

---

### 4.3 Product Bundles

Bundles let you sell a group of products together at a combined price.

1. Go to **Inventory → Bundles → Create Bundle**
2. Name the bundle (e.g. "School Pack")
3. Set the bundle price
4. Add component products and their quantities
5. Save

When a bundle is sold, the system automatically deducts stock from each component product. The sale receipt shows the bundle name and price.

---

### 4.4 Categories

Organise your products into categories for easier searching and reporting.

1. Go to **Inventory → Categories**
2. Click **Add Category**
3. Enter category name (e.g. "Beverages", "Electronics", "Clothing")
4. Assign a colour for visual identification in the POS grid

Products without a category are listed under "Uncategorised".

---

### 4.5 Barcode Scanning

Merqio POS supports two barcode input modes:

**USB or Bluetooth Scanner (Recommended)**
- Plug in your USB barcode scanner or pair via Bluetooth
- Scanners act as a keyboard — when you scan, the barcode is typed into the active input field
- In the POS, the product is found and added to the cart immediately
- If the barcode is not found, an error is shown and you can add the product manually

**Camera (Chrome/Edge only)**
- Click the camera icon in the barcode field
- Point your device camera at the barcode
- The system reads it automatically using the browser's BarcodeDetector API

> **Supported formats:** EAN-13, EAN-8, UPC-A, UPC-E, Code 128, Code 39, QR Code

---

### 4.6 Bulk Import

Import your entire product catalogue from a spreadsheet in one step.

1. Go to **Inventory → Import**
2. Download the **Import Template** (CSV or Excel)
3. Fill in the template — see column guide below
4. Upload the filled file and click **Import**

**Template columns:**

| Column | Required | Notes |
|---|---|---|
| `name` | Yes | Product name |
| `sku` | No | Auto-generated if left blank |
| `barcode` | No | Must be unique |
| `price` | Yes | Selling price (numbers only, no KSh symbol) |
| `cost_price` | No | Buying price |
| `stock_qty` | No | Opening stock — defaults to 0 |
| `reorder_level` | No | Low-stock alert threshold — defaults to 0 |
| `category` | No | Must match an existing category name exactly |
| `unit` | No | e.g. pcs, kg, box |

After uploading, the import runs in the background. Refresh the page after a few seconds to see the results. Any rows with errors are skipped and the errors are listed so you can correct them.

> **Tip:** For large catalogues (1,000+ products), split into batches of 500 rows to avoid timeout issues.

---

### 4.7 Stock Adjustments

Use stock adjustments to record changes in stock that are not from a sale or purchase:

1. Go to **Inventory → Stock Adjustments → New Adjustment**
2. Select the product (and variant if applicable)
3. Choose the **adjustment type**:
   - **Addition** — stock found or returned
   - **Write-off** — damaged, expired, or lost goods
   - **Damage** — damaged goods (separate reporting category)
   - **Correction** — fixing a counting error
   - **Return In** — goods returned from a customer but not via the sales return flow
4. Enter the **quantity** (positive number — the system applies the direction)
5. Add a **note** explaining why the adjustment was made
6. Save

Each adjustment records the before and after quantities and the user who made it — visible in the Audit Log.

---

### 4.8 Low Stock Alerts

The system automatically sends email alerts when any product falls to or below its **reorder level**.

- Alerts are sent daily at 4:00 AM Nairobi time
- The alert lists all products at or below reorder level with current quantities
- To update reorder levels, edit the product and change the **Reorder Level** field

---

## 5. Point of Sale (POS)

### 5.1 Making a Sale

1. Click **New Sale** in the sidebar or press the POS button
2. The POS opens with:
   - **Left panel** — product grid and search
   - **Right panel** — cart and payment
3. Add products (see section 5.2)
4. Optionally select a **customer** from the dropdown (required for loyalty points and credit sales)
5. Apply discounts if needed (see section 5.3)
6. Select the **payment method**
7. Enter the **amount received**
8. Click **Complete Sale**

The system records the sale, deducts stock, prints or shows a receipt, and returns to a blank POS ready for the next transaction.

---

### 5.2 Searching and Scanning Products

The POS product panel gives you three ways to find and add products:

**1. Click the product card**
Products are displayed as cards in a grid. Click any card to add it to the cart. If the product has variants, a popup asks you to choose which variant.

**2. Category tabs**
Click a category tab above the grid to filter to that category. Click **All** to show all products.

**3. Live search**
Type in the search box to filter by product name or SKU. Matching products appear in a dropdown — click one to add it to the cart, or press the arrow keys to navigate and Enter to select.

**4. Barcode scanner**
Scan a barcode — the product is found and added instantly. Scanning the same barcode again increases the quantity by 1.

**5. Thermal printer connection**
Click **Connect Printer** to connect a USB thermal printer via Web Serial API (Chrome/Edge only). Once connected, receipts are printed automatically when a sale is completed.

> The scanner status badge in the top bar shows a pulsing green dot when a scanner is connected and ready.

---

### 5.3 Applying Discounts

**Order-level discount** (applies to the entire sale):
- In the cart panel, find the **Discount** field
- Enter a percentage (e.g. `10` for 10% off the total)
- Or enter a flat amount

**Per-line discount** (applies to one product only):
- In the cart, find the **Disc%** column next to the product row
- Click the **▾** picker to choose from your pre-defined discounts (e.g. "Staff 20%", "Happy Hour 15%")
- Or type a percentage directly into the field
- The line subtotal updates immediately

**Coupon code:**
- Click **Apply Coupon** and enter the code
- The discount is applied and shown separately in the order summary

---

### 5.4 Loyalty Points

If your business has an active loyalty program, customers earn and redeem points at the POS.

**Earning points:**
- Select a registered customer before completing the sale
- Points are automatically awarded based on your program settings (e.g. 1 point per KSh 10 spent)
- Points are recorded after the sale is marked paid

**Redeeming points:**
- Select the customer — their point balance and KSh value appears
- Click **Redeem Points** and enter the number of points to redeem
- The equivalent KSh value is deducted from the sale total
- Enter the remaining amount due for payment

> Loyalty points are only earned and redeemed by registered customers — walk-in customers do not earn points.

---

### 5.5 Payment Methods

| Method | Description |
|---|---|
| **Cash** | Enter the amount received; the system shows change due |
| **M-Pesa (STK Push)** | Enter the customer's phone — they receive a prompt on their phone to enter their PIN. The sale auto-completes when payment is confirmed |
| **M-Pesa (Manual)** | Customer pays via Paybill/Till and you enter the M-Pesa transaction code |
| **Card via Pesapal** | Redirects customer to Pesapal checkout for Visa/Mastercard/Airtel Money |
| **Bank Transfer** | Enter the bank reference number |
| **Cheque** | Enter the cheque number |
| **Store Credit** | Deduct from the customer's store credit balance |
| **Credit (Pay Later)** | Record as unpaid — added to customer's outstanding balance |

**Partial payments:** Enter less than the full amount. The sale is saved with a `partial` payment status and the balance is tracked. Record additional payments later via **Sales → View Sale → Record Payment**.

---

### 5.6 Printing Receipts

**Thermal printer (USB):**
1. Connect your printer via **Connect Printer** button in the POS
2. Receipts print automatically when a sale is completed
3. Supported printers: any ESC/POS thermal printer (Epson TM series, Xprinter, etc.)
4. Requires Chrome or Edge browser (Web Serial API)

**PDF receipt:**
- Go to **Sales → View Sale → Print Receipt** for a printable A4 receipt
- Or **Download PDF Invoice** for a formal tax invoice

**Email receipt:**
- Go to **Sales → View Sale → Send Receipt** to email the receipt to the customer

---

## 6. Sales Management

### 6.1 Viewing Sales History

Go to **Sales** in the sidebar to see all past transactions.

**Filter options:**
- Date range
- Payment status (paid / partial / unpaid)
- Sale status (completed / cancelled)
- Customer
- Payment method
- Cashier

Click any sale to view the full detail including items, payment history, VAT breakdown, and available actions.

---

### 6.2 Recording a Payment on a Sale

For sales with a balance due (credit or partial payment):

1. Go to **Sales → View Sale**
2. Click **Record Payment**
3. The payment page shows:
   - Sale summary on the left
   - Payment options on the right
4. Choose payment method and enter amount
5. Click **Confirm Payment**

The sale's `paid_amount` updates, the balance decreases, and if fully paid the status changes to **paid**.

---

### 6.3 M-Pesa STK Push Payment

This is the fastest way to collect M-Pesa payment without asking the customer for their transaction code.

1. On the payment page, enter the **customer's phone number** in the M-Pesa section
2. Adjust the **amount** if collecting a partial payment
3. Click **Send STK Push to Customer**
4. The customer's phone rings with an M-Pesa payment prompt
5. The customer enters their PIN
6. The page automatically detects the payment and redirects to the invoice — no manual action needed

**If the customer already paid directly via Paybill or Till (without STK Push):**
The page will also auto-detect this within seconds, provided your C2B URLs are registered (see section 20.1). The payment is matched to the sale by invoice number.

> **Important:** Tell the customer to enter the **invoice number** (e.g. `INV-00023`) as the account reference when paying via Paybill. This ensures automatic matching.

---

### 6.4 Card Payment via Pesapal

1. On the payment page, click **Pay by Card via Pesapal**
2. The system creates a payment order and redirects to the Pesapal hosted checkout
3. The customer pays with Visa, Mastercard, Airtel Money, or bank
4. Pesapal redirects back to the invoice page with confirmation

Pesapal payments go **directly to your business's Pesapal account** — not through Merqio POS.

---

### 6.5 Sale Returns and Refunds

To process a return or refund for a customer:

1. Go to **Sales → View Sale**
2. Click **Process Return**
3. Select the items being returned and the quantities
4. Choose **stock action:**
   - **Restock** — item goes back into inventory
   - **Write-off** — item is damaged and discarded
5. Choose **refund method:**
   - Cash refund
   - M-Pesa refund
   - Store credit (added to customer's credit balance)
6. Click **Submit Return**

The system:
- Creates a return record (reference `RTN-XXXXX`)
- Adjusts stock if restocked
- Records the refund amount
- Updates the customer's balance if store credit was issued

---

### 6.6 Cancelling a Sale

To cancel a completed sale:

1. Go to **Sales → View Sale**
2. Click **Cancel Sale**
3. Confirm the cancellation

The system automatically:
- Restores all stock quantities
- Generates a credit note for any amount already paid (reference `CN-XXXXX`)
- Records a refund entry
- Reverses any loyalty points earned
- Reduces the customer's outstanding balance if it was a credit sale

> Cancelled sales remain in history — they cannot be deleted.

---

## 7. Invoicing

### 7.1 Creating an Invoice

Invoices are formal billing documents sent to customers (vs. sales receipts which are immediate).

1. Go to **Invoices → New Invoice**
2. Select the customer (required)
3. Set the **issue date** and **due date**
4. Add line items — search for products or type items manually
5. Add any notes or payment terms
6. Set status: **Draft** (to finish later) or save and send immediately

The invoice number is auto-generated (`INV-XXXXX`).

---

### 7.2 Sending an Invoice

1. Open the invoice
2. Click **Send Invoice**
3. The system emails the invoice as a PDF to the customer's registered email
4. The invoice status changes to **Sent**

You can also download the PDF and send it manually via WhatsApp or any channel.

---

### 7.3 Recording Invoice Payments

1. Open the invoice
2. Click **Record Payment**
3. Enter the amount, payment method, and reference (e.g. M-Pesa code)
4. Save

Partial payments are supported — the invoice status shows **Partially Paid** with the remaining balance. When fully paid the status changes to **Paid**.

---

### 7.4 Recurring Invoices

Set up automatic invoicing for regular customers (e.g. monthly retainers, subscriptions).

1. Go to **Recurring Invoices → New Recurring Invoice**
2. Set the customer, line items, and amount
3. Choose the **frequency:** Weekly / Monthly / Quarterly / Yearly
4. Set the **start date** and optionally an end date
5. Save

The system automatically creates a new invoice on each due date. You can review, edit, or pause recurring invoices at any time.

---

## 8. Quotes and Estimates

Create a quote before committing to a sale — useful for customers who want a price estimate before confirming.

1. Go to **Quotes → New Quote**
2. Fill in the customer, items, and optional notes
3. Save and optionally send by email as a PDF

**Converting a quote to a sale:**
1. Open the quote
2. Click **Convert to Sale**
3. The POS opens with all quote items pre-loaded
4. Complete payment as normal

Quotes do not affect stock until converted to a sale.

---

## 9. Customer Management

### 9.1 Adding Customers

1. Go to **Customers → Add Customer**
2. Fill in:
   - Name (required)
   - Phone number (used for M-Pesa payment matching and loyalty)
   - Email (used for invoices and statements)
   - Physical address
   - Credit limit (maximum outstanding balance allowed)

Customers can also be added quickly at the POS by clicking **+ New Customer** in the customer dropdown.

---

### 9.2 Credit Accounts

Allow trusted customers to buy now and pay later.

**How it works:**
1. At the POS, select the customer
2. Choose **Credit (Pay Later)** as the payment method
3. The sale completes; the amount is added to the customer's **outstanding balance**
4. The customer's balance is visible on their profile and on the customer list
5. When the customer pays, record a payment against any outstanding sale

**Credit limit:**
Set a credit limit on the customer profile. If a customer tries to make a credit purchase that would exceed their limit, the cashier is warned.

**Customer statement:**
- Open the customer profile
- Click **View Statement** to see all transactions (sales, payments, returns) with running balance
- Download as PDF to share with the customer

---

### 9.3 Customer Portal

Customers can access their own portal to view their invoices, account balance, and transaction history.

1. Enable the portal: **Settings → Business Profile → Enable Customer Portal**
2. Share the portal link with your customer: `https://merqiopos.com/portal/login`
3. Customers log in with their registered email and a one-time code sent to their email

---

## 10. Loyalty Program

Reward repeat customers with a points-based loyalty program.

**Setting up:**
1. Go to **Settings → Loyalty Program**
2. Toggle the program **Active**
3. Set:
   - **Points per Shilling** — e.g. `1` means 1 point per KSh spent
   - **Redemption rate** — e.g. `10` means KSh 1 of discount per 10 points
   - **Minimum points to redeem** — prevents redeeming tiny amounts

**At the POS:**
- Select a registered customer — their points balance and KSh value appear automatically
- After completing the sale, points are earned and added to the customer's balance
- At redemption, points are deducted

**Viewing a customer's points:**
Open the customer profile to see total points, point history, and KSh value.

> Loyalty is entirely optional. If you do not set up a loyalty program, no loyalty UI appears in the POS.

---

## 11. Discounts and Coupons

### Discounts

Pre-define reusable discount rules that cashiers can apply at the POS.

1. Go to **Discounts → New Discount**
2. Set:
   - **Name** (e.g. "Staff Discount", "Happy Hour")
   - **Type:** Percentage or Fixed amount
   - **Value** (e.g. 15 for 15%)
   - **Start / End date** (optional — auto-expires)
   - **Minimum purchase amount** (optional)
   - **Usage limit** (optional — e.g. first 100 customers only)
3. Save

At the POS, cashiers can apply a pre-defined discount to individual product lines using the **▾ picker** in the cart, or to the whole order.

### Coupons

Coupon codes can be given to customers for one-time or limited-use discounts.

1. Go to **Coupons → New Coupon**
2. Set the code (e.g. `WELCOME20`), discount type, value, and any usage limits
3. At the POS, click **Apply Coupon** and enter the code

---

## 12. Expenses

Track business expenses to maintain accurate profit and loss figures.

1. Go to **Expenses → Add Expense**
2. Fill in:
   - **Date**
   - **Amount**
   - **Category** (e.g. Rent, Electricity, Salaries, Transport)
   - **Description**
   - **Payment method** (Cash, M-Pesa, Bank, etc.)
   - **Receipt attachment** (optional — upload a photo of the receipt)
3. Save

Expenses appear in the Profit & Loss report deducted from gross profit.

**Expense categories:** Manage categories at **Expenses → Categories**. Customise them to match your type of business.

---

## 13. Suppliers and Purchase Orders

### Adding Suppliers

1. Go to **Suppliers → Add Supplier**
2. Enter supplier name, contact person, phone, email, and address
3. Save

### Creating a Purchase Order

1. Go to **Purchase Orders → New PO**
2. Select the supplier
3. Add the items and quantities you are ordering
4. Set the expected delivery date
5. Save as **Draft** or **Submit**

### PO Lifecycle

| Status | Meaning |
|---|---|
| **Draft** | Created but not yet sent to supplier |
| **Submitted** | Sent to supplier — awaiting delivery |
| **Partially Received** | Some items received; awaiting remainder |
| **Complete** | All items received |
| **Cancelled** | PO cancelled |

---

## 14. Stock Receiving

Record stock arriving from a supplier — with or without a purchase order.

**From a Purchase Order:**
1. Open the PO
2. Click **Receive Stock**
3. Enter the quantities actually received for each item (may differ from ordered)
4. Save — stock is incremented automatically and PO status updates

**Without a Purchase Order (standalone receive):**
1. Go to **Inventory → Stock Receives → New Receive**
2. Select the supplier
3. Add items and quantities
4. Save — stock updates immediately

Each receive gets a reference number (`RCV-XXXXX`) and is recorded in the audit log.

---

## 15. Shift Management

Shifts help cashiers and managers track cash at the till and reconcile at end of day.

### Opening a Shift

1. Go to **Shifts → Open Shift**
2. Enter the **opening float** (cash in the till at the start)
3. Click **Open Shift**

All sales made while the shift is open are recorded against it.

### Closing a Shift

1. Go to **Shifts → Close Shift** (or click **Close Shift** in the active shift banner)
2. Count the cash in the till and enter the **closing cash amount**
3. The system calculates:
   - **Expected cash** = opening float + cash sales during shift
   - **Cash variance** = closing cash − expected cash (should be zero)
4. Add any handover notes
5. Click **Close Shift**

The shift report shows total sales by payment method, cash variance, and a list of all transactions.

---

## 16. Reports

### Sales Report

**Sales → Reports → Sales Report**

- Filter by date range, product, category, or cashier
- See total revenue, number of transactions, average sale value, and VAT collected
- Export to Excel or PDF

### Profit and Loss Report

**Reports → Profit & Loss**

Shows revenue, cost of goods sold (COGS), gross profit, total expenses, and net profit for any date range. Export to PDF or Excel.

### Stock Report

**Reports → Stock**

- Current stock levels for all products
- Products below reorder level highlighted
- Stock value (quantity × buying price)

### Customer Report

**Reports → Customers**

- Total spend per customer
- Outstanding balances
- Last purchase date

### Cross-Store Report (Growth plan and above)

**Organisation Dashboard → Cross-Store Reports**

Compare performance across all your branches — total revenue, stock levels, and staff activity side by side.

---

## 17. Staff and HR

Manage employee records for HR and payroll purposes.

1. Go to **Staff → Add Staff Member**
2. Either invite an existing user or create a new one
3. Fill in the **HR profile:**
   - Employment date
   - Job title and department
   - Employment type (permanent, contract, casual)
   - National ID number
   - KRA PIN (required for PAYE)
   - Bank name, branch, and account number (for payroll disbursement)
   - NSSF number
   - SHIF number
   - Phone number (for M-Pesa B2C payroll)
4. Set their **salary** and **allowances**

Staff members appear in the payroll engine once their profile is complete.

---

## 18. Payroll

### Processing Payroll

1. Go to **Payroll → New Payroll Period**
2. Set the **pay period** (e.g. June 2025) and **pay date**
3. Click **Calculate** — the system computes for every employee:
   - Basic salary + allowances = Gross pay
   - PAYE (progressive bands per KRA 2024/25 rates)
   - NSSF (6% employee + 6% employer, tiered caps)
   - SHIF (2.75% of gross)
   - Net pay = Gross − PAYE − NSSF employee − SHIF
4. Review each line — adjust individual items if needed
5. Click **Approve Period** to lock the calculations
6. Pay employees one by one by clicking **Mark Paid** on each line (or use M-Pesa B2C disbursement if configured)
7. When all employees are paid, the period closes and the total salary expense is automatically posted

### Payslips

- Click **Download Payslip** on any payroll item to download the employee's payslip as a PDF
- The payslip shows gross pay, each deduction, and net pay

### P9 Forms

At year end, generate P9 annual tax certificates for every employee:

1. Go to **Payroll → P9 Forms**
2. Select the tax year
3. Download individual PDFs or a bulk ZIP

P9 forms are required for KRA annual income tax returns.

### Auto-Payroll

Automate payroll so the system runs it on your configured pay-day.

1. Go to **Settings → Payroll Settings**
2. Enable **Auto-Payroll**
3. Set your **pay-day** (1–28 of the month)
4. Choose the **automation mode:**
   - **Calculate only** — system creates and calculates; you review and approve
   - **Auto-approve** — system calculates and approves; you only confirm payment
   - **Fully automatic** — no human touch required; use with care

---

## 19. Multi-Store Management

### Adding a Branch

Growth and Enterprise plans allow multiple stores.

1. Go to **Organisation → Stores → Add Store**
2. Enter the branch name, address, and contact details
3. Save — the new branch is immediately accessible via the store switcher

### Switching Stores

Use the **store switcher** in the top navigation bar to switch between branches without logging out. All data (inventory, sales, customers, staff) is completely separate between branches.

### Inter-Store Stock Transfers

Move stock from one branch to another:

1. Go to **Organisation → Stock Transfers → New Transfer**
2. Select **source branch**, **destination branch**, and items with quantities
3. Submit — the transfer enters **Pending** status

**Transfer lifecycle:**
1. **Pending** — submitted, awaiting approval
2. **Approved** — an owner or overall manager approves
3. **Dispatched** — stock is deducted from source branch
4. **Received** — destination branch confirms receipt; stock is added

Reference number: `TRF-XXXXX`

---

## 20. Payment Integrations

### 20.1 M-Pesa Setup (Per Business)

Configure M-Pesa so your customers can pay you directly via your own Paybill or Till number. Payments go straight to your M-Pesa business account — not through Merqio POS.

**Before you begin:** You need a Safaricom Daraja account. Register at [developer.safaricom.co.ke](https://developer.safaricom.co.ke).

**Setup steps:**

1. Go to **Settings → M-Pesa**
2. Set environment to **Sandbox** for testing or **Production** for live transactions
3. Enter your credentials:
   - **Consumer Key** — from Daraja portal
   - **Consumer Secret** — from Daraja portal
   - **Shortcode** — your Paybill or Till number
   - **Passkey** — from Daraja portal (for STK Push)
   - **Till Number** — only if you use Buy Goods (leave blank for Paybill)
4. Click **Save Credentials**
5. Scroll to **Paybill / Till Auto-Detection (C2B)** and click **Register C2B URLs with Safaricom**

After C2B registration, when a customer dials `*147#` and pays to your Paybill/Till with the invoice number as account reference, the sale is automatically marked paid and any open payment page redirects instantly.

**Callback URL for Daraja portal:** `https://merqiopos.com/api/mpesa/callback`

---

### 20.2 Pesapal Card Payments Setup

Accept Visa, Mastercard, and Airtel Money via Pesapal. Payments go to your Pesapal account.

1. Register at [pesapal.com](https://www.pesapal.com) and get your API credentials
2. Go to **Settings → Pesapal (Card)**
3. Set environment to **Sandbox** (testing) or **Production**
4. Enter **Consumer Key** and **Consumer Secret**
5. Click **Save Credentials**
6. Click **Register IPN with Pesapal** — this tells Pesapal where to send payment confirmations

Once set up, a **Pay by Card via Pesapal** button appears on the payment page.

---

### 20.3 Subscription Billing

Your Merqio POS subscription is billed separately to you as the platform owner.

- **Paystack (card)** — pay with Visa or Mastercard via secure Paystack checkout
- **M-Pesa** — receive an STK Push on your phone

Go to **Settings → Subscription** to view your current plan, payment history, and upgrade or renew.

---

## 21. Settings

| Setting | Location | Who can access |
|---|---|---|
| Business Profile | Settings → Business | Owner |
| Team Members | Settings → Team | Owner |
| VAT | Settings → VAT | Owner |
| M-Pesa | Settings → M-Pesa | Owner |
| Pesapal | Settings → Pesapal | Owner |
| Loyalty Program | Settings → Loyalty Program | Owner |
| SMS Notifications | Settings → SMS | Owner |
| Payroll Settings | Settings → Payroll | Owner |
| API Access | Settings → API | Owner |
| Subscription | Settings → Subscription | Owner |
| Two-Factor Auth | Settings → Two-Factor | All users |
| Audit Log | Sidebar → Audit Log | Owner only |

---

## 22. User Roles and Permissions

Add team members at **Settings → Team → Invite Team Member**.

| Role | What they can do |
|---|---|
| **Owner** | Everything — settings, billing, all data, audit log |
| **Overall Manager** | Same as owner except billing and subscription; cannot use the POS/make sales |
| **Manager** | Inventory, sales, invoices, expenses, customers, reports for their branch |
| **Cashier** | Make sales, view inventory (no editing), print receipts, view customers |
| **Staff** | No access to sales, inventory, customers, or bookings — but they do log in and use their own **staff portal**: view/download payslips, request leave, clock in/out, request salary advances. After logging in, staff land on a tile screen showing just their Dashboard (everyone gets this tile screen; staff simply see fewer tiles than other roles, since it's the same screen with tiles gated per role) |

**Inviting a team member:**
1. Go to **Settings → Team**
2. Click **Invite Team Member**
3. Enter their email and select their role
4. They receive an invitation email with a link to set their password

**Removing access:**
- Go to **Settings → Team**
- Click **Remove** next to the team member
- They can no longer log in to this business

---

## 23. Two-Factor Authentication (2FA)

Add an extra layer of security to your account with 2FA.

**Setup:**
1. Go to **Settings → Two-Factor Authentication**
2. Click **Enable 2FA**
3. Scan the QR code with an authenticator app (Google Authenticator, Authy, Microsoft Authenticator)
4. Enter the 6-digit code from the app to confirm

> **There are no backup/recovery codes.** An earlier version of this guide said to save backup codes at setup — that feature does not exist in the app. If you lose the device your authenticator app is on, there is currently no self-service or admin-side way to disable 2FA and get back in; keep your authenticator app backed up (most support cloud backup/restore) before enabling this.

**Logging in with 2FA:**
1. Enter email and password as normal
2. When prompted, open your authenticator app and enter the 6-digit code

**Sudo mode:**
Sensitive actions (changing business profile, M-Pesa settings, generating API tokens, team changes) require you to re-verify your 2FA code even if you are already logged in. This protects you if you leave your computer unattended.

---

## 24. REST API

Available on the Enterprise plan. Use the API to integrate Merqio POS with your own apps, ecommerce store, or reporting tools.

**Get an API token:**
1. Go to **Settings → API Access**
2. Click **Generate New Token**
3. Copy the token — it is only shown once

**Using the API:**
Include the token in every request header:
```
Authorization: Bearer YOUR_TOKEN
```

**Base URL:** `https://merqiopos.com/api/v1`

| Method | Endpoint | Description |
|---|---|---|
| GET | `/products` | List all products |
| GET | `/products/{id}` | Get a single product |
| POST | `/products` | Create a product |
| PUT | `/products/{id}` | Update a product |
| GET | `/sales` | List sales |
| GET | `/sales/{id}` | Get a single sale |
| GET | `/sales/summary` | Monthly revenue summary |
| GET | `/customers` | List customers |
| GET | `/customers/{id}` | Get a customer |
| POST | `/customers` | Create a customer |
| PUT | `/customers/{id}` | Update a customer |

All endpoints return JSON. Products and customers are scoped to your active business automatically.

---

## 25. Troubleshooting

### M-Pesa STK Push is not being received

1. Verify your Daraja credentials in **Settings → M-Pesa** — make sure you are in the correct environment (sandbox vs production)
2. Check that the phone number is in the correct format (07XX XXX XXX or 254XXXXXXXXX)
3. Confirm the customer's phone is on the Safaricom network
4. Check that your account is active on the Safaricom Developer Portal
5. For production, make sure you have switched from sandbox to production mode

### Customer's Paybill payment is not auto-detected

1. Check that C2B URLs are registered: **Settings → M-Pesa → Register C2B URLs**
2. Confirm the customer entered the correct invoice number (e.g. `INV-00023`) as the account reference
3. Check that the shortcode in your settings matches your actual Paybill/Till number

### Barcode scanner not working

1. Ensure the scanner is connected (USB or Bluetooth) and the driver is installed
2. Click inside the barcode search field before scanning — the scanner types into the active field
3. For camera scanning, use Chrome or Edge browser (Safari and Firefox are not supported)
4. Verify the barcode format is supported (EAN-13, EAN-8, UPC, Code 128, Code 39, QR)

### Import failed or products not imported

1. Download and use the official template — do not change column headers
2. Ensure all required fields (`name`, `price`) are filled
3. Category names must exactly match existing categories (case-sensitive)
4. Barcode values must be unique — duplicates are skipped
5. Keep files under 5 MB; split large files into batches of 500 rows

### 2FA code not accepted

1. Ensure your phone's clock is accurate — TOTP codes depend on the correct time
2. If your phone clock is off, codes will not match even if entered correctly
3. There is no backup-code fallback (see §23) — if you're fully locked out, contact support

### Receipt not printing

1. Ensure you are using Chrome or Edge (Web Serial API is not supported in Safari or Firefox)
2. Click **Connect Printer** in the POS before making a sale
3. When prompted, select your thermal printer from the device list and click **Connect**
4. If the printer does not appear, check the USB connection and ensure no other app is using the printer port

### "Feature not available on your plan" error

- The feature requires a higher subscription plan
- Go to **Settings → Subscription** to upgrade
- During your free trial (Solo plan only), Solo-plan features are available — this error may appear after trial expiry, or if the feature genuinely requires a higher plan than Solo

---

## Support

| Channel | Contact | Hours |
|---|---|---|
| Email | support@merqiopos.com | 24 hours (reply within 1 business day) |
| WhatsApp | +254 718 215 432 | Mon–Fri, 8 AM – 6 PM EAT |
| In-app | Click the **?** help icon in any page | Always available |

When contacting support, please include:
- Your business name and email
- A description of the issue
- Any error messages you see
- The page or feature where the issue occurs

---

*Merqio POS — Built for Kenyan businesses, by Frenkia Technologies.*
*© 2026 Merqio POS. All rights reserved.*
