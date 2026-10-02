@extends('layouts.app')
@section('title', 'Loyalty Tiers')

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
    <a href="{{ route('settings.loyalty') }}" class="btn btn-secondary">&larr; Back to Loyalty</a>
</div>

<div class="settings-layout">

    @include('settings._nav')

    <div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('settings.loyalty.tiers.save') }}">
            @csrf

            <div class="settings-card" style="margin-bottom: var(--space-5);">
                <div class="settings-card-header">
                    <h2>Loyalty Tiers</h2>
                    <p>Define tiers based on accumulated loyalty points. Higher tiers earn points faster and receive better discounts.</p>
                </div>
                <div class="settings-card-body">
                    <table class="settings-table" style="width:100%; border-collapse:collapse; margin-bottom: var(--space-3);" id="tiers-table">
                        <thead>
                            <tr style="border-bottom:1px solid var(--color-border);">
                                <th style="text-align:left;">Tier Name</th>
                                <th style="text-align:right; width:120px;">Min Points</th>
                                <th style="text-align:right; width:100px;">Multiplier</th>
                                <th style="text-align:right; width:100px;">Discount %</th>
                                <th style="text-align:left;">Perks</th>
                                <th style="width:40px;"></th>
                            </tr>
                        </thead>
                        <tbody id="tiers-body">
                            @foreach($tiers as $i => $tier)
                            <tr class="tier-row">
                                <td data-label="Tier Name"><input type="text" name="tier_name[]" class="form-control" value="{{ $tier['name'] }}" required></td>
                                <td data-label="Min Points"><input type="number" name="tier_min_points[]" class="form-control" value="{{ $tier['min_points'] }}" min="0"></td>
                                <td data-label="Multiplier"><input type="number" name="tier_multiplier[]" class="form-control" value="{{ $tier['multiplier'] }}" step="0.1" min="1"></td>
                                <td data-label="Discount %"><input type="number" name="tier_discount[]" class="form-control" value="{{ $tier['discount_pct'] }}" step="0.5" min="0" max="100"></td>
                                <td data-label="Perks"><input type="text" name="tier_perks[]" class="form-control" value="{{ $tier['perks'] }}"></td>
                                <td data-label=""><button type="button" onclick="this.closest('tr').remove()" style="background:none; border:none; color:var(--color-danger); cursor:pointer; font-size:1.2rem;">&times;</button></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <button type="button" id="add-tier" class="btn btn-secondary">Add Tier</button>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Tiers</button>
            </div>
        </form>

    </div>
</div>
</div>{{-- end .page --}}
@endsection

@push('scripts')
<script>
document.getElementById('add-tier').addEventListener('click', function() {
    const body = document.getElementById('tiers-body');
    const row = document.createElement('tr');
    row.className = 'tier-row';
    row.innerHTML = '<td data-label="Tier Name"><input type="text" name="tier_name[]" class="form-control" placeholder="e.g. Platinum" required></td><td data-label="Min Points"><input type="number" name="tier_min_points[]" class="form-control" value="0" min="0"></td><td data-label="Multiplier"><input type="number" name="tier_multiplier[]" class="form-control" value="1" step="0.1" min="1"></td><td data-label="Discount %"><input type="number" name="tier_discount[]" class="form-control" value="0" step="0.5" min="0" max="100"></td><td data-label="Perks"><input type="text" name="tier_perks[]" class="form-control" placeholder="Benefits..."></td><td data-label=""><button type="button" onclick="this.closest(\'tr\').remove()" style="background:none;border:none;color:var(--color-danger);cursor:pointer;font-size:1.2rem;">&times;</button></td>';
    body.appendChild(row);
});
</script>
@endpush
