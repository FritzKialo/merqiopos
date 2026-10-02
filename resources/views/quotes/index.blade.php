@extends('layouts.app')
@section('title', 'Quotes & Estimates')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/sales.css') }}">
@endpush

@section('content')
<div class="page">
    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Quotes & Estimates</h1>
            <p class="page-subtitle">Create and manage quotes, estimates, and convert them to sales.</p>
        </div>
        <div class="action-buttons">
            <a href="{{ route('quotes.create') }}" class="btn btn--primary">
                
                New Quote
            </a>
        </div>
    </div>

    {{-- KPI Strip --}}
    <div class="kpi-strip">
        <div class="kpi-item">
            <span class="kpi-value">{{ $stats['total'] }}</span>
            <span class="kpi-label">Total Quotes</span>
        </div>
        <div class="kpi-item">
            <span class="kpi-value">{{ $stats['pending'] }}</span>
            <span class="kpi-label">Pending</span>
        </div>
        <div class="kpi-item">
            <span class="kpi-value">{{ $stats['accepted'] }}</span>
            <span class="kpi-label">Accepted</span>
        </div>
        <div class="kpi-item">
            <span class="kpi-value">{{ $stats['converted'] }}</span>
            <span class="kpi-label">Converted This Month</span>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="alert alert-success" style="margin-bottom: var(--space-4);">
            
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger" style="margin-bottom: var(--space-4);">
            
            {{ session('error') }}
        </div>
    @endif

    {{-- Filters --}}
    <form method="GET" action="{{ route('quotes.index') }}" id="filterForm">
        <div class="toolbar">
            <div class="toolbar-search">
                
                <input type="text" name="search" placeholder="Quote # or customer name..." value="{{ request('search') }}">
            </div>

            <select name="status" class="toolbar-select" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="draft"     {{ request('status') == 'draft'     ? 'selected' : '' }}>Draft</option>
                <option value="sent"      {{ request('status') == 'sent'      ? 'selected' : '' }}>Sent</option>
                <option value="accepted"  {{ request('status') == 'accepted'  ? 'selected' : '' }}>Accepted</option>
                <option value="rejected"  {{ request('status') == 'rejected'  ? 'selected' : '' }}>Rejected</option>
                <option value="expired"   {{ request('status') == 'expired'   ? 'selected' : '' }}>Expired</option>
                <option value="converted" {{ request('status') == 'converted' ? 'selected' : '' }}>Converted</option>
            </select>

            <div style="display: flex; gap: 8px;">
                <input type="date" name="date_from" class="toolbar-select" value="{{ request('date_from') }}" placeholder="From">
                <input type="date" name="date_to"   class="toolbar-select" value="{{ request('date_to') }}"   placeholder="To">
            </div>

            <button type="submit" class="btn btn--outline btn--sm">
                 Filter
            </button>

            <div style="margin-left: auto;">
                @if(request()->hasAny(['search','status','date_from','date_to']))
                    <a href="{{ route('quotes.index') }}" class="btn btn--outline btn--sm">Clear Filters</a>
                @endif
            </div>
        </div>
    </form>

    {{-- Quotes Table --}}
    <div class="table-section">
        @if($quotes->isEmpty())
            <div class="empty-state">
                
                <h3>No quotes found</h3>
                <p>Create your first quote or estimate to get started.</p>
                <a href="{{ route('quotes.create') }}" class="btn btn--primary" style="margin-top: 1rem;">
                     New Quote
                </a>
            </div>
        @else
            <div class="table-wrapper">
                <table class="table-responsive-cards">
                    <thead>
                        <tr>
                            <th>Quote #</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Valid Until</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($quotes as $quote)
                        <tr>
                            <td data-label="Quote #">
                                <span class="invoice-number">{{ $quote->quote_number }}</span>
                            </td>
                            <td data-label="Customer">
                                <span class="customer-name">{{ $quote->customer->name ?? '—' }}</span>
                            </td>
                            <td data-label="Date" style="color: var(--color-text-muted); font-size: 0.85rem;">
                                {{ $quote->quote_date->format('d M Y') }}
                            </td>
                            <td data-label="Valid Until" style="font-size: 0.85rem;">
                                @if($quote->valid_until)
                                    <span style="color: {{ $quote->valid_until->isPast() && !in_array($quote->status, ['converted','accepted']) ? 'var(--color-danger)' : 'var(--color-text-muted)' }};">
                                        {{ $quote->valid_until->format('d M Y') }}
                                    </span>
                                @else
                                    <span style="color: var(--color-text-muted);">—</span>
                                @endif
                            </td>
                            <td data-label="Total">
                                <span class="sales-total">KSh {{ number_format($quote->total, 0) }}</span>
                            </td>
                            <td data-label="Status">
                                @if($quote->status === 'draft')
                                    <span class="badge badge-neutral">Draft</span>
                                @elseif($quote->status === 'sent')
                                    <span class="badge badge-blue">Sent</span>
                                @elseif($quote->status === 'accepted')
                                    <span class="badge badge-success">Accepted</span>
                                @elseif($quote->status === 'rejected')
                                    <span class="badge badge-danger">Rejected</span>
                                @elseif($quote->status === 'expired')
                                    <span class="badge" style="background: #fff7ed; color: #ea580c;">Expired</span>
                                @elseif($quote->status === 'converted')
                                    <span class="badge badge-purple">Converted</span>
                                @endif
                            </td>
                            <td data-label="Actions">
                                <div class="action-buttons">
                                    <a href="{{ route('quotes.show', $quote) }}"
                                       class="btn btn--outline btn--sm" title="View">
                                        View
                                    </a>
                                    @if(in_array($quote->status, ['draft', 'sent']))
                                        <a href="{{ route('quotes.edit', $quote) }}"
                                           class="btn btn--outline btn--sm" title="Edit">
                                            Edit
                                        </a>
                                    @endif
                                    @if($quote->canBeConverted())
                                        <form method="POST"
                                              action="{{ route('quotes.convert', $quote) }}"
                                              onsubmit="return confirm('Convert this quote to a sale?')"
                                              style="display:inline;">
                                            @csrf
                                            <button type="submit" class="btn btn--primary btn--sm" title="Convert to Sale">
                                                Convert
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $quotes->links('vendor.pagination.custom') }}
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Auto-submit date filters on change
    document.querySelectorAll('input[name="date_from"], input[name="date_to"]').forEach(function(el) {
        el.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    });

    // Auto-hide alerts
    document.querySelectorAll('.alert').forEach(function(alert) {
        setTimeout(function() {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s';
            setTimeout(function() { alert.remove(); }, 500);
        }, 4000);
    });
</script>
@endpush
