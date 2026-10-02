@extends('layouts.app')
@section('title', 'Edit Webhook')

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
                <h2>Edit Webhook</h2>
                <a href="{{ route('settings.webhooks.index') }}" class="btn btn-outline">Back</a>
            </div>
            <div class="settings-card-body">
                <form method="POST" action="{{ route('settings.webhooks.update', $webhook) }}">
                    @csrf @method('PUT')

                    <div class="form-group">
                        <label class="form-label" for="url">Endpoint URL *</label>
                        <input type="url" id="url" name="url" class="form-control {{ $errors->has('url') ? 'is-invalid' : '' }}"
                               value="{{ old('url', $webhook->url) }}" required>
                        @error('url') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="secret">Secret (optional)</label>
                        <input type="text" id="secret" name="secret" class="form-control"
                               value="{{ old('secret', $webhook->secret) }}" placeholder="Leave blank to keep existing">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Events *</label>
                        <div class="settings-grid-2" style="display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap: var(--space-2); margin-top:6px;">
                            @foreach($events as $event)
                            <label style="display:flex; align-items:center; gap: var(--space-2); font-size:0.875rem; cursor:pointer;">
                                <input type="checkbox" name="events[]" value="{{ $event }}"
                                    {{ in_array($event, old('events', $webhook->events ?? [])) ? 'checked' : '' }}>
                                {{ $event }}
                            </label>
                            @endforeach
                        </div>
                        @error('events') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-group">
                        <label style="display:flex; align-items:center; gap: var(--space-2); font-size:0.875rem; cursor:pointer;">
                            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $webhook->is_active) ? 'checked' : '' }}>
                            Active
                        </label>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Update Webhook</button>
                        <a href="{{ route('settings.webhooks.index') }}" class="btn btn-outline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>
</div>{{-- end .page --}}
@endsection
