@extends('layouts.app')
@section('title', 'New Sale')

{{-- Kiosk mode: the till doesn't need the app chrome around it — see the
     body.kiosk-mode rules in sidebar.css, which hide the sidebar/topbar
     (shared with the home-menu launcher) so the sale screen gets the full
     viewport. Navigation while "inside" the till (back out, log out) is
     handled by the buttons in the POS's own top bar instead. --}}
@section('body-class', 'kiosk-mode')

@push('styles')
<style>
/* Only claim the full viewport height on the two-column desktop layout —
   below 900px .pos-shell already switches to height:auto (natural document
   scroll, per the request: fit-to-one-page applies to tablet/desktop, not
   phones/small tablets) in the existing responsive block further down, and
   this needs to not fight that. The nav dock sits below .pos-shell inside
   this wrapper and keeps its own natural height (it isn't flex:1) — the
   form above it absorbs whatever's left so the two together land on
   exactly 100vh with no page scroll. */
@media (min-width: 901px) {
    body.kiosk-mode .pos-kiosk-wrap {
        display: flex;
        flex-direction: column;
        height: 100vh;
    }
    body.kiosk-mode .pos-kiosk-wrap form {
        display: flex;
        flex-direction: column;
        flex: 1;
        min-height: 0;
    }
    body.kiosk-mode .pos-shell {
        flex: 1;
        min-height: 0;
        height: auto;
        /* A grid's auto-sized row never shrinks below its tallest item's
           content height — without this, the payment column's long stack
           of fields (methods, keypad, quick amounts, paid/balance...) was
           forcing .pos-shell taller than the space left by the nav dock,
           which pushed the whole page into scrolling instead of just that
           column scrolling internally as intended. minmax(0, 1fr) lets the
           row actually shrink to the flex-allotted height. */
        grid-template-rows: minmax(0, 1fr);
    }
    body.kiosk-mode .pos-cart,
    body.kiosk-mode .pos-payment {
        min-height: 0;
    }
}

/* ── POS Shell ──────────────────────────────────────────────── */
.pos-shell {
    display: grid;
    grid-template-columns: 1fr 360px;
    gap: 0;
    height: calc(100vh - 58px);
    overflow: hidden;
}

/* ── LEFT PANEL ─────────────────────────────────────────────── */
.pos-cart {
    display: flex;
    flex-direction: column;
    background: var(--color-background);
    border-right: 1px solid var(--color-border);
    overflow: hidden;
}

/* Top bar */
.pos-topbar {
    padding: 10px 14px 0;
    flex-shrink: 0;
    background: var(--color-background);
}
.pos-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 8px;
}
.pos-meta-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.pos-invoice { font-size: 12px; color: var(--color-text-muted); font-weight: 600; letter-spacing:.03em; }
.pos-back { font-size: 12px; color: var(--color-text-muted); text-decoration:none; }
.pos-back:hover { color: var(--color-text); }

/* Scanner badge */
.scanner-badge {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 11px; font-weight: 600; color: #16a34a;
    background: #f0fdf4; border: 1px solid #bbf7d0;
    border-radius: 999px; padding: 3px 9px; cursor: default;
}
.scanner-dot {
    width: 7px; height: 7px; border-radius: 50%;
    background: #16a34a;
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0%,100% { opacity:1; } 50% { opacity:.4; }
}
.printer-btn {
    font-size: 11px; font-weight: 600;
    background: var(--color-surface-2); border: 1px solid var(--color-border);
    border-radius: 8px; padding: 4px 10px; cursor: pointer;
    color: var(--color-text); font-family: var(--font-main);
    transition: background .1s;
}
.printer-btn:hover { background: var(--color-border); }
.printer-btn.connected { color:#16a34a; border-color:#bbf7d0; background:#f0fdf4; }
.pos-void-btn {
    font-size: 11px; font-weight: 700;
    background: var(--color-danger-light); border: 1px solid var(--color-danger);
    border-radius: 8px; padding: 4px 10px; cursor: pointer;
    color: var(--color-danger); font-family: var(--font-main);
    transition: background .1s, color .1s;
}
.pos-void-btn:hover { background: var(--color-danger); color: #fff; }

/* Search bar */
.pos-search-wrap {
    display: flex; align-items: center; gap: 8px;
    background: var(--color-surface);
    border: 1.5px solid var(--color-primary);
    border-radius: var(--radius-lg, 10px); padding: 0 12px;
    margin-bottom: 0; transition: box-shadow .15s;
    position: relative;
    box-shadow: var(--shadow-xs);
}
.pos-search-wrap:focus-within { box-shadow: 0 0 0 3px var(--color-primary-light); }
.pos-scan-icon { font-size: 16px; flex-shrink: 0; }
.pos-scan-input {
    flex: 1; border: none; background: transparent;
    font-size: 14px; padding: 11px 0;
    color: var(--color-text); outline: none;
    font-family: var(--font-main);
}
.pos-scan-input::placeholder { color: var(--color-text-muted); }
.pos-camera-btn {
    font-size: 12px; font-weight: 600;
    background: var(--color-surface-2); border: 1px solid var(--color-border);
    border-radius: 7px; padding: 5px 9px; cursor: pointer;
    color: var(--color-text); white-space: nowrap; font-family: var(--font-main);
}
.pos-camera-btn:hover { background: var(--color-border); }

/* Live search dropdown */
.search-dropdown {
    position: absolute; top: calc(100% + 4px); left: 0; right: 0;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 10px;
    box-shadow: 0 8px 24px rgba(0,0,0,.12);
    z-index: 100; max-height: 260px; overflow-y: auto;
    margin: 0 14px;
}
.search-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 14px; cursor: pointer; border-bottom: 1px solid var(--color-border);
    transition: background .1s; gap: 8px;
}
.search-item:last-child { border-bottom: none; }
.search-item:hover, .search-item.active { background: var(--color-primary-light); }
.search-item-name { font-size: 13px; font-weight: 600; color: var(--color-text); }
.search-item-sku { font-size: 11px; color: var(--color-text-muted); }
.search-item-price { font-size: 13px; font-weight: 700; color: var(--color-primary); white-space: nowrap; }
.search-item-stock { font-size: 11px; color: var(--color-text-muted); white-space: nowrap; }


/* Split: product grid + cart */
.pos-split {
    display: grid;
    /* The product grid used to take half this column — shrunk to a single
       fixed-height row (see .pos-product-grid-wrap) so the cart, which is
       what actually grows during a sale, gets the space back. */
    grid-template-rows: auto 1fr;
    flex: 1;
    min-height: 0;
    overflow: hidden;
    padding-top: 8px;
}

/* Thin, unobtrusive scrollbars on every internal scroll area — a full-width
   OS scrollbar reads as "this panel is broken", a hairline one reads as
   "this panel scrolls on purpose". */
.pos-product-grid-wrap,
.pos-cart-items,
.pos-payment {
    scrollbar-width: thin;
    scrollbar-color: var(--color-border-2) transparent;
}
.pos-product-grid-wrap::-webkit-scrollbar,
.pos-cart-items::-webkit-scrollbar,
.pos-payment::-webkit-scrollbar {
    width: 6px;
}
.pos-product-grid-wrap::-webkit-scrollbar-thumb,
.pos-cart-items::-webkit-scrollbar-thumb,
.pos-payment::-webkit-scrollbar-thumb {
    background: var(--color-border-2);
    border-radius: 6px;
}
.pos-product-grid-wrap::-webkit-scrollbar-track,
.pos-cart-items::-webkit-scrollbar-track,
.pos-payment::-webkit-scrollbar-track {
    background: transparent;
}

/* Product strip — shrunk from a full browsing grid to a single scrollable
   row (featured products, or everything if none are flagged featured; see
   SalesController::create()) so the cart below gets most of the column's
   height. Anything not shown here is still reachable via search/scan. */
.pos-product-grid-wrap {
    overflow-x: auto;
    overflow-y: hidden;
    padding: 0 14px 10px;
    border-bottom: 2px solid var(--color-border);
}
.pos-product-grid {
    display: flex;
    gap: 8px;
}
.prod-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg, 12px);
    padding: 8px 6px;
    cursor: pointer;
    transition: all .12s;
    text-align: center;
    user-select: none;
    flex: 0 0 92px;
    width: 92px;
    box-shadow: var(--shadow-xs);
}
.prod-card:hover { border-color: var(--color-primary); background: var(--color-primary-light); transform: translateY(-1px); box-shadow: var(--shadow-sm); }
.prod-card:active { transform: translateY(0); }
.prod-card.out-of-stock { opacity: .4; cursor: not-allowed; }
.prod-card.bundle-card { border-style: dashed; }
.prod-card-name { font-size: 11px; font-weight: 700; color: var(--color-text); margin-bottom: 3px; line-height: 1.25; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.prod-card-price { font-size: 12px; font-weight: 800; color: var(--color-primary); margin-bottom: 2px; }
.prod-card-stock { font-size: 9.5px; color: var(--color-text-muted); }
.prod-card-stock.low { color: #f59e0b; font-weight: 700; }

/* Cart section */
.pos-cart-section {
    display: flex; flex-direction: column; overflow: hidden; min-height: 0;
}
.pos-cart-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 8px 14px 6px; flex-shrink: 0;
}
.pos-cart-items {
    flex: 1; overflow-y: auto; padding: 0 14px 8px;
}

/* Cart row (card style) */
.cart-row {
    display: grid;
    grid-template-columns: 1fr auto auto auto auto;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md, 10px);
    margin-bottom: 6px;
    font-size: 13px;
    box-shadow: var(--shadow-xs);
    transition: box-shadow .12s;
}
.cart-row:hover { box-shadow: var(--shadow-sm); }
.cart-row-name { font-weight: 600; color: var(--color-text); font-size: 13px; min-width: 0; }
.cart-row-price { font-size: 12px; color: var(--color-text-muted); margin-top: 2px; }

/* Empty state */
.pos-empty {
    text-align: center; padding: 24px 16px; color: var(--color-text-muted);
}
.pos-empty-icon { margin-bottom: 6px; color: var(--color-text-muted); }
.pos-empty-icon svg { width: 30px; height: 30px; }
.pos-empty p { font-size: 13px; }

/* Qty stepper */
.qty-stepper { display:flex; align-items:center; border:1px solid var(--color-border); border-radius:7px; overflow:hidden; }
.qty-btn { width:26px; height:26px; border:none; background:var(--color-surface-2); color:var(--color-text); font-size:15px; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background .1s; }
.qty-btn:hover { background:var(--color-border); }
.qty-input { width:36px; border:none; border-left:1px solid var(--color-border); border-right:1px solid var(--color-border); text-align:center; font-size:13px; font-weight:700; padding:3px 0; background:var(--color-surface); color:var(--color-text); font-family:var(--font-main); outline:none; }

.pos-price-input { width:75px; font-size:12px; border:1px solid var(--color-border); border-radius:6px; padding:4px 6px; background:var(--color-surface); color:var(--color-text); text-align:right; font-family:var(--font-main); outline:none; }
.pos-disc-input { width:46px; font-size:12px; border:1px solid var(--color-border); border-radius:6px; padding:4px 6px; background:var(--color-surface); color:var(--color-text); text-align:right; font-family:var(--font-main); outline:none; }
.pos-subtotal { font-weight:800; font-size:13px; white-space:nowrap; min-width:70px; text-align:right; }
.pos-remove-btn { width:24px; height:24px; border:none; background:none; color:var(--color-text-muted); font-size:15px; cursor:pointer; border-radius:5px; display:flex; align-items:center; justify-content:center; transition:background .1s,color .1s; flex-shrink:0; }
.pos-remove-btn:hover { background:#fee2e2; color:#b91c1c; }

/* Keep old classes used in sales.js rows */
.pos-product-select { display:none; } /* hidden — we use product grid now */
.pos-add-btn { display:none; }       /* hidden — add via grid */

/* ── RIGHT: Payment Panel ───────────────────────────────────── */
.pos-payment {
    display: flex;
    flex-direction: column;
    background: var(--color-surface);
    overflow-y: auto;
    padding: 12px 14px;
    gap: 0;
}

/* ── Nav Dock — the sidebar's main items, surfaced as 3D "keycap" boxes in
   their own strip below the till instead of living in a hidden sidebar.
   Same role/feature gating as the real sidebar in layouts/app.blade.php —
   only the top-level items, not every indented sub-link. */
.pos-nav-dock {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap;
    gap: 14px;
    padding: 16px;
    flex-shrink: 0;
    background: var(--color-surface);
    border-top: 1px solid var(--color-border);
}
.pos-nav-tile {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 7px;
    width: 92px;
    height: 80px;
    flex-shrink: 0;
    border-radius: 14px;
    border: 1px solid var(--color-border-2);
    background: linear-gradient(180deg, var(--color-surface) 0%, var(--color-surface-2) 100%);
    color: var(--color-text);
    text-decoration: none;
    font-size: 11.5px;
    font-weight: 700;
    text-align: center;
    line-height: 1.15;
    /* The bottom "ledge" (solid shadow) is what reads as a raised, pressable
       key — the soft shadow above it just lifts the whole thing off the
       page. */
    box-shadow: 0 4px 0 var(--color-border-2), 0 6px 10px rgba(0,0,0,.08);
    transition: transform .08s ease, box-shadow .08s ease;
}
.pos-nav-tile:hover {
    transform: translateY(-2px);
    border-color: var(--color-primary);
    box-shadow: 0 6px 0 var(--color-border-2), 0 10px 16px rgba(0,0,0,.12);
    color: var(--color-primary);
}
.pos-nav-tile:active {
    transform: translateY(3px);
    box-shadow: 0 1px 0 var(--color-border-2), 0 1px 3px rgba(0,0,0,.08);
}
.pos-nav-tile svg { width: 24px; height: 24px; flex-shrink: 0; }

.pos-customer-row {
    display: flex;
    gap: 8px;
    margin-bottom: 6px;
}
.pos-customer-row select,
.pos-customer-row input {
    flex: 1;
    font-size: 12.5px;
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 5px 8px;
    background: var(--color-surface-2);
    color: var(--color-text);
    font-family: var(--font-main);
    outline: none;
    min-width: 0;
}

/* Totals block */
.pos-totals {
    background: var(--color-surface-2);
    border-radius: var(--radius-lg, 12px);
    padding: 7px 10px;
    margin-bottom: 6px;
}
.pos-totals-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 2px 0;
    font-size: 13px;
    color: var(--color-text-muted);
}
.pos-totals-row.total-row {
    border-top: 2px solid var(--color-border);
    margin-top: 3px;
    padding-top: 5px;
}
.pos-total-label {
    font-size: 13px;
    font-weight: 700;
    color: var(--color-text);
    text-transform: uppercase;
    letter-spacing: .04em;
}
.pos-total-amount {
    font-family: var(--font-heading);
    font-size: 22px;
    font-weight: 800;
    color: var(--color-text);
    letter-spacing: -.02em;
}

.pos-discount-inline {
    display: flex;
    align-items: center;
    gap: 6px;
}
.pos-discount-inline input {
    width: 80px;
    font-size: 13px;
    border: 1px solid var(--color-border);
    border-radius: 6px;
    padding: 3px 7px;
    background: var(--color-surface);
    color: var(--color-text);
    text-align: right;
    font-family: var(--font-main);
    outline: none;
}

/* Coupon */
.pos-coupon-row {
    display: flex;
    gap: 6px;
    margin-bottom: 6px;
}
.pos-coupon-row input {
    flex: 1;
    font-size: 12.5px;
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 5px 9px;
    background: var(--color-surface-2);
    color: var(--color-text);
    font-family: var(--font-main);
    outline: none;
}
.pos-apply-btn {
    font-size: 12px;
    font-weight: 700;
    background: var(--color-surface-2);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 5px 12px;
    cursor: pointer;
    color: var(--color-text);
    font-family: var(--font-main);
    white-space: nowrap;
}
.pos-apply-btn:hover { background: var(--color-border); }

/* Payment method buttons */
.pos-method-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--color-text-muted);
    margin-bottom: 6px;
}
.pos-methods {
    /* One row of 4 instead of the original 2x2 grid — halves this block's
       height on the compact desktop layout, where the column is a fixed
       360px so 4 narrow buttons still tap fine. Mobile already used 4
       columns too (see the max-width:900px override below) since there
       the column goes full-page-width once .pos-shell stacks. */
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 6px;
    margin-bottom: 8px;
}
.pos-method-btn {
    padding: 7px 4px;
    border-radius: var(--radius-md, 10px);
    border: 1.5px solid var(--color-border);
    background: var(--color-surface);
    color: var(--color-text);
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    font-family: var(--font-main);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
    transition: all .12s;
    box-shadow: var(--shadow-xs);
}
.pos-method-btn .method-icon { display: flex; }
.pos-method-btn .method-icon svg { width: 16px; height: 16px; }
.pos-method-btn:hover { border-color: var(--color-primary); background: var(--color-primary-light); }
.pos-method-btn:disabled { opacity: .4; cursor: not-allowed; box-shadow: none; }
.pos-method-btn:disabled:hover { border-color: var(--color-border); background: var(--color-surface); }
.pos-method-btn.active {
    border-color: var(--color-primary);
    background: var(--color-primary-light);
    color: var(--color-primary);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary) 15%, transparent);
}

/* Amount paid */
.pos-amount-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--color-text-muted);
    margin-bottom: 6px;
}
.pos-amount-input {
    width: 100%;
    font-family: var(--font-heading);
    font-size: 20px;
    font-weight: 800;
    text-align: right;
    border: 2px solid var(--color-border);
    border-radius: 10px;
    padding: 5px 10px;
    background: var(--color-surface-2);
    color: var(--color-text);
    outline: none;
    letter-spacing: -.02em;
    transition: border-color .15s;
    box-sizing: border-box;
    margin-bottom: 5px;
}
.pos-amount-input:focus { border-color: var(--color-primary); }

/* On-screen numeric keypad — for entering the tendered amount by touch on
   registers/tablets with no physical keyboard (the reference hardware POS
   photo the request was based on always has one of these). */
.pos-keypad {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 3px;
    margin-bottom: 5px;
}
.keypad-btn {
    padding: 3px 0;
    border-radius: var(--radius-sm, 7px);
    border: 1px solid var(--color-border);
    box-shadow: var(--shadow-xs);
    background: var(--color-surface-2);
    color: var(--color-text);
    font-size: 13px;
    font-weight: 700;
    font-family: var(--font-heading);
    cursor: pointer;
    line-height: 1.3;
    transition: background .1s;
}
.keypad-btn:hover { background: var(--color-border); }
.keypad-btn:active { transform: scale(0.96); }
.keypad-btn.keypad-backspace { color: var(--color-text-muted); font-size: 14px; }

/* Quick amount buttons */
.pos-quick-amounts {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 6px;
    margin-bottom: 6px;
}
.pos-quick-btn {
    padding: 5px 4px;
    border-radius: var(--radius-md, 8px);
    border: 1px solid var(--color-border);
    background: var(--color-surface-2);
    color: var(--color-text);
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    font-family: var(--font-main);
    text-align: center;
    transition: background .1s, box-shadow .1s;
    box-shadow: var(--shadow-xs);
}
.pos-quick-btn:hover { background: var(--color-border); }

/* Change row */
.pos-change-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    padding: 7px 12px;
    background: var(--color-surface-2);
    border-radius: 10px;
    margin-bottom: 6px;
    font-weight: 700;
    font-size: 14px;
}
.pos-change-row .change-label { color: var(--color-text-muted); }
.pos-change-amount { font-family: var(--font-heading); font-size: 18px; font-weight: 800; color: var(--color-success); }

/* Paid / Balance */
.pos-paid-balance {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px;
    margin-bottom: 6px;
}
.pos-stat-box {
    background: var(--color-surface-2);
    border-radius: var(--radius-md, 10px);
    padding: 5px 10px;
    text-align: center;
}
.pos-stat-box .stat-label { font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: var(--color-text-muted); font-weight: 600; }
.pos-stat-box .stat-value { font-family: var(--font-heading); font-size: 17px; font-weight: 800; color: var(--color-text); margin-top: 3px; }
.pos-stat-box.is-paid .stat-value { color: var(--color-success); }
.pos-stat-box.is-balance .stat-value { color: var(--color-danger); }

/* M-Pesa notice — was a one-off hardcoded blue; reuses the app's real
   info-alert palette instead (still a distinct color from the neutral
   surface, since this is a genuine "read this" notice, just not a
   bespoke shade found nowhere else in the app). */
.pos-mpesa-notice {
    display: none;
    background: var(--color-info-light);
    border-left: 3px solid var(--color-info);
    border-radius: 8px;
    padding: 8px 10px;
    font-size: 12.5px;
    color: var(--color-info);
    margin-bottom: 8px;
    line-height: 1.4;
}

/* Record button */
.pos-record-btn {
    width: 100%;
    padding: 10px;
    background: var(--color-primary);
    color: #fff;
    border: none;
    border-radius: var(--radius-lg, 12px);
    font-size: 15px;
    font-weight: 800;
    font-family: var(--font-heading);
    cursor: pointer;
    letter-spacing: -.01em;
    transition: opacity .15s, transform .1s, box-shadow .15s;
    margin-bottom: 5px;
    /* A shadow tinted with the button's own color reads as considered/
       branded, matching the same treatment used on the app's other
       primary actions (see quick-action-primary in dashboard.css). */
    box-shadow: 0 2px 10px color-mix(in srgb, var(--color-primary) 35%, transparent);
}
.pos-record-btn:hover { opacity: .95; transform: translateY(-1px); box-shadow: 0 4px 16px color-mix(in srgb, var(--color-primary) 45%, transparent); }
.pos-record-btn:active { transform: translateY(0); }

.pos-fullpaid-btn {
    width: 100%;
    padding: 6px;
    background: none;
    border: 1.5px dashed var(--color-success);
    border-radius: 10px;
    color: var(--color-success);
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    font-family: var(--font-main);
    transition: background .1s;
}
.pos-fullpaid-btn:hover { background: var(--color-success-light); }

/* Barcode video */
#barcodeVideoWrap {
    display: none;
    border: 2px solid var(--color-primary);
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 10px;
}
#barcodeSaleVideo { width: 100%; display: block; }

/* ── Mobile checkout: sticky bar + slide-up payment sheet ──────
   Desktop/tablet (≥901px) never sees any of this — .pos-payment stays the
   normal always-visible right column it already is, and the handle/back
   button and cart bar are hidden outright so they can't be tabbed to. */
.pos-sheet-handle-row { display: none; }
.mobile-cart-bar { display: none; }

/* Responsive */
@media (max-width: 900px) {
    .pos-shell {
        grid-template-columns: 1fr;
        height: auto;
        overflow: visible;
        /* This used to be `margin: -16px`, written to cancel the shared
           `.page` wrapper's own padding so the shell bleeds edge-to-edge —
           but this kiosk view (body.kiosk-mode) never renders inside a
           `.page` div at all (.pos-kiosk-wrap sits directly in
           .main-content, which has none of its own padding either). So
           there was never any padding to cancel, and the negative margin
           was pure unconditional overflow: it stretched .pos-shell 16px
           past the viewport on every phone/tablet ≤900px, causing a
           permanent horizontal scrollbar. */
        margin: 0;
    }
    .pos-cart { height: auto; }
    .pos-methods { grid-template-columns: repeat(4, 1fr); }

    /* Leave room at the bottom of the scrolling page for the fixed
       .mobile-cart-bar so it never overlaps the last nav-dock tile. */
    .pos-kiosk-wrap { padding-bottom: 68px; }

    /* .pos-payment becomes a full-screen slide-up sheet instead of the
       normal in-flow column below the cart — reached via the fixed
       .mobile-cart-bar instead of scrolling past the whole cart. Same
       markup, same fields; only how it's reached changes. */
    .pos-payment {
        position: fixed;
        inset: 0;
        z-index: 700;
        border-top: none;
        transform: translateY(100%);
        transition: transform .28s ease;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
    }
    .pos-payment.mobile-sheet-open { transform: translateY(0); }

    /* Grab-handle + explicit "Back to cart" affordance at the top of the
       sheet — phones have no adjacent cart column to tap back into like
       desktop does, so this is the one way out besides Record Sale. */
    .pos-sheet-handle-row {
        display: flex;
        justify-content: center;
        position: sticky;
        top: 0;
        background: var(--color-surface);
        padding: 6px 0 10px;
        margin: -16px -16px 10px;
        z-index: 1;
    }
    .pos-sheet-back {
        display: flex; align-items: center; gap: 6px;
        border: none; background: var(--color-surface-2);
        color: var(--color-text); font-family: var(--font-main);
        font-size: 13px; font-weight: 700;
        border-radius: 999px; padding: 8px 16px 8px 12px;
        cursor: pointer;
    }

    /* Sticky bar: cart count + running total + Charge CTA, always visible
       while browsing/building the cart on a phone. Hidden while the
       payment sheet itself is open (it would sit underneath it anyway). */
    .mobile-cart-bar {
        display: flex;
        align-items: center;
        gap: 10px;
        position: fixed;
        left: 0; right: 0; bottom: 0;
        z-index: 650;
        background: var(--color-primary);
        color: #fff;
        border: none;
        padding: 12px 16px calc(12px + env(safe-area-inset-bottom, 0px));
        font-family: var(--font-main);
        cursor: pointer;
        box-shadow: 0 -4px 16px rgba(0,0,0,.15);
    }
    .mobile-cart-bar.is-hidden { display: none; }
    .mobile-cart-bar-count { font-size: 12px; font-weight: 700; opacity: .85; white-space: nowrap; }
    .mobile-cart-bar-total { font-size: 16px; font-weight: 800; font-family: var(--font-heading); margin-right: auto; }
    .mobile-cart-bar-cta { display: flex; align-items: center; gap: 6px; font-size: 14px; font-weight: 800; white-space: nowrap; }

    /* Background scroll lock while the sheet is open. */
    body.mobile-checkout-open { overflow: hidden; }

    /* The one-page fit above is a desktop/tablet-only request — phones and
       small tablets keep scrolling the page normally, so there's no reason
       to run them at the same compacted spacing. Restore the original,
       more comfortable touch-target sizing here instead. */
    .pos-payment { padding: 16px; }
    .pos-customer-row { margin-bottom: 14px; }
    .pos-customer-row select, .pos-customer-row input { padding: 7px 10px; }
    .pos-totals { padding: 14px; margin-bottom: 14px; }
    .pos-totals-row { padding: 5px 0; }
    .pos-totals-row.total-row { margin-top: 8px; padding-top: 12px; }
    .pos-total-amount { font-size: 26px; }
    .pos-coupon-row { margin-bottom: 14px; }
    .pos-method-label { margin-bottom: 8px; }
    .pos-methods { gap: 8px; margin-bottom: 14px; }
    .pos-method-btn { padding: 12px 8px; gap: 4px; }
    .pos-method-btn .method-icon svg { width: 20px; height: 20px; }
    .pos-amount-input { font-size: 28px; padding: 10px 16px; margin-bottom: 8px; }
    .pos-keypad { gap: 6px; margin-bottom: 10px; }
    .keypad-btn { padding: 12px 0; font-size: 16px; }
    .pos-quick-amounts { margin-bottom: 14px; }
    .pos-quick-btn { padding: 7px 4px; }
    .pos-change-row { padding: 10px 14px; margin-bottom: 14px; }
    .pos-change-amount { font-size: 20px; }
    .pos-paid-balance { gap: 8px; margin-bottom: 14px; }
    .pos-stat-box { padding: 10px 12px; }
    .pos-mpesa-notice { padding: 10px 12px; margin-bottom: 14px; line-height: 1.5; }
    .pos-record-btn { padding: 16px; margin-bottom: 8px; }
    .pos-fullpaid-btn { padding: 10px; }
    #loyaltyNone, #loyaltyNoPoints { padding: 8px 12px !important; margin-bottom: 10px !important; }
    #loyaltyPanel { padding: 10px 12px !important; margin-bottom: 10px !important; }
}
/* The invoice/scanner/printer/void/back cluster was a single unwrapped flex
   row with no phone-width handling at all — on a narrow screen it would
   either overflow horizontally or crush every label into unreadable text.
   The scanner-ready badge is purely decorative status (the scanner works
   automatically whether it's shown or not), so it's the one dropped first;
   everything else wraps and shrinks instead of being hidden outright. */
@media (max-width: 640px) {
    #scannerStatus { display: none; }
    .pos-meta-actions { gap: 6px; }
    .printer-btn, .pos-void-btn { padding: 4px 8px; font-size: 10.5px; }
    .pos-back { font-size: 11px; }
}
/* Tablets held landscape (1024x768) and short laptop screens: the full-size
   dock wrapped onto a second row, eating the height the payment column needed
   so the Record Sale button ended up below the fold. Compact keycaps keep it
   on one row. */
@media (min-width: 901px) and (max-width: 1240px), (min-width: 901px) and (max-height: 820px) {
    .pos-nav-dock { gap: 8px; padding: 8px 10px; }
    .pos-nav-tile { width: 76px; height: 62px; gap: 4px; font-size: 10px; border-radius: 11px; }
    .pos-nav-tile svg { width: 20px; height: 20px; }
}
@media (max-width: 420px) {
    .pos-keypad { gap: 5px; }
    .keypad-btn { padding: 10px 0; font-size: 15px; }
    .pos-quick-amounts { grid-template-columns: repeat(2, 1fr); }
    .pos-nav-tile { width: 60px; height: 56px; font-size: 9px; }
    .pos-nav-tile svg { width: 17px; height: 17px; }
}
</style>
@endpush

@section('content')

{{-- ── Offline PIN lock screen ──────────────────────────────────────────────
Placed as the very first thing in the page, with its own tiny inline script
run immediately (not deferred to the bottom-of-page script block), so a
cold, offline page load shows the lock BEFORE any POS content ever paints —
not after a flash of it. This overlay is a LOCAL convenience gate on an
already-authenticated, already-cached page — it doesn't replace real login,
and doesn't run while already online (going offline mid-session does NOT
re-lock an in-progress sale; this only ever gates a fresh page load). --}}
<div id="offlineLockScreen" style="display:none; position:fixed; inset:0; background:#0f172a; z-index:99999; align-items:center; justify-content:center; flex-direction:column; color:#fff;">
    <div style="max-width:320px; width:92%; text-align:center;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:40px;height:40px;margin:0 auto 20px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        <h2 style="margin:0 0 6px; font-size:18px;">You're Offline</h2>
        <p id="offlineLockMsg" style="font-size:13px; color:#94a3b8; margin:0 0 20px;">Enter your PIN to unlock this till.</p>
        <div id="offlineLockPinArea">
            <input type="password" inputmode="numeric" pattern="[0-9]*" maxlength="6" id="offlineLockPinInput" style="width:100%; font-size:24px; text-align:center; letter-spacing:6px; padding:12px; border-radius:8px; border:none; margin-bottom:12px; box-sizing:border-box;">
            <div id="offlineLockError" style="color:#f87171; font-size:13px; margin-bottom:12px; min-height:18px;"></div>
            <button type="button" id="offlineLockUnlockBtn" style="width:100%; background:#16a34a; color:#fff; border:none; border-radius:8px; padding:12px; font-weight:700; font-size:15px; cursor:pointer;">Unlock</button>
        </div>
    </div>
</div>
<script>
(function () {
    // Synchronous, runs immediately at this point in the HTML — before the
    // rest of the page below it even parses. Only decides whether to SHOW
    // the overlay; the actual PIN check (needs Web Crypto, which is async)
    // is wired up later once the full script block at the bottom loads.
    if (navigator.onLine) return;
    var hasPinSetup = !!localStorage.getItem('pos_offline_pin_hash');
    var overlay = document.getElementById('offlineLockScreen');
    var pinArea = document.getElementById('offlineLockPinArea');
    var msg     = document.getElementById('offlineLockMsg');
    overlay.style.display = 'flex';
    if (!hasPinSetup) {
        pinArea.style.display = 'none';
        msg.textContent = "Offline access hasn't been set up on this device yet. Connect to the internet and set an Offline PIN from the till's toolbar first.";
    }
})();
</script>

{{-- ── Offline status banner ── --}}
<div id="offlineBanner" style="display:none; background:#1e293b; color:#f8fafc; padding:10px 16px; font-size:13px; font-weight:600; align-items:center; gap:10px; border-bottom:2px solid #f59e0b;">
    <span style="display:inline-flex;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;"><line x1="1" y1="1" x2="23" y2="23"/><path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"/><path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"/><path d="M10.71 5.05A16 16 0 0 1 22.58 9"/><path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg></span>
    <span>You are offline. Sales will be saved and uploaded automatically when your connection returns.</span>
    <span id="offlineQueueCount" style="margin-left:auto; background:#f59e0b; color:#1e293b; border-radius:20px; padding:2px 10px; font-size:12px;"></span>
</div>
<div id="syncBanner" style="display:none; background:#16a34a; color:#fff; padding:10px 16px; font-size:13px; font-weight:600; align-items:center; gap:10px;">
    <span style="display:inline-flex;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></span>
    <span id="syncBannerMsg">Sales synced successfully.</span>
</div>

{{-- A sale that fails to sync represents a real transaction — payment
already taken, goods already handed over — that never made it into the
system. This has to stay visible until resolved, online or offline,
which is why it's a separate banner from the two above. --}}
<div id="failedSalesBanner" style="display:none; background:#b91c1c; color:#fff; padding:10px 16px; font-size:13px; font-weight:600; align-items:center; gap:10px;">
    <span style="display:inline-flex;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></span>
    <span id="failedSalesMsg"></span>
    <button type="button" id="viewFailedSalesBtn" style="margin-left:auto; background:#fff; color:#b91c1c; border:none; border-radius:6px; padding:4px 12px; font-size:12px; font-weight:700; cursor:pointer;">View</button>
</div>

<div id="failedSalesModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9998; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:12px; max-width:520px; width:92%; max-height:80vh; overflow-y:auto; padding:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <h3 style="margin:0; font-size:16px;">Sales That Failed to Sync</h3>
            <button type="button" id="closeFailedSalesModalBtn" style="background:none; border:none; font-size:20px; cursor:pointer; line-height:1;" aria-label="Close">&times;</button>
        </div>
        <p style="font-size:13px; color:#64748b; margin:0 0 14px;">
            These sales were made but never reached the system. Fix the underlying issue — restock the item, or log back in if your session expired — then Retry. Only Discard if you've already recorded this sale another way; it can't be undone.
        </p>
        <div id="failedSalesList"></div>
    </div>
</div>

{{-- Offline PIN setup — a LOCAL convenience gate on this device only. It
does not create any new login credential and is never sent to the server;
it just protects the already-cached, already-authenticated till screen
above from casual walk-up use while offline. --}}
<div id="offlinePinModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9998; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:12px; max-width:380px; width:92%; padding:24px;">
        <h3 style="margin:0 0 8px; font-size:16px;">Offline Access PIN</h3>
        <p style="font-size:13px; color:#64748b; margin:0 0 16px;">
            Lets you keep using THIS till on THIS device if the internet drops. It's a local unlock for a screen that's already signed in — not a new login, and anyone who knows it can use the till offline, so only set one on a device you trust.
        </p>
        <div id="offlinePinCurrentState" style="font-size:12px; color:#16a34a; font-weight:600; margin-bottom:12px;"></div>
        <div class="form-group">
            <label style="font-size:13px; font-weight:600;">New PIN (4–6 digits)</label>
            <input type="password" inputmode="numeric" pattern="[0-9]*" maxlength="6" id="offlinePinInput1" class="form-control" style="font-size:20px; text-align:center; letter-spacing:4px;">
        </div>
        <div class="form-group">
            <label style="font-size:13px; font-weight:600;">Confirm PIN</label>
            <input type="password" inputmode="numeric" pattern="[0-9]*" maxlength="6" id="offlinePinInput2" class="form-control" style="font-size:20px; text-align:center; letter-spacing:4px;">
        </div>
        <div id="offlinePinError" style="color:#b91c1c; font-size:13px; margin-bottom:10px; display:none;"></div>
        <div style="display:flex; gap:8px;">
            <button type="button" id="offlinePinSaveBtn" style="flex:1; background:#16a34a; color:#fff; border:none; border-radius:6px; padding:10px; font-weight:600; cursor:pointer;">Save PIN</button>
            <button type="button" id="offlinePinCancelBtn" style="flex:1; background:#fff; color:#111; border:1px solid #d1d5db; border-radius:6px; padding:10px; font-weight:600; cursor:pointer;">Cancel</button>
        </div>
        <button type="button" id="offlinePinRemoveBtn" style="margin-top:10px; width:100%; background:none; border:none; color:#b91c1c; font-size:12px; cursor:pointer; display:none;">Remove offline PIN from this device</button>
    </div>
</div>

@php
    $navUser     = auth()->user();
    $navBusiness = $navUser->currentBusiness();
    $navOrg      = $navBusiness?->organization;
@endphp
<div class="pos-kiosk-wrap">
<form method="POST" action="{{ route('sales.store') }}" id="saleForm">
@csrf
<input type="hidden" name="offline_id" id="offlineIdHidden" value="">
<input type="hidden" id="saleId" value="">
<input type="hidden" name="discount_id" id="discountIdHidden">
<input type="hidden" name="coupon_discount_amount" id="couponDiscountHidden" value="0">
@if($hasLoyalty)
<input type="hidden" name="loyalty_points_redeemed" id="loyaltyPointsHidden" value="0">
<input type="hidden" name="loyalty_discount_amount" id="loyaltyDiscountHidden" value="0">
@endif
<input type="hidden" id="totalValue" value="0">
<input type="hidden" name="payment_method" id="paymentMethodHidden" value="cash">
<input type="hidden" name="notes" id="notesHidden" value="">

<div class="pos-shell">

    {{-- ═══════════ LEFT: PRODUCTS + CART ═══════════ --}}
    <div class="pos-cart">

        {{-- Top bar: invoice, back, scanner status --}}
        <div class="pos-topbar">
            <div class="pos-meta">
                <span class="pos-invoice">Invoice: <strong>{{ $invoiceNo }}</strong></span>
                <div class="pos-meta-actions">
                    <span id="scannerStatus" class="scanner-badge" title="USB barcode scanner: plug in and scan — it works automatically">
                        <span class="scanner-dot"></span> Scanner Ready
                    </span>
                    <button type="button" id="printerConnectBtn" class="printer-btn" onclick="connectPrinter()" title="Connect thermal printer via USB">
                        Connect Printer
                    </button>
                    <button type="button" id="offlinePinSetupBtn" class="printer-btn" onclick="openOfflinePinSetup()" title="Set a PIN to unlock this till on this device when there's no internet">
                        Offline Access
                    </button>
                    <button type="button" class="pos-void-btn" onclick="voidSale()" title="Clear the cart and start over">
                        Void Sale
                    </button>
                    {{-- sales.index (Sales History) is owner/manager only —
                    a cashier hitting it gets bounced with a "no access"
                    error, so send them to their own dashboard instead. Now
                    that kiosk mode hides the sidebar entirely, this is a
                    cashier's way out of the till — from the dashboard, the
                    normal profile-menu Logout (in layouts/app.blade.php,
                    unrelated markup to what stood here) still works fine.
                    A separate Logout form used to sit here too, but it
                    stopped responding to real clicks for a reason we
                    couldn't pin down even with a live DevTools session —
                    no console error, no network request, correct element
                    under the cursor — so removed rather than leave a dead
                    button in the one screen a cashier can't navigate away
                    from any other way. --}}
                    <a href="{{ auth()->user()->role === 'cashier' ? route('dashboard') : route('sales.index') }}" class="pos-back">← Back</a>
                </div>
            </div>

            {{-- Search bar with live autocomplete --}}
            <div class="pos-search-wrap" id="barcodeScanBar">
                <span class="pos-scan-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:15px;height:15px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
                <input type="text" id="barcodeInput" class="pos-scan-input"
                    placeholder="Search product name, SKU or scan barcode…"
                    autocomplete="off" spellcheck="false"
                    oninput="liveSearch(this.value)" onkeydown="searchKeyNav(event)">
                <span id="barcodeFeedback" style="font-size:12px;color:var(--color-success);white-space:nowrap;flex-shrink:0;"></span>
                <button type="button" id="barcodeCameraBtn" class="pos-camera-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;vertical-align:-2px;"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg> Camera</button>
            </div>
            {{-- Live search dropdown --}}
            <div id="searchDropdown" class="search-dropdown" style="display:none;"></div>

            <div id="barcodeVideoWrap"><video id="barcodeSaleVideo"></video></div>
        </div>

        {{-- Split: product strip (top, shrunk) + cart (bottom, gets the rest) --}}
        <div class="pos-split">

            {{-- Product strip — featured products, or all of them if none are
                 flagged featured (see SalesController::create()). Everything
                 else is still one search/scan away. --}}
            <div class="pos-product-grid-wrap">
                <div class="pos-product-grid" id="productGrid">
                    @foreach($gridProducts as $p)
                    <div class="prod-card {{ $p->stock_qty <= 0 ? 'out-of-stock' : '' }}"
                         data-cat="{{ $p->category_id ?? 'none' }}"
                         data-name="{{ strtolower($p->name) }}"
                         data-sku="{{ strtolower($p->barcode ?? '') }}"
                         onclick="addProductById({{ $p->id }})">
                        <div class="prod-card-name">{{ $p->name }}</div>
                        <div class="prod-card-price">KSh {{ number_format($p->selling_price, 2) }}</div>
                        <div class="prod-card-stock {{ $p->stock_qty <= 5 ? 'low' : '' }}">
                            {{ $p->stock_qty }} {{ $p->unit }}
                        </div>
                    </div>
                    @endforeach
                    @foreach($bundles as $b)
                    <div class="prod-card bundle-card" data-cat="bundle" data-name="{{ strtolower($b->name) }}" data-sku=""
                         onclick="addBundleById({{ $b->id }})">
                        <div class="prod-card-name">{{ $b->name }}</div>
                        <div class="prod-card-price">KSh {{ number_format($b->price, 2) }}</div>
                        <div class="prod-card-stock">Bundle</div>
                    </div>
                    @endforeach
                </div>
                <div id="gridEmpty" style="display:none;text-align:center;padding:32px;color:var(--color-text-muted);font-size:13px;">
                    No products match your search.
                </div>
            </div>

            {{-- Cart items --}}
            <div class="pos-cart-section">
                <div class="pos-cart-header">
                    <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--color-text-muted);">Cart</span>
                    <span id="cartCount" style="font-size:11px;color:var(--color-text-muted);"></span>
                </div>
                <div class="pos-cart-items" id="itemsBody">
                    {{-- Cart rows injected by JS --}}
                </div>
                <div id="posEmpty" class="pos-empty">
                    <div class="pos-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg></div>
                    <p>Tap a product or scan to add items</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════ RIGHT: PAYMENT ═══════════ --}}
    <div class="pos-payment" id="posPaymentPanel">

        {{-- Mobile-only sheet header: back-to-cart handle + grab bar. Hidden
             on desktop/tablet (≥901px) where .pos-payment is just the normal
             right-hand column, not a slide-up sheet — see mobile-sheet-open
             rules below. --}}
        <div class="pos-sheet-handle-row">
            <button type="button" class="pos-sheet-back" onclick="closeMobileCheckout()" aria-label="Back to cart">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                Back to cart
            </button>
        </div>

        {{-- Customer + Notes --}}
        <div class="pos-customer-row">
            <select name="customer_id" id="customerSelect" title="Customer">
                <option value="">Walk-in Customer</option>
                @foreach($customers as $c)
                    <option value="{{ $c->id }}" {{ old('customer_id') == $c->id ? 'selected' : '' }}>
                        {{ $c->name }}{{ $c->phone ? ' ('.$c->phone.')' : '' }}
                    </option>
                @endforeach
            </select>
            <input type="text" id="notesInput" placeholder="Note (optional)" value="{{ old('notes') }}"
                oninput="document.getElementById('notesHidden').value=this.value">
        </div>

        {{-- Totals --}}
        <div class="pos-totals">
            <div class="pos-totals-row">
                <span>Subtotal</span>
                <span id="displaySubtotal">KSh 0.00</span>
            </div>
            <div class="pos-totals-row">
                <span>Discount (KSh)</span>
                <div class="pos-discount-inline">
                    <input type="number" name="discount_amount" id="discountInput"
                        value="{{ old('discount_amount', 0) }}" min="0" step="0.01"
                        oninput="updateSummary()">
                </div>
            </div>
            <div class="pos-totals-row" id="couponRow" style="display:none;">
                <span style="color:var(--color-success);">Coupon</span>
                <span id="displayCoupon" style="color:var(--color-success);">- KSh 0.00</span>
            </div>
            @if($hasLoyalty)
            <div class="pos-totals-row" id="loyaltyRow" style="display:none;">
                <span style="color:#4f46e5;">Loyalty Points</span>
                <span id="displayLoyalty" style="color:#4f46e5;">- KSh 0.00</span>
            </div>
            @endif
            <div class="pos-totals-row total-row">
                <span class="pos-total-label">Total</span>
                <span class="pos-total-amount" id="displayTotal">KSh 0.00</span>
            </div>
        </div>

        {{-- Coupon code --}}
        <div class="pos-coupon-row">
            <input type="text" id="couponCodeInput" placeholder="Discount code…">
            <button type="button" class="pos-apply-btn" onclick="applyDiscountCode()">Apply</button>
        </div>
        <div id="couponMsg" style="font-size:12px; margin-top:-10px; margin-bottom:10px;"></div>

        @if($hasLoyalty)
        {{-- Loyalty redemption — only shown when business has an active loyalty program --}}
        <div id="loyaltyNone" style="background:#f8f8ff; border:1px dashed #c7d2fe; border-radius:8px; padding:4px 8px; margin-bottom:5px; font-size:11px; color:#9ca3af; text-align:center;">
            Loyalty points apply to registered customers only
        </div>
        <div id="loyaltyNoPoints" style="display:none; background:#f8f8ff; border:1px dashed #c7d2fe; border-radius:8px; padding:4px 8px; margin-bottom:5px; font-size:11px; color:#9ca3af; text-align:center;">
            <span id="loyaltyNoPointsName"></span> has no redeemable loyalty points yet
        </div>
        <div id="loyaltyPanel" style="display:none; background:var(--color-surface); border:1px solid #c7d2fe; border-radius:8px; padding:8px 10px; margin-bottom:6px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                <span style="font-size:12px; font-weight:700; color:#4f46e5;">Loyalty Points</span>
                <span id="loyaltyBalance" style="font-size:12px; color:#4f46e5; font-weight:600;"></span>
            </div>
            <div style="display:flex; gap:6px; align-items:center;">
                <input type="number" id="loyaltyRedeemInput" min="0" step="1"
                    placeholder="Points to redeem"
                    style="flex:1; padding:6px 10px; border:1px solid var(--color-border); border-radius:6px; font-size:13px;">
                <button type="button" onclick="applyLoyalty()" class="pos-apply-btn">Apply</button>
                <button type="button" onclick="clearLoyalty()" id="loyaltyClearBtn"
                    style="display:none; padding:6px 10px; border:1px solid #e5e7eb; border-radius:6px; background:#f9fafb; font-size:12px; cursor:pointer;">✕</button>
            </div>
            <div id="loyaltyMsg" style="font-size:12px; margin-top:6px;"></div>
        </div>
        @endif

        {{-- Payment method --}}
        <div class="pos-method-label">Payment Method</div>
        <div class="pos-methods">
            <button type="button" class="pos-method-btn active" data-method="cash" onclick="selectMethod('cash')">
                <span class="method-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><line x1="6" y1="9" x2="6" y2="9.01"/><line x1="18" y1="15" x2="18" y2="15.01"/></svg></span>Cash
            </button>
            <button type="button" class="pos-method-btn" data-method="mpesa" id="methodBtnMpesa" onclick="selectMethod('mpesa')">
                <span class="method-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="2" width="10" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg></span>M-Pesa
            </button>
            <button type="button" class="pos-method-btn" data-method="bank_transfer" id="methodBtnBank" onclick="selectMethod('bank_transfer')">
                <span class="method-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="21" x2="21" y2="21"/><line x1="5" y1="21" x2="5" y2="7"/><line x1="19" y1="21" x2="19" y2="7"/><polygon points="12 3 2 9 22 9"/><line x1="9" y1="21" x2="9" y2="9"/><line x1="15" y1="21" x2="15" y2="9"/></svg></span>Bank
            </button>
            <button type="button" class="pos-method-btn" data-method="credit" onclick="selectMethod('credit')">
                <span class="method-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span>Credit
            </button>
        </div>

        <div class="pos-mpesa-notice" id="mpesaRefGroup">
            @if($mpesaConfigured)
            After recording this sale you will be prompted to complete payment via <strong>M-Pesa STK Push</strong>.
            @else
            M-Pesa STK Push isn't set up. This sale will be recorded as <strong>unpaid</strong> —
            <a href="{{ route('settings.mpesa') }}" style="font-weight:700;">configure M-Pesa in Settings</a> to enable prompts.
            @endif
        </div>
        <div class="pos-mpesa-notice" id="bankNotice" style="display:none;">
            After recording this sale you'll confirm the <strong>bank transfer</strong> and enter its reference on the next step.
        </div>

        {{-- Amount paid --}}
        <div class="pos-amount-label">Amount Tendered (KSh)</div>
        <input type="number" name="paid_amount" id="paidAmountInput" class="pos-amount-input"
            value="{{ old('paid_amount', 0) }}" min="0" step="0.01"
            oninput="updateSummary()" placeholder="0">

        {{-- On-screen numeric keypad (touch input for the field above) --}}
        <div class="pos-keypad">
            <button type="button" class="keypad-btn" onclick="keypadPress('7')">7</button>
            <button type="button" class="keypad-btn" onclick="keypadPress('8')">8</button>
            <button type="button" class="keypad-btn" onclick="keypadPress('9')">9</button>
            <button type="button" class="keypad-btn" onclick="keypadPress('4')">4</button>
            <button type="button" class="keypad-btn" onclick="keypadPress('5')">5</button>
            <button type="button" class="keypad-btn" onclick="keypadPress('6')">6</button>
            <button type="button" class="keypad-btn" onclick="keypadPress('1')">1</button>
            <button type="button" class="keypad-btn" onclick="keypadPress('2')">2</button>
            <button type="button" class="keypad-btn" onclick="keypadPress('3')">3</button>
            <button type="button" class="keypad-btn" onclick="keypadPress('.')">.</button>
            <button type="button" class="keypad-btn" onclick="keypadPress('0')">0</button>
            <button type="button" class="keypad-btn keypad-backspace" onclick="keypadBackspace()">⌫</button>
        </div>

        {{-- Quick amount buttons --}}
        <div class="pos-quick-amounts" id="quickAmounts">
            <button type="button" class="pos-quick-btn" onclick="setExact()">Exact</button>
            <button type="button" class="pos-quick-btn" onclick="setRound(500)">500</button>
            <button type="button" class="pos-quick-btn" onclick="setRound(1000)">1,000</button>
            <button type="button" class="pos-quick-btn" onclick="setRound(2000)">2,000</button>
        </div>

        {{-- Change --}}
        <div class="pos-change-row" id="changeRow" style="display:none;">
            <span class="change-label">Change</span>
            <span class="pos-change-amount" id="displayChange">KSh 0.00</span>
        </div>

        {{-- Paid / Balance --}}
        <div class="pos-paid-balance">
            <div class="pos-stat-box is-paid">
                <div class="stat-label">Paid</div>
                <div class="stat-value" id="displayPaid">KSh 0.00</div>
            </div>
            <div class="pos-stat-box is-balance">
                <div class="stat-label">Balance Due</div>
                <div class="stat-value" id="displayBalance">KSh 0.00</div>
            </div>
        </div>

        {{-- Action buttons --}}
        <button type="button" class="pos-record-btn" id="submitBtn" onclick="handleFormSubmit()">
            ✓ Record Sale
        </button>
        <button type="button" class="pos-fullpaid-btn" id="fullyPaidBtn"
            onclick="document.getElementById('paidAmountInput').value=document.getElementById('totalValue').value; updateSummary();">
            Mark as Fully Paid
        </button>
    </div>

</div>{{-- .pos-shell --}}
</form>

{{-- Mobile-only sticky checkout bar — phones/small tablets (≤900px) only.
     Desktop/tablet already shows cart total + Record Sale in the always-
     visible right-hand payment column, so this stays hidden there (see
     .mobile-cart-bar display rules). Tapping it slides the existing
     .pos-payment panel up as a full-screen sheet instead of making the
     cashier scroll past the whole cart to reach payment/checkout. --}}
<button type="button" class="mobile-cart-bar is-hidden" id="mobileCartBar" onclick="openMobileCheckout()">
    <span class="mobile-cart-bar-count" id="mobileCartCount">0 items</span>
    <span class="mobile-cart-bar-total" id="mobileCartTotal">KSh 0.00</span>
    <span class="mobile-cart-bar-cta">
        Charge
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
    </span>
</button>

{{-- Nav Dock — the sidebar's main items (same role/feature gating as
     layouts/app.blade.php), surfaced as 3D "keycap" tiles since kiosk mode
     hides the real sidebar. Top-level items only, not every indented
     sub-link — and no tile for the page you're already on. Sits below the
     till itself, not squeezed into the top bar. --}}
<div class="pos-nav-dock">
    <a href="{{ route('dashboard') }}" class="pos-nav-tile">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
        Dashboard
    </a>
    @if($navUser->hasAnyRole('owner', 'overall_manager', 'manager'))
    <a href="{{ route('sales.index') }}" class="pos-nav-tile">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
        Sales
    </a>
    @endif
    @if($navUser->hasAnyRole('owner', 'overall_manager', 'manager', 'cashier'))
    <a href="{{ route('inventory.index') }}" class="pos-nav-tile">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
        Inventory
    </a>
    @endif
    @if($navUser->hasAnyRole('owner', 'overall_manager', 'manager', 'cashier') && $navBusiness?->hasFeature('customers'))
    <a href="{{ route('customers.index') }}" class="pos-nav-tile">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Customers
    </a>
    @endif
    @if($navUser->hasAnyRole('owner', 'overall_manager', 'manager', 'cashier'))
    <a href="{{ route('services.index') }}" class="pos-nav-tile">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
        Services
    </a>
    @endif
    @if($navUser->hasAnyRole('owner', 'overall_manager', 'manager'))
    @if($navBusiness?->hasFeature('expenses'))
    <a href="{{ route('expenses.index') }}" class="pos-nav-tile">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
        Expenses
    </a>
    @endif
    <a href="{{ route('reports.index') }}" class="pos-nav-tile">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
        Reports
    </a>
    @if($navBusiness?->hasFeature('suppliers'))
    <a href="{{ route('suppliers.index') }}" class="pos-nav-tile">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
        Suppliers
    </a>
    @endif
    <a href="{{ route('discounts.index') }}" class="pos-nav-tile">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
        Discounts
    </a>
    @if($navOrg?->hasFeature('payroll'))
    <a href="{{ route('staff.index') }}" class="pos-nav-tile">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/></svg>
        Staff
    </a>
    @endif
    @endif
</div>
</div>{{-- .pos-kiosk-wrap --}}

{{-- Variant modal --}}
<div id="variantModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:var(--color-surface); border-radius:12px; padding:24px; min-width:340px; max-width:460px; width:90%;">
        <h3 style="margin:0 0 10px; font-size:15px;">Select Variant</h3>
        <p id="variantProductName" style="margin:0 0 14px; color:var(--color-text-muted); font-size:13px;"></p>
        <div id="variantList"></div>
        <button type="button" class="btn btn-outline" style="margin-top:14px; width:100%;" onclick="closeVariantModal()">Cancel</button>
    </div>
</div>

{{-- Serial modal --}}
<div id="serialModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:var(--color-surface); border-radius:12px; padding:24px; min-width:340px; max-width:460px; width:90%;">
        <h3 style="margin:0 0 10px; font-size:15px;">Select Serial Number</h3>
        <p id="serialProductName" style="margin:0 0 14px; color:var(--color-text-muted); font-size:13px;"></p>
        <select id="serialSelect" class="form-control" style="margin-bottom:14px;"></select>
        <div style="display:flex; gap:8px;">
            <button type="button" class="btn btn-primary" style="flex:1;" onclick="confirmSerial()">Confirm</button>
            <button type="button" class="btn btn-outline" style="flex:1;" onclick="closeSerialModal()">Cancel</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
{{-- Must load before the inline block below — that block captures
     window.updateSummary/window.recalcRow as "_orig" and wraps them (to
     add coupon/loyalty totals and cart-count tracking on top of sales.js's
     base versions). sales.js used to be included after this inline script,
     so its own updateSummary/recalcRow definitions ran last and silently
     replaced the wrapped versions — the on-screen Total (and the "Exact"
     tendered-amount button, which reads the same hidden total field) then
     never reflected an applied coupon or loyalty discount, even though the
     sale was correctly discounted server-side. --}}
<script src="{{ asset('js/sales.js') }}?v={{ @filemtime(public_path('js/sales.js')) ?: '1' }}"></script>
<script>
var PRODUCTS            = @json($productsData);
{{-- 'items' must be included — sales.js's addRow() reads bundle.items[0].product_id
     to tag the bundle's display-only summary cart line, and crashed (TypeError,
     bundle never added to cart) on every attempt to sell a bundle without it. --}}
var BUNDLES             = {!! json_encode($bundles->map(fn($b) => ['id'=>$b->id,'name'=>$b->name.' (Bundle)','price'=>$b->price,'is_bundle'=>true,'items'=>$b->items->map(fn($i) => ['product_id'=>$i->product_id])->values()])) !!};
var LINE_DISCOUNTS      = @json($lineDiscounts);
var APPLY_DISCOUNT_URL    = "{{ route('sales.apply-discount') }}";
var LOYALTY_URL_BASE      = "{{ url('sales/customer-loyalty') }}/";
var CSRF_TOKEN            = "{{ csrf_token() }}";
var _loyaltyData          = null; // {points, value, redemption_rate, min_points, can_redeem}

var _pendingVariantProductId   = null;
var _pendingVariantRowCallback = null;
var _pendingSerialProductId    = null;
var _pendingSerialRowCallback  = null;

/* ── Payment method selector ── */
function selectMethod(method) {
    document.getElementById('paymentMethodHidden').value = method;
    document.querySelectorAll('.pos-method-btn').forEach(function(btn) {
        btn.classList.toggle('active', btn.dataset.method === method);
    });
    document.getElementById('mpesaRefGroup').style.display = (method === 'mpesa') ? 'block' : 'none';
    document.getElementById('quickAmounts').style.display  = (method === 'cash')   ? 'grid'  : 'none';
}

/* ── Quick amount helpers ── */
function setExact() {
    var total = parseFloat(document.getElementById('totalValue').value) || 0;
    document.getElementById('paidAmountInput').value = total.toFixed(2);
    updateSummary();
}
function setRound(amount) {
    document.getElementById('paidAmountInput').value = amount;
    updateSummary();
}

/* ── On-screen numeric keypad ── */
function keypadPress(char) {
    var input = document.getElementById('paidAmountInput');
    var current = (input.value === '0' || input.value === '') ? '' : String(input.value);
    if (char === '.' && current.includes('.')) return; // no double decimal point
    input.value = current + char;
    updateSummary();
}
function keypadBackspace() {
    var input = document.getElementById('paidAmountInput');
    input.value = String(input.value || '').slice(0, -1);
    updateSummary();
}

/* ── Void sale: clear the in-progress cart and reset the payment panel ── */
function voidSale() {
    var rows = document.querySelectorAll('#itemsBody .cart-row');
    if (rows.length === 0) return;
    if (!confirm('Void this sale and clear the cart? This cannot be undone.')) return;

    rows.forEach(function (r) { r.remove(); });

    document.getElementById('discountInput').value = 0;
    document.getElementById('discountIdHidden').value = '';
    document.getElementById('couponDiscountHidden').value = 0;
    document.getElementById('couponCodeInput').value = '';
    document.getElementById('couponRow').style.display = 'none';
    document.getElementById('couponMsg').innerHTML = '';
    document.getElementById('paidAmountInput').value = 0;

    // clearLoyalty() assumes the loyalty panel's elements exist, which they
    // only do when the business has an active loyalty program — guard on
    // that instead of calling it unconditionally.
    if (document.getElementById('loyaltyRow') && typeof clearLoyalty === 'function') {
        clearLoyalty();
    }

    if (typeof updateCartCount === 'function') updateCartCount();
    updateSummary();
    closeMobileCheckout();
}

/* ── Mobile checkout sheet (≤900px only — see CSS) ──────────────
   The cart/product view and the payment panel are the same markup and
   same desktop-column layout at every width; on a phone .pos-payment is
   additionally toggled into a full-screen sheet via this class instead of
   living in-flow below the cart, so checkout is one tap away from the
   sticky .mobileCartBar rather than a long scroll past every cart row. */
function openMobileCheckout() {
    var panel = document.getElementById('posPaymentPanel');
    if (!panel) return;
    panel.classList.add('mobile-sheet-open');
    document.body.classList.add('mobile-checkout-open');
    panel.scrollTop = 0;
}
function closeMobileCheckout() {
    var panel = document.getElementById('posPaymentPanel');
    if (!panel) return;
    panel.classList.remove('mobile-sheet-open');
    document.body.classList.remove('mobile-checkout-open');
}

/* ── Extended updateSummary to show change ── */
var _origUpdateSummary;
document.addEventListener('DOMContentLoaded', function() {
    var _orig = window.updateSummary;
    window.updateSummary = function() {
        if (_orig) _orig();

        // Re-derive total including loyalty discount
        var loyaltyDiscEl = document.getElementById('loyaltyDiscountHidden');
        var loyaltyDisc = loyaltyDiscEl ? (parseFloat(loyaltyDiscEl.value) || 0) : 0;
        var couponDisc  = parseFloat(document.getElementById('couponDiscountHidden').value) || 0;
        var flatDisc    = parseFloat(document.getElementById('discountInput').value) || 0;
        var subtotalText = (document.getElementById('displaySubtotal').textContent || '').replace(/[^0-9.]/g, '');
        var subtotal    = parseFloat(subtotalText) || 0;
        var newTotal    = Math.max(0, subtotal - flatDisc - couponDisc - loyaltyDisc);
        document.getElementById('totalValue').value = newTotal.toFixed(2);
        if (document.getElementById('displayTotal')) {
            document.getElementById('displayTotal').textContent = 'KSh ' + newTotal.toFixed(2);
        }
        var mobileTotal = document.getElementById('mobileCartTotal');
        if (mobileTotal) mobileTotal.textContent = 'KSh ' + newTotal.toFixed(2);

        var paid   = parseFloat(document.getElementById('paidAmountInput').value) || 0;
        var change = paid - newTotal;
        var changeRow = document.getElementById('changeRow');
        if (change > 0) {
            changeRow.style.display = 'flex';
            document.getElementById('displayChange').textContent = 'KSh ' + change.toFixed(2);
        } else {
            changeRow.style.display = 'none';
        }
        var displayPaid    = document.getElementById('displayPaid');
        var displayBalance = document.getElementById('displayBalance');
        if (displayPaid)    displayPaid.textContent    = 'KSh ' + Math.min(paid, newTotal).toFixed(2);
        if (displayBalance) displayBalance.textContent = 'KSh ' + Math.max(0, newTotal - paid).toFixed(2);

        // Empty state
        var rows   = document.getElementById('itemsBody').querySelectorAll('tr');
        var empty  = document.getElementById('posEmpty');
        if (empty) empty.style.display = rows.length === 0 ? 'block' : 'none';
    };
    // Show empty state on load
    window.updateSummary();
    @if($hasLoyalty)
    // Fetch loyalty info when customer changes
    document.getElementById('customerSelect').addEventListener('change', function() {
        fetchLoyalty(this.value);
    });
    // Show walk-in hint on load
    fetchLoyalty(document.getElementById('customerSelect').value);
    @endif
});

/* ── Discount code ── */
function applyDiscountCode() {
    var code = document.getElementById('couponCodeInput').value.trim();
    if (!code) return;
    var subtotalVal = parseFloat(document.getElementById('totalValue').value) || 0;
    var msgEl = document.getElementById('couponMsg');
    msgEl.textContent = 'Applying…';
    fetch(APPLY_DISCOUNT_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
        body: JSON.stringify({ code: code, order_amount: subtotalVal })
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            msgEl.innerHTML = '<span style="color:var(--color-danger);">' + data.error + '</span>';
        } else {
            msgEl.innerHTML = '<span style="color:var(--color-success);">' + data.message + '</span>';
            document.getElementById('discountIdHidden').value   = data.discount_id;
            document.getElementById('couponDiscountHidden').value = data.discount_amount;
            document.getElementById('couponRow').style.display  = 'flex';
            document.getElementById('displayCoupon').textContent = '- KSh ' + parseFloat(data.discount_amount).toFixed(2);
            updateSummary();
        }
    })
    .catch(() => { msgEl.innerHTML = '<span style="color:var(--color-danger);">Error applying code.</span>'; });
}

/* ── Loyalty redemption ── */
function fetchLoyalty(customerId) {
    clearLoyalty();
    var noneEl     = document.getElementById('loyaltyNone');
    var noPointsEl = document.getElementById('loyaltyNoPoints');
    var panel      = document.getElementById('loyaltyPanel');
    if (!customerId) {
        noneEl.style.display     = 'block';
        noPointsEl.style.display = 'none';
        panel.style.display      = 'none';
        _loyaltyData = null;
        return;
    }
    noneEl.style.display     = 'none';
    noPointsEl.style.display = 'none';
    panel.style.display      = 'none';
    fetch(LOYALTY_URL_BASE + customerId, {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN }
    })
    .then(r => r.json())
    .then(data => {
        _loyaltyData = data;
        if (!data.enabled) return; // program not active — show nothing
        if (!data.can_redeem) {
            // Customer has points but below minimum, or zero points
            noPointsEl.style.display = 'block';
            document.getElementById('loyaltyNoPointsName').textContent = data.customer_name;
            return;
        }
        panel.style.display = 'block';
        document.getElementById('loyaltyBalance').textContent =
            data.points.toFixed(0) + ' pts ≈ KSh ' + parseFloat(data.value).toFixed(2);
        document.getElementById('loyaltyRedeemInput').max = data.points;
        document.getElementById('loyaltyRedeemInput').placeholder = 'Max ' + data.points.toFixed(0) + ' pts';
    })
    .catch(() => {});
}

function applyLoyalty() {
    if (!_loyaltyData || !_loyaltyData.enabled) return;
    var pts     = parseFloat(document.getElementById('loyaltyRedeemInput').value) || 0;
    var msgEl   = document.getElementById('loyaltyMsg');
    if (pts <= 0) { msgEl.innerHTML = '<span style="color:var(--color-danger);">Enter points to redeem.</span>'; return; }
    if (pts > _loyaltyData.points) { msgEl.innerHTML = '<span style="color:var(--color-danger);">Not enough points. Max: ' + _loyaltyData.points.toFixed(0) + '</span>'; return; }
    if (pts < _loyaltyData.min_points) { msgEl.innerHTML = '<span style="color:var(--color-danger);">Minimum redemption: ' + _loyaltyData.min_points + ' pts</span>'; return; }
    var discount = parseFloat((pts * _loyaltyData.redemption_rate).toFixed(2));
    document.getElementById('loyaltyPointsHidden').value  = pts;
    document.getElementById('loyaltyDiscountHidden').value = discount;
    document.getElementById('loyaltyRow').style.display   = 'flex';
    document.getElementById('displayLoyalty').textContent = '- KSh ' + discount.toFixed(2);
    document.getElementById('loyaltyClearBtn').style.display = 'inline-block';
    msgEl.innerHTML = '<span style="color:#4f46e5;">' + pts.toFixed(0) + ' pts redeemed → KSh ' + discount.toFixed(2) + ' off</span>';
    updateSummary();
}

function clearLoyalty() {
    document.getElementById('loyaltyPointsHidden').value  = 0;
    document.getElementById('loyaltyDiscountHidden').value = 0;
    document.getElementById('loyaltyRow').style.display   = 'none';
    document.getElementById('loyaltyClearBtn').style.display = 'none';
    document.getElementById('loyaltyRedeemInput').value   = '';
    document.getElementById('loyaltyMsg').innerHTML        = '';
    updateSummary();
}

/* ── Variant modal ── */
function openVariantModal(productId, rowCallback) {
    var product = PRODUCTS.find(p => p.id == productId);
    if (!product || !product.variants || !product.variants.length) return false;
    _pendingVariantProductId   = productId;
    _pendingVariantRowCallback = rowCallback;
    document.getElementById('variantProductName').textContent = product.name;
    var list = document.getElementById('variantList');
    list.innerHTML = '';
    // The product itself is sellable too (its own price and stock), not only
    // its variants — otherwise the plain item shown on the tile could never
    // be added to a cart once a variant existed.
    if (parseFloat(product.stock_qty) > 0) {
        var baseBtn = document.createElement('button');
        baseBtn.type = 'button';
        baseBtn.className = 'btn btn-outline';
        baseBtn.style = 'width:100%; margin-bottom:8px; text-align:left;';
        baseBtn.innerHTML = '<strong>Standard</strong> — KSh ' + parseFloat(product.selling_price).toFixed(2) + ' (stock: ' + product.stock_qty + ')';
        baseBtn.onclick = function() { rowCallback(null, product.selling_price, ''); closeVariantModal(); };
        list.appendChild(baseBtn);
    }
    product.variants.forEach(function(v) {
        var price = v.price !== null ? v.price : product.selling_price;
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-outline';
        btn.style = 'width:100%; margin-bottom:8px; text-align:left;';
        btn.innerHTML = '<strong>' + v.name + '</strong> — KSh ' + parseFloat(price).toFixed(2) + ' (stock: ' + v.stock_qty + ')';
        btn.onclick = function() { rowCallback(v.id, price, v.name); closeVariantModal(); };
        list.appendChild(btn);
    });
    document.getElementById('variantModal').style.display = 'flex';
    return true;
}
function closeVariantModal() { document.getElementById('variantModal').style.display = 'none'; }

/* ── Serial modal ── */
function openSerialModal(productId, rowCallback) {
    var product = PRODUCTS.find(p => p.id == productId);
    if (!product || !product.serials || !product.serials.length) return false;
    _pendingSerialProductId   = productId;
    _pendingSerialRowCallback = rowCallback;
    document.getElementById('serialProductName').textContent = product.name;
    var sel = document.getElementById('serialSelect');
    sel.innerHTML = '<option value="">— Select Serial —</option>';
    product.serials.forEach(function(s) {
        var opt = document.createElement('option');
        opt.value = s.id;
        opt.textContent = s.serial_number;
        sel.appendChild(opt);
    });
    document.getElementById('serialModal').style.display = 'flex';
    return true;
}
function closeSerialModal() { document.getElementById('serialModal').style.display = 'none'; }
function confirmSerial() {
    var sel = document.getElementById('serialSelect');
    if (sel.value && _pendingSerialRowCallback) {
        _pendingSerialRowCallback(sel.value, sel.options[sel.selectedIndex].textContent);
    }
    closeSerialModal();
}

/* ── Product grid: add by ID ─── */
function addProductById(productId) {
    var p = PRODUCTS.find(function(x){ return x.id == productId; });
    if (!p) return;
    if (p.has_variants) { openVariantModal(productId, function(variantId, variantPrice, variantName){ addCartRow(p, variantPrice, variantId); }); return; }
    if (p.track_serials) { openSerialModal(productId, function(serialId){ addCartRow(p, p.selling_price, null, serialId); }); return; }
    addCartRow(p, p.selling_price);
}

function addBundleById(bundleId) {
    addRow(null, 1, null, bundleId);
}

function addCartRow(product, price, variantId, serialId) {
    // Check if already in cart — increment qty
    var existing = document.querySelector('[data-product-id="' + product.id + (variantId ? '-' + variantId : '') + '"]');
    if (existing) {
        var qtyInput = document.getElementById('qty-' + existing.dataset.rowId);
        if (qtyInput) { qtyInput.value = parseInt(qtyInput.value) + 1; recalcRow(existing.dataset.rowId); return; }
    }
    addRow(product.id, 1, price, null, variantId, serialId);
    // Flash the search bar feedback
    var fb = document.getElementById('barcodeFeedback');
    fb.textContent = '✓ ' + product.name + ' added';
    setTimeout(function(){ fb.textContent = ''; }, 1500);
}

/* ── Category filter ─── Kept as the "reset to show everything" helper for
   search (below) even though the tab UI itself was removed — btn is now
   always null when called, so this only ever resets visibility. */
function filterCategory(catId, btn) {
    document.querySelectorAll('.cat-tab').forEach(function(t){ t.classList.remove('active'); });
    if (btn) btn.classList.add('active');
    var cards = document.querySelectorAll('#productGrid .prod-card');
    var anyVisible = false;
    cards.forEach(function(c){
        var show = catId === 'all' || c.dataset.cat == catId;
        c.style.display = show ? '' : 'none';
        if (show) anyVisible = true;
    });
    document.getElementById('gridEmpty').style.display = anyVisible ? 'none' : 'block';
}

/* ── Live search ─── */
var _searchIndex = -1;
function liveSearch(query) {
    var dropdown = document.getElementById('searchDropdown');
    var q = query.trim().toLowerCase();
    if (!q) { dropdown.style.display = 'none'; filterCategory('all', null); return; }
    var matches = PRODUCTS.filter(function(p){
        return p.name.toLowerCase().includes(q) || (p.barcode && p.barcode.toLowerCase().includes(q));
    }).slice(0, 8);
    // Also filter the product grid cards
    var cards = document.querySelectorAll('#productGrid .prod-card');
    var anyVisible = false;
    cards.forEach(function(c){
        var show = c.dataset.name.includes(q) || c.dataset.sku.includes(q);
        c.style.display = show ? '' : 'none';
        if (show) anyVisible = true;
    });
    document.getElementById('gridEmpty').style.display = anyVisible ? 'none' : 'block';

    if (matches.length === 0) { dropdown.style.display = 'none'; return; }
    _searchIndex = -1;
    dropdown.innerHTML = matches.map(function(p, i){
        return '<div class="search-item" data-idx="' + i + '" data-id="' + p.id + '" onclick="pickSearchResult(' + p.id + ')">' +
            '<div><div class="search-item-name">' + p.name + '</div>' +
            '<div class="search-item-sku">' + (p.barcode || 'No barcode') + '</div></div>' +
            '<div style="text-align:right"><div class="search-item-price">KSh ' + parseFloat(p.selling_price).toFixed(2) + '</div>' +
            '<div class="search-item-stock">' + p.stock_qty + ' ' + p.unit + '</div></div>' +
            '</div>';
    }).join('');
    dropdown.style.display = 'block';
}

function pickSearchResult(productId) {
    document.getElementById('searchDropdown').style.display = 'none';
    document.getElementById('barcodeInput').value = '';
    filterCategory('all', null);
    addProductById(productId);
    document.getElementById('barcodeInput').focus();
}

function searchKeyNav(e) {
    var dropdown = document.getElementById('searchDropdown');
    var items = dropdown.querySelectorAll('.search-item');
    if (!items.length || dropdown.style.display === 'none') return;
    if (e.key === 'ArrowDown') { e.preventDefault(); _searchIndex = Math.min(_searchIndex + 1, items.length - 1); items.forEach(function(el,i){ el.classList.toggle('active', i === _searchIndex); }); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); _searchIndex = Math.max(_searchIndex - 1, 0); items.forEach(function(el,i){ el.classList.toggle('active', i === _searchIndex); }); }
    else if (e.key === 'Escape') { dropdown.style.display = 'none'; }
    // Enter handled by existing barcode logic in sales.js
}

document.addEventListener('click', function(e){
    if (!e.target.closest('#barcodeScanBar') && !e.target.closest('.search-dropdown')) {
        document.getElementById('searchDropdown').style.display = 'none';
    }
});

/* ── Cart count ── */
var _origRecalcRow = window.recalcRow;
function updateCartCount() {
    var count = document.querySelectorAll('#itemsBody .cart-row').length;
    var el = document.getElementById('cartCount');
    if (el) el.textContent = count ? count + ' item' + (count > 1 ? 's' : '') : '';
    var empty = document.getElementById('posEmpty');
    if (empty) empty.style.display = count ? 'none' : 'block';

    var mobileBar   = document.getElementById('mobileCartBar');
    var mobileCount = document.getElementById('mobileCartCount');
    if (mobileCount) mobileCount.textContent = count + ' item' + (count !== 1 ? 's' : '');
    // Nothing to check out yet — keep the bar off the screen rather than
    // showing a live "Charge" CTA for an empty cart.
    if (mobileBar) mobileBar.classList.toggle('is-hidden', count === 0);
}

/* ── Thermal Printer (Web Serial API) ── */
var _printer = null;
async function connectPrinter() {
    if (!('serial' in navigator)) {
        alert('Web Serial API not supported in this browser.\nUse Chrome or Edge and ensure the site is served over HTTPS.\n\nAlternatively, use the "Print Receipt" button after each sale to print via your OS print dialog.');
        return;
    }
    try {
        var port = await navigator.serial.requestPort();
        await port.open({ baudRate: 9600 });
        _printer = port;
        var btn = document.getElementById('printerConnectBtn');
        btn.textContent = 'Printer Connected';
        btn.classList.add('connected');
    } catch(e) {
        if (e.name !== 'NotFoundError') alert('Could not connect to printer: ' + e.message);
    }
}
</script>
{{-- sales.js itself now loads earlier, at the top of this stack — see the
     comment there. Its cache-buster comment still applies to offline-db.js
     below: it uses the same file-modification-time buster as everywhere
     else in the app so every deploy naturally busts both caches. --}}
<script src="{{ asset('js/offline-db.js') }}?v={{ @filemtime(public_path('js/offline-db.js')) ?: '1' }}"></script>
<script>
// ── Offline POS integration ────────────────────────────────────────────────

var _isOnline = navigator.onLine;

function updateOfflineUI() {
    _isOnline = navigator.onLine;
    var banner = document.getElementById('offlineBanner');
    if (banner) banner.style.display = _isOnline ? 'none' : 'flex';
    var btn = document.getElementById('submitBtn');
    if (btn) {
        btn.textContent = _isOnline ? '✓ Record Sale' : 'Save Offline';
    }
    updatePaymentMethodAvailability();
    if (_isOnline) checkAndSync();
}

/**
 * M-Pesa and Bank Transfer are both two-step flows: this form only creates
 * the sale, then a SEPARATE page/action does the actual STK push or bank
 * reference entry. The offline-sync replay never visits that second step,
 * so a sale queued offline with either method would previously sync
 * "successfully" while silently staying unpaid forever, with nothing ever
 * telling the cashier it still needs manual follow-up. Cash and Credit
 * need no live step afterward, so only these two are blocked offline.
 */
function updatePaymentMethodAvailability() {
    var mpesaBtn = document.getElementById('methodBtnMpesa');
    var bankBtn  = document.getElementById('methodBtnBank');
    if (!mpesaBtn || !bankBtn) return;

    mpesaBtn.disabled = !_isOnline;
    bankBtn.disabled  = !_isOnline;
    mpesaBtn.title = _isOnline ? '' : 'Requires an internet connection — the M-Pesa prompt can\'t be sent offline.';
    bankBtn.title  = _isOnline ? '' : 'Requires an internet connection to confirm the transfer reference.';

    var currentMethod = document.getElementById('paymentMethodHidden')?.value;
    if (!_isOnline && (currentMethod === 'mpesa' || currentMethod === 'bank_transfer')) {
        selectMethod('cash');
        var toast = document.createElement('div');
        toast.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#1e293b;color:#f8fafc;padding:12px 24px;border-radius:10px;font-size:14px;font-weight:600;z-index:9999;text-align:center;max-width:90vw;';
        toast.textContent = 'Switched to Cash — M-Pesa/Bank Transfer need an internet connection.';
        document.body.appendChild(toast);
        setTimeout(function () { toast.remove(); }, 4000);
    }
}

window.addEventListener('online',  updateOfflineUI);
window.addEventListener('offline', updateOfflineUI);
updateOfflineUI();

// Update queue count badge + failed-sales banner
async function refreshQueueCount() {
    var count = await OfflineDB.countPending();
    var el = document.getElementById('offlineQueueCount');
    if (el) el.textContent = count ? count + ' queued' : '';

    var failed = await getFailedSales();
    var banner = document.getElementById('failedSalesBanner');
    var msg    = document.getElementById('failedSalesMsg');
    if (banner) {
        if (failed.length > 0) {
            banner.style.display = 'flex';
            if (msg) msg.textContent = failed.length + ' sale' + (failed.length > 1 ? 's' : '') + ' failed to sync — action needed.';
        } else {
            banner.style.display = 'none';
        }
    }
}
refreshQueueCount();

function formatQueuedAt(iso) {
    try { return new Date(iso).toLocaleString(); } catch (e) { return iso; }
}

async function renderFailedSalesList() {
    var container = document.getElementById('failedSalesList');
    if (!container) return;
    var failed = await getFailedSales();

    if (!failed.length) {
        container.innerHTML = '<p style="text-align:center;color:#64748b;font-size:13px;padding:20px 0;">No failed sales.</p>';
        return;
    }

    container.innerHTML = failed.map(function (r) {
        var itemCount = Object.keys(r.payload).filter(function (k) {
            return /^items\[\d+\]\[product_id\]$/.test(k);
        }).length;
        return '' +
            '<div style="border:1px solid #fecaca;background:#fef2f2;border-radius:8px;padding:12px;margin-bottom:10px;">' +
                '<div style="display:flex;justify-content:space-between;font-size:12px;color:#64748b;margin-bottom:4px;">' +
                    '<span>' + formatQueuedAt(r.queued_at) + '</span>' +
                    '<span>' + itemCount + ' item' + (itemCount !== 1 ? 's' : '') + ', KSh ' + (r.payload.paid_amount || '?') + '</span>' +
                '</div>' +
                '<div style="font-size:13px;color:#991b1b;font-weight:600;margin-bottom:10px;">' + (r.error || 'Unknown error') + '</div>' +
                '<div style="display:flex;gap:8px;">' +
                    '<button type="button" class="retry-failed-btn" data-id="' + r.offline_id + '" style="flex:1;background:#16a34a;color:#fff;border:none;border-radius:6px;padding:8px;font-size:13px;font-weight:600;cursor:pointer;">Retry</button>' +
                    '<button type="button" class="discard-failed-btn" data-id="' + r.offline_id + '" style="flex:1;background:#fff;color:#b91c1c;border:1px solid #b91c1c;border-radius:6px;padding:8px;font-size:13px;font-weight:600;cursor:pointer;">Discard</button>' +
                '</div>' +
            '</div>';
    }).join('');

    container.querySelectorAll('.retry-failed-btn').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            btn.disabled = true;
            btn.textContent = 'Retrying…';
            await retryOfflineSale(btn.dataset.id);
            await syncPendingSales();
            await refreshQueueCount();
            await renderFailedSalesList();
        });
    });

    container.querySelectorAll('.discard-failed-btn').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            if (!confirm('Only discard this if you already recorded this sale another way. This cannot be undone. Continue?')) return;
            await discardOfflineSale(btn.dataset.id);
            await refreshQueueCount();
            await renderFailedSalesList();
        });
    });
}

document.getElementById('viewFailedSalesBtn')?.addEventListener('click', async function () {
    await renderFailedSalesList();
    document.getElementById('failedSalesModal').style.display = 'flex';
});
document.getElementById('closeFailedSalesModalBtn')?.addEventListener('click', function () {
    document.getElementById('failedSalesModal').style.display = 'none';
});

// ── Offline PIN — local unlock gate, never sent to the server ─────────────
// Stored as SHA-256(salt + pin) with a random per-device salt in
// localStorage. This is a convenience lock on an already-authenticated
// cached page, not a real credential — see the comments above the modal
// and lock-screen markup. A wrong PIN is throttled client-side (the only
// enforcement possible with no server reachable), not a hard barrier.

async function sha256Hex(text) {
    var data = new TextEncoder().encode(text);
    var hashBuffer = await crypto.subtle.digest('SHA-256', data);
    return Array.from(new Uint8Array(hashBuffer)).map(function (b) { return b.toString(16).padStart(2, '0'); }).join('');
}

function randomSalt() {
    var arr = new Uint8Array(16);
    crypto.getRandomValues(arr);
    return Array.from(arr).map(function (b) { return b.toString(16).padStart(2, '0'); }).join('');
}

function openOfflinePinSetup() {
    var hasPin = !!localStorage.getItem('pos_offline_pin_hash');
    document.getElementById('offlinePinCurrentState').textContent = hasPin ? 'An offline PIN is already set on this device.' : '';
    document.getElementById('offlinePinRemoveBtn').style.display = hasPin ? 'block' : 'none';
    document.getElementById('offlinePinInput1').value = '';
    document.getElementById('offlinePinInput2').value = '';
    document.getElementById('offlinePinError').style.display = 'none';
    document.getElementById('offlinePinModal').style.display = 'flex';
}

document.getElementById('offlinePinCancelBtn').addEventListener('click', function () {
    document.getElementById('offlinePinModal').style.display = 'none';
});

document.getElementById('offlinePinSaveBtn').addEventListener('click', async function () {
    var pin1 = document.getElementById('offlinePinInput1').value.trim();
    var pin2 = document.getElementById('offlinePinInput2').value.trim();
    var errEl = document.getElementById('offlinePinError');

    if (!/^\d{4,6}$/.test(pin1)) {
        errEl.textContent = 'PIN must be 4-6 digits.';
        errEl.style.display = 'block';
        return;
    }
    if (pin1 !== pin2) {
        errEl.textContent = 'PINs do not match.';
        errEl.style.display = 'block';
        return;
    }

    var salt = randomSalt();
    var hash = await sha256Hex(salt + pin1);
    localStorage.setItem('pos_offline_pin_salt', salt);
    localStorage.setItem('pos_offline_pin_hash', hash);
    localStorage.removeItem('pos_offline_pin_fails');
    localStorage.removeItem('pos_offline_pin_lockout_until');

    document.getElementById('offlinePinModal').style.display = 'none';
    var toast = document.createElement('div');
    toast.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#16a34a;color:#fff;padding:12px 24px;border-radius:10px;font-size:14px;font-weight:600;z-index:9999;';
    toast.textContent = 'Offline PIN saved for this device.';
    document.body.appendChild(toast);
    setTimeout(function () { toast.remove(); }, 3000);
});

document.getElementById('offlinePinRemoveBtn').addEventListener('click', function () {
    if (!confirm('Remove the offline PIN from this device? You will not be able to use this till offline until you set a new one (while online).')) return;
    localStorage.removeItem('pos_offline_pin_hash');
    localStorage.removeItem('pos_offline_pin_salt');
    localStorage.removeItem('pos_offline_pin_fails');
    localStorage.removeItem('pos_offline_pin_lockout_until');
    document.getElementById('offlinePinModal').style.display = 'none';
});

// ── Offline lock-screen unlock (the overlay itself is shown synchronously
// by the tiny inline script at the top of the page — this just wires up
// the actual PIN check, which needs async Web Crypto) ─────────────────────

function currentLockout() {
    var until = parseInt(localStorage.getItem('pos_offline_pin_lockout_until') || '0', 10);
    var remaining = until - Date.now();
    return remaining > 0 ? remaining : 0;
}

function updateLockUnlockButtonState() {
    var remaining = currentLockout();
    var btn   = document.getElementById('offlineLockUnlockBtn');
    var input = document.getElementById('offlineLockPinInput');
    var err   = document.getElementById('offlineLockError');
    if (remaining > 0) {
        btn.disabled = true;
        input.disabled = true;
        err.textContent = 'Too many wrong attempts. Try again in ' + Math.ceil(remaining / 1000) + 's.';
        setTimeout(updateLockUnlockButtonState, 1000);
    } else {
        btn.disabled = false;
        input.disabled = false;
        if (err.textContent.indexOf('Try again') !== -1) err.textContent = '';
    }
}

async function attemptOfflineUnlock() {
    if (currentLockout() > 0) return;

    var entered = document.getElementById('offlineLockPinInput').value.trim();
    var salt    = localStorage.getItem('pos_offline_pin_salt') || '';
    var stored  = localStorage.getItem('pos_offline_pin_hash') || '';
    var errEl   = document.getElementById('offlineLockError');

    var hash = await sha256Hex(salt + entered);

    if (hash === stored) {
        localStorage.removeItem('pos_offline_pin_fails');
        localStorage.removeItem('pos_offline_pin_lockout_until');
        document.getElementById('offlineLockScreen').style.display = 'none';
        return;
    }

    var fails = parseInt(localStorage.getItem('pos_offline_pin_fails') || '0', 10) + 1;
    localStorage.setItem('pos_offline_pin_fails', String(fails));
    document.getElementById('offlineLockPinInput').value = '';

    // Escalating client-side-only cooldown — a deterrent, not a hard
    // barrier (see the design note above the PIN section).
    var cooldownMs = fails >= 6 ? 5 * 60 * 1000 : fails >= 3 ? 30 * 1000 : 0;
    if (cooldownMs > 0) {
        localStorage.setItem('pos_offline_pin_lockout_until', String(Date.now() + cooldownMs));
        updateLockUnlockButtonState();
    } else {
        errEl.textContent = 'Incorrect PIN.';
    }
}

document.getElementById('offlineLockUnlockBtn')?.addEventListener('click', attemptOfflineUnlock);
document.getElementById('offlineLockPinInput')?.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') attemptOfflineUnlock();
});
if (document.getElementById('offlineLockScreen').style.display !== 'none') {
    updateLockUnlockButtonState();
}

// Override handleFormSubmit to intercept when offline
var _origHandleFormSubmit = window.handleFormSubmit;
window.handleFormSubmit = async function () {
    var rows = document.querySelectorAll('#itemsBody .cart-row');
    if (rows.length === 0) {
        alert('Please add at least one product.');
        return;
    }

    if (_isOnline) {
        // Normal online path
        document.getElementById('saleForm').submit();
        return;
    }

    // ── Offline path: queue in IndexedDB ──────────────────────────────────
    var btn = document.getElementById('submitBtn');
    btn.disabled    = true;
    btn.textContent = 'Saving…';

    try {
        var form    = document.getElementById('saleForm');
        var formData = new FormData(form);

        var offline_id = await queueOfflineSale(formData);

        // Show confirmation
        showOfflineQueued(offline_id);
        await refreshQueueCount();

        // Register background sync if supported
        if ('serviceWorker' in navigator && 'SyncManager' in window) {
            var reg = await navigator.serviceWorker.ready;
            await reg.sync.register('sync-sales');
        }
    } catch (err) {
        alert('Could not save sale offline: ' + err.message);
    } finally {
        btn.disabled    = false;
        btn.textContent = 'Save Offline';
    }
};

function showOfflineQueued(offline_id) {
    // Reset the POS to take another sale; show a toast
    var toast = document.createElement('div');
    toast.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#1e293b;color:#f8fafc;padding:12px 24px;border-radius:10px;font-size:14px;font-weight:600;z-index:9999;box-shadow:0 4px 16px rgba(0,0,0,.25);';
    toast.innerHTML = 'Sale saved — will sync when back online';
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);

    // Clear the cart so cashier can start next sale
    if (typeof clearCart === 'function') clearCart();
    else location.reload();
    closeMobileCheckout();
}

// ── Sync pending sales when back online ───────────────────────────────────

async function checkAndSync() {
    var count = await OfflineDB.countPending();
    if (!count) return;

    var syncBanner = document.getElementById('syncBanner');
    var syncMsg    = document.getElementById('syncBannerMsg');
    if (syncBanner) {
        syncBanner.style.display = 'flex';
        if (syncMsg) syncMsg.textContent = 'Syncing ' + count + ' offline sale' + (count > 1 ? 's' : '') + '…';
    }

    var result = await syncPendingSales();
    await refreshQueueCount();

    if (syncBanner) {
        if (result.synced > 0 || result.failed > 0) {
            // Failed records don't auto-retry — see the red "failed to sync"
            // banner (refreshed above) for those; this message just points
            // there instead of falsely promising a retry that won't happen.
            var parts = [];
            if (result.synced) parts.push(result.synced + ' sale' + (result.synced > 1 ? 's' : '') + ' synced successfully.');
            if (result.failed) parts.push(result.failed + ' failed to sync — see below.');
            if (syncMsg) syncMsg.textContent = parts.join(' ');
            setTimeout(() => { syncBanner.style.display = 'none'; }, 5000);
        } else {
            syncBanner.style.display = 'none';
        }
    }
}

// ── Listen for SW background sync messages ────────────────────────────────

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.addEventListener('message', async function(event) {
        var msg = event.data;
        if (!msg) return;

        if (msg.type === 'sale-synced') {
            await refreshQueueCount();
            var syncBanner = document.getElementById('syncBanner');
            var syncMsg    = document.getElementById('syncBannerMsg');
            if (syncBanner) {
                syncBanner.style.display = 'flex';
                if (syncMsg) syncMsg.textContent = 'Sale ' + msg.invoice_number + ' synced.';
                setTimeout(() => { syncBanner.style.display = 'none'; }, 4000);
            }
        }

        if (msg.type === 'sale-failed') {
            await refreshQueueCount();
        }
    });
}
</script>
@endpush
