@extends('layouts.app')
@section('title', 'Stock Receives')
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Stock Receives</h1>
            <p class="page-subtitle">Goods received into inventory</p>
        </div>
        <a href="{{ route('receives.create') }}" class="btn btn--primary">Receive Stock</a>
    </div>

    @if(session('success')) <div class="alert alert--success">{{ session('success') }}</div> @endif

    <div class="table-card">
        <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Receive #</th>
                    <th>Date</th>
                    <th>Supplier</th>
                    <th>PO Ref</th>
                    <th>Invoice Ref</th>
                    <th>Total Cost</th>
                    <th>Received By</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($receives as $receive)
                <tr>
                    <td data-label="Receive #"><strong>{{ $receive->receive_number }}</strong></td>
                    <td data-label="Date">{{ $receive->received_date->format('d M Y') }}</td>
                    <td data-label="Supplier">{{ $receive->supplier?->name ?? '—' }}</td>
                    <td data-label="PO Ref">{{ $receive->purchaseOrder?->po_number ?? '—' }}</td>
                    <td data-label="Invoice Ref">{{ $receive->invoice_ref ?? '—' }}</td>
                    <td data-label="Total Cost">KSh {{ number_format($receive->total_cost, 0) }}</td>
                    <td data-label="Received By">{{ $receive->user?->name }}</td>
                    <td data-label="View"><a href="{{ route('receives.show', $receive) }}" class="btn btn--outline btn--sm">View</a></td>
                </tr>
                @empty
                <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:2rem;">No stock receives yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    {{ $receives->links() }}
</div>
@endsection
