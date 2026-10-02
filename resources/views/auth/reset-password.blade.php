@extends('layouts.auth')
@section('title', 'Set New Password')

@section('content')
<div class="auth-card">

    <div class="auth-logo">
        <div class="auth-logo-icon">
            
        </div>
        <h1>Merqio<span>POS</span></h1>
        <p>Business Management Made Simple</p>
    </div>

    <h2 class="auth-title">Set New Password</h2>
    <p class="auth-subtitle">Choose a strong password for your account</p>

    @if($errors->any())
        <div class="alert alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.update') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <div class="form-group">
            <label class="form-label" for="email">Email Address</label>
            <input
                type="email"
                id="email"
                name="email"
                class="form-control {{ $errors->has('email') ? 'is-invalid' : '' }}"
                value="{{ old('email', $email) }}"
                placeholder="you@business.com"
                required>
            @error('email')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="password">New Password</label>
            <div class="form-control-wrap">
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control {{ $errors->has('password') ? 'is-invalid' : '' }}"
                    placeholder="Min 8 characters"
                    required>
                <button type="button" class="password-toggle ph-bold ph-eye" data-target="password" aria-label="Show password"></button>
            </div>
            @error('password')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="password_confirmation">Confirm New Password</label>
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

        <button type="submit" class="btn btn-primary">
            Reset Password
        </button>
    </form>

    <div class="auth-footer">
        <a href="{{ route('login') }}">Back to Sign In</a>
    </div>

</div>
@endsection
