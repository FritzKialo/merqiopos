<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;color:#1a202c;">
    <div style="max-width:520px;margin:0 auto;padding:24px;">
        <div style="background:#fff;border-radius:10px;padding:28px;border:1px solid #e2e8f0;">
            @if($sale->business->show_logo_on_receipt && $sale->business->logo)
                <div style="text-align:center;margin-bottom:10px;">
                    <img src="{{ asset('storage/' . $sale->business->logo) }}" alt="{{ $sale->business->name }}" style="max-height:48px;max-width:200px;">
                </div>
            @endif
            <h2 style="margin:0 0 4px;">{{ $sale->business->name }}</h2>
            <p style="margin:0 0 20px;color:#718096;font-size:13px;">Receipt {{ $sale->invoice_number }}</p>

            <p style="font-size:15px;">Hi {{ $sale->customer?->name ?? 'there' }}, thank you for your purchase!</p>

            <table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:14px;">
                @foreach($sale->items as $item)
                <tr>
                    <td style="padding:6px 0;border-bottom:1px solid #edf2f7;">{{ $item->quantity }} × {{ $item->product_name ?? ($item->product->name ?? 'Item') }}</td>
                    <td style="padding:6px 0;border-bottom:1px solid #edf2f7;text-align:right;">KSh {{ number_format($item->subtotal ?? ($item->line_total ?? 0), 2) }}</td>
                </tr>
                @endforeach
                <tr>
                    <td style="padding:10px 0;font-weight:700;">Total Paid</td>
                    <td style="padding:10px 0;font-weight:700;text-align:right;">KSh {{ number_format($sale->paid_amount, 2) }}</td>
                </tr>
            </table>

            <div style="text-align:center;margin:24px 0;">
                <a href="{{ $link }}" style="display:inline-block;background:#0a0a0a;color:#fff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:600;">View / Print Receipt</a>
            </div>

            <p style="font-size:12px;color:#a0aec0;text-align:center;margin:16px 0 0;">
                {{-- A space between @endif and @if is required here — Blade's
                compiler silently fails to compile a directive that immediately
                follows another with zero whitespace between them (confirmed:
                the previous version compiled the first @endif fine but left
                the very next @if as literal uncompiled text, which then broke
                the whole file on its own now-orphaned @endif). This is why
                "Send Receipt" was silently failing for every business with an
                address on file — the mail's view threw a ParseError on every
                send, caught and (until just now) logged at a level production
                doesn't record. --}}
                {{ $sale->business->name }}@if($sale->business->phone) · {{ $sale->business->phone }}@endif @if($sale->business->address) · {{ $sale->business->address }}@endif
            </p>
            <p style="font-size:11px;color:#cbd5e0;text-align:center;margin:6px 0 0;">Powered by Merqio POS</p>
        </div>
    </div>
</body>
</html>
