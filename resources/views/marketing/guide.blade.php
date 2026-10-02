@extends('layouts.marketing')
@section('title', 'Getting Started Guide')
@section('meta_description', 'The complete Merqio POS guide — from creating your account to using every feature: POS, inventory, invoicing, payroll, and more.')

@push('styles')
<style>
.guide-shell {
    display: grid;
    grid-template-columns: 260px 1fr;
    gap: 3rem;
    max-width: 1200px;
    margin: 0 auto;
    padding: 2.5rem 1.5rem 6rem;
    align-items: start;
}
.guide-header {
    background: var(--mkt-ivory);
    border-bottom: 1px solid var(--mkt-border);
    padding: 3.5rem 1.5rem 3rem;
}
.guide-header-inner {
    max-width: 1200px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 1.2fr 0.8fr;
    gap: 2.5rem;
    align-items: center;
}
.guide-header h1 {
    font-family: var(--mkt-serif);
    font-size: clamp(1.9rem, 4vw, 2.7rem);
    font-weight: 600;
    color: var(--mkt-ink);
    margin-bottom: 0.6rem;
    letter-spacing: -0.01em;
}
.guide-header p {
    color: var(--mkt-ink-soft);
    font-size: 1.05rem;
    max-width: 560px;
    line-height: 1.6;
}
.guide-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem 1.25rem;
    margin-top: 1.1rem;
    font-size: 0.85rem;
    color: var(--mkt-ink-soft);
}
.guide-meta strong { color: var(--mkt-ink); }

/* Search box floats a small live-filter widget beside the intro copy —
   filters the sidebar TOC (and its mobile <select> equivalent) rather
   than the article text, since that's what actually gets scanned on a
   25-section reference page. */
.guide-search-card {
    background: #fff;
    border: 1px solid var(--mkt-border);
    border-radius: 12px;
    padding: 1.35rem 1.5rem;
    box-shadow: 0 24px 48px -28px rgba(28,26,23,0.18);
}
.guide-search-card label {
    display: block;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--mkt-ink-soft);
    margin-bottom: 0.5rem;
}
.guide-search-wrap { position: relative; }
.guide-search-wrap svg {
    position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
    color: var(--mkt-ink-soft); pointer-events: none;
}
#guideSearch {
    width: 100%;
    padding: 0.65rem 0.9rem 0.65rem 2.2rem;
    border: 1.5px solid var(--mkt-border);
    border-radius: 8px;
    font-size: 0.92rem;
    font-family: inherit;
    background: var(--mkt-ivory);
    color: var(--mkt-ink);
}
#guideSearch:focus { outline: none; border-color: var(--mkt-accent); background: #fff; }
.guide-search-count { font-size: 0.78rem; color: var(--mkt-ink-soft); margin-top: 0.6rem; }

@media (max-width: 900px) {
    .guide-header-inner { grid-template-columns: 1fr; }
    .guide-search-card { display: none; }
}

/* ── Quick start ─────────────────────────────────────────────── */
.guide-quickstart {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2.25rem 1.5rem 0;
}
.guide-quickstart-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.09em;
    color: var(--mkt-ink-soft);
    margin-bottom: 0.9rem;
}
.guide-quickstart-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
}
.guide-quickstart-card {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1.1rem 1.2rem;
    background: #fff;
    border: 1px solid var(--mkt-border);
    border-radius: 10px;
    text-decoration: none;
    transition: border-color 0.15s, transform 0.15s, box-shadow 0.15s;
}
.guide-quickstart-card:hover {
    border-color: var(--mkt-accent);
    transform: translateY(-2px);
    box-shadow: 0 16px 32px -20px rgba(67,56,202,0.35);
}
.guide-quickstart-icon {
    flex-shrink: 0;
    width: 36px; height: 36px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    background: var(--mkt-accent-tint);
    color: var(--mkt-accent);
}
.guide-quickstart-card strong { display: block; font-size: 0.92rem; color: var(--mkt-ink); margin-bottom: 0.15rem; }
.guide-quickstart-card span { font-size: 0.8rem; color: var(--mkt-ink-soft); line-height: 1.4; }
@media (max-width: 900px) {
    .guide-quickstart-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 560px) {
    .guide-quickstart-grid { grid-template-columns: 1fr; }
}

/* ── TOC ─────────────────────────────────────────────────────── */
.guide-toc {
    position: sticky;
    top: 90px;
    max-height: calc(100vh - 110px);
    overflow-y: auto;
    padding-right: 0.5rem;
    font-size: 0.86rem;
}
.guide-toc-title {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--mkt-accent);
    margin-bottom: 0.75rem;
}

/* Grouped TOC — eight thematic clusters instead of one flat list of 25
   items, each with its own icon so the sidebar reads as a map of the
   product, not a numbered index. */
.guide-toc-group { margin-bottom: 1.1rem; }
.guide-toc-group-label {
    display: flex; align-items: center; gap: 0.5rem;
    font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.07em; color: var(--mkt-ink); margin-bottom: 0.3rem;
}
.guide-toc-group-label svg { color: var(--mkt-accent); flex-shrink: 0; }
.guide-toc ol, .guide-toc ul { list-style: none; margin: 0; padding: 0; }
.guide-toc a {
    display: block;
    padding: 0.3rem 0.5rem;
    border-radius: 6px;
    color: var(--mkt-ink-soft);
    text-decoration: none;
    line-height: 1.35;
    transition: background 0.12s, color 0.12s;
}
.guide-toc a:hover { background: var(--mkt-accent-tint); color: var(--mkt-accent); }
.guide-toc a.active { background: var(--mkt-accent-tint); color: var(--mkt-accent); font-weight: 600; }
.guide-toc .guide-toc-sub { list-style: none; margin: 0.1rem 0 0.35rem 1.6rem; padding: 0; }
.guide-toc .guide-toc-sub a { font-size: 0.82rem; padding: 0.18rem 0.5rem; }
.guide-toc [hidden] { display: none !important; }
.guide-toc-empty {
    font-size: 0.82rem; color: var(--mkt-ink-soft); padding: 0.5rem;
    display: none;
}

.guide-toc-mobile { display: none; }

/* ── Content ─────────────────────────────────────────────────── */
.guide-content { min-width: 0; }
.guide-section {
    padding-top: 1.5rem;
    margin-top: 1.5rem;
    border-top: 1px solid var(--mkt-border);
    scroll-margin-top: 90px;
}
.guide-section:first-child { border-top: none; margin-top: 0; padding-top: 0; }
.guide-section h2 {
    font-family: var(--mkt-serif);
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--mkt-ink);
    margin-bottom: 0.9rem;
    scroll-margin-top: 90px;
}
.guide-section h3 {
    font-family: var(--mkt-serif);
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--mkt-ink);
    margin: 1.6rem 0 0.6rem;
    scroll-margin-top: 90px;
}
.guide-section h4 {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--mkt-ink);
    margin: 1.1rem 0 0.4rem;
}
.guide-section p { color: var(--mkt-ink-soft); line-height: 1.65; margin-bottom: 0.9rem; }
.guide-section ul, .guide-section ol.guide-steps {
    color: var(--mkt-ink-soft);
    line-height: 1.65;
    margin: 0 0 1rem 1.3rem;
}
.guide-section li { margin-bottom: 0.3rem; }
.guide-section strong { color: var(--mkt-ink); }
.guide-section code {
    background: var(--mkt-ivory-deep);
    border: 1px solid var(--mkt-border);
    border-radius: 4px;
    padding: 0.1rem 0.4rem;
    font-size: 0.88em;
    color: var(--mkt-accent);
}
.guide-section pre {
    background: var(--mkt-ink);
    color: #f2ede3;
    border-radius: 10px;
    padding: 0.9rem 1.1rem;
    overflow-x: auto;
    font-size: 0.85rem;
    margin-bottom: 1rem;
}
.guide-section pre code { background: none; border: none; color: inherit; padding: 0; }

.guide-table-wrap { overflow-x: auto; margin-bottom: 1.1rem; }
.guide-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
    background: #fff;
    border: 1px solid var(--mkt-border);
    border-radius: 10px;
    overflow: hidden;
}
.guide-table th, .guide-table td {
    text-align: left;
    padding: 0.6rem 0.9rem;
    border-bottom: 1px solid var(--mkt-border);
    vertical-align: top;
}
.guide-table th {
    background: var(--mkt-ivory-deep);
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--mkt-ink-soft);
}
.guide-table tr:last-child td { border-bottom: none; }

.guide-note {
    display: flex;
    gap: 0.65rem;
    background: var(--mkt-accent-tint);
    border-left: 3px solid var(--mkt-accent);
    border-radius: 8px;
    padding: 0.8rem 1rem;
    margin: 0 0 1.1rem;
    font-size: 0.9rem;
    color: var(--mkt-ink);
}
.guide-note.guide-warn {
    background: #fdf3e7;
    border-left-color: #b45309;
}
.guide-note svg { flex-shrink: 0; margin-top: 2px; color: var(--mkt-accent); }
.guide-note.guide-warn svg { color: #b45309; }

/* "Why it matters" — the business-value framing at the top of each major
   section, distinct from the how-to steps and from the tip/warning notes. */
.guide-why {
    display: flex;
    gap: 0.7rem;
    background: #fff;
    border: 1px solid var(--mkt-border);
    border-left: 3px solid var(--mkt-ink);
    border-radius: 8px;
    padding: 0.85rem 1.05rem;
    margin: 0 0 1.2rem;
    font-size: 0.92rem;
    color: var(--mkt-ink);
    line-height: 1.6;
}
.guide-why svg { flex-shrink: 0; margin-top: 3px; color: var(--mkt-ink-soft); }
.guide-why strong { color: var(--mkt-ink); }
.guide-why-label {
    display: block;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--mkt-ink-soft);
    margin-bottom: 0.2rem;
}

.guide-crumb {
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--mkt-accent);
    margin-bottom: 0.3rem;
}

/* Category eyebrow — rendered via ::before from a data-group attribute
   on the section itself, so 25 sections share one rule instead of 25
   copies of the same markup. Ties each section back to its sidebar
   group as you scroll, without needing a full icon per heading. */
.guide-section[data-group]::before {
    content: attr(data-group);
    display: block;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--mkt-accent);
    margin-bottom: 0.5rem;
}

/* ── Closing CTA — split like the rest of the site's redesigned CTAs,
   instead of one plain centered block. ─────────────────────────── */
.guide-cta {
    margin-top: 3rem;
    padding: 2.25rem;
    background: var(--mkt-ink);
    border-radius: 16px;
    color: #fff;
    display: grid;
    grid-template-columns: 1.1fr 0.9fr;
    gap: 2rem;
    align-items: center;
}
.guide-cta h3 { font-family: var(--mkt-serif); font-size: 1.4rem; margin-bottom: 0.5rem; color: #fff; }
.guide-cta p { color: rgba(255,255,255,0.7); margin-bottom: 1.2rem; }
.guide-cta-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.6rem; }
.guide-cta-list li {
    font-size: 0.85rem; color: rgba(255,255,255,0.7); line-height: 1.5;
    padding-left: 0.9rem; border-left: 2px solid rgba(255,255,255,0.25);
}
@media (max-width: 720px) {
    .guide-cta { grid-template-columns: 1fr; text-align: center; }
    .guide-cta-list { align-items: center; }
    .guide-cta-list li { border-left: none; border-top: 2px solid rgba(255,255,255,0.25); padding-left: 0; padding-top: 0.4rem; text-align: center; }
}

/* ── Back to top ─────────────────────────────────────────────── */
.guide-top-btn {
    position: fixed;
    right: 1.5rem;
    bottom: 1.5rem;
    width: 44px; height: 44px;
    border-radius: 50%;
    background: var(--mkt-accent);
    color: #fff;
    border: none;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    box-shadow: 0 12px 28px -10px rgba(67,56,202,0.5);
    opacity: 0;
    pointer-events: none;
    transform: translateY(8px);
    transition: opacity 0.2s, transform 0.2s;
    z-index: 20;
}
.guide-top-btn.visible { opacity: 1; pointer-events: auto; transform: translateY(0); }
.guide-top-btn:hover { background: var(--mkt-accent-soft); }

@media (max-width: 900px) {
    .guide-shell { grid-template-columns: 1fr; gap: 1.5rem; padding-top: 1.5rem; }
    .guide-toc { display: none; }
    .guide-toc-mobile {
        display: block;
        position: sticky;
        top: 0;
        z-index: 5;
        background: var(--mkt-ivory);
        padding: 0.75rem 0;
        border-bottom: 1px solid var(--mkt-border);
        margin-bottom: 0.5rem;
    }
    .guide-toc-mobile select {
        width: 100%;
        padding: 0.7rem 0.9rem;
        border-radius: 10px;
        border: 1px solid var(--mkt-border);
        background: #fff;
        color: var(--mkt-ink);
        font-size: 0.95rem;
        font-family: inherit;
    }
}
</style>
@endpush

@section('content')

<div class="guide-header">
    <div class="guide-header-inner">
        <div>
            <div class="guide-crumb">Documentation</div>
            <h1>The Merqio POS Getting Started Guide</h1>
            <p>Everything from creating your account to running payroll — start to finish, grouped by what you're trying to do. Search the contents, browse a quick-start card below, or jump straight to a section.</p>
            <div class="guide-meta">
                <span><strong>Version:</strong> 2026</span>
                <span><strong>25 sections</strong></span>
                <span><strong>Support:</strong> support@merqiopos.com</span>
            </div>
        </div>
        <div class="guide-search-card">
            <label for="guideSearch">Search this guide</label>
            <div class="guide-search-wrap">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="guideSearch" placeholder="e.g. barcode, payroll, M-Pesa…" autocomplete="off">
            </div>
            <div class="guide-search-count" id="guideSearchCount"></div>
        </div>
    </div>
</div>

<div class="guide-quickstart">
    <div class="guide-quickstart-label">New here? Start with these</div>
    <div class="guide-quickstart-grid">
        <a href="#s2-1" class="guide-quickstart-card">
            <span class="guide-quickstart-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
            <span><strong>Create your account</strong><span>Sign up and start your 1-month free trial</span></span>
        </a>
        <a href="#s4-1" class="guide-quickstart-card">
            <span class="guide-quickstart-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg></span>
            <span><strong>Add your first product</strong><span>Get your inventory into the system</span></span>
        </a>
        <a href="#s5-1" class="guide-quickstart-card">
            <span class="guide-quickstart-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg></span>
            <span><strong>Make your first sale</strong><span>Ring up a sale at the till</span></span>
        </a>
        <a href="#s18" class="guide-quickstart-card">
            <span class="guide-quickstart-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></span>
            <span><strong>Run your first payroll</strong><span>PAYE, NSSF and SHIF calculated for you</span></span>
        </a>
    </div>
</div>

<div class="guide-toc-mobile">
    <select id="guideJump" aria-label="Jump to section">
        <option value="">Jump to a section…</option>
        <optgroup label="Getting Started">
            <option value="s1">1. Introduction</option>
            <option value="s2">2. Getting Started</option>
            <option value="s3">3. Dashboard</option>
        </optgroup>
        <optgroup label="Selling">
            <option value="s5">5. Point of Sale (POS)</option>
            <option value="s6">6. Sales Management</option>
            <option value="s7">7. Invoicing</option>
            <option value="s8">8. Quotes and Estimates</option>
        </optgroup>
        <optgroup label="Customers & Growth">
            <option value="s9">9. Customer Management</option>
            <option value="s10">10. Loyalty Program</option>
            <option value="s11">11. Discounts and Coupons</option>
        </optgroup>
        <optgroup label="Inventory & Purchasing">
            <option value="s4">4. Inventory Management</option>
            <option value="s13">13. Suppliers and Purchase Orders</option>
            <option value="s14">14. Stock Receiving</option>
        </optgroup>
        <optgroup label="Operations">
            <option value="s12">12. Expenses</option>
            <option value="s15">15. Shift Management</option>
            <option value="s16">16. Reports</option>
        </optgroup>
        <optgroup label="Team & Payroll">
            <option value="s17">17. Staff and HR</option>
            <option value="s18">18. Payroll</option>
            <option value="s22">22. User Roles and Permissions</option>
        </optgroup>
        <optgroup label="Multi-Store & Integrations">
            <option value="s19">19. Multi-Store Management</option>
            <option value="s20">20. Payment Integrations</option>
            <option value="s24">24. REST API</option>
        </optgroup>
        <optgroup label="Account & Security">
            <option value="s21">21. Settings</option>
            <option value="s23">23. Two-Factor Authentication</option>
            <option value="s25">25. Troubleshooting</option>
        </optgroup>
    </select>
</div>

<div class="guide-shell">

    <nav class="guide-toc" aria-label="Table of contents">
        <div class="guide-toc-title">On this page</div>

        <div class="guide-toc-group" data-group>
            <div class="guide-toc-group-label"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/></svg>Getting Started</div>
            <ul>
                <li><a href="#s1">Introduction</a></li>
                <li><a href="#s2">Getting Started</a>
                    <ul class="guide-toc-sub">
                        <li><a href="#s2-1">Creating Your Account</a></li>
                        <li><a href="#s2-2">Choosing a Plan</a></li>
                        <li><a href="#s2-3">Business Profile</a></li>
                    </ul>
                </li>
                <li><a href="#s3">Dashboard</a></li>
            </ul>
        </div>

        <div class="guide-toc-group" data-group>
            <div class="guide-toc-group-label"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>Selling</div>
            <ul>
                <li><a href="#s5">Point of Sale (POS)</a>
                    <ul class="guide-toc-sub">
                        <li><a href="#s5-1">Making a Sale</a></li>
                        <li><a href="#s5-2">Searching &amp; Scanning</a></li>
                        <li><a href="#s5-3">Discounts</a></li>
                        <li><a href="#s5-4">Loyalty Points</a></li>
                        <li><a href="#s5-5">Payment Methods</a></li>
                        <li><a href="#s5-6">Printing Receipts</a></li>
                    </ul>
                </li>
                <li><a href="#s6">Sales Management</a>
                    <ul class="guide-toc-sub">
                        <li><a href="#s6-1">Sales History</a></li>
                        <li><a href="#s6-2">Recording a Payment</a></li>
                        <li><a href="#s6-3">M-Pesa STK Push</a></li>
                        <li><a href="#s6-4">Card via Pesapal</a></li>
                        <li><a href="#s6-5">Returns &amp; Refunds</a></li>
                        <li><a href="#s6-6">Cancelling a Sale</a></li>
                    </ul>
                </li>
                <li><a href="#s7">Invoicing</a></li>
                <li><a href="#s8">Quotes and Estimates</a></li>
            </ul>
        </div>

        <div class="guide-toc-group" data-group>
            <div class="guide-toc-group-label"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Customers &amp; Growth</div>
            <ul>
                <li><a href="#s9">Customer Management</a>
                    <ul class="guide-toc-sub">
                        <li><a href="#s9-1">Adding Customers</a></li>
                        <li><a href="#s9-2">Credit Accounts</a></li>
                        <li><a href="#s9-3">Customer Portal</a></li>
                    </ul>
                </li>
                <li><a href="#s10">Loyalty Program</a></li>
                <li><a href="#s11">Discounts and Coupons</a></li>
            </ul>
        </div>

        <div class="guide-toc-group" data-group>
            <div class="guide-toc-group-label"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>Inventory &amp; Purchasing</div>
            <ul>
                <li><a href="#s4">Inventory Management</a>
                    <ul class="guide-toc-sub">
                        <li><a href="#s4-1">Adding Products</a></li>
                        <li><a href="#s4-2">Variants</a></li>
                        <li><a href="#s4-3">Bundles</a></li>
                        <li><a href="#s4-4">Categories</a></li>
                        <li><a href="#s4-5">Barcode Scanning</a></li>
                        <li><a href="#s4-6">Bulk Import</a></li>
                        <li><a href="#s4-7">Stock Adjustments</a></li>
                        <li><a href="#s4-8">Low Stock Alerts</a></li>
                    </ul>
                </li>
                <li><a href="#s13">Suppliers and Purchase Orders</a></li>
                <li><a href="#s14">Stock Receiving</a></li>
            </ul>
        </div>

        <div class="guide-toc-group" data-group>
            <div class="guide-toc-group-label"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>Operations</div>
            <ul>
                <li><a href="#s12">Expenses</a></li>
                <li><a href="#s15">Shift Management</a></li>
                <li><a href="#s16">Reports</a></li>
            </ul>
        </div>

        <div class="guide-toc-group" data-group>
            <div class="guide-toc-group-label"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>Team &amp; Payroll</div>
            <ul>
                <li><a href="#s17">Staff and HR</a></li>
                <li><a href="#s18">Payroll</a></li>
                <li><a href="#s22">User Roles and Permissions</a></li>
            </ul>
        </div>

        <div class="guide-toc-group" data-group>
            <div class="guide-toc-group-label"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg>Multi-Store &amp; Integrations</div>
            <ul>
                <li><a href="#s19">Multi-Store Management</a></li>
                <li><a href="#s20">Payment Integrations</a></li>
                <li><a href="#s24">REST API</a></li>
            </ul>
        </div>

        <div class="guide-toc-group" data-group>
            <div class="guide-toc-group-label"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>Account &amp; Security</div>
            <ul>
                <li><a href="#s21">Settings</a></li>
                <li><a href="#s23">Two-Factor Authentication</a></li>
                <li><a href="#s25">Troubleshooting</a></li>
            </ul>
        </div>

        <div class="guide-toc-empty" id="guideTocEmpty">No sections match "<span id="guideTocEmptyTerm"></span>".</div>
    </nav>

    <div class="guide-content">

        {{-- ═══════════ 1. INTRODUCTION ═══════════ --}}
        <section class="guide-section" id="s1" data-group="Getting Started">
            <h2>1. Introduction</h2>
            <p>Merqio POS is a complete business management platform built specifically for Kenyan small and medium enterprises. It brings together everything you need to run your business into a single, easy-to-use system:</p>
            <ul>
                <li><strong>Point of Sale</strong> — fast, touch-friendly POS with barcode scanning and thermal receipt printing</li>
                <li><strong>Inventory</strong> — real-time stock tracking with low-stock alerts</li>
                <li><strong>Invoicing</strong> — professional PDF invoices with M-Pesa and card payment collection</li>
                <li><strong>Customers</strong> — loyalty points, credit accounts, purchase history</li>
                <li><strong>Payroll</strong> — PAYE, NSSF, and SHIF calculations with P9 forms for KRA</li>
                <li><strong>M-Pesa</strong> — receive payments directly into your own business M-Pesa account (not through us)</li>
                <li><strong>Multi-store</strong> — manage multiple branches from one account</li>
            </ul>
            <p>Everything is cloud-based — no installation required. Access it from any browser on your phone, tablet, or computer.</p>
        </section>

        {{-- ═══════════ 2. GETTING STARTED ═══════════ --}}
        <section class="guide-section" id="s2" data-group="Getting Started">
            <h2>2. Getting Started</h2>
            <div class="guide-why">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0 0 12 2z"/></svg>
                <span><span class="guide-why-label">Why it matters</span>Everything downstream — receipts, invoices, VAT calculations, payroll — pulls from the business profile and plan you set up here. A wrong phone number on your profile means customers can't reach you from an invoice; skipping VAT registration when you should have it means every past sale needs correcting later. Ten minutes now saves hours of cleanup later.</span>
            </div>

            <h3 id="s2-1">2.1 Creating Your Account</h3>
            <ol class="guide-steps">
                <li>Go to <a href="{{ route('home') }}">merqiopos.com</a> and click <strong>Get Started</strong> or <strong>Register</strong></li>
                <li>Fill in your full name, email address, a password (minimum 8 characters), and your business/organization name</li>
                <li>Click <strong>Create Account</strong></li>
                <li>You'll be logged in immediately and placed on a <strong>1-month free trial</strong> of the Solo plan</li>
            </ol>
            <div class="guide-note">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <span>Your trial includes full access to all Solo-plan features — M-Pesa sales payments, customer management, invoicing, basic reports, and basic payroll — no credit card required.</span>
            </div>

            <h3 id="s2-2">2.2 Choosing a Subscription Plan</h3>
            <p>After your trial, go to <strong>Settings → Subscription</strong> to pick a plan.</p>
            <div class="guide-table-wrap">
                <table class="guide-table">
                    <thead><tr><th>Plan</th><th>Monthly Price</th><th>Best For</th></tr></thead>
                    <tbody>
                        <tr><td>Solo</td><td>KSh 999</td><td>Single shop, up to 3 staff</td></tr>
                        <tr><td>Growth</td><td>KSh 2,999</td><td>Up to 3 branches, 15 staff, full payroll</td></tr>
                        <tr><td>Enterprise</td><td>KSh 5,999</td><td>Unlimited branches and staff, API access</td></tr>
                    </tbody>
                </table>
            </div>
            <p><strong>To subscribe:</strong></p>
            <ol class="guide-steps">
                <li>Go to <strong>Settings → Subscription</strong></li>
                <li>Click <strong>Pay with Card / Paystack</strong> on your chosen plan — you'll be redirected to a secure Paystack page to pay by Visa, Mastercard, or M-Pesa</li>
                <li>Or click <strong>Pay via M-Pesa</strong> to receive an STK Push prompt on your phone</li>
                <li>Once payment is confirmed, your plan activates immediately</li>
            </ol>
            <p>Subscriptions last 30 days from the payment date and renew manually — you'll get a reminder email 3 days before expiry.</p>

            <h3 id="s2-3">2.3 Setting Up Your Business Profile</h3>
            <p>Complete your business profile so receipts, invoices, and reports are accurate.</p>
            <ol class="guide-steps">
                <li>Go to <strong>Settings → Business Profile</strong></li>
                <li>Fill in business name, phone, email, physical address, logo (PNG/JPG, ~400×400px, max 2MB), and currency (defaults to KSh)</li>
                <li>Click <strong>Save Business Profile</strong></li>
            </ol>
            <h4>VAT Registration (if applicable)</h4>
            <ol class="guide-steps">
                <li>Go to <strong>Settings → VAT Settings</strong></li>
                <li>Toggle <strong>VAT Registered</strong> on</li>
                <li>Enter your <strong>KRA PIN</strong> and <strong>VAT registration number</strong></li>
                <li>Set your <strong>VAT rate</strong> (standard is 16%)</li>
                <li>Save — future sales and invoices calculate and display VAT automatically</li>
            </ol>
        </section>

        {{-- ═══════════ 3. DASHBOARD ═══════════ --}}
        <section class="guide-section" id="s3" data-group="Getting Started">
            <h2>3. Dashboard</h2>
            <div class="guide-why">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0 0 12 2z"/></svg>
                <span><span class="guide-why-label">Why it matters</span>Most business problems are cheapest to fix the day they happen — a slow morning, a product that's about to run out, an expense spike. The dashboard puts all of that in front of you before you even open a report, so you catch it on day one instead of at month-end when the damage is already done.</span>
            </div>
            <p>The Dashboard is your business at a glance:</p>
            <div class="guide-table-wrap">
                <table class="guide-table">
                    <thead><tr><th>Card</th><th>What it shows</th></tr></thead>
                    <tbody>
                        <tr><td>Today's Revenue</td><td>Total sales value for today</td></tr>
                        <tr><td>Today's Sales</td><td>Number of transactions today</td></tr>
                        <tr><td>Monthly Revenue</td><td>Sales for the current calendar month</td></tr>
                        <tr><td>Stock Alerts</td><td>Products at or below reorder level</td></tr>
                        <tr><td>Recent Sales</td><td>Last 10 sales with status badges</td></tr>
                        <tr><td>Revenue Chart</td><td>30-day sales trend</td></tr>
                        <tr><td>Top Products</td><td>Best-selling products this month</td></tr>
                        <tr><td>Expense Summary</td><td>Expenses posted this month</td></tr>
                    </tbody>
                </table>
            </div>
            <p>The dashboard reflects the <strong>currently active store</strong>. Managing multiple branches? Use the store switcher in the sidebar to switch.</p>
        </section>

        {{-- ═══════════ 4. INVENTORY ═══════════ --}}
        <section class="guide-section" id="s4" data-group="Inventory &amp; Purchasing">
            <h2>4. Inventory Management</h2>
            <div class="guide-why">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0 0 12 2z"/></svg>
                <span><span class="guide-why-label">Why it matters</span>Inventory is usually the single biggest chunk of a small business's cash — money sitting on shelves instead of in the bank. Get it wrong in either direction and it costs you: too little stock and you turn away paying customers; too much and cash is tied up in things that aren't selling. Accurate buying prices here are also what makes your profit numbers (§16) actually mean something, rather than just guesswork.</span>
            </div>

            <h3 id="s4-1">4.1 Adding Products</h3>
            <p>Go to <strong>Inventory → Products → Add Product</strong>.</p>
            <div class="guide-table-wrap">
                <table class="guide-table">
                    <thead><tr><th>Field</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td>Product Name</td><td>Required — the display name used everywhere</td></tr>
                        <tr><td>SKU</td><td>Leave blank to auto-generate</td></tr>
                        <tr><td>Barcode</td><td>EAN-13, UPC, or Code 128 — scan directly into this field</td></tr>
                        <tr><td>Category / Sub-category</td><td>Group products for filtering and reports (one level of sub-categories supported)</td></tr>
                        <tr><td>Brand</td><td>Optional</td></tr>
                        <tr><td>Selling Price</td><td>The price charged to customers</td></tr>
                        <tr><td>Buying / Cost Price</td><td>Used for profit calculations — not shown to customers</td></tr>
                        <tr><td>VAT Rate</td><td>Blank uses your business default; set to 0 for VAT-exempt items</td></tr>
                        <tr><td>Stock Quantity</td><td>Opening stock on hand</td></tr>
                        <tr><td>Reorder Level</td><td>You're alerted when stock falls to this number</td></tr>
                        <tr><td>Sell Unit / Buy Unit</td><td>e.g. sell by "piece" but buy by the "carton" — set how many sell-units are in one buy-unit; stock is always tracked in the sell unit</td></tr>
                        <tr><td>Image / Gallery</td><td>Shown in the POS grid and the online store</td></tr>
                        <tr><td>Featured / Hide in POS / Hide in Shop</td><td>Visibility toggles</td></tr>
                    </tbody>
                </table>
            </div>
            <p>Click <strong>Save Product</strong>. It appears immediately in your POS and inventory list.</p>

            <h3 id="s4-2">4.2 Product Variants</h3>
            <p>Use variants when a product comes in different sizes, colours, or configurations — each variant can have its own price and stock level.</p>
            <ol class="guide-steps">
                <li>Open the product → scroll to <strong>Variants</strong></li>
                <li>Click <strong>Add Variant</strong>, enter a name (e.g. "500ml", "Red / Size L"), price, and stock</li>
                <li>Repeat for each variant</li>
            </ol>
            <p>At the POS, tapping a product with variants prompts the cashier to pick one before it's added to the cart.</p>

            <h3 id="s4-3">4.3 Product Bundles</h3>
            <p>Bundles let you sell a group of products together at a combined price.</p>
            <ol class="guide-steps">
                <li>Go to <strong>Inventory → Bundles → Create Bundle</strong></li>
                <li>Name it (e.g. "School Pack"), set the bundle price</li>
                <li>Add component products and their quantities, then save</li>
            </ol>
            <p>Selling a bundle automatically deducts stock from each component product; the receipt shows the bundle name and price.</p>

            <h3 id="s4-4">4.4 Categories</h3>
            <ol class="guide-steps">
                <li>Go to <strong>Inventory → Categories</strong></li>
                <li>Click <strong>Add Category</strong>, name it (e.g. "Beverages")</li>
                <li>Optionally nest it under a parent category (one level of sub-categories)</li>
            </ol>
            <p>Products without a category are listed under "Uncategorised".</p>

            <h3 id="s4-5">4.5 Barcode Scanning</h3>
            <p>Two input modes are supported:</p>
            <p><strong>USB or Bluetooth Scanner (recommended)</strong> — scanners behave like a keyboard: scanning types the code into the active field, so in the POS the product is found and added instantly. Not found? An error is shown and you can add it manually.</p>
            <p><strong>Camera (Chrome/Edge only)</strong> — click the camera icon and point your device camera at the barcode; it's read automatically via the browser's BarcodeDetector API. Not supported in Safari or Firefox.</p>
            <div class="guide-note">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <span>Supported formats: EAN-13, EAN-8, UPC-A, UPC-E, Code 128, Code 39, QR Code.</span>
            </div>

            <h3 id="s4-6">4.6 Bulk Import</h3>
            <ol class="guide-steps">
                <li>Go to <strong>Inventory → Import</strong>, download the template (CSV or Excel)</li>
                <li>Fill it in — required columns are <code>name</code> and <code>price</code>; optional columns include <code>sku</code>, <code>barcode</code>, <code>cost_price</code>, <code>stock_qty</code>, <code>reorder_level</code>, <code>category</code>, <code>unit</code></li>
                <li>Upload and click <strong>Import</strong> — it runs in the background; refresh after a few seconds to see results</li>
            </ol>
            <p>Rows with errors are skipped and listed so you can fix and re-upload them. For 1,000+ products, split into batches of 500 rows.</p>

            <h3 id="s4-7">4.7 Stock Adjustments</h3>
            <p>Use these for stock changes that aren't from a sale or purchase: <strong>Addition</strong> (found/returned stock), <strong>Write-off</strong> (damaged/expired/lost), <strong>Damage</strong>, <strong>Correction</strong> (fixing a count error), or <strong>Return In</strong>. Go to <strong>Inventory → Stock Adjustments → New Adjustment</strong>, pick the product, type, quantity, and a note explaining why. Every adjustment records before/after quantities and who made it — visible in the Audit Log.</p>

            <h3 id="s4-8">4.8 Low Stock Alerts</h3>
            <p>An email alert is sent daily at 4:00 AM Nairobi time listing every product at or below its reorder level. Update the <strong>Reorder Level</strong> field on a product to change its threshold.</p>
        </section>

        {{-- ═══════════ 5. POS ═══════════ --}}
        <section class="guide-section" id="s5" data-group="Selling">
            <h2>5. Point of Sale (POS)</h2>
            <div class="guide-why">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0 0 12 2z"/></svg>
                <span><span class="guide-why-label">Why it matters</span>The till is where the business actually makes money, and it's also where small mistakes compound fastest — a wrong price typed in, a sale never recorded, cash that doesn't match what's in the drawer. Every sale made here automatically updates stock and feeds every report and the dashboard, so a fast, accurate checkout isn't just about the customer waiting in line — it's the source of truth for the rest of the system.</span>
            </div>

            <h3 id="s5-1">5.1 Making a Sale</h3>
            <ol class="guide-steps">
                <li>Open <strong>New Sale</strong> from the home screen or sidebar</li>
                <li>Add products (see 5.2) — the left side is the product strip and cart, the right side is checkout</li>
                <li>Optionally pick a <strong>customer</strong> (needed for loyalty points and credit sales)</li>
                <li>Apply discounts if needed (5.3)</li>
                <li>Choose the <strong>payment method</strong>, enter the <strong>amount received</strong></li>
                <li>Click <strong>Record Sale</strong></li>
            </ol>
            <p>The sale is recorded, stock is deducted, a receipt is shown/printed, and the till resets for the next transaction.</p>
            <div class="guide-note">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <span>The till itself runs full-screen, without the usual sidebar/menu around it. A row of quick-access tiles at the bottom (Dashboard, Inventory, Customers, Reports, and more depending on your role) gets you to everything else without leaving the sale in progress.</span>
            </div>

            <h3 id="s5-2">5.2 Searching and Scanning Products</h3>
            <p>Tap a product card to add it (variants prompt a picker first); type in the search box for live filtering by name or SKU (arrow keys + Enter to pick from the dropdown); or scan a barcode — scanning the same code again just bumps the quantity. Click <strong>Connect Printer</strong> to pair a USB thermal printer (Chrome/Edge, Web Serial API) so receipts print automatically.</p>

            <h3 id="s5-3">5.3 Applying Discounts</h3>
            <p><strong>Order-level:</strong> enter a percentage or flat amount in the cart's Discount field. <strong>Coupon code:</strong> click Apply and enter the code — it's shown separately in the order summary.</p>

            <h3 id="s5-4">5.4 Loyalty Points</h3>
            <p>If your business has an active loyalty program: select a registered customer to see their points balance and KSh value, earn points automatically once the sale is paid, or redeem points to knock money off the total before payment. Walk-in customers neither earn nor redeem points.</p>

            <h3 id="s5-5">5.5 Payment Methods</h3>
            <div class="guide-table-wrap">
                <table class="guide-table">
                    <thead><tr><th>Method</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td>Cash</td><td>Enter amount received; change due is shown</td></tr>
                        <tr><td>M-Pesa (STK Push)</td><td>Customer gets a prompt on their phone; the sale auto-completes on confirmation</td></tr>
                        <tr><td>Bank Transfer</td><td>Enter the bank reference number</td></tr>
                        <tr><td>Credit (Pay Later)</td><td>Recorded as unpaid, added to the customer's outstanding balance</td></tr>
                    </tbody>
                </table>
            </div>
            <p><strong>Partial payments:</strong> enter less than the full amount — the sale is saved as <code>partial</code> and the balance is tracked. Record the rest later from the sale's detail page.</p>

            <h3 id="s5-6">5.6 Printing Receipts</h3>
            <p>Connect a thermal printer (any ESC/POS printer — Epson TM series, Xprinter, etc.) via the POS's Connect Printer button; receipts print automatically after each sale. Or open a sale and use Print Receipt (A4) / Download PDF Invoice / Send Receipt by email.</p>
        </section>

        {{-- ═══════════ 6. SALES MANAGEMENT ═══════════ --}}
        <section class="guide-section" id="s6" data-group="Selling">
            <h2>6. Sales Management</h2>
            <div class="guide-why">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0 0 12 2z"/></svg>
                <span><span class="guide-why-label">Why it matters</span>A sale isn't finished the moment it's rung up — payments come in late, customers ask for refunds, mistakes get made. Being able to find any past sale, chase down what's still owed, and correctly reverse an error (rather than just deleting it and losing the trail) is what keeps your records trustworthy enough to make decisions from, and defensible if a customer or KRA ever questions a transaction.</span>
            </div>

            <h3 id="s6-1">6.1 Viewing Sales History</h3>
            <p>Go to <strong>Sales</strong> to see every past transaction — filterable by date range, payment status, sale status, customer, payment method, or cashier. Click any sale for the full detail: items, payment history, VAT breakdown, and available actions.</p>

            <h3 id="s6-2">6.2 Recording a Payment on a Sale</h3>
            <p>For a sale with a balance due, open it, click <strong>Record Payment</strong>, choose the method and amount, and confirm. The balance decreases and the sale is marked <strong>paid</strong> once it's fully settled.</p>

            <h3 id="s6-3">6.3 M-Pesa STK Push Payment</h3>
            <p>On the payment page, enter the customer's phone, adjust the amount if it's a partial payment, and click <strong>Send STK Push</strong>. Their phone rings with a payment prompt; once they enter their PIN, the page detects it automatically — no manual step needed. If they instead pay directly via Paybill/Till, that's auto-detected too, as long as C2B URLs are registered (see §20.1) and they used the invoice number as the payment reference.</p>

            <h3 id="s6-4">6.4 Card Payment via Pesapal</h3>
            <p>Click <strong>Pay by Card via Pesapal</strong> on the payment page — the customer is redirected to Pesapal's hosted checkout (Visa, Mastercard, Airtel Money) and returned once paid. Pesapal payments go directly to your business's own Pesapal account, not through Merqio POS.</p>

            <h3 id="s6-5">6.5 Sale Returns and Refunds</h3>
            <p>Open the sale, click <strong>Process Return</strong>, choose which items and quantities, whether they go back into stock (<strong>Restock</strong>) or are written off, and how the customer is refunded (cash, M-Pesa, or store credit). A return record is created with its own reference number, stock and balances update accordingly.</p>

            <h3 id="s6-6">6.6 Cancelling a Sale</h3>
            <p>Open the sale and click <strong>Cancel Sale</strong>. This restores stock, generates a credit note for anything already paid, reverses any loyalty points earned, and reduces the customer's balance if it was a credit sale. Cancelled sales stay in history — they can't be deleted.</p>
        </section>

        {{-- ═══════════ 7. INVOICING ═══════════ --}}
        <section class="guide-section" id="s7" data-group="Selling">
            <h2>7. Invoicing</h2>
            <div class="guide-why">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0 0 12 2z"/></svg>
                <span><span class="guide-why-label">Why it matters</span>Businesses that sell to other businesses rarely get paid on the spot — a proper invoice with clear terms is what actually gets you paid, and on time. It's also your paper trail: a professional PDF invoice looks more credible to a customer (and to KRA) than a WhatsApp message asking for money.</span>
            </div>
            <p>Invoices are formal billing documents (unlike sales receipts, which are immediate). Go to <strong>Invoices → New Invoice</strong>, pick the customer, set issue/due dates, add line items, and save as draft or send right away — the invoice number is auto-generated.</p>
            <p><strong>Sending:</strong> open the invoice and click <strong>Send Invoice</strong> to email it as a PDF; you can also download and share it yourself.</p>
            <p><strong>Recording payments:</strong> open the invoice, click <strong>Record Payment</strong>, enter amount/method/reference. Partial payments show as <strong>Partially Paid</strong> until the balance reaches zero.</p>
            <p><strong>Recurring invoices:</strong> set a customer, items, and a frequency (weekly/monthly/quarterly/yearly) with a start (and optional end) date — a new invoice is generated automatically on each due date, and you can review, edit, or pause the schedule any time.</p>
        </section>

        {{-- ═══════════ 8. QUOTES ═══════════ --}}
        <section class="guide-section" id="s8" data-group="Selling">
            <h2>8. Quotes and Estimates</h2>
            <div class="guide-why">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0 0 12 2z"/></svg>
                <span><span class="guide-why-label">Why it matters</span>Giving a customer a written quote — rather than a verbal number — makes negotiations faster and protects you if a price gets disputed later. Because quotes don't touch stock, you can send out as many as you like without ever risking a double-sale of the same items.</span>
            </div>
            <p>Create a quote before committing to a sale — useful when a customer wants a price estimate first. Go to <strong>Quotes → New Quote</strong>, fill in customer/items/notes, save, and optionally email it as a PDF. To convert one to a real sale, open it and click <strong>Convert to Sale</strong> — the POS opens with everything pre-loaded, ready for payment. Quotes never touch stock until they're converted.</p>
        </section>

        {{-- ═══════════ 9. CUSTOMERS ═══════════ --}}
        <section class="guide-section" id="s9" data-group="Customers &amp; Growth">
            <h2>9. Customer Management</h2>
            <div class="guide-why">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0 0 12 2z"/></svg>
                <span><span class="guide-why-label">Why it matters</span>Repeat customers are far cheaper to keep than new ones are to find, and you can't build that relationship if you have no record of who's bought from you before. Credit accounts, done with a real limit and a paper trail, let you offer the flexibility that wins loyal customers — without the limit, "buy now pay later" quietly turns into money you'll never see again.</span>
            </div>

            <h3 id="s9-1">9.1 Adding Customers</h3>
            <p>Go to <strong>Customers → Add Customer</strong> and fill in name (required), phone (used for M-Pesa matching and loyalty), email, address, and a credit limit. You can also add one on the fly at the POS via the customer dropdown's <strong>+ New Customer</strong>.</p>

            <h3 id="s9-2">9.2 Credit Accounts</h3>
            <p>Let trusted customers buy now, pay later: select <strong>Credit (Pay Later)</strong> at checkout and the amount is added to their outstanding balance. Set a <strong>credit limit</strong> on their profile — the cashier is warned if a purchase would exceed it. Open a customer's profile and click <strong>View Statement</strong> for a full running-balance history you can download as a PDF.</p>

            <h3 id="s9-3">9.3 Customer Portal</h3>
            <p>Turn on <strong>Settings → Business Profile → Enable Customer Portal</strong> and share <code>https://merqiopos.com/portal/login</code> with your customers — they log in with their registered email and a one-time code to view their invoices, balance, and transaction history themselves.</p>
        </section>

        {{-- ═══════════ 10. LOYALTY ═══════════ --}}
        <section class="guide-section" id="s10" data-group="Customers &amp; Growth">
            <h2>10. Loyalty Program</h2>
            <div class="guide-why">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0 0 12 2z"/></svg>
                <span><span class="guide-why-label">Why it matters</span>It costs far less to sell to someone who's already bought from you than to attract a stranger. A points program gives customers a reason to come back to you specifically instead of a competitor next door selling the same thing at a similar price.</span>
            </div>
            <p>Go to <strong>Settings → Loyalty Program</strong>, switch it on, and set points-per-shilling, a redemption rate (e.g. KSh 1 off per 10 points), and a minimum-points-to-redeem. Once active, any registered customer's points balance appears automatically at the POS, earns after each paid sale, and can be redeemed toward a future purchase. It's entirely optional — no loyalty UI appears anywhere until you turn it on.</p>
        </section>

        {{-- ═══════════ 11. DISCOUNTS & COUPONS ═══════════ --}}
        <section class="guide-section" id="s11" data-group="Customers &amp; Growth">
            <h2>11. Discounts and Coupons</h2>
            <div class="guide-why">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0 0 12 2z"/></svg>
                <span><span class="guide-why-label">Why it matters</span>A discount you control and can turn off is a marketing tool; a discount every cashier makes up on the spot is just lost revenue. Pre-defined discounts and coupon codes let you run promotions, move slow-selling stock, or reward specific customers — while still tracking exactly how much each one actually cost you.</span>
            </div>
            <p><strong>Discounts</strong> are reusable rules cashiers can apply at the till: go to <strong>Discounts → New Discount</strong>, name it, set type (percentage/fixed), value, optional start/end dates, minimum purchase, and usage limit.</p>
            <p><strong>Coupons</strong> are one-off or limited-use codes you hand out to customers: create one under <strong>Coupons → New Coupon</strong> with a code (e.g. <code>WELCOME20</code>), then customers redeem it at checkout via <strong>Apply Coupon</strong>.</p>
        </section>

        {{-- ═══════════ 12. EXPENSES ═══════════ --}}
        <section class="guide-section" id="s12" data-group="Operations">
            <h2>12. Expenses</h2>
            <div class="guide-why">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0 0 12 2z"/></svg>
                <span><span class="guide-why-label">Why it matters</span>Revenue tells you how much came in; it says nothing about whether you actually made money. A business can have great sales and still be losing cash every month if rent, salaries, and transport aren't tracked properly — expenses are the other half of the profit picture, and the whole point of §16's Profit &amp; Loss report.</span>
            </div>
            <p>Go to <strong>Expenses → Add Expense</strong> and record the date, amount, category (Rent, Electricity, Salaries, Transport, etc.), description, payment method, and optionally a receipt photo. Expenses reduce gross profit in your Profit &amp; Loss report. Manage categories at <strong>Expenses → Categories</strong> to match how your business is organized.</p>
        </section>

        {{-- ═══════════ 13. SUPPLIERS & PO ═══════════ --}}
        <section class="guide-section" id="s13" data-group="Inventory &amp; Purchasing">
            <h2>13. Suppliers and Purchase Orders</h2>
            <div class="guide-why">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0 0 12 2z"/></svg>
                <span><span class="guide-why-label">Why it matters</span>A written purchase order — not a phone call you half-remember — is what protects you when a supplier delivers less than promised, or the wrong items. It also gives you a clean history to negotiate better terms once a supplier can see you're a reliable, organized buyer.</span>
            </div>
            <p>Add suppliers under <strong>Suppliers → Add Supplier</strong> with their contact details. Create a purchase order via <strong>Purchase Orders → New PO</strong> — pick the supplier, add items/quantities, set an expected delivery date, and save as draft or submit.</p>
            <div class="guide-table-wrap">
                <table class="guide-table">
                    <thead><tr><th>PO Status</th><th>Meaning</th></tr></thead>
                    <tbody>
                        <tr><td>Draft</td><td>Created but not yet sent to supplier</td></tr>
                        <tr><td>Submitted</td><td>Sent — awaiting delivery</td></tr>
                        <tr><td>Partially Received</td><td>Some items received, more to come</td></tr>
                        <tr><td>Complete</td><td>All items received</td></tr>
                        <tr><td>Cancelled</td><td>PO cancelled</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        {{-- ═══════════ 14. STOCK RECEIVING ═══════════ --}}
        <section class="guide-section" id="s14" data-group="Inventory &amp; Purchasing">
            <h2>14. Stock Receiving</h2>
            <div class="guide-why">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0 0 12 2z"/></svg>
                <span><span class="guide-why-label">Why it matters</span>Stock quantities are only as accurate as the moment goods actually arrive — recording receipts properly (including catching short deliveries against what was ordered) is what keeps your inventory numbers honest, instead of drifting further from reality every week.</span>
            </div>
            <p><strong>Against a PO:</strong> open it and click <strong>Receive Stock</strong>, enter the quantities actually delivered (which can differ from what was ordered) — stock and PO status update automatically. <strong>Standalone (no PO):</strong> go to <strong>Inventory → Stock Receives → New Receive</strong>, pick the supplier and items. Every receive gets its own reference number and shows up in the audit log.</p>
        </section>

        {{-- ═══════════ 15. SHIFTS ═══════════ --}}
        <section class="guide-section" id="s15" data-group="Operations">
            <h2>15. Shift Management</h2>
            <div class="guide-why">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0 0 12 2z"/></svg>
                <span><span class="guide-why-label">Why it matters</span>Cash is the easiest thing in a business to lose track of — through honest mistakes or otherwise. Opening with a known float and closing with a counted, reconciled amount means any variance shows up the same day, against one shift and one cashier, instead of becoming an unexplained gap discovered weeks later.</span>
            </div>
            <p><strong>Opening:</strong> go to <strong>Shifts → Open Shift</strong> and enter the opening float — every sale made afterward is tracked against it. <strong>Closing:</strong> count the till and enter the closing cash amount; the system calculates expected cash (float + cash sales) and the variance (should be zero), then add handover notes and close. The shift report breaks down total sales by payment method alongside the cash variance and full transaction list.</p>
        </section>

        {{-- ═══════════ 16. REPORTS ═══════════ --}}
        <section class="guide-section" id="s16" data-group="Operations">
            <h2>16. Reports</h2>
            <div class="guide-why">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0 0 12 2z"/></svg>
                <span><span class="guide-why-label">Why it matters</span>Every sale, expense, and stock movement you've recorded elsewhere in the system only becomes useful once you can see it in aggregate — which products actually make money, whether this month was better than last, where cash is really going. Reports are where all that bookkeeping pays off as actual decisions.</span>
            </div>
            <p><strong>Sales Report</strong> — filter by date/product/category/cashier; totals, transaction count, average sale, VAT collected; export to Excel/PDF.</p>
            <p><strong>Profit &amp; Loss</strong> — revenue, cost of goods sold, gross profit, expenses, net profit for any date range.</p>
            <p><strong>Stock Report</strong> — current levels, low-stock items highlighted, stock value at cost.</p>
            <p><strong>Customer Report</strong> — spend per customer, outstanding balances, last purchase date.</p>
            <p><strong>Cross-Store Report</strong> (Growth plan and above) — compare branches side by side from the Organization Dashboard.</p>
        </section>

        {{-- ═══════════ 17. STAFF & HR ═══════════ --}}
        <section class="guide-section" id="s17" data-group="Team &amp; Payroll">
            <h2>17. Staff and HR</h2>
            <div class="guide-why">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0 0 12 2z"/></svg>
                <span><span class="guide-why-label">Why it matters</span>KRA PIN, NSSF, and SHIF numbers aren't paperwork for its own sake — payroll can't be calculated correctly without them, and missing statutory details are exactly the kind of thing that turns into a compliance problem months later. A complete HR profile up front is what makes payday (§18) a five-minute task instead of a scramble.</span>
            </div>
            <p>Go to <strong>Staff → Add Staff Member</strong>, invite an existing user or create a new one, then fill in their HR profile — employment date, job title, department, employment type, National ID, KRA PIN (needed for PAYE), bank details, NSSF/SHIF numbers, and phone (needed for M-Pesa B2C payroll). Set their salary and allowances; they'll show up in the payroll engine once the profile is complete.</p>
            <div class="guide-note">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <span>Staff accounts (the <code>staff</code> role) can't access sales, inventory, customers, or bookings anywhere in the app. Instead they get their own self-service portal after logging in: payslips, leave requests, clock in/out, and salary advances. See §22 for the full roles breakdown.</span>
            </div>
        </section>

        {{-- ═══════════ 18. PAYROLL ═══════════ --}}
        <section class="guide-section" id="s18" data-group="Team &amp; Payroll">
            <h2>18. Payroll</h2>
            <div class="guide-why">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0 0 12 2z"/></svg>
                <span><span class="guide-why-label">Why it matters</span>Getting PAYE, NSSF, and SHIF wrong isn't just an employee-trust problem — it's a KRA compliance one, with real penalties attached. Automating the calculation against current tax bands means you pay people correctly and on time, and have P9 certificates ready when annual returns are due, without redoing the math by hand every month.</span>
            </div>
            <p><strong>Processing:</strong> go to <strong>Payroll → New Payroll Period</strong>, set the pay period and pay date, click <strong>Calculate</strong> — the system works out gross pay, PAYE (2024/25 KRA bands), NSSF (6%+6%, tiered caps), SHIF (2.75% of gross), and net pay for every employee. Review, adjust if needed, <strong>Approve Period</strong> to lock it in, then pay employees one by one (or via M-Pesa B2C if configured). The period closes and the salary expense posts automatically once everyone's paid.</p>
            <p><strong>Payslips</strong> — download a PDF for any payroll line, showing gross, every deduction, and net pay.</p>
            <p><strong>P9 Forms</strong> — at year end, generate KRA-compliant annual tax certificates per employee (individually or as a bulk ZIP) from <strong>Payroll → P9 Forms</strong>.</p>
            <p><strong>Auto-Payroll</strong> — in <strong>Settings → Payroll Settings</strong>, enable it, set your pay-day (1–28), and choose a mode: <strong>Calculate only</strong> (you review and approve), <strong>Auto-approve</strong> (you only confirm payment), or <strong>Fully automatic</strong> (no human touch — use with care).</p>
        </section>

        {{-- ═══════════ 19. MULTI-STORE ═══════════ --}}
        <section class="guide-section" id="s19" data-group="Multi-Store &amp; Integrations">
            <h2>19. Multi-Store Management</h2>
            <div class="guide-why">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0 0 12 2z"/></svg>
                <span><span class="guide-why-label">Why it matters</span>Growing from one shop to several usually breaks whatever system worked for the first one — spreadsheets don't scale, and mixing up branches' stock or cash is how expansion quietly becomes a mess. Keeping each branch's data separate but comparable side by side is what lets you actually manage multiple locations instead of just hoping each one is fine.</span>
            </div>
            <p>Growth and Enterprise plans support multiple stores. Add one via <strong>Organization → Stores → Add Store</strong> — it's immediately available in the store switcher. Switching stores keeps you logged in; inventory, sales, customers, and staff are completely separate per branch.</p>
            <p><strong>Inter-store transfers:</strong> go to <strong>Organization → Stock Transfers → New Transfer</strong>, pick source/destination and items. A transfer moves through <strong>Pending → Approved</strong> (by an owner/overall manager) <strong>→ Dispatched</strong> (stock leaves the source) <strong>→ Received</strong> (stock lands at the destination), with its own reference number throughout.</p>
        </section>

        {{-- ═══════════ 20. PAYMENT INTEGRATIONS ═══════════ --}}
        <section class="guide-section" id="s20" data-group="Multi-Store &amp; Integrations">
            <h2>20. Payment Integrations</h2>
            <div class="guide-why">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0 0 12 2z"/></svg>
                <span><span class="guide-why-label">Why it matters</span>Most customers in Kenya expect to pay by M-Pesa, and a payment that has to be manually confirmed (checking your phone, cross-referencing a code) is slower and more error-prone than one the system matches automatically. Setting this up properly means payments land, get matched to the right sale, and are reflected in your reports without anyone re-typing anything — and the money always goes straight to your own account, never through Merqio POS.</span>
            </div>

            <h3 id="s20-1">20.1 M-Pesa Setup (Per Business)</h3>
            <p>Payments go straight to your own M-Pesa account, never through Merqio POS. You'll need a Safaricom Daraja account (register at <a href="https://developer.safaricom.co.ke" target="_blank" rel="noopener">developer.safaricom.co.ke</a>).</p>
            <ol class="guide-steps">
                <li>Go to <strong>Settings → M-Pesa</strong>, set environment to Sandbox or Production</li>
                <li>Enter your Consumer Key, Consumer Secret, Shortcode, Passkey, and Till Number if applicable</li>
                <li>Click <strong>Save Credentials</strong></li>
                <li>Click <strong>Register C2B URLs with Safaricom</strong> so Paybill/Till payments (not just STK Push) auto-match to a sale by invoice number</li>
            </ol>
            <p>Callback URL for the Daraja portal: <code>https://merqiopos.com/api/mpesa/callback</code></p>

            <h3 id="s20-2">20.2 Pesapal Card Payments Setup</h3>
            <p>Register at <a href="https://www.pesapal.com" target="_blank" rel="noopener">pesapal.com</a>, then go to <strong>Settings → Pesapal (Card)</strong>, set the environment, enter your Consumer Key/Secret, save, and click <strong>Register IPN with Pesapal</strong>. A "Pay by Card via Pesapal" button then appears on the payment page.</p>

            <h3 id="s20-3">20.3 Subscription Billing</h3>
            <p>Your own Merqio POS subscription (as the business owner) is billed separately — via Paystack (card) or M-Pesa STK Push. Manage it under <strong>Settings → Subscription</strong>.</p>
        </section>

        {{-- ═══════════ 21. SETTINGS ═══════════ --}}
        <section class="guide-section" id="s21" data-group="Account &amp; Security">
            <h2>21. Settings</h2>
            <div class="guide-why">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0 0 12 2z"/></svg>
                <span><span class="guide-why-label">Why it matters</span>Most settings only need to be touched once, but getting them right the first time avoids re-doing work — a VAT rate changed after 200 invoices, or M-Pesa credentials fixed after a week of manually chasing payments. This table is a map of where everything lives so you're not hunting for it.</span>
            </div>
            <div class="guide-table-wrap">
                <table class="guide-table">
                    <thead><tr><th>Setting</th><th>Location</th><th>Who can access</th></tr></thead>
                    <tbody>
                        <tr><td>Business Profile</td><td>Settings → Business</td><td>Owner</td></tr>
                        <tr><td>Team Members</td><td>Settings → Team</td><td>Owner</td></tr>
                        <tr><td>VAT</td><td>Settings → VAT</td><td>Owner</td></tr>
                        <tr><td>M-Pesa</td><td>Settings → M-Pesa</td><td>Owner</td></tr>
                        <tr><td>Pesapal</td><td>Settings → Pesapal</td><td>Owner</td></tr>
                        <tr><td>Loyalty Program</td><td>Settings → Loyalty Program</td><td>Owner</td></tr>
                        <tr><td>SMS Notifications</td><td>Settings → SMS</td><td>Owner</td></tr>
                        <tr><td>Payroll Settings</td><td>Settings → Payroll</td><td>Owner</td></tr>
                        <tr><td>API Access</td><td>Settings → API</td><td>Owner</td></tr>
                        <tr><td>Subscription</td><td>Settings → Subscription</td><td>Owner</td></tr>
                        <tr><td>Two-Factor Auth</td><td>Settings → Two-Factor</td><td>All users</td></tr>
                        <tr><td>Audit Log</td><td>Sidebar → Audit Log</td><td>Owner only</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        {{-- ═══════════ 22. ROLES ═══════════ --}}
        <section class="guide-section" id="s22" data-group="Team &amp; Payroll">
            <h2>22. User Roles and Permissions</h2>
            <div class="guide-why">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0 0 12 2z"/></svg>
                <span><span class="guide-why-label">Why it matters</span>Not every employee needs — or should have — access to your financials, other staff's payroll, or the ability to change prices. Giving each person only what their job actually requires limits the damage of an honest mistake and makes it much easier to trace what happened (and by whom) if something ever does go wrong.</span>
            </div>
            <p>Add team members at <strong>Settings → Team → Invite Team Member</strong>.</p>
            <div class="guide-table-wrap">
                <table class="guide-table">
                    <thead><tr><th>Role</th><th>What they can do</th></tr></thead>
                    <tbody>
                        <tr><td>Owner</td><td>Everything — settings, billing, all data, audit log</td></tr>
                        <tr><td>Overall Manager</td><td>Same as owner except billing/subscription; cannot use the POS</td></tr>
                        <tr><td>Manager</td><td>Inventory, sales, invoices, expenses, customers, reports for their branch</td></tr>
                        <tr><td>Cashier</td><td>Make sales, view inventory (no editing), print receipts, view customers</td></tr>
                        <tr><td>Staff</td><td>No access to sales, inventory, customers, or bookings — but they log in and use their own staff portal (payslips, leave, attendance, salary advances)</td></tr>
                    </tbody>
                </table>
            </div>
            <p>Every role lands on the same colorful tile "launcher" screen right after logging in — each tile is just gated to what that role can actually reach, so a cashier sees more tiles than staff, and an owner sees more than either.</p>
            <p><strong>Inviting:</strong> Settings → Team → Invite Team Member → enter email + role → they get an email link to set a password. <strong>Removing:</strong> Settings → Team → Remove next to their name; they can no longer log in to this business.</p>
        </section>

        {{-- ═══════════ 23. 2FA ═══════════ --}}
        <section class="guide-section" id="s23" data-group="Account &amp; Security">
            <h2>23. Two-Factor Authentication (2FA)</h2>
            <div class="guide-why">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0 0 12 2z"/></svg>
                <span><span class="guide-why-label">Why it matters</span>A password alone is one leak, one guessed word, or one shared login away from someone else having full access to your sales figures, customer data, and M-Pesa settings. 2FA means a stolen password by itself isn't enough to get in — worth the small extra step at login, especially for the owner account.</span>
            </div>
            <p>Go to <strong>Settings → Two-Factor Authentication</strong>, click <strong>Enable 2FA</strong>, scan the QR code with an authenticator app (Google Authenticator, Authy, Microsoft Authenticator), and enter the 6-digit code to confirm.</p>
            <div class="guide-note">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <span>As soon as 2FA is confirmed, you'll see 8 one-time <strong>recovery codes</strong> — save them somewhere safe (a password manager, printed and locked away). Each works once in place of your authenticator app, so losing your phone doesn't lock you out. The challenge screen has a "Use a recovery code instead" link. Run low? Generate a fresh set anytime from Settings → Two-Factor — the old ones stop working immediately.</span>
            </div>
            <p>Once enabled, logging in asks for the 6-digit code after your password as usual. Certain sensitive actions (business profile changes, M-Pesa settings, API tokens, team changes) also require re-entering your code even mid-session — this "sudo mode" protects you if you step away from an unlocked computer.</p>
            <p>Lost your device <em>and</em> your recovery codes? An owner or manager can reset a team member's 2FA from <strong>Settings → Team</strong> once they've confirmed the person's identity another way. If it's the owner's own account, support can reset it from the admin side after identity verification.</p>
        </section>

        {{-- ═══════════ 24. API ═══════════ --}}
        <section class="guide-section" id="s24" data-group="Multi-Store &amp; Integrations">
            <h2>24. REST API</h2>
            <div class="guide-why">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0 0 12 2z"/></svg>
                <span><span class="guide-why-label">Why it matters</span>Once a business is big enough to run its own ecommerce store, accounting software, or reporting tools, retyping data between systems by hand becomes its own source of errors and wasted time. The API lets those other tools read and write directly, so your product and sales data stays in one place instead of three slightly-different copies.</span>
            </div>
            <p>Available on the Enterprise plan. Generate a token at <strong>Settings → API Access → Generate New Token</strong> (shown once — copy it immediately), then include it on every request:</p>
            <pre><code>Authorization: Bearer YOUR_TOKEN</code></pre>
            <p>Base URL: <code>https://merqiopos.com/api/v1</code></p>
            <div class="guide-table-wrap">
                <table class="guide-table">
                    <thead><tr><th>Method</th><th>Endpoint</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td>GET</td><td><code>/products</code></td><td>List products</td></tr>
                        <tr><td>GET</td><td><code>/products/{id}</code></td><td>Get a product</td></tr>
                        <tr><td>POST</td><td><code>/products</code></td><td>Create a product</td></tr>
                        <tr><td>PUT</td><td><code>/products/{id}</code></td><td>Update a product</td></tr>
                        <tr><td>GET</td><td><code>/sales</code></td><td>List sales</td></tr>
                        <tr><td>GET</td><td><code>/sales/{id}</code></td><td>Get a sale</td></tr>
                        <tr><td>GET</td><td><code>/sales/summary</code></td><td>Monthly revenue summary</td></tr>
                        <tr><td>GET</td><td><code>/customers</code></td><td>List customers</td></tr>
                        <tr><td>GET</td><td><code>/customers/{id}</code></td><td>Get a customer</td></tr>
                        <tr><td>POST</td><td><code>/customers</code></td><td>Create a customer</td></tr>
                        <tr><td>PUT</td><td><code>/customers/{id}</code></td><td>Update a customer</td></tr>
                    </tbody>
                </table>
            </div>
            <p>All endpoints return JSON, scoped automatically to your active business.</p>
        </section>

        {{-- ═══════════ 25. TROUBLESHOOTING ═══════════ --}}
        <section class="guide-section" id="s25" data-group="Account &amp; Security">
            <h2>25. Troubleshooting</h2>

            <h4>M-Pesa STK Push isn't being received</h4>
            <p>Check your Daraja credentials and environment (sandbox vs production) in Settings → M-Pesa; confirm the phone number format and that it's on the Safaricom network; make sure your Daraja Developer Portal app is active.</p>

            <h4>Customer's Paybill payment isn't auto-detected</h4>
            <p>Confirm C2B URLs are registered (Settings → M-Pesa → Register C2B URLs), that the customer used the correct invoice number as the payment reference, and that your configured shortcode matches your real Paybill/Till.</p>

            <h4>Barcode scanner not working</h4>
            <p>Make sure it's connected and its driver installed; click into the barcode field before scanning; use Chrome or Edge for camera scanning (not Safari/Firefox); confirm the barcode format is supported.</p>

            <h4>Import failed or products missing</h4>
            <p>Use the official template without changing column headers; make sure <code>name</code> and <code>price</code> are filled on every row; category names must match exactly (case-sensitive); barcode duplicates are skipped; keep files under 5MB.</p>

            <h4>2FA code not accepted</h4>
            <p>Check that your phone's clock is accurate — TOTP codes are time-based and won't match if it's off. There's no backup-code fallback (see §23) — if you're fully locked out, contact support.</p>

            <h4>Receipt not printing</h4>
            <p>Use Chrome or Edge (Web Serial API isn't supported elsewhere); click Connect Printer before the sale; pick your printer from the device list when prompted; check the USB connection isn't in use by another app.</p>

            <h4>"Feature not available on your plan"</h4>
            <p>The feature needs a higher plan — go to Settings → Subscription to upgrade. During your free trial, all Business-plan features are available; this can appear once the trial ends.</p>
        </section>

        <div class="guide-cta">
            <div>
                <h3>Still stuck?</h3>
                <p>We reply within one business day — sooner on WhatsApp.</p>
                <a href="{{ route('contact') }}" class="mkt-btn-primary">Contact Support &rarr;</a>
            </div>
            <ul class="guide-cta-list">
                <li>Real replies from a Kenyan support team</li>
                <li>Most questions answered same-day</li>
                <li>WhatsApp support for urgent issues</li>
            </ul>
        </div>

    </div>
</div>

<button type="button" class="guide-top-btn" id="guideTopBtn" aria-label="Back to top">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
</button>

@endsection

@push('scripts')
<script>
(function () {
    var jump = document.getElementById('guideJump');
    if (jump) {
        jump.addEventListener('change', function () {
            if (this.value) {
                var el = document.getElementById(this.value);
                if (el) el.scrollIntoView({ behavior: 'smooth' });
            }
        });
    }

    // Highlight the active TOC entry as the reader scrolls.
    var sections = Array.prototype.slice.call(document.querySelectorAll('.guide-section[id]'));
    var tocLinks = Array.prototype.slice.call(document.querySelectorAll('.guide-toc-group > ul > li > a'));
    if (sections.length && tocLinks.length && 'IntersectionObserver' in window) {
        var byId = {};
        tocLinks.forEach(function (a) { byId[a.getAttribute('href').slice(1)] = a; });

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                var link = byId[entry.target.id];
                if (!link) return;
                if (entry.isIntersecting) {
                    tocLinks.forEach(function (a) { a.classList.remove('active'); });
                    link.classList.add('active');
                }
            });
        }, { rootMargin: '-15% 0px -70% 0px' });

        sections.forEach(function (s) { observer.observe(s); });
    }

    // Live-filter the sidebar TOC by text — matches a top-level section or
    // any of its sub-items; a group with nothing matching hides entirely.
    var search = document.getElementById('guideSearch');
    var countEl = document.getElementById('guideSearchCount');
    var emptyEl = document.getElementById('guideTocEmpty');
    var emptyTerm = document.getElementById('guideTocEmptyTerm');
    if (search) {
        var groups = Array.prototype.slice.call(document.querySelectorAll('.guide-toc-group'));
        search.addEventListener('input', function () {
            var term = this.value.trim().toLowerCase();
            var visibleCount = 0;

            groups.forEach(function (group) {
                var topItems = Array.prototype.slice.call(group.querySelectorAll(':scope > ul > li'));
                var groupHasMatch = false;

                topItems.forEach(function (li) {
                    var text = li.textContent.toLowerCase();
                    var matches = !term || text.indexOf(term) !== -1;
                    li.hidden = !matches;
                    if (matches) { groupHasMatch = true; visibleCount++; }
                });

                group.hidden = !groupHasMatch;
            });

            if (emptyEl) {
                emptyEl.style.display = (term && visibleCount === 0) ? 'block' : 'none';
                if (emptyTerm) emptyTerm.textContent = this.value.trim();
            }
            if (countEl) {
                countEl.textContent = term ? (visibleCount + ' section' + (visibleCount === 1 ? '' : 's') + ' found') : '';
            }
        });
    }

    // Back-to-top — appears once the reader has scrolled past the header.
    var topBtn = document.getElementById('guideTopBtn');
    if (topBtn) {
        window.addEventListener('scroll', function () {
            topBtn.classList.toggle('visible', window.scrollY > 600);
        }, { passive: true });
        topBtn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
})();
</script>
@endpush
