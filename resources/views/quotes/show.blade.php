@extends('layouts.app')
@section('title', $quote->quote_number)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/sales.css') }}">
    <style>
        .quote-info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: var(--space-4);
            margin-bottom: var(--space-5);
        }

        @media (max-width: 768px) {
            .quote-info-grid { grid-template-columns: 1fr; }
        }

        .info-card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: var(--space-4);
            box-shadow: var(--shadow-xs);
        }

        .info-card h4 {
            font-size: var(--text-xs);
            font-weight: 700;
            color: var(--color-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.07em;
            margin-bottom: var(--space-3);
            padding-bottom: var(--space-2);
            border-bottom: 1px solid var(--color-border);
        }

        .info-card p {
            font-size: var(--text-sm);
            color: var(--color-text);
            margin: 4px 0;
        }

        .totals-box {
            max-width: 340px;
            margin-left: auto;
            background: var(--color-surface-2);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 16px;
            font-size: var(--text-sm);
            color: var(--color-text-muted);
            border-bottom: 1px solid var(--color-border);
        }

        .totals-row:last-child { border-bottom: none; }

        .totals-row.grand-total {
            background: var(--color-primary);
            color: #fff;
            font-weight: 700;
            font-size: var(--text-base);
            padding: 12px 16px;
        }

        .converted-notice {
            background: var(--color-info-light);
            border: 1px solid var(--color-info);
            border-radius: var(--radius-lg);
            padding: var(--space-4);
            margin-bottom: var(--space-5);
            display: flex;
            align-items: center;
            gap: var(--space-3);
            font-size: var(--text-sm);
            color: var(--color-info);
        }

        @media (max-width: 640px) {
            .quote-notes-terms-grid { grid-template-columns: 1fr !important; }
        }
    </style>
@endpush

@section('content')

{{-- Page Header --}}
<div class="page-header">
    <div>
        <h1 class="page-title" style="display: flex; align-items: center; gap: var(--space-3);">
            {{ $quote->quote_number }}
            @if($quote->status === 'draft')
                <span class="badge badge-neutral" style="font-size: 0.85rem;">Draft</span>
            @elseif($quote->status === 'sent')
                <span class="badge badge-blue" style="font-size: 0.85rem;">Sent</span>
            @elseif($quote->status === 'accepted')
                <span class="badge badge-success" style="font-size: 0.85rem;">Accepted</span>
            @elseif($quote->status === 'rejected')
                <span class="badge badge-danger" style="font-size: 0.85rem;">Rejected</span>
            @elseif($quote->status === 'expired')
                <span class="badge badge-warning" style="font-size: 0.85rem;">Expired</span>
            @elseif($quote->status === 'converted')
                <span class="badge badge-purple" style="font-size: 0.85rem;">Converted</span>
            @endif
        </h1>
        <p class="page-subtitle">
            Created by {{ $quote->user->name }} on {{ $quote->created_at->format('d M Y, g:i A') }}
        </p>
    </div>
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">

        {{-- Edit (draft only) --}}
        @if(in_array($quote->status, ['draft', 'sent']))
            <a href="{{ route('quotes.edit', $quote) }}" class="btn btn--outline">
                 Edit
            </a>
        @endif

        {{-- Print / PDF --}}
        <a href="{{ route('quotes.pdf', $quote) }}" target="_blank" class="btn btn--outline">
             Print
        </a>

        {{-- Email to customer --}}
        @if($quote->customer && $quote->customer->email)
            <form method="POST" action="{{ route('quotes.email', $quote) }}"
                  onsubmit="return confirm('Email this quote to {{ addslashes($quote->customer->email) }}?')"
                  style="display:inline;">
                @csrf
                <button type="submit" class="btn btn--outline">
                     Email Quote
                </button>
            </form>
        @endif

        {{-- Mark Sent --}}
        @if(in_array($quote->status, ['draft']))
            <form method="POST" action="{{ route('quotes.mark-sent', $quote) }}" style="display:inline;">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn--outline">
                     Mark Sent
                </button>
            </form>
        @endif

        {{-- Mark Accepted --}}
        @if(in_array($quote->status, ['sent', 'draft']))
            <form method="POST" action="{{ route('quotes.mark-accepted', $quote) }}" style="display:inline;">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn--outline" style="color: var(--color-success); border-color: var(--color-success);">
                     Mark Accepted
                </button>
            </form>
        @endif

        {{-- Mark Rejected --}}
        @if(in_array($quote->status, ['sent', 'draft', 'accepted']))
            <form method="POST" action="{{ route('quotes.mark-rejected', $quote) }}"
                  onsubmit="return confirm('Mark this quote as rejected?')"
                  style="display:inline;">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn--outline" style="color: var(--color-danger); border-color: var(--color-danger);">
                     Mark Rejected
                </button>
            </form>
        @endif

        {{-- Convert to Sale --}}
        @if($quote->canBeConverted())
            <form method="POST" action="{{ route('quotes.convert', $quote) }}"
                  onsubmit="return confirm('Convert this quote to a sale? This cannot be undone.')"
                  style="display:inline;">
                @csrf
                <button type="submit" class="btn btn--primary">
                     Convert to Sale
                </button>
            </form>
        @endif

        {{-- Delete (draft only) --}}
        @if($quote->status === 'draft')
            <form method="POST" action="{{ route('quotes.destroy', $quote) }}"
                  onsubmit="return confirm('Delete this quote? This cannot be undone.')"
                  style="display:inline;">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">
                     Delete
                </button>
            </form>
        @endif

        <a href="{{ route('quotes.index') }}" class="btn btn--outline">
             Back
        </a>
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

{{-- Converted notice --}}
@if($quote->status === 'converted' && $quote->convertedSale)
    <div class="converted-notice">
        
        <span>
            This quote was converted to sale
            <a href="{{ route('sales.show', $quote->convertedSale) }}"
               style="font-weight: 700; text-decoration: underline;">
                {{ $quote->convertedSale->invoice_number }}
            </a>.
        </span>
    </div>
@endif

{{-- Quote Info Cards --}}
<div class="quote-info-grid">
    <div class="info-card">
        <h4> Customer</h4>
        @if($quote->customer)
            <p><strong>{{ $quote->customer->name }}</strong></p>
            <p class="text-muted">{{ $quote->customer->phone ?? '—' }}</p>
            <p class="text-muted">{{ $quote->customer->email ?? '—' }}</p>
        @else
            <p class="text-muted">No customer specified</p>
        @endif
    </div>
    <div class="info-card">
        <h4> Dates</h4>
        <p>Quote Date: <strong>{{ $quote->quote_date->format('d M Y') }}</strong></p>
        @if($quote->valid_until)
            <p>
                Valid Until:
                <strong style="color: {{ $quote->valid_until->isPast() && !in_array($quote->status, ['converted','accepted']) ? 'var(--color-danger)' : 'inherit' }};">
                    {{ $quote->valid_until->format('d M Y') }}
                </strong>
                @if($quote->valid_until->isPast() && !in_array($quote->status, ['converted','accepted']))
                    <span style="color: var(--color-danger); font-size: var(--text-xs);">(Expired)</span>
                @endif
            </p>
        @else
            <p class="text-muted">No expiry date set</p>
        @endif
    </div>
    <div class="info-card">
        <h4> Quote Info</h4>
        <p>Quote #: <strong>{{ $quote->quote_number }}</strong></p>
        <p>Created by: <strong>{{ $quote->user->name }}</strong></p>
        <p>{{ $quote->created_at->format('d M Y, g:i A') }}</p>
    </div>
</div>

{{-- Items Table --}}
<div class="report-section" style="margin-bottom: var(--space-5);">
    <div class="report-section-header">
        <h2> Line Items</h2>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($quote->items as $i => $item)
                <tr>
                    <td data-label="#">{{ $i + 1 }}</td>
                    <td data-label="Product">
                        <strong>{{ $item->product_name }}</strong>
                    </td>
                    <td data-label="Description" class="text-muted">
                        {{ $item->description ?? '—' }}
                    </td>
                    <td data-label="Qty">{{ $item->quantity }}</td>
                    <td data-label="Unit Price">KSh {{ number_format($item->unit_price, 2) }}</td>
                    <td data-label="Subtotal"><strong>KSh {{ number_format($item->subtotal, 2) }}</strong></td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background: var(--color-surface-2);">
                    <td colspan="5" style="text-align: right; font-weight: 700; padding: 12px 16px;">Subtotal</td>
                    <td style="font-weight: 700; padding: 12px 16px;">KSh {{ number_format($quote->subtotal, 2) }}</td>
                </tr>
                @if($quote->discount_amount > 0)
                <tr>
                    <td colspan="5" style="text-align: right; padding: 8px 16px; color: var(--color-danger);">Discount</td>
                    <td style="color: var(--color-danger); padding: 8px 16px;">- KSh {{ number_format($quote->discount_amount, 2) }}</td>
                </tr>
                @endif
                @if($quote->tax_amount > 0)
                <tr>
                    <td colspan="5" style="text-align: right; padding: 8px 16px; color: var(--color-text-muted);">
                        Tax ({{ number_format($quote->tax_rate, 1) }}%)
                    </td>
                    <td style="padding: 8px 16px; color: var(--color-text-muted);">+ KSh {{ number_format($quote->tax_amount, 2) }}</td>
                </tr>
                @endif
                <tr style="background: var(--color-success-light);">
                    <td colspan="5" style="text-align: right; font-weight: 700; font-size: 1.1rem; padding: 12px 16px;">TOTAL</td>
                    <td style="font-weight: 700; font-size: 1.1rem; color: var(--color-primary); padding: 12px 16px;">KSh {{ number_format($quote->total, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- Notes & Terms --}}
@if($quote->notes || $quote->terms)
<div class="quote-notes-terms-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4); margin-bottom: var(--space-5);">
    @if($quote->notes)
    <div class="info-card">
        <h4> Notes</h4>
        <p style="white-space: pre-line;">{{ $quote->notes }}</p>
    </div>
    @endif
    @if($quote->terms)
    <div class="info-card">
        <h4> Terms & Conditions</h4>
        <p style="white-space: pre-line;">{{ $quote->terms }}</p>
    </div>
    @endif
</div>
@endif

<div class="info-card" style="margin-top: var(--space-5);">
    @include('partials.attachments', ['modelType' => 'Quote', 'modelId' => $quote->id])
</div>

@endsection

@push('scripts')
<script>
    document.querySelectorAll('.alert').forEach(function(alert) {
        setTimeout(function() {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s';
            setTimeout(function() { alert.remove(); }, 500);
        }, 4000);
    });
</script>
@endpush
