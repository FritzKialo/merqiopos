@extends('layouts.app')
@section('title', $recurringInvoice->title)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/sales.css') }}">
    <style>
        .ri-info-grid{ display:grid; grid-template-columns:repeat(3,1fr); gap:var(--space-4); margin-bottom:var(--space-5); }
        @media(max-width:768px){ .ri-info-grid{ grid-template-columns:1fr; } }
        .info-card{ background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-lg); padding:var(--space-4); }
        .info-card h4{ font-size:var(--text-xs); font-weight:700; color:var(--color-text-muted); text-transform:uppercase; letter-spacing:.07em; margin-bottom:var(--space-3); padding-bottom:var(--space-2); border-bottom:1px solid var(--color-border); }
        .info-card p{ font-size:var(--text-sm); color:var(--color-text); margin:4px 0; }
        .totals-box{ max-width:340px; margin-left:auto; background:var(--color-surface-2); border:1px solid var(--color-border); border-radius:var(--radius-lg); overflow:hidden; }
        .totals-row{ display:flex; justify-content:space-between; padding:10px 16px; font-size:var(--text-sm); color:var(--color-text-muted); border-bottom:1px solid var(--color-border); }
        .totals-row:last-child{ border-bottom:none; }
        .totals-row.grand-total{ background:var(--color-primary); color:#fff; font-weight:700; font-size:var(--text-base); padding:12px 16px; }
    </style>
@endpush

@section('content')
<div class="page">

    {{-- Header --}}
    <div class="page-header">
        <div>
            <div style="display:flex; align-items:center; gap:var(--space-3); flex-wrap:wrap; margin-bottom:var(--space-2);">
                <h1 class="page-title" style="margin:0;">{{ $recurringInvoice->title }}</h1>
                @if($recurringInvoice->is_active)
                    <span class="badge badge-success" style="font-size:var(--text-sm);">Active</span>
                @else
                    <span class="badge badge-secondary" style="font-size:var(--text-sm);">Paused</span>
                @endif
                <span class="badge" style="background:var(--color-surface-2); color:var(--color-text); border:1px solid var(--color-border); font-size:var(--text-sm);">
                    {{ ucfirst($recurringInvoice->frequency) }}
                </span>
            </div>
            <p class="page-subtitle">
                <a href="{{ route('recurring.index') }}" style="color:var(--color-text-muted);">
                     Back to Recurring Invoices
                </a>
            </p>
        </div>
        <div class="action-buttons" style="display:flex; gap:var(--space-2); flex-wrap:wrap;">
            <a href="{{ route('recurring.edit', $recurringInvoice) }}" class="btn btn--outline">
                 Edit
            </a>
            <form method="POST" action="{{ route('recurring.toggle', $recurringInvoice) }}" style="display:inline;">
                @csrf @method('PATCH')
                <button type="submit" class="btn {{ $recurringInvoice->is_active ? 'btn--warning' : 'btn--success' }}">
                    
                    {{ $recurringInvoice->is_active ? 'Pause' : 'Resume' }}
                </button>
            </form>
            @if($recurringInvoice->is_active)
            <form method="POST" action="{{ route('recurring.run-now', $recurringInvoice) }}" style="display:inline;"
                  onsubmit="return confirm('Generate a sale invoice right now from this template?')">
                @csrf
                <button type="submit" class="btn btn--primary">
                     Run Now
                </button>
            </form>
            @endif
            <form method="POST" action="{{ route('recurring.destroy', $recurringInvoice) }}" style="display:inline;"
                  onsubmit="return confirm('Delete this recurring invoice? This cannot be undone.')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn--danger">
                    Delete
                </button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success"> {{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger"> {{ session('error') }}</div>
    @endif

    {{-- Info Cards --}}
    <div class="ri-info-grid">
        <div class="info-card">
            <h4>Schedule</h4>
            <p><strong>Frequency:</strong> {{ ucfirst($recurringInvoice->frequency) }}</p>
            <p><strong>Next Run:</strong>
                @if($recurringInvoice->is_active)
                    <span style="{{ $recurringInvoice->isDue() ? 'color:var(--color-danger);font-weight:700;' : '' }}">
                        {{ $recurringInvoice->next_run_date->format('d M Y') }}
                    </span>
                @else
                    <span style="color:var(--color-text-muted);">Paused</span>
                @endif
            </p>
            <p><strong>Last Run:</strong> {{ $recurringInvoice->last_run_date ? $recurringInvoice->last_run_date->format('d M Y') : 'Never' }}</p>
            <p><strong>End Date:</strong> {{ $recurringInvoice->end_date ? $recurringInvoice->end_date->format('d M Y') : 'No end date' }}</p>
        </div>
        <div class="info-card">
            <h4>Customer</h4>
            @if($recurringInvoice->customer)
                <p><strong>{{ $recurringInvoice->customer->name }}</strong></p>
                <p>{{ $recurringInvoice->customer->phone ?? '' }}</p>
                <p>{{ $recurringInvoice->customer->email ?? '' }}</p>
                <a href="{{ route('customers.show', $recurringInvoice->customer) }}" style="font-size:var(--text-xs); color:var(--color-primary);">View Customer</a>
            @else
                <p style="color:var(--color-text-muted);">No customer assigned</p>
            @endif
        </div>
        <div class="info-card">
            <h4>Statistics</h4>
            <p><strong>Invoices Generated:</strong> {{ $recurringInvoice->run_count }}</p>
            <p><strong>Total Value Each:</strong> KSh {{ number_format($recurringInvoice->total, 2) }}</p>
            <p><strong>Total Generated:</strong> KSh {{ number_format($recurringInvoice->total * $recurringInvoice->run_count, 2) }}</p>
            <p><strong>Created By:</strong> {{ $recurringInvoice->user->name ?? '—' }}</p>
        </div>
    </div>

    {{-- Items Table --}}
    <div class="report-section" style="margin-bottom:var(--space-5);">
        <div class="report-section-header">
            <h2> Line Items</h2>
        </div>
        <div class="table-responsive-cards">
            <table>
                <thead>
                    <tr>
                        <th>Product / Service</th>
                        <th>Description</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recurringInvoice->items as $item)
                    <tr>
                        <td data-label="Product"><strong>{{ $item->product_name }}</strong></td>
                        <td data-label="Description">{{ $item->description ?? '—' }}</td>
                        <td data-label="Qty">{{ $item->quantity }}</td>
                        <td data-label="Unit Price">KSh {{ number_format($item->unit_price, 2) }}</td>
                        <td data-label="Subtotal"><strong>KSh {{ number_format($item->subtotal, 2) }}</strong></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Totals --}}
        <div style="padding:var(--space-4);">
            <div class="totals-box">
                <div class="totals-row"><span>Subtotal</span><span>KSh {{ number_format($recurringInvoice->subtotal, 2) }}</span></div>
                @if($recurringInvoice->discount_amount > 0)
                <div class="totals-row"><span>Discount</span><span>- KSh {{ number_format($recurringInvoice->discount_amount, 2) }}</span></div>
                @endif
                @if($recurringInvoice->tax_amount > 0)
                <div class="totals-row"><span>Tax ({{ $recurringInvoice->tax_rate }}%)</span><span>KSh {{ number_format($recurringInvoice->tax_amount, 2) }}</span></div>
                @endif
                <div class="totals-row grand-total"><span>Total Per Invoice</span><span>KSh {{ number_format($recurringInvoice->total, 2) }}</span></div>
            </div>
        </div>
    </div>

    @if($recurringInvoice->notes)
    <div class="report-section">
        <div class="report-section-header"><h2> Notes</h2></div>
        <div style="padding:var(--space-4);"><p style="color:var(--color-text-muted);">{{ $recurringInvoice->notes }}</p></div>
    </div>
    @endif

    <div class="report-section">
        <div style="padding:var(--space-4);">
            @include('partials.attachments', ['modelType' => 'RecurringInvoice', 'modelId' => $recurringInvoice->id])
        </div>
    </div>

</div>
@endsection
