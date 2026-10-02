@extends('layouts.app')
@section('title', 'Serial Numbers — ' . $product->name)

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Serial Numbers</h1>
        <p class="page-subtitle">{{ $product->name }}</p>
    </div>
    <a href="{{ route('inventory.edit', $product) }}" class="btn btn-outline">&#8592; Back to Product</a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

{{-- Bulk Add Form --}}
<div class="form-card" style="max-width:860px; margin-bottom:var(--space-lg);">
    <h3 style="margin:0 0 var(--space-md); font-size:var(--text-base);">Bulk Add Serial Numbers</h3>
    <form method="POST" action="{{ route('inventory.serials.store', $product) }}">
        @csrf
        <div class="form-grid-2">
            <div class="form-group" style="grid-column:1/-1;">
                <label class="form-label">Serial Numbers (one per line)</label>
                <textarea name="serials" class="form-control" rows="5"
                    placeholder="SN12345&#10;SN12346&#10;SN12347"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Received Date</label>
                <input type="date" name="received_date" class="form-control" value="{{ now()->format('Y-m-d') }}">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">+ Add Serials</button>
    </form>
</div>

{{-- Status Filter Tabs --}}
<div style="display:flex; gap:8px; margin-bottom:var(--space-md);">
    @foreach(['all' => 'All', 'in_stock' => 'In Stock', 'sold' => 'Sold', 'returned' => 'Returned', 'scrapped' => 'Scrapped'] as $val => $label)
        <a href="{{ route('inventory.serials.index', [$product, 'status' => $val === 'all' ? null : $val]) }}"
            class="btn {{ $status === $val || ($status === 'all' && $val === 'all') ? 'btn-primary' : 'btn-outline' }}"
            style="font-size:0.8rem; padding:5px 12px;">
            {{ $label }}
        </a>
    @endforeach
</div>

{{-- Serials Table --}}
@if($serials->isNotEmpty())
<div class="table-card" style="max-width:860px;">
    <div class="table-wrapper">
    <table class="table">
        <thead>
            <tr>
                <th>Serial Number</th>
                <th>Status</th>
                <th>Received</th>
                <th>Sold Date</th>
                <th>Sale</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($serials as $sn)
            <tr>
                <td data-label="Serial Number"><code>{{ $sn->serial_number }}</code></td>
                <td data-label="Status">
                    @php
                        $badgeClass = match($sn->status) {
                            'in_stock' => 'badge-success',
                            'sold'     => 'badge-secondary',
                            'returned' => 'badge-warning',
                            'scrapped' => 'badge-danger',
                            default    => 'badge-secondary',
                        };
                    @endphp
                    <span class="badge {{ $badgeClass }}">{{ ucfirst(str_replace('_',' ',$sn->status)) }}</span>
                </td>
                <td data-label="Received">{{ $sn->received_date?->format('d M Y') ?? '—' }}</td>
                <td data-label="Sold Date">{{ $sn->sold_date?->format('d M Y') ?? '—' }}</td>
                <td data-label="Sale">
                    @if($sn->sale)
                        <a href="{{ route('sales.show', $sn->sale) }}">{{ $sn->sale->invoice_number }}</a>
                    @else
                        —
                    @endif
                </td>
                <td data-label="Actions">
                    <button type="button" class="btn btn-secondary" style="font-size:0.75rem; padding:4px 8px;"
                        onclick="toggleSnEdit({{ $sn->id }})">Edit</button>
                    <div id="sn-edit-{{ $sn->id }}" style="display:none; margin-top:8px;">
                        <form method="POST" action="{{ route('inventory.serials.update', [$product, $sn]) }}">
                            @csrf @method('PATCH')
                            <select name="status" class="form-control" style="margin-bottom:6px;">
                                @foreach(['in_stock','sold','returned','scrapped'] as $st)
                                    <option value="{{ $st }}" {{ $sn->status === $st ? 'selected' : '' }}>{{ ucfirst(str_replace('_',' ',$st)) }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="notes" class="form-control"
                                style="margin-bottom:6px;" placeholder="Notes" value="{{ $sn->notes }}">
                            <button type="submit" class="btn btn-primary" style="font-size:0.75rem; padding:4px 8px;">Save</button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>
    <div style="padding:var(--space-sm);">
        {{ $serials->links() }}
    </div>
</div>
@else
    <p style="color:var(--color-text-muted);">No serial numbers found.</p>
@endif

@endsection

@push('scripts')
<script>
function toggleSnEdit(id) {
    var el = document.getElementById('sn-edit-' + id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>
@endpush
