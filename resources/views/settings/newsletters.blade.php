@extends('layouts.app')
@section('title', 'Newsletters')

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
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="settings-card">
            <div class="settings-card-header">
                <h2>Send a Newsletter</h2>
                <p>An email announcement to your own customers — order updates, promotions, closures. Includes an unsubscribe link automatically.</p>
            </div>
            <div class="settings-card-body">
                <form method="POST" action="{{ route('settings.newsletters.store') }}">
                    @csrf

                    <div class="form-group">
                        <label class="form-label" for="title">Subject *</label>
                        <input type="text" id="title" name="title" class="form-control {{ $errors->has('title') ? 'is-invalid' : '' }}"
                               value="{{ old('title') }}" maxlength="150" placeholder="e.g. 20% off this weekend" required>
                        @error('title') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="message">Message *</label>
                        <textarea id="message" name="message" class="form-control {{ $errors->has('message') ? 'is-invalid' : '' }}"
                                  rows="6" maxlength="5000" required>{{ old('message') }}</textarea>
                        @error('message') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">Send to *</label>
                        <div style="display:flex; flex-direction:column; gap:8px;">
                            <label style="display:flex; align-items:center; gap:8px; font-size:.875rem; cursor:pointer;">
                                <input type="radio" name="scope" value="store" {{ old('scope', 'store') === 'store' ? 'checked' : '' }}>
                                Customers of this store only
                            </label>
                            @if($multiStore)
                            <label style="display:flex; align-items:center; gap:8px; font-size:.875rem; cursor:pointer;">
                                <input type="radio" name="scope" value="org" {{ old('scope') === 'org' ? 'checked' : '' }}>
                                Customers across all stores in the organization
                            </label>
                            @endif
                        </div>
                        @error('scope') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                    </div>

                    <p style="font-size:12.5px; color:var(--color-text-muted); margin:-4px 0 16px;">
                        Only customers with an email on file who haven't unsubscribed will be included. Sending happens in the background, so this may take a few minutes for a large list.
                    </p>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"
                            onclick="return confirm('Send this newsletter now?')">Send Newsletter</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="settings-card" style="margin-top:var(--space-5);">
            <div class="settings-card-header">
                <h2>Sent Newsletters</h2>
            </div>
            <div class="settings-card-body">
                @if($newsletters->isEmpty())
                <p style="color:var(--color-text-muted); font-size:.875rem;">No newsletters sent yet.</p>
                @else
                <div class="data-table-wrap">
                    <table class="settings-table">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Audience</th>
                                <th>Reached</th>
                                <th>Email Failures</th>
                                <th>Sent By</th>
                                <th>Date</th>
                                @role('owner')<th></th>@endrole
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($newsletters as $newsletter)
                            <tr>
                                <td data-label="Subject"><strong>{{ $newsletter->title }}</strong></td>
                                <td data-label="Audience">{{ $newsletter->scopeLabel() }}</td>
                                <td data-label="Reached">{{ $newsletter->recipient_count }}</td>
                                <td data-label="Email Failures">{{ $newsletter->email_failed_count }}</td>
                                <td data-label="Sent By">{{ $newsletter->sender?->name ?? 'Unknown' }}</td>
                                <td data-label="Date">{{ $newsletter->created_at->format('d M Y, g:i A') }}</td>
                                @role('owner')
                                <td data-label="">
                                    <form method="POST" action="{{ route('settings.newsletters.destroy', $newsletter) }}"
                                          onsubmit="return confirm('Delete this record? This only removes it from this list — it does not unsend the emails.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </td>
                                @endrole
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div style="margin-top:var(--space-4);">{{ $newsletters->links() }}</div>
                @endif
            </div>
        </div>

    </div>
</div>
</div>{{-- end .page --}}
@endsection
