@extends('layouts.app')
@section('title', 'Process Return')
@push('styles')
<style>
@media (max-width: 700px) {
    .return-refund-grid { grid-template-columns: 1fr 1fr !important; }
}
@media (max-width: 480px) {
    .return-refund-grid { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Process Return</h1>
            <p class="page-subtitle">Sale {{ $sale->invoice_number }} &mdash; {{ $sale->customer?->name ?? 'Walk-in' }}</p>
        </div>
        <a href="{{ route('returns.index') }}" class="btn btn--outline">Back</a>
    </div>

    @if($errors->any())
        <div class="alert alert--error">
            @foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('returns.store') }}">
        @csrf
        <input type="hidden" name="sale_id" value="{{ $sale->id }}">

        <div class="table-card" style="margin-bottom:1.5rem;">
            <div class="card-body">
                <h3 style="margin-bottom:1rem;">Select Items to Return</h3>
            </div>
            <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Return?</th>
                        <th>Product</th>
                        <th>Ordered</th>
                        <th>Already Returned</th>
                        <th>Max Returnable</th>
                        <th>Unit Price</th>
                        <th>Qty to Return</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sale->items as $item)
                    @php
                        $alreadyReturned = $returnedQtys[$item->id] ?? 0;
                        $maxReturnable   = $item->quantity - $alreadyReturned;
                    @endphp
                    @if($maxReturnable > 0)
                    <tr>
                        <td data-label="Return?">
                            <input type="checkbox" name="items[{{ $loop->index }}][selected]" value="1"
                                   onchange="toggleRow(this)">
                            <input type="hidden" name="items[{{ $loop->index }}][sale_item_id]" value="{{ $item->id }}">
                        </td>
                        <td data-label="Product">{{ $item->product_name }}</td>
                        <td data-label="Ordered">{{ $item->quantity }}</td>
                        <td data-label="Already Returned">{{ $alreadyReturned }}</td>
                        <td data-label="Max Returnable">{{ $maxReturnable }}</td>
                        <td data-label="Unit Price">KSh {{ number_format($item->unit_price, 0) }}</td>
                        <td data-label="Qty to Return">
                            <input type="number" name="items[{{ $loop->index }}][quantity_returned]"
                                   min="1" max="{{ $maxReturnable }}" value="1"
                                   class="form-control" style="width:80px;" disabled>
                        </td>
                    </tr>
                    @endif
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>

        <div class="table-card">
            <div class="card-body">
                <div class="return-refund-grid" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1.5rem;">
                    <div class="form-group">
                        <label class="form-label">Refund Method *</label>
                        <select name="refund_method" class="form-control" required>
                            <option value="cash">Cash</option>
                            <option value="mpesa">M-Pesa</option>
                            <option value="store_credit">Store Credit</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Stock Action *</label>
                        <select name="stock_action" class="form-control" required>
                            <option value="restock">Restock (add back to inventory)</option>
                            <option value="writeoff">Write-off (damaged/unusable)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reason *</label>
                        <input type="text" name="reason" class="form-control" required
                               placeholder="e.g. Defective item, wrong size" value="{{ old('reason') }}">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                </div>
                <div style="margin-top:1rem;">
                    <button type="submit" class="btn btn--primary">Process Return</button>
                    <a href="{{ route('returns.index') }}" class="btn btn--outline">Cancel</a>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function toggleRow(checkbox) {
    const row = checkbox.closest('tr');
    const input = row.querySelector('input[type="number"]');
    input.disabled = !checkbox.checked;
}
</script>
@endsection
