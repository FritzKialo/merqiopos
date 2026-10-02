<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekly Debt Digest</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0; }
        .wrapper { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: #ffffff; padding: 28px 40px 22px; text-align: center; border-bottom: 1px solid #e2e8f0; }
        .header h1 { margin: 0; font-size: 22px; color: #4338ca; }
        .header p { margin: 8px 0 0; font-size: 14px; color: #64748b; }
        .body { padding: 40px; color: #334155; line-height: 1.7; }
        .body h2 { color: #1e293b; margin-top: 0; }
        .summary-box { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 16px 20px; margin: 20px 0; text-align: center; }
        .summary-total { font-size: 2rem; font-weight: 700; color: #0c4a6e; }
        .summary-label { font-size: 0.85rem; color: #0369a1; }
        .table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px; }
        .table th { background: #f8fafc; text-align: left; padding: 10px 14px; border-bottom: 2px solid #e2e8f0; color: #475569; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; }
        .table td { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; color: #334155; }
        .table tr:last-child td { border-bottom: none; }
        .amount { font-weight: 600; color: #dc2626; }
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
        <h1 style="margin:0; color:#4338ca !important;">⏰ Weekly Debt Digest</h1>
        <p style="margin:8px 0 0; color:#64748b !important;">Outstanding customer balances at {{ $business->name }}</p>
    </div>
    <div class="body">
        <h2>Hi, {{ $business->owner?->name ?? $business->name }}!</h2>

        <p>Here is your weekly summary of customers with outstanding balances as of {{ now()->format('d M Y') }}:</p>

        <div class="summary-box">
            <div class="summary-total">KSh {{ number_format($totalOwed, 0) }}</div>
            <div class="summary-label">Total owed across {{ $customers->count() }} {{ $customers->count() === 1 ? 'customer' : 'customers' }}</div>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Customer</th>
                    <th>Phone</th>
                    <th>Balance Owed</th>
                </tr>
            </thead>
            <tbody>
                @foreach($customers->sortByDesc('balance_owed') as $i => $customer)
                <tr>
                    <td style="color:#94a3b8;">{{ $i + 1 }}</td>
                    <td>{{ $customer->name }}</td>
                    <td style="color:#64748b;">{{ $customer->phone ?? '—' }}</td>
                    <td class="amount">KSh {{ number_format($customer->balance_owed, 0) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Inline style duplicates the .btn rule above — same Gmail
        <style>-stripping risk that leaves button text/background both white. --}}
        <a href="{{ config('app.url') }}/customers" class="btn" style="display:inline-block !important; background:#4f46e5 !important; color:#ffffff !important; padding:14px 32px !important; border-radius:6px !important; text-decoration:none !important; font-weight:600 !important;">View Customers</a>

        <p style="font-size: 13px; color: #64748b; margin-top: 24px;">
            You receive this digest every Monday. Record a payment in Merqio POS when a customer clears their balance.
        </p>
    </div>
    <div class="footer">
        <p>&copy; {{ date('Y') }} Merqio POS &mdash; Nairobi, Kenya</p>
        <p>This is an automated weekly digest. Please do not reply to this email.</p>
    </div>
</div>
</body>
</html>
