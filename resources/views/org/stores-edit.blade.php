@extends('layouts.org')
@section('title', 'Edit Store — ' . $business->name)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}?v={{ @filemtime(public_path('css/settings.css')) ?: '1' }}">
@endpush

@section('content')
<div class="page">

<div class="page-header">
    <div>
        <h1 class="page-title">Edit Store</h1>
        <p class="page-subtitle">Update details for <strong>{{ $business->name }}</strong>.</p>
    </div>
    <a href="{{ route('org.stores') }}" class="btn btn-secondary">
         Back to Stores
    </a>
</div>

<div class="settings-card" style="max-width: 680px;">
    <div class="settings-card-header">
        <h2> Store Details</h2>
        <p>Update store information. Changes take effect immediately.</p>
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

        <form method="POST" action="{{ route('org.stores.update', $business) }}">
            @csrf
            @method('PUT')

            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label" for="name">Store Name *</label>
                    <input type="text" id="name" name="name"
                        class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
                        value="{{ old('name', $business->name) }}"
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
                            <option value="{{ $value }}"
                                {{ old('business_type', $business->business_type) === $value ? 'selected' : '' }}>
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
                        value="{{ old('email', $business->email) }}"
                        required>
                    @error('email')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">Phone Number *</label>
                    <input type="text" id="phone" name="phone"
                        class="form-control {{ $errors->has('phone') ? 'is-invalid' : '' }}"
                        value="{{ old('phone', $business->phone) }}"
                        required>
                    @error('phone')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="address">Address</label>
                    <input type="text" id="address" name="address"
                        class="form-control {{ $errors->has('address') ? 'is-invalid' : '' }}"
                        value="{{ old('address', $business->address) }}">
                    @error('address')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="city">City / Town</label>
                    <input type="text" id="city" name="city"
                        class="form-control {{ $errors->has('city') ? 'is-invalid' : '' }}"
                        value="{{ old('city', $business->city) }}">
                    @error('city')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="form-actions" style="margin-top: var(--space-5);">
                <button type="submit" class="btn btn-primary">
                     Save Changes
                </button>
                <a href="{{ route('org.stores') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

{{-- Danger zone: enter store --}}
<div class="settings-card" style="max-width: 680px; margin-top: var(--space-4); border-color: var(--color-primary);">
    <div class="settings-card-header">
        <h2> Enter Store</h2>
        <p>Switch your active context to this store and go to its dashboard.</p>
    </div>
    <div class="settings-card-body">
        <form method="POST" action="{{ route('org.switch', $business) }}">
            @csrf
            <button type="submit" class="btn btn-primary">
                 Enter {{ $business->name }}
            </button>
        </form>
    </div>
</div>

</div>
@endsection
