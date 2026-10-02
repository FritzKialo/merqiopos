@extends('layouts.app')
@section('title', $product->name)

@push('styles')
    <link rel="stylesheet" 
          href="{{ asset('css/inventory.css') }}?v={{ @filemtime(public_path('css/inventory.css')) ?: '1' }}">
@endpush

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">{{ $product->name }}</h1>
        <p class="page-subtitle">
            SKU: {{ $product->sku }} 
            &nbsp;|&nbsp;
            {{ $product->category->name ?? 'No Category' }}
        </p>
    </div>
    <div style="display:flex; gap:8px;">
        @role('owner','overall_manager','manager')
        <a href="{{ route('inventory.edit', $product) }}"
           class="btn btn-primary">
            &#9998; Edit
        </a>
        @endrole
        <a href="{{ route('inventory.index') }}"
           class="btn btn-outline">
            &#8592; Back
        </a>
    </div>
</div>

<div class="detail-grid">

    {{-- Pricing Details --}}
    <div class="detail-section">
        <h3>&#128176; Pricing</h3>
        @role('owner','overall_manager','manager')
        <div class="detail-row">
            <span class="detail-key">Buying Price</span>
            <span class="detail-value">
                KSh {{ number_format($product->buying_price, 2) }}
            </span>
        </div>
        @endrole
        <div class="detail-row">
            <span class="detail-key">Selling Price</span>
            <span class="detail-value">
                KSh {{ number_format($product->selling_price, 2) }}
            </span>
        </div>
        @role('owner','overall_manager','manager')
        <div class="detail-row">
            <span class="detail-key">Profit / Unit</span>
            <span class="detail-value" style="color: var(--success);">
                KSh {{ number_format($product->profitPerUnit(), 2) }}
            </span>
        </div>
        <div class="detail-row">
            <span class="detail-key">Profit Margin</span>
            <span class="detail-value">{{ $product->profitMargin() }}%</span>
        </div>
        @endrole

    </div>

    {{-- Stock Details --}}
    <div class="detail-section">
        <h3>&#128230; Stock</h3>
        <div class="detail-row">
            <span class="detail-key">Current Stock</span>
            <span class="detail-value">
                {{ $product->stock_qty }} 
                {{ $product->unit }}
            </span>
        </div>
        <div class="detail-row">
            <span class="detail-key">Reorder Level</span>
            <span class="detail-value">
                {{ $product->reorder_level }} 
                {{ $product->unit }}
            </span>
        </div>
        @role('owner','overall_manager','manager')
        <div class="detail-row">
            <span class="detail-key">Stock Value</span>
            <span class="detail-value">
                KSh {{ number_format($product->stockValue(), 2) }}
            </span>
        </div>
        @endrole
        <div class="detail-row">
            <span class="detail-key">Stock Status</span>
            <span class="detail-value">
                @if($product->isOutOfStock())
                    <span class="badge badge-danger">
                        Out of Stock
                    </span>
                @elseif($product->isLowStock())
                    <span class="badge badge-warning">
                        Low Stock
                    </span>
                @else
                    <span class="badge badge-success">
                        In Stock
                    </span>
                @endif
            </span>
        </div>
    </div>

    {{-- General Details --}}
    <div class="detail-section">
        <h3>&#128203; Details</h3>
        <div class="detail-row">
            <span class="detail-key">Category</span>
            <span class="detail-value">
                {{ $product->category->name ?? '—' }}
            </span>
        </div>
        <div class="detail-row">
            <span class="detail-key">Unit</span>
            <span class="detail-value">
                {{ ucfirst($product->unit) }}
            </span>
        </div>
        <div class="detail-row">
            <span class="detail-key">Status</span>
            <span class="detail-value">
                <span class="badge 
                    {{ $product->status == 'active' 
                        ? 'badge-success' 
                        : 'badge-neutral' }}">
                    {{ ucfirst($product->status) }}
                </span>
            </span>
        </div>
        <div class="detail-row">
            <span class="detail-key">Added On</span>
            <span class="detail-value">
                {{ $product->created_at
                    ->format('d M Y') }}
            </span>
        </div>
    </div>

    {{-- Description --}}
    @if($product->description)
    <div class="detail-section">
        <h3>&#128221; Description</h3>
        <p style="
            font-size:  var(--text-sm);
            color:      var(--text-secondary);
            line-height:1.6;">
            {{ $product->description }}
        </p>
    </div>
    @endif

</div>

@endsection
