@extends('layouts.app')
@section('title', 'Add Service')
@push('styles')
<style>
@media (max-width: 420px) {
    .service-form-grid { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page">
    <div class="page-header">
        <h1 class="page-title">Add Service</h1>
        <a href="{{ route('services.index') }}" class="btn btn-secondary">Back</a>
    </div>
    <div class="table-card" style="padding:1.5rem;max-width:520px;">
        @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif
        <form method="POST" action="{{ route('services.store') }}">
            @csrf
            <div style="display:flex;flex-direction:column;gap:1rem;">
                <div>
                    <label style="font-weight:600;font-size:0.85rem;display:block;margin-bottom:0.25rem;">Name *</label>
                    <input type="text" name="name" class="form-control" required value="{{ old('name') }}">
                </div>
                <div>
                    <label style="font-weight:600;font-size:0.85rem;display:block;margin-bottom:0.25rem;">Category</label>
                    <input type="text" name="category" class="form-control" value="{{ old('category') }}" placeholder="e.g. Hair, Massage…">
                </div>
                <div>
                    <label style="font-weight:600;font-size:0.85rem;display:block;margin-bottom:0.25rem;">Description</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                </div>
                <div class="service-form-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div>
                        <label style="font-weight:600;font-size:0.85rem;display:block;margin-bottom:0.25rem;">Duration (minutes) *</label>
                        <input type="number" name="duration_minutes" class="form-control" min="5" required value="{{ old('duration_minutes', 30) }}">
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:0.85rem;display:block;margin-bottom:0.25rem;">Price (KES) *</label>
                        <input type="number" name="price" class="form-control" step="0.01" min="0" required value="{{ old('price') }}">
                    </div>
                </div>
                <div>
                    <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                        <span style="font-weight:600;font-size:0.85rem;">Active</span>
                    </label>
                </div>
            </div>
            <div style="margin-top:1.25rem;display:flex;gap:0.5rem;">
                <button type="submit" class="btn btn-primary">Save Service</button>
                <a href="{{ route('services.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
