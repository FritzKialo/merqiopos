@extends('portal.layout')
@section('title', 'Reset Password')

@section('content')
<div style="max-width:400px; margin:0 auto; padding:48px 0;">
    <h1 style="margin:0 0 8px; font-size:1.4rem;">Forgot Password</h1>
    <p style="color:#666; margin-bottom:24px; font-size:0.9rem;">Enter your email to receive a reset link.</p>

    @if(session('status'))
    <div style="background:#d1fae5; color:#065f46; padding:12px 16px; border-radius:6px; margin-bottom:16px;">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('portal.forgot.post') }}">
        @csrf
        <div style="margin-bottom:16px;">
            <label style="display:block; font-weight:bold; margin-bottom:6px; font-size:0.9rem;">Email</label>
            <input type="email" name="email" required value="{{ old('email') }}"
                style="width:100%; padding:10px 12px; border:1px solid #ccc; border-radius:6px; font-family:Georgia,serif; font-size:0.95rem;">
            @error('email')<div style="color:#dc2626; font-size:0.8rem; margin-top:4px;">{{ $message }}</div>@enderror
        </div>
        <button type="submit" style="background:#000; color:#fff; border:none; padding:12px 24px; border-radius:6px; cursor:pointer; font-family:Georgia,serif; font-size:0.95rem; width:100%;">Send Reset Link</button>
    </form>
    <p style="margin-top:16px; font-size:0.85rem; text-align:center;"><a href="{{ route('portal.login') }}" style="color:#555;">← Back to Login</a></p>
</div>
@endsection
