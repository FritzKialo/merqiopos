@extends('portal.layout')
@section('title', 'My Profile')

@section('content')
<h1 style="margin:0 0 24px;">My Profile</h1>

<div style="max-width:480px;">
    @if(session('success'))
    <div style="background:#d1fae5; color:#065f46; padding:12px 16px; border-radius:6px; margin-bottom:16px;">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('portal.profile.update') }}">
        @csrf
        {{-- Previously this form spoofed a PUT verb here, but the registered
        route is a plain POST (routes/web.php: Route::post('/profile', ...))
        — every "Update Profile" submission from a real customer has been
        throwing a 405 Method Not Allowed error. Removed the verb spoof. --}}
        <div style="margin-bottom:16px;">
            <label style="display:block; font-weight:bold; margin-bottom:6px; font-size:0.9rem;">Full Name *</label>
            <input type="text" name="name" required value="{{ old('name', $customer->name) }}"
                style="width:100%; padding:10px 12px; border:1px solid #ccc; border-radius:6px; font-family:Georgia,serif; font-size:0.95rem;">
            @error('name')<div style="color:#dc2626; font-size:0.8rem; margin-top:4px;">{{ $message }}</div>@enderror
        </div>
        <div style="margin-bottom:16px;">
            <label style="display:block; font-weight:bold; margin-bottom:6px; font-size:0.9rem;">Phone</label>
            <input type="tel" name="phone" value="{{ old('phone', $customer->phone) }}"
                style="width:100%; padding:10px 12px; border:1px solid #ccc; border-radius:6px; font-family:Georgia,serif; font-size:0.95rem;">
        </div>
        <div style="margin-bottom:16px;">
            <label style="display:block; font-weight:bold; margin-bottom:6px; font-size:0.9rem;">Email</label>
            <input type="email" name="email" value="{{ old('email', $customer->email) }}"
                style="width:100%; padding:10px 12px; border:1px solid #ccc; border-radius:6px; font-family:Georgia,serif; font-size:0.95rem;">
        </div>
        <div style="margin-bottom:20px;">
            <label style="display:block; font-weight:bold; margin-bottom:6px; font-size:0.9rem;">New Password (leave blank to keep current)</label>
            <input type="password" name="password"
                style="width:100%; padding:10px 12px; border:1px solid #ccc; border-radius:6px; font-family:Georgia,serif; font-size:0.95rem;">
        </div>
        <button type="submit" style="background:#000; color:#fff; border:none; padding:12px 24px; border-radius:6px; cursor:pointer; font-family:Georgia,serif; font-size:0.95rem;">Save Changes</button>
    </form>
</div>
@endsection
