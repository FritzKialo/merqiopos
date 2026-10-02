@extends('layouts.app')
@section('title', 'Webhook Deliveries')

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

        <div class="settings-card">
            <div class="settings-card-header" style="display:flex; align-items:center; justify-content:space-between;">
                <div>
                    <h2>Delivery Log</h2>
                    <p style="word-break:break-all;">{{ $webhook->url }}</p>
                </div>
                <a href="{{ route('settings.webhooks.index') }}" class="btn btn-outline">Back</a>
            </div>
            <div class="settings-card-body" style="{{ $deliveries->isEmpty() ? '' : 'padding: 0;' }}">
                @if($deliveries->isEmpty())
                    <div class="empty-state"><p>No deliveries yet.</p></div>
                @else
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Status</th>
                                <th>Result</th>
                                <th>Time</th>
                                <th>Payload</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($deliveries as $d)
                            <tr>
                                <td data-label="Event"><span class="badge badge-secondary">{{ $d->event }}</span></td>
                                <td data-label="Status">{{ $d->response_status ?? '—' }}</td>
                                <td data-label="Result">
                                    @if($d->failed)
                                        <span class="badge badge-danger">Failed</span>
                                    @else
                                        <span class="badge badge-success">Delivered</span>
                                    @endif
                                </td>
                                <td data-label="Time">{{ $d->delivered_at ? $d->delivered_at->diffForHumans() : $d->created_at->diffForHumans() }}</td>
                                <td data-label="Payload">
                                    <details>
                                        <summary style="cursor:pointer; font-size:0.8rem;">View</summary>
                                        <pre style="font-size:0.75rem; background:var(--color-surface-2); border:1px solid var(--color-border); border-radius:4px; padding:8px; margin-top:4px; overflow-x:auto; max-width:400px; white-space:pre-wrap;">{{ json_encode($d->payload, JSON_PRETTY_PRINT) }}</pre>
                                        @if($d->response_body)
                                        <pre style="font-size:0.75rem; background:var(--color-surface-2); border:1px solid var(--color-border); border-radius:4px; padding:8px; margin-top:4px; overflow-x:auto; max-width:400px; white-space:pre-wrap; color:var(--color-text-muted);">Response: {{ $d->response_body }}</pre>
                                        @endif
                                    </details>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div style="padding: var(--space-4);">{{ $deliveries->links() }}</div>
                @endif
            </div>
        </div>

    </div>
</div>
</div>{{-- end .page --}}
@endsection
