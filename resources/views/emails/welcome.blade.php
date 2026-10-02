<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Merqio POS</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0; }
        .wrapper { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: #ffffff; padding: 28px 40px 22px; text-align: center; border-bottom: 1px solid #e2e8f0; }
        .header h1 { margin: 0; font-size: 26px; color: #4338ca; }
        .header p { margin: 8px 0 0; font-size: 14px; color: #64748b; }
        .body { padding: 40px; color: #334155; line-height: 1.7; }
        .body h2 { color: #1e293b; margin-top: 0; }
        .highlight { background: #f0f4ff; border-left: 4px solid #4f46e5; padding: 16px 20px; border-radius: 4px; margin: 24px 0; }
        .highlight p { margin: 4px 0; font-size: 14px; }
        .highlight strong { color: #4f46e5; }
        .btn { display: inline-block; background: #4f46e5; color: white; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-weight: 600; margin: 24px 0; }
        .footer { background: #f8fafc; padding: 24px 40px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
<div class="wrapper">
    {{-- Inline background/color duplicate the .header CSS above — Gmail
    (dark mode, clipped/long-email view) can strip or override a <style>-block
    rule, which would otherwise leave this heading text white on white. --}}
    <div class="header" style="background:#ffffff !important; padding:28px 40px 22px !important; text-align:center !important; border-bottom:1px solid #e2e8f0 !important;">
        <h1 style="margin:0; color:#4338ca !important;">🎉 Merqio POS</h1>
        <p style="margin:8px 0 0; color:#64748b !important;">Business Management Made Simple</p>
    </div>
    <div class="body">
        <h2>Welcome, {{ $user->name }}!</h2>
        <p>Your business <strong>{{ $business->name }}</strong> is now set up on Merqio POS. Your <strong>1-month free trial</strong> has started — no credit card required.</p>

        <div class="highlight">
            <p><strong>Business:</strong> {{ $business->name }}</p>
            <p><strong>Plan:</strong> Solo (Trial)</p>
            <p><strong>Trial ends:</strong> {{ $business->trial_ends_at->format('d M Y') }}</p>
        </div>

        <p>During your trial you have access to all Solo plan features:</p>
        <ul>
            <li>Inventory management</li>
            <li>Sales tracking &amp; invoicing</li>
            <li>Customer management &amp; debt tracking</li>
            <li>Basic payroll</li>
            <li>M-Pesa payment integration</li>
        </ul>

        {{-- Inline style duplicates the .btn rule above — same Gmail
        <style>-stripping risk that leaves button text/background both white. --}}
        <a href="{{ config('app.url') }}/dashboard" class="btn" style="display:inline-block !important; background:#4f46e5 !important; color:#ffffff !important; padding:14px 32px !important; border-radius:6px !important; text-decoration:none !important; font-weight:600 !important;">Go to Dashboard</a>

        <p style="font-size: 13px; color: #64748b;">If you have any questions, reply to this email or contact us at support@merqiopos.com.</p>
    </div>
    <div class="footer">
        <p>&copy; {{ date('Y') }} Merqio POS &mdash; Nairobi, Kenya</p>
        <p>You received this email because you created an account at merqiopos.com.</p>
    </div>
</div>
</body>
</html>
