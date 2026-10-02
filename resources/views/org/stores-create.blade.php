@extends('layouts.org')
@section('title', 'Add New Store')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}?v={{ @filemtime(public_path('css/settings.css')) ?: '1' }}">
@endpush

@section('content')
<div class="page">

<div class="page-header">
    <div>
        <h1 class="page-title">Add New Store</h1>
        <p class="page-subtitle">
            Create a new store under <strong>{{ $organization->name }}</strong>.
            You have {{ $organization->businesses->count() }} /
            {{ $organization->storeLimit() === -1 ? '∞' : $organization->storeLimit() }} stores.
        </p>
    </div>
    <a href="{{ route('org.dashboard') }}" class="btn btn-secondary">
         Back
    </a>
</div>

<div class="settings-card" style="max-width: 680px;">
    <div class="settings-card-header">
        <h2> Store Details</h2>
        <p>Basic information about this store. You can update these any time.</p>
    </div>
    <div class="settings-card-body">

        @if($errors->any())
            <div class="alert alert-danger" style="margin-bottom: var(--space-4);">
                <ul style="margin: 0; padding-left: 1.2em;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('org.stores.store') }}">
            @csrf

            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label" for="name">Store Name *</label>
                    <input type="text" id="name" name="name"
                        class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
                        value="{{ old('name') }}"
                        placeholder="e.g. Westlands Branch"
                        required>
                    @error('name')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="business_type">Business Type</label>
                    <select id="business_type" name="business_type"
                        class="form-control {{ $errors->has('business_type') ? 'is-invalid' : '' }}">
                        <option value="">— Select type —</option>
                        @foreach($businessTypes as $value => $label)
                            <option value="{{ $value }}" {{ old('business_type') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('business_type')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Store Email *</label>
                    <input type="email" id="email" name="email"
                        class="form-control {{ $errors->has('email') ? 'is-invalid' : '' }}"
                        value="{{ old('email') }}"
                        placeholder="store@yourbusiness.com"
                        required>
                    @error('email')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">Phone Number *</label>
                    <input type="text" id="phone" name="phone"
                        class="form-control {{ $errors->has('phone') ? 'is-invalid' : '' }}"
                        value="{{ old('phone') }}"
                        placeholder="0712 345 678"
                        required>
                    @error('phone')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="address">Address</label>
                    <input type="text" id="address" name="address"
                        class="form-control {{ $errors->has('address') ? 'is-invalid' : '' }}"
                        value="{{ old('address') }}"
                        placeholder="Street address">
                    @error('address')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="city">City / Town</label>
                    <input type="text" id="city" name="city"
                        class="form-control {{ $errors->has('city') ? 'is-invalid' : '' }}"
                        value="{{ old('city') }}"
                        placeholder="Nairobi">
                    @error('city')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="form-actions" style="margin-top: var(--space-5);">
                <button type="submit" class="btn btn-primary">
                     Create Store
                </button>
                <a href="{{ route('org.dashboard') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

</div>
@endsection
