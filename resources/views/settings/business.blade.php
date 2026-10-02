@extends('layouts.app')
@section('title', 'Business Settings')

@push('styles')
    <link rel="stylesheet"
          href="{{ asset('css/settings.css') }}?v={{ @filemtime(public_path('css/settings.css')) ?: '1' }}">
@endpush

@section('content')
<div class="page">

<div class="page-header">
    <div>
        <h1 class="page-title">Settings</h1>
        <p class="page-subtitle">
            Manage your business and account
        </p>
    </div>
</div>

<div class="settings-layout">

    {{-- Sidebar Nav --}}
    @include('settings._nav')

    {{-- Content --}}
    <div>
        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif

        <div class="settings-card">
            <div class="settings-card-header">
                <h2>Business Profile</h2>
                <p>
                    Update your business information
                    shown on invoices and receipts.
                </p>
            </div>
            <div class="settings-card-body">
                <form method="POST"
                      action="{{ route(
                        'settings.business.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label"
                                   for="name">
                                Business Name *
                            </label>
                            <input
                                type="text"
                                id="name"
                                name="name"
                                class="form-control
                                    {{ $errors->has('name')
                                        ? 'is-invalid'
                                        : '' }}"
                                value="{{ old('name',
                                    $business->name) }}"
                                required>
                            @error('name')
                                <span class="invalid-feedback">
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label"
                                   for="industry">
                                Industry
                            </label>
                            <select
                                id="industry"
                                name="industry"
                                class="form-control">
                                <option value="">
                                    — Select —
                                </option>
                                @foreach([
                                    'retail'     => 'Retail / Shop',
                                    'restaurant' => 'Restaurant / Food',
                                    'salon'      => 'Salon / Beauty',
                                    'wholesale'  => 'Wholesale',
                                    'clinic'     => 'Clinic / Pharmacy',
                                    'other'      => 'Other',
                                ] as $val => $label)
                                    <option
                                        value="{{ $val }}"
                                        {{ old('industry',
                                            $business->industry)
                                            == $val
                                            ? 'selected'
                                            : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label"
                                   for="email">
                                Business Email *
                            </label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                class="form-control
                                    {{ $errors->has('email')
                                        ? 'is-invalid'
                                        : '' }}"
                                value="{{ old('email',
                                    $business->email) }}"
                                required>
                            @error('email')
                                <span class="invalid-feedback">
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label"
                                   for="phone">
                                Phone Number *
                            </label>
                            <input
                                type="tel"
                                id="phone"
                                name="phone"
                                class="form-control
                                    {{ $errors->has('phone')
                                        ? 'is-invalid'
                                        : '' }}"
                                value="{{ old('phone',
                                    $business->phone) }}"
                                required>
                            @error('phone')
                                <span class="invalid-feedback">
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label"
                                   for="address">
                                Address
                            </label>
                            <input
                                type="text"
                                id="address"
                                name="address"
                                class="form-control"
                                value="{{ old('address',
                                    $business->address) }}"
                                placeholder="Street address">
                        </div>

                        <div class="form-group">
                            <label class="form-label"
                                   for="city">
                                City
                            </label>
                            <input
                                type="text"
                                id="city"
                                name="city"
                                class="form-control"
                                value="{{ old('city',
                                    $business->city) }}"
                                placeholder="e.g. Nairobi">
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label" for="kra_pin">
                                KRA PIN
                            </label>
                            <input
                                type="text"
                                id="kra_pin"
                                name="kra_pin"
                                class="form-control"
                                value="{{ old('kra_pin', $business->kra_pin) }}"
                                placeholder="e.g. A001234567X"
                                maxlength="20">
                            <span class="form-hint">Printed on invoices if provided.</span>
                        </div>

                        <div class="form-group">
                            {{-- spacer --}}
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="payment_terms">
                            Payment Terms
                        </label>
                        <textarea
                            id="payment_terms"
                            name="payment_terms"
                            class="form-control"
                            rows="3"
                            placeholder="e.g. Payment due within 30 days. Late payments attract 1.5% per month.">{{ old('payment_terms', $business->payment_terms) }}</textarea>
                        <span class="form-hint">Displayed in the footer of every invoice.</span>
                    </div>

                    <div class="form-actions">
                        <button type="submit"
                                class="btn btn-primary">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</div>{{-- end .page --}}

@endsection

@push('scripts')
    <script src="{{ asset('js/settings.js') }}"></script>
@endpush