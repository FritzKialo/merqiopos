@extends('layouts.app')
@section('title', 'Customers')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/customers.css') }}?v={{ @filemtime(public_path('css/customers.css')) ?: '1' }}">
@endpush

@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Customers</h1>
            <p class="page-subtitle">Manage relationships and track customer accounts.</p>
        </div>
        <div class="action-buttons">
            @role('owner','manager')
            @if(auth()->user()->currentBusiness()?->hasFeature('data_export'))
            <a href="{{ route('customers.export') }}" class="btn btn--outline">Export CSV</a>
            @endif
            @endrole
            <a href="{{ route('customers.create') }}" class="btn btn--primary">Add Customer</a>
        </div>
    </div>

    {{-- KPI Strip --}}
    <div class="kpi-strip">
        <div class="kpi-item">
            <span class="kpi-value">{{ $stats['total'] }}</span>
            <span class="kpi-label">Total Customers</span>
        </div>
        <div class="kpi-item">
            <span class="kpi-value">{{ $stats['new_month'] }}</span>
            <span class="kpi-label">New This Month</span>
        </div>
        <div class="kpi-item">
            <span class="kpi-value" style="{{ $stats['with_debt'] > 0 ? 'color: var(--color-danger);' : '' }}">{{ $stats['with_debt'] }}</span>
            <span class="kpi-label">Active Debtors</span>
        </div>
        <div class="kpi-item">
            <span class="kpi-value" style="{{ $stats['total_debt'] > 0 ? 'color: var(--color-danger);' : '' }}">
                KES {{ number_format($stats['total_debt'], 2) }}
            </span>
            <span class="kpi-label">Total Debt</span>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('customers.index') }}" id="filterForm">
        <div class="toolbar">
            <div class="toolbar-search">
                <input type="text" name="search" placeholder="Name, phone or email..." value="{{ request('search') }}">
            </div>

            <div style="display: flex; align-items: center; gap: 12px; margin-left: auto; flex-wrap: wrap;">
                <label style="display: flex; align-items: center; gap: 6px; font-size: 0.875rem; color: var(--color-text-muted); cursor: pointer;">
                    <input type="checkbox" name="with_debt" value="1" {{ request('with_debt') ? 'checked' : '' }} onchange="this.form.submit()">
                    Debtors only
                </label>
                @if(request()->hasAny(['search', 'with_debt']))
                    <a href="{{ route('customers.index') }}" class="btn btn--outline btn--sm">Clear</a>
                @endif
                <button type="submit" class="btn btn--primary btn--sm">Search</button>
            </div>
        </div>
    </form>

    {{-- Table --}}
    <div class="table-section">
        @if($customers->isEmpty())
            <div class="empty-state">
                <h3>No customers found</h3>
                <p>Try adjusting your search or add a new customer.</p>
                <a href="{{ route('customers.create') }}" class="btn btn--primary" style="margin-top: 1rem;">Add Customer</a>
            </div>
        @else
            <div class="table-wrapper">
                <table class="table-responsive-cards">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Contact</th>
                            <th>Total Spent</th>
                            <th>Orders</th>
                            <th>Balance</th>
                            <th>Member Since</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($customers as $customer)
                        <tr>
                            <td data-label="Customer">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div class="customer-avatar">{{ strtoupper(substr($customer->name, 0, 1)) }}</div>
                                    <a href="{{ route('customers.show', $customer) }}" style="font-weight: 700; color: var(--color-text);">
                                        {{ $customer->name }}
                                    </a>
                                </div>
                            </td>
                            <td data-label="Contact">
                                <div style="font-size: 0.875rem;">{{ $customer->phone ?? '—' }}</div>
                                <div style="font-size: 0.75rem; color: var(--color-text-muted);">{{ $customer->email ?? '—' }}</div>
                            </td>
                            <td data-label="Total Spent">KES {{ number_format($customer->totalSpent(), 2) }}</td>
                            <td data-label="Orders">{{ $customer->totalPurchases() }}</td>
                            <td data-label="Balance">
                                @if($customer->balance_owed > 0)
                                    <span style="color: var(--color-danger); font-weight: 700;">KES {{ number_format($customer->balance_owed, 2) }}</span>
                                @else
                                    <span class="badge badge-success">Cleared</span>
                                @endif
                            </td>
                            <td data-label="Member Since" style="color: var(--color-text-muted); font-size: 0.8rem;">
                                {{ $customer->created_at->format('d M Y') }}
                            </td>
                            <td data-label="Actions">
                                <div class="action-buttons">
                                    <a href="{{ route('customers.show', $customer) }}" class="btn btn--outline btn--sm">View</a>
                                    @role('owner','manager')
                                    <a href="{{ route('customers.edit', $customer) }}" class="btn btn--primary btn--sm">Edit</a>
                                    <form method="POST" action="{{ route('customers.destroy', $customer) }}" onsubmit="return confirm('Delete {{ addslashes($customer->name) }}?')" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn--danger btn--sm">Delete</button>
                                    </form>
                                    @endrole
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $customers->links('vendor.pagination.custom') }}
        @endif
    </div>
</div>
@endsection
