@extends('layouts.app')
@section('title', $creditNote->number)
@push('styles')
<style>
@media (min-width: 769px) {
    .cn-show-table th, .cn-show-table td { padding: 0.75rem 1rem; }
}
/* Inline 2fr/1fr grid had no mobile collapse — squeezed the items table and
   details sidebar into unusably narrow columns on a phone. */
@media (max-width: 900px) {
    .cn-show-layout { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ $creditNote->number }}</h1>
            <p class="page-subtitle">
                @if($creditNote->status === 'draft')
                    <span class="badge badge-secondary">Draft</span>
                @else
                    <span class="badge badge-success">Issued</span>
                @endif
            </p>
        </div>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            @if($creditNote->status === 'draft')
                <form method="POST" action="{{ route('credit-notes.issue', $creditNote) }}" style="display:inline;"
                      onsubmit="return confirm('Issue this credit note? This will reduce the linked invoice balance.')">
                    @csrf @method('PATCH')
                    <button class="btn btn-success">Issue Credit Note</button>
                </form>
            @endif
            <a href="{{ route('credit-notes.pdf', $creditNote) }}" class="btn btn-secondary" target="_blank">Print / PDF</a>
            <a href="{{ route('credit-notes.index') }}" class="btn btn-secondary">Back</a>
        </div>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif

    <div class="cn-show-layout" style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;">
        <div>
            {{-- Items --}}
            <div class="table-card" style="margin-bottom:1.5rem;">
                <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--color-border);font-weight:700;">Items</div>
                {{-- Was fully inline-padded with no data-label — inline padding
                on <td> beats the media query's own card-stack padding
                regardless of breakpoint, so desktop padding now lives in the
                scoped @push('styles') block below instead. --}}
                <table class="cn-show-table" style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="border-bottom:1px solid var(--color-border);">
                            <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">#</th>
                            <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Description</th>
                            <th style="text-align:right;font-size:0.8rem;text-transform:uppercase;">Qty</th>
                            <th style="text-align:right;font-size:0.8rem;text-transform:uppercase;">Unit Price</th>
                            <th style="text-align:right;font-size:0.8rem;text-transform:uppercase;">VAT</th>
                            <th style="text-align:right;font-size:0.8rem;text-transform:uppercase;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($creditNote->items as $i => $item)
                        <tr style="border-bottom:1px solid var(--color-border);">
                            <td data-label="#" style="color:var(--color-text-muted);">{{ $i + 1 }}</td>
                            <td data-label="Description">{{ $item->description }}</td>
                            <td data-label="Qty" style="text-align:right;">{{ number_format($item->quantity, 2) }}</td>
                            <td data-label="Unit Price" style="text-align:right;">KSh {{ number_format($item->unit_price, 2) }}</td>
                            <td data-label="VAT" style="text-align:right;color:var(--color-text-muted);">
                                {{ $item->vat_rate > 0 ? $item->vat_rate . '%' : '—' }}
                            </td>
                            <td data-label="Total" style="text-align:right;font-weight:600;">KSh {{ number_format($item->total, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="border-top:2px solid var(--color-border);">
                            <td colspan="5" style="text-align:right;color:var(--color-text-muted);">Subtotal</td>
                            <td style="text-align:right;">KSh {{ number_format($creditNote->subtotal, 2) }}</td>
                        </tr>
                        @if($creditNote->vat_amount > 0)
                        <tr>
                            <td colspan="5" style="text-align:right;color:var(--color-text-muted);">VAT</td>
                            <td style="text-align:right;">KSh {{ number_format($creditNote->vat_amount, 2) }}</td>
                        </tr>
                        @endif
                        <tr style="background:var(--color-surface);">
                            <td colspan="5" style="text-align:right;font-weight:700;">Total</td>
                            <td style="text-align:right;font-weight:700;font-size:1.05rem;">KSh {{ number_format($creditNote->total, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Reason --}}
            <div class="table-card" style="padding:1.25rem;">
                <h3 style="font-weight:700;margin-bottom:0.5rem;">Reason</h3>
                <p style="color:var(--color-text-muted);font-size:0.9rem;">{{ $creditNote->reason }}</p>
            </div>
        </div>

        <div>
            <div class="table-card" style="padding:1.25rem;">
                <h3 style="font-weight:700;margin-bottom:0.75rem;">Details</h3>
                <div style="font-size:0.85rem;display:flex;flex-direction:column;gap:0.5rem;">
                    <div>
                        <span class="text-muted">Customer</span><br>
                        <strong>{{ $creditNote->customer?->name ?? '—' }}</strong>
                    </div>
                    @if($creditNote->invoice)
                    <div>
                        <span class="text-muted">Linked Invoice</span><br>
                        <a href="{{ route('invoices.show', $creditNote->invoice) }}">
                            {{ $creditNote->invoice->invoice_number }}
                        </a>
                    </div>
                    @endif
                    <div>
                        <span class="text-muted">Created</span><br>
                        {{ $creditNote->created_at->format('d M Y') }}
                    </div>
                    @if($creditNote->issued_at)
                    <div>
                        <span class="text-muted">Issued</span><br>
                        {{ $creditNote->issued_at->format('d M Y H:i') }}
                    </div>
                    @endif
                    @if($creditNote->status === 'issued' && $creditNote->invoice)
                    <div class="alert alert-success" style="margin-top:0.5rem;font-size:0.82rem;">
                        Applied KSh {{ number_format($creditNote->total, 2) }} to invoice balance.
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="table-card" style="margin-top:1.5rem;padding:1.25rem;">
        @include('partials.attachments', ['modelType' => 'CreditNote', 'modelId' => $creditNote->id])
    </div>
</div>
@include('partials.etims-refund-status', ['etimsType' => 'credit_note', 'etimsId' => $creditNote->id])
@endsection
