@extends('layouts.app')
@section('title', 'Team Members')

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

        @if(session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif

        {{-- Team Table --}}
        <div class="table-section">
            <div class="report-section-header" style="margin-bottom: 1rem;">
                <h2>Team Members</h2>
                <button
                    class="btn btn--primary btn--sm"
                    onclick="document.getElementById(
                        'addMemberModal')
                        .classList.add('open')">
                    Add Member
                </button>
            </div>

            @if($members->isEmpty())
                <div class="empty-state">
                    <h3>No team members yet.</h3>
                    <p>Add managers or cashiers to your team.</p>
                </div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Role</th>
                            <th>Last Login</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($members as $member)
                        <tr>
                            <td data-label="Member">
                                <div class="member-info">
                                    <div class="member-avatar">
                                        {{ strtoupper(
                                            substr(
                                                $member->name,
                                                0, 1)) }}
                                    </div>
                                    <div>
                                        <div style="
                                            font-weight: 600;">
                                            {{ $member->name }}
                                        </div>
                                        <div style="
                                            font-size:
                                                0.78rem;
                                            color: var(--color-text-muted);">
                                            {{ $member->email }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Role">
                                <span class="badge badge-neutral">
                                    {{ $member->roleLabel() }}
                                </span>
                            </td>
                            <td data-label="Last Login" style="
                                color:     var(--color-text-muted);
                                font-size: 0.875rem;">
                                {{ $member->last_login_at
                                    ? $member->last_login_at
                                        ->diffForHumans()
                                    : 'Never' }}
                            </td>
                            <td data-label="Status">
                                @if($member->is_active)
                                    <span class="badge
                                        badge-success">
                                        Active
                                    </span>
                                @else
                                    <span class="badge
                                        badge-danger">
                                        Inactive
                                    </span>
                                @endif
                            </td>
                            <td data-label="">
                                <div class="action-buttons">
                                    <button
                                        class="btn btn--outline btn--sm"
                                        onclick="openEditModal(
                                            {{ $member->id }},
                                            '{{ addslashes($member->name) }}',
                                            '{{ addslashes($member->email) }}',
                                            '{{ addslashes($member->role) }}'
                                        )">
                                        Edit
                                    </button>
                                    <form
                                        method="POST"
                                        action="{{ route(
                                            'settings.team.toggle',
                                            $member) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button
                                            type="submit"
                                            class="btn btn--outline btn--sm">
                                            {{ $member->is_active
                                                ? 'Disable'
                                                : 'Enable' }}
                                        </button>
                                    </form>
                                    @if($member->hasTwoFactorEnabled())
                                    <form
                                        method="POST"
                                        action="{{ route(
                                            'settings.team.reset-2fa',
                                            $member) }}"
                                        onsubmit="return confirm(
                                            'Reset two-factor authentication for {{ addslashes($member->name) }}? Only do this after confirming their identity another way (in person, a call) — this removes their 2FA protection until they set it up again.')">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="btn btn--outline btn--sm">
                                            Reset 2FA
                                        </button>
                                    </form>
                                    @endif
                                    <form
                                        method="POST"
                                        action="{{ route(
                                            'settings.team.destroy',
                                            $member) }}"
                                        onsubmit="return confirm(
                                            'Remove {{ addslashes($member->name) }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="btn btn--danger btn--sm">
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Owner card --}}
        <div class="settings-card">
            <div class="settings-card-header">
                <h2>Account Owner</h2>
                <p>
                    The owner account cannot be
                    modified by team members.
                </p>
            </div>
            <div class="settings-card-body">
                <div class="member-info">
                    <div class="member-avatar"
                         style="
                            background: var(--color-success);
                            width:  48px;
                            height: 48px;
                            font-size: 1.2rem;">
                        {{ strtoupper(substr(
                            Auth::user()->name, 0, 1)) }}
                    </div>
                    <div>
                        <div style="font-weight: 700;">
                            {{ Auth::user()->name }}
                        </div>
                        <div style="
                            color:     var(--color-text-muted);
                            font-size: 0.875rem;">
                            {{ Auth::user()->email }}
                        </div>
                        <span class="badge badge-success"
                              style="margin-top: 4px;">
                            Owner
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Add Member Modal --}}
<div class="modal-overlay" id="addMemberModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Add Team Member</h3>
            <button class="modal-close"
                    onclick="document.getElementById(
                        'addMemberModal')
                        .classList.remove('open')">
                &times;
            </button>
        </div>

        <form method="POST"
              action="{{ route('settings.team.store') }}">
            @csrf

            <div class="form-group">
                <label class="form-label">
                    Full Name *
                </label>
                <input
                    type="text"
                    name="name"
                    class="form-control
                        {{ $errors->has('name')
                            ? 'is-invalid' : '' }}"
                    value="{{ old('name') }}"
                    placeholder="e.g. John Kamau"
                    required>
                @error('name')
                    <span class="invalid-feedback">
                        {{ $message }}
                    </span>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label">
                    Email Address *
                </label>
                <input
                    type="email"
                    name="email"
                    class="form-control
                        {{ $errors->has('email')
                            ? 'is-invalid' : '' }}"
                    value="{{ old('email') }}"
                    placeholder="john@example.com"
                    required>
                @error('email')
                    <span class="invalid-feedback">
                        {{ $message }}
                    </span>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label">
                    Role *
                </label>
                <select name="role"
                        class="form-control">
                    @if(Auth::user()->isOwner())
                    <option value="overall_manager"
                        {{ old('role') == 'overall_manager' ? 'selected' : '' }}>
                        Overall Manager (all branches)
                    </option>
                    @endif
                    <option value="manager"
                        {{ old('role') == 'manager' ? 'selected' : '' }}>
                        Manager
                    </option>
                    <option value="cashier"
                        {{ old('role') == 'cashier' ? 'selected' : '' }}>
                        Cashier
                    </option>
                    <option value="staff"
                        {{ old('role') == 'staff' ? 'selected' : '' }}>
                        Staff
                    </option>
                </select>
                <span class="form-hint">
                    @if(Auth::user()->isOwner())Overall managers oversee every branch (like the owner, minus billing). @endif
                    Managers run a single branch. Cashiers process sales. Staff are general employees.
                </span>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">
                        Password *
                    </label>
                    <input
                        type="password"
                        name="password"
                        class="form-control
                            {{ $errors->has('password')
                                ? 'is-invalid' : '' }}"
                        placeholder="Min. 8 characters"
                        required>
                    @error('password')
                        <span class="invalid-feedback">
                            {{ $message }}
                        </span>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Confirm Password *
                    </label>
                    <input
                        type="password"
                        name="password_confirmation"
                        class="form-control"
                        placeholder="Repeat password"
                        required>
                </div>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn--outline"
                    onclick="document.getElementById(
                        'addMemberModal')
                        .classList.remove('open')">
                    Cancel
                </button>
                <button type="submit"
                        class="btn btn--primary">
                    Add Member
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Member Modal --}}
<div class="modal-overlay" id="editMemberModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Edit Member</h3>
            <button class="modal-close"
                    onclick="document.getElementById(
                        'editMemberModal')
                        .classList.remove('open')">
                &times;
            </button>
        </div>

        <form method="POST"
              id="editMemberForm">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label class="form-label">
                    Full Name *
                </label>
                <input
                    type="text"
                    name="name"
                    id="editName"
                    class="form-control"
                    required>
            </div>

            <div class="form-group">
                <label class="form-label">
                    Email Address *
                </label>
                <input
                    type="email"
                    name="email"
                    id="editEmail"
                    class="form-control"
                    required>
            </div>

            <div class="form-group">
                <label class="form-label">Role *</label>
                <select name="role"
                        id="editRole"
                        class="form-control">
                    @if(Auth::user()->isOwner())
                    <option value="overall_manager">
                        Overall Manager (all branches)
                    </option>
                    @endif
                    <option value="manager">Manager</option>
                    <option value="cashier">Cashier</option>
                    <option value="staff">Staff</option>
                </select>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">
                        New Password
                    </label>
                    <input
                        type="password"
                        name="password"
                        class="form-control"
                        placeholder="Leave blank to keep">
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Confirm Password
                    </label>
                    <input
                        type="password"
                        name="password_confirmation"
                        class="form-control"
                        placeholder="Repeat if changing">
                </div>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn--outline"
                    onclick="document.getElementById(
                        'editMemberModal')
                        .classList.remove('open')">
                    Cancel
                </button>
                <button type="submit"
                        class="btn btn--primary">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
</div>{{-- end .page --}}

@endsection

@push('scripts')
    <script>
        window.__teamHasErrors = {{ ($errors->hasAny(['name', 'email', 'role', 'password']) && !isset($editMember)) ? 'true' : 'false' }};
    </script>
    <script src="{{ asset('js/settings.js') }}?v=20260626"></script>
@endpush