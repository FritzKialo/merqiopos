@extends('layouts.app')
@section('title', 'Batches — ' . $product->name)

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Expiry Batches</h1>
        <p class="page-subtitle">{{ $product->name }}</p>
    </div>
    <a href="{{ route('inventory.edit', $product) }}" class="btn btn-outline">&#8592; Back to Product</a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

{{-- Add Batch Form — managers/owners only --}}
@role('owner','overall_manager','manager')
<div class="form-card" style="max-width:860px; margin-bottom:var(--space-lg);">
    <h3 style="margin:0 0 var(--space-md); font-size:var(--text-base);">Add Batch</h3>
    <form method="POST" action="{{ route('inventory.batches.store', $product) }}">
        @csrf
        <div class="form-grid-2">
            <div class="form-group">
                <label class="form-label">Batch Number *</label>
                <input type="text" name="batch_number" class="form-control" required placeholder="e.g. LOT-2024-001">
            </div>
            <div class="form-group">
                <label class="form-label">Expiry Date</label>
                <input type="date" name="expiry_date" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Quantity *</label>
                <input type="number" name="quantity" class="form-control" min="0" step="0.01" required>
            </div>
            <div class="form-group">
                <label class="form-label">Cost Price (KSh)</label>
                <input type="number" name="cost_price" class="form-control" min="0" step="0.01">
            </div>
            <div class="form-group">
                <label class="form-label">Received Date *</label>
                <input type="date" name="received_date" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
            </div>
            <div class="form-group">
                <label class="form-label">Supplier</label>
                <select name="supplier_id" class="form-control">
                    <option value="">— None —</option>
                    @foreach(\App\Models\Supplier::where('business_id', auth()->user()->currentBusiness()->id)->orderBy('name')->get() as $s)
                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="2"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">+ Add Batch</button>
    </form>
</div>
@endrole

{{-- Batches Table --}}
@if($batches->isNotEmpty())
<div class="table-card" style="max-width:860px;">
    <div class="table-wrapper">
    <table class="table">
        <thead>
            <tr>
                <th>Batch #</th>
                <th>Expiry Date</th>
                <th>Qty</th>
                @role('owner','overall_manager','manager')<th>Cost</th>@endrole
                <th>Received</th>
                <th>Supplier</th>
                @role('owner','overall_manager','manager')<th>Actions</th>@endrole
            </tr>
        </thead>
        <tbody>
            @foreach($batches as $b)
            @php
                $expired     = $b->isExpired();
                $days        = $b->daysUntilExpiry();
                $expiringSoon = !$expired && $days !== null && $days <= $product->expiry_alert_days;
            @endphp
            <tr style="{{ $expired ? 'background:#fff0f0;' : ($expiringSoon ? 'background:#fffbeb;' : '') }}">
                <td data-label="Batch #">{{ $b->batch_number }}</td>
                <td data-label="Expiry Date">
                    @if($b->expiry_date)
                        {{ $b->expiry_date->format('d M Y') }}
                        @if($expired)
                            <span class="badge badge-danger">Expired</span>
                        @elseif($expiringSoon)
                            <span class="badge badge-warning">{{ $days }}d left</span>
                        @endif
                    @else
                        <span style="color:var(--color-text-muted);">No expiry</span>
                    @endif
                </td>
                <td data-label="Qty">{{ number_format($b->quantity, 2) }}</td>
                @role('owner','overall_manager','manager')
                <td data-label="Cost">{{ $b->cost_price ? 'KSh ' . number_format($b->cost_price, 2) : '—' }}</td>
                @endrole
                <td data-label="Received">{{ $b->received_date->format('d M Y') }}</td>
                <td data-label="Supplier">{{ $b->supplier?->name ?? '—' }}</td>
                @role('owner','overall_manager','manager')
                <td data-label="Actions">
                    <form method="POST" action="{{ route('inventory.batches.destroy', [$product, $b]) }}"
                        onsubmit="return confirm('Remove this batch and decrement stock by {{ $b->quantity }}?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger" style="font-size:0.75rem; padding:4px 8px;">Remove</button>
                    </form>
                </td>
                @endrole
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>
</div>
@else
    <p style="color:var(--color-text-muted);">No batches recorded yet.</p>
@endif

@endsection
