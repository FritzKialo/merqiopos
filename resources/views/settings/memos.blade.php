@extends('layouts.app')
@section('title', 'Memos')

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
                <h2>Send a Memo</h2>
                <p>A one-way announcement — it lands in the recipient's notification bell, no reply needed. Good for policy changes, closures, shift reminders, anything the whole team (or a slice of it) should just know.</p>
            </div>
            <div class="settings-card-body">
                <form method="POST" action="{{ route('settings.memos.store') }}">
                    @csrf

                    <div class="form-group">
                        <label class="form-label" for="title">Subject *</label>
                        <input type="text" id="title" name="title" class="form-control {{ $errors->has('title') ? 'is-invalid' : '' }}"
                               value="{{ old('title') }}" maxlength="150" placeholder="e.g. Early closing this Friday" required>
                        @error('title') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="message">Message *</label>
                        <textarea id="message" name="message" class="form-control {{ $errors->has('message') ? 'is-invalid' : '' }}"
                                  rows="4" maxlength="1000" required>{{ old('message') }}</textarea>
                        <span class="form-hint">Keep it short — this shows as a notification, not a full page.</span>
                        @error('message') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">Send to *</label>
                        <div style="display:flex; flex-direction:column; gap:8px;">
                            <label style="display:flex; align-items:center; gap:8px; font-size:.875rem; cursor:pointer;">
                                <input type="radio" name="scope" value="store" id="scope-store" {{ old('scope', 'store') === 'store' ? 'checked' : '' }}>
                                All staff at this store
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; font-size:.875rem; cursor:pointer;">
                                <input type="radio" name="scope" value="role" id="scope-role" {{ old('scope') === 'role' ? 'checked' : '' }}>
                                Just one role at this store
                            </label>
                            @if($multiStore)
                            <label style="display:flex; align-items:center; gap:8px; font-size:.875rem; cursor:pointer;">
                                <input type="radio" name="scope" value="org" {{ old('scope') === 'org' ? 'checked' : '' }}>
                                Everyone across all stores in the organization
                            </label>
                            @endif
                        </div>
                        @error('scope') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-group" id="target-role-group" style="{{ old('scope') === 'role' ? '' : 'display:none;' }} max-width:280px;">
                        <label class="form-label" for="target_role">Role</label>
                        <select name="target_role" id="target_role" class="form-control {{ $errors->has('target_role') ? 'is-invalid' : '' }}">
                            @foreach($roles as $role)
                            <option value="{{ $role }}" {{ old('target_role') === $role ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $role)) }}</option>
                            @endforeach
                        </select>
                        @error('target_role') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Send Memo</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="settings-card" style="margin-top:var(--space-5);">
            <div class="settings-card-header">
                <h2>Sent Memos</h2>
            </div>
            <div class="settings-card-body">
                @if($memos->isEmpty())
                <p style="color:var(--color-text-muted); font-size:.875rem;">No memos sent yet.</p>
                @else
                <div class="data-table-wrap">
                    <table class="settings-table">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Audience</th>
                                <th>Reached</th>
                                <th>Sent By</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($memos as $memo)
                            <tr>
                                <td data-label="Subject"><strong>{{ $memo->title }}</strong></td>
                                <td data-label="Audience">{{ $memo->scopeLabel() }}</td>
                                <td data-label="Reached">{{ $memo->recipient_count }}</td>
                                <td data-label="Sent By">{{ $memo->sender?->name ?? 'Unknown' }}</td>
                                <td data-label="Date">{{ $memo->created_at->format('d M Y, g:i A') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div style="margin-top:var(--space-4);">{{ $memos->links() }}</div>
                @endif
            </div>
        </div>

    </div>
</div>
</div>{{-- end .page --}}

@push('scripts')
<script>
(function () {
    var radios = document.querySelectorAll('input[name="scope"]');
    var roleGroup = document.getElementById('target-role-group');
    radios.forEach(function (r) {
        r.addEventListener('change', function () {
            roleGroup.style.display = (document.getElementById('scope-role').checked) ? '' : 'none';
        });
    });
})();
</script>
@endpush
@endsection
