@extends('layouts.app')
@section('title', 'Add Asset')
@push('styles')
<style>
@media (max-width: 640px) {
    .asset-form-grid { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page-header">
    <h1>Add Asset</h1>
    <a href="{{ route('assets.index') }}" class="btn btn-secondary">← Back</a>
</div>

<div class="card" style="max-width:600px;">
    <div class="card-body">
        @if($errors->any())
            <div class="alert alert-danger" style="margin-bottom:16px;">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('assets.store') }}">
            @csrf
            <div class="asset-form-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div style="grid-column:1/-1;">
                    <label class="form-label">Asset Name *</label>
                    <input type="text" name="name" class="form-control" required value="{{ old('name') }}">
                </div>
                <div>
                    <label class="form-label">Category *</label>
                    <input type="text" name="category" class="form-control" required value="{{ old('category') }}" placeholder="e.g. Vehicle, Equipment">
                </div>
                <div>
                    <label class="form-label">Purchase Cost (KSh) *</label>
                    <input type="number" name="purchase_cost" class="form-control" required step="0.01" min="0" value="{{ old('purchase_cost') }}">
                </div>
                <div>
                    <label class="form-label">Purchase Date *</label>
                    <input type="date" name="purchase_date" class="form-control" required value="{{ old('purchase_date', now()->toDateString()) }}">
                </div>
                <div>
                    <label class="form-label">Depreciation *</label>
                    <select name="depreciation_method" class="form-control" required>
                        <option value="straight_line" {{ old('depreciation_method', 'straight_line') === 'straight_line' ? 'selected' : '' }}>Straight-line</option>
                        <option value="none" {{ old('depreciation_method') === 'none' ? 'selected' : '' }}>None</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Useful Life (years) *</label>
                    <input type="number" name="useful_life_years" class="form-control" required min="1" value="{{ old('useful_life_years', 5) }}">
                </div>
                <div>
                    <label class="form-label">Residual / Salvage Value (KSh)</label>
                    <input type="number" name="residual_value" class="form-control" step="0.01" min="0" value="{{ old('residual_value', 0) }}">
                </div>
                <div style="grid-column:1/-1;">
                    <label class="form-label">Description (serial no., location, etc.)</label>
                    <input type="text" name="description" class="form-control" value="{{ old('description') }}">
                </div>
                <div style="grid-column:1/-1;">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
                </div>
            </div>
            <div style="margin-top:20px;">
                <button type="submit" class="btn btn-primary">Save Asset</button>
                <a href="{{ route('assets.index') }}" class="btn btn-secondary" style="margin-left:8px;">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
