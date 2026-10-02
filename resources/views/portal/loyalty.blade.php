@extends('portal.layout')
@section('title', 'Loyalty Points')

@section('content')
<h1 style="margin:0 0 24px;">Loyalty Points</h1>

<div style="background:#000; color:#fff; border-radius:12px; padding:32px; max-width:360px; margin-bottom:32px; text-align:center;">
    <div style="font-size:0.9rem; opacity:0.7; margin-bottom:8px;">Your Balance</div>
    <div style="font-size:3rem; font-weight:bold;">{{ number_format($balance) }}</div>
    <div style="font-size:0.9rem; opacity:0.7;">points</div>
</div>

<h2 style="font-size:1.1rem; margin:0 0 16px;">Transaction History</h2>

@if($transactions->isEmpty())
<p style="color:#888;">No loyalty transactions yet.</p>
@else
<div class="table-wrap">
<table style="width:100%; border-collapse:collapse; min-width:460px;">
    <thead><tr style="border-bottom:2px solid #000;">
        <th style="text-align:left; padding:8px 12px; font-size:0.9rem;">Date</th>
        <th style="text-align:left; padding:8px 12px; font-size:0.9rem;">Description</th>
        <th style="text-align:right; padding:8px 12px; font-size:0.9rem;">Points</th>
        <th style="text-align:right; padding:8px 12px; font-size:0.9rem;">Balance</th>
    </tr></thead>
    <tbody>
        @foreach($transactions as $tx)
        <tr style="border-bottom:1px solid #e0e0e0;">
            <td data-label="Date" style="padding:10px 12px; font-size:0.9rem;">{{ $tx->created_at->format('d M Y') }}</td>
            <td data-label="Description" style="padding:10px 12px; font-size:0.9rem;">{{ $tx->description ?? ucfirst($tx->type) }}</td>
            <td data-label="Points" style="padding:10px 12px; font-size:0.9rem; text-align:right; color:{{ $tx->points > 0 ? '#065f46' : '#991b1b' }}; font-weight:bold;">
                {{ $tx->points > 0 ? '+' : '' }}{{ number_format($tx->points) }}
            </td>
            <td data-label="Balance" style="padding:10px 12px; font-size:0.9rem; text-align:right;">{{ number_format($tx->balance_after) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
</div>
{{ $transactions->links() }}
@endif
@endsection
