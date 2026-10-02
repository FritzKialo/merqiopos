@extends('layouts.app')
@section('title', 'Delivery Notes')
@push('styles')
<style>
@media (min-width: 769px) {
    .dn-index-table th, .dn-index-table td { padding: 0.75rem 1rem; }
}
</style>
@endpush
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Delivery Notes</h1>
            <p class="page-subtitle">Track goods dispatched and delivered</p>
        </div>
        <a href="{{ route('delivery-notes.create') }}" class="btn btn-primary">+ New Delivery Note</a>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif

    <div class="table-card">
        @if($notes->isEmpty())
            <div style="padding:3rem;text-align:center;color:var(--color-text-muted);">No delivery notes yet. <a href="{{ route('delivery-notes.create') }}">Create one</a>.</div>
        @else
        <table class="dn-index-table" style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="background:var(--color-surface);border-bottom:2px solid var(--color-border);">
                    <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Number</th>
                    <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Customer</th>
                    <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Linked To</th>
                    <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Status</th>
                    <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Date</th>
                    <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Created By</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($notes as $note)
                <tr style="border-bottom:1px solid var(--color-border);">
                    <td data-label="Number" style="font-weight:700;">{{ $note->number }}</td>
                    <td data-label="Customer">{{ $note->customer?->name ?? '—' }}</td>
                    <td data-label="Linked To" style="font-size:0.85rem;color:var(--color-text-muted);">
                        @if($note->invoice_id) INV @elseif($note->sale_id) Sale @else — @endif
                    </td>
                    <td data-label="Status">
                        @if($note->status === 'draft')
                            <span class="badge badge-secondary">Draft</span>
                        @elseif($note->status === 'dispatched')
                            <span class="badge badge-warning">Dispatched</span>
                        @else
                            <span class="badge badge-success">Delivered</span>
                        @endif
                    </td>
                    <td data-label="Date" style="font-size:0.85rem;">{{ $note->created_at->format('d M Y') }}</td>
                    <td data-label="Created By" style="font-size:0.85rem;color:var(--color-text-muted);">{{ $note->user?->name ?? '—' }}</td>
                    <td data-label="">
                        <a href="{{ route('delivery-notes.show', $note) }}" class="btn btn-secondary" style="font-size:0.8rem;padding:0.25rem 0.75rem;">View</a>
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
