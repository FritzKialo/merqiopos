@extends('layouts.app')
@section('title', $supplier->name)

@section('content')

{{-- Profile Header --}}
<div class="profile-header">
    <div class="profile-avatar">
        {{ strtoupper(substr($supplier->name, 0, 1)) }}
    </div>
    <div class="profile-info">
        <h2>{{ $supplier->name }}</h2>
        <p>
            @if($supplier->contact_person)
                 {{ $supplier->contact_person }}
            @endif
            @if($supplier->contact_person && $supplier->phone)
                &nbsp;&nbsp;
            @endif
            @if($supplier->phone)
                 {{ $supplier->phone }}
            @endif
        </p>
        @if($supplier->email)
            <p> {{ $supplier->email }}</p>
        @endif
        @if($supplier->address)
            <p> {{ $supplier->address }}</p>
        @endif
        <p style="margin-top: 4px;">
            @if($supplier->is_active)
                <span class="badge badge-success">Active</span>
            @else
                <span class="badge badge-danger">Inactive</span>
            @endif
            @if($supplier->account_number)
                &nbsp;
                <span style="font-size: 0.8rem; color: var(--color-text-muted);">Acct: {{ $supplier->account_number }}</span>
            @endif
        </p>
    </div>
    <div class="profile-actions">
        <a href="{{ route('suppliers.payments', $supplier) }}" class="btn btn-primary">
            Record / View Payments
        </a>
        <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-outline">
             Edit
        </a>
        <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}"
              onsubmit="return confirm('Delete {{ addslashes($supplier->name) }}? This cannot be undone.')"
              style="display:inline;">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-outline" style="color: var(--color-danger); border-color: var(--color-danger);">
                 Delete
            </button>
        </form>
        <a href="{{ route('suppliers.index') }}" class="btn btn-outline">
            &#8592; Back
        </a>
    </div>
</div>

{{-- KPI Strip --}}
<div class="kpi-strip">
    <div class="kpi-item">
        <span class="kpi-value">{{ $stats['total_orders'] }}</span>
        <span class="kpi-label">Total Orders</span>
    </div>
    <div class="kpi-item">
        <span class="kpi-value">KSh {{ number_format($stats['total_spent'], 0) }}</span>
        <span class="kpi-label">Total Spent</span>
    </div>
    <div class="kpi-item">
        <span class="kpi-value" style="{{ $stats['unpaid_balance'] > 0 ? 'color: var(--color-danger);' : 'color: var(--color-success);' }}">
            KSh {{ number_format($stats['unpaid_balance'], 0) }}
        </span>
        <span class="kpi-label">Outstanding Balance</span>
    </div>
</div>

{{-- Notes --}}
@if($supplier->notes)
    <div class="report-section" style="margin-bottom: var(--space-lg); padding: var(--space-lg);">
        <strong> Notes:</strong>
        <p style="margin-top: 8px; color: var(--color-text-muted); font-size: var(--text-sm);">
            {{ $supplier->notes }}
        </p>
    </div>
@endif

{{-- Recent Purchase Orders --}}
<div class="report-section">
    <div class="report-section-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2> Recent Purchase Orders</h2>
        <a href="{{ route('purchases.index', ['supplier_id' => $supplier->id]) }}" class="btn btn--outline btn--sm">
            View All Orders
        </a>
    </div>

    @if($recentOrders->isEmpty())
        <div class="empty-state">
            
            <h3>No purchase orders yet</h3>
            <p>Create a purchase order from this supplier to get started.</p>
            <a href="{{ route('purchases.create') }}" class="btn btn--primary" style="margin-top: 1rem;">
                New Purchase Order
            </a>
        </div>
    @else
        <div class="table-wrapper">
            <table class="table-responsive-cards">
                <thead>
                    <tr>
                        <th>PO Number</th>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentOrders as $po)
                    <tr>
                        <td data-label="PO Number">
                            <a href="{{ route('purchases.show', $po) }}"
                               style="font-weight: 700; color: var(--color-primary);">
                                {{ $po->po_number }}
                            </a>
                        </td>
                        <td data-label="Date" style="color: var(--color-text-muted); font-size: 0.875rem;">
                            {{ $po->order_date->format('d M Y') }}
                        </td>
                        <td data-label="Total">
                            <strong>KSh {{ number_format($po->total, 2) }}</strong>
                        </td>
                        <td data-label="Paid">
                            KSh {{ number_format($po->amount_paid, 2) }}
                        </td>
                        <td data-label="Status">
                            @if($po->status === 'received')
                                <span class="badge badge-success">Received</span>
                            @elseif($po->status === 'partially_received')
                                <span class="badge badge-warning">Part. Received</span>
                            @elseif($po->status === 'ordered')
                                <span class="badge" style="background: #dbeafe; color: #1d4ed8;">Ordered</span>
                            @elseif($po->status === 'cancelled')
                                <span class="badge badge-danger">Cancelled</span>
                            @else
                                <span class="badge badge-neutral">{{ ucfirst($po->status) }}</span>
                            @endif
                        </td>
                        <td data-label="Actions">
                            <a href="{{ route('purchases.show', $po) }}" class="btn btn--outline btn--sm" title="View">
                                View
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="padding: var(--space-md); text-align: center; border-top: 1px solid var(--color-border);">
            <a href="{{ route('purchases.index', ['supplier_id' => $supplier->id]) }}"
               style="color: var(--color-primary); font-size: 0.875rem; font-weight: 600; text-decoration: none;">
                View all orders for {{ $supplier->name }} &rarr;
            </a>
        </div>
    @endif
</div>

@endsection
