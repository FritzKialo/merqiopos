@extends('layouts.app')
@section('title', 'Product Variants — ' . $product->name)

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Product Variants</h1>
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

{{-- Add Variant Form --}}
<div class="form-card" style="max-width:860px; margin-bottom:var(--space-lg);">
    <h3 style="margin:0 0 var(--space-md); font-size:var(--text-base);">Add Variant</h3>
    <form method="POST" action="{{ route('inventory.variants.store', $product) }}">
        @csrf
        <div class="form-grid-2">
            <div class="form-group">
                <label class="form-label">Name *</label>
                <input type="text" name="name" class="form-control" placeholder="e.g. Red / Large" required>
            </div>
            <div class="form-group">
                <label class="form-label">SKU</label>
                <input type="text" name="sku" class="form-control" placeholder="Optional">
            </div>
            <div class="form-group">
                <label class="form-label">Price Override (KSh)</label>
                <input type="number" name="price" class="form-control" step="0.01" min="0" placeholder="Leave blank to inherit">
            </div>
            <div class="form-group">
                <label class="form-label">Cost Price (KSh)</label>
                <input type="number" name="cost_price" class="form-control" step="0.01" min="0">
            </div>
            <div class="form-group">
                <label class="form-label">Stock Qty *</label>
                <input type="number" name="stock_qty" class="form-control" value="0" min="0" step="0.01" required>
            </div>
            <div class="form-group">
                <label class="form-label">Reorder Level</label>
                <input type="number" name="reorder_level" class="form-control" value="0" min="0" step="0.01">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">+ Add Variant</button>
    </form>
</div>

{{-- Variants Table --}}
@if($variants->isNotEmpty())
<div class="table-card" style="max-width:860px;">
    <div class="table-wrapper">
    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>SKU</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Active</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($variants as $variant)
            <tr>
                <td data-label="Name">{{ $variant->name }}</td>
                <td data-label="SKU">{{ $variant->sku ?? '—' }}</td>
                <td data-label="Price">
                    @if($variant->price !== null)
                        KSh {{ number_format($variant->price, 2) }}
                    @else
                        <span style="color:var(--color-text-muted);">Inherit ({{ number_format($product->selling_price, 2) }})</span>
                    @endif
                </td>
                <td data-label="Stock">{{ number_format($variant->stock_qty, 2) }}</td>
                <td data-label="Active">
                    <span class="badge {{ $variant->is_active ? 'badge-success' : 'badge-secondary' }}">
                        {{ $variant->is_active ? 'Yes' : 'No' }}
                    </span>
                </td>
                <td data-label="Actions">
                    <div style="display:flex; gap:6px; align-items:center;">
                        <button type="button" class="btn btn-secondary" style="font-size:0.75rem; padding:4px 8px;"
                            onclick="toggleEditForm({{ $variant->id }})">Edit</button>
                        <form method="POST" action="{{ route('inventory.variants.destroy', [$product, $variant]) }}"
                            onsubmit="return confirm('Delete this variant?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger" style="font-size:0.75rem; padding:4px 8px;">Delete</button>
                        </form>
                    </div>
                    {{-- Inline edit form --}}
                    <div id="edit-form-{{ $variant->id }}" style="display:none; margin-top:8px; padding:12px; background:var(--color-surface); border:1px solid var(--color-border); border-radius:6px;">
                        <form method="POST" action="{{ route('inventory.variants.update', [$product, $variant]) }}">
                            @csrf @method('PATCH')
                            <div class="form-grid-2" style="gap:8px;">
                                <div class="form-group" style="margin-bottom:8px;">
                                    <label class="form-label" style="font-size:0.75rem;">Name</label>
                                    <input type="text" name="name" class="form-control" value="{{ $variant->name }}" required>
                                </div>
                                <div class="form-group" style="margin-bottom:8px;">
                                    <label class="form-label" style="font-size:0.75rem;">SKU</label>
                                    <input type="text" name="sku" class="form-control" value="{{ $variant->sku }}">
                                </div>
                                <div class="form-group" style="margin-bottom:8px;">
                                    <label class="form-label" style="font-size:0.75rem;">Price Override</label>
                                    <input type="number" name="price" class="form-control" value="{{ $variant->price }}" step="0.01" min="0">
                                </div>
                                <div class="form-group" style="margin-bottom:8px;">
                                    <label class="form-label" style="font-size:0.75rem;">Stock Qty</label>
                                    <input type="number" name="stock_qty" class="form-control" value="{{ $variant->stock_qty }}" step="0.01" min="0" required>
                                </div>
                                <div class="form-group" style="margin-bottom:8px;">
                                    <label class="form-label" style="font-size:0.75rem;">Active</label>
                                    <select name="is_active" class="form-control">
                                        <option value="1" {{ $variant->is_active ? 'selected' : '' }}>Yes</option>
                                        <option value="0" {{ !$variant->is_active ? 'selected' : '' }}>No</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary" style="font-size:0.75rem; padding:4px 10px;">Save</button>
                            <button type="button" class="btn btn-outline" style="font-size:0.75rem; padding:4px 10px;"
                                onclick="toggleEditForm({{ $variant->id }})">Cancel</button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>
</div>
@else
<p style="color:var(--color-text-muted);">No variants yet. Add the first one above.</p>
@endif

@endsection

@push('scripts')
<script>
function toggleEditForm(id) {
    var el = document.getElementById('edit-form-' + id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>
@endpush
