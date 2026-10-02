@extends('layouts.app')
@section('title', 'Currency Settings')

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
        @if($errors->any())
            <div class="alert alert-danger">
                <ul style="margin:0; padding-left:20px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="settings-card" style="margin-bottom: var(--space-5);">
            <div class="settings-card-header">
                <h2>Configured Currencies</h2>
                <p>All sales, invoices and financial reports are recorded and shown in KES. The rates below are saved for your own reference — nothing in the app converts amounts with them yet.</p>
            </div>
            <div class="settings-card-body">
                <div class="alert alert--info" style="margin-bottom: var(--space-4);">
                    <strong>Base Currency: KES (Kenyan Shilling)</strong>
                </div>

                @if($rates->isEmpty())
                    <p style="color: var(--color-text-muted); text-align:center; padding: var(--space-5) 0;">No foreign currencies configured yet.</p>
                @else
                    <table class="settings-table" style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="border-bottom:1px solid var(--color-border); background:var(--color-surface-2);">
                                <th style="text-align:left;">Currency</th>
                                <th style="text-align:left;">Code</th>
                                <th style="text-align:right;">Rate (KES per 1 unit)</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rates as $rate)
                            <tr style="border-bottom:1px solid var(--color-border);">
                                <td data-label="Currency">
                                    @if(isset($commonCurrencies[$rate->currency_code]))
                                        {{ $commonCurrencies[$rate->currency_code]['name'] }}
                                        <span style="color: var(--color-text-muted); font-size:0.85rem;">({{ $commonCurrencies[$rate->currency_code]['symbol'] }})</span>
                                    @else
                                        {{ $rate->currency_code }}
                                    @endif
                                </td>
                                <td data-label="Code" style="font-family:monospace; font-weight:600;">{{ $rate->currency_code }}</td>
                                <td data-label="Rate" style="text-align:right;">
                                    <form method="POST" action="{{ route('settings.currencies.update', $rate) }}" style="display:flex; gap:8px; justify-content:flex-end; align-items:center;">
                                        @csrf @method('PUT')
                                        <input type="number" name="rate" class="form-control" step="0.0001" min="0.0001" value="{{ $rate->rate }}" style="width:120px; text-align:right;">
                                        <button type="submit" class="btn btn-primary" style="padding:4px 12px; font-size:0.85rem;">Update</button>
                                    </form>
                                </td>
                                <td data-label="Actions" style="text-align:right;">
                                    <form method="POST" action="{{ route('settings.currencies.destroy', $rate) }}" onsubmit="return confirm('Remove this currency?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-danger" style="padding:4px 12px; font-size:0.85rem;">Remove</button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <div class="settings-card">
            <div class="settings-card-header">
                <h2>Add Currency</h2>
            </div>
            <div class="settings-card-body">
                <form method="POST" action="{{ route('settings.currencies.store') }}">
                    @csrf
                    <div class="settings-grid-3" style="display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr) auto; gap: var(--space-4); align-items:flex-end;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Currency</label>
                            <select id="currency-select" class="form-control" onchange="prefillRate(this.value)">
                                <option value="">— Select common or enter below —</option>
                                @foreach($commonCurrencies as $code => $info)
                                    @if(!$rates->pluck('currency_code')->contains($code))
                                    <option value="{{ $code }}" data-rate="{{ $info['default_rate'] }}">{{ $info['name'] }} ({{ $code }})</option>
                                    @endif
                                @endforeach
                                <option value="_custom">Custom currency code...</option>
                            </select>
                            <input type="text" name="currency_code" id="currency-code-input" class="form-control" style="margin-top:8px; text-transform:uppercase;" placeholder="3-letter code e.g. AED" maxlength="3" pattern="[A-Z]{3}" required>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Rate (KES per 1 unit) *</label>
                            <input type="number" name="rate" id="rate-input" class="form-control" step="0.0001" min="0.0001" required placeholder="e.g. 130.0000">
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary">Add Currency</button>
                        </div>
                    </div>
                </form>

                <div style="margin-top: var(--space-4); padding: var(--space-3); background:var(--color-surface-2); border-radius:var(--radius);">
                    <strong style="font-size:0.9rem;">Common Rates Reference</strong>
                    <div style="display:flex; flex-wrap:wrap; gap: var(--space-4); margin-top: var(--space-2);">
                        @foreach($commonCurrencies as $code => $info)
                        <span style="color: var(--color-text-muted); font-size:0.85rem;">{{ $code }}: ~{{ $info['default_rate'] >= 1 ? 'KSh '.number_format($info['default_rate'], 2) : number_format($info['default_rate'], 4) }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
</div>{{-- end .page --}}
@endsection

@push('scripts')
<script>
const commonRates = @json($commonCurrencies);

function prefillRate(code) {
    const input = document.getElementById('currency-code-input');
    const rateInput = document.getElementById('rate-input');
    if (code && code !== '_custom' && commonRates[code]) {
        input.value = code;
        rateInput.value = commonRates[code].default_rate;
    } else if (code === '_custom') {
        input.value = '';
        input.focus();
    } else {
        input.value = '';
    }
}

document.getElementById('currency-code-input').addEventListener('input', function () {
    this.value = this.value.toUpperCase();
});
</script>
@endpush
