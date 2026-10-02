@extends('layouts.app')
@section('title', 'Suppliers')

@section('content')
<div class="page">
    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Suppliers</h1>
            <p class="page-subtitle">Manage your suppliers and purchasing contacts.</p>
        </div>
        <div class="action-buttons">
            <a href="{{ route('suppliers.create') }}" class="btn btn--primary">
                
                New Supplier
            </a>
        </div>
    </div>

    {{-- KPI Strip --}}
    <div class="kpi-strip">
        <div class="kpi-item">
            <span class="kpi-value">{{ $stats['total'] }}</span>
            <span class="kpi-label">Total Suppliers</span>
        </div>
        <div class="kpi-item">
            <span class="kpi-value">{{ $stats['active'] }}</span>
            <span class="kpi-label">Active</span>
        </div>
        <div class="kpi-item">
            <span class="kpi-value">{{ $stats['inactive'] }}</span>
            <span class="kpi-label">Inactive</span>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('suppliers.index') }}" id="filterForm">
        <div class="toolbar">
            <div class="toolbar-search">
                
                <input type="text" name="search" placeholder="Name, phone or email..." value="{{ request('search') }}">
            </div>

            <div style="display: flex; align-items: center; gap: 12px; margin-left: auto; flex-wrap: wrap;">
                <select name="status" class="form-control" style="width: auto; font-size: 0.875rem;" onchange="this.form.submit()">
                    <option value="">All Suppliers</option>
                    <option value="active"   {{ request('status') === 'active'   ? 'selected' : '' }}>Active Only</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive Only</option>
                </select>

                @if(request()->hasAny(['search', 'status']))
                    <a href="{{ route('suppliers.index') }}" class="btn btn--outline btn--sm">Clear</a>
                @endif
                <button type="submit" class="btn btn--primary btn--sm">Search</button>
            </div>
        </div>
    </form>

    {{-- Table --}}
    <div class="table-section">
        @if($suppliers->isEmpty())
            <div class="empty-state">
                
                <h3>No suppliers found</h3>
                <p>Try adjusting your search or add a new supplier.</p>
                <a href="{{ route('suppliers.create') }}" class="btn btn--primary" style="margin-top: 1rem;">Add Supplier</a>
            </div>
        @else
            <div class="table-wrapper">
                <table class="table-responsive-cards">
                    <thead>
                        <tr>
                            <th>Supplier</th>
                            <th>Contact Person</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Total Orders</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($suppliers as $supplier)
                        <tr>
                            <td data-label="Supplier">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div class="customer-avatar">
                                        {{ strtoupper(substr($supplier->name, 0, 1)) }}
                                    </div>
                                    <a href="{{ route('suppliers.show', $supplier) }}" style="font-weight: 700; color: var(--color-text);">
                                        {{ $supplier->name }}
                                    </a>
                                </div>
                            </td>
                            <td data-label="Contact Person">
                                {{ $supplier->contact_person ?? '—' }}
                            </td>
                            <td data-label="Phone">
                                <div style="font-size: 0.875rem; color: var(--color-text);">{{ $supplier->phone ?? '—' }}</div>
                            </td>
                            <td data-label="Email">
                                <div style="font-size: 0.875rem; color: var(--color-text-muted);">{{ $supplier->email ?? '—' }}</div>
                            </td>
                            <td data-label="Total Orders">
                                <span class="badge badge-neutral shadow-sm">{{ $supplier->purchase_orders_count }}</span>
                            </td>
                            <td data-label="Status">
                                @if($supplier->is_active)
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="badge badge-danger">Inactive</span>
                                @endif
                            </td>
                            <td data-label="Actions">
                                <div class="action-buttons">
                                    <a href="{{ route('suppliers.show', $supplier) }}" class="btn btn--outline btn--sm" title="View">
                                        View
                                    </a>
                                    <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn--primary btn--sm" title="Edit">
                                        Edit
                                    </a>
                                    <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}" onsubmit="return confirm('Delete {{ addslashes($supplier->name) }}?')" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn--danger btn--sm" title="Delete">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $suppliers->links('vendor.pagination.custom') }}
        @endif
    </div>
</div>
@endsection
