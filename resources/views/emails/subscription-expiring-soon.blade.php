<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Expiring Soon</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0; }
        .wrapper { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: #ffffff; padding: 28px 40px 22px; text-align: center; border-bottom: 1px solid #e2e8f0; }
        .header h1 { margin: 0; font-size: 24px; color: #d97706; }
        .header p { margin: 8px 0 0; font-size: 14px; color: #64748b; }
        .body { padding: 40px; color: #334155; line-height: 1.7; }
        .body h2 { color: #1e293b; margin-top: 0; }
        .warning { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 16px 20px; margin: 20px 0; font-size: 14px; }
        .warning strong { color: #92400e; }
        .btn { display: inline-block; background: #4f46e5; color: white; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-weight: 600; margin: 16px 0; }
        .footer { background: #f8fafc; padding: 24px 40px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
<div class="wrapper">
    {{-- Inline background/color duplicate the .header CSS above — Gmail
    (dark mode, clipped/long-email view) can strip or override a <style>-block
    rule, which would otherwise leave this heading text white on white. --}}
    <div class="header" style="background:#ffffff !important; padding:28px 40px 22px !important; text-align:center !important; border-bottom:1px solid #e2e8f0 !important;">
        <h1 style="margin:0; color:#d97706 !important;">⏳ Subscription Expiring Soon</h1>
        <p style="margin:8px 0 0; color:#64748b !important;">Action required to keep your account active</p>
    </div>
    <div class="body">
        <h2>Hi, {{ $organization->owner?->name ?? $organization->name }}!</h2>

        <div class="warning">
            <strong>Your Merqio POS access for {{ $organization->name }} expires on {{ $expiryDate }} ({{ $daysLeft }} days from now).</strong>
        </div>

        <p>After expiry, you will lose access to:</p>
        <ul>
            <li>Sales recording and invoicing</li>
            <li>Inventory and stock management</li>
            <li>Customer and expense tracking</li>
            <li>All reports and data</li>
        </ul>

        <p><strong>Your data will NOT be deleted</strong> — it will be preserved and available when you renew.</p>

        {{-- Inline style duplicates the .btn rule above — same Gmail
        <style>-stripping risk that leaves button text/background both white. --}}
        <a href="{{ config('app.url') }}/settings/subscription" class="btn" style="display:inline-block !important; background:#4f46e5 !important; color:#ffffff !important; padding:14px 32px !important; border-radius:6px !important; text-decoration:none !important; font-weight:600 !important;">Renew Subscription</a>

        <p style="font-size: 13px; color: #64748b;">
            Renewal is quick and easy via M-Pesa. If you need help, contact support@merqiopos.com.
        </p>
    </div>
    <div class="footer">
        <p>&copy; {{ date('Y') }} Merqio POS &mdash; Nairobi, Kenya</p>
        <p>This is an automated reminder. Please do not reply to this email.</p>
    </div>
</div>
</body>
</html>
