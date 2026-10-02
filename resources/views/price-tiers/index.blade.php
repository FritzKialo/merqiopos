@extends('layouts.app')
@section('title', 'Price Tiers')
@push('styles')
<style>
@media (max-width: 700px) {
    .pt-layout-grid { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')

<div class="page-header">
    <h1 class="page-title">Price Tiers</h1>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="pt-layout-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-lg); align-items:start;">

    {{-- Add Tier Form --}}
    <div class="form-card">
        <h3 style="margin:0 0 var(--space-md); font-size:var(--text-base);">Add Price Tier</h3>
        <form method="POST" action="{{ route('price-tiers.store') }}">
            @csrf
            <div class="form-group">
                <label class="form-label">Name *</label>
                <input type="text" name="name" class="form-control" required placeholder="e.g. Wholesale">
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <input type="text" name="description" class="form-control" placeholder="Optional">
            </div>
            <div class="form-group">
                <label class="form-label" style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" name="is_default" value="1">
                    Set as Default Tier
                </label>
            </div>
            <button type="submit" class="btn btn-primary">+ Add Tier</button>
        </form>
    </div>

    {{-- Tiers List --}}
    <div>
        @forelse($tiers as $tier)
        <div class="form-card" style="margin-bottom:var(--space-md);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-sm);">
                <form method="POST" action="{{ route('price-tiers.update', $tier) }}" style="display:flex; gap:8px; align-items:center; flex:1; flex-wrap:wrap;">
                    @csrf @method('PUT')
                    <input type="text" name="name" value="{{ $tier->name }}" class="form-control" required style="width:160px;">
                    <input type="text" name="description" value="{{ $tier->description }}" class="form-control" placeholder="Description" style="flex:1; min-width:140px;">
                    <label style="display:inline-flex; align-items:center; gap:4px; font-size:0.8rem; white-space:nowrap;">
                        <input type="checkbox" name="is_default" value="1" {{ $tier->is_default ? 'checked' : '' }}> Default
                    </label>
                    <button type="submit" class="btn btn-secondary" style="font-size:0.75rem; padding:4px 8px;">Save</button>
                </form>
                <div style="display:flex; gap:6px; margin-left:8px;">
                    <form method="POST" action="{{ route('price-tiers.destroy', $tier) }}" onsubmit="return confirm('Delete tier?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger" style="font-size:0.75rem; padding:4px 8px;">Delete</button>
                    </form>
                </div>
            </div>
            <p style="margin:0; font-size:0.8rem; color:var(--color-text-muted);">
                {{ $tier->customers->count() }} customer(s) on this tier
            </p>
            @if($tier->customers->isNotEmpty())
            <div style="margin-top:6px; display:flex; flex-wrap:wrap; gap:4px;">
                @foreach($tier->customers->take(5) as $c)
                    <a href="{{ route('customers.show', $c) }}" class="badge badge-secondary">{{ $c->name }}</a>
                @endforeach
                @if($tier->customers->count() > 5)
                    <span class="badge badge-secondary">+{{ $tier->customers->count() - 5 }} more</span>
                @endif
            </div>
            @endif
        </div>
        @empty
            <p style="color:var(--color-text-muted);">No tiers yet.</p>
        @endforelse
    </div>

</div>

@endsection
