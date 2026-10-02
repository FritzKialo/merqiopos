@extends('layouts.app')
@section('title', 'Product Bundles')

@section('content')

<div class="page-header">
    <h1 class="page-title">Product Bundles</h1>
    <a href="{{ route('bundles.create') }}" class="btn btn-primary">+ New Bundle</a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if($bundles->isEmpty())
    <div class="alert alert-warning">No bundles yet. <a href="{{ route('bundles.create') }}">Create one</a>.</div>
@else
<div class="table-card">
    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>SKU</th>
                <th>Components</th>
                <th>Price</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($bundles as $b)
            <tr>
                <td data-label="Name">
                    <strong>{{ $b->name }}</strong>
                    @if($b->description)
                        <p style="margin:2px 0 0; font-size:0.78rem; color:var(--color-text-muted);">{{ Str::limit($b->description, 60) }}</p>
                    @endif
                </td>
                <td data-label="SKU">{{ $b->sku ?? '—' }}</td>
                <td data-label="Components">{{ $b->items_count }} product(s)</td>
                <td data-label="Price">KSh {{ number_format($b->price, 2) }}</td>
                <td data-label="Status">
                    <span class="badge {{ $b->is_active ? 'badge-success' : 'badge-secondary' }}">
                        {{ $b->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </td>
                <td data-label="Actions">
                    <div style="display:flex; gap:6px;">
                        <a href="{{ route('bundles.edit', $b) }}" class="btn btn-secondary" style="font-size:0.75rem; padding:4px 8px;">Edit</a>
                        <form method="POST" action="{{ route('bundles.destroy', $b) }}" onsubmit="return confirm('Delete bundle?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger" style="font-size:0.75rem; padding:4px 8px;">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

@endsection
