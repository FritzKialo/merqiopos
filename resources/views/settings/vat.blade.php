@extends('layouts.app')
@section('title', 'VAT Settings')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}?v={{ @filemtime(public_path('css/settings.css')) ?: '1' }}">
@endpush
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">VAT Settings</h1>
            <p class="page-subtitle">Configure VAT registration and default rate</p>
        </div>
    </div>
<div class="settings-layout">
@include('settings._nav')
<div>

    @if(session('success')) <div class="alert alert--success">{{ session('success') }}</div> @endif
    @if($errors->any())
        <div class="alert alert--error">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
    @endif

    <div class="settings-card" style="max-width:600px;">
        <div class="settings-card-body">
            <form method="POST" action="{{ route('settings.vat.update') }}">
                @csrf

                <div class="form-group">
                    <label class="form-label" style="display:flex;align-items:center;gap:0.75rem;cursor:pointer;">
                        <input type="hidden" name="vat_registered" value="0">
                        <input type="checkbox" name="vat_registered" value="1"
                               {{ $business->vat_registered ? 'checked' : '' }}
                               id="vatToggle" onchange="toggleVatFields()">
                        <span>Business is VAT Registered</span>
                    </label>
                </div>

                <div id="vatFields" style="{{ $business->vat_registered ? '' : 'display:none;' }}">
                    <div class="form-group">
                        <label class="form-label">VAT Registration Number</label>
                        <input type="text" name="vat_number" class="form-control"
                               value="{{ old('vat_number', $business->vat_number) }}"
                               placeholder="e.g. P051234567A">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Default VAT Rate (%)</label>
                        <input type="number" name="vat_rate" class="form-control"
                               value="{{ old('vat_rate', $business->vat_rate ?? 16) }}"
                               min="0" max="100" step="0.01">
                        <span class="form-hint">Kenya standard VAT is 16%. Applies to every sale unless you set up
                            <a href="{{ route('settings.tax-rules.index') }}">Tax Rules</a> for multiple rates or
                            per-product exemptions.</span>
                    </div>
                </div>

                <button type="submit" class="btn btn--primary">Save VAT Settings</button>
            </form>
        </div>
    </div>
</div>
</div>
</div>

<script>
function toggleVatFields() {
    const show = document.getElementById('vatToggle').checked;
    document.getElementById('vatFields').style.display = show ? '' : 'none';
}
</script>
@endsection
