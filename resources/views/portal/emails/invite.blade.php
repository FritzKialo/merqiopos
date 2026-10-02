<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You're invited to the {{ $businessName }} customer portal</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0; }
        .wrapper { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: #4f46e5; color: white; padding: 32px 40px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .body { padding: 40px; color: #334155; line-height: 1.7; }
        .body h2 { color: #1e293b; margin-top: 0; }
        .btn { display: inline-block; background: #4f46e5; color: white; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-weight: 600; margin: 24px 0; }
        .link-fallback { font-size: 12px; color: #94a3b8; word-break: break-all; margin-top: 8px; }
        .footer { background: #f8fafc; padding: 24px 40px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
<div class="wrapper">
    {{-- Inline background/color duplicate the .header CSS above — Gmail
    (dark mode, clipped/long-email view) can strip or override a <style>-block
    rule, which would otherwise leave the heading text white on white. --}}
    <div class="header" style="background:#4f46e5 !important; color:#ffffff !important; padding:32px 40px !important; text-align:center !important;">
        @if(isset($business) && $business->logo)
            <img src="{{ asset('storage/' . $business->logo) }}" alt="{{ $businessName }}" style="max-height:56px; max-width:220px;">
        @else
            <h1 style="margin:0; color:#ffffff !important;">{{ $businessName }}</h1>
        @endif
    </div>
    <div class="body">
        <h2>Hi {{ $customer->name }},</h2>
        <p>{{ $businessName }} has invited you to their customer portal. From there you can view your invoices, download statements, and pay outstanding balances online.</p>

        <p>Click below to set your password and activate your account:</p>

        {{-- Inline style duplicates the .btn rule above — Gmail (dark mode,
        clipped/long-email view) frequently strips or overrides <style>-block
        CSS on links, which previously left this button's text and background
        both white, invisible on the wrapper's white body. Inline + !important
        survives that. --}}
        <a href="{{ $setupUrl }}" class="btn" style="display:inline-block !important; background:#4f46e5 !important; color:#ffffff !important; padding:14px 32px !important; border-radius:6px !important; text-decoration:none !important; font-weight:600 !important;">Set Up My Account</a>

        <p class="link-fallback">
            If the button doesn't work, copy and paste this link into your browser:<br>
            {{ $setupUrl }}
        </p>

        <p style="font-size: 13px; color: #64748b;">This link is unique to you — please don't share it. If you weren't expecting this invitation, you can safely ignore this email.</p>
    </div>
    <div class="footer">
        @if(isset($business) && $business)
            @if($business->phone)
                <p>{{ $business->phone }}</p>
            @endif
            @if($business->email)
                <p>{{ $business->email }}</p>
            @endif
            @if($business->address)
                <p>{{ $business->address }}@if($business->city), {{ $business->city }}@endif</p>
            @endif
        @endif
        <p style="margin-top: 8px;">&copy; {{ date('Y') }} {{ $businessName }} &mdash; Powered by Merqio POS</p>
    </div>
</div>
</body>
</html>
