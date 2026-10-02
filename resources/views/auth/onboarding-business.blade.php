@extends('layouts.auth')
@section('title', 'Set Up Your Business')

@section('content')
<div class="auth-card">

    <div class="auth-logo">
        <div class="auth-logo-icon">

        </div>
        <h1>Merqio<span>POS</span></h1>
        <p>Business Management Made Simple</p>
    </div>

    <h2 class="auth-title">Welcome, {{ explode(' ', $user->name)[0] }}!</h2>
    <p class="auth-subtitle">
        One last step — tell us about your business to start your 1-month free trial.
    </p>

    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('onboarding.business.store') }}">
        @csrf

        <div class="form-group">
            <label class="form-label" for="business_name">
                Business Name *
            </label>
            <input
                type="text"
                id="business_name"
                name="business_name"
                class="form-control {{ $errors->has('business_name') ? 'is-invalid' : '' }}"
                value="{{ old('business_name') }}"
                placeholder="Acme Stores"
                required
                autofocus>
            @error('business_name')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label class="form-label" for="business_email">
                    Business Email *
                </label>
                <input
                    type="email"
                    id="business_email"
                    name="business_email"
                    class="form-control {{ $errors->has('business_email') ? 'is-invalid' : '' }}"
                    value="{{ old('business_email', $user->email) }}"
                    placeholder="info@yourbusiness.com"
                    required>
                @error('business_email')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="business_phone">
                    Business Phone *
                </label>
                <input
                    type="tel"
                    id="business_phone"
                    name="business_phone"
                    class="form-control {{ $errors->has('business_phone') ? 'is-invalid' : '' }}"
                    value="{{ old('business_phone') }}"
                    placeholder="0712 345 678"
                    required>
                @error('business_phone')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label class="form-label" for="business_address">
                    Address
                </label>
                <input
                    type="text"
                    id="business_address"
                    name="business_address"
                    class="form-control"
                    value="{{ old('business_address') }}"
                    placeholder="123 Moi Avenue">
            </div>

            <div class="form-group">
                <label class="form-label" for="business_city">
                    City
                </label>
                <input
                    type="text"
                    id="business_city"
                    name="business_city"
                    class="form-control"
                    value="{{ old('business_city') }}"
                    placeholder="Nairobi">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="industry">
                Industry
            </label>
            <select id="industry" name="industry" class="form-control">
                <option value="">Select industry</option>
                <option value="retail" {{ old('industry') == 'retail' ? 'selected' : '' }}>Retail / Shop</option>
                <option value="restaurant" {{ old('industry') == 'restaurant' ? 'selected' : '' }}>Restaurant / Food</option>
                <option value="salon" {{ old('industry') == 'salon' ? 'selected' : '' }}>Salon / Beauty</option>
                <option value="wholesale" {{ old('industry') == 'wholesale' ? 'selected' : '' }}>Wholesale</option>
                <option value="clinic" {{ old('industry') == 'clinic' ? 'selected' : '' }}>Clinic / Pharmacy</option>
                <option value="other" {{ old('industry') == 'other' ? 'selected' : '' }}>Other</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">
            Start My Free Trial
        </button>
    </form>

</div>
@endsection
