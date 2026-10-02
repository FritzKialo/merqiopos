@extends('layouts.app')
@section('title', 'New Warehouse')
@section('content')
<div class="page-header">
    <h1>New Warehouse</h1>
</div>

<div class="card" style="max-width:560px;">
    <div class="card-body">
        <form method="POST" action="{{ route('warehouses.store') }}">
            @csrf

            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label">Warehouse Name <span style="color:red;">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                       value="{{ old('name') }}" placeholder="e.g. Main Store, Nairobi Branch" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label">Code <span style="color:#aaa;font-size:0.85rem;">(optional)</span></label>
                <input type="text" name="code" class="form-control" value="{{ old('code') }}" placeholder="e.g. WH-01">
            </div>

            <div class="form-group" style="margin-bottom:24px;">
                <label class="form-label">Location / Address <span style="color:#aaa;font-size:0.85rem;">(optional)</span></label>
                <input type="text" name="location" class="form-control" value="{{ old('location') }}" placeholder="e.g. Industrial Area, Nairobi">
            </div>

            <div style="display:flex;gap:12px;">
                <button type="submit" class="btn btn-primary">Create Warehouse</button>
                <a href="{{ route('warehouses.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
