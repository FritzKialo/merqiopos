@extends('layouts.app')
@section('title', 'Stock Adjustments')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/inventory.css') }}?v={{ @filemtime(public_path('css/inventory.css')) ?: '1' }}">
@endpush

@section('content')
<div class="page">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Stock Adjustments</h1>
            <p class="page-subtitle">Track every addition, deduction, and correction to your inventory.</p>
        </div>
        <div class="action-buttons">
            <a href="{{ route('inventory.adjustments.create') }}" class="btn btn--primary">
                
                New Adjustment
            </a>
        </div>
    </div>

    {{-- KPI Strip --}}
    <div class="kpi-strip">
        <div class="kpi-item">
            <span class="kpi-value" style="color: var(--color-success);">+{{ number_format($stats['additions_today']) }}</span>
            <span class="kpi-label">Today's Additions</span>
        </div>
        <div class="kpi-item">
            <span class="kpi-value" style="color: var(--color-danger);">-{{ number_format($stats['deductions_today']) }}</span>
            <span class="kpi-label">Today's Deductions</span>
        </div>
        <div class="kpi-item">
            <span class="kpi-value">{{ number_format($stats['adjusted_this_month']) }}</span>
            <span class="kpi-label">Products Adjusted This Month</span>
        </div>
    </div>

    {{-- Filter Bar --}}
    <form method="GET" action="{{ route('inventory.adjustments') }}">
        <div class="toolbar">
            <select name="product_id" class="toolbar-select" onchange="this.form.submit()">
                <option value="">All Products</option>
                @foreach($products as $p)
                    <option value="{{ $p->id }}" {{ request('product_id') == $p->id ? 'selected' : '' }}>
                        {{ $p->name }} ({{ $p->sku }})
                    </option>
                @endforeach
            </select>

            <select name="type" class="toolbar-select" onchange="this.form.submit()">
                <option value="">All Types</option>
                <option value="addition"   {{ request('type') === 'addition'   ? 'selected' : '' }}>Addition</option>
                <option value="deduction"  {{ request('type') === 'deduction'  ? 'selected' : '' }}>Deduction</option>
                <option value="correction" {{ request('type') === 'correction' ? 'selected' : '' }}>Correction</option>
            </select>

            <div style="margin-left: auto;">
                @if(request()->hasAny(['product_id', 'type']))
                    <a href="{{ route('inventory.adjustments') }}" class="btn btn--outline btn--sm">Clear</a>
                @endif
            </div>
        </div>
    </form>

    {{-- Adjustments Table --}}
    <div class="table-section">
        @if($adjustments->isEmpty())
            <div class="empty-state">
                
                <h3>No adjustments found</h3>
                <p>Record your first stock adjustment to get started.</p>
                <a href="{{ route('inventory.adjustments.create') }}"
                   class="btn btn--primary"
                   style="margin-top: 1rem;">New Adjustment</a>
            </div>
        @else
            <div class="table-wrapper">
                <table class="table-responsive-cards">
                    <thead>
                        <tr>
                            <th>Date / Time</th>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Type</th>
                            <th>Before</th>
                            <th>Change</th>
                            <th>After</th>
                            <th>Reason</th>
                            <th>Recorded By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($adjustments as $adj)
                        <tr>
                            <td data-label="Date / Time">
                                <span style="white-space: nowrap;">
                                    {{ $adj->created_at->format('d M Y') }}
                                </span>
                                <div style="font-size: 0.75rem; color: var(--color-text-muted);">
                                    {{ $adj->created_at->format('H:i') }}
                                </div>
                            </td>
                            <td data-label="Product">
                                <span class="product-name">
                                    {{ $adj->product->name ?? '—' }}
                                </span>
                            </td>
                            <td data-label="SKU">
                                <div class="product-sku">{{ $adj->product->sku ?? '—' }}</div>
                            </td>
                            <td data-label="Type">
                                @if($adj->type === 'addition')
                                    <span class="badge badge-success">Addition</span>
                                @elseif($adj->type === 'deduction')
                                    <span class="badge badge-danger">Deduction</span>
                                @else
                                    <span class="badge badge-blue">Correction</span>
                                @endif
                            </td>
                            <td data-label="Before">
                                {{ number_format($adj->quantity_before) }}
                            </td>
                            <td data-label="Change">
                                @if($adj->type === 'addition')
                                    <span class="text-success" style="font-weight: 600;">
                                        +{{ number_format($adj->quantity_change) }}
                                    </span>
                                @elseif($adj->type === 'deduction')
                                    <span class="text-danger" style="font-weight: 600;">
                                        -{{ number_format($adj->quantity_change) }}
                                    </span>
                                @else
                                    <span class="text-info" style="font-weight: 600;">
                                        ={{ number_format($adj->quantity_after) }}
                                    </span>
                                @endif
                            </td>
                            <td data-label="After">
                                {{ number_format($adj->quantity_after) }}
                            </td>
                            <td data-label="Reason">
                                {{ ucwords(str_replace('_', ' ', $adj->reason)) }}
                            </td>
                            <td data-label="Recorded By">
                                {{ $adj->user->name ?? '—' }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $adjustments->links('vendor.pagination.custom') }}
        @endif
    </div>

</div>
@endsection

