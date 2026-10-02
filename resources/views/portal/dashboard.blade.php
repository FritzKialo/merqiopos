@extends('portal.layout')
@section('title', 'My Account')

@section('content')
<h1 style="margin:0 0 8px;">Welcome, {{ $customer->name }}</h1>
@if($business)
<p style="color:var(--color-text-muted); margin:0 0 24px;">{{ $business->name }}</p>
@endif

@if($totalOutstanding > 0)
<div style="background:#fee2e2; border:1px solid #fca5a5; border-radius:8px; padding:14px 18px; margin-bottom:24px; font-size:0.9rem;">
    You have <strong>{{ $openCount }} unpaid invoice{{ $openCount !== 1 ? 's' : '' }}</strong> totalling <strong>KSh {{ number_format($totalOutstanding, 0) }}</strong>.
</div>
@endif

<div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:16px; margin-bottom:32px;">
    <a class="portal-tile" href="{{ route('portal.invoices') }}" style="background:var(--color-surface); border:1px solid var(--color-border); border-radius:12px; padding:20px; text-decoration:none; color:var(--color-text);">
        <div style="margin-bottom:8px;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:26px;height:26px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></div>
        <div style="font-weight:bold;">Invoices</div>
        <div style="font-size:0.85rem; color:var(--color-text-muted);">View your invoices</div>
    </a>
    <a class="portal-tile" href="{{ route('portal.statement') }}" style="background:var(--color-surface); border:1px solid var(--color-border); border-radius:12px; padding:20px; text-decoration:none; color:var(--color-text);">
        <div style="margin-bottom:8px;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:26px;height:26px;"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div>
        <div style="font-weight:bold;">Statement</div>
        <div style="font-size:0.85rem; color:var(--color-text-muted);">Account statement</div>
    </a>
    @if($loyaltyProgram)
    <a class="portal-tile" href="{{ route('portal.loyalty') }}" style="background:var(--color-surface); border:1px solid var(--color-border); border-radius:12px; padding:20px; text-decoration:none; color:var(--color-text);">
        <div style="margin-bottom:8px;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:26px;height:26px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></div>
        <div style="font-weight:bold;">Loyalty Points</div>
        <div style="font-size:0.85rem; color:var(--color-text-muted);">{{ number_format($loyaltyPoints) }} pts</div>
    </a>
    @endif
    <a class="portal-tile" href="{{ route('portal.profile') }}" style="background:var(--color-surface); border:1px solid var(--color-border); border-radius:12px; padding:20px; text-decoration:none; color:var(--color-text);">
        <div style="margin-bottom:8px;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:26px;height:26px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
        <div style="font-weight:bold;">My Profile</div>
        <div style="font-size:0.85rem; color:var(--color-text-muted);">Update details</div>
    </a>
</div>

<h2 style="font-size:1.1rem; margin:0 0 16px;">Recent Invoices</h2>
@if($recentInvoices->isEmpty())
<p style="color:var(--color-text-muted);">No invoices yet.</p>
@else
<div class="table-wrap">
<table style="width:100%; border-collapse:collapse; min-width:480px;">
    <thead><tr style="border-bottom:2px solid var(--color-text);">
        <th style="text-align:left; padding:8px 12px; font-size:0.9rem;">Invoice #</th>
        <th style="text-align:left; padding:8px 12px; font-size:0.9rem;">Date</th>
        <th style="text-align:right; padding:8px 12px; font-size:0.9rem;">Amount</th>
        <th style="text-align:left; padding:8px 12px; font-size:0.9rem;">Status</th>
    </tr></thead>
    <tbody>
        @foreach($recentInvoices as $inv)
        <tr style="border-bottom:1px solid var(--color-border);">
            <td data-label="Invoice #" style="padding:10px 12px;"><a href="{{ route('portal.invoices.show', $inv) }}" style="color:var(--color-text);">{{ $inv->invoice_number }}</a></td>
            <td data-label="Date" style="padding:10px 12px; font-size:0.9rem;">{{ $inv->issue_date->format('d M Y') }}</td>
            <td data-label="Amount" style="padding:10px 12px; font-size:0.9rem; text-align:right;">KSh {{ number_format($inv->total, 0) }}</td>
            <td data-label="Status" style="padding:10px 12px; font-size:0.9rem;">
                <span style="background:{{ $inv->status === 'paid' ? '#d1fae5' : ($inv->status === 'overdue' ? '#fee2e2' : '#fef9c3') }}; color:{{ $inv->status === 'paid' ? '#065f46' : ($inv->status === 'overdue' ? '#991b1b' : '#713f12') }}; padding:2px 10px; border-radius:999px; font-size:0.8rem;">
                    {{ ucfirst($inv->status) }}
                </span>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
</div>
@endif
@endsection
