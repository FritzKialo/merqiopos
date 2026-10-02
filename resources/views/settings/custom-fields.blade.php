@extends('layouts.app')
@section('title', 'Custom Fields')

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
    <button class="btn btn-primary" onclick="document.getElementById('new-field-form').style.display='block'">Add Field</button>
</div>

<div class="settings-layout">

    @include('settings._nav')

    <div>

        <div id="new-field-form" style="display:none; margin-bottom: var(--space-5);" class="settings-card">
            <div class="settings-card-header">
                <h2>New Custom Field</h2>
            </div>
            <div class="settings-card-body">
                <form method="POST" action="{{ route('settings.custom-fields.store') }}">
                    @csrf
                    <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap: var(--space-3); margin-bottom: var(--space-4);">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Entity Type *</label>
                            <select name="entity_type" class="form-control" required>
                                <option value="product">Product</option>
                                <option value="customer">Customer</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Label *</label>
                            <input type="text" name="label" class="form-control" required>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Field Key *</label>
                            <input type="text" name="field_key" class="form-control" required placeholder="snake_case">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Field Type *</label>
                            <select name="field_type" class="form-control" required>
                                <option value="text">Text</option>
                                <option value="number">Number</option>
                                <option value="date">Date</option>
                                <option value="boolean">Yes/No</option>
                                <option value="select">Dropdown</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Required?</label>
                            <select name="is_required" class="form-control">
                                <option value="0">No</option>
                                <option value="1">Yes</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" id="options-row" style="display:none;">
                        <label class="form-label">Dropdown Options (one per line)</label>
                        <textarea name="options_text" class="form-control" rows="3" placeholder="Option A&#10;Option B&#10;Option C"></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Field</button>
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('new-field-form').style.display='none'">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="settings-grid-2" style="display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap: var(--space-5);">
            @foreach([['product','Product Fields',$productFields],['customer','Customer Fields',$customerFields]] as [$type,$label,$fields])
            <div class="settings-card">
                <div class="settings-card-header">
                    <h2>{{ $label }}</h2>
                </div>
                <div class="settings-card-body">
                    @if($fields->isEmpty())
                        <p style="color: var(--color-text-muted); font-size:0.9rem;">No custom fields defined.</p>
                    @else
                        @foreach($fields as $field)
                        <div style="display:flex; align-items:center; gap: var(--space-2); padding:10px 0; border-bottom:1px solid var(--color-border);">
                            <form method="POST" action="{{ route('settings.custom-fields.update', $field) }}" style="display:flex; align-items:center; gap: var(--space-2); flex:1;">
                                @csrf @method('PUT')
                                <div style="flex:1;">
                                    <input type="text" name="label" value="{{ $field->label }}" class="form-control" required style="font-size:0.9rem;">
                                    <div style="font-size:0.75rem; color: var(--color-text-muted); margin-top:2px;">{{ $field->field_key }} · {{ $field->field_type }}</div>
                                </div>
                                <label style="display:inline-flex; align-items:center; gap:4px; font-size:0.8rem; white-space:nowrap;">
                                    <input type="checkbox" name="is_required" value="1" {{ $field->is_required ? 'checked' : '' }}> required
                                </label>
                                <button type="submit" class="btn btn-secondary btn-sm">Save</button>
                            </form>
                            <form method="POST" action="{{ route('settings.custom-fields.destroy', $field) }}" onsubmit="return confirm('Delete this field?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </div>
                        @endforeach
                    @endif
                </div>
            </div>
            @endforeach
        </div>

    </div>
</div>
</div>{{-- end .page --}}
@endsection
