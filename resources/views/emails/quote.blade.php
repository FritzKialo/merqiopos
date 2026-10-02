<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation #{{ $quote->quote_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0; color: #334155; }
        .wrapper { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: #ffffff; padding: 28px 40px 22px; text-align: center; border-bottom: 1px solid #e2e8f0; }
        .header h1 { margin: 0; font-size: 24px; color: #4338ca; }
        .header p { margin: 6px 0 0; font-size: 14px; color: #64748b; }
        .body { padding: 40px; line-height: 1.7; }
        .body h2 { color: #1e293b; margin-top: 0; }
        .quote-meta { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px 20px; margin: 20px 0; font-size: 14px; }
        .quote-meta p { margin: 4px 0; }
        .quote-meta strong { color: #1e293b; }
        table.items { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px; }
        table.items th { background: #4f46e5; color: white; padding: 10px 12px; text-align: left; font-weight: 600; }
        table.items td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; }
        table.items tr:last-child td { border-bottom: none; }
        .totals { text-align: right; margin: 0 0 24px; font-size: 14px; }
        .totals .total-row { display: flex; justify-content: flex-end; gap: 40px; padding: 4px 0; }
        .totals .grand-total { font-size: 18px; font-weight: 700; color: #4f46e5; border-top: 2px solid #e2e8f0; padding-top: 8px; margin-top: 4px; }
        .attachment-note { background: #f0fdfa; border-left: 4px solid #0f766e; padding: 16px 20px; border-radius: 4px; margin: 20px 0; font-size: 14px; }
        .attachment-note strong { color: #0f766e; }
        .footer { background: #f8fafc; padding: 24px 40px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
        .footer p { margin: 4px 0; }
    </style>
</head>
<body>
<div class="wrapper">

    @include('emails.partials.business-header', [
        'business' => $quote->business,
        'subtitle' => 'Quotation',
        'icon'     => '📝',
    ])

    <div class="body">
        <h2>Dear {{ $quote->customer->name ?? 'Valued Customer' }},</h2>
        <p>
            <strong>{{ $quote->business->name }}</strong> has prepared the following quotation for your review.
            A full copy is attached to this email as a PDF.
        </p>

        <div class="quote-meta">
            <p><strong>Quote #:</strong> {{ $quote->quote_number }}</p>
            <p><strong>Date:</strong> {{ $quote->quote_date->format('d M Y') }}</p>
            @if($quote->valid_until)
                <p><strong>Valid Until:</strong> {{ $quote->valid_until->format('d M Y') }}</p>
            @endif
        </div>

        <table class="items">
            <thead>
                <tr>
                    {{-- Inline background/color duplicate the table.items th CSS
                    above — same Gmail <style>-stripping risk as the header/button. --}}
                    <th style="width: 40%; background:#4f46e5 !important; color:#ffffff !important;">Item</th>
                    <th style="text-align: center; background:#4f46e5 !important; color:#ffffff !important;">Qty</th>
                    <th style="text-align: right; background:#4f46e5 !important; color:#ffffff !important;">Unit Price</th>
                    <th style="text-align: right; background:#4f46e5 !important; color:#ffffff !important;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($quote->items as $item)
                <tr>
                    <td>{{ $item->product_name }}</td>
                    <td style="text-align: center;">{{ $item->quantity }}</td>
                    <td style="text-align: right;">KSh {{ number_format($item->unit_price, 2) }}</td>
                    <td style="text-align: right;">KSh {{ number_format($item->subtotal, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div class="total-row">
                <span>Subtotal:</span>
                <span>KSh {{ number_format($quote->subtotal, 2) }}</span>
            </div>
            @if($quote->discount_amount > 0)
            <div class="total-row" style="color: #dc2626;">
                <span>Discount:</span>
                <span>- KSh {{ number_format($quote->discount_amount, 2) }}</span>
            </div>
            @endif
            @if($quote->tax_amount > 0)
            <div class="total-row">
                <span>Tax:</span>
                <span>KSh {{ number_format($quote->tax_amount, 2) }}</span>
            </div>
            @endif
            <div class="total-row grand-total">
                <span>TOTAL:</span>
                <span>KSh {{ number_format($quote->total, 2) }}</span>
            </div>
        </div>

        <div class="attachment-note">
            <strong>📎 Attached:</strong> Quote-{{ $quote->quote_number }}.pdf — a printable copy of this quotation for your records.
        </div>

        @if($quote->terms)
        <p style="font-size: 13px; color: #64748b;"><strong>Terms:</strong> {{ $quote->terms }}</p>
        @endif

        @if($quote->notes)
        <p style="font-size: 13px; color: #64748b;"><strong>Notes:</strong> {{ $quote->notes }}</p>
        @endif

        <p style="font-size: 13px; color: #64748b; margin-top: 24px;">
            Thank you for considering us. If you have any questions about this quotation, please get in touch.
        </p>
    </div>

    @include('emails.partials.business-footer', ['business' => $quote->business])
</div>
</body>
</html>
