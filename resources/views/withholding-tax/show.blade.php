@extends('layouts.app')
@section('title', 'WHT Record')
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h1>Withholding Tax Record</h1>
    <a href="{{ route('withholding-tax.index') }}" class="btn btn-secondary">← Back</a>
</div>
<div class="card" style="max-width:600px;">
<div class="card-body">
<table class="table-plain" style="width:100%;border-collapse:collapse;">
<tbody>
<tr style="border-bottom:1px solid var(--color-border);"><td style="padding:10px 0;color:var(--color-text-muted);width:40%;">Payee Name</td><td style="padding:10px 0;font-weight:600;">{{ $wht->payee_name }}</td></tr>
<tr style="border-bottom:1px solid var(--color-border);"><td style="padding:10px 0;color:var(--color-text-muted);">KRA PIN</td><td style="padding:10px 0;font-family:monospace;">{{ $wht->payee_kra_pin ?? '—' }}</td></tr>
<tr style="border-bottom:1px solid var(--color-border);"><td style="padding:10px 0;color:var(--color-text-muted);">Payment Type</td><td style="padding:10px 0;">{{ ucfirst(str_replace('_',' ',$wht->wht_type)) }}</td></tr>
<tr style="border-bottom:1px solid var(--color-border);"><td style="padding:10px 0;color:var(--color-text-muted);">Payment Date</td><td style="padding:10px 0;">{{ $wht->payment_date->format('d M Y') }}</td></tr>
<tr style="border-bottom:1px solid var(--color-border);"><td style="padding:10px 0;color:var(--color-text-muted);">Gross Amount</td><td style="padding:10px 0;">KSh {{ number_format($wht->gross_amount, 2) }}</td></tr>
<tr style="border-bottom:1px solid var(--color-border);"><td style="padding:10px 0;color:var(--color-text-muted);">WHT Rate</td><td style="padding:10px 0;">{{ $wht->wht_rate }}%</td></tr>
<tr style="border-bottom:1px solid var(--color-border);"><td style="padding:10px 0;color:var(--color-text-muted);">WHT Amount</td><td style="padding:10px 0;font-weight:700;color:var(--color-danger);">KSh {{ number_format($wht->wht_amount, 2) }}</td></tr>
<tr style="border-bottom:1px solid var(--color-border);"><td style="padding:10px 0;color:var(--color-text-muted);">Net Paid to Payee</td><td style="padding:10px 0;font-weight:700;color:var(--color-success);">KSh {{ number_format($wht->net_amount, 2) }}</td></tr>
@if($wht->certificate_number)
<tr style="border-bottom:1px solid var(--color-border);"><td style="padding:10px 0;color:var(--color-text-muted);">Certificate No.</td><td style="padding:10px 0;font-family:monospace;">{{ $wht->certificate_number }}</td></tr>
@endif
@if($wht->notes)
<tr style="border-bottom:1px solid var(--color-border);"><td style="padding:10px 0;color:var(--color-text-muted);">Notes</td><td style="padding:10px 0;">{{ $wht->notes }}</td></tr>
@endif
<tr><td style="padding:10px 0;color:var(--color-text-muted);">Recorded</td><td style="padding:10px 0;font-size:0.85rem;color:var(--color-text-muted);">{{ $wht->created_at->format('d M Y H:i') }}</td></tr>
</tbody>
</table>
<div class="alert alert-warning" style="margin-top:20px;">
Remit KSh {{ number_format($wht->wht_amount, 2) }} to KRA via <strong>iTax</strong> by the 20th of {{ $wht->payment_date->addMonth()->format('F Y') }}.
</div>
<div style="margin-top:16px;display:flex;gap:12px;">
    <form method="POST" action="{{ route('withholding-tax.destroy', $wht) }}" onsubmit="return confirm('Delete this WHT record?')">
        @csrf @method('DELETE')
        <button class="btn btn-danger">Delete Record</button>
    </form>
</div>
</div>
</div>
@endsection
