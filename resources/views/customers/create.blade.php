@extends('layouts.app')
@section('title', 'Add Customer')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/customers.css') }}">
@endpush

@section('content')
<div class="page">

<div class="page-header">
    <div>
        <h1 class="page-title">Add Customer</h1>
        <p class="page-subtitle">Register a new customer</p>
    </div>
    <a href="{{ route('customers.index') }}" class="btn btn-outline">Back</a>
</div>

<div class="form-card" style="max-width: 640px;">
    <form method="POST" action="{{ route('customers.store') }}">
        @csrf

        <div class="form-group">
            <label class="form-label" for="name">Full Name *</label>
            <input type="text" id="name" name="name"
                   class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
                   value="{{ old('name') }}"
                   placeholder="e.g. Jane Wanjiku"
                   required autofocus>
            @error('name')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label class="form-label" for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone"
                       class="form-control {{ $errors->has('phone') ? 'is-invalid' : '' }}"
                       value="{{ old('phone') }}"
                       placeholder="07XX XXX XXX">
                @error('phone')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" id="email" name="email"
                       class="form-control {{ $errors->has('email') ? 'is-invalid' : '' }}"
                       value="{{ old('email') }}"
                       placeholder="jane@example.com">
                @error('email')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="address">Address</label>
            <input type="text" id="address" name="address"
                   class="form-control"
                   value="{{ old('address') }}"
                   placeholder="e.g. Westlands, Nairobi">
        </div>

        @php $__tags = \App\Models\CustomerTag::forBusiness()->orderBy('name')->get(); @endphp
        @if($__tags->isNotEmpty())
        <div class="form-group">
            <label class="form-label">Tags</label>
            <input type="hidden" name="tags_submitted" value="1">
            @php $__chosen = collect(old('tag_ids', []))->map(fn ($i) => (int) $i)->all(); @endphp
            <div style="display:flex;flex-wrap:wrap;gap:10px 16px;">
                @foreach($__tags as $__t)
                    <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;">
                        <input type="checkbox" name="tag_ids[]" value="{{ $__t->id }}" {{ in_array($__t->id, $__chosen, true) ? 'checked' : '' }}>
                        <span style="padding:2px 10px;border-radius:999px;background:{{ $__t->color }};color:#fff;font-size:0.8rem;">{{ $__t->name }}</span>
                    </label>
                @endforeach
            </div>
            <small style="color:var(--text-muted);">Tags let you send a campaign to a group of customers.</small>
        </div>
        @endif

        <div class="form-group">
            <label class="form-label" for="notes">Notes</label>
            <textarea id="notes" name="notes"
                      class="form-control"
                      rows="2"
                      placeholder="Any additional notes about this customer">{{ old('notes') }}</textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Customer</button>
            <a href="{{ route('customers.index') }}" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

</div>{{-- end .page --}}
@endsection
