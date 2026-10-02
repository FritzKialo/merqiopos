@extends('layouts.app')
@section('title', 'Leave Types')

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
        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        {{-- Add new leave type --}}
        <div class="table-section" style="margin-bottom:1.5rem;">
            <div class="report-section-header" style="margin-bottom:1rem;">
                <h2>Add Leave Type</h2>
            </div>
            <form method="POST" action="{{ route('settings.leave-types.store') }}"
                  style="display:flex; gap:0.75rem; flex-wrap:wrap; align-items:flex-end;">
                @csrf
                <div>
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Annual Leave"
                           value="{{ old('name') }}" style="width:200px;" required>
                </div>
                <div>
                    <label class="form-label">Days / year</label>
                    <input type="number" name="days_per_year" class="form-control" min="0" max="365"
                           value="{{ old('days_per_year', 21) }}" style="width:120px;" required>
                </div>
                <label style="display:flex; align-items:center; gap:6px; padding-bottom:8px; font-size:0.9rem;">
                    <input type="checkbox" name="is_paid" value="1" checked> Paid
                </label>
                <label style="display:flex; align-items:center; gap:6px; padding-bottom:8px; font-size:0.9rem;">
                    <input type="checkbox" name="requires_approval" value="1" checked> Requires approval
                </label>
                <button type="submit" class="btn btn--primary">Add</button>
            </form>
            <p style="margin-top:0.75rem; font-size:0.8rem; color:var(--color-text-muted);">
                These are the options staff pick from on the “Apply for Leave” form.
                “Requires approval” means a manager must approve before it counts; otherwise it’s auto-approved.
            </p>
        </div>

        {{-- Existing leave types --}}
        <div class="table-section">
            <div class="report-section-header" style="margin-bottom:1rem;">
                <h2>Leave Types</h2>
            </div>

            @if($leaveTypes->isEmpty())
                <div class="empty-state">
                    <h3>No leave types yet.</h3>
                    <p>Add your first leave type above so staff can request leave.</p>
                </div>
            @else
                @foreach($leaveTypes as $type)
                <div style="display:flex; gap:0.75rem; flex-wrap:wrap; align-items:flex-end;
                            padding:0.85rem 0; border-bottom:1px solid var(--color-border);">
                    <form method="POST" action="{{ route('settings.leave-types.update', $type) }}"
                          style="display:flex; gap:0.75rem; flex-wrap:wrap; align-items:flex-end;">
                        @csrf @method('PUT')
                        <div>
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" value="{{ $type->name }}" required style="width:200px;">
                        </div>
                        <div>
                            <label class="form-label">Days / year</label>
                            <input type="number" name="days_per_year" class="form-control" min="0" max="365" value="{{ $type->days_per_year }}" required style="width:110px;">
                        </div>
                        <label style="display:flex; align-items:center; gap:6px; padding-bottom:8px; font-size:0.9rem;">
                            <input type="checkbox" name="is_paid" value="1" {{ $type->is_paid ? 'checked' : '' }}> Paid
                        </label>
                        <label style="display:flex; align-items:center; gap:6px; padding-bottom:8px; font-size:0.9rem;">
                            <input type="checkbox" name="requires_approval" value="1" {{ $type->requires_approval ? 'checked' : '' }}> Requires approval
                        </label>
                        <button type="submit" class="btn btn--secondary">Save</button>
                    </form>
                    <form method="POST" action="{{ route('settings.leave-types.destroy', $type) }}"
                          onsubmit="return confirm('Delete &ldquo;{{ addslashes($type->name) }}&rdquo;? This only works if no one has requested it yet.')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn--danger">Delete</button>
                    </form>
                </div>
                @endforeach
            @endif
        </div>
    </div>
</div>
</div>
@endsection
