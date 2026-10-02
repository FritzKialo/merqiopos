@extends('admin.layouts.app')
@section('title', 'Newsletters')
@section('subtitle', 'Broadcast announcements to organization owners platform-wide')

@section('content')

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Newsletters</h1>
        <p class="admin-page-subtitle">Send an announcement to organization owners — delivered by email and in their notification bell.</p>
    </div>
</div>

@if(session('success'))
    <div class="admin-alert admin-alert-success">
        <i class="ph-bold ph-check-circle"></i>
        {{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div class="admin-alert admin-alert-error">
        <i class="ph-bold ph-warning-circle"></i>
        {{ session('error') }}
    </div>
@endif

<div class="admin-panel" style="margin-bottom:24px;">
    <div style="padding:20px;">
        <form method="POST" action="{{ route('admin.newsletters.store') }}">
            @csrf

            <div class="form-group">
                <label class="form-label" for="title">Subject *</label>
                <input type="text" id="title" name="title" class="form-control {{ $errors->has('title') ? 'is-invalid' : '' }}"
                       value="{{ old('title') }}" maxlength="150" placeholder="e.g. New feature: Payroll statutory reports" required>
                @error('title') <span class="invalid-feedback">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="message">Message *</label>
                <textarea id="message" name="message" class="form-control {{ $errors->has('message') ? 'is-invalid' : '' }}"
                          rows="5" maxlength="5000" required>{{ old('message') }}</textarea>
                @error('message') <span class="invalid-feedback">{{ $message }}</span> @enderror
            </div>

            <div style="display:flex; gap:16px; flex-wrap:wrap;">
                <div class="form-group" style="max-width:220px; flex:1;">
                    <label class="form-label" for="audience_status">Status filter</label>
                    <select name="audience_status" id="audience_status" class="form-control">
                        <option value="">All statuses</option>
                        <option value="active"    {{ old('audience_status') === 'active'    ? 'selected' : '' }}>Active</option>
                        <option value="trial"     {{ old('audience_status') === 'trial'     ? 'selected' : '' }}>On Trial</option>
                        <option value="suspended" {{ old('audience_status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
                    </select>
                </div>
                <div class="form-group" style="max-width:220px; flex:1;">
                    <label class="form-label" for="audience_plan">Plan filter</label>
                    <select name="audience_plan" id="audience_plan" class="form-control">
                        <option value="">All plans</option>
                        <option value="solo"       {{ old('audience_plan') === 'solo'       ? 'selected' : '' }}>Solo</option>
                        <option value="growth"     {{ old('audience_plan') === 'growth'     ? 'selected' : '' }}>Growth</option>
                        <option value="enterprise" {{ old('audience_plan') === 'enterprise' ? 'selected' : '' }}>Enterprise</option>
                    </select>
                </div>
            </div>
            <p style="font-size:12.5px; color:var(--color-text-secondary); margin:-8px 0 16px;">
                Leave both filters unset to reach every organization owner on the platform. Sends to all owners of matching organizations, immediately, by email and in-app.
            </p>

            <button type="submit" class="admin-btn admin-btn-primary"
                onclick="return confirm('Send this newsletter now? This emails every matching organization owner immediately.')">
                <i class="ph-bold ph-paper-plane-tilt"></i> Send Newsletter
            </button>
        </form>
    </div>
</div>

<div class="admin-panel">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Audience</th>
                    <th>Reached</th>
                    <th>Email Failures</th>
                    <th>Sent By</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($newsletters as $newsletter)
                <tr>
                    <td><strong>{{ $newsletter->title }}</strong></td>
                    <td>{{ $newsletter->audienceLabel() }}</td>
                    <td>{{ $newsletter->recipient_count }}</td>
                    <td>
                        @if($newsletter->email_failed_count > 0)
                            <span class="admin-badge admin-badge-red">{{ $newsletter->email_failed_count }}</span>
                        @else
                            <span class="admin-badge admin-badge-gray">0</span>
                        @endif
                    </td>
                    <td>{{ $newsletter->sender?->name ?? 'Unknown' }}</td>
                    <td style="color: var(--color-text-secondary); font-size:12px; white-space:nowrap;">
                        {{ $newsletter->created_at->format('d M Y, g:i A') }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center; padding:40px; color: var(--color-text-secondary);">
                        No newsletters sent yet.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($newsletters->hasPages())
    <div class="admin-pagination">
        {{ $newsletters->links() }}
    </div>
    @endif
</div>

@endsection
