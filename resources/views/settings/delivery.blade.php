@extends('layouts.app')
@section('title', 'Delivery Settings')

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

        <form method="POST" action="{{ route('settings.delivery.update') }}">
            @csrf

            <div class="settings-card" style="margin-bottom: var(--space-5);">
                <div class="settings-card-header">
                    <h2>Flat Rate Delivery</h2>
                </div>
                <div class="settings-card-body">
                    <div class="form-group" style="max-width:200px;">
                        <label class="form-label">Default Delivery Fee (KSh)</label>
                        <input type="number" name="delivery_fee" class="form-control" value="{{ $business->delivery_fee ?? 0 }}" step="0.01" min="0">
                        <span class="form-hint">Set to 0 for free delivery. Applied to all online orders.</span>
                    </div>
                </div>
            </div>

            <div class="settings-card" style="margin-bottom: var(--space-5);">
                <div class="settings-card-header">
                    <h2>Delivery Zones (Optional)</h2>
                    <p>Define different fees per zone. If zones are set, customers choose at checkout.</p>
                </div>
                <div class="settings-card-body">
                    <table class="settings-table" style="width:100%; border-collapse:collapse; margin-bottom: var(--space-3);" id="zones-table">
                        <thead>
                            <tr style="border-bottom:1px solid var(--color-border);">
                                <th style="text-align:left;">Zone Name</th>
                                <th style="text-align:right; width:150px;">Fee (KSh)</th>
                                <th style="width:40px;"></th>
                            </tr>
                        </thead>
                        <tbody id="zones-body">
                            @foreach($zones as $zone)
                            <tr class="zone-row">
                                <td data-label="Zone Name"><input type="text" name="zone_name[]" class="form-control" value="{{ $zone['name'] }}"></td>
                                <td data-label="Fee (KSh)"><input type="number" name="zone_fee[]" class="form-control" value="{{ $zone['fee'] }}" min="0" step="0.01"></td>
                                <td data-label=""><button type="button" onclick="this.closest('tr').remove()" style="background:none; border:none; color:var(--color-danger); cursor:pointer; font-size:1.2rem;">&times;</button></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <button type="button" id="add-zone" class="btn btn-secondary">Add Zone</button>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Delivery Settings</button>
            </div>
        </form>

    </div>
</div>
</div>{{-- end .page --}}
@endsection

@push('scripts')
<script>
document.getElementById('add-zone').addEventListener('click', function() {
    const body = document.getElementById('zones-body');
    const row = document.createElement('tr');
    row.className = 'zone-row';
    row.innerHTML = '<td data-label="Zone Name"><input type="text" name="zone_name[]" class="form-control" placeholder="e.g. CBD, Westlands"></td><td data-label="Fee (KSh)"><input type="number" name="zone_fee[]" class="form-control" value="0" min="0" step="0.01"></td><td data-label=""><button type="button" onclick="this.closest(\'tr\').remove()" style="background:none;border:none;color:var(--color-danger);cursor:pointer;font-size:1.2rem;">&times;</button></td>';
    body.appendChild(row);
});
</script>
@endpush
