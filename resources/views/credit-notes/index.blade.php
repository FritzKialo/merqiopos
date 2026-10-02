@extends('layouts.app')
@section('title', 'Credit Notes')
@push('styles')
<style>
@media (min-width: 769px) {
    .cn-table th, .cn-table td { padding: 0.75rem 1rem; }
}
</style>
@endpush
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Credit Notes</h1>
            <p class="page-subtitle">Issue formal credit against invoices</p>
        </div>
        <a href="{{ route('credit-notes.create') }}" class="btn btn-primary">+ New Credit Note</a>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif

    <div class="table-card">
        @if($notes->isEmpty())
            <div style="padding:3rem;text-align:center;color:var(--color-text-muted);">No credit notes yet. <a href="{{ route('credit-notes.create') }}">Create one</a>.</div>
        @else
        {{-- Was a fully inline-styled table with padding set on every <td>
        (no data-label either) — the padding alone would have silently
        defeated the global mobile card-stack rule even after adding
        data-label, since an inline style always wins over the media
        query's own td{padding-left:48%} regardless of specificity or
        breakpoint. Padding now lives only in the desktop-scoped style
        block below so the mobile rule can actually apply. --}}
        <table class="table cn-table" style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="background:var(--color-surface);border-bottom:2px solid var(--color-border);">
                    <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Number</th>
                    <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Customer</th>
                    <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Invoice</th>
                    <th style="text-align:right;font-size:0.8rem;text-transform:uppercase;">Total</th>
                    <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Status</th>
                    <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($notes as $note)
                <tr style="border-bottom:1px solid var(--color-border);">
                    <td data-label="Number" style="font-weight:700;">{{ $note->number }}</td>
                    <td data-label="Customer">{{ $note->customer?->name ?? '—' }}</td>
                    <td data-label="Invoice" style="font-size:0.85rem;color:var(--color-text-muted);">
                        @if($note->invoice)
                            <a href="{{ route('invoices.show', $note->invoice) }}">{{ $note->invoice->invoice_number }}</a>
                        @else — @endif
                    </td>
                    <td data-label="Total" style="font-weight:600;">KSh {{ number_format($note->total, 2) }}</td>
                    <td data-label="Status">
                        @if($note->status === 'draft')
                            <span class="badge badge-secondary">Draft</span>
                        @else
                            <span class="badge badge-success">Issued</span>
                        @endif
                    </td>
                    <td data-label="Date" style="font-size:0.85rem;">
                        {{ $note->issued_at ? $note->issued_at->format('d M Y') : $note->created_at->format('d M Y') }}
                    </td>
                    <td data-label="">
                        <a href="{{ route('credit-notes.show', $note) }}" class="btn btn-secondary" style="font-size:0.8rem;padding:0.25rem 0.75rem;">View</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if($notes->hasPages())
        <div style="padding:1rem 1.25rem;">{{ $notes->links() }}</div>
        @endif
        @endif
    </div>
</div>
@endsection
