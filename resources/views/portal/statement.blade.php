@extends('portal.layout')
@section('title', 'Account Statement')

@section('content')
<h1 style="margin:0 0 24px;">Account Statement</h1>

<div style="display:flex; gap:16px; flex-wrap:wrap; margin-bottom:24px;">
    <div style="background:#fff; border:1px solid #e0e0e0; border-radius:8px; padding:16px 20px; min-width:140px;">
        <div style="font-size:0.8rem; color:#888; margin-bottom:4px;">Total Invoiced</div>
        <div style="font-weight:bold; font-size:1.1rem;">KSh {{ number_format($totalInvoiced, 0) }}</div>
    </div>
    <div style="background:#fff; border:1px solid #e0e0e0; border-radius:8px; padding:16px 20px; min-width:140px;">
        <div style="font-size:0.8rem; color:#888; margin-bottom:4px;">Total Paid</div>
        <div style="font-weight:bold; font-size:1.1rem;">KSh {{ number_format($totalPaid, 0) }}</div>
    </div>
    <div style="background:{{ $balance > 0 ? '#fee2e2' : '#d1fae5' }}; border:1px solid {{ $balance > 0 ? '#fca5a5' : '#6ee7b7' }}; border-radius:8px; padding:16px 20px; min-width:140px;">
        <div style="font-size:0.8rem; color:#888; margin-bottom:4px;">Balance Due</div>
        <div style="font-weight:bold; font-size:1.1rem;">KSh {{ number_format(abs($balance), 0) }}</div>
    </div>
</div>

@if($invoices->isEmpty())
<p style="color:#888; text-align:center; padding:48px 0;">No transactions on record.</p>
@else
<div class="table-wrap">
<table style="width:100%; border-collapse:collapse; min-width:480px;">
    <thead><tr style="border-bottom:2px solid #000;">
        <th style="text-align:left; padding:8px 12px; font-size:0.9rem;">Date</th>
        <th style="text-align:left; padding:8px 12px; font-size:0.9rem;">Description</th>
        <th style="text-align:right; padding:8px 12px; font-size:0.9rem;">Amount</th>
        <th style="text-align:left; padding:8px 12px; font-size:0.9rem;">Status</th>
    </tr></thead>
    <tbody>
        @foreach($invoices as $inv)
        <tr style="border-bottom:1px solid #e0e0e0;">
            <td data-label="Date" style="padding:10px 12px; font-size:0.9rem;">{{ $inv->issue_date->format('d M Y') }}</td>
            <td data-label="Description" style="padding:10px 12px; font-size:0.9rem;"><a href="{{ route('portal.invoices.show', $inv) }}" style="color:#000;">{{ $inv->invoice_number }}</a></td>
            <td data-label="Amount" style="padding:10px 12px; font-size:0.9rem; text-align:right;">KSh {{ number_format($inv->total, 0) }}</td>
            <td data-label="Status" style="padding:10px 12px; font-size:0.9rem;">
                <span style="color:{{ $inv->status === 'paid' ? '#065f46' : '#991b1b' }};">{{ ucfirst($inv->status) }}</span>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
</div>
@endif
@endsection
