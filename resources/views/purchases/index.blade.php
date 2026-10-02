@extends('layouts.app')
@section('title', 'Purchase Orders')

@section('content')
<div class="page">
    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Purchase Orders</h1>
            <p class="page-subtitle">Track stock purchases and supplier payments.</p>
        </div>
        <div class="action-buttons">
            <a href="{{ route('purchases.create') }}" class="btn btn--primary">
                
                New Purchase Order
            </a>
        </div>
    </div>

    {{-- KPI Strip --}}
    <div class="kpi-strip">
        <div class="kpi-item">
            <span class="kpi-value">{{ $stats['total'] }}</span>
            <span class="kpi-label">Total POs</span>
        </div>
        <div class="kpi-item">
            <span class="kpi-value">{{ $stats['pending'] }}</span>
            <span class="kpi-label">Pending</span>
        </div>
        <div class="kpi-item">
            <span class="kpi-value">KSh {{ number_format($stats['this_month'], 2) }}</span>
            <span class="kpi-label">This Month Spent</span>
        </div>
        <div class="kpi-item">
            <span class="kpi-value" style="{{ $stats['unpaid_balance'] > 0 ? 'color:var(--color-danger);' : '' }}">
                KSh {{ number_format($stats['unpaid_balance'], 2) }}
            </span>
            <span class="kpi-label">Unpaid Balance</span>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('purchases.index') }}" id="filterForm">
        <div class="toolbar" style="flex-wrap: wrap; gap: 10px;">
            <select name="status" class="form-control" style="width: auto; font-size: 0.875rem;" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="draft"              {{ request('status') === 'draft'              ? 'selected' : '' }}>Draft</option>
                <option value="ordered"            {{ request('status') === 'ordered'            ? 'selected' : '' }}>Ordered</option>
                <option value="partially_received" {{ request('status') === 'partially_received' ? 'selected' : '' }}>Partially Received</option>
                <option value="received"           {{ request('status') === 'received'           ? 'selected' : '' }}>Received</option>
                <option value="cancelled"          {{ request('status') === 'cancelled'          ? 'selected' : '' }}>Cancelled</option>
            </select>

            <select name="supplier_id" class="form-control" style="width: auto; font-size: 0.875rem;" onchange="this.form.submit()">
                <option value="">All Suppliers</option>
                @foreach($suppliers as $s)
                    <option value="{{ $s->id }}" {{ request('supplier_id') == $s->id ? 'selected' : '' }}>
                        {{ $s->name }}
                    </option>
                @endforeach
            </select>

            <input type="date" name="date_from" class="form-control" style="width: auto; font-size: 0.875rem;"
                   value="{{ request('date_from') }}" placeholder="From date">

            <input type="date" name="date_to" class="form-control" style="width: auto; font-size: 0.875rem;"
                   value="{{ request('date_to') }}" placeholder="To date">

            <div style="display: flex; align-items: center; gap: 8px; margin-left: auto;">
                @if(request()->hasAny(['status', 'supplier_id', 'date_from', 'date_to']))
                    <a href="{{ route('purchases.index') }}" class="btn btn--outline btn--sm">Clear</a>
                @endif
                <button type="submit" class="btn btn--primary btn--sm">Filter</button>
            </div>
        </div>
    </form>

    {{-- Table --}}
    <div class="table-section">
        @if($purchaseOrders->isEmpty())
            <div class="empty-state">
                
                <h3>No purchase orders found</h3>
                <p>Try adjusting your filters or create a new purchase order.</p>
                <a href="{{ route('purchases.create') }}" class="btn btn--primary" style="margin-top: 1rem;">New Purchase Order</a>
            </div>
        @else
            <div class="table-wrapper">
                <table class="table-responsive-cards">
                    <thead>
                        <tr>
                            <th>PO #</th>
                            <th>Supplier</th>
                            <th>Order Date</th>
                            <th>Expected</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($purchaseOrders as $po)
                        <tr>
                            <td data-label="PO #">
                                <a href="{{ route('purchases.show', $po) }}"
                                   style="font-weight: 700; color: var(--color-primary);">
                                    {{ $po->po_number }}
                                </a>
                            </td>
                            <td data-label="Supplier">
                                @if($po->supplier)
                                    <a href="{{ route('suppliers.show', $po->supplier) }}"
                                       style="color: var(--color-text);">
                                        {{ $po->supplier->name }}
                                    </a>
                                @else
                                    <span style="color: var(--color-text-muted);">—</span>
                                @endif
                            </td>
                            <td data-label="Order Date" style="color: var(--color-text-muted); font-size: 0.875rem;">
                                {{ $po->order_date->format('d M Y') }}
                            </td>
                            <td data-label="Expected" style="color: var(--color-text-muted); font-size: 0.875rem;">
                                {{ $po->expected_date ? $po->expected_date->format('d M Y') : '—' }}
                            </td>
                            <td data-label="Total">
                                <strong>KSh {{ number_format($po->total, 2) }}</strong>
                            </td>
                            <td data-label="Paid">
                                KSh {{ number_format($po->amount_paid, 2) }}
                            </td>
                            <td data-label="Status">
                                @if($po->status === 'draft')
                                    <span class="badge badge-neutral">Draft</span>
                                @elseif($po->status === 'ordered')
                                    <span class="badge" style="background: #dbeafe; color: #1d4ed8;">Ordered</span>
                                @elseif($po->status === 'partially_received')
                                    <span class="badge badge-warning">Part. Received</span>
                                @elseif($po->status === 'received')
                                    <span class="badge badge-success">Received</span>
                                @elseif($po->status === 'cancelled')
                                    <span class="badge badge-danger">Cancelled</span>
                                @endif
                            </td>
                            <td data-label="Payment">
                                @if($po->payment_status === 'paid')
                                    <span class="badge badge-success">Paid</span>
                                @elseif($po->payment_status === 'partial')
                                    <span class="badge badge-warning">Partial</span>
                                @else
                                    <span class="badge badge-danger">Unpaid</span>
                                @endif
                            </td>
                            <td data-label="Actions">
                                <div class="action-buttons">
                                    <a href="{{ route('purchases.show', $po) }}" class="btn btn--outline btn--sm" title="View">
                                        View
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $purchaseOrders->links('vendor.pagination.custom') }}
        @endif
    </div>
</div>
@endsection
