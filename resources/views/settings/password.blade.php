@extends('layouts.app')
@section('title', 'Change Password')

@push('styles')
    <link rel="stylesheet"
          href="{{ asset('css/settings.css') }}?v={{ @filemtime(public_path('css/settings.css')) ?: '1' }}">
@endpush

@section('content')
<div class="page">

<div class="page-header">
    <div>
        <h1 class="page-title">Settings</h1>
        <p class="page-subtitle">
            Manage your business and account
        </p>
    </div>
</div>

<div class="settings-layout">

    @include('settings._nav')

    <div>
        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        <div class="settings-card">
            <div class="settings-card-header">
                <h2>Change Password</h2>
                <p>
                    Use a strong password with letters,
                    numbers, and symbols.
                </p>
            </div>
            <div class="settings-card-body">
                <form method="POST"
                      action="{{ route(
                        'settings.password.update') }}"
                      style="max-width: 480px;">
                    @csrf
                    @method('PUT')

                    <div class="form-group">
                        <label class="form-label"
                               for="current_password">
                            Current Password *
                        </label>
                        <input
                            type="password"
                            id="current_password"
                            name="current_password"
                            class="form-control
                                {{ $errors->has(
                                    'current_password')
                                    ? 'is-invalid'
                                    : '' }}"
                            placeholder="Enter current password"
                            required>
                        @error('current_password')
                            <span class="invalid-feedback">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label"
                               for="password">
                            New Password *
                        </label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control
                                {{ $errors->has('password')
                                    ? 'is-invalid'
                                    : '' }}"
                            placeholder="Min. 8 characters"
                            oninput="checkStrength(this.value)"
                            required>
                        @error('password')
                            <span class="invalid-feedback">
                                {{ $message }}
                            </span>
                        @enderror

                        {{-- Strength indicator --}}
                        <div class="password-strength"
                             id="strengthInfo"
                             style="display: none;">
                            <span id="strengthLabel">
                            </span>
                            <div class="strength-track">
                                <div class="strength-fill"
                                     id="strengthBar"
                                     style="width: 0%;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"
                               for="password_confirmation">
                            Confirm New Password *
                        </label>
                        <input
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            class="form-control"
                            placeholder="Repeat new password"
                            required>
                    </div>

                    <div class="form-actions">
                        <button type="submit"
                                class="btn btn-primary">
                            Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</div>{{-- end .page --}}

@endsection

@push('scripts')
    <script src="{{ asset('js/settings.js') }}"></script>
@endpush