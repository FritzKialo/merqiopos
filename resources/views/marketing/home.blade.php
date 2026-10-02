@extends('layouts.marketing')
@section('title', 'Merqio POS — Complete Business Management for Kenyan SMEs')
@section('meta_description', 'Run your shop, supermarket, pharmacy, or chain of branches from one place. Sales, inventory, M-Pesa payments, invoices, payroll, and more — built for Kenya.')

@push('styles')
<style>
/* =========================================================
   Home page re-theme — quiet, minimal, single-accent corporate
   look (Stripe/Linear-style), replacing the earlier vibrant
   indigo/coral bento treatment. The indigo/Fraunces palette
   itself now lives in marketing.css's own :root (site-wide),
   so only this page's hardcoded-dark-gradient sections need
   overriding here.
   ========================================================= */

/* Hardcoded dark-gradient sections in marketing.css — replaced with
   plain white/light backgrounds and normal dark text instead of a
   second, louder color override. Quiet, not just re-colored. */
.mkt-hero, .mkt-page-hero { background: #fff !important; }
.mkt-hero h1, .mkt-hero p, .mkt-page-hero h1, .mkt-page-hero p { color: var(--mkt-ink) !important; }
.mkt-hero h1 span, .mkt-page-hero .mkt-eyebrow { color: var(--mkt-accent) !important; font-style: normal !important; }
.mkt-badge { color: var(--mkt-ink) !important; background: var(--mkt-ivory) !important; border-color: var(--mkt-border) !important; }
.mkt-hero-trust span { color: var(--mkt-ink-soft) !important; }
.mkt-hero-trust span::before { color: var(--mkt-accent) !important; }
.mkt-hero .mkt-btn-outline { color: var(--mkt-ink) !important; border-color: var(--mkt-border) !important; }
.mkt-hero .mkt-btn-outline:hover { border-color: var(--mkt-ink) !important; background: var(--mkt-ivory) !important; }

.mkt-strip { background: var(--mkt-ivory) !important; border-top: 1px solid var(--mkt-border); border-bottom: 1px solid var(--mkt-border); }
.mkt-ribbon-item { color: var(--mkt-ink) !important; }

.mkt-kenya { background: var(--mkt-ivory) !important; }
.mkt-kenya::before { display: none !important; }
.mkt-kenya-text h2, .mkt-checklist li span { color: var(--mkt-ink) !important; }
.mkt-kenya-text p, .mkt-checklist li { color: var(--mkt-ink-soft) !important; }
.mkt-kenya .mkt-kicker { color: var(--mkt-accent) !important; }
.mkt-checklist li::before { background: var(--mkt-accent) !important; }

/* Real Fraunces serif on every section heading, matching the hero, for
   one cohesive editorial typographic system instead of the hero being
   the only place that departs from plain sans. */
.mkt-section-head h2, .mkt-spotlight-text h2, .mkt-kenya-text h2, .mkt-cta-band h2,
.mkt-who-text h2, .mkt-how-head h2, .mkt-compliance-text h2 {
    font-family: 'Fraunces', serif !important; font-weight: 600 !important; letter-spacing: -0.01em;
}

.mkt-cta-band { background: var(--mkt-ivory) !important; border-top: 1px solid var(--mkt-border); }
.mkt-cta-band h2 { color: var(--mkt-ink) !important; }
.mkt-cta-band p { color: var(--mkt-ink-soft) !important; }
.mkt-btn-primary-xl { background: var(--mkt-accent) !important; color: #fff !important; }
.mkt-btn-primary-xl:hover { background: var(--mkt-accent-soft) !important; }
.mkt-btn-outline-xl { color: var(--mkt-ink) !important; border-color: var(--mkt-border) !important; }
.mkt-btn-outline-xl:hover { border-color: var(--mkt-ink) !important; color: var(--mkt-ink) !important; }

/* ── Hero — left-aligned, real serif display headline, product
   shot beside the text instead of stacked underneath. A genuinely
   different structure, not just a re-color of the centered version. ── */
.mkt-hero { padding: 108px 0 90px; text-align: left; position: relative; overflow: visible; background: #fff; }
.mkt-hero-inner {
    display: grid; grid-template-columns: 1fr 0.95fr; gap: 3.5rem; align-items: center;
}
.mkt-hero-text { max-width: 480px; }

.mkt-eyebrow-dot {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 0.78rem; font-weight: 600; color: var(--mkt-ink-soft);
    text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 1.1rem;
}
.mkt-eyebrow-dot::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: var(--mkt-accent); flex-shrink: 0; }

.mkt-hero h1 {
    font-family: 'Fraunces', serif; font-weight: 600; font-style: normal;
    font-size: clamp(2.1rem, 3.4vw, 2.9rem); line-height: 1.15; letter-spacing: -0.01em;
    color: var(--mkt-ink) !important; margin: 0 0 1.1rem; max-width: none;
}
.mkt-hero h1 span { font-family: 'Fraunces', serif; font-style: italic !important; color: var(--mkt-accent) !important; }
.mkt-hero > .mkt-container > .mkt-hero-text > p { max-width: 420px; color: var(--mkt-ink-soft); margin: 0 0 1.75rem; text-align: left; }
.mkt-hero-actions { justify-content: flex-start; }
.mkt-hero-trust { justify-content: flex-start; margin-top: 1.25rem; }

.mkt-shot-frame {
    border-radius: 12px; margin: 0;
    background: #fff; border: 1px solid var(--mkt-border);
    box-shadow: 0 8px 16px -8px rgba(15,23,42,0.08), 0 30px 70px -25px rgba(67,56,202,0.22);
    overflow: hidden; position: relative;
}
.mkt-shot-bar { display: flex; align-items: center; gap: 6px; padding: 10px 14px; background: var(--mkt-ivory); border-bottom: 1px solid var(--mkt-border); }
.mkt-shot-bar span { width: 9px; height: 9px; border-radius: 50%; background: var(--mkt-border); }
.mkt-shot-frame img { display: block; width: 100%; height: auto; }

@media (max-width: 900px) {
    .mkt-hero { text-align: center; padding-top: 80px; }
    .mkt-hero-inner { grid-template-columns: 1fr; }
    .mkt-hero-text { max-width: 100%; margin: 0 auto; }
    .mkt-eyebrow-dot, .mkt-hero-actions, .mkt-hero-trust { justify-content: center; }
    .mkt-hero > .mkt-container > .mkt-hero-text > p { margin-left: auto; margin-right: auto; text-align: center; }
}

/* ── Value strip — one inline ribbon, not four boxed columns ── */
.mkt-strip { padding: 1.5rem 0; }
.mkt-ribbon { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 0.5rem 0; }
.mkt-ribbon-item {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.5rem 1.5rem; font-size: 0.85rem; font-weight: 600; color: var(--mkt-ink);
    border-right: 1px solid var(--mkt-border);
}
.mkt-ribbon-item:last-child { border-right: none; }
.mkt-ribbon-item svg { width: 16px; height: 16px; color: var(--mkt-accent); flex-shrink: 0; }

/* ── Who it's for — asymmetric split (text left, tag cluster right),
   mirroring the hero's composition instead of centered-heading-then-
   full-width-row. ── */
.mkt-who-split { padding: 4.5rem 0; }
.mkt-who-grid { display: grid; grid-template-columns: 0.85fr 1.15fr; gap: 3.5rem; align-items: center; }
.mkt-who-text h2 { margin: 0.5rem 0 0.85rem; font-size: 1.65rem; }
.mkt-who-text p { color: var(--mkt-ink-soft); font-size: 0.92rem; line-height: 1.6; margin: 0; max-width: 340px; }
.mkt-tags-cluster { justify-content: flex-start; margin-top: 0; }
.mkt-tag {
    background: #fff; border: 1px solid var(--mkt-border); border-radius: 999px;
    padding: 0.5rem 1.05rem; font-size: 0.83rem; font-weight: 600; color: var(--mkt-ink);
    transition: border-color 0.15s, color 0.15s, transform 0.15s;
}
.mkt-tag:hover { border-color: var(--mkt-accent); color: var(--mkt-accent); transform: translateY(-1px); }
.mkt-tags { display: flex; flex-wrap: wrap; gap: 0.6rem; }

@media (max-width: 900px) {
    .mkt-who-grid { grid-template-columns: 1fr; text-align: center; }
    .mkt-who-text p { margin-left: auto; margin-right: auto; }
    .mkt-tags, .mkt-tags-cluster { justify-content: center; }
}

/* ── Feature spotlights ── */
.mkt-spotlight { padding: 4rem 0; }
.mkt-spotlight-row { display: grid; grid-template-columns: 1fr 1fr; gap: 3.5rem; align-items: center; }
.mkt-spotlight-row.reverse > *:first-child { order: 2; }
.mkt-spotlight-shot {
    border-radius: 10px; overflow: hidden; background: #fff; border: 1px solid var(--mkt-border);
    box-shadow: 0 16px 40px -24px rgba(15,23,42,0.16);
}
.mkt-spotlight-shot-bar { display: flex; gap: 5px; padding: 9px 12px; background: var(--mkt-ivory); border-bottom: 1px solid var(--mkt-border); }
.mkt-spotlight-shot-bar span { width: 8px; height: 8px; border-radius: 50%; background: var(--mkt-border); }
.mkt-spotlight-shot img { display: block; width: 100%; height: auto; }
.mkt-spotlight-text h2 { font-family: var(--mkt-serif); font-size: 1.55rem; font-weight: 700; margin-bottom: 1rem; color: var(--mkt-ink); letter-spacing: -0.01em; }
.mkt-check-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.75rem; }
.mkt-check-list li { display: flex; align-items: flex-start; gap: 0.65rem; font-size: 0.92rem; color: var(--mkt-ink-soft); }
.mkt-check-list li svg { width: 16px; height: 16px; color: var(--mkt-accent); flex-shrink: 0; margin-top: 3px; }

/* ── Compact feature tile grid ── */
.mkt-tile-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1px; background: var(--mkt-border); border: 1px solid var(--mkt-border); border-radius: 10px; overflow: hidden; margin-top: 1.75rem; }
.mkt-tile { background: #fff; padding: 20px 18px; }
.mkt-tile-icon { width: 32px; height: 32px; border-radius: 7px; display: flex; align-items: center; justify-content: center; margin-bottom: 10px; background: var(--mkt-accent-tint); color: var(--mkt-accent); }
.mkt-tile-icon svg { width: 16px; height: 16px; }
.mkt-tile h4 { font-size: 0.86rem; font-weight: 700; color: var(--mkt-ink); margin-bottom: 3px; }
.mkt-tile p { font-size: 0.75rem; color: var(--mkt-ink-soft); line-height: 1.4; margin: 0; }

/* ── Payments — plain light band, no glass ── */
.mkt-payments { padding: 3.5rem 0; background: var(--mkt-ivory); }
.mkt-payments-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; align-items: center; }
.mkt-pay-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.mkt-pay-card { background: #fff; border: 1px solid var(--mkt-border); border-radius: 10px; padding: 1rem 1.1rem; }
.mkt-pay-card h4 { font-size: 0.85rem; font-weight: 700; margin-bottom: 3px; color: var(--mkt-ink); }
.mkt-pay-card p { font-size: 0.75rem; color: var(--mkt-ink-soft); line-height: 1.45; margin: 0; }

/* ── Compliance — asymmetric split: intro text left, a compact
   two-column definition list right, instead of six boxed cards. ── */
.mkt-compliance { padding: 4rem 0; background: var(--mkt-ivory); }
.mkt-compliance-grid-split { display: grid; grid-template-columns: 0.75fr 1.25fr; gap: 3.5rem; align-items: center; }
.mkt-compliance-text h2 { margin: 0.5rem 0 0.85rem; font-size: 1.65rem; }
.mkt-compliance-text p { color: var(--mkt-ink-soft); font-size: 0.92rem; line-height: 1.6; margin: 0; max-width: 320px; }
.mkt-deflist { display: grid; grid-template-columns: 1fr 1fr; gap: 0; }
.mkt-deflist > div { padding: 0.9rem 0; border-bottom: 1px solid var(--mkt-border); }
.mkt-deflist > div:nth-last-child(-n+2) { border-bottom: none; }
.mkt-deflist dt { font-size: 0.86rem; font-weight: 700; color: var(--mkt-ink); }
.mkt-deflist dd { font-size: 0.78rem; color: var(--mkt-ink-soft); margin: 2px 0 0; }

/* ── Roles — a list of role rows with a status badge, not a plain
   black-header HTML table. ── */
.mkt-roles { background: #fff; }
.mkt-role-list { margin-top: 2rem; border-top: 1px solid var(--mkt-border); }
.mkt-role-row {
    display: grid; grid-template-columns: 160px 1fr auto; gap: 1.5rem; align-items: center;
    padding: 1rem 0; border-bottom: 1px solid var(--mkt-border);
}
.mkt-role-name { font-family: 'Fraunces', serif; font-weight: 600; font-size: 1.05rem; color: var(--mkt-ink); }
.mkt-role-desc { font-size: 0.86rem; color: var(--mkt-ink-soft); }
.mkt-role-badge {
    font-size: 0.72rem; font-weight: 700; padding: 0.3rem 0.75rem; border-radius: 999px;
    background: var(--mkt-ivory); color: var(--mkt-ink-soft); white-space: nowrap; justify-self: end;
}
.mkt-role-badge.full { background: var(--mkt-accent-tint); color: var(--mkt-accent); }

/* ── CTA — split: message+action left, reassurance list right ── */
.mkt-cta-grid { display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 3rem; align-items: center; }
.mkt-cta-list { list-style: none; margin: 0; padding: 1.5rem 0 1.5rem 1.75rem; border-left: 1px solid var(--mkt-border); display: flex; flex-direction: column; gap: 0.85rem; }
.mkt-cta-list li { font-size: 0.88rem; color: var(--mkt-ink-soft); position: relative; }
.mkt-cta-list li::before { content: ''; position: absolute; left: -1.75rem; top: 7px; width: 7px; height: 7px; border-radius: 50%; background: var(--mkt-accent); }

@media (max-width: 900px) {
    .mkt-compliance-grid-split, .mkt-cta-grid { grid-template-columns: 1fr; text-align: center; }
    .mkt-compliance-text p { margin-left: auto; margin-right: auto; }
    .mkt-deflist { max-width: 420px; margin: 0 auto; text-align: left; }
    {{-- align-items was "center" here, which (since each <li>'s box shrinks
    to its own text width) centered every bullet+line individually instead
    of lining them up — the bullet (li::before, anchored to each li's own
    left edge) landed at a different x position per item depending on how
    long that line's text was. flex-start keeps the shared left edge the
    text-align:left below already implies, while margin:auto still centers
    the whole block as one unit. --}}
    .mkt-cta-list { border-left: none; padding-left: 0; align-items: flex-start; text-align: left; max-width: 320px; margin: 0 auto; }
    .mkt-role-row { grid-template-columns: 1fr; text-align: center; gap: 0.4rem; }
    .mkt-role-badge { justify-self: center; }
}
@media (max-width: 480px) {
    .mkt-deflist { grid-template-columns: 1fr; }
    .mkt-deflist > div:nth-last-child(-n+2) { border-bottom: 1px solid var(--mkt-border); }
    .mkt-deflist > div:last-child { border-bottom: none; }
}

/* ── How it works — vertical left-aligned timeline, replacing the
   horizontal 4-up row entirely. Heading sits beside the timeline
   (asymmetric, matching the hero and "who it's for" split) rather
   than centered above it. ── */
.mkt-how { padding: 4.5rem 0; background: #fff; }
.mkt-how-grid { display: grid; grid-template-columns: 0.8fr 1.2fr; gap: 3.5rem; align-items: start; }
.mkt-how-head { position: sticky; top: 100px; }
.mkt-how-head h2 { margin: 0.5rem 0 0.85rem; font-size: 1.65rem; }
.mkt-how-head p { color: var(--mkt-ink-soft); font-size: 0.92rem; line-height: 1.6; margin: 0; max-width: 300px; }

.mkt-timeline { position: relative; }
.mkt-timeline::before {
    content: ''; position: absolute; left: 21px; top: 8px; bottom: 8px; width: 1px; background: var(--mkt-border);
}
.mkt-tl-step { display: flex; gap: 1.5rem; position: relative; padding-bottom: 2.5rem; }
.mkt-tl-step:last-child { padding-bottom: 0; }
.mkt-tl-num {
    font-family: 'Fraunces', serif; font-weight: 600; font-style: italic;
    width: 44px; height: 44px; border-radius: 50%; flex-shrink: 0;
    background: #fff; border: 1px solid var(--mkt-border); color: var(--mkt-accent);
    display: flex; align-items: center; justify-content: center; font-size: 1.15rem;
    position: relative; z-index: 1;
}
.mkt-tl-body { padding-top: 8px; }
.mkt-tl-body h4 { font-size: 0.96rem; font-weight: 700; color: var(--mkt-ink); margin-bottom: 0.3rem; }
.mkt-tl-body p { font-size: 0.85rem; color: var(--mkt-ink-soft); line-height: 1.5; margin: 0; }

@media (max-width: 900px) {
    .mkt-how-grid { grid-template-columns: 1fr; text-align: center; }
    .mkt-how-head { position: static; }
    .mkt-how-head p { margin-left: auto; margin-right: auto; }
    .mkt-timeline { text-align: left; max-width: 420px; margin: 0 auto; }
}

@media (max-width: 900px) {
    .mkt-spotlight-row, .mkt-spotlight-row.reverse { grid-template-columns: 1fr; }
    .mkt-spotlight-row.reverse > *:first-child { order: 0; }
}
@media (max-width: 768px) {
    .mkt-ribbon-item { border-right: none; padding: 0.35rem 1rem; }
    .mkt-payments-grid, .mkt-pay-cards { grid-template-columns: 1fr; }
    .mkt-tile-grid { grid-template-columns: 1fr 1fr; }
    .mkt-compare-table th:nth-child(3), .mkt-compare-table td:nth-child(3) { display: none; }
}
</style>
@endpush

@section('content')

{{-- ── HERO ── --}}
<section class="mkt-hero">
    <div class="mkt-container mkt-hero-inner">
        <div class="mkt-hero-text">
            <span class="mkt-eyebrow-dot">Built for Kenyan businesses</span>
            <h1>Run your whole business from <span>one smart system</span></h1>
            <p>Sales, inventory, M-Pesa, invoicing, and payroll — one login, every branch.</p>
            <div class="mkt-hero-actions">
                @if(auth()->check())
                    <a href="{{ route('dashboard') }}" class="mkt-btn-primary">Go to Dashboard &rarr;</a>
                @else
                    <a href="{{ route('register') }}" class="mkt-btn-primary">
                        <span class="mkt-btn-text-full">Start free — no card needed</span>
                        <span class="mkt-btn-text-short">Start free</span>
                        &rarr;
                    </a>
                    <form method="POST" action="{{ route('demo.start') }}" style="display:inline;margin:0;">@csrf<button type="submit" class="mkt-btn-outline" style="font:inherit;cursor:pointer;">Try the demo</button></form>
                @endif
            </div>
            <div class="mkt-hero-trust">
                <span>1-month free trial</span>
                <span>No credit card required</span>
                <span>M-Pesa integrated</span>
            </div>
        </div>
        <div class="mkt-shot-frame">
            <div class="mkt-shot-bar"><span></span><span></span><span></span></div>
            <img src="{{ asset('images/screenshots/pos-demo.jpg') }}" alt="Merqio POS point of sale screen" loading="lazy">
        </div>
    </div>
</section>

{{-- ── VALUE STRIP — single inline ribbon, not four boxed columns ── --}}
<section class="mkt-strip">
    <div class="mkt-container mkt-ribbon">
        <div class="mkt-ribbon-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            <span>All-in-one</span>
        </div>
        <div class="mkt-ribbon-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            <span>M-Pesa + Card</span>
        </div>
        <div class="mkt-ribbon-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <span>Multi-branch</span>
        </div>
        <div class="mkt-ribbon-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4"/><path d="M12 3a9 9 0 1 0 9 9"/><path d="M12 3v9h9"/></svg>
            <span>KRA ready</span>
        </div>
    </div>
</section>

{{-- ── WHO IT'S FOR — asymmetric split, mirrors the hero's text-left /
     visual-right composition instead of a centered heading over a
     wrapped row of pills. ── --}}
<section class="mkt-who-split">
    <div class="mkt-container mkt-who-grid">
        <div class="mkt-who-text">
            <span class="mkt-kicker">Who it's for</span>
            <h2>Built for every kind of Kenyan business</h2>
            <p>From a corner duka to a multi-branch chain — one system that fits how you already trade.</p>
        </div>
        <div class="mkt-tags mkt-tags-cluster">
            <span class="mkt-tag">Supermarkets &amp; Dukas</span>
            <span class="mkt-tag">Pharmacies</span>
            <span class="mkt-tag">Salons &amp; Barbershops</span>
            <span class="mkt-tag">Restaurants &amp; Cafés</span>
            <span class="mkt-tag">Hardware</span>
            <span class="mkt-tag">Clothing &amp; Fashion</span>
            <span class="mkt-tag">Electronics</span>
            <span class="mkt-tag">Services &amp; Consulting</span>
        </div>
    </div>
</section>

{{-- ── HOW IT WORKS — vertical left-aligned timeline instead of a
     horizontal 4-up row. ── --}}
<section class="mkt-how">
    <div class="mkt-container mkt-how-grid">
        <div class="mkt-how-head">
            <span class="mkt-kicker">How it works</span>
            <h2>Up and running in four simple steps</h2>
            <p>No installation, no IT team. Just sign up and start selling.</p>
        </div>
        <div class="mkt-timeline">
            <div class="mkt-tl-step">
                <div class="mkt-tl-num">1</div>
                <div class="mkt-tl-body">
                    <h4>Create your account</h4>
                    <p>Sign up free — no card, no setup fee.</p>
                </div>
            </div>
            <div class="mkt-tl-step">
                <div class="mkt-tl-num">2</div>
                <div class="mkt-tl-body">
                    <h4>Set up your business</h4>
                    <p>Add your logo, VAT number, and M-Pesa Till.</p>
                </div>
            </div>
            <div class="mkt-tl-step">
                <div class="mkt-tl-num">3</div>
                <div class="mkt-tl-body">
                    <h4>Add your products</h4>
                    <p>Import from Excel or add them one by one.</p>
                </div>
            </div>
            <div class="mkt-tl-step">
                <div class="mkt-tl-num">4</div>
                <div class="mkt-tl-body">
                    <h4>Start selling</h4>
                    <p>Scan, charge M-Pesa or cash, print a receipt.</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── SPOTLIGHT: POS ── --}}
<section class="mkt-spotlight" style="background: var(--mkt-ivory);">
    <div class="mkt-container">
        <div class="mkt-spotlight-row">
            <div class="mkt-spotlight-shot">
                <div class="mkt-spotlight-shot-bar"><span></span><span></span><span></span></div>
                <img src="{{ asset('images/screenshots/pos-demo.jpg') }}" alt="Point of sale checkout screen" loading="lazy">
            </div>
            <div class="mkt-spotlight-text">
                <span class="mkt-kicker">Point of sale</span>
                <h2>Checkout in seconds, not minutes</h2>
                <ul class="mkt-check-list">
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg> Scan or search — item goes straight to cart</li>
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg> Split payment — cash, M-Pesa, and card together</li>
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg> Print or send a digital receipt instantly</li>
                </ul>
            </div>
        </div>
    </div>
</section>

{{-- ── SPOTLIGHT: INVENTORY ── --}}
<section class="mkt-spotlight">
    <div class="mkt-container">
        <div class="mkt-spotlight-row reverse">
            <div class="mkt-spotlight-shot">
                <div class="mkt-spotlight-shot-bar"><span></span><span></span><span></span></div>
                <img src="{{ asset('images/screenshots/inventory-demo.jpg') }}" alt="Inventory management screen" loading="lazy">
            </div>
            <div class="mkt-spotlight-text">
                <span class="mkt-kicker">Inventory</span>
                <h2>Know your stock, down to the shilling</h2>
                <ul class="mkt-check-list">
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg> Live stock value and margin per product</li>
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg> Low-stock alerts before you run out</li>
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg> Bulk import your whole catalogue from Excel</li>
                </ul>
            </div>
        </div>
    </div>
</section>

{{-- ── COMPACT FEATURE GRID ── --}}
<section class="mkt-section" id="features" style="background: var(--mkt-ivory); padding: 3.5rem 0;">
    <div class="mkt-container">
        <div class="mkt-section-head">
            <span class="mkt-kicker">And everything else</span>
            <h2>One system, every part of the business</h2>
        </div>
        <div class="mkt-tile-grid">
            <div class="mkt-tile">
                <div class="mkt-tile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
                <h4>Customers &amp; Loyalty</h4>
                <p>Credit accounts &amp; points</p>
            </div>
            <div class="mkt-tile">
                <div class="mkt-tile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
                <h4>Invoices &amp; Quotes</h4>
                <p>Branded PDFs, recurring billing</p>
            </div>
            <div class="mkt-tile">
                <div class="mkt-tile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div>
                <h4>Reports &amp; P&amp;L</h4>
                <p>Daily sales, profit, stock value</p>
            </div>
            <div class="mkt-tile">
                <div class="mkt-tile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div>
                <h4>Payroll &amp; HR</h4>
                <p>PAYE, NSSF, SHIF, P9</p>
            </div>
            <div class="mkt-tile">
                <div class="mkt-tile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg></div>
                <h4>Expenses &amp; Suppliers</h4>
                <p>Purchase orders to received stock</p>
            </div>
            <div class="mkt-tile">
                <div class="mkt-tile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
                <h4>Multi-Branch</h4>
                <p>One dashboard, every store</p>
            </div>
            <div class="mkt-tile">
                <div class="mkt-tile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
                <h4>Shifts</h4>
                <p>Float, cash count, no surprises</p>
            </div>
            <div class="mkt-tile">
                <div class="mkt-tile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></div>
                <h4>Discounts &amp; Coupons</h4>
                <p>Timed promos, coupon codes</p>
            </div>
            <div class="mkt-tile">
                <div class="mkt-tile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.68 13a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.54 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6.18 6.18l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg></div>
                <h4>SMS Alerts</h4>
                <p>Auto-notify on invoices &amp; balances</p>
            </div>
            <div class="mkt-tile">
                <div class="mkt-tile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
                <h4>Security &amp; Access</h4>
                <p>Roles, 2FA, full audit trail</p>
            </div>
        </div>
    </div>
</section>

{{-- ── PAYMENT SECTION ── --}}
<section class="mkt-payments">
    <div class="mkt-container">
        <div class="mkt-payments-grid">
            <div>
                <span class="mkt-kicker">Payments</span>
                <h2 style="font-family:var(--mkt-serif); font-size:1.75rem; font-weight:700; margin-bottom:0.75rem; line-height:1.25; color:var(--mkt-ink);">Get paid the way your customers pay</h2>
                <p style="color:var(--mkt-ink-soft); font-size:0.9rem; margin-bottom:1.5rem;">Straight to your own M-Pesa Paybill or Till — never ours.</p>
                @if(!auth()->check())
                <a href="{{ route('register') }}" class="mkt-btn-primary">Set up payments free &rarr;</a>
                @endif
            </div>
            <div class="mkt-pay-cards">
                <div class="mkt-pay-card"><h4>M-Pesa STK Push</h4><p>Phone prompt, auto-completes</p></div>
                <div class="mkt-pay-card"><h4>Paybill / Till</h4><p>Auto-matches, no cashier step</p></div>
                <div class="mkt-pay-card"><h4>Card via Pesapal</h4><p>Visa, Mastercard, Airtel Money</p></div>
                <div class="mkt-pay-card"><h4>Cash, Bank &amp; Credit</h4><p>All tracked in one place</p></div>
            </div>
        </div>
    </div>
</section>

{{-- ── KENYA COMPLIANCE — asymmetric split, compact definition-list
     instead of six boxed cards in a grid. ── --}}
<section class="mkt-compliance">
    <div class="mkt-container mkt-compliance-grid-split">
        <div class="mkt-compliance-text">
            <span class="mkt-kicker">Built for Kenya</span>
            <h2>KRA-compliant, out of the box</h2>
            <p>No manual workarounds — Merqio POS already knows Kenyan tax law and calculates everything correctly.</p>
        </div>
        <dl class="mkt-deflist">
            <div><dt>PAYE</dt><dd>2024/25 bands + relief</dd></div>
            <div><dt>SHIF</dt><dd>2.75% of gross pay</dd></div>
            <div><dt>NSSF</dt><dd>Tiered per the 2013 Act</dd></div>
            <div><dt>VAT (16%)</dt><dd>Filing-ready reports</dd></div>
            <div><dt>M-Pesa</dt><dd>Direct Daraja integration</dd></div>
            <div><dt>Tax Invoices</dt><dd>KRA PIN + VAT breakdown</dd></div>
        </dl>
    </div>
</section>

{{-- ── ROLES — a role list with access badges, replacing the plain
     HTML table (black header bar + ASCII ✓/✗) that clashed with the
     rest of the page's editorial look. ── --}}
<section class="mkt-section mkt-roles" style="padding-top:3.5rem; padding-bottom:3.5rem;">
    <div class="mkt-container">
        <div class="mkt-section-head">
            <span class="mkt-kicker">Team management</span>
            <h2>The right access for every role</h2>
        </div>
        <div class="mkt-role-list">
            <div class="mkt-role-row">
                <div class="mkt-role-name">Owner</div>
                <div class="mkt-role-desc">Full access — every branch, all data, all settings</div>
                <div class="mkt-role-badge full">Full access</div>
            </div>
            <div class="mkt-role-row">
                <div class="mkt-role-name">Overall Manager</div>
                <div class="mkt-role-desc">Same operational access as owner, across all branches</div>
                <div class="mkt-role-badge">No billing</div>
            </div>
            <div class="mkt-role-row">
                <div class="mkt-role-name">Manager</div>
                <div class="mkt-role-desc">Runs inventory, staff, sales, and reports for their branch</div>
                <div class="mkt-role-badge">No settings</div>
            </div>
            <div class="mkt-role-row">
                <div class="mkt-role-name">Cashier</div>
                <div class="mkt-role-desc">Sells, views stock, prints receipts</div>
                <div class="mkt-role-badge">No access</div>
            </div>
            <div class="mkt-role-row">
                <div class="mkt-role-name">Staff</div>
                <div class="mkt-role-desc">HR and payroll record only — doesn't use the system directly</div>
                <div class="mkt-role-badge">No access</div>
            </div>
        </div>
    </div>
</section>

{{-- ── CTA — split: heading + action on the left, a short reassurance
     list on the right, instead of one plain centered block. ── --}}
<div class="mkt-cta-band">
    <div class="mkt-container mkt-cta-grid">
        <div>
            <h2>Ready to run your business smarter?</h2>
            <p>Free trial, no card needed.</p>
            <div class="mkt-cta-actions">
                @if(auth()->check())
                    <a href="{{ route('dashboard') }}" class="mkt-btn-primary-xl">Go to Dashboard &rarr;</a>
                @else
                    <a href="{{ route('register') }}" class="mkt-btn-primary-xl">Start your free trial &rarr;</a>
                    <a href="{{ route('contact') }}" class="mkt-btn-outline-xl">Talk to us</a>
                @endif
            </div>
        </div>
        <ul class="mkt-cta-list">
            <li>Full access to every feature during your trial</li>
            <li>No card required, cancel any time</li>
            <li>Set up your first store in under 10 minutes</li>
        </ul>
    </div>
</div>

@endsection
