@extends('layouts.app')
@section('title', 'Tax Rules')

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

        <div class="table-section" style="margin-bottom:1.5rem;">
            <div class="report-section-header" style="margin-bottom:1rem;">
                <h2>Add Tax Rule</h2>
            </div>
            <form method="POST" action="{{ route('settings.tax-rules.store') }}"
                  style="display:flex; gap:0.75rem; flex-wrap:wrap; align-items:flex-end;">
                @csrf
                <div>
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. VAT" value="{{ old('name') }}" style="width:180px;" required>
                </div>
                <div>
                    <label class="form-label">Code</label>
                    <input type="text" name="code" class="form-control" placeholder="e.g. VAT" value="{{ old('code') }}" style="width:100px;text-transform:uppercase;" required>
                </div>
                <div>
                    <label class="form-label">Rate (%)</label>
                    <input type="number" name="rate" class="form-control" min="0" max="100" step="0.01" value="{{ old('rate') }}" style="width:110px;" required>
                </div>
                <div>
                    <label class="form-label">Applies To</label>
                    <select name="tax_category" class="form-control" style="width:150px;">
                        <option value="standard">Standard</option>
                        <option value="reduced">Reduced</option>
                        <option value="zero_rated">Zero-rated</option>
                        <option value="exempt">Exempt</option>
                    </select>
                </div>
                <label style="display:flex; align-items:center; gap:6px; padding-bottom:8px; font-size:0.9rem;">
                    <input type="checkbox" name="enabled" value="1" checked> Enabled
                </label>
                <button type="submit" class="btn btn--primary">Add</button>
            </form>
            <p style="margin-top:0.75rem; font-size:0.8rem; color:var(--color-text-muted);">
                "Applies To" matches a product's Tax Category (set on each product in Inventory — defaults to Standard).
                A sale's tax is the sum of every enabled rule matching each item's category.
                Rules are always treated as tax-inclusive (already baked into the item price) in this version —
                compounding/exclusive add-on charges (e.g. Service Charge) aren't calculated yet even though you can
                create the rule now.
            </p>
        </div>

        <div class="table-section">
            <div class="report-section-header" style="margin-bottom:1rem;">
                <h2>Tax Rules</h2>
            </div>

            @if($taxRules->isEmpty())
                <div class="empty-state">
                    <h3>No tax rules yet.</h3>
                    <p>Without any rules here, sales keep using your flat <a href="{{ route('settings.vat') }}">VAT Settings</a> rate — nothing changes until you add one.</p>
                </div>
            @else
                @foreach($taxRules as $rule)
                <div style="display:flex; gap:0.75rem; flex-wrap:wrap; align-items:flex-end;
                            padding:0.85rem 0; border-bottom:1px solid var(--color-border);">
                    <form method="POST" action="{{ route('settings.tax-rules.update', $rule) }}"
                          style="display:flex; gap:0.75rem; flex-wrap:wrap; align-items:flex-end;">
                        @csrf @method('PUT')
                        <div>
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" value="{{ $rule->name }}" required style="width:180px;">
                        </div>
                        <div>
                            <label class="form-label">Code</label>
                            <input type="text" name="code" class="form-control" value="{{ $rule->code }}" required style="width:100px;text-transform:uppercase;">
                        </div>
                        <div>
                            <label class="form-label">Rate (%)</label>
                            <input type="number" name="rate" class="form-control" min="0" max="100" step="0.01" value="{{ $rule->rate }}" required style="width:110px;">
                        </div>
                        <div>
                            <label class="form-label">Applies To</label>
                            <select name="tax_category" class="form-control" style="width:150px;">
                                <option value="standard" {{ $rule->tax_category === 'standard' ? 'selected' : '' }}>Standard</option>
                                <option value="reduced" {{ $rule->tax_category === 'reduced' ? 'selected' : '' }}>Reduced</option>
                                <option value="zero_rated" {{ $rule->tax_category === 'zero_rated' ? 'selected' : '' }}>Zero-rated</option>
                                <option value="exempt" {{ $rule->tax_category === 'exempt' ? 'selected' : '' }}>Exempt</option>
                            </select>
                        </div>
                        <label style="display:flex; align-items:center; gap:6px; padding-bottom:8px; font-size:0.9rem;">
                            <input type="checkbox" name="enabled" value="1" {{ $rule->enabled ? 'checked' : '' }}> Enabled
                        </label>
                        <button type="submit" class="btn btn--secondary">Save</button>
                    </form>
                    <form method="POST" action="{{ route('settings.tax-rules.destroy', $rule) }}"
                          onsubmit="return confirm('Delete &ldquo;{{ addslashes($rule->name) }}&rdquo;?')">
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
