<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Confirmed</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0; }
        .wrapper { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: #ffffff; padding: 28px 40px 22px; text-align: center; border-bottom: 1px solid #e2e8f0; }
        .header h1 { margin: 0; font-size: 24px; color: #16a34a; }
        .header p { margin: 8px 0 0; font-size: 14px; color: #64748b; }
        .body { padding: 40px; color: #334155; line-height: 1.7; }
        .body h2 { color: #1e293b; margin-top: 0; }
        .receipt { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 20px 24px; margin: 24px 0; }
        .receipt table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .receipt td { padding: 6px 0; color: #374151; }
        .receipt td:last-child { font-weight: 600; text-align: right; }
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
        <h1 style="margin:0; color:#16a34a !important;">✅ Payment Confirmed</h1>
        <p style="margin:8px 0 0; color:#64748b !important;">Your Merqio POS subscription is active</p>
    </div>
    <div class="body">
        <h2>Thank you, {{ $organization->owner?->name ?? $organization->name }}!</h2>
        <p>Your subscription payment has been received and your account has been activated.</p>

        <div class="receipt">
            <table>
                <tr>
                    <td>Organization</td>
                    <td>{{ $organization->name }}</td>
                </tr>
                <tr>
                    <td>Plan</td>
                    <td>{{ ucfirst($subscription->plan) }}</td>
                </tr>
                <tr>
                    <td>Amount Paid</td>
                    <td>KSh {{ number_format($subscription->amount, 2) }}</td>
                </tr>
                <tr>
                    <td>M-Pesa Reference</td>
                    <td>{{ $subscription->payment_reference ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Valid From</td>
                    <td>{{ \Carbon\Carbon::parse($subscription->start_date)->format('d M Y') }}</td>
                </tr>
                <tr>
                    <td>Valid Until</td>
                    <td>{{ \Carbon\Carbon::parse($subscription->end_date)->format('d M Y') }}</td>
                </tr>
            </table>
        </div>

        {{-- Inline style duplicates the .btn rule above — same Gmail
        <style>-stripping risk that leaves button text/background both white. --}}
        <a href="{{ config('app.url') }}/dashboard" class="btn" style="display:inline-block !important; background:#4f46e5 !important; color:#ffffff !important; padding:14px 32px !important; border-radius:6px !important; text-decoration:none !important; font-weight:600 !important;">Go to Dashboard</a>

        <p style="font-size: 13px; color: #64748b;">Keep this email as your payment receipt. For support, contact support@merqiopos.com.</p>
    </div>
    <div class="footer">
        <p>&copy; {{ date('Y') }} Merqio POS &mdash; Nairobi, Kenya</p>
    </div>
</div>
</body>
</html>
