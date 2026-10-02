@extends('layouts.app')
@section('title', 'New Supplier')

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title"> New Supplier</h1>
        <p class="page-subtitle">Add a new supplier to your business</p>
    </div>
    <a href="{{ route('suppliers.index') }}" class="btn btn-outline">&#8592; Back</a>
</div>

<div class="form-card" style="max-width: 640px;">
    <form method="POST" action="{{ route('suppliers.store') }}">
        @csrf

        <div class="form-group">
            <label class="form-label" for="name">Supplier Name *</label>
            <input type="text" id="name" name="name"
                   class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
                   value="{{ old('name') }}"
                   placeholder="e.g. Nairobi Wholesale Suppliers Ltd"
                   required autofocus>
            @error('name')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label class="form-label" for="contact_person">Contact Person</label>
                <input type="text" id="contact_person" name="contact_person"
                       class="form-control {{ $errors->has('contact_person') ? 'is-invalid' : '' }}"
                       value="{{ old('contact_person') }}"
                       placeholder="e.g. James Kamau">
                @error('contact_person')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="account_number">Account Number</label>
                <input type="text" id="account_number" name="account_number"
                       class="form-control {{ $errors->has('account_number') ? 'is-invalid' : '' }}"
                       value="{{ old('account_number') }}"
                       placeholder="e.g. SUP-001">
                @error('account_number')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label class="form-label" for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone"
                       class="form-control {{ $errors->has('phone') ? 'is-invalid' : '' }}"
                       value="{{ old('phone') }}"
                       placeholder="07XX XXX XXX">
                @error('phone')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" id="email" name="email"
                       class="form-control {{ $errors->has('email') ? 'is-invalid' : '' }}"
                       value="{{ old('email') }}"
                       placeholder="supplier@example.com">
                @error('email')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="address">Address</label>
            <textarea id="address" name="address"
                      class="form-control"
                      rows="2"
                      placeholder="e.g. Industrial Area, Nairobi">{{ old('address') }}</textarea>
        </div>

        <div class="form-group">
            <label class="form-label" for="notes">Notes</label>
            <textarea id="notes" name="notes"
                      class="form-control"
                      rows="2"
                      placeholder="Any additional notes about this supplier">{{ old('notes') }}</textarea>
        </div>

        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.9rem; color: var(--color-text);">
                <input type="checkbox" name="is_active" value="1"
                       {{ old('is_active', '1') ? 'checked' : '' }}>
                Active Supplier
            </label>
            <small class="form-hint">Inactive suppliers won't appear in purchase order dropdowns.</small>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                 Save Supplier
            </button>
            <a href="{{ route('suppliers.index') }}" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

@endsection
