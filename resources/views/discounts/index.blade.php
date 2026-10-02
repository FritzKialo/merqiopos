@extends('layouts.app')
@section('title', 'Discounts')

@section('content')

<div class="page-header">
    <h1 class="page-title">Discounts</h1>
    <a href="{{ route('discounts.create') }}" class="btn btn-primary">+ New Discount</a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if($discounts->isEmpty())
    <div class="alert alert-warning">No discounts yet. <a href="{{ route('discounts.create') }}">Create one</a>.</div>
@else
<div class="table-card">
    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Code</th>
                <th>Type</th>
                <th>Value</th>
                <th>Uses</th>
                <th>Valid</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($discounts as $d)
            <tr>
                <td data-label="Name">{{ $d->name }}</td>
                <td data-label="Code">
                    @if($d->code)
                        <code style="background:var(--color-surface); padding:2px 6px; border-radius:4px; font-size:0.85rem;">{{ $d->code }}</code>
                    @else
                        <span style="color:var(--color-text-muted);">—</span>
                    @endif
                </td>
                <td data-label="Type">
                    <span class="badge badge-secondary">{{ ucfirst($d->type) }}</span>
                </td>
                <td data-label="Value">
                    @if($d->type === 'percentage')
                        {{ $d->value }}%
                    @else
                        KSh {{ number_format($d->value, 2) }}
                    @endif
                </td>
                <td data-label="Uses">
                    {{ $d->uses_count }}{{ $d->max_uses ? ' / ' . $d->max_uses : '' }}
                </td>
                <td data-label="Valid" style="font-size:0.8rem;">
                    @if($d->valid_from || $d->valid_until)
                        {{ $d->valid_from?->format('d M Y') ?? '—' }} to {{ $d->valid_until?->format('d M Y') ?? '∞' }}
                    @else
                        Always
                    @endif
                </td>
                <td data-label="Status">
                    <form method="POST" action="{{ route('discounts.toggle', $d) }}" style="display:inline;">
                        @csrf @method('PATCH')
                        <button type="submit" class="badge {{ $d->is_active ? 'badge-success' : 'badge-secondary' }}"
                            style="border:none; cursor:pointer; padding:4px 10px;">
                            {{ $d->is_active ? 'Active' : 'Inactive' }}
                        </button>
                    </form>
                </td>
                <td data-label="Actions">
                    <div style="display:flex; gap:6px;">
                        <a href="{{ route('discounts.edit', $d) }}" class="btn btn-secondary" style="font-size:0.75rem; padding:4px 8px;">Edit</a>
                        <form method="POST" action="{{ route('discounts.destroy', $d) }}" onsubmit="return confirm('Delete?')">
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
