@extends('portal.layout')
@section('title', 'Invoice ' . $invoice->invoice_number)

@push('styles')
<style>
@media (max-width: 500px) {
    .portal-inv-card { padding: 18px !important; }
}
</style>
@endpush

@section('content')
<p style="margin-bottom:16px;"><a href="{{ route('portal.invoices') }}" style="color:#555;">← My Invoices</a></p>

<div class="portal-inv-card" style="background:#fff; border:1px solid #e0e0e0; border-radius:10px; padding:32px; max-width:700px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:32px; flex-wrap:wrap; gap:16px;">
        <div>
            <h1 style="margin:0 0 4px; font-size:1.4rem;">Invoice {{ $invoice->invoice_number }}</h1>
            <div style="color:#888; font-size:0.9rem;">{{ $invoice->issue_date->format('d F Y') }}</div>
        </div>
        <span style="background:{{ $invoice->status === 'paid' ? '#d1fae5' : ($invoice->status === 'overdue' ? '#fee2e2' : '#fef9c3') }}; color:{{ $invoice->status === 'paid' ? '#065f46' : ($invoice->status === 'overdue' ? '#991b1b' : '#713f12') }}; padding:6px 16px; border-radius:999px; font-weight:bold; font-size:0.9rem;">{{ ucfirst($invoice->status) }}</span>
    </div>

    <div class="table-wrap">
    <table style="width:100%; border-collapse:collapse; margin-bottom:24px; min-width:380px;">
        <thead><tr style="border-bottom:2px solid #000;">
            <th style="text-align:left; padding:8px 12px; font-size:0.85rem;">Item</th>
            <th style="text-align:right; padding:8px 12px; font-size:0.85rem;">Qty</th>
            <th style="text-align:right; padding:8px 12px; font-size:0.85rem;">Unit</th>
            <th style="text-align:right; padding:8px 12px; font-size:0.85rem;">Total</th>
        </tr></thead>
        <tbody>
            @foreach($invoice->items as $item)
            <tr style="border-bottom:1px solid #e0e0e0;">
                <td data-label="Item" style="padding:10px 12px; font-size:0.9rem;">{{ $item->description }}</td>
                <td data-label="Qty" style="padding:10px 12px; font-size:0.9rem; text-align:right;">{{ $item->quantity }}</td>
                <td data-label="Unit" style="padding:10px 12px; font-size:0.9rem; text-align:right;">KSh {{ number_format($item->unit_price, 2) }}</td>
                <td data-label="Total" style="padding:10px 12px; font-size:0.9rem; text-align:right;">KSh {{ number_format($item->subtotal, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="border-top:2px solid #000; font-weight:bold;">
                <td colspan="3" style="padding:12px; text-align:right;">Total</td>
                <td style="padding:12px; text-align:right;">KSh {{ number_format($invoice->total, 2) }}</td>
            </tr>
        </tfoot>
    </table>
    </div>

    @if($invoice->notes)
    <div style="background:#f9f9f9; border-radius:6px; padding:12px 16px; font-size:0.85rem; color:#555;">{{ $invoice->notes }}</div>
    @endif

    <div style="margin-top:24px; display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
        <a href="{{ route('portal.invoices.pdf', $invoice) }}" target="_blank" style="background:#000; color:#fff; padding:10px 20px; border-radius:6px; text-decoration:none; font-size:0.9rem;">Download PDF</a>
    </div>

    {{-- Pay via M-Pesa — the controller/callback/webhook side of this was
    already fixed and verified end-to-end earlier, but this page (the only
    place a customer could actually trigger it) never had the form at all,
    so there was no way to reach it from the real UI. --}}
    @if($invoice->balance_due > 0)
    <div style="margin-top:24px; padding-top:24px; border-top:1px solid #e0e0e0;">
        <h3 style="margin:0 0 4px; font-size:1rem;">Pay via M-Pesa</h3>
        <p style="color:#888; font-size:0.85rem; margin:0 0 16px;">
            Balance due: <strong>KSh {{ number_format($invoice->balance_due, 2) }}</strong>
        </p>
        <form method="POST" action="{{ route('portal.pay', $invoice) }}" style="display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end;">
            @csrf
            <div>
                <label style="display:block; font-size:0.8rem; font-weight:600; margin-bottom:4px;">M-Pesa Phone Number</label>
                <input type="tel" name="phone" required placeholder="07XXXXXXXX" pattern="^(07|01|2547|2541)\d{8}$"
                       style="padding:10px 12px; border:1px solid #ccc; border-radius:6px; font-size:0.9rem; width:200px; max-width:100%; box-sizing:border-box;">
            </div>
            <button type="submit" style="background:#000; color:#fff; padding:10px 20px; border-radius:6px; border:none; cursor:pointer; font-size:0.9rem;">
                Pay Now
            </button>
        </form>
    </div>
    @endif
</div>
@endsection
