@extends('layouts.app')
@section('title', 'Open Shift')
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Open Shift</h1>
            <p class="page-subtitle">Count your opening cash float before starting</p>
        </div>
        <a href="{{ route('shifts.index') }}" class="btn btn--outline">Back</a>
    </div>

    @if($errors->any())
        <div class="alert alert--error">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
    @endif

    <div class="table-card" style="max-width:480px;">
        <div class="card-body">
            <form method="POST" action="{{ route('shifts.open.store') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">Opening Cash Float (KSh) *</label>
                    <input type="number" name="opening_float" class="form-control"
                           value="{{ old('opening_float', 0) }}" min="0" step="0.01" required autofocus>
                    <span class="form-hint">Count the cash in the till before any sales.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes…">{{ old('notes') }}</textarea>
                </div>
                <button type="submit" class="btn btn--primary" style="width:100%;">Open Shift</button>
            </form>
        </div>
    </div>
</div>
@endsection
