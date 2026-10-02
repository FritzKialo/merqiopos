@extends('layouts.marketing')
@section('title', 'Pricing — Merqio POS')
@section('meta_description', 'Simple, transparent pricing for Kenyan businesses — 3 plans from KSh 999/month. 1-month free trial on Solo, no card required. All prices in KSh.')

@push('styles')
<style>
/* The indigo/Fraunces palette lives in marketing.css's own :root now
   (site-wide), so this page only needs to override the page-hero's
   hardcoded dark-gradient background (it assumes light text on dark by
   default) and light it up to match the rest of the redesign. */
.mkt-page-hero { background: #fff !important; padding: 100px 0 56px; text-align: left; }
.mkt-page-hero::after { display: none; }
.mkt-page-hero .mkt-eyebrow {
    display: inline-flex; align-items: center; gap: 8px;
    color: var(--mkt-ink-soft) !important; font-size: 0.78rem; font-weight: 600;
    text-transform: uppercase; letter-spacing: 0.06em;
}
.mkt-page-hero .mkt-eyebrow::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: var(--mkt-accent); }
.mkt-page-hero h1 { font-weight: 600; color: var(--mkt-ink) !important; }
.mkt-page-hero p { color: var(--mkt-ink-soft) !important; }

/* Plans keep their ORIGINAL green identity — only the rest of the page
   (hero, headings, FAQ, CTA) takes the new indigo. Re-declaring the
   accent variables scoped to the plans grid and the comparison table
   (whose "featured" column echoes the same plan) overrides the
   site-wide indigo :root for just those two components; the featured
   card's original inverted-dark look is untouched since no override
   rule for it exists in this file. */
.mkt-plans-grid, .mkt-compare-categories {
    --mkt-accent:      #234d3a;
    --mkt-accent-soft: #2f6349;
    --mkt-accent-tint: #eaf1ec;
    --mkt-border:      #e2dccd;
}

/* Comparison intro — asymmetric left-aligned head instead of centered,
   matching the split pattern used across the rest of the redesign. */
.mkt-compare-head-split {
    display: grid; grid-template-columns: 0.55fr 1fr; gap: 2.5rem;
    align-items: end; margin-bottom: 2rem;
}
.mkt-compare-head-split h2 { margin: 0.35rem 0 0; }
.mkt-compare-head-split p { font-size: 0.92rem; color: var(--mkt-ink-soft); line-height: 1.6; margin: 0; }
@media (max-width: 720px) {
    .mkt-compare-head-split { grid-template-columns: 1fr; gap: 0.75rem; }
}

/* Comparison — broken into per-category cards instead of one long
   monolithic table, so it reads as a set of digestible panels. */
.mkt-compare-categories {
    display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;
}
.mkt-compare-card {
    background: #fff; border: 1px solid var(--mkt-border); border-radius: 8px;
    padding: 1.35rem 1.5rem 0.5rem;
}
.mkt-compare-card h3 {
    font-family: 'Fraunces', serif; font-weight: 600; font-size: 1rem;
    color: var(--mkt-ink); margin: 0 0 0.85rem;
}
.mkt-compare-mini-head, .mkt-compare-mini-row {
    display: grid; grid-template-columns: 1.7fr 0.7fr 0.7fr 0.7fr; gap: 0.25rem;
    align-items: center;
}
.mkt-compare-mini-head span {
    font-size: 0.62rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
    color: var(--mkt-ink-soft); text-align: center; padding-bottom: 0.5rem;
}
.mkt-compare-mini-head span:first-child { text-align: left; }
.mkt-compare-mini-head span.featured { color: var(--mkt-accent); }
.mkt-compare-mini-row {
    padding: 0.45rem 0; border-top: 1px solid var(--mkt-border);
    font-size: 0.8rem; color: var(--mkt-ink-soft);
}
.mkt-compare-mini-row .feature-name { color: var(--mkt-ink); font-weight: 500; }
.mkt-compare-mini-row span:not(.feature-name) { text-align: center; }
.mkt-compare-mini-row span.featured { background: var(--mkt-accent-tint); border-radius: 4px; padding: 0.15rem 0; font-weight: 600; color: var(--mkt-accent); }
.mkt-compare-mini-row .ck { color: var(--mkt-accent); }
.mkt-compare-mini-row .x { color: #c9c2b3; }
.mkt-compare-mini-row span.featured.x { color: rgba(28,26,23,0.3); font-weight: 400; }
@media (max-width: 700px) {
    .mkt-compare-categories { grid-template-columns: 1fr; }
}

/* FAQ — an open editorial grid of answer cards instead of a single
   click-to-expand accordion column. */
.mkt-faq-head-left { max-width: 460px; margin-bottom: 2rem; }
.mkt-faq-head-left h2 { margin: 0.35rem 0 0; }
.mkt-faq-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem 1.75rem; }
.mkt-faq-card { background: #fff; border: 1px solid var(--mkt-border); border-radius: 8px; padding: 1.35rem 1.5rem; }
.mkt-faq-card .q { font-family: 'Fraunces', serif; font-weight: 600; font-size: 0.98rem; color: var(--mkt-ink); margin: 0 0 0.5rem; }
.mkt-faq-card .a { font-size: 0.85rem; color: var(--mkt-ink-soft); line-height: 1.6; margin: 0; }
@media (max-width: 780px) {
    .mkt-faq-grid { grid-template-columns: 1fr; }
}

/* CTA — split heading/action + a small decision-helper card echoing the
   hero's price card, instead of one centered block or a plain list. */
.mkt-cta-band-inner.mkt-cta-split {
    display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 3rem;
    align-items: center; text-align: left; max-width: 920px;
}
.mkt-cta-split .mkt-cta-actions { justify-content: flex-start; }
.mkt-cta-decision-card { background: #fff; border-radius: 10px; padding: 1.5rem; }
.mkt-cta-decision-card h4 { font-family: 'Fraunces', serif; font-weight: 600; font-size: 1.05rem; color: var(--mkt-ink); margin: 0 0 0.4rem; }
.mkt-cta-decision-card p { font-size: 0.85rem; color: var(--mkt-ink-soft); line-height: 1.55; margin: 0 0 1rem; }
.mkt-cta-decision-card a { font-size: 0.85rem; font-weight: 600; color: var(--mkt-accent); text-decoration: none; }
.mkt-cta-decision-card a:hover { color: var(--mkt-accent-soft); }
@media (max-width: 720px) {
    .mkt-cta-band-inner.mkt-cta-split { grid-template-columns: 1fr; text-align: center; }
    .mkt-cta-split .mkt-cta-actions { justify-content: center; }
    .mkt-cta-decision-card { text-align: center; }
}

/* Hero — asymmetric split with a price-preview card beside the text,
   instead of a plain single-column block with nothing next to it. */
.mkt-page-hero .mkt-container { max-width: 1100px; }
.mkt-hero-split {
    display: grid; grid-template-columns: 1.15fr 0.85fr; gap: 3rem;
    align-items: center;
}
.mkt-hero-split .mkt-eyebrow, .mkt-hero-split h1, .mkt-hero-split p { text-align: left; }
.mkt-hero-price-card {
    background: #fff; border: 1px solid var(--mkt-border); border-radius: 10px;
    padding: 1.75rem; box-shadow: 0 24px 48px -28px rgba(28,26,23,0.25);
}
.mkt-hero-price-card .label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--mkt-ink-soft); }
.mkt-hero-price-card .amount { font-family: 'Fraunces', serif; font-size: 2.4rem; font-weight: 600; color: var(--mkt-ink); line-height: 1; margin: 0.5rem 0 0.1rem; }
.mkt-hero-price-card .amount span { font-size: 1rem; font-weight: 500; color: var(--mkt-ink-soft); }
.mkt-hero-price-card .period { font-size: 0.8rem; color: var(--mkt-ink-soft); margin-bottom: 1.1rem; }
.mkt-hero-price-card ul { list-style: none; margin: 0 0 1.25rem; padding: 0; display: flex; flex-direction: column; gap: 0.5rem; }
.mkt-hero-price-card li { font-size: 0.83rem; color: var(--mkt-ink-soft); display: flex; gap: 0.5rem; align-items: flex-start; }
.mkt-hero-price-card li::before { content: '\2713'; color: var(--mkt-accent); flex-shrink: 0; }
.mkt-hero-price-card a { display: block; text-align: center; font-size: 0.85rem; font-weight: 600; color: var(--mkt-accent); text-decoration: none; padding-top: 0.9rem; border-top: 1px solid var(--mkt-border); }
.mkt-hero-price-card a:hover { color: var(--mkt-accent-soft); }
@media (max-width: 860px) {
    .mkt-hero-split { grid-template-columns: 1fr; }
    .mkt-hero-price-card { max-width: 360px; margin: 0 auto; }
}

/* Plans — a real section intro (there wasn't one at all before), and the
   featured plan lifted onto its own plane instead of sitting flush with
   the other two cards. */
.mkt-plans-head-split {
    display: grid; grid-template-columns: 0.55fr 1fr; gap: 2.5rem;
    align-items: end; margin-bottom: 2.25rem;
}
.mkt-plans-head-split h2 { margin: 0.35rem 0 0; }
.mkt-plans-head-split p { font-size: 0.92rem; color: var(--mkt-ink-soft); line-height: 1.6; margin: 0; }
@media (min-width: 901px) {
    .mkt-plans-grid { grid-template-columns: 0.94fr 1.14fr 0.94fr; max-width: none; margin: 0; }
    .mkt-plan:nth-child(1) { transform: translateY(14px); }
    .mkt-plan.featured { transform: translateY(-18px); box-shadow: 0 28px 56px -28px rgba(0,0,0,0.4); z-index: 2; }
    .mkt-plan:nth-child(3) { transform: translateY(4px); }
}
@media (max-width: 720px) {
    .mkt-plans-head-split { grid-template-columns: 1fr; gap: 0.75rem; }
}
</style>
@endpush

@section('content')

<section class="mkt-page-hero">
    <div class="mkt-container">
        <div class="mkt-hero-split">
            <div>
                <span class="mkt-eyebrow">Pricing</span>
                <h1>Simple pricing, no surprises</h1>
                <p>All prices in Kenyan Shillings. Billed monthly. Cancel anytime. Start with a 1-month free trial on Solo — no card required.</p>
            </div>
            <div class="mkt-hero-price-card">
                <div class="label">Starts at</div>
                <div class="amount">KSh 999<span>/mo</span></div>
                <div class="period">1-month free trial &bull; no card required</div>
                <ul>
                    <li>1 store, up to 3 team members</li>
                    <li>M-Pesa STK Push built in</li>
                    <li>Basic payroll &amp; customer accounts</li>
                </ul>
                <a href="#plans">See all 3 plans &darr;</a>
            </div>
        </div>
    </div>
</section>

<section class="mkt-section" id="plans" style="padding-bottom:48px;">
    <div class="mkt-container-wide">
        <div class="mkt-plans-head-split">
            <div>
                <span class="mkt-eyebrow">Plans</span>
                <h2 class="mkt-h2">Pick your plan</h2>
            </div>
            <p>Start on Solo with a full month free, no card required. Move up to Growth or Enterprise the moment you need more stores, more staff, or deeper reporting — your data carries over instantly.</p>
        </div>
        <div class="mkt-plans-grid">

            {{-- Solo --}}
            <div class="mkt-plan">
                <div class="mkt-plan-name">Solo</div>
                <div class="mkt-plan-price">
                    <span class="mkt-plan-currency">KSh</span>
                    <span class="mkt-plan-amount">999</span>
                </div>
                <div class="mkt-plan-period">per organisation / month &bull; 1-month free trial</div>
                <p class="mkt-plan-desc">For a single shop ready for real payroll, M-Pesa payments, and more room to grow.</p>
                <hr class="mkt-plan-divider">
                <ul class="mkt-plan-features">
                    <li><span class="ck">&#10003;</span> 1 store</li>
                    <li><span class="ck">&#10003;</span> Up to 200 products</li>
                    <li><span class="ck">&#10003;</span> Up to 3 team members</li>
                    <li><span class="ck">&#10003;</span> Basic payroll</li>
                    <li><span class="ck">&#10003;</span> M-Pesa STK Push</li>
                    <li><span class="ck">&#10003;</span> Customer accounts</li>
                    <li><span class="x">&#10007;</span> Barcode scanning</li>
                    <li><span class="x">&#10007;</span> Supplier & purchase orders</li>
                </ul>
                <a href="{{ route('register') }}" class="mkt-plan-cta">Start free trial</a>
            </div>

            {{-- Growth --}}
            <div class="mkt-plan featured">
                <div class="mkt-plan-badge">Most Popular</div>
                <div class="mkt-plan-name">Growth</div>
                <div class="mkt-plan-price">
                    <span class="mkt-plan-currency">KSh</span>
                    <span class="mkt-plan-amount">2,999</span>
                </div>
                <div class="mkt-plan-period">per organisation / month</div>
                <p class="mkt-plan-desc">For growing businesses that need full control over inventory, team, payroll, and financials.</p>
                <hr class="mkt-plan-divider">
                <ul class="mkt-plan-features">
                    <li><span class="ck">&#10003;</span> Up to 3 stores</li>
                    <li><span class="ck">&#10003;</span> Unlimited products</li>
                    <li><span class="ck">&#10003;</span> Up to 15 team members</li>
                    <li><span class="ck">&#10003;</span> Advanced P&amp;L reports</li>
                    <li><span class="ck">&#10003;</span> Barcode scanning (camera + USB)</li>
                    <li><span class="ck">&#10003;</span> M-Pesa STK Push</li>
                    <li><span class="ck">&#10003;</span> Customer credit accounts</li>
                    <li><span class="ck">&#10003;</span> Suppliers & purchase orders</li>
                    <li><span class="ck">&#10003;</span> Recurring invoices, quotes & PDF export</li>
                    <li><span class="ck">&#10003;</span> Payroll, statutory deductions & P9 forms</li>
                    <li><span class="ck">&#10003;</span> Cross-store reporting</li>
                </ul>
                <a href="{{ route('register') }}" class="mkt-plan-cta">Get started</a>
            </div>

            {{-- Enterprise --}}
            <div class="mkt-plan">
                <div class="mkt-plan-name">Enterprise</div>
                <div class="mkt-plan-price">
                    <span class="mkt-plan-currency">KSh</span>
                    <span class="mkt-plan-amount">5,999</span>
                </div>
                <div class="mkt-plan-period">per organisation / month</div>
                <p class="mkt-plan-desc">For multi-branch chains that need every store connected, unlimited team, and API access.</p>
                <hr class="mkt-plan-divider">
                <ul class="mkt-plan-features">
                    <li><span class="ck">&#10003;</span> Everything in Growth</li>
                    <li><span class="ck">&#10003;</span> Unlimited stores / branches</li>
                    <li><span class="ck">&#10003;</span> Unlimited team members</li>
                    <li><span class="ck">&#10003;</span> REST API access</li>
                    <li><span class="ck">&#10003;</span> Priority support</li>
                </ul>
                <a href="{{ route('register') }}" class="mkt-plan-cta">Get started</a>
            </div>
        </div>

        <p style="text-align:center; margin-top:1.5rem; font-size:0.82rem; color:var(--mkt-ink-soft);">
            Solo includes a 1-month free trial — no credit card required. Growth and Enterprise are billed from day one.
        </p>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════ COMPARISON TABLE --}}
<section class="mkt-section" style="background:var(--mkt-ivory); border-top:1px solid var(--mkt-border);">
    <div class="mkt-container">
        <div class="mkt-compare-head-split">
            <div>
                <span class="mkt-eyebrow">Compare plans</span>
                <h2 class="mkt-h2">Everything, side by side</h2>
            </div>
            <p>Every plan covers sales, expenses, and M-Pesa out of the box. The difference is how many stores, staff, and reports you need as you grow — scan the table below to see exactly where each plan draws the line.</p>
        </div>

        <div class="mkt-compare-categories">

            <div class="mkt-compare-card">
                <h3>Core</h3>
                <div class="mkt-compare-mini-head"><span>Feature</span><span>Solo</span><span class="featured">Growth</span><span>Ent.</span></div>
                <div class="mkt-compare-mini-row"><span class="feature-name">Stores / branches</span><span>1</span><span class="featured">Up to 3</span><span>Unlimited</span></div>
                <div class="mkt-compare-mini-row"><span class="feature-name">Products</span><span>200</span><span class="featured">Unlimited</span><span>Unlimited</span></div>
                <div class="mkt-compare-mini-row"><span class="feature-name">Sales &amp; expenses</span><span class="ck">&#10003;</span><span class="featured ck">&#10003;</span><span class="ck">&#10003;</span></div>
                <div class="mkt-compare-mini-row"><span class="feature-name">Team members</span><span>3</span><span class="featured">15</span><span>Unlimited</span></div>
            </div>

            <div class="mkt-compare-card">
                <h3>Payments</h3>
                <div class="mkt-compare-mini-head"><span>Feature</span><span>Solo</span><span class="featured">Growth</span><span>Ent.</span></div>
                <div class="mkt-compare-mini-row"><span class="feature-name">Cash &amp; bank transfer</span><span class="ck">&#10003;</span><span class="featured ck">&#10003;</span><span class="ck">&#10003;</span></div>
                <div class="mkt-compare-mini-row"><span class="feature-name">M-Pesa STK Push</span><span class="ck">&#10003;</span><span class="featured ck">&#10003;</span><span class="ck">&#10003;</span></div>
                <div class="mkt-compare-mini-row"><span class="feature-name">Credit accounts</span><span class="ck">&#10003;</span><span class="featured ck">&#10003;</span><span class="ck">&#10003;</span></div>
            </div>

            <div class="mkt-compare-card">
                <h3>Inventory</h3>
                <div class="mkt-compare-mini-head"><span>Feature</span><span>Solo</span><span class="featured">Growth</span><span>Ent.</span></div>
                <div class="mkt-compare-mini-row"><span class="feature-name">Stock tracking</span><span class="ck">&#10003;</span><span class="featured ck">&#10003;</span><span class="ck">&#10003;</span></div>
                <div class="mkt-compare-mini-row"><span class="feature-name">Barcode scanning</span><span class="x">&#10007;</span><span class="featured ck">&#10003;</span><span class="ck">&#10003;</span></div>
                <div class="mkt-compare-mini-row"><span class="feature-name">Bulk import (Excel)</span><span class="x">&#10007;</span><span class="featured ck">&#10003;</span><span class="ck">&#10003;</span></div>
                <div class="mkt-compare-mini-row"><span class="feature-name">Suppliers &amp; POs</span><span class="x">&#10007;</span><span class="featured ck">&#10003;</span><span class="ck">&#10003;</span></div>
            </div>

            <div class="mkt-compare-card">
                <h3>Reports</h3>
                <div class="mkt-compare-mini-head"><span>Feature</span><span>Solo</span><span class="featured">Growth</span><span>Ent.</span></div>
                <div class="mkt-compare-mini-row"><span class="feature-name">Basic sales report</span><span class="ck">&#10003;</span><span class="featured ck">&#10003;</span><span class="ck">&#10003;</span></div>
                <div class="mkt-compare-mini-row"><span class="feature-name">Profit &amp; Loss</span><span class="x">&#10007;</span><span class="featured ck">&#10003;</span><span class="ck">&#10003;</span></div>
                <div class="mkt-compare-mini-row"><span class="feature-name">PDF &amp; Excel export</span><span class="x">&#10007;</span><span class="featured ck">&#10003;</span><span class="ck">&#10003;</span></div>
                <div class="mkt-compare-mini-row"><span class="feature-name">Cross-store reports</span><span class="x">&#10007;</span><span class="featured ck">&#10003;</span><span class="ck">&#10003;</span></div>
            </div>

            <div class="mkt-compare-card">
                <h3>Staff &amp; Payroll</h3>
                <div class="mkt-compare-mini-head"><span>Feature</span><span>Solo</span><span class="featured">Growth</span><span>Ent.</span></div>
                <div class="mkt-compare-mini-row"><span class="feature-name">Role-based access</span><span class="x">&#10007;</span><span class="featured ck">&#10003;</span><span class="ck">&#10003;</span></div>
                <div class="mkt-compare-mini-row"><span class="feature-name">Payroll processing</span><span class="ck">&#10003;</span><span class="featured ck">&#10003;</span><span class="ck">&#10003;</span></div>
                <div class="mkt-compare-mini-row"><span class="feature-name">P9 tax certificate (KRA)</span><span class="x">&#10007;</span><span class="featured ck">&#10003;</span><span class="ck">&#10003;</span></div>
            </div>

            <div class="mkt-compare-card">
                <h3>Platform</h3>
                <div class="mkt-compare-mini-head"><span>Feature</span><span>Solo</span><span class="featured">Growth</span><span>Ent.</span></div>
                <div class="mkt-compare-mini-row"><span class="feature-name">REST API access</span><span class="x">&#10007;</span><span class="featured x">&#10007;</span><span class="ck">&#10003;</span></div>
                <div class="mkt-compare-mini-row"><span class="feature-name">Priority support</span><span class="x">&#10007;</span><span class="featured x">&#10007;</span><span class="ck">&#10003;</span></div>
            </div>

        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════ FAQ --}}
<section class="mkt-section" style="border-top:1px solid var(--mkt-border);">
    <div class="mkt-container">
        <div class="mkt-faq-head-left">
            <span class="mkt-eyebrow">FAQ</span>
            <h2 class="mkt-h2">Common questions</h2>
        </div>
        <div class="mkt-faq-grid">
            <div class="mkt-faq-card">
                <p class="q">Can I try before I pay?</p>
                <p class="a">Yes — sign up for Solo and you get a full 1-month free trial, no card required. Growth and Enterprise are billed from day one, since businesses at that stage already know they need the extra stores, team seats, and reporting.</p>
            </div>
            <div class="mkt-faq-card">
                <p class="q">What happens when the trial ends?</p>
                <p class="a">You'll be asked to choose a paid plan to keep your account active. Your data is never deleted while you decide — pick a plan from Settings whenever you're ready and everything picks up right where you left off.</p>
            </div>
            <div class="mkt-faq-card">
                <p class="q">How does M-Pesa payment work?</p>
                <p class="a">On every plan, you trigger an M-Pesa STK Push directly from the sale screen. The customer receives a prompt on their phone, enters their PIN, and the payment is confirmed and recorded automatically.</p>
            </div>
            <div class="mkt-faq-card">
                <p class="q">Can I manage more than one shop?</p>
                <p class="a">Yes. Growth supports up to 3 stores and Enterprise unlimited stores — all under one organisation login. Switch between them from a single account, with a combined organisation dashboard.</p>
            </div>
            <div class="mkt-faq-card">
                <p class="q">What happens when I exceed the product limit on Solo?</p>
                <p class="a">You'll be prompted to upgrade. Your existing data is never deleted — upgrading takes seconds and you're immediately on the next plan up with more room.</p>
            </div>
            <div class="mkt-faq-card">
                <p class="q">Is my business data safe?</p>
                <p class="a">Your data is stored securely on servers in Kenya, encrypted at rest and in transit, and backed up daily. We never share your business data with third parties.</p>
            </div>
            <div class="mkt-faq-card">
                <p class="q">Can I cancel anytime?</p>
                <p class="a">Yes. Cancel from account settings at any time with no penalty. You never lose your data, and you can export everything before you go.</p>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════ CTA --}}
<section class="mkt-cta-band">
    <div class="mkt-container">
        <div class="mkt-cta-band-inner mkt-cta-split">
            <div>
                <h2>Still have questions?</h2>
                <p>Our team is here to help you find the right plan for your business size and budget.</p>
                <div class="mkt-cta-actions">
                    <a href="{{ route('contact') }}" class="mkt-btn-primary mkt-btn-xl">Talk to us &rarr;</a>
                    @if(!auth()->check())
                        <a href="{{ route('register') }}" class="mkt-btn-outline mkt-btn-xl">Start free trial</a>
                    @endif
                </div>
            </div>
            <div class="mkt-cta-decision-card">
                <h4>Not sure yet?</h4>
                <p>Start with Solo — a full month free, no card required. Upgrade to Growth or Enterprise the moment you outgrow it, and your data carries over instantly.</p>
                <a href="#plans">Compare all 3 plans &rarr;</a>
            </div>
        </div>
    </div>
</section>

@endsection
