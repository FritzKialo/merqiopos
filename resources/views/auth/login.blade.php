@extends('layouts.auth')
@section('title', 'Login')

@section('content')
<div class="auth-card">

    <div class="auth-logo">
        <div class="auth-logo-icon">
            
        </div>
        <h1>Merqio<span>POS</span></h1>
        <p>Business Management Made Simple</p>
    </div>

    <h2 class="auth-title">Welcome Back</h2>
    <p class="auth-subtitle">Sign in to your account</p>

    {{-- Alerts --}}
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
          action="{{ route('login.post') }}"
          id="loginForm">
        @csrf

        <div class="form-group">
            <label class="form-label" for="email">
                Email Address
            </label>
            <input 
                type="email" 
                id="email" 
                name="email"
                class="form-control 
                    {{ $errors->has('email') 
                        ? 'is-invalid' : '' }}"
                value="{{ old('email') }}"
                placeholder="you@business.com"
                required
                autofocus>
            @error('email')
                <span class="invalid-feedback">
                    {{ $message }}
                </span>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="password">
                Password
            </label>
            <div class="form-control-wrap">
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control
                        {{ $errors->has('password')
                            ? 'is-invalid' : '' }}"
                    placeholder="••••••••"
                    required>
                <button type="button" class="password-toggle ph-bold ph-eye" data-target="password" aria-label="Show password"></button>
            </div>
            @error('password')
                <span class="invalid-feedback">
                    {{ $message }}
                </span>
            @enderror
        </div>

        <div class="remember-row">
            <label class="checkbox-label">
                <input type="checkbox" name="remember">
                Remember me
            </label>
            <a href="{{ route('password.request') }}" style="font-size:0.875rem; color:var(--auth-accent); font-weight:600;">
                Forgot password?
            </a>
        </div>

        <button type="submit" class="btn btn-primary">
            Sign In
        </button>
    </form>

    <div class="auth-footer">
        Don't have an account? 
        <a href="{{ route('register') }}">
            Create one free
        </a>
    </div>

</div>
@endsection