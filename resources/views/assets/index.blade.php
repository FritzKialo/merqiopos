@extends('layouts.app')
@section('title', 'Asset Management')
@push('styles')
<style>
@media (max-width: 640px) {
    .asset-summary-grid { grid-template-columns: 1fr !important; }
}
@media (min-width: 769px) {
    .assets-table th, .assets-table td { padding: 0.75rem 1rem; }
}
</style>
@endpush
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Assets</h1>
            <p class="page-subtitle">Business asset register</p>
        </div>
        <a href="{{ route('assets.create') }}" class="btn btn-primary">+ Add Asset</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success" style="margin-bottom:1rem;">{{ session('success') }}</div>
    @endif

    {{-- Summary Cards --}}
    <div class="asset-summary-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem;">
        <div class="table-card" style="padding:1.25rem;text-align:center;">
            <div style="font-size:0.75rem;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.4rem;">Total Asset Value</div>
            <div style="font-size:1.4rem;font-weight:700;">KSh {{ number_format($totalPortfolioValue, 2) }}</div>
        </div>
        <div class="table-card" style="padding:1.25rem;text-align:center;">
            <div style="font-size:0.75rem;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.4rem;">Total Depreciation (All Time)</div>
            <div style="font-size:1.4rem;font-weight:700;color:var(--color-warning);">KSh {{ number_format($totalDepreciation, 2) }}</div>
        </div>
        <div class="table-card" style="padding:1.25rem;text-align:center;">
            <div style="font-size:0.75rem;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.4rem;">Annual Depreciation</div>
            <div style="font-size:1.4rem;font-weight:700;color:var(--color-warning);">KSh {{ number_format($annualDepreciation, 2) }}/yr</div>
        </div>
    </div>

    <div class="table-card">
        @if($assets->isEmpty())
            <div style="padding:3rem;text-align:center;color:var(--color-text-muted);">No assets recorded yet. <a href="{{ route('assets.create') }}">Add your first asset</a>.</div>
        @else
        <table class="assets-table" style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="background:var(--color-surface);border-bottom:2px solid var(--color-border);">
                    <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Name</th>
                    <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Category</th>
                    <th style="text-align:center;font-size:0.8rem;text-transform:uppercase;">Purchase Date</th>
                    <th style="text-align:right;font-size:0.8rem;text-transform:uppercase;">Cost</th>
                    <th style="text-align:right;font-size:0.8rem;text-transform:uppercase;">Current Value</th>
                    <th style="text-align:right;font-size:0.8rem;text-transform:uppercase;">Depr./Year</th>
                    <th style="text-align:center;font-size:0.8rem;text-transform:uppercase;">Status</th>
                    <th style="text-align:center;font-size:0.8rem;text-transform:uppercase;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($assets as $asset)
                <tr style="border-bottom:1px solid var(--color-border);">
                    <td data-label="Name" style="font-weight:500;">{{ $asset->name }}</td>
                    <td data-label="Category" style="color:var(--color-text-muted);">{{ $asset->category }}</td>
                    <td data-label="Purchase Date" style="text-align:center;">{{ $asset->purchase_date->format('d M Y') }}</td>
                    <td data-label="Cost" style="text-align:right;">KSh {{ number_format($asset->purchase_cost, 2) }}</td>
                    <td data-label="Current Value" style="text-align:right;font-weight:600;">KSh {{ number_format($asset->current_value, 2) }}</td>
                    <td data-label="Depr./Year" style="text-align:right;color:var(--color-text-muted);">
                        KSh {{ number_format($asset->annualDepreciation(), 2) }}
                    </td>
                    <td data-label="Status" style="text-align:center;">
                        @if($asset->status === 'active')
                            <span class="badge badge-success">Active</span>
                        @elseif($asset->status === 'disposed')
                            <span class="badge badge-secondary">Disposed</span>
                        @else
                            <span class="badge badge-danger">Written Off</span>
                        @endif
                    </td>
                    <td data-label="Actions" style="text-align:center;">
                        <div style="display:flex;gap:0.4rem;justify-content:center;">
                            <a href="{{ route('assets.edit', $asset) }}" class="btn btn-secondary" style="padding:0.25rem 0.5rem;font-size:0.75rem;">Edit</a>
                            @if($asset->status === 'active')
                                <button onclick="openDispose({{ $asset->id }}, '{{ addslashes($asset->name) }}', {{ $asset->current_value }})"
                                    class="btn btn-danger" style="padding:0.25rem 0.5rem;font-size:0.75rem;">Dispose</button>
                            @endif
                            <form method="POST" action="{{ route('assets.destroy', $asset) }}" style="display:inline;"
                                  onsubmit="return confirm('Delete asset &ldquo;{{ addslashes($asset->name) }}&rdquo;? This cannot be undone.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger" style="padding:0.25rem 0.5rem;font-size:0.75rem;">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>

{{-- Dispose Modal --}}
<div id="dispose-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:var(--color-surface);padding:2rem;border-radius:8px;max-width:400px;width:100%;">
        <h3 style="margin:0 0 1rem;font-family:var(--font-main);">Dispose Asset: <span id="dispose-name"></span></h3>
        <p style="margin:0 0 1rem;font-size:0.875rem;color:var(--color-text-muted);">Book value: KSh <span id="dispose-book-value"></span></p>
        <form id="dispose-form" method="POST">
            @csrf @method('PATCH')
            <div style="margin-bottom:0.75rem;">
                <label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:0.25rem;">Disposal Date</label>
                <input type="date" name="disposal_date" class="form-control" value="{{ now()->toDateString() }}" required>
            </div>
            <div style="margin-bottom:0.75rem;">
                <label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:0.25rem;">Sale Proceeds (KSh)</label>
                <input type="number" name="disposal_proceeds" class="form-control" min="0" step="0.01" placeholder="0.00">
            </div>
            <div style="margin-bottom:1rem;">
                <label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:0.25rem;">Notes</label>
                <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>
            <div style="display:flex;gap:0.5rem;">
                <button type="submit" class="btn btn-danger">Confirm Disposal</button>
                <button type="button" onclick="closeDispose()" class="btn btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openDispose(id, name, bookValue) {
    document.getElementById('dispose-name').textContent = name;
    document.getElementById('dispose-book-value').textContent = parseFloat(bookValue).toLocaleString('en-KE', {minimumFractionDigits: 2});
    document.getElementById('dispose-form').action = '/assets/' + id + '/dispose';
    const modal = document.getElementById('dispose-modal');
    modal.style.display = 'flex';
}
function closeDispose() {
    document.getElementById('dispose-modal').style.display = 'none';
}
</script>
@endsection
