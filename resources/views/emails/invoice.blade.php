<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #{{ $sale->invoice_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0; color: #334155; }
        .wrapper { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: #ffffff; padding: 28px 40px 22px; text-align: center; border-bottom: 1px solid #e2e8f0; }
        .header h1 { margin: 0; font-size: 24px; color: #4338ca; }
        .header p { margin: 6px 0 0; font-size: 14px; color: #64748b; }
        .body { padding: 40px; line-height: 1.7; }
        .body h2 { color: #1e293b; margin-top: 0; }
        .invoice-meta { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px 20px; margin: 20px 0; font-size: 14px; }
        .invoice-meta p { margin: 4px 0; }
        .invoice-meta strong { color: #1e293b; }
        table.items { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px; }
        table.items th { background: #4f46e5; color: white; padding: 10px 12px; text-align: left; font-weight: 600; }
        table.items td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; }
        table.items tr:last-child td { border-bottom: none; }
        table.items tfoot td { font-weight: 700; background: #f8fafc; }
        .totals { text-align: right; margin: 0 0 24px; font-size: 14px; }
        .totals .total-row { display: flex; justify-content: flex-end; gap: 40px; padding: 4px 0; }
        .totals .grand-total { font-size: 18px; font-weight: 700; color: #4f46e5; border-top: 2px solid #e2e8f0; padding-top: 8px; margin-top: 4px; }
        .mpesa-box { background: #f0fdf4; border-left: 4px solid #22c55e; padding: 16px 20px; border-radius: 4px; margin: 20px 0; font-size: 14px; }
        .mpesa-box p { margin: 4px 0; }
        .mpesa-box strong { color: #16a34a; }
        .btn { display: inline-block; background: #4f46e5; color: white; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-weight: 600; margin: 20px 0; font-size: 15px; }
        .footer { background: #f8fafc; padding: 24px 40px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
        .footer p { margin: 4px 0; }
    </style>
</head>
<body>
<div class="wrapper">

    @include('emails.partials.business-header', [
        'business' => $sale->business,
        'subtitle' => 'Invoice',
        'showLogo' => $sale->business->show_logo_on_invoice,
        'icon'     => '🧾',
    ])

    <div class="body">
        <h2>Dear {{ $sale->customer->name ?? 'Valued Customer' }},</h2>
        <p>
            <strong>{{ $sale->business->name }}</strong> has sent you an invoice for your recent purchase.
            Please review the details below.
        </p>

        {{-- Invoice Meta --}}
        <div class="invoice-meta">
            <p><strong>Invoice #:</strong> {{ $sale->invoice_number }}</p>
            <p><strong>Date:</strong> {{ $sale->created_at->format('d M Y') }}</p>
            @if($sale->business->payment_terms)
                <p><strong>Payment Terms:</strong> {{ $sale->business->payment_terms }}</p>
            @endif
            @if($sale->balance_due > 0)
                <p><strong>Amount Due:</strong> KSh {{ number_format($sale->balance_due, 2) }}</p>
            @else
                <p><strong>Status:</strong> <span style="color: #16a34a; font-weight: 700;">PAID</span></p>
            @endif
        </div>

        {{-- Items Table --}}
        <table class="items">
            <thead>
                <tr>
                    {{-- Inline background/color duplicate the table.items th CSS
                    above — same Gmail <style>-stripping risk as the header/button. --}}
                    <th style="width: 40%; background:#4f46e5 !important; color:#ffffff !important;">Product</th>
                    <th style="text-align: center; background:#4f46e5 !important; color:#ffffff !important;">Qty</th>
                    <th style="text-align: right; background:#4f46e5 !important; color:#ffffff !important;">Unit Price</th>
                    <th style="text-align: right; background:#4f46e5 !important; color:#ffffff !important;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sale->items as $item)
                <tr>
                    <td>{{ $item->product_name }}</td>
                    <td style="text-align: center;">{{ $item->quantity }}</td>
                    <td style="text-align: right;">KSh {{ number_format($item->unit_price, 2) }}</td>
                    <td style="text-align: right;">KSh {{ number_format($item->subtotal, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Totals --}}
        <div class="totals">
            <div class="total-row">
                <span>Subtotal:</span>
                <span>KSh {{ number_format($sale->subtotal, 2) }}</span>
            </div>
            @if($sale->discount_amount > 0)
            <div class="total-row" style="color: #dc2626;">
                <span>Discount:</span>
                <span>- KSh {{ number_format($sale->discount_amount, 2) }}</span>
            </div>
            @endif
            @if($sale->tax_amount > 0)
            <div class="total-row">
                <span>Tax:</span>
                <span>KSh {{ number_format($sale->tax_amount, 2) }}</span>
            </div>
            @endif
            <div class="total-row grand-total">
                <span>TOTAL:</span>
                <span>KSh {{ number_format($sale->total_amount, 2) }}</span>
            </div>
            @if($sale->paid_amount > 0)
            <div class="total-row" style="color: #16a34a;">
                <span>Paid:</span>
                <span>KSh {{ number_format($sale->paid_amount, 2) }}</span>
            </div>
            @endif
            @if($sale->balance_due > 0)
            <div class="total-row" style="color: #dc2626; font-weight: 700;">
                <span>Balance Due:</span>
                <span>KSh {{ number_format($sale->balance_due, 2) }}</span>
            </div>
            @endif
        </div>

        {{-- M-Pesa Payment Instructions --}}
        @if($sale->business->hasMpesaConfigured() && $sale->balance_due > 0)
        <div class="mpesa-box">
            <p><strong>Pay via M-Pesa:</strong></p>
            <p>Paybill: <strong>{{ $sale->business->mpesa_shortcode }}</strong></p>
            @if($sale->business->mpesa_till_number)
            <p>Till Number: <strong>{{ $sale->business->mpesa_till_number }}</strong></p>
            @endif
            <p>Amount: <strong>KSh {{ number_format($sale->balance_due, 2) }}</strong></p>
        </div>
        @endif

        {{-- CTA --}}
        {{-- Inline style duplicates the .btn rule above — Gmail (dark mode,
        clipped/long-email view) frequently strips or overrides <style>-block
        CSS on links, which previously left this button's text and background
        both white, invisible on the wrapper's white body. Inline + !important
        survives that. --}}
        <a href="{{ route('sales.show', $sale) }}" class="btn" style="display:inline-block !important; background:#4f46e5 !important; color:#ffffff !important; padding:14px 32px !important; border-radius:6px !important; text-decoration:none !important; font-weight:600 !important; font-size:15px !important;">View Invoice</a>

        @if($sale->notes)
        <p style="font-size: 13px; color: #64748b;"><strong>Notes:</strong> {{ $sale->notes }}</p>
        @endif

        <p style="font-size: 13px; color: #64748b; margin-top: 24px;">
            Thank you for your business. If you have any questions about this invoice, please contact us.
        </p>
    </div>

    @include('emails.partials.business-footer', ['business' => $sale->business])
</div>
</body>
</html>
