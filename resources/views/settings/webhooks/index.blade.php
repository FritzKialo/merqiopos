@extends('layouts.app')
@section('title', 'Webhooks')

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

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="settings-card">
            <div class="settings-card-header" style="display:flex; align-items:center; justify-content:space-between;">
                <div>
                    <h2>Webhooks</h2>
                    <p>Receive POST notifications when events occur in your business.</p>
                </div>
                <a href="{{ route('settings.webhooks.create') }}" class="btn btn-primary">Add Webhook</a>
            </div>
            <div class="settings-card-body" style="{{ $webhooks->isEmpty() ? '' : 'padding: 0;' }}">
                @if($webhooks->isEmpty())
                    <div class="empty-state">
                        <p>No webhooks configured yet.</p>
                        <a href="{{ route('settings.webhooks.create') }}" class="btn btn-primary">Add your first webhook</a>
                    </div>
                @else
                    <table class="table">
                        <thead>
                            <tr>
                                <th>URL</th>
                                <th>Events</th>
                                <th>Status</th>
                                <th>Last Triggered</th>
                                <th>Failures</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($webhooks as $webhook)
                            <tr>
                                <td data-label="URL" style="max-width:280px; word-break:break-all; font-size:0.85rem;">{{ $webhook->url }}</td>
                                <td data-label="Events">
                                    @foreach($webhook->events as $evt)
                                        <span class="badge badge-secondary" style="margin:1px;">{{ $evt }}</span>
                                    @endforeach
                                </td>
                                <td data-label="Status">
                                    @if($webhook->is_active)
                                        <span class="badge badge-success">Active</span>
                                    @else
                                        <span class="badge badge-danger">Inactive</span>
                                    @endif
                                </td>
                                <td data-label="Last Triggered">{{ $webhook->last_triggered_at ? $webhook->last_triggered_at->diffForHumans() : '—' }}</td>
                                <td data-label="Failures">
                                    @if($webhook->failure_count > 0)
                                        <span style="color:var(--color-danger);">{{ $webhook->failure_count }}</span>
                                    @else
                                        0
                                    @endif
                                </td>
                                <td data-label="Actions">
                                    <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                        <form method="POST" action="{{ route('settings.webhooks.test', $webhook) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-secondary btn-sm">Test</button>
                                        </form>
                                        <a href="{{ route('settings.webhooks.deliveries', $webhook) }}" class="btn btn-secondary btn-sm">Log</a>
                                        <a href="{{ route('settings.webhooks.edit', $webhook) }}" class="btn btn-secondary btn-sm">Edit</a>
                                        <form method="POST" action="{{ route('settings.webhooks.destroy', $webhook) }}" onsubmit="return confirm('Delete this webhook?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
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

    </div>
</div>
</div>{{-- end .page --}}
@endsection
