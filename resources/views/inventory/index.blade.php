@extends('layouts.app')
@section('title', 'Inventory')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/inventory.css') }}?v={{ @filemtime(public_path('css/inventory.css')) ?: '1' }}">
@endpush

@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Inventory</h1>
            <p class="page-subtitle">Track products, stock levels, and warehouse value.</p>
        </div>
        @role('owner','overall_manager','manager')
        <div class="action-buttons">
            <a href="{{ route('inventory.categories') }}" class="btn btn--outline">Categories</a>
            <a href="{{ route('inventory.import') }}" class="btn btn--outline">Import CSV</a>
            @if(auth()->user()->currentBusiness()?->hasFeature('data_export'))
            <a href="{{ route('inventory.export') }}" class="btn btn--outline">Export CSV</a>
            @endif
            <a href="{{ route('inventory.create') }}" class="btn btn--primary">Add Product</a>
        </div>
        @endrole
    </div>

    {{-- Import Results --}}
    @if(session('import_results'))
        @php $ir = session('import_results'); @endphp
        <div class="import-results-banner">
            <div class="import-results-summary">
                <span class="import-stat import-stat--success">{{ $ir['imported'] }} imported</span>
                @if($ir['skipped'] > 0)
                <span class="import-stat import-stat--warn">{{ $ir['skipped'] }} skipped</span>
                @endif
            </div>
            @if(!empty($ir['errors']))
                <details class="import-errors-toggle">
                    <summary>View {{ count($ir['errors']) }} {{ Str::plural('issue', count($ir['errors'])) }}</summary>
                    <ul class="import-errors-list">
                        @foreach($ir['errors'] as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>
    @endif

    {{-- KPI Strip --}}
    <div class="kpi-strip">
        <div class="kpi-item">
            <span class="kpi-value">{{ $stats['total'] }}</span>
            <span class="kpi-label">Total Products</span>
        </div>
        <div class="kpi-item">
            <span class="kpi-value">{{ $stats['active'] }}</span>
            <span class="kpi-label">Active Items</span>
        </div>
        <div class="kpi-item">
            <span class="kpi-value" style="{{ $stats['low_stock'] > 0 ? 'color: var(--color-danger);' : '' }}">{{ $stats['low_stock'] }}</span>
            <span class="kpi-label">Low Stock</span>
        </div>
        @role('owner','overall_manager','manager')
        <div class="kpi-item">
            <span class="kpi-value">KSh {{ number_format($stats['value'], 2) }}</span>
            <span class="kpi-label">Valuation</span>
        </div>
        @endrole
    </div>

    {{-- Toolbar --}}
    <form method="GET" action="{{ route('inventory.index') }}" id="filterForm">
        <div class="toolbar">
            <div class="toolbar-search">
                <input type="text" name="search" placeholder="Search products or SKU..." value="{{ request('search') }}">
            </div>

            <select name="category_id" class="toolbar-select" onchange="this.form.submit()">
                <option value="">All Categories</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
            </select>

            <select name="status" class="toolbar-select" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>

            <div style="display: flex; align-items: center; gap: 12px; margin-left: auto;">
                <label style="display: flex; align-items: center; gap: 6px; font-size: 0.875rem; color: var(--color-text-muted); cursor: pointer;">
                    <input type="checkbox" name="low_stock" value="1" {{ request('low_stock') ? 'checked' : '' }} onchange="this.form.submit()">
                    Low stock only
                </label>
                @if(request()->hasAny(['search','category_id','status','low_stock']))
                    <a href="{{ route('inventory.index') }}" class="btn btn--outline btn--sm">Clear</a>
                @endif
            </div>
        </div>
    </form>

    <form method="POST" action="{{ route('inventory.bulk-action') }}" id="bulkForm">
        @csrf

        @role('owner','overall_manager','manager')
        <div class="bulk-bar" id="bulkBar">
            <span class="bulk-bar-count"><span id="bulkCount">0</span> selected</span>
            <div class="bulk-bar-actions">
                <button type="submit" name="bulk_action" value="activate" class="btn btn--outline btn--sm">Activate</button>
                <button type="submit" name="bulk_action" value="deactivate" class="btn btn--outline btn--sm">Deactivate</button>
                <button type="submit" name="bulk_action" value="delete" class="btn btn--danger btn--sm"
                        onclick="return confirm('Delete selected products? This cannot be undone.')">Delete</button>
            </div>
        </div>
        @endrole

        <div class="table-section">
            @if($products->isEmpty())
                <div class="empty-state">
                    <h3>No products found</h3>
                    <p>Try adjusting your search or filters.</p>
                    @role('owner','overall_manager','manager')
                    <a href="{{ route('inventory.create') }}" class="btn btn--primary" style="margin-top: 1rem;">Add Product</a>
                    @endrole
                </div>
            @else
                <div class="table-wrapper">
                    <table class="table-responsive-cards">
                        <thead>
                            <tr>
                                @role('owner','overall_manager','manager')
                                <th style="width:36px;"><input type="checkbox" id="selectAll" title="Select all"></th>
                                @endrole
                                <th>Product</th>
                                <th>Category</th>
                                @role('owner','overall_manager','manager')<th>Buying</th>@endrole
                                <th>Selling</th>
                                @role('owner','overall_manager','manager')<th>Margin</th>@endrole
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($products as $product)
                            <tr class="{{ $product->isOutOfStock() ? 'out-of-stock' : ($product->isLowStock() ? 'low-stock' : '') }}">
                                @role('owner','overall_manager','manager')
                                <td data-label=""><input type="checkbox" name="product_ids[]" value="{{ $product->id }}" class="row-check"></td>
                                @endrole
                                <td data-label="Product">
                                    <span class="product-name">{{ $product->name }}</span>
                                    <div class="product-sku">{{ $product->sku }}</div>
                                </td>
                                <td data-label="Category">{{ $product->category->name ?? '—' }}</td>
                                @role('owner','overall_manager','manager')
                                <td data-label="Buying">KSh {{ number_format($product->buying_price, 0) }}</td>
                                @endrole
                                <td data-label="Selling">KSh {{ number_format($product->selling_price, 0) }}</td>
                                @role('owner','overall_manager','manager')
                                <td data-label="Margin">
                                    <span class="badge {{ $product->profitMargin() >= 20 ? 'badge-success' : 'badge-warning' }}">
                                        {{ $product->profitMargin() }}%
                                    </span>
                                </td>
                                @endrole
                                <td data-label="Stock">
                                    @if($product->isOutOfStock())
                                        <span class="badge badge-danger">Out of Stock</span>
                                    @elseif($product->isLowStock())
                                        <span class="badge badge-warning">{{ $product->stock_qty }} {{ $product->unit }}</span>
                                    @else
                                        <span class="badge badge-neutral">{{ $product->stock_qty }} {{ $product->unit }}</span>
                                    @endif
                                </td>
                                <td data-label="Status">
                                    <span class="badge {{ $product->status === 'active' ? 'badge-success' : 'badge-neutral' }}">
                                        {{ ucfirst($product->status) }}
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    <div class="action-buttons">
                                        <a href="{{ route('inventory.show', $product) }}" class="btn btn--outline btn--sm">View</a>
                                        @role('owner','overall_manager','manager')
                                        <a href="{{ route('inventory.edit', $product) }}" class="btn btn--primary btn--sm">Edit</a>
                                        <button class="btn btn--danger btn--sm" onclick="confirmDelete({{ $product->id }}, '{{ addslashes($product->name) }}')">Delete</button>
                                        @endrole
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $products->links('vendor.pagination.custom') }}
            @endif
        </div>

    </form>

{{-- Delete forms (must be outside bulkForm — nested forms are invalid HTML) --}}
@foreach($products as $product)
@role('owner','overall_manager','manager')
<form id="delete-{{ $product->id }}" method="POST" action="{{ route('inventory.destroy', $product) }}" style="display:none;">
    @csrf @method('DELETE')
</form>
@endrole
@endforeach

{{-- Delete Modal --}}
<div class="modal-overlay" id="deleteModal">
    <div class="modal card">
        <div class="modal-header">
            <h3>Confirm Delete</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <p>Are you sure you want to delete <strong id="deleteProductName"></strong>? This action is permanent.</p>
        <div class="modal-footer">
            <button class="btn btn--outline" onclick="closeModal()">Cancel</button>
            <button class="btn btn--danger" id="confirmDeleteBtn">Delete</button>
        </div>
    </div>
</div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/inventory.js') }}?v={{ @filemtime(public_path('js/inventory.js')) ?: '1' }}"></script>
@endpush

