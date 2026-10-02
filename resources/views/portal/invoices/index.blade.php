@extends('portal.layout')
@section('title', 'My Invoices')

@section('content')
<h1 style="margin:0 0 24px;">My Invoices</h1>

@if($invoices->isEmpty())
<p style="color:#888; text-align:center; padding:48px 0;">No invoices found.</p>
@else
<div class="table-wrap">
<table style="width:100%; border-collapse:collapse; min-width:560px;">
    <thead><tr style="border-bottom:2px solid #000;">
        <th style="text-align:left; padding:8px 12px; font-size:0.9rem;">Invoice #</th>
        <th style="text-align:left; padding:8px 12px; font-size:0.9rem;">Date</th>
        <th style="text-align:left; padding:8px 12px; font-size:0.9rem;">Due</th>
        <th style="text-align:right; padding:8px 12px; font-size:0.9rem;">Amount</th>
        <th style="text-align:left; padding:8px 12px; font-size:0.9rem;">Status</th>
        <th></th>
    </tr></thead>
    <tbody>
        @foreach($invoices as $inv)
        <tr style="border-bottom:1px solid #e0e0e0;">
            <td data-label="Invoice #" style="padding:10px 12px;">{{ $inv->invoice_number }}</td>
            <td data-label="Date" style="padding:10px 12px; font-size:0.9rem;">{{ $inv->issue_date->format('d M Y') }}</td>
            <td data-label="Due" style="padding:10px 12px; font-size:0.9rem;">{{ $inv->due_date ? $inv->due_date->format('d M Y') : '—' }}</td>
            <td data-label="Amount" style="padding:10px 12px; font-size:0.9rem; text-align:right;">KSh {{ number_format($inv->total, 0) }}</td>
            <td data-label="Status" style="padding:10px 12px;">
                <span style="background:{{ $inv->status === 'paid' ? '#d1fae5' : ($inv->status === 'overdue' ? '#fee2e2' : '#fef9c3') }}; color:{{ $inv->status === 'paid' ? '#065f46' : ($inv->status === 'overdue' ? '#991b1b' : '#713f12') }}; padding:2px 10px; border-radius:999px; font-size:0.8rem;">{{ ucfirst($inv->status) }}</span>
            </td>
            <td class="td-action" style="padding:10px 12px;"><a href="{{ route('portal.invoices.show', $inv) }}" style="color:#000; font-size:0.85rem;">View →</a></td>
        </tr>
        @endforeach
    </tbody>
</table>
</div>
{{ $invoices->links() }}
@endif
@endsection
