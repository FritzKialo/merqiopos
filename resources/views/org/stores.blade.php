@extends('layouts.org')
@section('title', 'Stores')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}?v={{ @filemtime(public_path('css/settings.css')) ?: '1' }}">
@endpush

@section('content')
<div class="page">

<div class="page-header">
    <div>
        <h1 class="page-title">Stores</h1>
        <p class="page-subtitle">
            {{-- planName() returns "Trial" while on trial — not useful here,
            since this is explaining a concrete resource limit, so the real
            underlying plan name is shown instead. --}}
            {{ $businesses->count() }} /
            {{ $organization->storeLimit() === -1 ? '∞' : $organization->storeLimit() }}
            stores on the <strong>{{ $organization->planConfig()['name'] }}</strong> plan.
        </p>
    </div>
    @if($organization->canAddStore())
        <a href="{{ route('org.stores.create') }}" class="btn btn-primary">
             Add Store
        </a>
    @else
        <a href="{{ route('settings.subscription') }}" class="btn btn-secondary">
             Upgrade to Add More
        </a>
    @endif
</div>

@if($businesses->isEmpty())
    <div class="empty-state">
        
        <p>No stores yet. Add your first store to get started.</p>
        <a href="{{ route('org.stores.create') }}" class="btn btn-primary">
             Add First Store
        </a>
    </div>
@else
    <div class="table-card">
        <table class="table">
            <thead>
                <tr>
                    <th>Store</th>
                    <th>Type</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Staff</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($businesses as $business)
                <tr>
                    <td data-label="Store">
                        <div style="font-weight: 600;">{{ $business->name }}</div>
                        @if($business->city)
                            <div style="font-size: 0.78rem; color: var(--color-text-muted);">
                                 {{ $business->city }}
                            </div>
                        @endif
                    </td>
                    <td data-label="Type">
                        <span class="store-type-badge">
                            {{ ucfirst($business->business_type ?? 'Retail') }}
                        </span>
                    </td>
                    <td data-label="Email" style="color: var(--color-text-muted); font-size: 0.85rem;">
                        {{ $business->email }}
                    </td>
                    <td data-label="Phone" style="color: var(--color-text-muted); font-size: 0.85rem;">
                        {{ $business->phone }}
                    </td>
                    <td data-label="Staff" style="text-align: center;">
                        <span class="badge badge-secondary">{{ $business->users_count }}</span>
                    </td>
                    <td data-label="Status">
                        @if($business->isLockedByPlan())
                            <span class="badge badge-warning">Read-only (plan limit)</span>
                        @else
                            <span class="badge badge-{{ $business->isActive() ? 'success' : 'danger' }}">
                                {{ $business->isActive() ? 'Active' : 'Inactive' }}
                            </span>
                        @endif
                    </td>
                    <td data-label="Actions" style="text-align: right;">
                        <div style="display: flex; gap: var(--space-2); justify-content: flex-end;">
                            <form method="POST" action="{{ route('org.switch', $business) }}" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-sm" title="Enter store">
                                     Enter
                                </button>
                            </form>
                            <a href="{{ route('org.stores.edit', $business) }}"
                               class="btn btn-secondary btn-sm" title="Edit store">
                                Edit
                            </a>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- Plan limit warning --}}
@if(!$organization->canAddStore() && $organization->storeLimit() !== -1)
    <div class="alert alert-warning" style="margin-top: var(--space-4);">
        
        {{-- planName() returns "Trial" while on trial — see the comment above
        this file's other plan-name reference. --}}
        You've reached the {{ $organization->storeLimit() }}-store limit on the
        <strong>{{ $organization->planConfig()['name'] }}</strong> plan.
        <a href="{{ route('settings.subscription') }}" style="font-weight: 600;">
            Upgrade to add more stores.
        </a>
    </div>
@endif

</div>
@endsection
