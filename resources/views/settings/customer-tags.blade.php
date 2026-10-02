@extends('layouts.app')
@section('title', 'Customer Tags')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}?v={{ @filemtime(public_path('css/settings.css')) ?: '1' }}">
@endpush

@section('content')
<div class="page">

<div class="page-header">
    <div>
        <h1 class="page-title">Settings</h1>
        <p class="page-subtitle">Manage your business and account</p>
    </div>
</div>

<div class="settings-layout">

    @include('settings._nav')

    <div>

        <div class="settings-grid-2" style="display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap: var(--space-5);">
            <div class="settings-card">
                <div class="settings-card-header">
                    <h2>Add Tag</h2>
                </div>
                <div class="settings-card-body">
                    <form method="POST" action="{{ route('settings.customer-tags.store') }}">
                        @csrf
                        <div class="form-group">
                            <label class="form-label">Tag Name *</label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g. VIP, Wholesale">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Colour</label>
                            <input type="color" name="color" value="#3b82f6" style="height:40px; width:60px; border:1px solid var(--color-border); border-radius:var(--radius); padding:4px; cursor:pointer;">
                        </div>
                        <button type="submit" class="btn btn-primary">Add Tag</button>
                    </form>
                </div>
            </div>

            <div class="settings-card">
                <div class="settings-card-header">
                    <h2>Existing Tags</h2>
                </div>
                <div class="settings-card-body">
                    @if($tags->isEmpty())
                        <p style="color: var(--color-text-muted); font-size:0.9rem;">No tags yet.</p>
                    @else
                        @foreach($tags as $tag)
                        <div style="display:flex; align-items:center; gap: var(--space-2); padding:8px 0; border-bottom:1px solid var(--color-border);">
                            <form method="POST" action="{{ route('settings.customer-tags.update', $tag) }}" style="display:flex; align-items:center; gap: var(--space-2); flex:1;">
                                @csrf @method('PUT')
                                <input type="color" name="color" value="{{ $tag->color }}" style="height:32px; width:42px; border:1px solid var(--color-border); border-radius:var(--radius); padding:2px; cursor:pointer;">
                                <input type="text" name="name" value="{{ $tag->name }}" class="form-control" required style="flex:1;">
                                <span style="color: var(--color-text-muted); font-size:0.8rem; white-space:nowrap;">({{ $tag->customers_count ?? 0 }})</span>
                                <button type="submit" class="btn btn-secondary btn-sm">Save</button>
                            </form>
                            <form method="POST" action="{{ route('settings.customer-tags.destroy', $tag) }}" onsubmit="return confirm('Delete tag?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

    </div>
</div>
</div>{{-- end .page --}}
@endsection
