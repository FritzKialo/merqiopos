@extends('layouts.app')
@section('title', 'Manage Tables')
@push('styles')
<style>
@media (max-width: 700px) {
    .tables-manage-grid { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page-header">
    <h1>Manage Tables</h1>
    <a href="{{ route('tables.floor') }}" class="btn btn-secondary">← Floor Plan</a>
</div>

<div class="tables-manage-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:24px; max-width:900px;">
    <div class="card">
        <div class="card-body">
            <h3 style="margin:0 0 16px;">Add Table</h3>
            @if($errors->any())
                <div class="alert alert-danger" style="margin-bottom:12px;">{{ $errors->first() }}</div>
            @endif
            <form method="POST" action="{{ route('tables.store') }}">
                @csrf
                <div style="margin-bottom:12px;">
                    <label class="form-label">Table Number *</label>
                    <input type="text" name="number" class="form-control" required maxlength="20" value="{{ old('number') }}" placeholder="e.g. 1, A2, VIP">
                </div>
                <div style="margin-bottom:12px;">
                    <label class="form-label">Name (optional)</label>
                    <input type="text" name="name" class="form-control" maxlength="100" value="{{ old('name') }}" placeholder="e.g. Window table, VIP Room">
                </div>
                <div style="margin-bottom:16px;">
                    <label class="form-label">Capacity (seats) *</label>
                    <input type="number" name="capacity" class="form-control" min="1" required value="{{ old('capacity', 4) }}">
                </div>
                <button type="submit" class="btn btn-primary">Add Table</button>
            </form>
        </div>
    </div>

    @if(auth()->user()->hasAnyRole('owner', 'manager'))
    <div class="card" style="margin-bottom:16px;">
        <div class="card-body">
            <h3 style="margin:0 0 4px;">Service charge</h3>
            <p style="color:#888;font-size:.85rem;margin:0 0 12px;">Added to every new table bill. You can still change it on an individual order. Leave at 0 for none.</p>
            <form method="POST" action="{{ route('tables.service-charge.default') }}" style="display:flex;gap:8px;align-items:end;max-width:320px;">
                @csrf
                <div style="flex:1;">
                    <label class="form-label">Default %</label>
                    <input type="number" name="service_charge_percent" class="form-control" min="0" max="30" step="0.5" value="{{ (float) (auth()->user()->currentBusiness()->service_charge_percent ?? 0) }}">
                </div>
                <button type="submit" class="btn btn-primary">Save</button>
            </form>
        </div>
    </div>
    @endif

    <div class="card">
        <div class="card-body">
            <h3 style="margin:0 0 16px;">Existing Tables ({{ $tables->count() }})</h3>
            @if($tables->isEmpty())
            <p style="color:#888; font-size:0.9rem;">No tables set up yet.</p>
            @else
            @foreach($tables as $table)
            <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid #e0e0e0; flex-wrap:wrap; gap:8px;">
                <div>
                    <span style="font-weight:bold;">Table {{ $table->number }}</span>
                    <span style="font-size:0.8rem; color:#888;">{{ $table->name ? ' · '.$table->name : '' }} · {{ $table->capacity }} seats</span>
                </div>
                <div style="display:flex; gap:6px;">
                    <form method="POST" action="{{ route('tables.destroy', $table) }}" onsubmit="return confirm('Delete table?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </div>
            </div>
            @endforeach
            @endif
        </div>
    </div>
</div>
@endsection
