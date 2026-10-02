@extends('layouts.app')
@section('title', 'New Payroll Period')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}?v={{ @filemtime(public_path('css/settings.css')) ?: '1' }}">
@endpush

@section('content')
<div class="page">

<div class="page-header">
    <div>
        <h1 class="page-title">New Payroll Period</h1>
        <p class="page-subtitle">Define the date range for this pay run.</p>
    </div>
    <a href="{{ route('payroll.index') }}" class="btn btn-secondary">
         Back
    </a>
</div>

@if($errors->any())
<div class="alert alert-danger" style="margin-bottom:var(--space-4);">
    <ul style="margin:0; padding-left:1.2em;">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<div class="settings-card" style="max-width:600px;">
    <div class="settings-card-header">
        <h2> Period Details</h2>
        <p>The pay cycle is inherited from your payroll settings.</p>
    </div>
    <div class="settings-card-body">
        <form method="POST" action="{{ route('payroll.store') }}">
            @csrf

            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">Period Start *</label>
                    <input type="date" name="period_start" class="form-control"
                        value="{{ old('period_start', $suggestedStart->format('Y-m-d')) }}"
                        required>
                    @error('period_start')
                        <span class="invalid-feedback" style="display:block;">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Period End *</label>
                    <input type="date" name="period_end" class="form-control"
                        value="{{ old('period_end', $suggestedEnd->format('Y-m-d')) }}"
                        required>
                    @error('period_end')
                        <span class="invalid-feedback" style="display:block;">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Pay Cycle *</label>
                <select name="pay_cycle" class="form-control">
                    <option value="monthly"   {{ old('pay_cycle', $payCycle) === 'monthly'   ? 'selected' : '' }}>Monthly</option>
                    <option value="bi_weekly" {{ old('pay_cycle', $payCycle) === 'bi_weekly' ? 'selected' : '' }}>Bi-Weekly (every 2 weeks)</option>
                    <option value="weekly"    {{ old('pay_cycle', $payCycle) === 'weekly'    ? 'selected' : '' }}>Weekly</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2"
                    placeholder="Optional — e.g. includes bonus for Q2">{{ old('notes') }}</textarea>
            </div>

            <div class="form-actions" style="margin-top:var(--space-5);">
                <button type="submit" class="btn btn-primary">
                     Create Period
                </button>
                <a href="{{ route('payroll.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

</div>
@endsection
