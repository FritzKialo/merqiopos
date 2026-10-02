@extends('layouts.app')
@section('title', 'Edit Asset')
@push('styles')
<style>
@media (max-width: 640px) {
    .asset-form-grid { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page-header">
    <h1>Edit Asset</h1>
    <a href="{{ route('assets.index') }}" class="btn btn-secondary">← Back</a>
</div>

<div class="card" style="max-width:600px;">
    <div class="card-body">
        @if($errors->any())
            <div class="alert alert-danger" style="margin-bottom:16px;">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('assets.update', $asset) }}">
            @csrf @method('PUT')
            <div class="asset-form-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div style="grid-column:1/-1;">
                    <label class="form-label">Asset Name *</label>
                    <input type="text" name="name" class="form-control" required value="{{ old('name', $asset->name) }}">
                </div>
                <div>
                    <label class="form-label">Category *</label>
                    <input type="text" name="category" class="form-control" required value="{{ old('category', $asset->category) }}">
                </div>
                <div>
                    <label class="form-label">Purchase Cost (KSh) *</label>
                    <input type="number" name="purchase_cost" class="form-control" required step="0.01" value="{{ old('purchase_cost', $asset->purchase_cost) }}">
                </div>
                <div>
                    <label class="form-label">Purchase Date *</label>
                    <input type="date" name="purchase_date" class="form-control" required value="{{ old('purchase_date', $asset->purchase_date?->toDateString()) }}">
                </div>
                <div>
                    <label class="form-label">Depreciation *</label>
                    <select name="depreciation_method" class="form-control" required>
                        @foreach(['straight_line' => 'Straight-line', 'none' => 'None'] as $val => $lbl)
                        <option value="{{ $val }}" {{ old('depreciation_method', $asset->depreciation_method) === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Useful Life (years) *</label>
                    <input type="number" name="useful_life_years" class="form-control" required min="1" value="{{ old('useful_life_years', $asset->useful_life_years) }}">
                </div>
                <div>
                    <label class="form-label">Residual / Salvage Value (KSh)</label>
                    <input type="number" name="residual_value" class="form-control" step="0.01" value="{{ old('residual_value', $asset->residual_value) }}">
                </div>
                <div style="grid-column:1/-1;">
                    <label class="form-label">Description (serial no., location, etc.)</label>
                    <input type="text" name="description" class="form-control" value="{{ old('description', $asset->description) }}">
                </div>
                <div style="grid-column:1/-1;">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3">{{ old('notes', $asset->notes) }}</textarea>
                </div>
            </div>
            <div style="margin-top:20px;">
                <button type="submit" class="btn btn-primary">Update Asset</button>
                <a href="{{ route('assets.index') }}" class="btn btn-secondary" style="margin-left:8px;">Cancel</a>
            </div>
        </form>
    </div>
</div>

<div class="card" style="max-width:600px; margin-top:1rem;">
    <div class="card-body">
        @include('partials.attachments', ['modelType' => 'Asset', 'modelId' => $asset->id])
    </div>
</div>
@endsection
