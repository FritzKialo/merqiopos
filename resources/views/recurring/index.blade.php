@extends('layouts.app')
@section('title', 'Recurring Invoices')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/sales.css') }}">
@endpush

@section('content')
<div class="page">

    <div class="page-header">
        <div>
            <h1 class="page-title">Recurring Invoices</h1>
            <p class="page-subtitle">Automate repeating invoices for regular customers.</p>
        </div>
        <div class="action-buttons">
            <a href="{{ route('recurring.create') }}" class="btn btn--primary">
                
                New Recurring Invoice
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success"> {{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger"> {{ session('error') }}</div>
    @endif

    {{-- KPI Strip --}}
    <div class="kpi-strip">
        <div class="kpi-item">
            <span class="kpi-value">{{ $stats['active'] }}</span>
            <span class="kpi-label">Active</span>
        </div>
        <div class="kpi-item">
            <span class="kpi-value">{{ $stats['paused'] }}</span>
            <span class="kpi-label">Paused</span>
        </div>
        <div class="kpi-item">
            <span class="kpi-value">{{ $stats['due_today'] }}</span>
            <span class="kpi-label">Due Today</span>
        </div>
        <div class="kpi-item">
            <span class="kpi-value">{{ $stats['generated_month'] }}</span>
            <span class="kpi-label">Generated This Month</span>
        </div>
    </div>

    {{-- Table --}}
    <div class="table-section">
        @if($recurringInvoices->isEmpty())
            <div style="text-align:center; padding: var(--space-12); color: var(--color-text-muted);">
                
                <p>No recurring invoices yet.</p>
                <a href="{{ route('recurring.create') }}" class="btn btn--primary" style="margin-top:var(--space-3);">Create Your First</a>
            </div>
        @else
            <div class="table-responsive-cards">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Customer</th>
                            <th>Frequency</th>
                            <th>Next Run</th>
                            <th>Last Run</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recurringInvoices as $ri)
                        <tr>
                            <td data-label="Title">
                                <a href="{{ route('recurring.show', $ri) }}" style="font-weight:700; color:var(--color-primary);">
                                    {{ $ri->title }}
                                </a>
                            </td>
                            <td data-label="Customer">{{ $ri->customer->name ?? '—' }}</td>
                            <td data-label="Frequency">
                                <span class="badge" style="background:var(--color-surface-2); color:var(--color-text); border:1px solid var(--color-border);">
                                    {{ ucfirst($ri->frequency) }}
                                </span>
                            </td>
                            <td data-label="Next Run">
                                @if($ri->is_active)
                                    <span style="{{ $ri->isDue() ? 'color:var(--color-danger); font-weight:700;' : '' }}">
                                        {{ $ri->next_run_date->format('d M Y') }}
                                        @if($ri->isDue())  @endif
                                    </span>
                                @else
                                    <span style="color:var(--color-text-muted);">Paused</span>
                                @endif
                            </td>
                            <td data-label="Last Run">{{ $ri->last_run_date ? $ri->last_run_date->format('d M Y') : '—' }}</td>
                            <td data-label="Total"><strong>KSh {{ number_format($ri->total, 2) }}</strong></td>
                            <td data-label="Status">
                                @if($ri->is_active)
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="badge badge-secondary">Paused</span>
                                @endif
                            </td>
                            <td data-label="Actions">
                                <div style="display:flex; gap:var(--space-2); flex-wrap:wrap;">
                                    <a href="{{ route('recurring.show', $ri) }}" class="btn btn--secondary btn--sm">View</a>
                                    <form method="POST" action="{{ route('recurring.toggle', $ri) }}" style="display:inline;">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn btn--sm" style="background:{{ $ri->is_active ? 'var(--color-warning-light)' : 'var(--color-success-light)' }}; color:{{ $ri->is_active ? 'var(--color-warning)' : 'var(--color-success)' }}; border:none; cursor:pointer;">
                                            {{ $ri->is_active ? 'Pause' : 'Resume' }}
                                        </button>
                                    </form>
                                    @if($ri->is_active)
                                    <form method="POST" action="{{ route('recurring.run-now', $ri) }}" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="btn btn--primary btn--sm">Run Now</button>
                                    </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="padding: var(--space-4);">
                {{ $recurringInvoices->links('vendor.pagination.custom') }}
            </div>
        @endif
    </div>

</div>
@endsection
