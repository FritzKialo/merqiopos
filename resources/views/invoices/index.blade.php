@extends('layouts.app')
@section('title', 'Invoices')
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Invoices</h1>
            <p class="page-subtitle">Manage customer invoices and payments</p>
        </div>
        <a href="{{ route('invoices.create') }}" class="btn btn--primary">New Invoice</a>
    </div>

    @if(session('success')) <div class="alert alert--success">{{ session('success') }}</div> @endif
    @if(session('error'))   <div class="alert alert--error">{{ session('error') }}</div> @endif

    {{-- Status Tabs --}}
    <div style="display:flex;gap:0.5rem;margin-bottom:1rem;flex-wrap:wrap;">
        @foreach(['all','draft','sent','partial','overdue','paid'] as $tab)
        <a href="{{ route('invoices.index', ['status' => $tab]) }}"
           class="btn {{ $status === $tab ? 'btn--primary' : 'btn--outline' }} btn--sm">
            {{ ucfirst($tab) }} ({{ $counts[$tab] ?? 0 }})
        </a>
        @endforeach
    </div>

    <div class="table-card">
        <table class="table">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Customer</th>
                    <th>Issued</th>
                    <th>Due</th>
                    <th>Total</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $inv)
                <tr>
                    <td data-label="Invoice #"><strong>{{ $inv->invoice_number }}</strong></td>
                    <td data-label="Customer">{{ $inv->customer?->name ?? '—' }}</td>
                    <td data-label="Issued">{{ $inv->issue_date->format('d M Y') }}</td>
                    <td data-label="Due">{{ $inv->due_date->format('d M Y') }}</td>
                    <td data-label="Total">KSh {{ number_format($inv->total, 0) }}</td>
                    <td data-label="Paid">KSh {{ number_format($inv->amount_paid, 0) }}</td>
                    <td data-label="Balance">KSh {{ number_format($inv->balance_due, 0) }}</td>
                    <td data-label="Status">
                        @php
                            $badge = match($inv->status) {
                                'paid'    => 'badge--green',
                                'draft'   => 'badge--gray',
                                'partial' => 'badge--gray',
                                'overdue' => 'badge--red',
                                default   => 'badge--gray',
                            };
                        @endphp
                        <span class="badge {{ $badge }}">{{ ucfirst($inv->status) }}</span>
                    </td>
                    <td data-label="" style="white-space:nowrap;">
                        <a href="{{ route('invoices.show', $inv) }}" class="btn btn--outline btn--sm">View</a>
                        <a href="{{ route('invoices.pdf', $inv) }}" class="btn btn--outline btn--sm" target="_blank">PDF</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:2rem;">No invoices found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $invoices->links() }}
</div>
@endsection
