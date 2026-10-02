@extends('layouts.app')
@section('title', 'Loyalty Program')

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

        <div class="settings-card">
            <div class="settings-card-header">
                <h2>Loyalty Program</h2>
                <p>Reward repeat customers with points on every sale.</p>
            </div>
            <div class="settings-card-body">
                <form method="POST" action="{{ route('settings.loyalty.update') }}">
                    @csrf @method('POST')

                    <div class="form-group" style="display:flex; align-items:center; gap: var(--space-3);">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" id="loyalty_active"
                               {{ $program?->is_active ? 'checked' : '' }}
                               style="width:18px; height:18px; cursor:pointer;">
                        <label for="loyalty_active" style="font-weight:600; cursor:pointer;">Enable Loyalty Program</label>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Program Name</label>
                        <input type="text" name="name" class="form-control"
                               value="{{ old('name', $program?->name ?? 'Loyalty Program') }}" required>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Points per KSh 1 Spent</label>
                            <input type="number" name="points_per_shilling" class="form-control" step="0.0001" min="0"
                                   value="{{ old('points_per_shilling', $program?->points_per_shilling ?? 1.0) }}" required>
                            <span class="form-hint">e.g. 1 = earn 1 point per KSh</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">KSh Value per Point (Redemption Rate)</label>
                            <input type="number" name="redemption_rate" class="form-control" step="0.0001" min="0"
                                   value="{{ old('redemption_rate', $program?->redemption_rate ?? 0.01) }}" required>
                            <span class="form-hint">e.g. 0.01 = 100 pts = KSh 1</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Minimum Points for Redemption</label>
                        <input type="number" name="min_redemption_points" class="form-control" min="1"
                               value="{{ old('min_redemption_points', $program?->min_redemption_points ?? 100) }}" required>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Loyalty Settings</button>
                        <a href="{{ route('settings.loyalty.tiers') }}" class="btn btn-secondary">Manage Tiers</a>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>
</div>{{-- end .page --}}
@endsection
