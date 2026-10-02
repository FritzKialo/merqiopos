@extends('layouts.app')
@section('title', 'Branding & Customisation')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}?v={{ @filemtime(public_path('css/settings.css')) ?: '1' }}">
@endpush

@section('content')
<div class="page">

<div class="page-header">
    <div>
        <h1 class="page-title">Settings</h1>
        <p class="page-subtitle">Manage your business and account</p>
    </div>
</div>

<div class="settings-layout">

    @include('settings._nav')

    <div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('settings.branding.update') }}" enctype="multipart/form-data">
            @csrf

            {{-- Business Logo --}}
            <div class="settings-card" style="margin-bottom: var(--space-5);">
                <div class="settings-card-header">
                    <h2>Business Logo</h2>
                    <p>Shown on receipts, invoices, customer statements and your online shop.</p>
                </div>
                <div class="settings-card-body">
                    <div style="display:flex; align-items:center; gap:var(--space-4); flex-wrap:wrap;">
                        <div id="logo-preview-wrap" style="width:96px; height:96px; border:1px solid var(--color-border); border-radius:var(--radius); display:flex; align-items:center; justify-content:center; overflow:hidden; background:var(--color-surface-2); flex-shrink:0;">
                            @if($business->logo)
                                <img id="logo-preview-img" src="{{ asset('storage/' . $business->logo) }}" alt="{{ $business->name }}" style="max-width:100%; max-height:100%; object-fit:contain;">
                            @else
                                <span id="logo-preview-img" style="color:var(--color-text-muted); font-size:11px; text-align:center; padding:0 8px;">No logo yet</span>
                            @endif
                        </div>
                        <div style="flex:1; min-width:220px;">
                            <input type="file" name="logo" id="logo-input" class="form-control" accept="image/png,image/jpeg,image/webp" onchange="previewLogo(this)">
                            <span class="form-hint">PNG, JPG or WEBP, up to 2MB. Square images look best (e.g. 400&times;400px).</span>
                        </div>
                    </div>
                </div>
                @if($business->logo)
                <div class="settings-card-body" style="padding-top:0; border-top:1px solid var(--color-border);">
                    <button type="submit" form="remove-logo-form" class="btn btn-secondary" style="font-size:12px; padding:6px 12px;">Remove Logo</button>
                </div>
                @endif
            </div>

            {{-- Receipt Customisation --}}
            <div class="settings-card" style="margin-bottom: var(--space-5);">
                <div class="settings-card-header">
                    <h2>Receipt Customisation</h2>
                    <p>Text and styling shown on every printed or emailed receipt.</p>
                </div>
                <div class="settings-card-body">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Receipt Header Text</label>
                            <input type="text" name="receipt_header" class="form-control" value="{{ $business->receipt_header }}" placeholder="e.g. Visit us at ABC Street, Nairobi">
                            <span class="form-hint">Shown at the top of every receipt.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Receipt Tagline</label>
                            <input type="text" name="receipt_tagline" class="form-control" value="{{ $business->receipt_tagline }}" placeholder="e.g. Quality you can trust">
                            <span class="form-hint">Prominent text below business name.</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Receipt Footer Text</label>
                        <input type="text" name="receipt_footer" class="form-control" value="{{ $business->receipt_footer }}" placeholder="e.g. Thank you! Returns within 7 days with receipt.">
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Accent Colour</label>
                            <div style="display:flex; align-items:center; gap:var(--space-2);">
                                <input type="color" name="receipt_color" value="{{ $business->receipt_color ?? '#000000' }}" style="width:50px; height:36px; border:1px solid var(--color-border); border-radius:var(--radius); cursor:pointer;">
                                <span class="form-hint" style="margin-top:0;">Used for borders and accents on receipts</span>
                            </div>
                        </div>
                        <div class="form-group" style="display:flex; align-items:center;">
                            <label style="display:flex; align-items:center; gap:var(--space-2); cursor:pointer; margin-top:var(--space-4);">
                                <input type="checkbox" name="show_logo_on_receipt" value="1" {{ $business->show_logo_on_receipt ? 'checked' : '' }}>
                                Show Logo on Receipts
                            </label>
                        </div>
                        <div class="form-group" style="display:flex; align-items:center;">
                            <label style="display:flex; align-items:center; gap:var(--space-2); cursor:pointer;">
                                <input type="checkbox" name="show_shop_qr_on_receipt" value="1" {{ $business->show_shop_qr_on_receipt ? 'checked' : '' }}>
                                Print the shop QR code on receipts
                            </label>
                            <span class="form-hint" style="margin-left:8px;">Needs a public online store</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Invoice Customisation --}}
            <div class="settings-card" style="margin-bottom: var(--space-5);">
                <div class="settings-card-header">
                    <h2>Invoice Customisation</h2>
                    <p>Text shown on every invoice generated for this business.</p>
                </div>
                <div class="settings-card-body">
                    <div class="form-group">
                        <label class="form-label">Payment Terms</label>
                        <textarea name="invoice_terms" class="form-control" rows="3" placeholder="e.g. Payment due within 30 days of invoice date. Late payments subject to 2% monthly interest.">{{ $business->invoice_terms }}</textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Bank Details for Invoices</label>
                        <textarea name="invoice_bank_details" class="form-control" rows="3" placeholder="e.g. Bank: Equity Bank&#10;Account Name: ABC Company Ltd&#10;Account Number: 0123456789&#10;Branch: Nairobi">{{ $business->invoice_bank_details }}</textarea>
                    </div>
                    <label style="display:flex; align-items:center; gap:var(--space-2); cursor:pointer;">
                        <input type="checkbox" name="show_logo_on_invoice" value="1" {{ $business->show_logo_on_invoice ? 'checked' : '' }}>
                        Show Logo on Invoices
                    </label>
                </div>
            </div>

            {{-- Live Preview --}}
            <div class="settings-card" style="margin-bottom: var(--space-5);">
                <div class="settings-card-header">
                    <h2>Receipt Preview</h2>
                </div>
                <div class="settings-card-body">
                    <div id="receipt-preview" style="border:1px solid var(--color-border); border-radius:var(--radius); padding:var(--space-4); max-width:320px; font-family:monospace; font-size:12px; background:#fff;">
                        <div id="prev-logo-wrap" style="text-align:center; margin-bottom:6px; {{ ($business->show_logo_on_receipt && $business->logo) ? '' : 'display:none;' }}">
                            <img id="prev-logo-img" src="{{ $business->logo ? asset('storage/' . $business->logo) : '' }}" alt="" style="max-height:44px; max-width:100%;">
                        </div>
                        <div id="prev-header" style="text-align:center; padding:8px; font-weight:bold; border-bottom:2px solid #000; margin-bottom:8px;">
                            {{ $business->name }}
                        </div>
                        <div id="prev-tagline" style="text-align:center; font-size:11px; color:#555; margin-bottom:8px;">{{ $business->receipt_tagline }}</div>
                        <div id="prev-receipt-header" style="text-align:center; font-size:11px; margin-bottom:12px;">{{ $business->receipt_header }}</div>
                        <div style="border-top:1px dashed #ccc; border-bottom:1px dashed #ccc; padding:8px 0; margin-bottom:8px;">
                            <div style="display:flex; justify-content:space-between;"><span>Item 1 x1</span><span>KSh 100</span></div>
                            <div style="display:flex; justify-content:space-between;"><span>Item 2 x2</span><span>KSh 200</span></div>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-weight:bold;"><span>TOTAL</span><span>KSh 300</span></div>
                        <div id="prev-footer" style="text-align:center; font-size:10px; color:#555; margin-top:12px; border-top:1px dashed #ccc; padding-top:8px;">{{ $business->receipt_footer }}</div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Branding Settings</button>
            </div>
        </form>

        {{-- Separate form (not nested inside the main one) for the "Remove
             Logo" button above, associated via form="remove-logo-form". --}}
        <form method="POST" action="{{ route('settings.branding.logo.remove') }}" id="remove-logo-form" onsubmit="return confirm('Remove your business logo?')">
            @csrf
        </form>

    </div>
</div>
</div>{{-- end .page --}}

@endsection

@push('scripts')
<script>
function previewLogo(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = function (e) {
        const wrap = document.getElementById('logo-preview-wrap');
        wrap.innerHTML = '<img id="logo-preview-img" src="' + e.target.result + '" alt="Logo preview" style="max-width:100%; max-height:100%; object-fit:contain;">';

        // Reflect the newly chosen file in the receipt preview too, so
        // "Show Logo on Receipts" doesn't look like it's doing nothing
        // while a fresh (not-yet-saved) logo is selected.
        const prevImg = document.getElementById('prev-logo-img');
        if (prevImg) prevImg.src = e.target.result;
        updateLogoPreviewVisibility();
    };
    reader.readAsDataURL(input.files[0]);
}

function updateLogoPreviewVisibility() {
    const checkbox = document.querySelector('[name=show_logo_on_receipt]');
    const wrap = document.getElementById('prev-logo-wrap');
    const hasLogoSelected = document.getElementById('logo-input').files.length > 0;
    const hasSavedLogo = document.getElementById('prev-logo-img').getAttribute('src');
    wrap.style.display = (checkbox.checked && (hasLogoSelected || hasSavedLogo)) ? '' : 'none';
}
document.querySelector('[name=show_logo_on_receipt]')?.addEventListener('change', updateLogoPreviewVisibility);

function updatePreview() {
    document.getElementById('prev-tagline').textContent = document.querySelector('[name=receipt_tagline]').value;
    document.getElementById('prev-receipt-header').textContent = document.querySelector('[name=receipt_header]').value;
    document.getElementById('prev-footer').textContent = document.querySelector('[name=receipt_footer]').value;
    const color = document.querySelector('[name=receipt_color]').value;
    document.getElementById('prev-header').style.borderColor = color;
}
document.querySelectorAll('[name=receipt_tagline],[name=receipt_header],[name=receipt_footer],[name=receipt_color]').forEach(el => el.addEventListener('input', updatePreview));
</script>
@endpush
