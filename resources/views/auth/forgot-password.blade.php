@extends('layouts.auth')
@section('title', 'Forgot Password')

@section('content')
<div class="auth-card">

    <div class="auth-logo">
        <div class="auth-logo-icon">
            
        </div>
        <h1>Merqio<span>POS</span></h1>
        <p>Business Management Made Simple</p>
    </div>

    <h2 class="auth-title">Reset Password</h2>
    <p class="auth-subtitle">Enter your email and we'll send a reset link</p>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="form-group">
            <label class="form-label" for="email">Email Address</label>
            <input
                type="email"
                id="email"
                name="email"
                class="form-control {{ $errors->has('email') ? 'is-invalid' : '' }}"
                value="{{ old('email') }}"
                placeholder="you@business.com"
                required
                autofocus>
            @error('email')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary">
            Send Reset Link
        </button>
    </form>

    <div class="auth-footer">
        Remembered your password?
        <a href="{{ route('login') }}">Sign in</a>
    </div>

</div>
@endsection
