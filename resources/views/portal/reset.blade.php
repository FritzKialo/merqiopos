@extends('portal.layout')
@section('title', 'Reset Password')

@section('content')
<div style="max-width:400px; margin:0 auto; padding:48px 0;">
    <h1 style="margin:0 0 24px; font-size:1.4rem;">Set New Password</h1>

    <form method="POST" action="{{ route('portal.reset.post') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div style="margin-bottom:16px;">
            <label style="display:block; font-weight:bold; margin-bottom:6px; font-size:0.9rem;">Email</label>
            <input type="email" name="email" required value="{{ old('email', $email ?? '') }}"
                style="width:100%; padding:10px 12px; border:1px solid #ccc; border-radius:6px; font-family:Georgia,serif; font-size:0.95rem;">
        </div>
        <div style="margin-bottom:16px;">
            <label style="display:block; font-weight:bold; margin-bottom:6px; font-size:0.9rem;">New Password</label>
            <input type="password" name="password" required
                style="width:100%; padding:10px 12px; border:1px solid #ccc; border-radius:6px; font-family:Georgia,serif; font-size:0.95rem;">
        </div>
        <div style="margin-bottom:20px;">
            <label style="display:block; font-weight:bold; margin-bottom:6px; font-size:0.9rem;">Confirm Password</label>
            <input type="password" name="password_confirmation" required
                style="width:100%; padding:10px 12px; border:1px solid #ccc; border-radius:6px; font-family:Georgia,serif; font-size:0.95rem;">
        </div>
        @if($errors->any())
        <div style="background:#fee2e2; color:#991b1b; padding:12px 16px; border-radius:6px; margin-bottom:16px; font-size:0.85rem;">{{ $errors->first() }}</div>
        @endif
        <button type="submit" style="background:#000; color:#fff; border:none; padding:12px 24px; border-radius:6px; cursor:pointer; font-family:Georgia,serif; font-size:0.95rem; width:100%;">Reset Password</button>
    </form>
</div>
@endsection
