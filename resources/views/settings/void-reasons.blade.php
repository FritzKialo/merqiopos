@extends('layouts.app')
@section('title', 'Void Reasons')

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

        {{-- Add new void reason --}}
        <div class="table-section" style="margin-bottom:1.5rem;">
            <div class="report-section-header" style="margin-bottom:1rem;">
                <h2>Add Void Reason</h2>
            </div>
            <form method="POST" action="{{ route('settings.void-reasons.store') }}"
                  style="display:flex; gap:0.75rem; flex-wrap:wrap; align-items:flex-end;">
                @csrf
                <div>
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Customer Changed Order"
                           value="{{ old('name') }}" style="width:260px;" required>
                </div>
                <label style="display:flex; align-items:center; gap:6px; padding-bottom:8px; font-size:0.9rem;">
                    <input type="checkbox" name="enabled" value="1" checked> Enabled
                </label>
                <button type="submit" class="btn btn--primary">Add</button>
            </form>
            <p style="margin-top:0.75rem; font-size:0.8rem; color:var(--color-text-muted);">
                These are the options a cashier or manager picks from when cancelling a sale.
                A cashier's cancellation goes to a manager for approval first — see
                <a href="{{ route('void-requests.index') }}">Pending Voids</a>.
            </p>
        </div>

        {{-- Existing void reasons --}}
        <div class="table-section">
            <div class="report-section-header" style="margin-bottom:1rem;">
                <h2>Void Reasons</h2>
            </div>

            @if($voidReasons->isEmpty())
                <div class="empty-state">
                    <h3>No void reasons yet.</h3>
                    <p>Add your first reason above so staff can cancel a sale with an explanation on record.</p>
                </div>
            @else
                @foreach($voidReasons as $reason)
                <div style="display:flex; gap:0.75rem; flex-wrap:wrap; align-items:flex-end;
                            padding:0.85rem 0; border-bottom:1px solid var(--color-border);">
                    <form method="POST" action="{{ route('settings.void-reasons.update', $reason) }}"
                          style="display:flex; gap:0.75rem; flex-wrap:wrap; align-items:flex-end;">
                        @csrf @method('PUT')
                        <div>
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" value="{{ $reason->name }}" required style="width:260px;">
                        </div>
                        <label style="display:flex; align-items:center; gap:6px; padding-bottom:8px; font-size:0.9rem;">
                            <input type="checkbox" name="enabled" value="1" {{ $reason->enabled ? 'checked' : '' }}> Enabled
                        </label>
                        <button type="submit" class="btn btn--secondary">Save</button>
                    </form>
                    <form method="POST" action="{{ route('settings.void-reasons.destroy', $reason) }}"
                          onsubmit="return confirm('Delete &ldquo;{{ addslashes($reason->name) }}&rdquo;? This only works if it has never been used.')">
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
