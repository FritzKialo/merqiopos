@extends('layouts.auth')
@section('title', 'Register')

@section('content')
<div class="auth-card register-card">

    <div class="register-top-row">
        <div class="register-brand">
            <div class="register-brand-icon"></div>
            <span class="register-brand-name">Merqio<span>POS</span></span>
        </div>
        <div class="register-signin-link">
            Already have an account? <a href="{{ route('login') }}">Sign in</a>
        </div>
    </div>

    <h2 class="auth-title">Get Started</h2>
    <p class="auth-subtitle">
        1-month free trial. No credit card required.
    </p>

    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <a href="{{ route('google.redirect') }}" class="btn-google">
        <svg viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg">
            <path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.9c1.7-1.57 2.7-3.88 2.7-6.62z"/>
            <path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.9-2.26c-.8.54-1.84.86-3.06.86-2.35 0-4.34-1.59-5.05-3.72H.96v2.33A9 9 0 0 0 9 18z"/>
            <path fill="#FBBC05" d="M3.95 10.7A5.4 5.4 0 0 1 3.67 9c0-.59.1-1.17.28-1.7V4.97H.96A9 9 0 0 0 0 9c0 1.45.35 2.83.96 4.03l2.99-2.33z"/>
            <path fill="#EA4335" d="M9 3.58c1.32 0 2.51.46 3.44 1.35l2.58-2.58C13.46.89 11.43 0 9 0A9 9 0 0 0 .96 4.97l2.99 2.33C4.66 5.17 6.65 3.58 9 3.58z"/>
        </svg>
        Google
    </a>

    <div class="auth-divider">or</div>

    <form method="POST"
          action="{{ route('register.post') }}"
          id="registerForm">
        @csrf

        <div class="register-columns">

            {{-- Business Information --}}
            <div class="register-col">
                <div class="form-divider">
                    Business Information
                </div>

                <div class="form-group">
                    <label class="form-label"
                           for="business_name">
                        Business Name *
                    </label>
                    <input
                        type="text"
                        id="business_name"
                        name="business_name"
                        class="form-control
                            {{ $errors->has('business_name')
                                ? 'is-invalid' : '' }}"
                        value="{{ old('business_name') }}"
                        placeholder="Acme Stores"
                        required>
                    @error('business_name')
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
                        <option value="">Select industry</option>
                        <option value="retail"
                            {{ old('industry') == 'retail'
                                ? 'selected' : '' }}>
                            Retail / Shop
                        </option>
                        <option value="restaurant"
                            {{ old('industry') == 'restaurant'
                                ? 'selected' : '' }}>
                            Restaurant / Food
                        </option>
                        <option value="salon"
                            {{ old('industry') == 'salon'
                                ? 'selected' : '' }}>
                            Salon / Beauty
                        </option>
                        <option value="wholesale"
                            {{ old('industry') == 'wholesale'
                                ? 'selected' : '' }}>
                            Wholesale
                        </option>
                        <option value="clinic"
                            {{ old('industry') == 'clinic'
                                ? 'selected' : '' }}>
                            Clinic / Pharmacy
                        </option>
                        <option value="other"
                            {{ old('industry') == 'other'
                                ? 'selected' : '' }}>
                            Other
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label"
                           for="business_email">
                        Business Email *
                    </label>
                    <input
                        type="email"
                        id="business_email"
                        name="business_email"
                        class="form-control
                            {{ $errors->has('business_email')
                                ? 'is-invalid' : '' }}"
                        value="{{ old('business_email') }}"
                        placeholder="info@yourbusiness.com"
                        required>
                    @error('business_email')
                        <span class="invalid-feedback">
                            {{ $message }}
                        </span>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label"
                           for="business_phone">
                        Business Phone *
                    </label>
                    <input
                        type="tel"
                        id="business_phone"
                        name="business_phone"
                        class="form-control
                            {{ $errors->has('business_phone')
                                ? 'is-invalid' : '' }}"
                        value="{{ old('business_phone') }}"
                        placeholder="0712 345 678"
                        required>
                    @error('business_phone')
                        <span class="invalid-feedback">
                            {{ $message }}
                        </span>
                    @enderror
                </div>

            </div>

            {{-- Sits between the two columns in source order (so mobile's
            single-column tab order reads business fields, then address,
            then account — logically grouped), while desktop's CSS `order`
            visually pulls this below both columns instead. --}}
            <div class="register-address-row">
                <div class="form-group">
                    <label class="form-label"
                           for="business_address">
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
                    <label class="form-label"
                           for="business_city">
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

            {{-- Owner Information --}}
            <div class="register-col">
                <div class="form-divider">
                    Your Account
                </div>

                <div class="form-group">
                    <label class="form-label" for="owner_name">
                        Full Name *
                    </label>
                    <input
                        type="text"
                        id="owner_name"
                        name="owner_name"
                        class="form-control
                            {{ $errors->has('owner_name')
                                ? 'is-invalid' : '' }}"
                        value="{{ old('owner_name') }}"
                        placeholder="John Kamau"
                        required>
                    @error('owner_name')
                        <span class="invalid-feedback">
                            {{ $message }}
                        </span>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="owner_email">
                        Your Email *
                    </label>
                    <input
                        type="email"
                        id="owner_email"
                        name="owner_email"
                        class="form-control
                            {{ $errors->has('owner_email')
                                ? 'is-invalid' : '' }}"
                        value="{{ old('owner_email') }}"
                        placeholder="john@example.com"
                        required>
                    @error('owner_email')
                        <span class="invalid-feedback">
                            {{ $message }}
                        </span>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">
                        Password *
                    </label>
                    <div class="form-control-wrap">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control
                                {{ $errors->has('password')
                                    ? 'is-invalid' : '' }}"
                            placeholder="Min. 8 characters"
                            required>
                        <button type="button" class="password-toggle ph-bold ph-eye" data-target="password" aria-label="Show password"></button>
                    </div>
                    @error('password')
                        <span class="invalid-feedback">
                            {{ $message }}
                        </span>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label"
                           for="password_confirmation">
                        Confirm Password *
                    </label>
                    <div class="form-control-wrap">
                        <input
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            class="form-control"
                            placeholder="Repeat password"
                            required>
                        <button type="button" class="password-toggle ph-bold ph-eye" data-target="password_confirmation" aria-label="Show password"></button>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">
            Create My Free Account
        </button>
    </form>

</div>
@endsection